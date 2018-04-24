<?php

	require_once 'lib/function.php';

	
	$_GET['id']	         = filter_int($_GET['id']); // Thread ID
	$_GET['pid']         = filter_int($_GET['pid']); // Post ID
	$_GET['pin']         = filter_int($_GET['pin']); // Selected post ID for peeking (when a post is soft deleted)
	$_GET['rev']         = filter_int($_GET['rev']); // Post revision of pinned post
	$_GET['user']        = filter_int($_GET['user']); // User ID (posts by user)
	$_GET['vact']        = filter_string($_GET['vact']); // Vote action
	$_GET['vote']        = filter_int($_GET['vote']); // Vote choice ID

	
	// Skip to last post/end thread
	$gotopost	= null;
	if (filter_int($_GET['lpt'])) {
		$gotopost = $sql->resultq("SELECT MIN(`id`) FROM `posts` WHERE `thread` = '{$_GET['id']}' AND `date` > '".intval($_GET['lpt'])."'");
	} elseif (filter_int($_GET['end']) || (filter_int($_GET['lpt']) && !$gotopost)) {
		$gotopost = $sql->resultq("SELECT MAX(`id`) FROM `posts` WHERE `thread` = '{$_GET['id']}'");
	}
	if ($gotopost) {
		return header("Location: ?pid={$gotopost}#{$gotopost}");
	}
	
	
	// Poll votes
	if ($loguser['id'] && $_GET['id'] && $_GET['vact']) {
		check_token($_GET['auth'], TOKEN_VOTE);
		load_thread($_GET['id']); // Prevent voting on restricted threads
				
		$pollid = get_poll_from_thread($_GET['id']);
		if (!$pollid) {
			errorpage("Could not vote, because this thread is not a poll.", 'index.php', 'the index page');
		}
		$res = vote_poll($pollid, $_GET['vote'], $loguser['id'], $_GET['vact']);
		if (!$res) {
			errorpage("Could not vote on this poll.", 'index.php', 'the index page');
		}
		die(header("Location: ?id={$_GET['id']}"));
	}

	$ppp	= get_ppp();
	

	// Linking to a post ID
	if ($_GET['pid']) {
		$_GET['id']         = get_thread_from_post($_GET['pid']);
		$numposts 	        = $sql->resultq("SELECT COUNT(*) FROM `posts` WHERE `thread` = '{$_GET['id']}' AND `id` < '{$_GET['pid']}'");
		$_GET['page'] 		= floor($numposts / $ppp);
		
		// Canonical page w/o ppp link (for bots)
		$meta['canonical']	= "thread.php?id={$_GET['id']}&page={$_GET['page']}";
	} else {
		$_GET['page']		= filter_int($_GET['page']);
	}
	$specialscheme = $specialtitle = NULL;
	
	if ($_GET['id']) {
		load_thread($_GET['id']);

		$specialscheme = $forum['specialscheme'];
		$specialtitle  = $forum['specialtitle'];
		
		$tlinks = array();
		
		if ($loguser['id']) {
			// Unread posts count
			$readdate = (int) $sql->resultq("SELECT `readdate` FROM `forumread` WHERE `user` = '{$loguser['id']}' AND `forum` = '{$forum['id']}'");
			
			if ($thread['lastpostdate'] > $readdate)
				$sql->query("REPLACE INTO threadsread SET `uid` = '{$loguser['id']}', `tid` = '{$thread['id']}', `time` = '".ctime()."', `read` = '1'");

			$unreadcount = $sql->resultq("
				SELECT COUNT(*) FROM `threads`
				WHERE `id` NOT IN (SELECT `tid` FROM `threadsread` WHERE `uid` = '{$loguser['id']}' AND `read` = '1')
				AND `lastpostdate` > '{$readdate}' AND `forum` = '{$forum['id']}'
			");
			
			if ($unreadcount == 0)
				$sql->query("REPLACE INTO forumread VALUES ( {$loguser['id']}, {$forum['id']}, ".ctime().")");
		
			// Favorites
			if ($sql->resultq("SELECT COUNT(*) FROM favorites WHERE user = {$loguser['id']} AND thread = {$_GET['id']}"))
				$tlinks[] = "<a href='forum.php?act=rem&thread={$_GET['id']}' class='nobr'>Remove from favorites</a>";
			else
				$tlinks[] = "<a href='forum.php?act=add&thread={$_GET['id']}' class='nobr'>Add to favorites</a>";
		}
		
		// Forum/Thread navigation
		$tnext = $sql->resultq("SELECT id FROM threads WHERE forum={$forum['id']} AND lastpostdate>{$thread['lastpostdate']} ORDER BY lastpostdate ASC LIMIT 1");
		if ($tnext) $tlinks[] = "<a href='?id={$tnext}' class='nobr'>Next newer thread</a>";
		$tprev = $sql->resultq("SELECT id FROM threads WHERE forum={$forum['id']} AND lastpostdate<{$thread['lastpostdate']} ORDER BY lastpostdate DESC LIMIT 1");
		if ($tprev) $tlinks[] = "<a href='?id={$tprev}' class='nobr'>Next older thread</a>";
		$tlinks = implode(' | ', $tlinks);

		// Description for bots
		$text = $sql->resultq("SELECT text FROM posts WHERE thread = {$_GET['id']}");
		$text = strip_tags(str_replace(array("[", "]", "\r\n"), array("<", ">", " "), $text));
		$text = ((strlen($text) > 160) ? substr($text, 0, 157) . "..." : $text);
		$text = str_replace("\"", "&quot;", $text);
		$meta['description'] = $text;

		// don't count bot views
		if (!$isbot) {
			$sql->query("UPDATE threads SET views = views + 1 WHERE id = {$_GET['id']}");
		}
		$windowtitle = "{$forum['title']}: {$thread['title']}";
		
	}
	else if ($_GET['user']) {
		// Posts by user
		$uname = $sql->resultq("SELECT name FROM users WHERE id={$_GET['user']}");
		if (!$uname) {
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			errorpage("User ID #{$_GET['user']} doesn't exist.","index.php",'the index page');
		}

		$thread['replies'] = $sql->resultq("SELECT COUNT(*) FROM posts WHERE user = {$_GET['user']}") - 1;
		$thread['title'] = "Posts by {$uname}";
		$windowtitle = "Posts by {$uname}";
		$forum['id'] = 0;
		$forum['title'] = "";
		$tlinks = '';
	}
	else {
		$meta['noindex'] = true; // prevent search engines from indexing what they can't access
		errorpage("No thread specified.","index.php",'the index page');
	}
	
	
	pageheader($windowtitle, $specialscheme, $specialtitle);

	if ($_GET['id'] && !$forum_error) {
		print "<table class='table'><td class='tdbg1 fonts center'>".onlineusers($forum, $thread)."</table>";
		$ismod = ismod($forum['id']);
	}	
	
	// Moderator options
	$modfeats = '';
	if ($_GET['id']) {
		if ($ismod) {
			$fulledit = "<a href='editthread.php?id={$_GET['id']}'>Edit thread<a>";
			$linklist = array();
			$link = "<a href='editthread.php?id={$_GET['id']}&auth=".generate_token(TOKEN_MGET)."&action";

			if (!$thread['sticky'])
				$linklist[] = "$link=qstick'>Stick</a>";
			else
				$linklist[] = "$link=qunstick'>Unstick</a>";

			if (!$thread['closed'])
				$linklist[] = "$link=qclose'>Close</a>";
			else
				$linklist[] = "$link=qunclose'>Open</a>";

			if ($thread['forum'] != $config['trash-forum'])
				$linklist[] = "<a href='editthread.php?id={$_GET['id']}&action=trashthread'>Trash</a>";

			//$linklist[] = "$link=delete'>Delete</a>";
			$linklist = implode(' | ', $linklist);
			$modfeats = "<tr><td class='tdbgc fonts' colspan=2>Moderating options: $linklist -- $fulledit</td></tr>";
		}
		else if ($loguser['id'] == $thread['user']) {
			// Allow users to rename their own thread
			$modfeats = "<tr><td class='tdbgc fonts' colspan=2>Thread options: <a href='editthread.php?id={$_GET['id']}'>Edit thread</a></td></tr>";
		}
	}

	$polltbl = "";
	if ($_GET['id'] && $forum['pollstyle'] != -2 && $thread['poll']) {
		if (load_poll($thread['poll'], $forum['pollstyle'])) {
			$polltbl = print_poll($poll, $thread, $forum['id']);
		}
	}

	loadtlayout();
	
	switch ($loguser['viewsig']) {
		case 1:  $sfields = ',p.headtext,p.signtext'; break;
		case 2:  $sfields = ',u.postheader headtext,u.signature signtext'; break;
		default: $sfields = ''; break;
	}
	$ufields = userfields();

	
	$act = load_syndromes();
	
	$postlist = "
		{$polltbl}
		<table class='table'>
		{$modfeats}
		{$forum_error}
	";

	$links = array(
		$forum['title']   => "forum.php?id={$forum['id']}",
		$thread['title']  => NULL,
	);
	// New Reply / Thread / Poll links
	$newxlinks = "";
	if ($_GET['id'] && $forum['id']) {
		if ($forum['pollstyle'] != -2) $newxlinks .= "<a href='newthread.php?poll=1&id={$forum['id']}'>{$newpollpic}</a> - ";
		else                           $newxlinks .= "{$nopollpic} - ";
		                               $newxlinks .= "<a href='newthread.php?id={$forum['id']}'>{$newthreadpic}</a>";
		if (!$thread['closed'])        $newxlinks .= " - <a href='newreply.php?id={$_GET['id']}'>{$newreplypic}</a>";
		else                           $newxlinks .= " - {$closedpic}";
	}
	$barlinks = dobreadcrumbs($links, $newxlinks); 

	
	// Query elements
	$min	= $ppp * $_GET['page'];
	
	if ($_GET['user']) $searchon = "p.user={$_GET['user']}";
	else               $searchon = "p.thread={$_GET['id']}";

	// Workaround for the lack of scrollable cursors
	$layouts = $sql->query("SELECT p.headid, p.signid FROM posts p WHERE {$searchon} ORDER BY p.id ASC LIMIT $min, $ppp");
	preplayouts($layouts);
	
	$showattachments = $config['allow-attachments'] || !$config['hide-attachments'];
	if ($showattachments) {
		$attachments = load_attachments($searchon, $min, $ppp);
	}
	if ($config['allow-avatar-storage']) {
		$avatars = load_avatars($searchon, $min, $ppp); 
	}
	
	// heh
	$posts = $sql->query("
		SELECT 	p.id, p.thread, p.user, p.date, p.ip, p.num, p.noob, p.moodid, p.headid, p.signid,
				p.text$sfields, p.edited, p.editdate, p.options, p.tagval, p.deleted, p.revision,
				u.id uid, u.name, $ufields, u.regdate
		FROM posts p
		
		LEFT JOIN users u ON p.user = u.id
		
		WHERE {$searchon}
		ORDER BY p.id
		LIMIT $min,$ppp
	");
	
	$controls['ip'] = "";
	for ($i = 0; $post = $sql->fetch($posts); ++$i) {
		$postlist	.= '<tr>';
		$bg = $i % 2 + 1;
		
		// link & quote
		$controls['quote'] = "<a href=\"?pid={$post['id']}#{$post['id']}\">Link</a>";
		if (!$post['deleted']) {
			if ($_GET['id'] && ! $thread['closed']) {
				$controls['quote'] .= " | <a href='newreply.php?id={$_GET['id']}&postid={$post['id']}'>Quote</a>";
			}
		}
		
		// Edit actions can only be done by a mod or the post author
		$controls['edit'] = '';
		if ($ismod || (!$banned && !$post['deleted'] && $post['user'] == $loguser['id'])) {
			$tokenstr = "&auth=".generate_token(TOKEN_MGET);
			
        	if ($ismod || ($_GET['id'] && !$thread['closed'])) {
				$controls['edit'] = " | <a href='editpost.php?id={$post['id']}'>Edit</a>";
			}
			
			if ($post['deleted']) {
				if ($ismod) {
					// Post peeking feature
					if ($post['id'] == $_GET['pin']) {
						$post['deleted'] = false;
						$controls['edit'] .= " | <a href='thread.php?pid={$post['id']}'>Unpeek</a>";
					} else {
						$controls['edit'] .= " | <a href='thread.php?pid={$post['id']}&pin={$post['id']}#{$post['id']}'>Peek</a>";
					}
				}
				$controls['edit'] .= " | <a href='editpost.php?id={$post['id']}&action=delete'>Undelete</a>";
			} else {
				$controls['edit'] .= " | <a href='editpost.php?id={$post['id']}&action=noob{$tokenstr}'>".($post['noob'] ? "Un" : "")."n00b</a>";
				$controls['edit'] .= " | <a href='editpost.php?id={$post['id']}&action=delete'>Delete</a>";
			}
			// Fetch the selected post revision
			if ($ismod && $post['id'] == $_GET['pin'] && $_GET['rev']) {
				$oldrev = $sql->fetchq("SELECT revdate, revuser, text, headtext, signtext, headid, signid FROM posts_old WHERE pid = {$_GET['pin']} AND revision = {$_GET['rev']}");
				if (!$oldrev) {
					$post['text'] = "<i>Post revision #{$_GET['rev']} not found.</i>";
					$post['headtext'] = $post['signtext'] = "";
					$post['headid']   = $post['signid']   = 0;
				} else {
					$post = array_merge($post, $oldrev);
				}
			}
			if ($sysadmin && $config['allow-post-deletion']) {
				$controls['edit'] .= " | <a href='editpost.php?id={$post['id']}&action=erase'>Erase</a>";
			}
			
		}

		if ($isadmin) {
			$controls['ip'] = " | IP: <a href='admin-ipsearch.php?ip={$post['ip']}'>{$post['ip']}</a>";
		}
		
		if ($showattachments && isset($attachments[$post['id']])) {
			$post['attach'] = $attachments[$post['id']];
		}


		$pforum		= NULL;
		$pthread	= NULL;
		if (!$_GET['id']) {
			// Enable caching for these
			$pthread = $sql->fetchq("SELECT id,title,forum FROM threads WHERE id={$post['thread']}", PDO::FETCH_ASSOC, mysql::USE_CACHE);
			$pforum  = $sql->resultq("SELECT minpower FROM forums WHERE id=".filter_int($pthread['forum']), 0, 0, mysql::USE_CACHE);
		}
		
		$post['act']     = filter_int($act[$post['user']]);
		if ($config['allow-avatar-storage']) {
			$post['piclink'] = filter_string($avatars[$post['user']][$post['moodid']]);
		}
		if (!$pforum || $pforum <= $loguser['powerlevel'])
			$postlist .= threadpost($post, $bg, $forum['id'], $pthread);
		else
			$postlist .= "<table class='table'><tr><td class='tdbg$bg fonts center'><i>(post in restricted forum)</i></td></tr></table>";
	}

	// Strip _GET variables that can set the page number
	$query = preg_replace("'page=(\d*)'si", '', '?'.$_SERVER["QUERY_STRING"]);
	$query = preg_replace("'pid=(\d*)'si", "id={$_GET['id']}", $query);
	$query = preg_replace("'&{2,}'si", "&", $query);
	//if ($query && substr($query, -1) != "&")
	//	$query	.= "&";

	$pagelinks = pagelist($query, $thread['replies'] + 1, $ppp);
	
	//print $header.sizelimitjs()."
	
	print "
		{$barlinks}
		<table width=100%><td align=left class='fonts'>$pagelinks</td><td align=right class='fonts'>$tlinks</table>
		{$postlist}
		<table class='table'>
		{$modfeats}
		</table>
		<table width=100%><td align=left class='fonts'>$pagelinks</td><td align=right class='fonts'>$tlinks</table>
		{$barlinks}";
	
	pagefooter();
	