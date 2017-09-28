<?php

	/*
		Standard Jul-based mysql class 
		
		Changelog:
		rev0 - original mysql_ version; as present in Xkeeper0/jul
		rev1 - PDO conversion
		rev2 - minor bug fixes; adds special case for prepares
		rev3 - fixes several glaring issues with hash handling; adds uniform logging and base for automatic rollback
		rev4 - automatic rollback completed; removal of manual querycheck passing; disables extra functions in favour of PDO fetch flags
	*/
	class mysql {
		// a 'backport' of my 'static' class in not-as-static form
		// the statistics remain static so they're global just in case this gets used for >1 connection
		public static $queries   = 0;
		public static $cachehits = 0;
		public static $rowsf     = 0;
		public static $rowst     = 0;
		public static $time      = 0;

		// Query debugging functions for admins
		public static $connection_count = 0;
		public static $debug_on   = false;
		public static $debug_list = array(); // [<id>, <function>, <file:line>, <info>, <time taken>, <prepared>]

		// Constant messages for our sanity
		const MSG_NONE     = 0;
		const MSG_QUERY    = 0b1;
		const MSG_PREPARED = 0b10;
		const MSG_EXECUTE  = 0b100;
		const MSG_CACHED   = 0b1000;
		const MSG_ERROR    = 0b10000;
		
		// fetchX flags
		const USE_CACHE    = 0b1;
		const FETCH_ALL    = 0b10;
		
		public  $cache      = array();
		public  $connection = NULL;
		public  $id         = 0;
		public  $error      = NULL;
		public  $fail_message = "";
		private $server_name = ""; // Marks MySQL or MariaDB
		private $in_lock     = false;

		public function connect($host, $user, $pass, $dbname, $persist = false) {
			global $config;
				
			$start = microtime(true);
			
			// Connect to the database
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
				return NULL;
			}
			
			$t 			= microtime(true) - $start;
			$this->id 	= ++self::$connection_count;
						
			if ($config['enable-sql-debugger']) {
				self::$debug_on = true;
			}
			
			// This is very important to make the stupidly long MySQL text shorter
			$this->server_name = (
				strpos($this->connection->getAttribute(PDO::ATTR_SERVER_VERSION), "MariaDB") ? 
				"MariaDB" :
				"MySQL");
			
			
			if (self::$debug_on) {
				$message = (($persist) ? "Persistent c" : "C" )."onnection established to {$this->server_name} server ($host, $user, using password: ".(($pass!=="") ? "YES" : "NO").")";
				$this->log($message, $t, self::MSG_NONE);
			}
			
			self::$time += $t;
			return $this->connection;
		}
		
		public function query($query, $hash = false) {
			$res   = NULL;
			$type  = self::MSG_QUERY;
			// Already cached the result?
			// Just update the stats then.
			if ($hash && isset($this->cache[$hash])) {
				//$start = microtime(true);
				++self::$cachehits;
				//$t = microtime(true) - $start;
				
				if (self::$debug_on) {
					$this->log($query, 0, $type | self::MSG_CACHED);
				}
			} else {
				$start = microtime(true);
				try {
					$res = $this->connection->query($query);
					++self::$queries;
					
					if (strtoupper(substr(trim($query), 0, 6)) == "SELECT")
						self::$rowst += $res->rowCount();
					
					//$querycheck[] = true;
				}
				catch (PDOException $e) {
					// the huge SQL warning text sucks
					$err = str_replace("You have an error in your SQL syntax; check the manual that corresponds to your {$this->server_name} server version for the right syntax to use", "SQL syntax error", $e->getMessage());
					trigger_error("MySQL error: {$err}", E_USER_ERROR);
					
					$this->transactionError($e, $query, $type, $err);
					//$querycheck[] = false;
				}

				$t = microtime(true) - $start;
				self::$time += $t;

				if (self::$debug_on) {
					if (isset($err)) {
						$error_flag = self::MSG_ERROR;
					} else {
						$err        = NULL;
						$error_flag = 0;
					}
					$this->log($query, $t, $type | $error_flag, $err);
				}
			}

			return $res;
		}
		
		public function prepare($query, $options = array(), $hash = NULL) {
			$res  = NULL;
			$type = self::MSG_QUERY | self::MSG_PREPARED;
			if ($hash && isset($this->cache[$hash])) { // Already cached the result?
				//$start = microtime(true);
				++self::$cachehits;
				//$t = microtime(true) - $start;
				
				if (self::$debug_on) {
					$this->log($query, 0, $type | self::MSG_CACHED);
				}
			} else {
			
				$start = microtime(true);
				
				try {
					// Prepares don't add towards the query count
					$res = $this->connection->prepare($query, $options);
				}
				catch (PDOException $e) {
					$err = str_replace("You have an error in your SQL syntax; check the manual that corresponds to your {$this->server_name} server version for the right syntax to use", "SQL syntax error", $e->getMessage());
					trigger_error("MySQL error: $err", E_USER_ERROR);
					$this->transactionError($e, $query, $type, $err);
				}

				$t = microtime(true) - $start;
				self::$time += $t;

				if (self::$debug_on) {
					if (isset($err)) {
						$error_flag = self::MSG_ERROR;
					} else {
						$err        = NULL;
						$error_flag = 0;
					}
					$this->log($query, $t, $type | $error_flag, $err);
				}
			}

			return $res;
		}
		
		public function execute($result, $vals = array()){
			$type  = self::MSG_QUERY | self::MSG_EXECUTE;
			if (!$result) {
				// This happens. And it's not pretty.
				$query = "[No query ref]";
				$err   = "Called execute method with a NULL \$result pointer.";
				trigger_error("MySQL (execute) error: {$err}", E_USER_ERROR);
				$this->transactionError($e, $query, $type, $err);
				$this->log($query, 0, $type | self::MSG_ERROR, $err);
				$res = NULL;
			} else {
				$query = $result->queryString;
				
				$start = microtime(true);
				try {
					$res = $result->execute($vals);
					
					// More uncatchable stupid shit. Not really supposed to throw PDOExceptions but who cares.
					if (!is_numeric($result->errorInfo()[0])) {
						throw new PDOException("Error code ".$result->errorInfo()[0]);
					}
					++self::$queries;
					
					if (strtoupper(substr(trim($query), 0, 6)) == "SELECT")
						self::$rowst += $result->rowCount();
				}
				catch (PDOException $e){
					$err = $e->getMessage();
					trigger_error("MySQL (execute) error: {$err}", E_USER_ERROR);
					$this->transactionError($e, $query." | Values: <i>".implode("</i>,<br/><i>", $vals)."</i>", $type, $err);
					$res = false;
				}
				
				$t = microtime(true) - $start;
				self::$time += $t;

				if (self::$debug_on) {
					if (isset($err)) {
						$error_flag = self::MSG_ERROR;
					} else {
						$err        = NULL;
						$error_flag = 0;
					}
					$query .= " | Values: <i>" . implode("</i>,<br/><i>", $vals) . "</i>";
					$this->log($query, $t, $type | $error_flag, $err);
				}
			}
			return $res;
		}

		public function fetch($result, $flag = PDO::FETCH_ASSOC, $hash = NULL){
			$start = microtime(true);
			$res   = NULL;
			
			// if it's cached we just pick the result
			if ($hash && isset($this->cache[$hash]))
				$res = $this->cache[$hash];
				
			else if ($result != false && $res = $result->fetch($flag)) { //, $reset ? PDO::FETCH_ORI_ABS : PDO::FETCH_ORI_NEXT))
				++self::$rowsf;
				if ($hash) $this->cache[$hash] = $res;
			}
			
			self::$time += microtime(true) - $start;
			return $res;
		}
		
		public function fetchAll($result, $flag = PDO::FETCH_ASSOC, $hash = NULL){
			$start = microtime(true);
			$res   = NULL;
			
			if ($hash && isset($this->cache[$hash]))
				$res = $this->cache[$hash];
			
			else if ($result != false && $res = $result->fetchAll($flag)) {
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
			
			if ($hash && isset($this->cache[$hash]))
				$res = $this->cache[$hash];
			else if($result != false && $result->rowCount() > $row) {
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
			//$querycheck[] = $result; // Pass result to query result array
			return $q;
		}
		
		public function fetchq($query, $flag = PDO::FETCH_ASSOC, $options = 0){ //$cache = false, $all = false){
			$hash = self::getQueryHash($query, $options);
			$res  = $this->query($query, $hash);
			$res  = ($options & self::FETCH_ALL) ? $this->fetchAll($res, $flag, $hash) : $this->fetch($res, $flag, $hash);
			return $res;
		}
		
		public function fetchp($query, $values = array(), $flag = PDO::FETCH_ASSOC, $options = 0){ //$cache = false, $all = false){
			$hash = self::getQueryHash($query, $options);
			$res = $this->prepare($query, array(), $hash);
			if ($hash === NULL) 
				if (!($this->execute($res, $values)))
					return false;
			
			$res = ($options & self::FETCH_ALL) ? $this->fetchAll($res, $flag, $hash) : $this->fetch($res, $flag, $hash);
			return $res;
		}
		
		public function resultq($query, $row=0, $col=0, $options = 0){
			$hash = self::getQueryHash($query, $options);
			$res = $this->query($query, $hash);
			$res = $this->result($res, $row, $col, $hash);
			return $res;
		}
		
		public function resultp($query, $values = array(), $row=0, $col=0, $options = 0){
			$hash = self::getQueryHash($query, $options);
			$res = $this->prepare($query, array(), $hash);
			if ($hash === NULL) 
				if (!($this->execute($res, $values)))
					return false;
			
			$res = $this->result($res, $row, $col, $hash);
			return $res;
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
		
		public function lock_tables($locks, $read = false) {
			if ($this->in_lock === false) {
				$this->connection->exec("LOCK TABLES ".implode(',', $locks).($read ? " READ |" : "")." WRITE");
				$this->in_lock = true;
			}
		}
		
		public function unlock_tables() {
			if ($this->in_lock === true) {
				$this->connection->exec("UNLOCK TABLES");
				$this->in_lock = false;
			}
		}
		
		public function beginTransaction($locks = NULL){
			if (!$this->connection->inTransaction()) {
				$start = microtime(true);
				try {
					// Just in case lock before starting a transaction
					if ($locks !== NULL) {
						$this->lock_tables($locks);
					}
					$result = $this->connection->beginTransaction();
				}
				catch (PDOException $e){
					$this->unlock_tables();
					$err = $e->getMessage();
					trigger_error("Could not begin transaction: $err", E_USER_ERROR);
					$result = NULL;
				}
					
				self::$time += microtime(true) - $start;
			} else {
				$result = true; // We *are* in a transaction, so might as well return true
			}
			return $result;
		}
		
		public function commit(){
			if ($this->connection->inTransaction()) {
				$start = microtime(true);
				try{
					$result = $this->connection->commit();
				}
				catch (PDOException $e){
					$err = $e->getMessage();
					trigger_error("Could not commit transaction: $err", E_USER_ERROR);
					$result = NULL;
				}
				$this->unlock_tables();
				$this->fail_message = "";
				self::$time += microtime(true) - $start;
			} else {
				$result = false;
			}
			return $result;
		}
		
		/*
		// Takes an array with the result of the queries as argument
		// Typically it's the same array passed as third argument to $sql->query and $sql->queryp
		// Here, that array is checked so that the transaction will be committed only if all the queries were successful
		public function checkTransaction($list){
			foreach ($list as $queryres) {
				if ($queryres === false){
					$this->rollBack();
					return false;
				}
			}
			$this->commit();
			return true;
		}*/
		
		public function rollBack(){
			if ($this->connection->inTransaction()) {
				$start = microtime(true);
				try{
					$result = $this->connection->rollBack(); //false on failure
				}
				catch (PDOException $e){
					$err = $e->getMessage();
					trigger_error("Could not rollback transaction: $err", E_USER_ERROR);
					$result = NULL;
				}
				$this->unlock_tables();
				$this->fail_message = "";
				self::$time += microtime(true) - $start;
			} else {
				$result = false;
			}
			return $result;
		}
		
		// Debugging shit for admins
		public static function debugprinter() {
			if (!self::$debug_on) return "";
			
			$out = "<br>
				<table class='table'>
					<tr>
						<td class='tdbgh center b' colspan=5>
							SQL Debug
						</td>
					</tr>
					<tr>
						<td class='tdbgh center' width=20 >&nbsp</td>
						<td class='tdbgh center' width=20 >ID</td>
						<td class='tdbgh center' width=300>Function</td>
						<td class='tdbgh center' width=*  >Query</td>
						<td class='tdbgh center' width=90 >Time</td>
					</tr>";
			$oldid  = NULL;
			$offset = 0;
			foreach(self::$debug_list as $i => $d) {
				// Cycling tccell1/2
				$cell = (($i & 1)+1);
				
				// Is the connection ID not identical to the previous?
				if ($oldid != $d[0]) {
					// If so, add a separator
					$out .= "
						<tr>
							<td class='tdbgc center' style='height: 4px' colspan=5>
							</td>
						</tr>";
				}
				$oldid = $d[0];
				if (isset($d[5])) {
					// Prepared queries don't count towards the query count...
					$c = "[P]";
					$offset++;
				} else {
					$c = $i - $offset; //... and as such we need to take that in consideration
				}
				$out .= "
					<tr>
						<td class='tdbg{$cell} center'>{$c}</td>
						<td class='tdbg{$cell} center'>{$d[0]}</td>
						<td class='tdbg{$cell} center'>
							{$d[1]}<span class='fonts'><br>
							{$d[2]}</span>
						</td>
						<td class='tdbg{$cell}'>".str_replace("\t", "", trim($d[3]))."</td>
						<td class='tdbg{$cell} center'>{$d[4]}</td>
					</tr>";
			}
			$out .= "</table>";
			return $out;
		}
		
		private static function getbacktrace() {
			$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			
			// Loop until we have found the real location of the query
			for ($i = 1; strpos($backtrace[$i]['file'], "mysql.php"); ++$i);
			
			// And check in what function it comes from
			$backtrace[$i]['pfunc'] = (isset($backtrace[$i+1]) ? $backtrace[$i+1]['function'] : "<i>(main)</i>");
			$backtrace[$i]['file']  = str_replace($_SERVER['DOCUMENT_ROOT'], "", $backtrace[$i]['file']);
			
			return $backtrace[$i];
		}
		
		public static function setplaceholders() {
			$out = "";
			$fields = func_get_args();
			$i = false;
			foreach ($fields as $field) {
				$out .= ($i ? "," : "")."$field=:".str_replace("`","",$field);
				$i = true;
			}
			return $out;
		}
		
		private function log($msg, $time, $msg_type, $error_text = "") {
			$time_txt = sprintf("%01.6fs", $time);
			if (!$msg_type) {
				$msg = "<i>{$msg}</i>";
			} else if ($msg_type & self::MSG_QUERY) {
				$msg = htmlentities($msg);
				
				if ($msg_type & self::MSG_ERROR) {
					$color = "FF0000";
					$title = $error_text;
				} else if ($msg_type & self::MSG_CACHED) {
					$color = "00dd00";
				} else if ($msg_type & self::MSG_PREPARED) {
					$color = "ffff44";
				} else if ($msg_type & self::MSG_EXECUTE) {
					$color = "ffcc44";
				}
				
				if (isset($color)) {
					$msg = "<span style='color:#{$color}".(isset($title) ? ";border-bottom:1px dotted {$color}' title=\"{$error_text}\"" : "'").">{$msg}</span>";
					$time_txt = "<span style='color:#{$color}'>{$time_txt}</span>";
				}
			}
			
			$b = self::getbacktrace();
			self::$debug_list[] = array(
				$this->id,
				$b['pfunc'],
				$b['file'] . ":" . $b['line'],
				$msg,
				$time_txt,
				($msg_type & self::MSG_PREPARED ? true : NULL)
			);
		}
		
		private function transactionError($err, $query, $msg_type, $error_text = "Unknown") {
			if ($this->connection->inTransaction()) {
				global $config;
				// An error occurred in one of the queries in a transaction.
				// (try to) rollback everything and stop the script
				$res = $this->rollBack();
				
				if (self::$debug_on) {
					$this->log($query, 0, $msg_type | self::MSG_ERROR, $error_text);
				}
				/*
				$title = "error while executing query '<i>{$query}</i>' in transaction";
				$message = $error_text."<br>".
							"<span style='color:#fff'>".
								($this->fail_message ? "Fail message: ".$this->fail_message : "")."<br>".
								"<br>".
								"The transaction <span style='color:#".($res ? "0F0'>has been" : "F00'>could <b>not</b> be")."</span> rolled back.".
							"</span>";
				
				$b = self::getbacktrace();
				throw new mysqlException($err, $message, $b['file'], $b['line'], $title);*/
				
				// Hide everything else in the page
				echo "<div style='position: fixed; left: 0px; top: 0px; width: 100%; height: 100vh; background: #000; padding: 20px'>";
				if (has_perm('view-debugger') || $config['always-show-debug']) {
					$b = self::getbacktrace();
					fatal_error("error while executing query '<i>{$query}</i>' in transaction", $error_text."<br><span style='color:#fff'>".($this->fail_message ? "Fail message: ".$this->fail_message : "")."<br>\n<br>\nThe transaction <span style='color:#".($res ? "0F0'>has been" : "F00'>could <b>not</b> be")."</span> rolled back.</span>", $b['file'], $b['line']);
				} else {
					dialog("This webpage has stopped working.<br><br>Please return to the <a href='index.php'>index</a> page.");
				}
				
			}
		}
		
		private static function getQueryHash($query, $flags = self::USE_CACHE) {
			return ($flags & self::USE_CACHE) ? md5($query) : NULL;
		}
	}
	
	// Cannot preserve file/line data otherwise
	class mysqlException extends Exception {
		protected $file = "";
		protected $line = "";
		protected $title = "";
		public function __construct($ex, $message, $file, $line, $title) {
			parent::__construct($message);
			$this->file = $file;
			$this->line = $line;
			$this->title = $title;
		}
		/*
		public function getMessage() {
			return parent::getMessage($message);
		}*/
		public function getQueryFile()  { return $this->file;  }
		public function getQueryLine()  { return $this->line;  }
		public function getTitle() { return $this->title; }
	}		