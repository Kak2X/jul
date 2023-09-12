<?php
	chdir("..");
	require "lib/function.php";
	require "lib/config.php";
	
	$sql = new mysql();
	$sql->connect($sqlhost, $sqluser, $sqlpass, $dbname) or fatal("Couldn't connect to the MySQL server.");
	
	$_GET['act'] = filter_string($_GET['act']);
	if ($_GET['act'] == "v") {
		print $sql->resultq("SELECT views FROM misc");
		die;
	}
	
	fatal("No valid command.");
	
	function fatal($err) {
		http_response_code(500);
		die($err);
	}