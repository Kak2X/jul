<?php
	// Get out PMA, we're using mysqldump now
	chdir("..");
	require "lib/config.php";
	
	if (!$config['allow-debug-dump']) {
		die("Disabled.");
	}
	
	// Clear out any previous state
	if (ob_get_level()) ob_end_clean();
	
	// Set the correct headers to make this file downloadable
	header("Pragma: public");
	//header("Expires: 0");
	//header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	
	header("Cache-Control: public");
	header('Connection: Keep-Alive');
	header("Content-Disposition: attachment");
	header("Content-Description: File Transfer");
	header("Content-Disposition: filename=\"develop.sql\"");
	header("Content-Transfer-Encoding: binary");
	//header("Content-Length: 0");
	header("Content-Type: application/octet-stream");

	system("/xampp/mysql/bin/mysqldump -u {$sqluser} ".($sqlpass ? "-p{$sqlpass} " : "")."{$dbname}");