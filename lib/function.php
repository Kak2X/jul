<?php
	$startingtime = microtime(true);
	
	require 'lib/helpers.php';	// Global mini functions
	
	require 'lib/defines.php'; // Constants and global variables
	require 'lib/auth.php'; // Permissions and authentication
	
	require 'lib/config.php';
	require 'lib/mysql.php';
	
	require 'lib/layout.php';
	require 'lib/rpg.php';

	require 'lib/irc.php'; // IRC Reporting...
	require 'lib/errorhandler.php'; // ... for errors
	require 'lib/common.php';
	
	require 'lib/forumlib.php';
	require 'lib/filters.php';
	require 'lib/graphics.php';
	require 'lib/threadpost.php';
// 	require 'lib/replytoolbar.php';
	require 'lib/datetime.php';