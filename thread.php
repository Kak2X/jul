<?php

	require_once 'lib/function.php';

	
	$id			 = filter_int($_GET['id']);
	$_GET['pin'] = filter_int($_GET['pin']);
	$user		 = filter_int($_GET['user']);
	
	$gotopost	= null;

	
	// Skip to last post/end thread
	if (filter_int($_GET['lpt'])) {
		$gotopost = $sql->resultq("SELECT MIN(`id`) FROM `posts` WHERE `thread` = '{$id}' AND `date` > '".intval($_GET['lpt'])."'");
	} elseif (filter_int($_GET['end']) || (filter_int($_GET['lpt']) && !$gotopost)) {
		$gotopost = $sql->resultq("SELECT MAX(`id`) FROM `posts` WHERE `thread` = '{$id}'");
	}
	if ($gotopost) {
		return header("Location: ?pid={$gotopost}#{$gotopost}");
	}
	

	
	// Poll votes
	
	$action = filter_string($_GET['vact']);
	$choice = filter_int($_GET['vote']);
	
	if ($id && $action) {
		if ($loguser['id']) {
		check_token($_GET['auth'], TOKEN_VOTE);
		// does the poll exist?
		$pollid	= $sql->resultq("SELECT poll FROM threads WHERE id = {$id}");
		if (!$pollid)
			die(header("Location: ?id={$id}"));

		$poll = $sql->fetchq("SELECT * FROM poll WHERE id = $pollid");
		
		

		// no wrong poll bullshit
		$valid = $sql->resultq("SELECT COUNT(*) FROM `poll_choices` WHERE `poll` = '$pollid' AND `id` = '$choice'");

		if ($loguser['id'] && $poll && !$poll['closed'] && $valid) {
			if ($action == 'add') {
				if (!$poll['doublevote'])
					$sql->query("DELETE FROM `pollvotes` WHERE `user` = '{$loguser['id']}' AND `poll` = '$pollid'");
				$sql->query("INSERT INTO pollvotes (poll,choice,user) VALUES ($pollid,$choice,{$loguser['id']})");
			}
			else
				$sql->query("DELETE FROM `pollvotes` WHERE `user` = '{$loguser['id']}' AND `poll` = '$pollid' AND `choice` = '$choice'");
		}
		}
		die(header("Location: ?id={$id}"));
	}

	$ppp	= isset($_GET['ppp']) ? ((int) $_GET['ppp']) : ($loguser['id'] ? $loguser['postsperpage'] : $config['default-ppp']);
	$ppp	= max(min($ppp, 500), 1);
	
	
	$pid	= filter_int($_GET['pid']);

	// Linking to a post ID
	if ($pid) {
		$id		= $sql->resultq("SELECT `thread` FROM `posts` WHERE `id` = '{$pid}'");
		if (!$id) {
			$meta['noindex'] = true; // prevent search engines from indexing
			errorpage("Couldn't find a post with ID #".intval($pid).".  Perhaps it's been deleted?", "index.php", 'the index page');
		}
		$numposts 	= $sql->resultq("SELECT COUNT(*) FROM `posts` WHERE `thread` = '{$id}' AND `id` < '{$pid}'");
		$page 		= floor($numposts / $ppp);
		
		// Canonical page w/o ppp link (for bots)
		$meta['canonical']	= "thread.php?id=$id&page=$page";
	} else {
		$page		= filter_int($_GET['page']);
	}

	//define('INVALID_THREAD', -1);
	//define('INVALID_FORUM', -2);
	$forum_error = 0;
	$forumid = 0;

	$specialscheme = $specialtitle = NULL;
	$thread	= array();

	// fuck brace overkill
	if ($id) do {
		// Posts in thread
		$thread = $sql->fetchq("SELECT * FROM threads WHERE id = $id");
		$tlinks = '';

		if (!$thread) {
			$meta['noindex'] = true; // prevent search engines from indexing
			if (!$ismod) {
				trigger_error("Accessed nonexistant thread number #$id", E_USER_NOTICE);
				notAuthorizedError();
			}

			if ($sql->resultq("SELECT COUNT(*) FROM `posts` WHERE `thread` = '{$id}'") <= 0) {
				errorpage("Thread ID #{$id} doesn't exist, and no posts are associated with the invalid thread ID.","index.php",'the index page');
			}

			// Mod+ can see and possibly remove bad posts
			$forum_error = INVALID_THREAD;
			$thread['closed'] = true;
			$thread['title'] = "Bad posts with ID #$id";
			$forum['id'] = 0;
			break;
		}

		//$thread['title'] = str_replace("<", "&lt;", $thread['title']);
		
		
		$forumid = (int) $thread['forum'];
		$forum = $sql->fetchq("SELECT * FROM forums WHERE id = $forumid");

		if (!$forum) {
			$meta['noindex'] = true; // prevent search engines from indexing
			if (!$ismod) {
				trigger_error("Accessed thread number #$id with bad forum ID $forumid", E_USER_WARNING);
				notAuthorizedError();
			}
			$forum_error = INVALID_FORUM;
			$forum['title'] = " --- BAD FORUM ID --- ";
			$forum['id'] = 0;
			break;
		}

		if ($forum['minpower'] && $forum['minpower'] > $loguser['powerlevel']) {
			trigger_error("Attempted to access thread $id in level-{$forum['minpower']} restricted forum $forumid (".($loguser['id'] ? "user's powerlevel: {$loguser['powerlevel']}; user's name: ".$loguser['name'] : "guest's IP: ".$_SERVER['REMOTE_ADDR']).")", E_USER_NOTICE);
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			notAuthorizedError();
		}

		$specialscheme = $forum['specialscheme'];
		$specialtitle  = $forum['specialtitle'];
		
		$tlinks = array();
		
		if ($loguser['id']) {
			
			// Unread posts count
			$readdate = (int) $sql->resultq("SELECT `readdate` FROM `forumread` WHERE `user` = '{$loguser['id']}' AND `forum` = '$forumid'");
			
			if ($thread['lastpostdate'] > $readdate)
				$sql->query("REPLACE INTO threadsread SET `uid` = '{$loguser['id']}', `tid` = '{$thread['id']}', `time` = '".ctime()."', `read` = '1'");

			$unreadcount = $sql->resultq("
				SELECT COUNT(*) FROM `threads`
				WHERE `id` NOT IN (SELECT `tid` FROM `threadsread` WHERE `uid` = '{$loguser['id']}' AND `read` = '1')
				AND `lastpostdate` > '{$readdate}' AND `forum` = '{$forumid}'
			");
			
			if ($unreadcount == 0)
				$sql->query("REPLACE INTO forumread VALUES ( {$loguser['id']}, $forumid, ".ctime().")");
		
			// Favorites
			if ($sql->resultq("SELECT COUNT(*) FROM favorites WHERE user = {$loguser['id']} AND thread = {$id}"))
				$tlinks[] = "<a href='forum.php?act=rem&thread={$id}' class='nobr'>Remove from favorites</a>";
			else
				$tlinks[] = "<a href='forum.php?act=add&thread={$id}' class='nobr'>Add to favorites</a>";
		}
		
		// Forum/Thread navigation
		$tnext = $sql->resultq("SELECT id FROM threads WHERE forum=$forumid AND lastpostdate>{$thread['lastpostdate']} ORDER BY lastpostdate ASC LIMIT 1");
		if ($tnext) $tlinks[] = "<a href='?id={$tnext}' class='nobr'>Next newer thread</a>";
		$tprev = $sql->resultq("SELECT id FROM threads WHERE forum=$forumid AND lastpostdate<{$thread['lastpostdate']} ORDER BY lastpostdate DESC LIMIT 1");
		if ($tprev) $tlinks[] = "<a href='?id={$tprev}' class='nobr'>Next older thread</a>";

		$tlinks = implode(' | ', $tlinks);

		// Description for bots
		//$text = $sql->resultq("SELECT text FROM posts_text pt LEFT JOIN posts p ON (pt.pid = p.id) WHERE p.thread=$id ORDER BY pt.pid ASC LIMIT 1");
		$text = $sql->resultq("SELECT text FROM posts WHERE thread = $id");
		$text = strip_tags(str_replace(array("[", "]", "\r\n"), array("<", ">", " "), $text));
		$text = ((strlen($text) > 160) ? substr($text, 0, 157) . "..." : $text);
		$text = str_replace("\"", "&quot;", $text);
		$meta['description'] = $text;

		// don't count bot views
		if (!$isbot)
			$sql->query("UPDATE threads SET views = views + 1 WHERE id = $id");

		$windowtitle = "{$forum['title']}: {$thread['title']}";
		
	} while (false);
	else if ($user) {
		// Posts by user
		$uname = $sql->resultq("SELECT name FROM users WHERE id={$user}");
		if (!$uname) {
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			errorpage("User ID #{$user} doesn't exist.","index.php",'the index page');
		}

		$thread['replies'] = $sql->resultq("SELECT COUNT(*) FROM posts WHERE user = {$user}") - 1;
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

	//temporary
	if ($windowtitle) $windowtitle = $config['board-name']." -- $windowtitle";
	
	pageheader($windowtitle, $specialscheme, $specialtitle);

	if ($id && !$forum_error) {
		print "<table class='table'><td class='tdbg1 fonts center'>".onlineusers($forum, $thread)."</table>";
		if ($sql->resultq("SELECT 1 FROM forummods WHERE forum='$forumid' and user = '{$loguser['id']}'"))
			$ismod = true;
	}	
	
	// Moderator options
	$modfeats = '';
	if ($id) {
		if ($ismod) {

			$fulledit = "<a href='editthread.php?id={$id}'>Edit thread<a>";
			$linklist = array();
			$link = "<a href='editthread.php?id={$id}&auth=".generate_token(TOKEN_MGET)."&action";

			if (!$thread['sticky'])
				$linklist[] = "$link=qstick'>Stick</a>";
			else
				$linklist[] = "$link=qunstick'>Unstick</a>";

			if (!$thread['closed'])
				$linklist[] = "$link=qclose'>Close</a>";
			else
				$linklist[] = "$link=qunclose'>Open</a>";

			if ($thread['forum'] != $config['trash-forum'])
				$linklist[] = "<a href='editthread.php?id={$id}&action=trashthread'>Trash</a>";

			//$linklist[] = "$link=delete'>Delete</a>";
			$linklist = implode(' | ', $linklist);
			$modfeats = "<tr><td class='tdbgc fonts' colspan=2>Moderating options: $linklist -- $fulledit</td></tr>";
		}
		else if ($loguser['id'] == $thread['user']) {
			// Allow users to rename their own thread
			$modfeats = "<tr><td class='tdbgc fonts' colspan=2>Thread options: <a href='editthread.php?id=$id'>Edit thread</a></td></tr>";
		}
	}


	$errormsgs = '';
	if ($forum_error) {
		switch($forum_error) {
        	case INVALID_THREAD: $errortext='This thread does not exist, but posts exist that are associated with this invalid thread ID.'; break;
        	case INVALID_FORUM: $errortext='This thread has an invalid forum ID; it is located in a forum that does not exist.'; break;
		}
		$errormsgs = "<tr><td style='background:#cc0000;color:#eeeeee;text-align:center;font-weight:bold;'>$errortext</td></tr>";
	}

	$polltbl	= "";
	if ($id && $forum['pollstyle'] != -2 && $thread['poll']) {
		
		$poll = $sql->fetchq("SELECT * FROM poll WHERE id='{$thread['poll']}'");
		
		// Determine the user's poll votes
		$uservote = array();
		if ($loguser['id']) {
			$lsql = $sql->query("SELECT `choice` FROM `pollvotes` WHERE `poll` = '{$poll['id']}' AND `user` = '{$loguser['id']}'");
			while ($userchoice = $sql->fetch($lsql, PDO::FETCH_ASSOC))
				$uservote[$userchoice['choice']] = true;
		}
		
		// Forcing a poll style?
		if ($forum['pollstyle'] >= 0)			
			$pollstyle = $forum['pollstyle'];
		else
			$pollstyle = $loguser['pollstyle'];

		// Account for the two poll styles
		$tvotes2 = $sql->resultq("SELECT COUNT(*) FROM pollvotes WHERE poll = {$poll['id']}");
		$tvotesi = $sql->resultq("
			SELECT SUM(u.influence)
			FROM pollvotes p
			LEFT JOIN users u ON p.user = u.id
			WHERE poll = {$poll['id']}
		");

		$pollvotes = $sql->getresultsbykey("
			SELECT choice, COUNT(*) cnt
			FROM pollvotes
			WHERE poll = {$poll['id']}
			GROUP BY choice WITH ROLLUP
		");
		$pollinflu = $sql->getresultsbykey("
			SELECT choice, SUM(u.influence) inf
			FROM pollvotes p
			LEFT JOIN users u ON p.user = u.id
			WHERE poll = {$poll['id']}
			GROUP BY choice WITH ROLLUP
		");

		$tvotes_u = (int) $sql->resultq("SELECT COUNT(DISTINCT `user`) FROM pollvotes WHERE poll = {$poll['id']}");
		$tvotes_c = isset($pollvotes[""]) ? $pollvotes[""] : 0;
		$tvotes_i = isset($pollinflu[""]) ? $pollvotes[""] : 0;

		$confirm = generate_token(TOKEN_VOTE);

		$pollcs = $sql->query("SELECT * FROM poll_choices WHERE poll = {$poll['id']}");
		
		$choices = "";
		while ($pollc = $sql->fetch($pollcs)) {
			$votes = filter_int($pollvotes[$pollc['id']]);
			$influ = filter_int($pollinflu[$pollc['id']]);

			if ($pollstyle) {
				// Influence
				if ($tvotes_i != 0 && $tvotes_u != 0)
					$pct = $pct2 = sprintf('%02.1f', $influ / $tvotes_i * 100);
				else
					$pct = $pct2 = "0.0";
				$votes = "$influ point".($influ == 1 ? '' : 's')." ($votes)";
			}
			else {
				// Normal
				if ($tvotes_c != 0 && $tvotes_u != 0) {
					$pct = sprintf('%02.1f', $votes / $tvotes_c * 100);
					$pct2 = sprintf('%02.1f', $votes / $tvotes_u * 100);
				} else
					$pct = $pct2 = "0.0";
				$votes = "$votes vote".($votes == 1 ? '' : 's');
			}

			$barpart = "<table cellpadding=0 cellspacing=0 width=$pct% bgcolor='".($pollc['color'] ? $pollc['color'] : "cccccc")."'><td>&nbsp;</table>";
			if ($pct == "0.0")
				$barpart = '&nbsp;';

			if (isset($uservote[$pollc['id']])) {
				$linkact = 'del';
				$dot = "<img src='images/dot4.gif' align='absmiddle'> ";
			}
			else {
				$linkact = 'add';
				$dot = "<img src='images/_.gif' width=8 height=8 align='absmiddle'> ";
			}

			$link = '';
			if ($loguser['id'] && !$poll['closed'])
				$link = "<a href='?id={$id}&auth={$confirm}&vact={$linkact}&vote=$pollc[id]'>";

			$choices	.= "<tr>
				<td class='tdbg1' width=20%>$dot$link".xssfilters($pollc['choice'])."</a></td>
				<td class='tdbg2' width=60%>$barpart</td>
				<td class='tdbg1 center' width=20%>".($poll['doublevote'] ? "$pct% of users, $votes ($pct2%)" : "$pct%, $votes")."</td>
				</tr>";
		}

		if ($poll['closed']) $polltext = 'This poll is closed.';
		else                 $polltext = 'Multi-voting is '.(($poll['doublevote']) ? 'enabled.' : 'disabled.');
		if ($tvotes_u != 1) $s_have = 's have';
		else                $s_have = ' has';

		
		if ($ismod)
			$polledit = "-- <a href='editpoll.php?id=$id'>Edit poll</a>";
		else if ($loguser['id'] == $thread['user'])
			$polledit = "-- <a href='editpoll.php?id=$id&close&auth=".generate_token(TOKEN_MGET)."'>".($poll['closed'] ? "Open" : "Close")." poll</a>";
		else
			$polledit = "";

		$polltbl = 
			"<table class='table'>
				<tr>
					<td class='tdbgc center' colspan=3>
						<b>".htmlspecialchars($poll['question'])."</b>
					</td>
				</tr>
				<tr>
					<td class='tdbg2 fonts' colspan=3>
						".nl2br(dofilters($poll['briefing']), $thread['forum'])."
					</td>
				</tr>
				$choices
				<tr>
					<td class='tdbg2 fonts' colspan=3>
						&nbsp;$polltext $tvotes_u user$s_have voted. $polledit
					</td>
				</tr>
			</table>
			<br>";
	}

	loadtlayout();
	
	switch($loguser['viewsig']) {
		case 1:  $sfields = ',p.headtext,p.signtext'; break;
		case 2:  $sfields = ',u.postheader headtext,u.signature signtext'; break;
		default: $sfields = ''; break;
	}
	$ufields = userfields();

	/*
	$activity = $sql->query("SELECT user, COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." GROUP BY user");
	while ($n = $sql->fetch($activity))
		$act[$n['user']] = $n['num'];
*/

	// Activity in the last day (to determine syndromes)
	$act = $sql->getresultsbykey("SELECT user, COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." GROUP BY user");
	
	$postlist = "
		{$polltbl}
		<table class='table'>
		{$modfeats}
		{$errormsgs}
	";

	$threadforumlinks = "
		<table width=100%><td align=left class='font'><a href=index.php>{$config['board-name']}</a>"
		.
		(($forum['title']) ? " - <a href='forum.php?id=$forumid'>{$forum['title']}</a>" : "")
		.
		" - {$thread['title']}</td><td align=right class='fonts'>
	";
	
	// New Reply / Thread / Poll links
	if ($id && $forumid) {
		if ($forum['pollstyle'] != -2) $threadforumlinks .= "<a href='newthread.php?poll=1&id=$forumid'>$newpollpic</a> - ";
		else                           $threadforumlinks .= "<img src='schemes/default/status/nopolls.png' align='absmiddle'> - ";
		$threadforumlinks .= "<a href='newthread.php?id=$forumid'>$newthreadpic</a>";
		if (!$thread['closed']) $threadforumlinks .= " - <a href='newreply.php?id=$id'>$newreplypic</a>";
		else                    $threadforumlinks .= " - $closedpic";
	}
	$threadforumlinks .= '</table>';

	
	// Query elements
	$min	= $ppp * $page;
	
	if ($user) $searchon = "p.user={$user}";
	else       $searchon = "p.thread={$id}";

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
				p.text$sfields, p.edited, p.editdate, p.options, p.tagval, p.deleted,
				u.id uid, u.name, $ufields, u.regdate
		FROM posts p
		
		LEFT JOIN users u ON p.user = u.id
		WHERE {$searchon}
		ORDER BY p.id
		LIMIT $min,$ppp
	");
	
	$controls['ip'] = "";
	for ($i = 0; $post = $sql->fetch($posts); ++$i) {
		
		// Post controls
		$postlist	.= '<tr>';

		$bg = $i % 2 + 1;

		$controls['quote'] = "<a href=\"?pid={$post['id']}#{$post['id']}\">Link</a>";
		if (!$post['deleted']) {
			if ($id && ! $thread['closed']) {
				$controls['quote'] .= " | <a href='newreply.php?id=$id&postid={$post['id']}'>Quote</a>";
			}
		}
		
		$controls['edit'] = '';
		if ($ismod || (!$banned && !$post['deleted'] && $post['user'] == $loguser['id'])) {
			$tokenstr = "&auth=".generate_token(TOKEN_MGET);
			
        	if ($ismod || ($id && !$thread['closed'])) {
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


		$pforum		= null;
		$pthread	= null;
		if (!$id) {
			// Enable caching for these
			$pthread = $sql->fetchq("SELECT id,title,forum FROM threads WHERE id={$post['thread']}", PDO::FETCH_BOTH, mysql::USE_CACHE);
			$pforum  = $sql->resultq("SELECT minpower FROM forums WHERE id=".filter_int($pthread['forum']), 0, 0, mysql::USE_CACHE);
		}
		
		$post['act']     = filter_int($act[$post['user']]);
		if ($config['allow-avatar-storage']) {
			$post['piclink'] = filter_string($avatars[$post['user']][$post['moodid']]);
		}
		if (!$pforum || $pforum <= $loguser['powerlevel'])
			$postlist .= threadpost($post, $bg, $forum['id'], $pthread);
		else
			$postlist .=
				"<table class='table'>
					<tr>
						<td class='tbl tdbg$bg' align=center>
							<small><i>
								(post in restricted forum)
							</i></small>
						</td>
					</tr>
				</table>";
	}

	// Strip _GET variables that can set the page number
	$query = preg_replace("'page=(\d*)'si", '', '?'.$_SERVER["QUERY_STRING"]);
	$query = preg_replace("'pid=(\d*)'si", "id={$id}", $query);
	$query = preg_replace("'&{2,}'si", "&", $query);
	//if ($query && substr($query, -1) != "&")
	//	$query	.= "&";

	$pagelinks = pagelist($query, $thread['replies'] + 1, $ppp);
	
	//print $header.sizelimitjs()."
	
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

/*
function notAuthorizedError() {
	global $loguser, $forum;
	$redir = (($loguser['id']) ? 'index.php' : 'login.php');
	$rtext = (($loguser['id']) ? 'the index page' : 'log in (then try again)');
	// Horrible hack
	$forum['id'] = NULL;
	errorpage("Couldn't enter the forum. You don't have access to this restricted forum.", $redir, $rtext);
}*/