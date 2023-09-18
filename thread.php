<?php

	require "lib/common.php";
	require "lib/search.php";
	
	$_GET['id']	         = filter_int($_GET['id']); // Thread ID
	
	//--
	// Skip to last post/end thread
	$_GET['lpt'] = filter_int($_GET['lpt']);
	$_GET['end'] = filter_bool($_GET['end']);
	
	$gotopost	= null;
	if ($_GET['lpt'])
		$gotopost = $sql->resultq("SELECT MIN(`id`) FROM `posts` WHERE `thread` = '{$_GET['id']}' AND `date` > '{$_GET['lpt']}'");
	if ($_GET['end'] || ($_GET['lpt'] && !$gotopost))
		$gotopost = $sql->resultq("SELECT MAX(`id`) FROM `posts` WHERE `thread` = '{$_GET['id']}'");
	if ($gotopost)
		return header("Location: ?pid={$gotopost}#{$gotopost}");
	//--
	
	// Layout mode
	$_GET['mode']        = filter_string($_GET['mode']); // ("", "user", "hi", "warn", "search")
	
	// Main filters that stack on top of each other	
	$_GET['user']        = filter_int($_GET['user']); // User ID (posts by user)
	$_GET['hi']          = numrange(filter_int($_GET['hi']), PHILI_MIN, PHILI_MAX); // Highlight filter for Thread/User modes
	$_GET['warn']        = filter_bool($_GET['warn']); // Warn filter for Thread/User modes
	$_GET['text']        = filter_string($_GET['text']); // Posts text
	$_GET['title']       = filter_string($_GET['title']); // Thread title (only makes sense in non-id mode)
	$_GET['wtxt']        = filter_string($_GET['wtxt']); // Highlight text
	$_GET['htxt']        = filter_string($_GET['htxt']); // Warning text
	
	$_GET['ipmask']      = filter_string($_GET['ipmask']); // IP Mask (admin-only)
	$_GET['forum']       = filter_int($_GET['forum']); // Forum filter (only makes sense in non-id mode)
	
	$_GET['date']        = filter_int($_GET['date']); // Date mode 
	$_GET['datedays']    = filter_int($_GET['datedays']); // Search in the last X days
	$datefrom            = fieldstotimestamp('f', '_GET'); // Date Range - From
	$dateto              = fieldstotimestamp('t', '_GET', true); // Date Range - To
	$_GET['order']       = filter_int($_GET['order']); // Post order
	$_GET['dir']         = filter_int($_GET['dir']); // Post direction
	
	// 'id' and 'hi' are the only two fields that don't trigger search mode.
	// In search mode, posts aren't marked as read.
	$search_mode = $_GET['warn'] || $_GET['user'] || $_GET['text'] || $_GET['title'] || $_GET['htxt'] || $_GET['wtxt'] || $_GET['ipmask'] || $_GET['forum'] || $_GET['date'] || $_GET['datedays'] || $datefrom || $dateto || $_GET['order'] || $_GET['dir'];
	// Also keep track if there are any filters outside of $_GET['id'], since some optimizations can be made
	$nonid_filters = $_GET['hi'] || $search_mode;
	
	// Option modifiers
	$_GET['pid']         = filter_int($_GET['pid']); // Post ID
	$_GET['pin']         = filter_int($_GET['pin']); // Selected post ID for peeking (when a post is soft deleted)
	$_GET['rev']         = filter_int($_GET['rev']); // Post revision of pinned post
	$ppp	             = get_ppp();                // Posts/page

	// Immediately attempt to retrieve the thread ID from the post
	// Unless the "no id (auto)filter" flag is set, which is the case for highlight navigation.
	if ($_GET['pid'] && !isset($_GET['nif'])) {
		$_GET['id'] = get_thread_from_post($_GET['pid']);
	}
	
	/*
		Prepare the additional query filters that can be applied on top of other modes
	*/
	$qwhere = [];
	$qvals  = [];
	
	if ($_GET['id']) {
		$qwhere[]    = "p.thread = ?";
		$qvals[]     = $_GET['id'];
	}
	if ($_GET['user']) {
		$qwhere[]    = "p.user = ?";
		$qvals[]     = $_GET['user'];
	}
	if ($_GET['hi']) {
		$qwhere[]    = "p.highlighted >= ?";
		$qvals[]     = $_GET['hi'];
	}
	if ($_GET['warn']) {
		$qwhere[]    = "p.warned != 0";
	}
	if ($_GET['text']) {
		if (!parse_search($_GET['text'], "p.text", $qwhere, $qvals))
			errorpage("Search failure: ".htmlspecialchars($_GET['text']));
	}
	if ($_GET['title']) {
		if (!parse_search($_GET['title'], "t.title", $qwhere, $qvals))
			errorpage("Search failure: ".htmlspecialchars($_GET['title']));
	}
	if ($_GET['htxt']) {
		if (!parse_search($_GET['htxt'], "p.highlighttext", $qwhere, $qvals))
			errorpage("Search failure: ".htmlspecialchars($_GET['htxt']));
	}
	if ($_GET['wtxt']) {
		if (!parse_search($_GET['wtxt'], "p.warntext", $qwhere, $qvals))
			errorpage("Search failure: ".htmlspecialchars($_GET['wtxt']));
	}
	if ($_GET['forum']) {
		$qwhere[]   = "t.forum = ?";
		$qvals[]    = $_GET['forum'];
	}
	switch ($_GET['date']) {
		case SDATE_LAST:
			$qwhere[]   = "p.date > ?";
			$qvals[]    = time() - $_GET['datedays'] * 86400;
			break;
		case SDATE_RANGE:
			$qwhere[]   = "p.date > ? AND p.date < ?";
			$qvals[]    = $datefrom;
			$qvals[]    = $dateto;
			break;
	}
	if ($isadmin) {
		if ($_GET['ipmask']) {
			$qwhere[]   = "p.ip LIKE ?";
			$qvals[]    = str_replace('*', '%', $_GET['ipmask']);
		}
	}	
	$qwhere = implode(" AND ", $qwhere);
	
	/*
		ORDER BY
	*/
	if ($_GET['order']) {
		if (!isset(ORDER_FIELDS[$_GET['order']]))
			errorpage("Nice try, but no.");
		$orderby = ORDER_FIELDS[$_GET['order']];
	} else {
		$orderby = "p.id";
	}
	
	if ($_GET['dir']) {
		$orderdir = ($_GET['dir'] == 2 ? "DESC" : "ASC");
	} else {
		$orderdir = "ASC";
	}
	
	// Strip _GET variables that can set the page number
	$query = preg_replace("'page=(\d*)'si", '', '?'.$_SERVER["QUERY_STRING"]);
	$query = preg_replace("'&?pid=(\d*)'si", isset($_GET['nif']) ? "" : "id={$_GET['id']}", $query);
	$query = preg_replace("'&?nif=(\d*)'si", "", $query);
	$query = preg_replace("'&{2,}'si", "&", $query);
	
	/*
		Display mode options.
	*/
	
	// The thread ID filter has its own special logic that influences everything else.
	// So it sits here, before all of the other modifiers.
	$multiforum = !$_GET['id']; // && !$_GET['forum'];
	$listpre    = "";
	if ($_GET['id']) {
		load_thread($_GET['id'], true);
		$totalposts = $thread['replies'] + 1;
		// don't count bot views
		if (!$isbot) {
			$sql->query("UPDATE threads SET views = views + 1 WHERE id = {$_GET['id']}");
		}
		$windowtitle = "{$forum['title']}: {$thread['title']}";
		$bartitle    = $thread['title'];
		$baseurl     = "?id={$_GET['id']}";
	} else {
		load_layout();
		$forum_error = "";
		$windowtitle = $bartitle = "Custom filter";
		$baseurl     = $query;
		$forum       = ['id' => 0, 'title' => ""]; // No forum part in barlinks
	}
	// With additional filters, we can't use $thread['replies'] + 1 as the number of posts.
	if (!$_GET['id'] || $nonid_filters) {
		$totalposts = $sql->resultp("
			SELECT COUNT(*) 
			FROM posts p 
			LEFT JOIN threads t ON p.thread = t.id 
			".($qwhere ? "WHERE {$qwhere}" : ""), $qvals);
	}
	
	// Display mode overrides
	switch ($_GET['mode']) {
		case "user":
			if (!$_GET['user'])
				errorpage("No user ID specified.","index.php",'the index page');
			$uname = $sql->resultq("SELECT name FROM users WHERE id={$_GET['user']}");
			if (!$uname) {
				$meta['noindex'] = true; // prevent search engines from indexing what they can't access
				errorpage("User ID #{$_GET['user']} doesn't exist.","index.php",'the index page');
			}
			$bartitle = $windowtitle = "Posts by {$uname}";
			$baseurl  = "?mode=user&user={$_GET['user']}";
			break;
		case "warn":
			if (!$_GET['warn'])
				errorpage("No warn filter specified.","index.php",'the index page');
			if (!$_GET['order']) {
				$orderby = "p.warndate";
				$orderdir = "DESC";
			}
			$bartitle = $windowtitle = "Warned posts";
			$baseurl  = "?mode=warn&warn={$_GET['warn']}";
			break;
		case "hi":
			if (!$_GET['hi'])
				errorpage("No highlight filter specified.","index.php",'the index page');
			if (!$_GET['order']) {
				$orderby = "p.highlightdate";
				$orderdir = "DESC";
			}
			$bartitle = $windowtitle = $_GET['hi'] == PHILI_LOCAL ? "Thread highlights" : "Featured posts";
			$baseurl  = "?mode=hi&hi={$_GET['hi']}";
			break;
		case "search":
			if (!$_GET['id'])
				$bartitle = $windowtitle = "Search results";
			$listpre .= post_search_table()."<br/>";
			break;
	}
	
	
	/*
		Page number & selection (after the order by overrides kick in)
	*/
	if ($_GET['pid']) {
		$numposts 	        = $sql->resultp("
			SELECT COUNT(*)
			FROM posts p 
			LEFT JOIN threads t ON p.thread = t.id
			WHERE p.id ".($orderdir == "ASC" ? "<" : ">")." '{$_GET['pid']}' ".($qwhere ? " AND {$qwhere}" : "")."
			ORDER BY {$orderby} {$orderdir}
		", $qvals);
		$_GET['page'] 		= floor($numposts / $ppp);
		// Canonical page w/o ppp link (for bots)
		$meta['canonical']	= "thread.php?id={$_GET['id']}&page={$_GET['page']}";
	} else {
		$_GET['page']		= filter_int($_GET['page']);
	}
	
	/*
		Moderator options
	*/
	if ($_GET['id'] && !$forum_error) {
		$ismod = ismod($forum['id']);
	}
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

	/*
		Extra thread option flags (for now, only the poll marker)
	*/
	if ($_GET['id'] && $forum['pollstyle'] != -2 && $thread['poll']) {
		if (load_poll($thread['poll'], $forum['pollstyle'])) {
			$listpre .= print_poll($poll, $thread, $forum['id'])."<br/>";
		}
	}


	/*
		Query elements
	*/
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
	
	loadtlayout();
	$sfields = postlayout_fields();
	$ufields = userfields();
	$min	 = $ppp * $_GET['page'];
	
	// heh
	$posts = $sql->fetchp(set_avatars_sql("
		SELECT 	p.id, p.thread, p.user, p.date, p.ip, p.num, p.noob, p.moodid, p.headid, p.signid, p.cssid,
				p.text{$sfields}, p.edited, p.editdate, p.nosmilies, p.nohtml, p.tagval, p.deleted, p.revision,
				p.highlighted, p.highlighttext, p.warned, p.warntext,
				u.id uid, u.name, $ufields, u.regdate{%AVFIELD%}{$trfield}
		FROM posts p
		
		LEFT JOIN users u ON p.user = u.id
		{$trjoin}
		{%AVJOIN%}
		
		".($qwhere ? "WHERE {$qwhere}" : "")."
		ORDER BY {$orderby} {$orderdir}
		LIMIT {$min},{$ppp}
	"), $qvals, PDO::FETCH_ASSOC, mysql::FETCH_ALL);
	
	/*
		Handle top links, now that we fetched the posts
	*/
	
	// Barlinks
	$links = [];
	if ($forum['id'] || $forum['title']) // Doesn't always exist, so avoid a blank link
		$links[] = [$forum['title'], "forum.php?id={$forum['id']}"];
	$links[] = [$bartitle, $baseurl];
	if ($_GET['id'] || $_GET['user']) { // For convenience
		if ($_GET['hi'])
			$links[] = [$_GET['hi'] == PHILI_LOCAL ? "Thread highlights" : "Featured posts", null];
		if ($_GET['warn'])
			$links[] = ["Warnings", null];
	}
	
	// Highlight navigation
	$highlights = [];
	$hprev = $hnext = null;
	if (!$_GET['hi'] && $posts) {
		
		// Build a list of in-page highlights.
		// These are order-independent since they go off array index.
		foreach ($posts as $post) {
			if ($post['highlighted']) {
				$highlights[] = $post['id'];
			}
		}
		
		// With the ones linking to other pages, it's a different story.
		// -> The "Next Highlight" link should always increase the page number.
		// -> The "Previous Highlight" link should always decrease the page number.
		
		
		// The two queries attempt to find the highlighted post IDs immediately before and after the first and last post in the page.
		// Where these posts are depends on the sort direction.
		if ($orderdir == "ASC") { // default order
			$firstpostid = $posts[0]['id'];
			$lastpostid  = $posts[count($posts)-1]['id'];
		} else {
			$firstpostid = $posts[count($posts)-1]['id'];
			$lastpostid  = $posts[0]['id'];
		}
		
		// For hprev, order by the opposite of $orderdir
		$hprev = $sql->resultp("
			SELECT p.id
			FROM posts p
			LEFT JOIN threads t ON p.thread = t.id
			LEFT JOIN users   u ON p.user   = u.id
			WHERE ".($qwhere ? "{$qwhere} AND " : "")." 
			  p.highlighted > ".PHILI_NONE." 
			  AND p.id < {$firstpostid} 
			ORDER BY {$orderby} ".($orderdir == "ASC" ? "DESC" : "ASC")."
			LIMIT 1
		", $qvals);
		$hnext = $sql->resultp("
			SELECT p.id
			FROM posts p
			LEFT JOIN threads t ON p.thread = t.id
			LEFT JOIN users   u ON p.user   = u.id
			WHERE ".($qwhere ? "{$qwhere} AND " : "")."
			  p.highlighted > ".PHILI_NONE."
			  AND p.id > {$lastpostid}
			ORDER BY {$orderby} {$orderdir}
			LIMIT 1
		", $qvals);
		
		// Switch around to account for the inverted order
		if ($orderdir == "DESC") {
			$welp = $hprev;
			$hprev = $hnext;
			$hnext = $welp;
		}
	}
	
	// New Reply / Thread / Poll links
	$newxlinks = [];
	if ($_GET['mode'] != 'search')
		$newxlinks[] = "<a href='{$baseurl}&mode=search'>Search</a>";
	if (!$_GET['hi']) {
		$newxlinks[] = "<a href='{$baseurl}&hi=1'>View highlights</a>";
		// Highlight navigation
		if ($hprev) $newxlinks[] = "<a href='{$query}&nif=1&pid={$hprev}#{$hprev}' class='nobr'>Previous highlight</a>";
		if ($hnext) $newxlinks[] = "<a href='{$query}&nif=1&pid={$hnext}#{$hnext}' class='nobr'>Next highlight</a>";
	}
	if (!$_GET['warn'])
		$newxlinks[] = "<a href='{$baseurl}&warn=1'>View warnings</a>";
	
	if ($_GET['id'] && $forum['id']) {
		if ($forum['pollstyle'] != -2) $newxlinks[] = "<a href='newthread.php?poll=1&id={$forum['id']}'>{$newpollpic}</a>";
		else                           $newxlinks[] = "{$nopollpic}";
		                               $newxlinks[] = "<a href='newthread.php?id={$forum['id']}'>{$newthreadpic}</a>";
		if (!$thread['closed'])        $newxlinks[] = "<a href='newreply.php?id={$_GET['id']}'>{$newreplypic}</a>";
		else                           $newxlinks[] = "{$closedpic}";
	}
	
	// Thread links
	$tlinks = [];
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
	$pagelinks = pagelist($query, $totalposts, $ppp);
	
	//--
	$postlist = "
		{$listpre}
		<table class='table'>
		{$modfeats}
		{$forum_error}
	";
	if (!$posts || !count($posts)) {
		$postlist .= "<table class='table'><tr><td class='tdbg1 center'>";
		if ($pagelinks)
			$postlist .= "There are no posts in this page. Please select a valid thread page:<br>$pagelinks";
		else if ($_GET['hi'])
			$postlist .= "No highlights found.";
		else
			$postlist .= "No posts found.";
		$postlist .= "</td></tr></table>";
	} else {
		
		// Old post revision info, replacing whatever it has
		if ($_GET['pin'] && $_GET['rev']) {
			$oldrev = $sql->fetchq("SELECT revdate, revuser, text, headtext, signtext, csstext, headid, signid, cssid FROM posts_old WHERE pid = {$_GET['pin']} AND revision = {$_GET['rev']}");
		} else {
			$oldrev = null;
		}
		
		// Description for bots, without having to execute another query
		$text = null;
		if ($_GET['pid']) {
			$text = extract_match($posts, 'id', $_GET['pid'], 'text');
		} else if ($_GET['id']) {
			$text = $posts[0]['text'];
		}
		if ($text) {
			$text = strip_tags(str_replace(array("[", "]", "\r\n"), array("<", ">", " "), $text));
			$text = ((strlen($text) > 160) ? substr($text, 0, 157) . "..." : $text);
			$text = str_replace("\"", "&quot;", $text);
			$meta['description'] = $text;
		}

		// Layout stuff
		$act = load_syndromes();
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
			
		// Load additional data
		$postrange = get_id_range($posts, 'id');
		$showattachments = $config['allow-attachments'] || !$config['hide-attachments'];
		if ($showattachments) {
			$attachments = load_attachments($qwhere, $qvals, $postrange);
		}
		hook_use('post-extra-db', $qwhere, $qvals, $postrange);
		
		
		// Render posts
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
			if (!$_GET['hi'] && $post['highlighted']) {
				$hkey = array_search($post['id'], $highlights);
				$post['highlightprev'] = $hkey ? "#".$highlights[$hkey-1] : ($hprev ? "{$query}&nif=1&pid={$hprev}#{$hprev}" : null);
				$post['highlightnext'] = $hkey != count($highlights) - 1 ? "#".$highlights[$hkey+1] : ($hnext ? "{$query}&nif=1&pid={$hnext}#{$hnext}" : null);
			}
			
			if ($multiforum) {
				$curpthread = filter_array($pthread[$post['thread']]); // may be invalid thread
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
	