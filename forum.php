<?php

	require_once "lib/common.php";
	
	
	$_GET['id']     = filter_int($_GET['id']);
	$_GET['user']   = filter_int($_GET['user']);
	$_GET['fav']    = filter_bool($_GET['fav']);
	$_GET['act']    = filter_string($_GET['act']);
	

	if ($loguser['id']) {
		$postread = $sql->getresultsbykey("SELECT forum, readdate FROM forumread WHERE user = {$loguser['id']}");
	}
	
	$specialscheme = $specialtitle = NULL;
		
	$forumlist   = "";
	$fonline     = "";
	$forum_error = "";
	$extracols   = 0;
	
	// extra columns
	$favremhead  = "";
	$favrem      = "";

	// Add/remove favorites
	if ($_GET['act'] == 'add' || $_GET['act'] == 'rem') {
		if (!$loguser['id']) {
			$meta['noindex'] = true; // prevent search engines from indexing
			errorpage("You need to be logged in to edit your favorites!", "forum.php?id={$t['forum']}", 'return to the forum');
		}
		$_GET['thread'] = filter_int($_GET['thread']);		
		$res = load_thread($_GET['thread'], false, true, true);
		
		if ($_GET['fav']) {
			$returl = "forum.php?fav=1";
			$rettxt = 'return to the favorites';
		} else {
			$returl = "forum.php?id={$thread['forum']}";
			$rettxt = 'return to the forum';
		}
		
		$favorited = $sql->resultq("SELECT COUNT(*) FROM favorites WHERE thread = {$_GET['thread']} AND user = {$loguser['id']}");
		if ($_GET['act'] == 'add' && !$favorited) {
			check_thread_error($res);
			$sql->query("INSERT INTO favorites (user, thread) VALUES ({$loguser['id']},{$_GET['thread']})");
			$tx = "\"".htmlspecialchars($thread['title'])."\" has been added to your favorites.";
		} else if ($_GET['act'] == 'rem' && $favorited) {
			$threadtitle = ($res == THREAD_OK) ? "\"".htmlspecialchars($thread['title'])."\"" : "The restricted thread";
			$sql->query("DELETE FROM favorites WHERE user = {$loguser['id']} AND thread = {$_GET['thread']}");
			$tx = "{$threadtitle} has been removed from your favorites.";
		} else {
			die(header("Location: {$returl}"));
		}
		
		errorpage($tx, $returl, $rettxt);
	}
	
	/*
		Determine the mode-specific options, starting with the custom ones.
	*/
	$opts = null;
	foreach (hook_use('forum-mode') as $res) {
		if ($res !== null) {
			$opts = $res;
			break;
		}
	}
	
	if ($opts !== null) {
		//trigger_error("Loaded custom option", E_USER_NOTICE);
	}
	// Favorites view
	else if ($_GET['fav']) {
		if (!$loguser['id']) {
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			errorpage("You need to be logged in to view your favorites.", 'login.php', 'log in (then try again)');
		}

		$opts = new forum_mode_opt();	
		$opts->pagetitle = 'Favorites';
		// Viewing another user's favorites?
		if ($_GET['user'] && $_GET['user'] != $loguser['id'] && $isadmin)
			$opts->pagetitle .= ' of '.$sql->resultq("SELECT name FROM users WHERE id = {$_GET['user']}");
		else
			$_GET['user'] = $loguser['id'];
		
		$opts->threadcount = $sql->resultq("SELECT COUNT(*) FROM favorites where user = {$_GET['user']}");
		$opts->pageurl = "fav=1";
		
		if ($_GET['user'] == $loguser['id']) {
			$favremhead = "<td class='tdbgh center fonts' style='width: 30px' title='Remove favorite'>Rem.</td>";
			$extracols++;
		}
	}
	// Posts by user
	else if ($_GET['user']) {
		$userdata = $sql->fetchq("SELECT $userfields FROM users u WHERE id = {$_GET['user']}");
		if (!$userdata) {
			$meta['noindex'] = true; // prevent search engines from indexing what they can't access
			errorpage("No user with that ID exists.",'index.php','the index page');
		}
		
		$opts = new forum_mode_opt();	
		$opts->pagetitle	= "Threads by {$userdata['name']}";
		$opts->threadcount	= $sql->resultq("SELECT COUNT(*) FROM threads where user = {$_GET['user']}");
		$opts->pageurl 		= "user={$_GET['user']}";
	}
	else if ($_GET['id']) { # Default case, show forum with id
		load_forum($_GET['id']);
		$ismod = ismod($_GET['id']);
		if ($forum_error) {
			$forum_error = "<table class='table'>{$forum_error}</table>";
		}
		
		$opts = new forum_mode_opt();	
		$opts->pagetitle 		= $forum['title'];
		$opts->threadcount 		= $forum['numthreads'];
		$opts->specialscheme 	= $forum['specialscheme'];
		$opts->specialtitle 	= $forum['specialtitle'];
		$opts->pageurl 			= "id={$_GET['id']}";
	}
	else {
		$meta['noindex'] = true; // prevent search engines from indexing what they can't access
		errorpage("No forum specified.","index.php",'the index page');
	}

	
	pageheader($opts->pagetitle, $opts->specialscheme, $opts->specialtitle);
	if ($_GET['id']) {
		print "<table class='table'><td class='tdbg1 fonts center'>".onlineusers($forum)."</table>";
	}
	
	// Get stats
	$hotcount = $sql->resultq('SELECT hotcount FROM misc');
	if ($hotcount <= 0) $hotcount = 0xFFFF;
	
	$ppp = get_ppp();
	$tpp = get_tpp();
	
	$_GET['page'] = filter_int($_GET['page']);
    $min = $_GET['page'] * $tpp;

	// Breadcrumbs bar / new thread links
	$links = array(
		[$opts->pagetitle, NULL]
	);
	
	$forumlist = '';
	if ($_GET['id']) {
		$forumlist = doforumlist($_GET['id']);
		
		// Make sure we can create polls
		$opts->barright .= "".
			(($forum['pollstyle'] != -2) ? "<a href='newthread.php?poll=1&id={$_GET['id']}'>{$newpollpic}</a>" : $nopollpic)
			." - <a href='newthread.php?id={$_GET['id']}'>{$newthreadpic}</a>";
		if ($ismod) {
			$opts->barright .= " - <a href='admin-forumbans.php?forum={$_GET['id']}'>Edit forum bans</a>";
		}
	}
	$infotable = dobreadcrumbs($links, $opts->barright); 
	

	// Forum page list at the top & bottom
	$forumpagelinks = '';
	if ($opts->threadcount > $tpp) {
		if (isset($_GET['tpp'])) $pageurl .= "&tpp=$tpp";
		$forumpagelinks = "<table style='width: 100%'><tr><td class='fonts'>".pagelist("?$pageurl", $opts->threadcount, $tpp, true)."</td></tr></table>";
    }

	
	$threadlist = "{$forum_error}<table class='table'>";

	if ($_GET['id']) {
		// Main forum view: Get the last announcement from
		// both the annc forum and the current forum
		
		// Conditional labels
		if ($_GET['id'] == $config['announcement-forum']) {
			$ac = [$config['announcement-forum'], "0"]; // Don't show forum attachments in the annc forum
		} else {
			$ac = [$config['announcement-forum'], "{$_GET['id']} AND t.announcement = 1"];
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
						<td colspan=".(7 + $extracols)." class='tdbgh center fonts'>
							{$al[$i]}nnouncements
						</td>
					</tr>
					<tr>
						<td class='tdbg2 center'>
							". ($loguser['id'] && $annc['readdate'] < $annc['date'] ? $statusicons['new'] : "&nbsp;") ."
						</td>
						<td class='tdbg1' colspan=".(6 + $extracols).">
							<a href=announcement.php".($i ? "?f={$annc['forum']}" : "").">".htmlspecialchars($annc['atitle'])."</a> -- Posted by ".getuserlink($annc)." on ".printdate($annc['date'])."
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
	
	if ($opts->query) {
		$threads = $sql->query(strtr($opts->query, [
			'{%TRVAL%}' => $q_trval,
			'{%TRJOIN%}' => $q_trjoin,
			'{%MIN%}' => $min,
			'{%TPP%}' => $tpp,
		]));
	} else if ($_GET['fav']) {
		$threads = $sql->query("
			SELECT  t.*, f.minpower, f.pollstyle, f.id forumid, f.login,
			        ".set_userfields('u1').", 
			        ".set_userfields('u2')."
					$q_trval
			
			FROM threads t
			LEFT JOIN users      u1 ON t.user       =  u1.id
			LEFT JOIN users      u2 ON t.lastposter =  u2.id
			LEFT JOIN forums      f ON t.forum      =   f.id
			LEFT JOIN favorites fav ON t.id         = fav.thread
			$q_trjoin
			
			WHERE fav.user = {$_GET['user']}
			ORDER BY t.sticky DESC, t.lastpostdate DESC
					
			LIMIT $min,$tpp			
		");
	} else if ($_GET['user']) {
		$vals = [
			'u1_name'       => $userdata['name'],		
			'u1_sex'        => $userdata['sex'],
			'u1_powerlevel' => $userdata['powerlevel'],
			'u1_aka'        => $userdata['aka'],
			'u1_birthday'   => $userdata['birthday'],
			'u1_minipic'    => $userdata['minipic'],
			'u1_namecolor'  => $userdata['namecolor'],
			'u1_id'         => $userdata['id'],
		];
		$threads = $sql->queryp("
			SELECT 	t.*, f.minpower, f.pollstyle, f.id forumid, f.login,
			        ".set_userfields('u1', $vals).", 
			        ".set_userfields('u2')."
					$q_trval
			
			FROM threads t
			LEFT JOIN users  u2 ON t.lastposter = u2.id
			LEFT JOIN forums  f ON t.forum      = f.id
			$q_trjoin
			
			WHERE t.user = {$_GET['user']}
			ORDER BY t.sticky DESC, t.lastpostdate DESC
					
			LIMIT $min,$tpp" ,$vals);
	} else {
		$threads = $sql->query("
			SELECT 	t.*,
			        ".set_userfields('u1').", 
			        ".set_userfields('u2')."
			        $q_trval
			
			FROM threads t
			LEFT JOIN users      u1 ON t.user       =  u1.id
			LEFT JOIN users      u2 ON t.lastposter =  u2.id
			$q_trjoin
			
			WHERE t.forum = {$_GET['id']}
			ORDER BY t.sticky DESC, t.lastpostdate DESC
					
			LIMIT $min,$tpp			
		");
	}
    $threadlist .= "<tr>
		{$favremhead}
		<td class='tdbgh center' width=30></td>
		<td class='tdbgh center' colspan=2 width=*> Thread</td>
		<td class='tdbgh center' width=14%>Started by</td>
		<td class='tdbgh center' width=60> Replies</td>
		<td class='tdbgh center' width=60> Views</td>
		<td class='tdbgh center' width=150> Last post</td>
	</tr>";

	$sticklast    = 0;
	$maxfromstart = (($loguser['pagestyle']) ?  9 :  4);
	$maxfromend   = (($loguser['pagestyle']) ? 20 : 10);
		
	$_GET['page'] = 0; // horrible hack for pagelist()

	if ($sql->num_rows($threads) <= 0) {
		$threadlist .= 
			"<tr>
				<td class='tdbg1 center' style='font-style:italic;' colspan=".(7 + $extracols).">
					There are no threads to display.
				</td>
			</tr>";
	} else for ($i = 1; $thread = $sql->fetch($threads); ++$i) {
		
		// Show any separator, if applicable
		$showsep = in_array(true, hook_use('forum-thread-separator', $thread));
		if ($opts->sepsticky) {
			$showsep |= ($sticklast && !$thread['sticky']);
			$sticklast = $thread['sticky'];
		}
		if ($showsep) {
			$threadlist .= "<tr><td class='tdbgh center' colspan=".(7 + $extracols)."><img src='images/_.gif' height=6 width=6>";
		}
		
		// Remove link for favourites (why? visible ability to remove "restricted" threads from the list) 
		if ($_GET['fav'] && $_GET['user'] == $loguser['id'])
			$favrem = "<td class='tdbg2 center' style='line-height: 50%'><a href='?act=rem&thread={$thread['id']}&fav=1' title='Remove'>&mdash;</a></td>";
		else
			$favrem = "";
		
		// Always check the powerlevel if we're not showing a forum id
		if (!$_GET['id'] && !can_view_forum($thread)) {
			$threadlist .= "<tr>$favrem<td class='tdbg2 fonts center' colspan=7>(restricted)</td></tr>";
			continue;
		}
		
		/*
			Thread status icon - New post calculation
		*/
		$stat    = new forum_thread_stat();
		$newpost = false;

		// Forum, logged in
		if ($loguser['id'] && $_GET['id'] && $thread['lastpostdate'] > filter_int($postread[$_GET['id']]) && !$thread['tread']) {
			$stat->stat_new = true;
			$newpost		= true;
			$newpostt		= ($thread['treadtime'] ? $thread['treadtime'] : filter_int($postread[$_GET['id']]));
		}
		// User's thread list / Favorites, logged in
		elseif ($loguser['id'] && !$_GET['id'] && $thread['lastpostdate'] > filter_int($postread[$thread['forumid']]) && !$thread['tread']) {
			$stat->stat_new = true;
			$newpost		= true;
			$newpostt		= ($thread['treadtime'] ? $thread['treadtime'] : filter_int($postread[$thread['forumid']]));
		}
		// Not logged in
		elseif (!$loguser['id'] && $thread['lastpostdate'] > time() - 3600) {
			$stat->stat_new = true;
			$newpost		= true;
			$newpostt		= time() - 3600;	// Mark as new posts made in the last hour
		}

		/*
			Thread status icon (+ secondary) - The rest
		*/
		$stat->stat_hot    = $thread['replies'] >= $hotcount;
		$stat->stat_off    = $thread['closed'];
		$stat->stat_ann    = $thread['announcement'] && (!$_GET['id'] || ($_GET['id'] != $config['announcement-forum']));
		$stat->stat_sticky = $thread['sticky'];	
		$stat->stat_poll   = $thread['poll'] && (!$_GET['id'] || $forum['pollstyle'] != -2); // Hide poll markers if disabled
		
		/*
			Thread title
		*/
		if (!trim($thread['title']))
			$stat->threadtitle = "<i>hurr durr i'm an idiot who made a blank thread</i>";
		else
			$stat->threadtitle = htmlspecialchars($thread['title']);
		
		$stat->threadtitle  = "<a href='thread.php?id={$thread['id']}'>{$stat->threadtitle}</a>";
		if ($thread['sticky'])
			$stat->threadtitle = "<i>{$stat->threadtitle}</i>";
		
		// Show thread description
		if ($threaddesc = trim($thread['description']))
			$stat->title_d .= "<div class='fonts'>".htmlspecialchars($threaddesc)."</div>";
		
		// Show forum name if not in a forum
		if (!$_GET['id'])
			$stat->inlineinfo[] = "In <a href='forum.php?id={$thread['forumid']}'>".htmlspecialchars($forumnames[$thread['forumid']])."</a>";

		// Apply the custom stats NOW, before the pagelinks can get placed as the rightmost part of the thread title
		hook_use('forum-thread-stat', $stat, $thread);

		// Extra pages
		$pagelinks = pagelist("thread.php?id={$thread['id']}", $thread['replies'] + 1, $ppp, $maxfromstart, $maxfromend);
		if ($thread['replies'] >= $ppp) {
			if ($loguser['pagestyle']) // Pagelinks on new line
				$stat->inlineinfo[] = $pagelinks;
			else // Inline pagelinks
				$stat->title_r .= " <span class='pagelinks fonts'>({$pagelinks})</span>";
		}

		if ($stat->inlineinfo)
			$stat->title_d .= '<div class="fonts" style="position: relative; top: -1px;">&nbsp;&nbsp;&nbsp;' . implode(' - ', $stat->inlineinfo) . '</div>';
		
		
		// Build primary / secondary icon html
		$pri_status = 
			($stat->stat_new ? "new" : "").
			($stat->stat_hot ? "hot" : "").
			($stat->stat_off ? "off" : "");
		$pri_icon = ($pri_status ? $statusicons[$pri_status] : "&nbsp;");
		
		$sec_status = 
			($stat->stat_ann ? "ann" : "").
			($stat->stat_sticky ? "sticky" : "").
			($stat->stat_poll ? "poll" : "");
		$sec_icon = $sec_status ? "<i>{$statusicons[$sec_status]}</i>" : "";
		
		$newlink = ($newpost ? "<a href='thread.php?id={$thread['id']}&lpt={$newpostt}'>{$statusicons['getnew']}</a> " : "");
		$posticon = $thread['icon'] ? "<img src=\"".escape_attribute($thread['icon'])."\">" : '&nbsp;';
			
		$threadauthor 	= getuserlink(get_userfields($thread, 'u1'), $thread['user']);
		$lastposter 	= getuserlink(get_userfields($thread, 'u2'), $thread['lastposter']);
		
		$threadlist .= 
			"<tr>
				$favrem
				<td class='tdbg1 center'>$pri_icon</td>
				<td class='tdbg2 center thread-icon-td'>
					<div class='thread-icon'>$posticon</div>
				</td>
				<td class='tdbg2'>
					$newlink
					{$sec_icon}{$stat->title_l}{$stat->threadtitle}{$stat->title_r}
					{$stat->title_d}
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
	
	// Options returned by 'forum-mode' hook 
	class forum_mode_opt {
		// Window title
		public $pagetitle;
		// Fixed part of the url marking the view mode
		public $pageurl;
		// Number of threads found
		public $threadcount;
		// Header bar - right
		public $barright;
		// Enable sticky separation
		public $sepsticky = true;
		// Special scheme id
		public $specialscheme = null;
		// Special board title id
		public $specialtitle = null;
		// Custom query for getting threads, required for extensions adding a 'forum-mode'
		public $query = null;
	}
	
	class forum_thread_stat {
		// Thread status icon markers
		public $stat_new = false;
		public $stat_hot = false;
		public $stat_off = false;
		
		// Secondary thread status icon -- text shown on the side
		public $stat_ann    = false;
		public $stat_sticky = false;
		public $stat_poll   = false;
		
		// Thread title
		public $threadtitle;
		// Info shown on the left side of the title
		public $title_l = "";
		// Info shown on the right side of the title
		public $title_r = "";
		// Info shown below the title, on separate lines
		public $title_d = "";
		// Info shown on the last line, inline with - separators
		public $inlineinfo = [];
	}