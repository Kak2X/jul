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
		static $debug_list = array(); // [<id>, <function>, <file:line>, <info>, <time taken>]

		var $cache = array();
		var $connection = NULL;
		var $id = 0;
		var $error = NULL;

		public function connect($host, $user, $pass, $dbname, $persist = false) {
			global $config;
				
			$start = microtime(true);
			
			$dsn = "mysql:dbname=$dbname;host=$host;charset=utf8";
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
			//$this->set_character_encoding("utf8");
			
						
			if ($config['enable-sql-debugger']) {
				self::$debug_on = true;
			}
			
			if (self::$debug_on) {
				$b = self::getbacktrace();
				self::$debug_list[] = array(
					$this->id,
					$b['pfunc'],
					$b['file'] . ":" . $b['line'],
					"<i>" . (($persist) ? "Persistent c" : "C" ) . "onnection established to mySQL server ($host, $user, using password: ". (($pass!=="") ? "YES" : "NO") . ")</i>",
					sprintf("%01.6fs", $t)
				);
			}

			self::$time += $t;
			return $this->connection;
		}

		/*public function selectdb($dbname)	{
			$start=microtime(true);
			$r = mysql_select_db($dbname, $this->connection);
			self::$time += microtime(true)-$start;
			return $r;
		}
		*/
		// $usecache contains hash
		public function query($query, $hash = false, &$querycheck = array()) {
			
			// Already cached the result?
			if ($hash && isset($this->cache[$hash])) {
				$start = microtime(true);
				++self::$cachehits;
				
				$t = microtime(true) - $start;
				if (self::$debug_on) {
					$b = self::getbacktrace();
					self::$debug_list[] = array(
						$this->id,
						$b['pfunc'],
						$b['file'] . ":" . $b['line'],
						"<font color=#00dd00>" . htmlentities($query) . "</font>",
						"<font color=#00dd00>" . sprintf("%01.6fs", $t) . "</font>"
					);
				}
				return NULL; // We don't need to return anything
			}
			
			
			$start = microtime(true);
			
			$res = NULL;
			try {
				$res = $this->connection->query($query);
				++self::$queries;
				
				//$type = strtoupper(substr(trim($query), 0, 6));
				if (strtoupper(substr(trim($query), 0, 6)) == "SELECT")
					self::$rowst += $res->rowCount();
				
				$querycheck[] = true;
			}
			catch (PDOException $e) {
				$err = $e->getMessage();
				// the huge SQL warning text sucks
				$err = str_replace("You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use", "SQL syntax error", $err);
				trigger_error("MySQL error: $err", E_USER_ERROR);
				$querycheck[] = false;
			}

			$t = microtime(true) - $start;
			self::$time += $t;

			if (self::$debug_on) {
				$b = self::getbacktrace();
				self::$debug_list[] = array(
					$this->id,
					$b['pfunc'],
					$b['file'] . ":" . $b['line'],
					((!isset($err)) ? htmlentities($query) : "<span style='color:#FF0000;border-bottom:1px dotted red;' title=\"$err\">".htmlentities($query)."</span>"),
					sprintf("%01.6fs", $t)
				);
			}

			return $res;
		}
		
		public function prepare($query, $options = array(), $hash = NULL) {
	
			// Already cached the result?
			if ($hash && isset($this->cache[$hash])) {
				$start = microtime(true);
				++self::$cachehits;
				
				$t = microtime(true) - $start;
				if (self::$debug_on) {
					$b = self::getbacktrace();
					self::$debug_list[] = array(
						$this->id,
						$b['pfunc'],
						$b['file'] . ":" . $b['line'],
						"<font color=#00dd00>".htmlentities($query)."</font>",
						"<font color=#00dd00>" . sprintf("%01.6fs", $t) . "</font>"
					);
				}
				return NULL; // We don't need to return anything
			}
			
			$start = microtime(true);
			
			$res = NULL;
			try {
				$res = $this->connection->prepare($query, $options);
				++self::$queries;
			}
			catch (PDOException $e) {
				$err = $e->getMessage();
				$err = str_replace("You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use", "SQL syntax error", $err);
				trigger_error("MySQL error: $err", E_USER_ERROR);
			}

			$t = microtime(true) - $start;
			self::$time += $t;

			if (self::$debug_on) {
				$b = self::getbacktrace();
				self::$debug_list[] = array(
					$this->id,
					$b['pfunc'],
					$b['file'] . ":" . $b['line'],
					((!isset($err)) ? "<span style='color:#ffff44'>".htmlentities($query)."</span>" : "<span style='color:#FF0000;border-bottom:1px dotted red;' title=\"$err\">".htmlentities($query)."</span>"),
					sprintf("%01.6fs", $t)
				);
			}

			return $res;
		}
		
		public function execute($result, $vals = array()){
			$start = microtime(true);
			
			// This is to prevent an uncatchable fatal error. Thank you PHP!
			if (!$result) return NULL;
			
			$query = $result->queryString;
			
			try {
				$res = $result->execute($vals);
				++self::$queries;
				
				if (strtoupper(substr(trim($query), 0, 6)) == "SELECT")
					self::$rowst += $result->rowCount();
			}
			catch (PDOException $e){
				$err = $e->getMessage();
				trigger_error("MySQL (execute) error: $err", E_USER_ERROR);
				$res = false;
			}
			
			$t = microtime(true) - $start;
			self::$time += $t;

			if (self::$debug_on) {
				$b = self::getbacktrace();
				$query .= " | Values: " . implode(",", $vals);
				self::$debug_list[] = array(
					$this->id,
					$b['pfunc'],
					$b['file'] . ":" . $b['line'],
					((!isset($err)) ? "<span style='color:#ffcc44'>".htmlentities($query)."</span>" : "<span style='color:#FF0000;border-bottom:1px dotted red;' title=\"$err\">".htmlentities($query)."</span>"),
					sprintf("%01.6fs", $t)
				);
			}
			
			return $res;
		}

		public function fetch($result, $flag = PDO::FETCH_BOTH, $hash = NULL){
			$start = microtime(true);
			$res = NULL;
			
			if ($hash && isset($this->cache[$hash]))
				$res = $this->cache[$hash];
				
			else if ($result != false && $res = $result->fetch($flag)) { //, $reset ? PDO::FETCH_ORI_ABS : PDO::FETCH_ORI_NEXT))
				++self::$rowsf;
				if ($hash) $this->cache[$hash] = $res;
			}
			
			self::$time += microtime(true) - $start;
			return $res;
		}
		
		public function fetchAll($result, $flag = PDO::FETCH_BOTH, $hash = NULL){
			$start = microtime(true);
			$res = NULL;
			
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
			$res = NULL;
			
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

		public function queryp($query, $values = array(), &$querycheck = array()) {
			$q = $this->prepare($query);
			$result = $this->execute($q, $values);
			$querycheck[] = $result; // Pass result to query result array
			return $q;
		}
		
		public function fetchq($query, $flag = PDO::FETCH_BOTH, $cache = false, $all = false){
			$hash = (!$cache) ? NULL : md5($query);
			$res = $this->query($query, $hash);
			$res = (!$all) ? $this->fetch($res, $flag, $hash) : $this->fetchAll($res, $flag, $hash);
			return $res;
		}
		
		public function fetchp($query, $values = array(), $flag = PDO::FETCH_BOTH, $cache = false, $all = false){
			$hash = (!$cache) ? NULL : md5($query);
			$res = $this->prepare($query, array(), $hash);
			if (!$cache) 
				if (!($this->execute($res, $values)))
					return false;
			
			$res = (!$all) ? $this->fetch($res, $flag, $hash) : $this->fetchAll($res, $flag, $hash);
			return $res;
		}
		
		public function resultq($query, $row=0, $col=0, $cache = false){
			$hash = (!$cache) ? NULL : md5($query);
			$res = $this->query($query, $hash);
			$res = $this->result($res, $row, $col, $hash);
			return $res;
		}
		
		public function resultp($query, $values = array(), $row=0, $col=0, $cache = false){
			$hash = (!$cache) ? NULL : md5($query);
			$res = $this->prepare($query, array(), $hash);
			if (!$cache) 
				if (!($this->execute($res, $values)))
					return false;
			
			$res = $this->result($res, $row, $col, $hash);
			return $res;
		}
		
		// Is this even used?
		public function getmultiresults($query, $key, $wanted='', $cache = false) {
			$hash = (!$cache) ? NULL : md5($query);
			// $tmp[<keyval>] = <serialized array>
			$q = $this->query($query, $hash);
			
			if ($hash && isset($this->cache[$hash]))
				return $this->cache[$hash];
			
			$tmp = $this->fetchAll($q, PDO::FETCH_GROUP | PDO::FETCH_COLUMN);
			foreach ($tmp as $keys => $values)
				$ret[$keys] = implode(",", $values);
				
			if ($hash)
				$this->cache[$hash] = $ret;
			/*
			$ret = array();
			$tmp = array();

			while ($res = @$this->fetch($q, MYSQL_ASSOC))
				$tmp[$res[$key]][] = $res[$wanted];
			foreach ($tmp as $keys => $values)
				$ret[$keys] = implode(",", $values);
			*/
			return $ret;
		}
		
		public function getresultsbykey($query, $key='', $wanted='', $cache = false) {
			$hash = (!$cache) ? NULL : md5($query);
			$q = $this->query($query, $cache);
			$ret = $this->fetchAll($q, PDO::FETCH_KEY_PAIR, $hash);
			/*
			$ret = array();
			while ($res = @$this->fetch($q, MYSQL_ASSOC))
				$ret[$res[$key]] = $res[$wanted];
			*/
			return $ret;
		}
		
		public function getresults($query, $wanted='', $cache = false) {
			$hash = (!$cache) ? NULL : md5($query);
			$q = $this->query($query, $cache);
			$ret = $this->fetchAll($q, PDO::FETCH_COLUMN, $hash);
				
			/*$ret = array();
			while ($res = @$this->fetch($q, MYSQL_ASSOC))
				$ret[] = $res[$wanted];
			*/
			return $ret;
		}
		
		public function getarraybykey($query, $key, $cache = false) {
			$hash = (!$cache) ? NULL : md5($query);
			// $tmp[<keyval>] = <all values>
			$q = $this->query($query, $hash);
			
			if ($hash && isset($this->cache[$hash]))
				return $this->cache[$hash];
			/*
			$ret = $this->fetchAll($q, PDO::FETCH_UNIQUE);
			// Code compatibility - FETCH_UNIQUE doesn't add the index to the actual array
			$keys = array_keys($ret);
			foreach ($keys as $id)
				$ret[$id][$key] = $id;
				*/
			$ret = array();
			while ($res = $this->fetch($q, PDO::FETCH_ASSOC))
				$ret[$res[$key]] = $res;
			
			if ($hash)
				$this->cache[$hash] = $ret;
			
			return $ret;
		}

		public function getarray($query, $cache = false) {
			$hash = (!$cache) ? NULL : md5($query);
			// $ret[<num>] = <entire assoc row>
			$q = $this->query($query, $hash);
			$ret = $this->fetchAll($q, PDO::FETCH_ASSOC, $hash);
			/*$ret = array();
			while ($res = @$this->fetch($q, MYSQL_ASSOC))
				$ret[] = $res;
			*/
			return $ret;
		}

		public function escape($s) {
			return $this->connection->quote($s);
		}
		
		public function num_rows($res) {
			if (!$res || is_bool($res)) return NULL;
			return $res->rowCount();
		}
		
		public function insert_id() {
			return $this->connection->lastInsertId();
		}
		
		
		// A port of the transaction system from boardc
		public function beginTransaction(){
			$start = microtime(true);
			try {
				$result = $this->connection->beginTransaction();
			}
			catch (PDOException $e){
				$err = $e->getMessage();
				trigger_error("Could not begin transaction: $err", E_USER_ERROR);
				$result = NULL;
			}
				
			self::$time += microtime(true) - $start;
			return $result;
		}
		
		public function commit(){
			$start = microtime(true);
			try{
				$result = $this->connection->commit();
			}
			catch (PDOException $e){
				$err = $e->getMessage();
				trigger_error("Could not commit transaction: $err", E_USER_ERROR);
				$result = NULL;
			}
			self::$time += microtime(true) - $start;
			return $result;
		}
		
		// Takes an array with the result of the queries as argument
		// Typically it's the same array passed as third argument to $sql->query and $sql->queryp
		// Here, that array is checked so that the transaction will be committed only if all the queries were successful
		public function checkTransaction($list){
			foreach ($list as $queryres)
				if ($queryres === false && $queryres !== 0){
					$this->rollBack();
					return false;
				}
			$this->commit();
			return true;
		}
		
		public function rollBack(){
			$start = microtime(true);
			try{
				$result = $this->connection->rollBack(); //false on failure
			}
			catch (PDOException $e){
				$err = $e->getMessage();
				trigger_error("Could not rollback transaction: $err", E_USER_ERROR);
				$result = NULL;
			}
			self::$time += microtime(true) - $start;
			return $result;
		}
	
		/*public function set_character_encoding($s) {
			return mysql_set_charset($s, $this->connection);
		}*/

		//private function __construct() {}

		// Debugging shit for admins
		public static function debugprinter() {
			if (!self::$debug_on) return "";
			
			$out  = "";
			$out .= "<br>
				<table class='table'>
					<tr>
						<td class='tdbgh center' colspan=5>
							<b>SQL Debug</b>
						</td>
					</tr>
					<tr>
						<td class='tdbgh center' width=20 >&nbsp</td>
						<td class='tdbgh center' width=20 >ID</td>
						<td class='tdbgh center' width=300>Function</td>
						<td class='tdbgh center' width=*  >Query</td>
						<td class='tdbgh center' width=90 >Time</td>
					</tr>";
			$oldid = NULL;
			foreach(self::$debug_list as $i => $d) {
				// Cycling tccell1/2
				$cell = (($i & 1)+1);
				
				// Is the query ID identical to the previous?
				if ($oldid != $d[0]) {
					// If so, add a separator
					$out .= "
						<tr>
							<td class='tdbgc center' colspan=5>
								<img src='images/_.gif' height='4' width='1'>
							</td>
						</tr>";
				}
				$oldid = $d[0];
				$out .= "
					<tr>
						<td class='tdbg{$cell} center'>$i</td>
						<td class='tdbg{$cell} center'>$d[0]</td>
						<td class='tdbg{$cell} center'>
							$d[1]<span class='fonts'><br>
							$d[2]</span>
						</td>
						<td class='tdbg{$cell}'>".str_replace("\t", "", trim($d[3]))."</td>
						<td class='tdbg{$cell} center'>$d[4]</td>
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
			if (!isset($backtrace[$i+1]))
				$backtrace[$i]['pfunc'] = "<i>(main)</i>";
			else
				$backtrace[$i]['pfunc'] = $backtrace[$i+1]['function'];
			
			$backtrace[$i]['file'] = str_replace($_SERVER['DOCUMENT_ROOT'], "", $backtrace[$i]['file']);
			
			return $backtrace[$i];
		}
	}
?>
