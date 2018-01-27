<?php
class mysql {
	// a 'backport' of my 'static' class in not-as-static form
	// the statistics remain static so they're global just in case this gets used for >1 connection
	static $queries   = 0;
	static $cachehits = 0;
	static $rowsf     = 0;
	static $rowst     = 0;
	static $time      = 0;

	// Query debugging functions for admins
	static $connection_count = 0;
	static $debug_on   = false;
	static $debug_list = array();

	public $cache = array();
	public $connection = NULL;
	public $id = 0;
	public $error = NULL;
	
	private $server_name = "Unknown";
	private $in_lock     = false;
	
	// Debug log types
	const MSG_NONE     = 0; // Informative message
	const MSG_QUERY    = 0b1; // Standard query
	const MSG_PREPARED = 0b10; // Prepared statement
	const MSG_EXECUTE  = 0b100; // Execution of prepared statement
	const MSG_CACHED   = 0b1000; // Query result fetched from cache
	const MSG_TRANSCHG = 0b10000; // Transaction flag change
	
	// Fetch flags
	const USE_CACHE    = 0b1;
	const FETCH_ALL    = 0b10;

	public function connect($host, $user, $pass, $dbname, $persist = false) {
		global $config;
			
		$start = microtime(true);
		
		$dsn = "mysql:dbname=$dbname;host=$host;charset=utf8mb4";
		$opt = array(
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
			PDO::ATTR_PERSISTENT         => $persist
		);
		try {
			$this->connection = new pdo($dsn, $user, $pass, $opt);
		}
		catch (PDOException $x) {
			$this->error = $x->getMessage();
			$this->connection = NULL;
		}
		
		$t 			= microtime(true) - $start;
		$this->id 	= ++self::$connection_count;
		
					
		if ($config['enable-sql-debugger']) {
			self::$debug_on = true;
		}
		
		// Need to distinguish between the two because of the error text
		$this->server_name = (
			strpos($this->connection->getAttribute(PDO::ATTR_SERVER_VERSION), "MariaDB")
			? "MariaDB"
			: "MySQL");
		
		if (self::$debug_on) {
			$message = (($persist) ? "Persistent c" : "C" )."onnection established to {$this->server_name} server ($host, $user, using password: ".(($pass!=="") ? "YES" : "NO").")";
			$this->log($message, $t, self::MSG_NONE);
		}

		self::$time += $t;
		return $this->connection;
	}
	
	// $usecache contains hash
	public function query($query, $hash = false) {
		$type  = self::MSG_QUERY;
		
		// If we cached the result, just add a hit to the cache hits and move on
		if ($hash && isset($this->cache[$hash])) {
			++self::$cachehits;
			if (self::$debug_on) {
				$this->log($query, 0, $type | self::MSG_CACHED);
			}
			return NULL;
		}
		
		
		$start = microtime(true);
		
		$res = NULL;
		try {
			$res = $this->connection->query($query);
			++self::$queries;
			
			// If the query was a SELECT, add the returned rows to the counter
			if (strtoupper(substr(trim($query), 0, 6)) == "SELECT")
				self::$rowst += $res->rowCount();
		}
		catch (PDOException $e) {
			// the huge SQL warning text sucks
			$err = str_replace("You have an error in your SQL syntax; check the manual that corresponds to your {$this->server_name} server version for the right syntax to use", "SQL syntax error", $e->getMessage());
			trigger_error("MySQL error: $err", E_USER_NOTICE);
			$this->transactionError($e, $query, $type, $err);
		}

		$t = microtime(true) - $start;
		self::$time += $t;

		if (self::$debug_on) {
			$this->log($query, $t, $type, $err);
		}

		return $res;
	}
	
	public function prepare($query, $options = array(), $hash = NULL) {
		$type = self::MSG_QUERY | self::MSG_PREPARED;
		// If we cached the result, just add a hit to the cache hits and move on
		if ($hash && isset($this->cache[$hash])) {
			++self::$cachehits;
			if (self::$debug_on) {
				$this->log($query, 0, $type | self::MSG_CACHED);
			}
			return NULL;
		}
		
		$start = microtime(true);
		$res = NULL;
		try {
			$res = $this->connection->prepare($query, $options);
		}
		catch (PDOException $e) {
			$err = str_replace("You have an error in your SQL syntax; check the manual that corresponds to your {$this->server_name} server version for the right syntax to use", "SQL syntax error", $e->getMessage());
			trigger_error("MySQL error: $err", E_USER_NOTICE);
			$this->transactionError($e, $query, $type, $err);
		}

		$t = microtime(true) - $start;
		self::$time += $t;

		if (self::$debug_on) {
			$this->log($query, $t, $type, $err);
		}

		return $res;
	}
	
	public function execute($result, $vals = array()){
		$type  = self::MSG_QUERY | self::MSG_EXECUTE;
		
		// This is to prevent an uncatchable fatal error.
		if (!$result) {
			$query = "[No query ref]";
			$err   = "Called execute method with a NULL \$result pointer.";
			trigger_error("MySQL (execute) error: {$err}", E_USER_NOTICE);
			$this->log($query, 0, $type, $err);
			return NULL;
		}
		
		$query = $result->queryString;
		$start = microtime(true);
		try {
			$res = $result->execute($vals);
			
			if (!is_numeric($result->errorInfo()[0])) {
				// The execute() failed but somehow didn't throw an exception
				// We'll do that manually I guess
				throw new PDOException("Error code ".$result->errorInfo()[0]);
			}
			
			++self::$queries;
			// If the query was a SELECT, add the returned rows to the counter
			if (strtoupper(substr(trim($query), 0, 6)) == "SELECT")
				self::$rowst += $result->rowCount();
		}
		catch (PDOException $e){
			$err = $e->getMessage();
			trigger_error("MySQL (execute) error: $err", E_USER_NOTICE);
			$this->transactionError($e, $query." | Values: '".implode("','", $vals)."'", $type, $err);
			$res = false;
		}
		
		$t = microtime(true) - $start;
		self::$time += $t;

		if (self::$debug_on) {
			$query .= " | Values: '" . implode("','", $vals) . "'";
			$this->log($query, $t, $type, $err);
		}
		
		return $res;
	}

	public function fetch($result, $flag = PDO::FETCH_ASSOC, $hash = NULL){
		$start = microtime(true);
		$res   = NULL;
		
		// If the result was cached, grab the result instead of attempting to fetch from a dummy pointer
		if ($hash && isset($this->cache[$hash])) {
			$res = $this->cache[$hash];
		} else if ($result != false && $res = $result->fetch($flag)) { //, $reset ? PDO::FETCH_ORI_ABS : PDO::FETCH_ORI_NEXT))
			++self::$rowsf;
			if ($hash) $this->cache[$hash] = $res;
		}
		
		self::$time += microtime(true) - $start;
		return $res;
	}
	
	public function fetchAll($result, $flag = PDO::FETCH_ASSOC, $hash = NULL){
		$start = microtime(true);
		$res   = NULL;
		
		if ($hash && isset($this->cache[$hash])) {
			$res = $this->cache[$hash];
		} else if ($result != false && $res = $result->fetchAll($flag)) {
			++self::$rowsf;
			if ($hash) $this->cache[$hash] = $res;
		}
		
		self::$time += microtime(true) - $start;
		return $res;
	}

	public function result($result, $row=0, $col=0, $hash = NULL){
		$start = microtime(true);
		$res   = NULL;
		
		if ($row) {
			trigger_error("Deprecated: passed \$row > 0", E_USER_NOTICE);
		}
		
		if ($hash && isset($this->cache[$hash])) {
			$res = $this->cache[$hash];
		} else if ($result != false && $result->rowCount() > $row) {
			$res = $result->fetchColumn($col);
			++self::$rowsf;
			if ($hash) $this->cache[$hash] = $res;
		} else {
			$res = NULL;
		}

		self::$time += microtime(true) - $start;
		return $res;
	}

	public function queryp($query, $values = array()) {
		$q = $this->prepare($query);
		$result = $this->execute($q, $values);
		return $q;
	}
	
	public function fetchq($query, $flag = PDO::FETCH_ASSOC, $options = 0) {
		$hash = self::getqueryhash($query, $options);
		$res = $this->query($query, $hash);
		$res = ($options & self::FETCH_ALL) ? $this->fetchAll($res, $flag, $hash) : $this->fetch($res, $flag, $hash);
		return $res;
	}
	
	public function fetchp($query, $values = array(), $flag = PDO::FETCH_ASSOC, $options = 0) {
		$hash = self::getQueryHash($query, $options);
		$res = $this->prepare($query, array(), $hash);
		if ($hash === NULL) 
			if (!($this->execute($res, $values)))
				return false;
		
		$res = ($options & self::FETCH_ALL) ? $this->fetchAll($res, $flag, $hash) : $this->fetch($res, $flag, $hash);
		return $res;
	}
	
	public function resultq($query, $row=0, $col=0, $options = 0){
		$hash = self::getqueryhash($query, $options);
		$res = $this->query($query, $hash);
		$res = $this->result($res, $row, $col, $hash);
		return $res;
	}
	
	public function resultp($query, $values = array(), $row=0, $col=0, $options = 0){
		$hash = self::getqueryhash($query, $options);
		$res = $this->prepare($query, array(), $hash);
		if ($hash === NULL) 
			if (!($this->execute($res, $values)))
				return false;
		
		$res = $this->result($res, $row, $col, $hash);
		return $res;
	}
	
	// Not used?
	public function getmultiresults($query, $key, $wanted='', $options = 0) {
		$hash = self::getqueryhash($query, $options);
		// $tmp[<keyval>] = <serialized array>
		$q = $this->query($query, $hash);
		
		if ($hash && isset($this->cache[$hash]))
			return $this->cache[$hash];
		
		$tmp = $this->fetchAll($q, PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
		foreach ($tmp as $keys => $values)
			$ret[$keys] = implode(",", $values);
			
		if ($hash)
			$this->cache[$hash] = $ret;
		return $ret;
	}
	
	public function getresultsbykey($query, $key='', $wanted='', $options = 0) {
		$hash = self::getqueryhash($query, $options);
		$q = $this->query($query, $hash);
		$ret = $this->fetchAll($q, PDO::FETCH_KEY_PAIR, $hash);
		return $ret;
	}
	
	public function getresults($query, $wanted='', $options = 0) {
		$hash = self::getqueryhash($query, $options);
		$q = $this->query($query, $hash);
		$ret = $this->fetchAll($q, PDO::FETCH_COLUMN, $hash);
		return $ret;
	}
	
	public function getarraybykey($query, $key, $options = 0) {
		$hash = self::getqueryhash($query, $options);
		// $tmp[<keyval>] = <all values>
		$q = $this->query($query, $hash);
		
		if ($hash && isset($this->cache[$hash]))
			return $this->cache[$hash];

		$ret = array();
		while ($res = $this->fetch($q, PDO::FETCH_ASSOC))
			$ret[$res[$key]] = $res;
		
		if ($hash)
			$this->cache[$hash] = $ret;
		
		return $ret;
	}

	public function getarray($query, $options = 0) {
		$hash = self::getqueryhash($query, $options);
		// $ret[<num>] = <entire assoc row>
		$q = $this->query($query, $hash);
		$ret = $this->fetchAll($q, PDO::FETCH_ASSOC, $hash);
		return $ret;
	}

	public function escape($s) {
		return $this->connection->quote($s);
	}
	
	public function num_rows($res) {
		if ($res === NULL || is_bool($res)) return NULL;
		return $res->rowCount();
	}
	
	public function insert_id() {
		return $this->connection->lastInsertId();
	}
	
	// Optional lock of table writes; can be set automatically when starting a transaction
	public function lock_tables($locks, $read = false) {
		if ($this->in_lock === false) {
			$type = ($read ? "READ | " : "" )."WRITE";
			$this->connection->exec("LOCK TABLES ".implode(" {$type}, ", $locks)." {$type}");
			$this->in_lock = true;
		}
	}
	
	public function unlock_tables() {
		if ($this->in_lock === true) {
			$this->connection->exec("UNLOCK TABLES");
			$this->in_lock = false;
		}
	}
	// in a transaction, a query failing automatically rolls back
	public function beginTransaction($locks = NULL){
		// If we already are in a transaction, might as well ignore it
		if ($this->connection->inTransaction()) {
			trigger_error("Attempted to start a nested transaction.", E_USER_NOTICE);
			return true;
		}
		
		$start = microtime(true);
		try {
			// Lock the specified tables, if any
			if ($locks !== NULL) {
				$this->lock_tables($locks);
			}
			$result = $this->connection->beginTransaction();
		}
		catch (PDOException $e){
			$this->unlock_tables(); // Unlock all the tables (if any)
			$err = $e->getMessage();
			trigger_error("Could not begin transaction: $err", E_USER_NOTICE);
			$result = NULL;
		}
		$t = microtime(true) - $start;
		self::$time += $t;
		
		if (self::$debug_on) {
			$lock_msg = ($locks !== NULL) 
				? " with locks to (".implode(', ', $locks).")"
				: ""; //" (no locks)";
			$this->log("Begin Transaction{$lock_msg}", $t, self::MSG_TRANSCHG, $err);
		}
		
		return $result;
	}
	
	public function commit(){
		if (!$this->connection->inTransaction()) {
			trigger_error("Attempted to commit while not in a transaction.", E_USER_NOTICE);
			return false;
		}
		
		$start = microtime(true);
		try{
			$result = $this->connection->commit();
		}
		catch (PDOException $e){
			$err = $e->getMessage();
			trigger_error("Could not commit transaction: $err", E_USER_NOTICE);
			$result = NULL;
		}
		$this->unlock_tables();
		$t = microtime(true) - $start;
		self::$time += $t;

		if (self::$debug_on) {
			$this->log("[Commit] End of transaction", $t, self::MSG_TRANSCHG, $err);
		}
		
		return $result;
	}
	
	public function checkTransaction($list){
		trigger_error("Deprecated: Used obsolete checkTransaction()", E_USER_NOTICE);
		$this->commit();
		return true;
	}
	
	public function rollBack(){
		if (!$this->connection->inTransaction()) {
			trigger_error("Attempted to rollback while not in a transaction.", E_USER_NOTICE);
			return false;
		}
		
		$start = microtime(true);
		try{
			$result = $this->connection->rollBack();
		}
		catch (PDOException $e){
			$err = $e->getMessage();
			trigger_error("Could not rollback transaction: $err", E_USER_NOTICE);
			$result = NULL;
		}
		$this->unlock_tables();
		$t = microtime(true) - $start;
		self::$time += $t;

		if (self::$debug_on) {
			$this->log("[Rollback] End of transaction", $t, self::MSG_TRANSCHG, $err);
		}
		return $result;
	}
	
	private function transactionError($err, $query, $msg_type, $error_text = "Unknown") {
		if ($this->connection->inTransaction()) {
			global $config, $sysadmin;
			// An error occurred in one of the queries in a transaction.
			// Try to rollback everything and then stop the script
			$res = $this->rollBack();
			
			if (self::$debug_on) {
				$this->log($query, 0, $msg_type, $error_text);
			}
			
			$b = self::getbacktrace();
			if ($sysadmin || $config['always-show-debug']) {
				fatal_error(
					"error while executing query '<b>{$query}</b>' in transaction",
					"{$error_text}<br><span style='color:#fff'><br><br>The transaction <span style='color:#".($res ? "0F0'>has been" : "F00'>could <b>not</b> be")."</span> rolled back.</span>",
					$b['file'], 
					$b['line']
				);
			} else {
				dialog(
					"<img src='images/sqlerror.png'><br><br>".
					"An SQL error happened.<br><br>".
					//"You should probably report this to the bug tracker.<br><br>".
					"Please return to the <a href='index.php'>index</a> page if it's available.",
					"Ohs no");
			}
			
			die;
		}
	}

	// Add an entry to the log table
	private function log($msg, $time, $msg_type, &$error_text = NULL) {
		$b = self::getbacktrace();
		self::$debug_list[] = array(
			$this->id, // Connection ID
			$b['pfunc'], // Parent function
			$b['file'] . ":" . $b['line'], // Line strike
			htmlentities(str_replace("\t", "", trim($msg))), // Message contents (usually a query)
			sprintf("%01.6fs", $time), // Time taken
			($msg_type & self::MSG_PREPARED || $msg_type & self::MSG_TRANSCHG || !$msg_type), // No increment flag
			$msg_type, // Message type (including error type)
			$error_text, // Errors
		);
	}
	
	public static function setplaceholders($arraySet) {
		$out = "";
		
		if (is_array($arraySet)) {
			// Parameters passed via array
			$fields = array_keys($arraySet);
		} else {
			// Parameters passed via different arguments
			$fields = func_get_args();
		}
		$i = false;
		foreach ($fields as $field) {
			$out .= ($i === true ? "," : "")."$field=:".str_replace("`","",$field);
			$i = true;
		}
		
		return $out;
	}

	private static function getbacktrace() {
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		
		// Loop until we have found the real location of the query
		for ($i = 1; strpos($backtrace[$i]['file'], "mysql.php"); ++$i);
		
		
		// And check in what function it comes from
		$backtrace[$i]['pfunc'] = (isset($backtrace[$i+1]) ? $backtrace[$i+1]['function'] : "<i>(main)</i>");
		$backtrace[$i]['file']  = strip_doc_root($backtrace[$i]['file']);
		
		return $backtrace[$i];
	}
	
	private static function getqueryhash($query, $cache = true) {
		return ($cache) ? md5($query) : NULL;
	}
}