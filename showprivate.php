<?php
	
	require 'lib/function.php';
	
	$meta['noindex'] = true;
	if (!$loguser['id']) {
		errorpage("You need to be logged in to read your private messages.", 'login.php', 'log in (then try again)');
	}

	
	$_GET['id']	  = filter_int($_GET['id']); // Thread ID
	$_GET['pid']  = filter_int($_GET['pid']); // Post ID in thread
	
	$_GET['dir']  = isset($_GET['dir']) ? (int) $_GET['dir'] : PMFOLDER_ALL; // Marks the folder we selected the thread (for next/previous thread navigation)
	$_GET['user'] = $isadmin ? filter_int($_GET['user']) : 0; // ^ but for the user we're choosing
	$navparam = '&'.opt_param(['dir', 'user']);
	if (!isset($_GET['user'])) $_GET['user'] = $loguser['id'];
	
	$_GET['pin'] = filter_int($_GET['pin']);
	$_GET['lpt'] = filter_int($_GET['lpt']);
	$_GET['end'] = filter_int($_GET['end']);	
	
	
	// Skip to last post/end thread
	$gotopost	= null;
	if ($_GET['lpt']) {
		$gotopost = $sql->resultq("SELECT MIN(`id`) FROM `pm_posts` WHERE `thread` = '{$_GET['id']}' AND `date` > '{$_GET['lpt']}'");
	} else if ($_GET['end'] || ($_GET['lpt'] && !$gotopost)) {
		$gotopost = $sql->resultq("SELECT MAX(`id`) FROM `pm_posts` WHERE `thread` = '{$_GET['id']}'");
	}
	if ($gotopost) {
		return header("Location: ?pid={$gotopost}{$navparam}#{$gotopost}");
	}
	
	$ppp	= get_ppp();
	
	// Linking to a post ID
	if ($_GET['pid']) {
		$_GET['id']		= $sql->resultq("SELECT `thread` FROM `pm_posts` WHERE `id` = '{$_GET['pid']}'");
		if (!$_GET['id']) {
			errorpage("Couldn't find a post with ID #{$_GET['pid']}.", "index.php", 'the index page');
		}
		$numposts 	= $sql->resultq("SELECT COUNT(*) FROM `pm_posts` WHERE `thread` = '{$_GET['id']}' AND `id` < '{$_GET['pid']}'");
		$page 		= floor($numposts / $ppp);
	} else {
		$page		= filter_int($_GET['page']);
	}
	
	// Are we allowed in?
	

	const E_BADPOSTS = -1;
	
	$thread_error = 0;

	$thread = $sql->fetchq("SELECT * FROM pm_threads WHERE id = {$_GET['id']}");
	$tlinks = '';

	if (!$thread) {
		if (!$isadmin) {
			trigger_error("Accessed nonexistant PM thread number #{$_GET['id']}", E_USER_NOTICE);
			errorpage("Couldn't enter the conversation, since you don't have access to it.", 'index.php', 'the index page');
		}

		if ($sql->resultq("SELECT COUNT(*) FROM `pm_posts` WHERE `thread` = '{$_GET['id']}'") <= 0) {
			errorpage("Thread ID #{$_GET['id']} doesn't exist, and no posts are associated with the invalid thread ID.","index.php",'the index page');
		}

		// Admin can see and possibly remove bad posts
		$thread_error     = E_BADPOSTS;
		$thread['closed'] = true;
		$thread['title']  = "Bad thread with ID #{$_GET['id']}";
		$windowtitle      = "";
	} else {
		
		$access = $sql->fetchq("SELECT * FROM pm_access WHERE thread = {$_GET['id']} AND user = {$loguser['id']}");
		if (!$access && !$isadmin) {
			trigger_error("Attempted to access PM thread {$_GET['id']} in a restricted conversation (user's name: {$loguser['name']})", E_USER_NOTICE);
			errorpage("Couldn't enter the conversation, since you don't have access to it.", 'index.php', 'the index page');
		}
		$tlinks = array();
		
		// An admin sneaking in shouldn't ever update the last read stats
		if ($access) {
			if (!valid_pm_folder($access['folder'], $loguser['id'])) {
				trigger_error("A PM thread was located in an invalid PM folder (user's name: {$loguser['name']} [#{$loguser['id']}]; folder #{$access['folder']}). The thread has been moved to the default folder.", E_USER_NOTICE);
				$access['folder'] = PMFOLDER_MAIN;
				$sql->query("UPDATE pm_access SET folder = ".PMFOLDER_MAIN." WHERE thread = {$_GET['id']} AND user = {$loguser['id']}");
			}
			
			// Unread posts count
			$readdate = (int) $sql->resultq("SELECT `readdate` FROM `pm_foldersread` WHERE `user` = '{$loguser['id']}' AND folder = {$access['folder']}");
			if ($thread['lastpostdate'] > $readdate) {
				$sql->query("REPLACE INTO pm_threadsread SET `uid` = '{$loguser['id']}', `tid` = '{$thread['id']}', `time` = '".ctime()."', `read` = '1'");
			}	
			// See if it's possible to merge in the folderread
			$unreadthreads = $sql->resultq("
				SELECT COUNT(*) 
				FROM pm_access a 
				LEFT JOIN pm_threads t ON a.thread = t.id 
				LEFT JOIN pm_threadsread r ON a.thread = r.tid AND r.uid = {$loguser['id']}
				WHERE a.user = {$loguser['id']} AND a.folder = {$access['folder']}
				  AND (!r.read OR r.read IS NULL) 
				  AND t.lastpostdate > {$readdate} 
			");
			if (!$unreadthreads) { // All threads in the folder have been read; we can merge
				$sql->query("REPLACE INTO pm_foldersread VALUES ({$loguser['id']}, {$access['folder']}, ".ctime().")");
			}
		}
		
		/*
			Previous/next thread in folder navigation
		*/
		switch ($_GET['dir']) {
			case PMFOLDER_ALL:
				$ffilter = "";
				break;
			case PMFOLDER_BY:
				$ffilter = " AND t.user = {$_GET['user']}";
				break;
			case PMFOLDER_TO:
				$ffilter = " AND t.user != {$_GET['user']}";
				break;
			default:
				$ffilter = " AND a.folder = {$_GET['dir']}";
				break;
		}
		$tnext = $sql->resultq("
			SELECT t.id 
			FROM pm_access a
			INNER JOIN pm_threads t ON a.thread = t.id
			WHERE a.user = {$_GET['user']}{$ffilter} AND t.lastpostdate > {$thread['lastpostdate']} 
			ORDER BY t.lastpostdate ASC 
			LIMIT 1
		");
		if ($tnext) $tlinks[] = "<a href='?id={$tnext}{$navparam}' class='nobr'>Next newer thread</a>";
		$tprev = $sql->resultq("
			SELECT t.id 
			FROM pm_access a
			INNER JOIN pm_threads t ON a.thread = t.id
			WHERE a.user = {$_GET['user']}{$ffilter} AND t.lastpostdate < {$thread['lastpostdate']} 
			ORDER BY t.lastpostdate DESC
			LIMIT 1
		");
		if ($tprev) $tlinks[] = "<a href='?id={$tprev}{$navparam}' class='nobr'>Next older thread</a>";
		$tlinks = implode(' | ', $tlinks);
		
		$windowtitle = "Private messages: {$thread['title']}";
		
	}
	
	pageheader($windowtitle);
	
	/*
		Thread controls
	*/
	$linklist = $fulledit = "";
	if ($isadmin || ($loguser['id'] == $thread['user'] && $config['allow-pmthread-edit'])) {
		$link = "<a href='editpmthread.php?id={$_GET['id']}&auth=".generate_token(TOKEN_MGET)."&action";
		if (!$thread['closed']) {
			$linklist .= "$link=qclose'>Close</a>";
		} else {
			$linklist .= "$link=qunclose'>Open</a>";
		}
		$fulledit = " -- <a href='editpmthread.php?id={$_GET['id']}'>Edit thread<a>";
	}
	if ($access) { // Moving a thread on a different folder should be always possible
		if ($access['folder'] != PMFOLDER_TRASH) {
			$linklist .= " - <a href='editpmthread.php?id={$_GET['id']}&action=trashthread'>Trash</a>";
		}
		$linklist .= " - <a href='editpmthread.php?id={$_GET['id']}&action=movethread'>Move</a>";
		$head = "Thread options";
	} else {
		$head = "Sneak mode";
	}
	$modfeats = "<tr><td class='tdbgc fonts' colspan=2>{$head}: {$linklist} {$fulledit}</td></tr>";
	


	$errormsgs = '';
	if ($thread_error) {
		switch($thread_error) {
        	case E_BADPOSTS: $errortext='This PM thread does not exist, but posts exist that are associated with this invalid thread ID.'; break;
		}
		$errormsgs = "<tr><td style='background:#cc0000;color:#eeeeee;text-align:center;font-weight:bold;'>$errortext</td></tr>";
	}
	loadtlayout();
	
	switch($loguser['viewsig']) {
		case 1:  $sfields = ',p.headtext,p.signtext'; break;
		case 2:  $sfields = ',u.postheader headtext,u.signature signtext'; break;
		default: $sfields = ''; break;
	}
	$ufields = userfields();

	// Activity in the last day (to determine syndromes)
	$act = $sql->getresultsbykey("SELECT user, COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." GROUP BY user");
	
	$postlist = "
		<table class='table'>
		{$modfeats}
		{$errormsgs}
	";

	$threadforumlinks = "";
	// New Reply / Thread / Poll links
	$threadforumlinks .= "<a href='sendprivate.php'>New conversation</a>";
	if (!$thread['closed']) $threadforumlinks .= " - <a href='sendprivate.php?id={$_GET['id']}'>{$newreplypic}</a>";
	
	$threadforumlinks = "
	<table style='width: 100%'>
		<tr>
			<td class='font'>
				<a href='index.php'>{$config['board-name']}</a> - 
				<a href='private.php'>Private messages</a> - 
				".htmlspecialchars($thread['title']).
			"</td>
			<td class='fonts right'>{$threadforumlinks}</td>
		</tr>
	</table>";

	
	// Query elements
	$min	= $ppp * $page;
	
	// Workaround for the lack of scrollable cursors
	$layouts = $sql->query("SELECT headid, signid FROM pm_posts WHERE thread = {$_GET['id']} ORDER BY id ASC LIMIT $min, $ppp");
	preplayouts($layouts);
	
	$showattachments = $config['allow-attachments'] || !$config['hide-attachments'];
	if ($showattachments) {
		$attachments = $sql->fetchq("
			SELECT p.id post, a.id, a.filename, a.size, a.views, a.is_image
			FROM pm_posts p
			LEFT JOIN attachments a ON p.id = a.pm
			WHERE p.thread = {$_GET['id']} AND a.id IS NOT NULL
			ORDER BY p.id ASC
			LIMIT {$min},{$ppp}
		", PDO::FETCH_GROUP, mysql::FETCH_ALL);
	}
	
	if ($config['allow-avatar-storage']) {
		$avatars = $sql->query("
			SELECT p.user, p.moodid, v.weblink
			FROM pm_posts p
			LEFT JOIN users_avatars v ON p.moodid = v.file
			WHERE p.thread = {$_GET['id']} AND v.user = p.user
			ORDER BY p.id ASC
			LIMIT {$min},{$ppp}
		");
		$avatars = prepare_avatars($avatars);
	}
	// heh
	$posts = $sql->query("
		SELECT 	p.id, p.thread, p.user, p.date, p.ip, p.noob, p.moodid, p.headid, p.signid,
				p.text$sfields, p.edited, p.editdate, p.options, p.tagval, p.deleted,
				u.id uid, u.name, $ufields, u.regdate
		FROM pm_posts p
		
		LEFT JOIN users u ON p.user = u.id
		WHERE p.thread = {$_GET['id']}
		ORDER BY p.id ASC
		LIMIT $min,$ppp
	");
	
	$controls['ip'] = "";
	for ($i = 0; $post = $sql->fetch($posts); ++$i) {
		$bg = $i % 2 + 1;

		$controls['quote'] = "<a href=\"?pid={$post['id']}#{$post['id']}\">Link</a>";
		if (!$post['deleted'] && !$thread['closed']) {
			$controls['quote'] .= " | <a href='sendprivate.php?id={$_GET['id']}&postid={$post['id']}'>Quote</a>";
		}
		
		$controls['edit'] = '';
		if ($isadmin || (!$banned && $config['allow-pmthread-edit'] && !$post['deleted'] && $post['user'] == $loguser['id'])) {
			$tokenstr = "&auth=".generate_token(TOKEN_MGET);
			
        	if ($isadmin || !$thread['closed']) {
				$controls['edit'] = " | <a href='editpmpost.php?id={$post['id']}'>Edit</a>";
			}
			
			if ($post['deleted']) {
				if ($isadmin) {
					// Post peeking feature
					if ($post['id'] == $_GET['pin']) {
						$post['deleted'] = false;
						$controls['edit'] .= " | <a href='?pid={$post['id']}{$navparam}'>Unpeek</a>";
					} else {
						$controls['edit'] .= " | <a href='?pid={$post['id']}&pin={$post['id']}#{$post['id']}{$navparam}'>Peek</a>";
					}
				}
				$controls['edit'] .= " | <a href='editpmpost.php?id={$post['id']}&action=delete'>Undelete</a>";
			} else {
				$controls['edit'] .= " | <a href='editpmpost.php?id={$post['id']}&action=noob{$tokenstr}'>".($post['noob'] ? "Un" : "")."n00b</a>";
				$controls['edit'] .= " | <a href='editpmpost.php?id={$post['id']}&action=delete'>Delete</a>";
			}
			if ($sysadmin && $config['allow-post-deletion']) {
				$controls['edit'] .= " | <a href='editpmpost.php?id={$post['id']}&action=erase'>Erase</a>";
			}
			
		}

		if ($isadmin) {
			$controls['ip'] = " | IP: <a href='admin-ipsearch.php?ip={$post['ip']}'>{$post['ip']}</a>";
		}
		
		if ($showattachments && isset($attachments[$post['id']])) {
			$post['attach'] = $attachments[$post['id']];
		}
		
		$post['act']     = filter_int($act[$post['user']]);
		if ($config['allow-avatar-storage']) {
			$post['piclink'] = filter_string($avatars[$post['user']][$post['moodid']]);
		}
		
		$postlist .= "<tr>".threadpost($post, $bg, -1)."</tr>";
	}

	// Strip _GET variables that can set the page number
	$query = preg_replace("'page=(\d*)'si", '', '?'.$_SERVER["QUERY_STRING"]);
	$query = preg_replace("'pid=(\d*)'si", "id={$_GET['id']}", $query);
	$query = preg_replace("'&{2,}'si", "&", $query);


	$pagelinks = pagelist($query, $thread['replies'] + 1, $ppp);

	
	print "
		$threadforumlinks
		<table width=100%><td align=left class='fonts'>$pagelinks</td><td align=right class='fonts'>$tlinks</table>
		{$postlist}
		<table class='table'>
		{$modfeats}
		</table>
		<table width=100%><td align=left class='fonts'>$pagelinks</td><td align=right class='fonts'>$tlinks</table>
		$threadforumlinks";
	
	pagefooter();