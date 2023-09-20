<?php
	
	require "lib/common.php";
	
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
	
	$_GET['pin']  = filter_int($_GET['pin']);
	$_GET['lpt']  = filter_int($_GET['lpt']);
	$_GET['end']  = filter_int($_GET['end']);	
	$_GET['hi']   = numrange(filter_int($_GET['hi']), PHILI_MIN, PHILI_MAX); // Highlight filter for Thread/User modes
	$_GET['warn'] = filter_bool($_GET['warn']); // Warn filter for Thread/User modes
	
	
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
	
	// Prepare the additional WHERE filters that can be applied on top of other modes
	$filterquery = "";
	$baseparams = "";
	if ($_GET['hi']) {
		$filterquery = "highlighted >= {$_GET['hi']}";
	}
	if ($_GET['warn']) {
		$filterquery .= ($filterquery ? " AND " : "")."warned != 0";
	}
	
	$baseparams = "id={$_GET['id']}";
	load_pm_thread($_GET['id']);
	
	// Replacement counter with the post filter
	if ($filterquery) {
		$thread['replies'] = $sql->resultq("SELECT COUNT(*) FROM pm_posts WHERE thread = {$_GET['id']}".($filterquery ? " AND {$filterquery}" : "")) - 1;
	}
	
	if ($access) {
		// Automatically move threads out of invalid folders upon access
		if (!valid_pm_folder($access['folder'], $loguser['id'])) {
			trigger_error("A PM thread was located in an invalid PM folder (user's name: {$loguser['name']} [#{$loguser['id']}]; folder #{$access['folder']}). The thread has been moved to the default folder.", E_USER_NOTICE);
			$access['folder'] = PMFOLDER_MAIN;
			$sql->query("UPDATE pm_access SET folder = ".PMFOLDER_MAIN." WHERE thread = {$_GET['id']} AND user = {$loguser['id']}");
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

	
	/*
		Thread controls
	*/
	$linklist = $fulledit = "";
	// Thread owner / admin actions
	if ($isadmin || ($loguser['id'] == $thread['user'] && $config['allow-pmthread-edit'])) {
		$link = "<a href='editpmthread.php?id={$_GET['id']}&auth=".generate_token(TOKEN_MGET)."&action";
		if ($isadmin) {
			if (isset($thread['error'])) {
				$linklist .= "<s>Close</s>";
			} else if (!$thread['closed']) {
				$linklist .= "$link=qclose'>Close</a>";
			} else {
				$linklist .= "$link=qunclose'>Open</a>";
			}
		}
		$fulledit = " -- <a href='editpmthread.php?id={$_GET['id']}'>Edit thread<a>";
	}
	// Moving a thread on a different folder should be always possible
	if ($access) { 
		if ($access['folder'] != PMFOLDER_TRASH) {
			$linklist .= ($linklist ? " - " : "")."<a href='editpmthread.php?id={$_GET['id']}&action=trashthread'>Trash</a>";
		}
		$linklist .= ($linklist ? " - " : "")."<a href='editpmthread.php?id={$_GET['id']}&action=movethread'>Move</a>";
		$head = "Thread options";
	} else {
		$head = "Sneak mode";
	}
	$modfeats = "<tr><td class='tdbgc fonts' colspan=2>{$head}: {$linklist} {$fulledit}</td></tr>";
	
	
	loadtlayout();
	
	$postlist = "
		<table class='table'>
		{$modfeats}
		{$forum_error}
	";
	
	// Query elements
	$min      = $ppp * $_GET['page'];
	$searchon = "p.thread = {$_GET['id']}";
		
	$sfields = postlayout_fields();
	$ufields = userfields();

	// heh
	$posts = $sql->getarray(set_avatars_sql("
		SELECT 	p.id, p.thread, p.user, p.date, p.ip, p.noob, p.moodid, p.headid, p.signid, p.cssid,
				p.text$sfields, p.editedby, p.editdate, p.deleted, p.deletedby, p.deletereason,
				p.nosmilies, p.nohtml, p.tagval, 0 revision,
				p.highlighted, p.highlighttext, p.warned, p.warntext,
				r.read tread, r.time treadtime,
				u.id uid, u.name, u.displayname, $ufields,
				".set_userfields('ue').", ".set_userfields('ud').", u.regdate{%AVFIELD%}
		FROM pm_posts p
		
		LEFT JOIN pm_threads     t ON p.thread = t.id
		LEFT JOIN pm_threadsread r ON t.id = r.tid AND r.uid = {$loguser['id']}
		LEFT JOIN users  u ON p.user      = u.id
		LEFT JOIN users ue ON p.editedby  = ue.id
		LEFT JOIN users ud ON p.deletedby = ud.id
		{%AVJOIN%}
		WHERE {$searchon}
		ORDER BY p.id ASC
		LIMIT $min,$ppp
	"));
	
	/*
		Handle top links, now that we fetched the posts
	*/
	
	// Barlinks
	$links = array(
		["Private messages" , "private.php"],
		[$thread['title']   , "showprivate.php?{$baseparams}"],
	);
	if ($_GET['hi'])
		$links[] = [$_GET['hi'] == PHILI_LOCAL ? "Thread highlights" : "Featured posts", null];
	if ($_GET['warn'])
		$links[] = ["Warnings", null];
	
	// Highlight navigation
	$highlights = [];
	$hprev = $hnext = null;
	if ($_GET['id'] && !$_GET['hi'] && $posts) {
		foreach ($posts as $post) {
			if ($post['highlighted']) {
				$highlights[] = $post['id'];
			}
		}
		$hprev = $sql->resultq("SELECT p.id FROM pm_posts p WHERE {$searchon} AND p.highlighted > ".PHILI_NONE." AND p.id < {$posts[0]['id']} ORDER BY p.id DESC LIMIT 1");
		$hnext = $sql->resultq("SELECT p.id FROM pm_posts p WHERE {$searchon} AND p.highlighted > ".PHILI_NONE." AND p.id > ".$posts[count($posts)-1]['id']." ORDER BY p.id ASC LIMIT 1");
	}
	
	// New reply text
	$newxlinks = [];

	if (!$_GET['hi']) {
		$newxlinks[] = "<a href='thread.php?{$baseparams}&hi=1'>View highlights</a>";
		if ($_GET['id']) {
			// Highlight navigation
			if ($hprev) $newxlinks[] = "<a href='?pid={$hprev}#{$hprev}' class='nobr'>Previous highlight</a>";
			if ($hnext) $newxlinks[] = "<a href='?pid={$hnext}#{$hnext}' class='nobr'>Next highlight</a>";
		}
	}
	if (!$_GET['warn'])
		$newxlinks[] = "<a href='?{$baseparams}&warn=1'>View warnings</a>";
	
	
	$newxlinks[] = "<a href='newpmthread.php'>New conversation</a>";
	if (!$thread['closed']) {
		$newxlinks[] = "<a href='newpmreply.php?id={$_GET['id']}'>{$newreplypic}</a>";
	}
	
	// Activity in the last day (to determine syndromes)
	$act = load_syndromes();
	preplayouts($posts);
		
	//--
	$postrange = get_id_range($posts, 'id');
	
	$showattachments = $config['allow-attachments'] || !$config['hide-attachments'];
	if ($showattachments) {
		$attachments = load_attachments($searchon, [], $postrange, MODE_PM);
	}
	hook_use('pm-extra-db', $searchon, $postrange);
	//--
	
	$controls['ip'] = "";
	$tokenstr       = "&auth=".generate_token(TOKEN_MGET);
	$bg = 0;
	$warnings_read = [];
	foreach ($posts as $post) {
		// For now rendering the post with the unread warning marks it as read.
		// This logic will either stay like this, or not.
		if ($post['user'] == $loguser['id'] && $post['warned'] == PWARN_WARN)
			$warnings_read[] = $post['id'];
			
		$bg = $bg % 2 + 1;
		
		
		$controls = ["<a href=\"?pid={$post['id']}#{$post['id']}\">Link</a>"];
		if (!$post['deleted'] && !$thread['closed']) {
			$controls[] = "<a href='newpmreply.php?id={$_GET['id']}&postid={$post['id']}'>Quote</a>";
		}
		
		if ($isadmin || (!$banned && $config['allow-pmthread-edit'] && !$loguser['editing_locked'] && !$post['deleted'] && $post['user'] == $loguser['id'])) {
			
        	if ($isadmin || !$thread['closed']) {
				$controls[] = "<a href='editpmpost.php?id={$post['id']}'>Edit</a>";
			}
			
			if ($post['deleted']) {
				if ($isadmin) {
					// Post peeking feature
					if ($post['id'] == $_GET['pin']) {
						$post['deleted'] = false;
						$controls[] = "<a href='?pid={$post['id']}{$navparam}#{$post['id']}'>Unpeek</a>";
					} else {
						$controls[] = "<a href='?pid={$post['id']}&pin={$post['id']}{$navparam}#{$post['id']}'>Peek</a>";
					}
				}
				$controls[] = "<a href='editpmpost.php?id={$post['id']}&action=delete'>Undelete</a>";
			} else {
				if ($ismod) {
					$controls[] = "<a href='editpmpost.php?id={$post['id']}&action=noob{$tokenstr}'>".($post['noob'] ? "Un" : "")."n00b</a>";
					//--
					if (can_edit_highlight($post))
						$controls[] = "<a href='editpmpost.php?id={$post['id']}&action=highlight&type=1{$tokenstr}'>".($post['highlighted'] ? "Unh" : "H")."ighlight</a>";
					$controls[] = "<a href='editpmpost.php?id={$post['id']}&action=warn{$tokenstr}'>".($post['warned'] ? "Unw" : "W")."arn</a>";
					//--
				}
				$controls[] = "<a href='editpmpost.php?id={$post['id']}&action=delete'>Delete</a>";
			}
			if ($sysadmin && $config['allow-post-deletion']) {
				$controls[] = "<a href='editpmpost.php?id={$post['id']}&action=erase'>Erase</a>";
			}
			
		}

		if ($isadmin) {
			$ip = htmlspecialchars($post['ip']);
			$controls[] = "IP: <a href='admin-ipsearch.php?ip={$ip}'>{$ip}</a>";
		}
		
		if ($showattachments && isset($attachments[$post['id']])) {
			$post['attach'] = $attachments[$post['id']];
		}
		hook_use_ref('pm-extra-fields', $post);
		// "new" indicator for individual posts
		$post['new'] = $post['date'] > $post['treadtime'];
		
		// Highlight arrow links
		if ($_GET['id'] && !$_GET['hi'] && $post['highlighted']) {
			$hkey = array_search($post['id'], $highlights);
			$post['highlightprev'] = $hkey ? "#".$highlights[$hkey-1] : ($hprev ? "showprivate.php?pid={$hprev}#{$hprev}" : null);
			$post['highlightnext'] = $hkey != count($highlights) - 1 ? "#".$highlights[$hkey+1] : ($hnext ? "showprivate.php?pid={$hnext}#{$hnext}" : null);
		}
			
		$post['act']     = filter_int($act[$post['user']]);	
		$postlist .= "<tr>".threadpost($post, $bg, MODE_PM, -1)."</tr>";
	}
	// Automark unread warnings
	if (count($warnings_read)) {
		$sql->query("UPDATE pm_posts SET warned = ".PWARN_WARNREAD." WHERE id IN (".implode(",", $warnings_read).")");
	}
	if ($access) {
		// Unread posts count
		$readdate = (int) $sql->resultq("SELECT `readdate` FROM `pm_foldersread` WHERE `user` = '{$loguser['id']}' AND folder = {$access['folder']}");
		if ($thread['lastpostdate'] > $readdate) {
			$sql->query("REPLACE INTO pm_threadsread SET `uid` = '{$loguser['id']}', `tid` = '{$thread['id']}', `time` = '".time()."', `read` = '1'");
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
			$sql->query("REPLACE INTO pm_foldersread VALUES ({$loguser['id']}, {$access['folder']}, ".time().")");
		}
	}
		
	// Strip _GET variables that can set the page number
	$query = preg_replace("'page=(\d*)'si", '', '?'.$_SERVER["QUERY_STRING"]);
	$query = preg_replace("'pid=(\d*)'si", "id={$_GET['id']}", $query);
	$query = preg_replace("'&{2,}'si", "&", $query);


	$pagelinks = pagelist($query, $thread['replies'] + 1, $ppp);

		
	pageheader("Private messages: ".htmlspecialchars($thread['title']));
	$barlinks = dobreadcrumbs($links, implode(" - ", $newxlinks)); 
	print "
		$barlinks
		<table width=100%><td align=left class='fonts'>$pagelinks</td><td align=right class='fonts'>$tlinks</table>
		{$postlist}
		<table class='table'>
		{$modfeats}
		</table>
		<table width=100%><td align=left class='fonts'>$pagelinks</td><td align=right class='fonts'>$tlinks</table>
		$barlinks";
	
	pagefooter();