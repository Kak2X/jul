<?php

	// Split from thread.php since it was handled separately to begin with.
	
	require_once "lib/common.php";
	
	$_GET['id']	         = filter_int($_GET['id']); // Thread ID
	$_GET['act']         = filter_string($_GET['act']); // Vote action
	$_GET['vote']        = filter_int($_GET['vote']); // Vote choice ID
	$_GET['redir']       = filter_string($_GET['redir']); // Page redirect
	
	
	if (!$_GET['id'])
		errorpage("No thread ID specified.");
	
	load_thread($_GET['id']); // Prevent voting on restricted threads
	if (!$thread['poll']) {
		errorpage("This thread doesn't have a poll associated to it.", 'index.php', 'the index page');
	}
		
	// Poll votes
	if ($_GET['act'] == "add" || $_GET['act'] == "del") {
		if (!$loguser['id'])
			errorpage("You must be logged in to vote on this poll.", 'index.php', 'the index page');
		
		check_token($_GET['auth'], TOKEN_VOTE);
		$res = vote_poll($thread['poll'], $_GET['vote'], $loguser['id'], $_GET['act']);
		if (!$res) {
			errorpage("Could not vote on this poll.", 'index.php', 'the index page');
		}
		die(header("Location: {$_GET['redir']}.php?id={$_GET['id']}"));
	}
	
	
	// Barlinks
	$links = [
		[$forum['title'], "forum.php?id={$forum['id']}"],
		[$thread['title'], "thread.php?id={$_GET['id']}"],
		["Poll votes", null],
	];
	$barlinks = dobreadcrumbs($links); 
	
	load_poll($thread['poll'], $forum['pollstyle'], true);
	pageheader("{$thread['title']} - View Poll");
	
	print $barlinks.print_poll($poll, $thread, $forum['id']).$barlinks;
	
	
	pagefooter();