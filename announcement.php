<?php
	require "lib/common.php";

	// This file felt completely neglected (for obvious reasons)
	
	$_GET['id']		= filter_int($_GET['id']);
	$_GET['page']	= filter_int($_GET['page']);
	$_GET['f']	    = filter_int($_GET['f']);
	
	// Make sure the forum exists and we can access it
	if ($_GET['f']) {
		load_forum($_GET['f']);
		$forumannc = true;
		if ($forum_error) {
			$forum_error = "<table class='table'>{$forum_error}</table>";
		}
	} else {
		$_GET['f'] = $config['announcement-forum'];
		if (!$_GET['f']) {
			errorpage("No announcement forum defined.");
		}
		$forumannc   = false;
		$forum_error = "";
	}
	
	$ismod     = ismod($_GET['f']);
	$canthread = ($isadmin || ($ismod && $forumannc));

		
	$smilies = readsmilies();
	
	pageheader();
	
	$links    = array();
	$barright = "";
	if ($forumannc) {
		$links[] = [$forum['title'], "forum.php?id={$forum['id']}"];
		if ($canthread) $barright = "<a href='newthread.php?id={$_GET['f']}&a=1'>Post new announcement</a>";
	} else {
		if ($canthread) $barright = "<a href='newthread.php?id={$_GET['f']}'>Post new announcement</a>";
	}
	$links[] = ['Announcements', NULL];
	print dobreadcrumbs($links, $barright); 
	
	loadtlayout();
	
	$ppp	= get_ppp();
	$min 	= $ppp * $_GET['page'];
	
	// Set better last read date
	if ($loguser['id']) {
		$readdate = $sql->resultq("SELECT `readdate` FROM `forumread` WHERE `user` = '{$loguser['id']}' AND `forum` = '{$_GET['f']}' LIMIT 1");
		$thread = $sql->fetchq("SELECT id, firstpostdate FROM threads WHERE forum = {$_GET['f']}".($forumannc ? " AND announcement = 1" : "")." ORDER BY firstpostdate DESC LIMIT 1");
		
		if ($thread) {
			if ($loguser['id'] && $thread['firstpostdate'] > $readdate) {
				// Set only the first post as marked so announcement replies won't get marked as read 
				$sql->query("REPLACE INTO threadsread SET `uid` = '{$loguser['id']}', `tid` = '{$thread['id']}', `time` = '".($thread['firstpostdate']++)."', `read` = '1'");
			}
			
			$sql->query("INSERT INTO announcementread (user, forum, readdate) VALUES({$loguser['id']}, {$_GET['f']}, ".time().") 
			ON DUPLICATE KEY UPDATE readdate = VALUES(readdate)");
		}
	}
	
	// Syndrome detection
	$act = load_syndromes();
	$searchon = "t.forum = {$_GET['f']} ".($forumannc ? "AND t.announcement = 1" : "");
	
	$ufields = userfields();
	
	// Get every first post for every (announcement) thread in the forum
	$anncs = $sql->getarray(set_avatars_sql("
		SELECT t.title atitle, t.description adesc, MIN(p.id) pid, p.*,
		       COUNT(p.id)-1 replies, u.id uid, u.name, u.displayname, $ufields, 
			   ".set_userfields('ue').", u.regdate{%AVFIELD%}  
		FROM threads t
		LEFT JOIN posts  p ON p.thread   = t.id
		LEFT JOIN users  u ON p.user     = u.id
		LEFT JOIN users ue ON p.editedby = ue.id
		{%AVJOIN%}
		WHERE {$searchon}
		  AND p.deleted = 0
		GROUP BY t.id
		ORDER BY p.date DESC
		LIMIT $min,$ppp
	"));
	$annctotal = $sql->resultq("SELECT COUNT(*) FROM threads WHERE forum = {$_GET['f']} ".($forumannc ? "AND announcement = 1" : ""));
	
	preplayouts($anncs);
	
	//--
	$postids = array_column($anncs, 'id');
	
	$showattachments = $config['allow-attachments'] || !$config['hide-attachments'];
	if ($showattachments) {
		$attachments = load_attachments($searchon, null, $postids, MODE_ANNOUNCEMENT);
	}
	hook_use('annc-extra-db', $searchon, $postids);
	//--
	
	$pagelinks = pagelist("?".($forumannc ? "f={$_GET['f']}&" : "")."ppp={$ppp}", $annctotal, $ppp);

	$annclist = "
	{$forum_error}
	<table class='table'>
		<tr>
			<td class='tdbgh center' style='width: 200px'>User</td>
			<td class='tdbgh center' colspan=2>Announcement</td>
		</tr>";
		
	$bg = 0;
	foreach ($anncs as $annc) {
		$annclist .= '<tr>';
		$bg = $bg % 2 + 1;
		
		if ($showattachments && isset($attachments[$annc['id']])) {
			$annc['attach'] = $attachments[$annc['id']];
		}
		
		$controls = [
			"<a href='thread.php?pid={$annc['id']}'>View replies</a> ({$annc['replies']})", 
			"<a href='newreply.php?id={$annc['thread']}&postid={$annc['id']}'>Quote</a>"
		];
		if ($canthread) {
			$tokenstr = "&auth=".generate_token(TOKEN_MGET);
			$controls[] = "<a href='editpost.php?id={$annc['id']}'>Edit</a>";
			$controls[] = "<a href='editpost.php?id={$annc['id']}&action=delete'>Delete</a>";
			$controls[] = "<a href='editpost.php?id={$annc['id']}&action=noob{$tokenstr}'>".($annc['noob'] ? "Un" : "")."n00b</a>";
			//--
			if (can_edit_highlight($annc))
				$controls[] = "<a href='editpost.php?id={$annc['id']}&action=highlight&type=1{$tokenstr}'>".($annc['highlighted'] ? "Unh" : "H")."ighlight</a>";
			$controls[] = "<a href='editpost.php?id={$annc['id']}&action=warn{$tokenstr}'>".($annc['warned'] ? "Unw" : "W")."arn</a>";			
			//--
			if ($isadmin) 
				$controls[] = "IP: ".htmlspecialchars($annc['ip']);
		}
		hook_use_ref('annc-extra-fields', $annc);
		
		$annc['act'] = filter_int($act[$annc['user']]);
		$annc['text'] = "<center><b>{$annc['atitle']}</b><div class='fonts'>{$annc['adesc']}</div></center><hr>{$annc['text']}";
		$annclist .= threadpost($annc, $bg, MODE_ANNOUNCEMENT, $_GET['f']);
	}
	
	echo "$pagelinks<table class='table'>$annclist</table>$pagelinks";
	
	pagefooter();
	