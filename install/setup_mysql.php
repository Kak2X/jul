<?php

class mysql_setup extends mysql {
	public $errors = 0;
	public $q_errors = array();
	
	public function connect($host, $user, $pass, $dbname = NULL, $dummy2 = NULL) {
		global $config;
			
		$start = microtime(true);
		
		$dsn = "mysql:".($dbname !== null ? "dbname=$dbname;" : "")."host=$host;charset=utf8mb4";
		$opt = array(
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => $dbname === null, // sigh
			PDO::ATTR_PERSISTENT         => false,
		);
		try {
			$this->connection = new pdo($dsn, $user, $pass, $opt);
		}
		catch (PDOException $x) {
			$this->error = $x->getMessage();
			return false;
		}
		
		$t 			= microtime(true) - $start;
		$this->id 	= ++self::$connection_count;
		
		// Need to distinguish between the two because of the error text
		$this->server_name = (
			strpos($this->connection->getAttribute(PDO::ATTR_SERVER_VERSION), "MariaDB")
			? "MariaDB"
			: "MySQL");

		self::$time += $t;
		return $this->connection;
	}
	
	public function selectdb($db){
		try {
			$res = $this->connection->query("USE `".str_replace('`', '``', $db)."`"); // sigh 2
		}
		catch (PDOException $x){
			$this->error = $x->getMessage();
			return false;
		}
		return true;
	}
	
	// Import the SQL file line by line
	// If a line ends with ; process the buffer		
	public function import($file){
		$b = "";
		$h = fopen($file, 'r');
		
		while(($l = fgets($h)) !== false){
			$l	  = trim($l);
			
			$comment = substr($l, 0, 2);
			if (!$l || $comment == "/*" || $comment == "--") {
				continue; // it's a comment; ignore this
			}
			$b .= $l;
			// If the last character is ;, execute the query
			if (substr($l, -1) == ';'){
				$res = $this->query($b);
				if (!$res) {
					++$this->errors;
					$this->q_errors[] = htmlspecialchars($b);
				}
				$b = "";
			}
		}
		fclose($h);
	}
}