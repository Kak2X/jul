<?php

	require_once "lib/common.php";


	// Main filters that stack on top of each other
	$_GET['id']	         = filter_int($_GET['id']); // Thread ID
	$_GET['user']        = filter_int($_GET['user']); // User ID (posts by user)
	$_GET['hi']          = numrange(filter_int($_GET['hi']), PHILI_MIN, PHILI_MAX); // Highlight filter for Thread/User modes
	$_GET['warn']        = filter_bool($_GET['warn']); // Warn filter for Thread/User modes
	// Additional filters (todo)...
	
	// Extra options
	$_GET['pid']         = filter_int($_GET['pid']); // Post ID
	$_GET['pin']         = filter_int($_GET['pin']); // Selected post ID for peeking (when a post is soft deleted)
	$_GET['rev']         = filter_int($_GET['rev']); // Post revision of pinned post
	
	
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

	$ppp	= get_ppp();

	// Linking to a post ID
	if ($_GET['pid']) {
		$_GET['id']         = get_thread_from_post($_GET['pid']);
		$numposts 	        = $sql->resultq("SELECT COUNT(*) FROM `posts` WHERE `thread` = '{$_GET['id']}' AND `id` < '{$_GET['pid']}'");
		$_GET['page'] 		= floor($numposts / $ppp);
		$_GET['hi'] = $_GET['warn'] = 0; // Not allowed
		
		
		// Canonical page w/o ppp link (for bots)
		$meta['canonical']	= "thread.php?id={$_GET['id']}&page={$_GET['page']}";
	} else {
		$_GET['page']		= filter_int($_GET['page']);
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
	
	$forum_error   = "";
	$multiforum    = false;
	$tlinks        = array();
	if ($_GET['id']) {
		load_thread($_GET['id'], true);
		
		// Description for bots
		$text = $sql->resultq("SELECT text FROM posts WHERE ".($_GET['pid'] ? "id = {$_GET['pid']}" : "thread = {$_GET['id']}"));
		$text = strip_tags(str_replace(array("[", "]", "\r\n"), array("<", ">", " "), $text));
		$text = ((strlen($text) > 160) ? substr($text, 0, 157) . "..." : $text);
		$text = str_replace("\"", "&quot;", $text);
		$meta['description'] = $text;
		
		// Replacement counter with the post filter
		if ($filterquery) {
			$thread['replies'] = $sql->resultq("SELECT COUNT(*) FROM posts WHERE thread = {$_GET['id']}".($filterquery ? " AND {$filterquery}" : "")) - 1;
		}

		// don't count bot views
		if (!$isbot) {
			$sql->query("UPDATE threads SET views = views + 1 WHERE id = {$_GET['id']}");
		}
		$windowtitle = htmlspecialchars($forum['title']).": ".htmlspecialchars($thread['title']);
		$baseparams = "id={$_GET['id']}"; // before extra filters
		$orderby = "p.id";
	}
	else if ($_GET['user']) {
		// Posts by user
		$uname = $sql->resultq("SELECT name FROM users WHERE id={$_GET['user']}");
		if (!$uname) {
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			errorpage("User ID #{$_GET['user']} doesn't exist.","index.php",'the index page');
		}

		$thread['replies'] = $sql->resultq("SELECT COUNT(*) FROM posts WHERE user = {$_GET['user']}".($filterquery ? " AND {$filterquery}" : "")) - 1;
		$thread['title'] = "Posts by {$uname}";
		$windowtitle = "Posts by ".htmlspecialchars($uname);
		$forum['id'] = 0;
		$forum['title'] = "";
		$multiforum = true; // Don't use single cache forum filter mode
		$baseparams = "user={$_GET['user']}";
		$orderby = "p.id";
		load_layout();
	}
	else if ($_GET['hi']) {
		// Featured posts list (global)
		$thread['replies'] = $sql->resultq("SELECT COUNT(*) FROM posts WHERE {$filterquery}") - 1;
		$thread['title'] = "Featured posts";
		$windowtitle = "Featured posts";
		$forum['id'] = 0;
		$forum['title'] = "";
		$multiforum = true; // Don't use single cache forum filter mode
		$orderby = "p.highlightdate DESC";
		load_layout();
	}
	else if ($_GET['warn']) {
		// Featured posts list (global)
		$thread['replies'] = $sql->resultq("SELECT COUNT(*) FROM posts WHERE {$filterquery}") - 1;
		$thread['title'] = "Warned posts";
		$windowtitle = "Warned posts";
		$forum['id'] = 0;
		$forum['title'] = "";
		$multiforum = true; // Don't use single cache forum filter mode
		$orderby = "p.warndate DESC"; // this is the other special one
		load_layout();
	}
	else {
		$meta['noindex'] = true; // prevent search engines from indexing what they can't access
		errorpage("No thread specified.","index.php",'the index page');
	}
	
	// Strip _GET variables that can set the page number
	$query = preg_replace("'page=(\d*)'si", '', '?'.$_SERVER["QUERY_STRING"]);
	$query = preg_replace("'pid=(\d*)'si", "id={$_GET['id']}", $query);
	$query = preg_replace("'&{2,}'si", "&", $query);
	//if ($query && substr($query, -1) != "&")
	//	$query	.= "&";

	$pagelinks = pagelist($query, $thread['replies'] + 1, $ppp);
	
	if ($_GET['id'] && !$forum_error) {
		$ismod = ismod($forum['id']);
	}	
	
	// Moderator options
	$modfeats = '';
	if ($_GET['id']) {
		if ($ismod) {
			$fulledit = "<a href='editthread.php?id={$_GET['id']}'>Edit thread<a>";
			
			// action key => label for QUICKMOD ACTIONS ONLY (instant, no confirmation) which is easier to support with hooks
			$actions = [];
			$actions[] = !$thread['sticky'] ? ['qstick', "Stick"] : ['qunstick', "Unstick"];
			$actions[] = !$thread['closed'] ? ['qclose', "Close"] : ['qunclose', "Open"];
			hook_use_ref('thread-quickmod-link', $actions);

			if ($thread['forum'] != $config['trash-forum'])
				$trash = " | <a href='editthread.php?id={$_GET['id']}&action=trashthread'>Trash</a>";
			else
				$trash = "";
			
			$baselink = "<a href='editthread.php?id={$_GET['id']}&auth=".generate_token(TOKEN_MGET)."&action=";
			$linklist = "";
			foreach ($actions as $action) {
				$linklist .= (($linklist !== "") ? " | " : "")."{$baselink}{$action[0]}'>{$action[1]}</a>";
			}
			$modfeats = "<tr><td class='tdbgc fonts' colspan='2'>Moderating options: {$linklist}{$trash} -- {$fulledit}</td></tr>";
		}
		else if ($loguser['id'] == $thread['user']) {
			// Allow users to rename their own thread
			$modfeats = "<tr><td class='tdbgc fonts' colspan=2>Thread options: <a href='editthread.php?id={$_GET['id']}'>Edit thread</a></td></tr>";
		}
	}

	$polltbl = "";
	if ($_GET['id'] && $forum['pollstyle'] != -2 && $thread['poll']) {
		if (load_poll($thread['poll'], $forum['pollstyle'])) {
			$polltbl = print_poll($poll, $thread, $forum['id'])."<br/>";
		}
	}

	loadtlayout();
	
	switch ($loguser['viewsig']) {
		case 1:  $sfields = ',p.headtext,p.signtext,p.csstext'; break;
		case 2:  $sfields = ',u.postheader headtext,u.signature signtext,u.css csstext'; break;
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

	// Query elements
	$min	= $ppp * $_GET['page'];
	
	$searchon = "";
	if ($_GET['user'])
		$searchon .= "p.user={$_GET['user']}";
	else if ($_GET['id']) 
		$searchon .= "p.thread={$_GET['id']}";
	if ($filterquery)
		$searchon .= ($searchon ? " AND " : "").$filterquery;
	
	// each posts can potentially come from a different forum
	// we must get these from the query in "Show Posts" mode
	$trfield = $trjoin = "";
	if (!$_GET['id']) {
		$trfield = ", r.read tread, r.time treadtime, f.readdate freadtime";
		$trjoin = "
		LEFT JOIN threads     t ON p.thread = t.id
		LEFT JOIN threadsread r ON t.id = r.tid AND r.uid = {$loguser['id']}
		LEFT JOIN forumread   f ON t.forum = f.forum AND f.user = {$loguser['id']}
		";
	}
	
	// heh
	$posts = $sql->getarray(set_avatars_sql("
		SELECT 	p.id, p.thread, p.user, p.date, p.ip, p.num, p.noob, p.moodid, p.headid, p.signid, p.cssid,
				p.text$sfields, p.edited, p.editdate, p.nosmilies, p.nohtml, p.tagval, p.deleted, p.revision,
				p.highlighted, p.highlighttext, p.warned, p.warntext,
				u.id uid, u.name, $ufields, u.regdate{%AVFIELD%}$trfield
		FROM posts p
		
		LEFT JOIN users u ON p.user = u.id
		$trjoin
		{%AVJOIN%}
		
		WHERE {$searchon}
		ORDER BY {$orderby}
		LIMIT $min,$ppp
	"));
	
	/*
		Handle top links, now that we fetched the posts
	*/
	
	// Barlinks
	$links = [];
	if ($forum['title']) // Doesn't always exist, so avoid a blank link
		$links[] = [$forum['title'], "forum.php?id={$forum['id']}"];
	if ($_GET['id'] || $_GET['user']) // Ignore in raw featured posts mode
		$links[] = [$thread['title'], "thread.php?{$baseparams}"];
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
		$hprev = $sql->resultq("SELECT p.id FROM posts p WHERE {$searchon} AND p.highlighted > ".PHILI_NONE." AND p.id < {$posts[0]['id']} ORDER BY p.id DESC LIMIT 1");
		$hnext = $sql->resultq("SELECT p.id FROM posts p WHERE {$searchon} AND p.highlighted > ".PHILI_NONE." AND p.id > ".$posts[count($posts)-1]['id']." ORDER BY p.id ASC LIMIT 1");
	}
	
	// New Reply / Thread / Poll links
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
		$newxlinks[] = "<a href='thread.php?{$baseparams}&warn=1'>View warnings</a>";
	
	if ($_GET['id'] && $forum['id']) {
		if ($forum['pollstyle'] != -2) $newxlinks[] = "<a href='newthread.php?poll=1&id={$forum['id']}'>{$newpollpic}</a>";
		else                           $newxlinks[] = "{$nopollpic}";
		                               $newxlinks[] = "<a href='newthread.php?id={$forum['id']}'>{$newthreadpic}</a>";
		if (!$thread['closed'])        $newxlinks[] = "<a href='newreply.php?id={$_GET['id']}'>{$newreplypic}</a>";
		else                           $newxlinks[] = "{$closedpic}";
	}
	
	// Thread links
	if ($_GET['id']) {
		if ($loguser['id']) {
			// Unread posts count (base value, for forum)
			$readdate = (int) $sql->resultq("SELECT `readdate` FROM `forumread` WHERE `user` = '{$loguser['id']}' AND `forum` = '{$forum['id']}'");
		
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
	}
	$tlinks = implode(' | ', $tlinks);
	
	//--
	if (!count($posts)) {
		$postlist .= "<table class='table'><tr><td class='tdbg1 center'>";
		if ($pagelinks)
			$postlist .= "There are no posts in this page. Please select a valid thread page:<br>$pagelinks";
		else if ($_GET['hi'])
			$postlist .= "No highlights found.";
		else
			$postlist .= "No posts found.";
		$postlist .= "</td></tr></table>";
	} else {
		if ($_GET['pin'] && $_GET['rev']) {
			$oldrev = $sql->fetchq("SELECT revdate, revuser, text, headtext, signtext, csstext, headid, signid, cssid FROM posts_old WHERE pid = {$_GET['pin']} AND revision = {$_GET['rev']}");
		} else {
			$oldrev = null;
		}
		
		preplayouts($posts, $oldrev);
		
		// Prepare the forum/thread cache for multiforum mode.
		// This will be used for multiforum filters and view permission checks.
		if ($multiforum) {
			$tids = implode(",",array_unique(array_column($posts, 'thread'))); 
			$pthread = $sql->fetchq("
				SELECT DISTINCT t.id tidx, t.id tid, t.title, t.forum, f.minpower, f.login
				FROM threads t
				LEFT JOIN forums f ON t.forum = f.id
				WHERE t.id IN ($tids)
			", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
			prepare_filters(array_column($posts, 'forum'));
		}
			

		$postrange = get_id_range($posts, 'id');
	
		$showattachments = $config['allow-attachments'] || !$config['hide-attachments'];
		if ($showattachments) {
			$attachments = load_attachments($searchon, $postrange);
		}
		hook_use('post-extra-db', $searchon, $postrange);
		
		$curpthread	= NULL;
		$controls['ip'] = "";
		$bg = 0;
		$warnings_read = [];
		foreach ($posts as $post) {
			
			// For now rendering the post with the unread warning marks it as read.
			// This logic will either stay like this, or not.
			if ($post['user'] == $loguser['id'] && $post['warned'] == PWARN_WARN)
				$warnings_read[] = $post['id'];
			
			$bg = $bg % 2 + 1;
			
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
				
				// Non-mods can edit the post as long as the thread isn't closed.
				if ($ismod || ($_GET['id'] && !$thread['closed'])) {
					$controls['edit'] = " | <a href='editpost.php?id={$post['id']}'>Edit</a>";
				}
				
				// If a post is deleted, the author can undelete it (and a mod can silently peek it)
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
					if ($ismod) {
						$controls['edit'] .= " | <a href='editpost.php?id={$post['id']}&action=noob{$tokenstr}'>".($post['noob'] ? "Un" : "")."n00b</a>";
						//--
						if (can_edit_highlight($post))
							$controls['edit'] .= " | <a href='editpost.php?id={$post['id']}&action=highlight&type=1{$tokenstr}'>".($post['highlighted'] ? "Unh" : "H")."ighlight</a>";
						$controls['edit'] .= " | <a href='editpost.php?id={$post['id']}&action=warn{$tokenstr}'>".($post['warned'] ? "Unw" : "W")."arn</a>";
						//--
					}
					$controls['edit'] .= " | <a href='editpost.php?id={$post['id']}&action=delete'>Delete</a>";
				}
				// Fetch the selected post revision
				if ($ismod && $post['id'] == $_GET['pin'] && $_GET['rev']) {
					if (!$oldrev) {
						$post['text'] = "(Post revision #{$_GET['rev']} not found)";
						$post['headtext'] = $post['signtext'] = $post['csstext'] = "";
						$post['headid']   = $post['signid']   = $posr['cssid']   = 0;
					} else {
						$post  = array_merge($post, $oldrev);
					}
				}
				
				// Danger zone
				if ($sysadmin && $config['allow-post-deletion']) {
					$controls['edit'] .= " | <a href='editpost.php?id={$post['id']}&action=erase'>Erase</a>";
				}
				
			}

			if ($isadmin) {
				$ip = htmlspecialchars($post['ip']);
				$controls['ip'] = " | IP: <a href=\"admin-ipsearch.php?ip={$ip}\">{$ip}</a>";
			}
			
			if ($showattachments && isset($attachments[$post['id']])) {
				$post['attach'] = $attachments[$post['id']];
			}
			hook_use_ref('post-extra-fields', $post);
			
			// Logged in users get the "new" indicator for individual posts
			if ($loguser['id']) {
				if ($_GET['id']) {
					$threadread = $thread['treadtime'] ? $thread['treadtime'] : $readdate;
				} else {
					$threadread = $post['treadtime'] ? $post['treadtime'] : $post['freadtime'];
				}
				$post['new'] = $post['date'] > $threadread;
			}
			
			// Highlight arrow links
			if ($_GET['id'] && !$_GET['hi'] && $post['highlighted']) {
				$hkey = array_search($post['id'], $highlights);
				$post['highlightprev'] = $hkey ? "#".$highlights[$hkey-1] : ($hprev ? "thread.php?pid={$hprev}#{$hprev}" : null);
				$post['highlightnext'] = $hkey != count($highlights) - 1 ? "#".$highlights[$hkey+1] : ($hnext ? "thread.php?pid={$hnext}#{$hnext}" : null);
			}
			
			if ($multiforum) {
				$curpthread = $pthread[$post['thread']];
				if (!can_view_forum($curpthread)) {
					$postlist .= "<table class='table'><tr><td class='tdbg$bg fonts center'><i>(post in restricted forum)</i></td></tr></table>";
					continue;
				}
				$forum['id'] = $curpthread['forum'];
			}
			$post['act']     = filter_int($act[$post['user']]);		
			$postlist .= threadpost($post, $bg, MODE_POST, $forum['id'], $curpthread, $multiforum);
				
		}
		
		// Unread posts count
		if ($_GET['id'] && $loguser['id']) {
			
			// don't mark thread read info when the last post isn't read
			// nice shortcut as we don't have something like postsread, so we can't really track the read status for specific posts
			// of course this won't work when viewing posts from newest to oldest
			if ($post['new']) {
				$targetdate = $post['date'] == $thread['lastpostdate'] ? time() : $post['date'];
				
				$sql->query("REPLACE INTO threadsread SET `uid` = '{$loguser['id']}', `tid` = '{$thread['id']}', `time` = '{$targetdate}', `read` = '1'");
				
				// When all threads without specific threadsread info have a lower last post date than the forumread date,
				// the forumread date can be updated
				$unreadcount = $sql->resultq("
					SELECT COUNT(*) FROM `threads`
					WHERE `id` NOT IN (SELECT `tid` FROM `threadsread` WHERE `uid` = '{$loguser['id']}' AND `read` = '1')
					AND `lastpostdate` > '{$readdate}' AND `forum` = '{$forum['id']}'
				");
			
				if ($unreadcount == 0) {
					$sql->query("REPLACE INTO forumread VALUES ( {$loguser['id']}, {$forum['id']}, {$targetdate})");
				}
			}
		}
		
		// Automark unread warnings
		if (count($warnings_read)) {
			$sql->query("UPDATE posts SET warned = ".PWARN_WARNREAD." WHERE id IN (".implode(",", $warnings_read).")");
		}
	}
	pageheader($windowtitle);
	$barlinks = dobreadcrumbs($links, implode(" - ", $newxlinks)); 
	
	if ($_GET['id'] && !$forum_error) {
		print "<table class='table'><td class='tdbg1 fonts center'>".onlineusers($forum, $thread)."</table>";
	}	
	
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
	