<?php
	require 'lib/function.php';
	
	$id 		= filter_int($_GET['id']);
	
	if (!$id) return header("Location: private.php");
	
	$windowtitle = "{$config['board-name']} -- Private Messages";
	
	$meta['noindex'] = true;

	$msg = $sql->fetchq("SELECT * FROM pmsgs WHERE id = $id");

	if (!$loguser['id']) {
		errorpage("Couldn't get the private message.  You are not logged in.",'login.php','log in (then try again)');
	}
	else if (!$msg || (($msg['userto'] != $loguser['id'] && $msg['userfrom'] != $loguser['id']) && !$isadmin)) {
		errorpage("Couldn't get the private message.  It either doesn't exist or was not sent to you.",'private.php','your private message inbox');
	}

	if ($isadmin && $msg['userto'] != $loguser['id'])
		$pmlinktext = "<a href='private.php?id={$msg['userto']}'>".$sql->resultq("SELECT name FROM users WHERE id = {$msg['userto']}")."'s private messages</a>";
	else $pmlinktext = "<a href='private.php'>Private messages</a>";

	$user = $sql->fetchq("SELECT * FROM users WHERE id = {$msg['userfrom']}");
	$windowtitle = "{$config['board-name']} -- Private Messages: ".htmlspecialchars($msg['title']);

	$top = "<div class='font'><a href='index.php'>{$config['board-name']}</a> - <a href='private.php'>$pmlinktext</a> - ".htmlspecialchars($msg['title'])."</div>";
	
	// Make sure we don't accidentaly mark the PM as read when viewing other people's inbox
	if ($msg['userto'] == $loguser['id'])
		$sql->query("UPDATE pmsgs SET msgread = 1 WHERE id=$id");

	
	// Threadpost requirements
	loadtlayout();
	$post = $user;
	$post['uid']    = $user['id'];
	$post['date']   = $msg['date'];
	$post['headid'] = $msg['headid'];
	$post['signid'] = $msg['signid'];
	$post['moodid'] = $msg['moodid'];
	$post['options'] = "0|0";
	$post['text']   = $msg['text'];
	$post['tagval'] = $msg['tagval'];
	$post['num']	= 0;
	$post['noob']	= 0;
	$post['act'] 	= $sql->resultq("SELECT COUNT(*) FROM posts WHERE date > ".(ctime() - 86400)." AND user = {$user['id']}");
	$post['piclink']   = $sql->resultq("SELECT weblink FROM users_avatars WHERE user = {$user['id']} AND file = {$msg['moodid']}");
	
	if ($loguser['viewsig'] == 2){
		$post['headtext'] = $user['postheader'];
		$post['signtext'] = $user['signature'];
	} else {
		$post['headtext'] = $msg['headtext'];
		$post['signtext'] = $msg['signtext'];
	}

	$controls = array(
		'quote' => ($msg['userto'] == $loguser['id'] ? "<a href='sendprivate.php?id=$id'>Reply</a>" : ""),
		'edit'  => "",
		'ip'    => ($isadmin ? "IP: <a href='admin-ipsearch.php?ip={$msg['ip']}'>{$msg['ip']}</a>" : ""),
	);
	if ($controls['quote']) {
		$controls['quote'] = " | {$controls['quote']}";
	}
	
	pageheader($windowtitle);
	print "{$top}<table class='table'>".threadpost($post,1)."</table>{$top}";
	pagefooter();
	