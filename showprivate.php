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
	else if (!$msg || (($msg['userto'] != $loguser['id'] && $msg['userfrom'] != $loguser['id']) && !has_perm('view-others-pms'))) {
		errorpage("Couldn't get the private message.  It either doesn't exist or was not sent to you.",'private.php','your private message inbox');
	}
	
	/*
		Move private message to a different folder
	*/
	if (isset($_GET['move'])) {
		check_token($_GET['auth']);
		$dest = filter_int($_GET['move']);
		$valid = $sql->resultq("SELECT 1 FROM pmsg_folders WHERE id = $dest AND user = {$msg['userto']}");
		if ($valid) {
			$sql->query("UPDATE pmsgs SET folderto = $dest WHERE id = $id");
		}
		return header("Location: showprivate.php?id=$id");
	}
	
	/*
		Folder select box
	*/
	$sel_folder[$msg['folderto']] = 'selected';
	$folders = $sql->fetchq("SELECT id, title FROM pmsg_folders WHERE user = {$msg['userto']} ORDER BY ord, id ASC", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
	$folderselect = "";
	foreach ($folders as $fid => $ftitle) {
		$folderselect .= "<option value='$fid' ".filter_string($sel_folder[$fid]).">".htmlspecialchars($ftitle)."</option>\n\r";
	}
	$folderselect = 
		"Move to:
			<select name='move' onChange='parent.location=\"showprivate.php?id=$id&move=\"+this.options[this.selectedIndex].value+\"&auth=".generate_token()."\"'>
				".(isset($sel_folder[NULL]) ? "<option value=0 selected>[Uncategorized]</option>" : "")."
				{$folderselect}
			</select>
			<noscript>
				<input type='submit' name='setdir' value='Move'>
				<input type='hidden' name='auth' value='".generate_token()."'>
				<input type='hidden' name='id' value=$id>
			</noscript>";
	
	
	
	if (has_perm('view-others-pms') && $msg['userto'] != $loguser['id'])
		$pmlinktext = "<a href='private.php?id={$msg['userto']}'>".$sql->resultq("SELECT name FROM users WHERE id = {$msg['userto']}")."'s private messages</a>";
	else $pmlinktext = "<a href='private.php'>Private messages</a>";

	$user = $sql->fetchq("SELECT * FROM users WHERE id = {$msg['userfrom']}");
	$windowtitle = "{$config['board-name']} -- Private Messages: ".htmlspecialchars($msg['title']);

	$bottom = "<div class='font'><a href='index.php'>{$config['board-name']}</a> - <a href='private.php'>$pmlinktext</a> - ".htmlspecialchars($msg['title'])."</div>";
	$top 	= 
	"<form method='GET' action='showprivate.php'>
		<table style='border-spacing: 0px; padding: 0px; width: 100%'>
			<tr>
				<td>{$bottom}</td>
				<td class='font right'>{$folderselect}</td>
			</tr>
		</table>
	</form>";
	
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
	
	if ($loguser['viewsig'] == 2){
		$post['headtext'] = $user['postheader'];
		$post['signtext'] = $user['signature'];
	} else {
		$post['headtext'] = $msg['headtext'];
		$post['signtext'] = $msg['signtext'];
	}

	if ($msg['userto'] == $loguser['id'])
		$quote = "<a href='sendprivate.php?id=$id'>Reply</a>";
	else $quote = "";
	if (has_perm('forum-admin'))
		$ip = ($quote ? ' | ' : '') . "IP: <a href='admin-ipsearch.php?ip={$msg['ip']}'>{$msg['ip']}</a>";

	
	pageheader($windowtitle);
	print "{$top}<table class='table'>".threadpost($post,1)."</table>{$bottom}";
	pagefooter();
?>