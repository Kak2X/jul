<?php

	// Split from thread.php since it was handled separately to begin with.
	
	require_once "lib/common.php";
	
	$_GET['id']	         = filter_int($_GET['id']); // Thread ID
	$_GET['act']         = filter_string($_GET['act']); // Vote action
	$_GET['vote']        = filter_int($_GET['vote']); // Vote choice ID
	
	if (!$_GET['id'])
		errorpage("No thread ID specified.");
	
	load_thread($_GET['id']); // Prevent voting on restricted threads
	$pollid = get_poll_from_thread($_GET['id']);
	if (!$pollid) {
		errorpage("This thread doesn't have a poll associated to it.", 'index.php', 'the index page');
	}
		
	// Poll votes
	if ($_GET['act']) {
		if (!$loguser['id'])
			errorpage("You must be logged in to vote on this poll.", 'index.php', 'the index page');
		
		check_token($_GET['auth'], TOKEN_VOTE);
		$res = vote_poll($pollid, $_GET['vote'], $loguser['id'], $_GET['act']);
		if (!$res) {
			errorpage("Could not vote on this poll.", 'index.php', 'the index page');
		}
		die(header("Location: thread.php?id={$_GET['id']}"));
	}
	
	errorpage("No action specified.");