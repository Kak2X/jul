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
		$_GET['id'] = get_pm_thread_from_post($_GET['pid']);
		$numposts 	= $sql->resultq("SELECT COUNT(*) FROM `pm_posts` WHERE `thread` = '{$_GET['id']}' AND `id` < '{$_GET['pid']}'");
		$_GET['page'] = floor($numposts / $ppp);
	} else {
		$_GET['page'] = filter_int($_GET['page']);
	}
	
	
	load_pm_thread($_GET['id']);
	if ($access) {
		// Automatically move threads out of invalid folders upon access
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
		Previous/next conversation in folder navigation
		This accounts for the folder the conversation was selected from ($_GET['dir'])
	*/
	$tlinks = array();
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
	
	pageheader("Private messages: {$thread['title']}");

	
	/*
		Thread controls
	*/
	$linklist = $fulledit = "";
	// Thread owner / admin actions
	if ($isadmin || ($loguser['id'] == $thread['user'] && $config['allow-pmthread-edit'])) {
		$link = "<a href='editpmthread.php?id={$_GET['id']}&auth=".generate_token(TOKEN_MGET)."&action";
		if (isset($thread['error'])) {
			$linklist .= "<s>Close</s>";
		} else if (!$thread['closed']) {
			$linklist .= "$link=qclose'>Close</a>";
		} else {
			$linklist .= "$link=qunclose'>Open</a>";
		}
		$fulledit = " -- <a href='editpmthread.php?id={$_GET['id']}'>Edit thread<a>";
	}
	// Moving a thread on a different folder should be always possible
	if ($access) { 
		if ($access['folder'] != PMFOLDER_TRASH) {
			$linklist .= " - <a href='editpmthread.php?id={$_GET['id']}&action=trashthread'>Trash</a>";
		}
		$linklist .= " - <a href='editpmthread.php?id={$_GET['id']}&action=movethread'>Move</a>";
		$head = "Thread options";
	} else {
		$head = "Sneak mode";
	}
	$modfeats = "<tr><td class='tdbgc fonts' colspan=2>{$head}: {$linklist} {$fulledit}</td></tr>";
	
	
	loadtlayout();
	
	switch($loguser['viewsig']) {
		case 1:  $sfields = ',p.headtext,p.signtext,p.csstext'; break;
		case 2:  $sfields = ',u.postheader headtext,u.signature signtext,u.css csstext'; break;
		default: $sfields = ''; break;
	}
	$ufields = userfields();

	// Activity in the last day (to determine syndromes)
	$act = load_syndromes();
	
	$postlist = "
		<table class='table'>
		{$modfeats}
		{$forum_error}
	";
	
	$links = array(
		["Private messages" , "private.php"],
		[$thread['title']   , NULL],
	);
	// New reply text
	$newxlinks = "<a href='sendprivate.php'>New conversation</a>";
	if (!$thread['closed']) {
		$newxlinks .= " - <a href='sendprivate.php?id={$_GET['id']}'>{$newreplypic}</a>";
	}
	$threadforumlinks = dobreadcrumbs($links, $newxlinks); 

	
	// Query elements
	$min      = $ppp * $_GET['page'];
	$searchon = "p.thread = {$_GET['id']}";
	
	// Workaround for the lack of scrollable cursors
	$layouts = $sql->query("SELECT p.headid, p.signid, p.cssid FROM pm_posts p WHERE {$searchon} ORDER BY p.id ASC LIMIT $min, $ppp");
	preplayouts($layouts);
	
	$showattachments = $config['allow-attachments'] || !$config['hide-attachments'];
	if ($showattachments) {
		$attachments = load_attachments($searchon, $min, $ppp, MODE_PM);
	}
	if ($config['enable-post-ratings']) {
		$ratings = load_ratings($searchon, $min, $ppp, MODE_PM);
	}
	
	// heh
	$posts = $sql->query(set_avatars_sql("
		SELECT 	p.id, p.thread, p.user, p.date, p.ip, p.noob, p.moodid, p.headid, p.signid, p.cssid,
				p.text$sfields, p.edited, p.editdate, p.options, p.tagval, p.deleted, 0 revision,
				u.id uid, u.name, $ufields, u.regdate{%AVFIELD%}
		FROM pm_posts p
		
		LEFT JOIN users u ON p.user = u.id
		{%AVJOIN%}
		WHERE {$searchon}
		ORDER BY p.id ASC
		LIMIT $min,$ppp
	"));
	
	$controls['ip'] = "";
	$tokenstr       = "&auth=".generate_token(TOKEN_MGET);
	for ($i = 0; $post = $sql->fetch($posts); ++$i) {
		$bg = $i % 2 + 1;

		// "?pid={$post['id']}"
		$controls['quote'] = "<a href=\"#{$post['id']}\">Link</a>";
		if (!$post['deleted'] && !$thread['closed']) {
			$controls['quote'] .= " | <a href='sendprivate.php?id={$_GET['id']}&postid={$post['id']}'>Quote</a>";
		}
		
		$controls['edit'] = '';
		if ($isadmin || (!$banned && $config['allow-pmthread-edit'] && !$post['deleted'] && $post['user'] == $loguser['id'])) {
			
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
		if ($config['enable-post-ratings']) {
			$post['showratings'] = true;
			if (isset($ratings[$post['id']])) {
				$post['rating'] = $ratings[$post['id']];
			}
		}
		
		$post['act']     = filter_int($act[$post['user']]);	
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