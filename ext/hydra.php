<?php

	header("Content-type: text/plain");
	$userid	= isset($_GET['u']) ? (int) $_GET['u'] : 0;

	if (!$userid) die("No userid specified.");
	chdir("..");
	require 'lib/function.php';

	print $sql -> resultq("SELECT `posts` FROM `users` WHERE `id` = '$userid'");


