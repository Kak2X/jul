<?php
	/*
		Backup script
		Run this in a cron job at midnight (or manually through admin-backup.php)
	*/
	
	if (substr(php_sapi_name(), 0, 3) != 'cli') {
		if (!defined('MANUAL_BACKUP')) die("No."); // If not called from cli or admin-backup, die instantly
		echo "<pre>";
		set_time_limit(0);
	} else {
		$startingtime = microtime(true);
		echo "Board Backup Script";
		echo "\n=====================\n\n";
	}
	
	chdir("..");
	
	
	$tables = array(
		'attachments',
		'archive_cat',
		'archive_items',
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
		'forummods',
		'forumbans',
//		'irc',
		'items',
		'itemcateg',
		'itemtypes',
		'ipbans',
		'misc',
		'news',
		'news_comments',
		'news_tags',
		'news_tags_assoc',
		'pendingusers',
//		'perm_definitions',
//		'perm_forums',
//		'perm_forumusers',
//		'perm_groups',
//		'perm_types_definitions',
//		'perm_users',
		'pm_access',
		'pm_folders',
		'pm_foldersread',
		'pm_posts',
		'pm_ratings',
		'pm_threads',
		'pm_threadsread',		
		'poll',
		'poll_choices',
		'pollvotes',
		'postlayouts',
		'postradar',
		'posts',
		'posts_old',
		'posts_ratings',
		'ranks',
		'ranksets',
		'ratings',
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
		'userratings',
		'users',
		'users_rpg',
//		'users_subgroups',
		'users_avatars',
		'users_comments'
	);
	
	// We don't need everything
	require_once "lib/config.php";
	require_once "lib/mysql.php";
	
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

	// We need to return the entire contents of the tables; so mark the backup flag as true to prevent interference from other users
	$sql->query("UPDATE misc SET backup = 1");
	
	echo "\nBacking up database...";
	// Board status things (to check which tables are empty)
	$status  = $sql->query("SHOW TABLE STATUS IN $dbname WHERE Name IN ('".implode("',\n'", $tables)."')");
	foreach($status as $x) $stat[$x['Name']] = $x['Rows'];
	
	// Use unbuffered query in an attempt to speed up the query fetching
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
		$openfile = "temp/cbak_{$table}"; //"{$config['backup-folder']}/tmp/$table";
		$handle   = fopen($openfile, 'w');
		$cols = $sql->fetchq("
			SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_SCHEMA = '$dbname' AND TABLE_NAME = '$table'
		", PDO::FETCH_COLUMN, mysql::FETCH_ALL);
		fwrite($handle, "INSERT INTO `$table` (`".implode('`,`', $cols)."`) VALUES\n");
		$cnt = count($cols);

		// Write out the data
		$data = $sql->query("SELECT * FROM $table");

		while (($r = $sql->fetch($data, PDO::FETCH_NUM)) !== false) {
			$set = true;
			for ($i = 0, $out = ''; $i < $cnt; $i++){
				if (!isset($r[$i])) 			$out .= "NULL,"; // explicitly required by some parts of the board
				//else if ($r[$i] == '0'.$r[$i])	$out .= $r[$i].","; // Numeric value?
				else							$out .= "'".str_replace("'", "''", $r[$i])."',"; // String
			}
			$out[strlen($out)-1] = ")"; // Replace the last comma
			fwrite($handle, "($out,\n");
		}
		
		fseek($handle, -2, SEEK_CUR); // set it before newline and ,
		fwrite($handle, ";");
		fclose($handle);
		
		echo $zip->addFile($openfile, "$table.sql") ? "OK!" : "ERROR!";

	}
	echo "\n\nFinalizing file...";
	echo $zip->close() ? "OK!\n\nBackup finished." : "ERROR!";
	
	echo "\nRemoving temporary files...\n";
	foreach($tables as $table){
		unlink("temp/cbak_{$table}");
	}
	
	print "\nTime taken: ".number_format(microtime(true)-$startingtime, 6)." seconds.";
	
	remove_lock();
	
	function remove_lock(){
		global $sql, $zip, $data;
		if ($data) foreach ($data as $x); // Clear any existing table buffer
		$sql->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
		$sql->query("UPDATE misc SET backup = 0");
		// Truncate ipinfo to refresh list of IPs
		//$sql->query("TRUNCATE ipinfo");
		//$sql->query("INSERT INTO ipinfo (ip, bot, proxy, tor) VALUES ('127.0.0.1', 0,0,0)");
	}
	/*
	function backup_error($string) {
		echo $string;
		remove_lock();
		die;
	}*/