<?php

	require_once 'lib/function.php';
	
	
	$id 	= filter_int($_GET['id']);
	$user 	= filter_int($_GET['user']);
	
	$fav	= filter_bool($_GET['fav']);
	$act	= filter_string($_GET['act']);
	

	if ($loguser['id']) {
		$postread = $sql->getresultsbykey("SELECT forum, readdate FROM forumread WHERE user = {$loguser['id']}");
	}
	
	$specialscheme = $specialtitle = NULL;
		
	$forumlist  = "";
	$fonline    = "";

	// Add/remove favorites
	if ($act == 'add' || $act == 'rem') {
		if (!$loguser['id']) {
			$meta['noindex'] = true; // prevent search engines from indexing
			errorpage("You need to be logged in to edit your favorites!", "forum.php?id={$t['forum']}", 'return to the forum');
		}
		
		$thread = filter_int($_GET['thread']);
		$t = $sql->fetchq("
			SELECT t.title, t.forum, f.minpower, v.thread fav
			FROM threads t
			INNER JOIN forums f ON t.forum = f.id
			LEFT JOIN favorites v ON t.id = v.thread AND v.user = {$loguser['id']}
			WHERE t.id = $thread
		");
		if ($t['minpower'] && $t['minpower'] > $loguser['powerlevel']) {
			errorpage("You can't favorite a thread you don't have access to!","index.php",'the board');
		}

		if ($act == 'add' && !$t['fav']) {
			$sql->query("INSERT INTO favorites (user, thread) VALUES ({$loguser['id']},{$thread})");
			$tx = "\"{$t['title']}\" has been added to your favorites.";
		} else if ($act == 'rem' && $t['fav']) {
			$sql->query("DELETE FROM favorites WHERE user = {$loguser['id']} AND thread = {$thread}");
			$tx = "\"{$t['title']}\" has been removed from your favorites.";
		} else {
			die(header("Location: forum.php?id={$t['forum']}"));
		}
		
		errorpage($tx, "forum.php?id={$t['forum']}", 'return to the forum');
	}

	
	// Favorites view
	if ($fav) {
		if (!$loguser['id']) {
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			errorpage("You need to be logged in to view your favorites.", 'login.php', 'log in (then try again)');
		}

		$forum['title'] = 'Favorites';
		
		if ($user && $user != $loguser['id'] && $isadmin)
			$forum['title'] .= ' of '.$sql->resultq("SELECT name FROM users WHERE id = {$user}");
		else
			$user = $loguser['id'];
		
		$threadcount = $sql->resultq("SELECT COUNT(*) FROM favorites where user = {$user}");
	}
	// Posts by user
	else if ($user) {
		$userdata = $sql->fetchq("SELECT $userfields FROM users u WHERE id = {$user}");
		
		if (!$userdata) {
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			errorpage("No user with that ID exists.",'index.php','the index page');
		}

		$forum['title'] = "Threads by {$userdata['name']}";
		$threadcount = $sql->resultq("SELECT COUNT(*) FROM threads where user = $user");
	}
	else if ($id) { # Default case, show forum with id
		$forum = $sql->fetchq("SELECT id, title, minpower, numthreads, specialscheme, specialtitle, pollstyle FROM forums WHERE id = $id");

		if (!$forum) {
			trigger_error("Attempted to access invalid forum $id", E_USER_NOTICE);
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			notAuthorizedError();
		}
		elseif ($forum['minpower'] && $forum['minpower'] > $loguser['powerlevel']) {
			trigger_error("Attempted to access level-{$forum['minpower']} restricted forum $id (".($loguser['id'] ? "user's powerlevel: {$loguser['powerlevel']}; user's name: ".$loguser['name'] : "guest's IP: ".$_SERVER['REMOTE_ADDR']).")", E_USER_NOTICE);
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			notAuthorizedError();
		}
		
		if (!$ismod) {
			$ismod = $sql->resultq("SELECT 1 FROM forummods WHERE forum = {$id} AND user = {$loguser['id']}");
		}
		
		$threadcount 	= $forum['numthreads'];
		$specialscheme 	= $forum['specialscheme'];
		$specialtitle 	= $forum['specialtitle'];
		
	}
	else {
		$meta['noindex'] = true; // prevent search engines from indexing what they can't access
		errorpage("No forum specified.","index.php",'the index page');
	}


	
	
	$windowtitle = $config['board-name']." -- ".$forum['title'];
	
	pageheader($windowtitle, $specialscheme, $specialtitle);

	$hotcount = $sql->resultq('SELECT hotcount FROM misc');
	if ($hotcount <= 0) $hotcount = 0xFFFF;
	
	
	$ppp = (isset($_GET['ppp']) ? ((int) $_GET['ppp']) : (($loguser['id']) ? $loguser['postsperpage'] : $config['default-ppp']));
	$ppp = max(min($ppp, 500), 1);

	$tpp = (isset($_GET['tpp']) ? ((int) $_GET['tpp']) : (($loguser['id']) ? $loguser['threadsperpage'] : $config['default-tpp']));
	$tpp = max(min($tpp, 500), 1);

	$page = filter_int($_GET['page']);
    $min = $page*$tpp;
	

	$newthreadbar = $forumlist = $modopt = '';
	if ($id) {
		$forumlist = doforumlist($id);
		
		// Make sure we can create polls
		$newthreadbar = "".
			(($forum['pollstyle'] != -2) ? "<a href='newthread.php?poll=1&id=$id'>$newpollpic</a>" : "<img src='images/nopolls.png' align='absmiddle'>")
			." - <a href='newthread.php?id=$id'>$newthreadpic</a>";
		if ($ismod) {
			$modopt = " - <a href='admin-forumbans.php?forum={$id}'>Edit forum bans</a>";
		}
	}
	
	$infotable =
		"<table style='width: 100%'>
			<tr>
				<td align=left class='font'>
					<a href='index.php'>{$config['board-name']}</a> - ".htmlspecialchars($forum['title'])."
				</td>
				<td align=right class='fonts'>
					{$newthreadbar}
					{$modopt}
				</td>
			</tr>
		</table>";
		

	// Forum page list at the top & bottom
	$forumpagelinks = '';
	if($threadcount > $tpp) {
		
		$query = ($id ? "id=$id" : ($user ? "user=$user" : "fav=1")); // Determine correct mode
		if (isset($_GET['tpp'])) $query .= "&tpp=$tpp";

		$forumpagelinks = 
			"<table style='width: 100%'>
				<tr>
					<td align=left class='fonts'>
						".pagelist("?$query", $threadcount, $tpp, true)."
					</td>
				</tr>
			</table>";
    }

	$threadlist = "<table class='table'>";

	if ($id) {
		// Main forum view: Get the last announcement from
		// both the annc forum and the current forum
		
		// Conditional labels
		if ($id == $config['announcement-forum']) {
			$ac = [$config['announcement-forum'], "0"]; // Don't show forum attachments in the annc forum
		} else {
			$ac = [$config['announcement-forum'], "$id AND t.announcement = 1"];
		}
		$al = ['A','Forum a'];
		
		for ($i = 0; $i < 2; ++$i) {
			
			$annc = $sql->fetchq("
				SELECT $userfields, t.id aid, t.title atitle, t.description adesc,
				       t.firstpostdate date, t.forum, r.readdate
				FROM threads t
				LEFT JOIN users            u ON t.user = u.id
				LEFT JOIN announcementread r ON t.forum = r.forum AND r.user = {$loguser['id']}
				WHERE t.forum = {$ac[$i]}
				ORDER BY t.firstpostdate DESC
				LIMIT 1
			");
			
			if ($annc) {
				$threadlist .= 
					"<tr>
						<td colspan=7 class='tdbgh center fonts'>
							{$al[$i]}nnouncements
						</td>
					</tr>
					<tr>
						<td class='tdbg2 center'>
							". ($loguser['id'] && $annc['readdate'] < $annc['date'] ? $statusicons['new'] : "&nbsp;") ."
						</td>
						<td class='tdbg1' colspan=6>
							<a href=announcement.php".($i ? "?f={$annc['forum']}" : "").">{$annc['atitle']}</a> -- Posted by ".getuserlink($annc)." on ".printdate($annc['date'])."
						</td>
					</tr>";
			}
		}
    } else {
		// Get forum names in threads by user / favourite list
		$forumnames = $sql->getresultsbykey("SELECT id, title FROM forums WHERE !minpower OR minpower <= {$loguser['powerlevel']}");
	}
	
	
	// Get threads
	if ($loguser['id']) {
		$q_trval 	= ", r.read tread, r.time treadtime ";
		$q_trjoin 	= "LEFT JOIN threadsread r ON t.id = r.tid AND r.uid = {$loguser['id']} ";
	} else {
		$q_trval = $q_trjoin = "";
	}
	
	// Now with FETCH_NAMED capabilities
	if ($fav) {
		$threads = $sql->query("
			SELECT  t.*, f.minpower, f.pollstyle, f.id forumid,
			        ".set_userfields('u1')." uid, 
			        ".set_userfields('u2')." uid
					$q_trval
			
			FROM threads t
			LEFT JOIN users      u1 ON t.user       =  u1.id
			LEFT JOIN users      u2 ON t.lastposter =  u2.id
			LEFT JOIN forums      f ON t.forum      =   f.id
			LEFT JOIN favorites fav ON t.id         = fav.thread
			$q_trjoin
			
			WHERE fav.user = {$user}
			ORDER BY t.sticky DESC, t.lastpostdate DESC
					
			LIMIT $min,$tpp			
		");

	} else if ($user) {
		$vals = [
			'u1name'		=> $userdata['name'],		
			'u1sex'			=> $userdata['sex'],
			'u1powerlevel'	=> $userdata['powerlevel'],
			'u1aka'			=> $userdata['aka'],
			'u1birthday'	=> $userdata['birthday'],
			'u1namecolor'	=> $userdata['namecolor']
		];
		$threads = $sql->queryp("
			SELECT 	t.*, f.minpower, f.pollstyle, f.id forumid,
			        ".set_userfields('u1', $vals).", 
			        ".set_userfields('u')." uid
					$q_trval
			
			FROM threads t
			LEFT JOIN users  u ON t.lastposter = u.id
			LEFT JOIN forums f ON t.forum      = f.id
			$q_trjoin
			
			WHERE t.user = {$user}
			ORDER BY t.sticky DESC, t.lastpostdate DESC
					
			LIMIT $min,$tpp" ,$vals);
	} else {
		$threads = $sql->query("
			SELECT 	t.*,
			        ".set_userfields('u1')." uid, 
			        ".set_userfields('u2')." uid
			        $q_trval
			
			FROM threads t
			LEFT JOIN users      u1 ON t.user       =  u1.id
			LEFT JOIN users      u2 ON t.lastposter =  u2.id
			$q_trjoin
			
			WHERE t.forum = {$id}
			ORDER BY t.sticky DESC, t.lastpostdate DESC
					
			LIMIT $min,$tpp			
		");
	}
    $threadlist .= "<tr>
		<td class='tdbgh center' width=30></td>
		<td class='tdbgh center' colspan=2 width=*> Thread</td>
		<td class='tdbgh center' width=14%>Started by</td>
		<td class='tdbgh center' width=60> Replies</td>
		<td class='tdbgh center' width=60> Views</td>
		<td class='tdbgh center' width=150> Last post</td>
	</tr>";

	$sticklast = 0;

	if ($sql->num_rows($threads) <= 0) {
		$threadlist .= 
			"<tr>
				<td class='tdbg1 center' style='font-style:italic;' colspan=7>
					There are no threads to display.
				</td>
			</tr>";
	} else for($i = 1; $thread = $sql->fetch($threads, PDO::FETCH_NAMED); ++$i) {
		
		// Sticky separator
		if($sticklast && !$thread['sticky'])
			$threadlist .= "<tr><td class='tdbgh center' colspan=7><img src='images/_.gif' height=6 width=6>";
		$sticklast = $thread['sticky'];

		// Always check the powerlevel if we're not showing a forum id
		if(!$id && $thread['minpower'] && $thread['minpower'] > $loguser['powerlevel']) {
			$threadlist .= "<tr><td class='tdbg2 fonts center' colspan=7>(restricted)</td></tr>";
			continue;
		}

		// Disabled polls
		if ($id && $forum['pollstyle'] == -2)
			$thread['poll'] = 0;

		
		
		/*
			Thread status icon
		*/
		$new          = "&nbsp;";
		$newpost      = false;
		$threadstatus	= "";

		// Forum, logged in
		if ($loguser['id'] && $id && $thread['lastpostdate'] > filter_int($postread[$id]) && !$thread['tread']) {
			$threadstatus	.= "new";
			$newpost		= true;
			$newpostt		= ($thread['treadtime'] ? $thread['treadtime'] : filter_int($postread[$id]));
		}
		// User's thread list / Favorites, logged in
		elseif ($loguser['id'] && !$id && $thread['lastpostdate'] > filter_int($postread[$thread['forumid']]) && !$thread['tread']) {
			$threadstatus	.= "new";
			$newpost		= true;
			$newpostt		= ($thread['treadtime'] ? $thread['treadtime'] : filter_int($postread[$thread['forumid']]));
		}
		// Not logged in
		elseif (!$loguser['id'] && $thread['lastpostdate'] > ctime() - 3600) {
			$threadstatus	.= "new";
			$newpost		= true;
			$newpostt		= ctime() - 3600;	// Mark as new posts made in the last hour
		}

		if ($thread['replies'] >= $hotcount) 	$threadstatus .= "hot";
		if ($thread['closed'])					$threadstatus .= "off";
		
		if ($threadstatus) $new = $statusicons[$threadstatus];

		$posticon = "<img src=\"".htmlspecialchars($thread['icon'])."\">";
		
		

		if (trim($thread['title']) == "")
			$thread['title']	= "<i>hurr durr i'm an idiot who made a blank thread</i>";
		else
			$thread['title'] = htmlspecialchars($thread['title']);//str_replace(array('<', '>'), array('&lt;', '&gt;'), trim($thread['title']));

		$threadtitle	= "<a href='thread.php?id={$thread['id']}'>{$thread['title']}</a>";
		$belowtitle   = array(); // An extra line below the title in certain circumstances
		
		/*
			Secondary thread status icon
		*/
		$sicon			= "";
		if ($thread['announcement'] && (!$id || ($id != $config['announcement-forum']))) {
			$sicon	.= "ann";
		}
		if ($thread['sticky'])	{
			$threadtitle	= "<i>". $threadtitle ."</i>";
			$sicon	.= "sticky";
		}
		
		if ($thread['poll'])	$sicon	.= "poll";
		if ($sicon)
			$threadtitle	= "<i>{$statusicons[$sicon]}</i> {$threadtitle}";

		// Show forum name if not in a forum
		if (!$id)
			$belowtitle[] = "In <a href='forum.php?id={$thread['forumid']}'>{$forumnames[$thread['forumid']]}</a>";

		// Extra pages
		$maxfromstart = (($loguser['pagestyle']) ?  9 :  4);
		$maxfromend   = (($loguser['pagestyle']) ? 20 : 10);
		
		$_GET['page'] = 0; // horrible hack
		$pagelinks = pagelist("thread.php?id={$thread['id']}", $thread['replies'] + 1, $ppp, $maxfromstart, $maxfromend);
		
		if($thread['replies'] >= $ppp) {
			if ($loguser['pagestyle'])
				$belowtitle[] = $pagelinks;
			else
				$threadtitle .= " <span class='pagelinks fonts'>({$pagelinks})</span>";
		}
		
		// The thread description has its own line though
		if ($threaddesc = trim($thread['description']))
			$threadtitle .= "<br><span class='fonts'>".htmlspecialchars($threaddesc)."</span>";

		if (!empty($belowtitle))
			$secondline = '<br><span class="fonts" style="position: relative; top: -1px;">&nbsp;&nbsp;&nbsp;' . implode(' - ', $belowtitle) . '</span>';
		else
			$secondline = '';

		if(!$thread['icon']) $posticon='&nbsp;';
		
		$threadauthor 	= getuserlink(array_column_by_key($thread, 0), $thread['user']);
		$lastposter 	= getuserlink(array_column_by_key($thread, 1), $thread['lastposter']);
		
		$threadlist .= 
			"<tr>
				<td class='tdbg1 center'>$new</td>
				<td class='tdbg2 center' width=40px>
					<div style='max-width:60px;max-height:30px;overflow:hidden;'>
						$posticon
					</div>
				</td>
				<td class='tdbg2'>
					". ($newpost ? "<a href='thread.php?id={$thread['id']}&lpt=$newpostt'>{$statusicons['getnew']}</a> " : "") ."
					$threadtitle$secondline
				</td>
				<td class='tdbg2 center'>{$threadauthor}<!--<span class='fonts'><br>".printdate($thread['firstpostdate'])."</span>--></td>
				<td class='tdbg1 center'>{$thread['replies']}</td>
				<td class='tdbg1 center'>{$thread['views']}</td>
				<td class='tdbg2 center'>
					<div class='lastpost'>
						".printdate($thread['lastpostdate'])."<br>
						by {$lastposter}
						<a href='thread.php?id={$thread['id']}&end=1'>{$statusicons['getlast']}</a>
					</div>
				</td>
			</tr>";
	}
	$threadlist .= "</table>";

	
	
	print "
		{$infotable}
		{$forumpagelinks}
		{$threadlist}
		{$forumpagelinks}
		{$infotable}
		{$forumlist}
	";
	
	pagefooter();

function notAuthorizedError() {
	global $log;
	$rreason = (($log) ? 'don\'t have access to it' : 'are not logged in');
	$redir = (($log) ? 'index.php' : 'login.php');
	$rtext = (($log) ? 'the index page' : 'log in (then try again)');
	errorpage("Couldn't enter this restricted forum, as you {$rreason}.", $redir, $rtext);
}