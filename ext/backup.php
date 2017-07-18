<?php
	/*
		Backup script
		Run this in a cron job at midnight (or manually through admin-backup.php)
	*/
	
	const DEBUG_BACKUP = true;
	
	if (substr(php_sapi_name(), 0, 3) != 'cli') {
		if (!DEBUG_BACKUP) die("No.");
		echo "<pre>";
		set_time_limit(0);
	}
	
	chdir("..");
	
	$startingtime = microtime(true);
	
	$tables = array(
		//'actionlog', # Not used (yet?)
		//'biggestposters',
		'blockedlayouts',
		'bots',
		'categories',
		'dailystats',
		'defines',
		'delusers',
		'events',
		//'failedlogins',
		//'failedregs',
		'failsupress',
		'favorites',
		'filters',
		'forums',
		'items',
		'itemcateg',
		'itemtypes',
		'ipbans',
		'misc',
		//'news',#boardc
		//'news_comments',#boardc
		'pendingusers',
		'perm_forums',
		'perm_forumusers',
		'perm_groups',
		'perm_users',
		'pmsgs',
		'pmsg_folders',
		'poll',
		'poll_choices',
		'pollvotes',
		'postlayouts',
		'postradar',
		'posts',
		//'posts_old',#boardc
		'ranks',
		'ranksets',
		'rpg_classes',
		'rpg_inventory',
		'schemes',
		'threads',
		'tinapoints',
		'tlayouts',
		'tournamentplayers',
		'tournaments',
		//'userpic',
		//'userpiccateg',
		'users',
		'users_rpg',
		//'user_avatars',#boardc
		//'user_comments'#boardc
	);
	
	// We don't need everything
	require_once "lib/config.php";
	require_once "lib/mysql.php";
	
	//echo "Board Backup Script";
	//echo "\n=====================\n\n";
	
	echo "Connecting to database...";
	$sql 			= new mysql;
	$connection 	= $sql->connect($sqlhost, $sqluser, $sqlpass, $dbname) or die("\nConnection error.");
	
	
	echo "\nInitializing .zip file...";
	$zip = new ZipArchive;
	$h = $zip->open("{$config['backup-folder']}/".date("Ymd").".zip", ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
	if ($h !== TRUE) die("ERROR!\nError code: $h");
	else echo "OK!";
	
	// Can't trust the script to not blow up
	register_shutdown_function('remove_lock');
	
	// We need to return the entire contents of the tables
	$sql->query("UPDATE misc SET backup = 1");
	
	echo "\nBacking up database...";
	// Board status things (to check which tables are empty)
	$status  = $sql->query("SHOW TABLE STATUS IN $dbname WHERE Name IN ('".implode("','", $tables)."')");
	foreach($status as $x) $stat[$x['Name']] = $x['Rows'];
	
	$sql->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
	
	foreach ($tables as $tid => $table) {
		echo "\n-$table...";
		
		// Before doing anything, check if there are rows in this table
		if (!$stat[$table]) {
			echo "OK! [Table empty; skipped]";
			unset($tables[$tid]); // Unset here so we won't try to unlink a nonexisting temporary file for this table
			continue;
		}
		
		// Determine field names for this table
		$handle = fopen("{$config['backup-folder']}/tmp/$table", 'w');
		$cols 	= $sql->fetchq("
			SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = '$table'
		", PDO::FETCH_COLUMN, mysql::FETCH_ALL);
		fwrite($handle, "INSERT INTO `$table` (`".implode('`,`', $cols)."`) VALUES\n");
		$cnt = count($cols);

		$data = $sql->query("SELECT * FROM $table");

		while (($r = $sql->fetch($data, PDO::FETCH_NUM)) !== false) {
			$set = true;
			for ($i = 0, $out = ''; $i < $cnt; $i++){
				if (!isset($r[$i])) 			$out .= "NULL,"; // explicitly required by some parts of the board
				//else if ($r[$i] == '0'.$r[$i])	$out .= $r[$i].","; // Numeric value?
				else							$out .= "'".str_replace("'", "''", $r[$i])."',"; // String
			}
			$out[strlen($out)-1] = ")"; // :D
			fwrite($handle, "($out,\n");
		}
		
		fseek($handle, -2, SEEK_CUR); // set it before newline and ,
		fwrite($handle, ";");
		fclose($handle);
		
		echo $zip->addFile("{$config['backup-folder']}/tmp/$table", "$table.sql") ? "OK!" : "ERROR!";

	}
	echo "\n\nFinalizing file...";
	echo $zip->close() ? "OK!\n\nBackup finished." : "ERROR!";
	
	echo "\nRemoving temporary files...\n";
	foreach($tables as $table){
		unlink("{$config['backup-folder']}/tmp/$table");
	}
	
	print "\nTime taken: ".number_format(microtime(true)-$startingtime, 6)." seconds.";
	
	function remove_lock(){
		global $sql, $zip, $data;
		if ($data) foreach ($data as $x); // Clear any existing table buffer
		$sql->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		$sql->query("UPDATE misc SET backup = 0");
		// Truncate ipinfo to refresh list of IPs
		//$sql->query("TRUNCATE ipinfo");
		//$sql->query("INSERT INTO ipinfo (ip, bot, proxy, tor) VALUES ('127.0.0.1', 0,0,0)");
	}
?>