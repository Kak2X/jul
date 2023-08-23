<?php

	if (isset($_GET['u']) && $_GET['u']) {
		header("Location: profile.php?id=". $_GET['u']);
		die();
	} elseif (isset($_GET['p']) && $_GET['p']) {
		header("Location: thread.php?pid=". $_GET['p'] ."#". $_GET['p']);
		die();
	} elseif (isset($_GET['t']) && $_GET['t']) {
		header("Location: thread.php?id=". $_GET['t']);
		die();
	}
	


/*
	if ($_GET["letitsnow"]) {
		if (!array_key_exists('snowglobe', $_COOKIE)) {
			$_COOKIE['snowglobe'] = 1;
		}

		if (!is_int($_COOKIE['snowglobe'])) {
			die("no.");
		}
		if ($_COOKIE['snowglobe'] == 0) {
			$_COOKIE['snowglobe'] = 1;
		} elseif ($_COOKIE['snowglobe'] == 1) {
			$_COOKIE['snowglobe'] = 0;
		}

		header("Location: index.php");
	}
*/

	require "lib/common.php";
	
	if (isset($_GET['wtf'])) {
		$loguser['splitcat'] = 1 - $loguser['splitcat'];
	}

	/* 
	$sql->query("UPDATE `users` SET `name` = 'Xkeeper' WHERE `id` = 1"); # I'm hiding it here too as a 'last resort'. Remove this and I'll make that Z-line a month instead.
	// You know me, I find it more fun to hide code to replace your name everywhere instead of altering the DB <3
//	$sql->query("UPDATE `users` SET `sex` = '1' WHERE `id` = 2100");  // Me too <3 ~Ras
*/

/* heavily unfinished mobile index page
	if ($x_hacks['smallbrowse'] == 1 and false) {
		require 'mobile/index.php';
		die;
	} */
	


	if ($loguser['id'] && isset($_GET['action'])) {
		
		switch ($_GET['action']) {
			case 'markforumread':
				$id = filter_int($_GET['forumid']);
				$sql->query("DELETE FROM forumread WHERE user = {$loguser['id']} AND forum = $id");
				$sql->query("DELETE FROM threadsread WHERE uid = {$loguser['id']} AND tid IN (SELECT `id` FROM `threads` WHERE `forum` = $id)");
				$sql->query("INSERT INTO forumread (user, forum, readdate) VALUES ({$loguser['id']}, $id, ".time().')');
				break;
			case 'markallforumsread':
				$sql->query("DELETE FROM forumread WHERE user = {$loguser['id']}");
				$sql->query("DELETE FROM threadsread WHERE uid = {$loguser['id']}");
				$sql->query("INSERT INTO forumread (user, forum, readdate) SELECT {$loguser['id']}, id, ".time()." FROM forums");
				break;
		}
		
		header("Location: index.php");
		die;
	}
	
	// Collapsable categories support
	$_GET['cat']	= filter_int($_GET['cat']);
	if (toggle_board_cookie_man($_GET['toggle'], "hcat[{$_GET['cat']}]", $_COOKIE['hcat'][$_GET['cat']])) {
		die(header("Location: index.php"));
	}
	
	// Move it after the auto-redirect actions, otherwise the redirect breaks
	pageheader();
	
	$postread = readpostread($loguser['id']);
	
	/*
		Birthday calculation
	*/

	$users1 = $sql->query("
		SELECT $userfields FROM users u
		WHERE birthday AND FROM_UNIXTIME(birthday, '%m-%d') = '".date('m-d',time() + $loguser['tzoff'])."'
		ORDER BY name
	");
	
	$blist	= "";
	
	for ($numbd = 0; $user = $sql->fetch($users1); ++$numbd) {
		$blist = $numbd ? ", " : "<tr><td class='tdbg2 center fonts' colspan='2'>Birthdays for ".date('F j', time() + $loguser['tzoff']).': ';
		
		$y = date('Y', time()) - date('Y', $user['birthday']);
		$userurl = getuserlink($user);
		$blist .= "$userurl ($y)"; 
	}
	if ($blist)
		$blist .= "</td></tr>";
	
	// Do not move this below the records updates
	$onlineusers = onlineusers();

	/*
		Are we logged in?
	*/
	
	if($loguser['id']){
		$myurl 	= getuserlink($loguser);
		$logmsg = "You are logged in as $myurl.";
	} else {
		$logmsg	= "";
	}
	
	// Lastest user registered
	$lastuser = $sql->fetchq("SELECT $userfields FROM users u ORDER BY u.id DESC LIMIT 1");
	$lastuserurl = $lastuser ? getuserlink($lastuser) : "<i>None</i>";
	
	

	$posts = $sql->fetchq('
		SELECT 	(SELECT COUNT(*) FROM posts WHERE date>'.(time()-3600).')  AS h, 
				(SELECT COUNT(*) FROM posts WHERE date>'.(time()-86400).') AS d');

	$count = $sql->fetchq('
		SELECT 	(SELECT COUNT(*) FROM users)   AS u,
				(SELECT COUNT(*) FROM threads) AS t, 
				(SELECT COUNT(*) FROM posts)   AS p');

	$misc = $sql->fetchq('SELECT maxpostsday, maxpostshour, maxusers FROM misc');
	
	// Have we set a new record?
	if ($posts['d'] > $misc['maxpostsday'])  $sql->query("UPDATE misc SET maxpostsday  = {$posts['d']}, maxpostsdaydate  = ".time());
	if ($posts['h'] > $misc['maxpostshour']) $sql->query("UPDATE misc SET maxpostshour = {$posts['h']}, maxpostshourdate = ".time());
	// $numon is currently thrown out by onlineusers() as a global variable
	if ($numon  > $misc['maxusers']) {
		$sql->queryp("UPDATE misc SET maxusers = :num, maxusersdate = :date, maxuserstext = :text",
			[
				'num'	=> $numon,
				'date'	=> time(),
				'text'	=> $onlineusers,
			]);
	}

	/*// index sparkline
	$sprkq = mysql_query('SELECT COUNT(id),date FROM posts WHERE date >="'.(time()-3600).'" GROUP BY (date % 60) ORDER BY date');
	$sprk = array();
	
	while ($r = mysql_fetch_row($sprkq)) {
		array_push($sprk,$r[0]);
	}
	// print_r($sprk);
	$sprk = implode(",",$sprk); */

	/*
		Recent posts counter
	*/
	if (filter_bool($_GET['oldcounter']))
		$statsblip	= "{$posts['d']} posts during the last day, {$posts['h']} posts during the last hour.";
	else {
		$nthreads = $sql->resultq("SELECT COUNT(*) FROM `threads` WHERE `lastpostdate` > '". (time() - 86400) ."'");
		$nusers   = $sql->resultq("SELECT COUNT(*) FROM `users`   WHERE `lastposttime` > '". (time() - 86400) ."'");
		$tthreads = ($nthreads === 1) ? "thread" : "threads";
		$tusers   = ($nusers   === 1) ? "user" : "users";
		$statsblip	= "$nusers $tusers active in $nthreads $tthreads during the last day.";
	}
	
	?>
		<table class='table'>
			<tr>
				<td class='tdbg1 fonts center'>
					<table class='w'>
						<tr>
							<td class='left'>
								<?=$logmsg?>
							</td>
							<td class='right'>
								<?=$count['u']?> registered users<br>
								Latest registered user: <?=$lastuserurl?>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<?=$blist?>
			<tr>
				<td class='tdbg2 fonts center'>
					<?=$count['t']?> threads and <?=$count['p']?> posts in the board | <?=$statsblip?>
				</td>
			<tr>
				<td class='tdbg1 fonts center'>
					<?= $onlineusers ?>
				</td>
			</tr>
		</table>
	<?php

	// Displays total PMs along with unread unlike layout.php
	if ($loguser['id']) {
		$new     = '&nbsp;';
		$lastmsg = "";
		// Get number of PM threads, and a count of those with unread posts
		$data = $sql->fetchq("
			SELECT COUNT(*) total,
			       COUNT(tr.read OR t.lastpostdate < fr.readdate) tread,
				   MAX(t.lastpostdate) lastpostdate
			FROM pm_threads t
			INNER JOIN pm_access       a ON t.id     = a.thread
			LEFT  JOIN pm_foldersread fr ON a.folder = fr.folder AND a.user = fr.user
			LEFT  JOIN pm_threadsread tr ON t.id     = tr.tid    AND tr.uid = {$loguser['id']}
			WHERE a.user = {$loguser['id']}
		");
		
		if ($data['total']) {
			if ($data['tread'] != $data['total']) {
				$new = $statusicons['new'];
			}
			$thread = $sql->fetchq("
				SELECT t.id tid, $userfields
				FROM pm_threads t
				INNER JOIN pm_access a ON t.id         = a.thread
				INNER JOIN users     u ON t.lastposter = u.id
				WHERE t.lastpostdate = {$data['lastpostdate']} AND a.user = {$loguser['id']}
			");
			$lastmsg = "<a href='showprivate.php?id={$thread['tid']}&lpt={$data['lastpostdate']}'>Last post</a> sent by ".getuserlink($thread)." on ".printdate($data['lastpostdate']);
		}		
		?><br>
			<table class='table'>
				<tr><td class='tdbgh fonts center' colspan=2>Private messages</td></tr>
				<tr>
					<td class='tdbg1 center'><?=$new?></td>
					<td class='tdbg2'>
						<a href='private.php'>Private messages</a> -- You have <?= $data['total'] ?> private conversations (<?= ($data['total'] - $data['tread']) ?> new). <?=$lastmsg?>
					</td>
				</tr>
			</table>
		<?php
	
	}
	
	/*
		Global announcements
	*/
	$annc = $sql->fetchq("
		SELECT t.id aid, t.title atitle, t.description adesc, t.firstpostdate date, t.forum, $userfields, r.readdate
		FROM threads t
		LEFT JOIN users            u ON t.user = u.id
		LEFT JOIN announcementread r ON t.forum = r.forum AND r.user = {$loguser['id']}
		WHERE t.forum = {$config['announcement-forum']}
		ORDER BY t.firstpostdate DESC
		LIMIT 1
	");
	
	if ($annc) {
		?>
		<br/>
		<table class='table'>
			<tr>
				<td colspan=2 class='tdbgh center fonts'>
					Announcements
				</td>
			</tr>
			<tr>
				<td class='tdbg2 center' style='width: 33px'>
					<?=($loguser['id'] && $annc['readdate'] < $annc['date'] ? $statusicons['new'] : "&nbsp;")?>
				</td>
				<td class='tdbg1'>
					<a href="announcement.php"><?=htmlspecialchars($annc['atitle'])?></a> -- Posted by <?=getuserlink($annc)?> on <?=printdate($annc['date'])?>
				</td>
			</tr>
		</table>
		<?php
	}
	
	print hook_print("index-window");
	
	
	// Display recent active threads
	// Part of this was lifted from latestposts.php and tweaked to show threads instead of posts
	
	const _WND_ACTIVE = -4;
	$hidden = filter_int($_COOKIE['hcat'][_WND_ACTIVE]);
	if ($hidden) {
?>
		<br/>
		<table class='table'>
			<tr><td class='tdbgc center'><a href='latestposts.php'>Recently active threads<?= _collapse_toggle(_WND_ACTIVE, $hidden) ?></a></tr>
		</table>
<?php
	} else {
		$data	= $sql->query("
			SELECT
				t.id as id,
				t.lastposter,
				t.lastpostdate as date,
				f.title as ftitle,
				t.forum as fid,
				t.title as title,
				$userfields uid
			FROM `threads` t
			LEFT JOIN `forums` f ON t.forum = f.id
			LEFT JOIN `users` u ON t.lastposter = u.id
			WHERE f.hidden = 0 AND ".can_view_forum_query()."
			ORDER BY t.lastpostdate DESC
			LIMIT 5
			");
		
	?>
			<br/>
			<table class='table'>
				<tr><td class='tdbgc center' colspan='4'><a href='latestposts.php'>Recently active threads<?= _collapse_toggle(_WND_ACTIVE, $hidden) ?></a></tr>
				<tr>
					<td class='tdbgh center' width='25%'>Forum</td>
					<td class='tdbgh center' width='45%'>Thread</td>
					<td class='tdbgh center' width='20%'>User</td>
					<td class='tdbgh center' width='10%'>Time</td>
				</tr>
	<?php		foreach ($data as $in) { ?>
				<tr>
					<td class='tdbg2 center'><a href='forum.php?id=<?=$in['fid']?>'><?= htmlspecialchars($in['ftitle']) ?></a></td>
					<td class='tdbg1'><a href='thread.php?id=<?=$in['id']?>&end=1'><?= htmlspecialchars($in['title']) ?></a></td>
					<td class='tdbg1 center'><?= getuserlink($in, $in['uid']) ?></td>
					<td class='tdbg2 center'><?= timeunits(time() - $in['date']) ?></td>
				</tr>
	<?php		} ?>
		</table>
	<?php
	}


// Hopefully this version won't break horribly if breathed on wrong
	$forumheaders ="
		<tr>
			<td class='tdbgh center' width=50>&nbsp;</td>
			<td class='tdbgh center'>Forum</td>
			<td class='tdbgh center' width=80>Threads</td>
			<td class='tdbgh center' width=80>Posts</td>
			<td class='tdbgh center' width=15%>Last post</td>
		</tr>
	";

	$forumquery = $sql->query("
		SELECT f.*, $userfields uid 
		FROM forums f
		LEFT JOIN users u      ON f.lastpostuser = u.id
		LEFT JOIN categories c ON f.catid = c.id
		WHERE ".can_view_forum_query()."
		AND (!f.hidden OR $sysadmin)
		ORDER BY c.corder, f.catid, f.forder
	");
	$catquery = $sql->query("
		SELECT id, name, ".($loguser['splitcat'] ? "" : "0 ")."side
		FROM categories
		WHERE (!minpower OR minpower <= {$loguser['powerlevel']})
		ORDER BY corder, id
	");
	$modquery = $sql->query("
		SELECT $userfields, m.forum
		FROM users u
		INNER JOIN forummods m ON u.id = m.user
		ORDER BY name
	");

	$categories	= array();
	$forums		= array();
	$mods		= array();

	while ($res = $sql->fetch($catquery))
		$categories[] = $res;
	while ($res = $sql->fetch($forumquery))
		$forums[] = $res;
	while ($res = $sql->fetch($modquery))
		$mods[] = $res;

// Quicker (?) new posts calculation that's hopefully accurate v.v
	if ($loguser['id']) {
		$qadd = array();
		foreach ($forums as $forum) {
			$qadd[] = "(lastpostdate > '".filter_int($postread[$forum['id']])."' AND forum = '{$forum['id']}')\r\n";
		}
		if ($qadd) {
			$qadd = "(".implode(' OR ', $qadd).")";
		} else {
			$qadd = "1";
		}
		$forumnew = $sql->getresultsbykey("
			SELECT forum, COUNT(*) AS unread 
			FROM threads t 
			LEFT JOIN threadsread tr ON (tr.tid = t.id AND tr.uid = {$loguser['id']})
			WHERE (`read` IS NULL OR `read` != 1) AND ({$qadd}) 
			GROUP BY forum
		");
	}
	
	
	$forumlist = array('','');

	// Category filtering	
	foreach ($categories as $category) {
		
		// Hide category by cookie
		$hidden = filter_int($_COOKIE['hcat'][$category['id']]);
		$forumlist[$category['side']] .= "
			<tr id='cat{$category['id']}'>
				<td class='tbl tdbgc center font' colspan=5>
					<a href='index.php?cat={$category['id']}'>".htmlspecialchars($category['name'])."</a>
					"._collapse_toggle($category['id'], $hidden)."
				</td>
			</tr>";
		
		
		if(($hidden || $_GET['cat']) && $_GET['cat'] != $category['id'])
		  continue;

		foreach ($forums as $forumplace => $forum) {
			
			if ($forum['catid'] != $category['id'])
				continue;

			
			
			
			/*
				Local mod display
			*/
			$m = 0;
			$modlist = "";
			foreach ($mods as $modplace => $mod) {
				
				if ($mod['forum'] != $forum['id'])
					continue;

				$namelink = getuserlink($mod);
				$modlist .=($m++?', ':'').$namelink;
				unset($mods[$modplace]);
			}

			if ($modlist)
				$modlist = "(moderated by: $modlist)";

			
			
			
			if($forum['numposts']) {
				$namelink = getuserlink($forum, $forum['uid']);
				$forumlastpost = printdate($forum['lastpostdate']);
				$by =  "<span class='fonts'>
							<br>
							by $namelink". ($forum['lastpostid'] ? " <a href='thread.php?pid={$forum['lastpostid']}#{$forum['lastpostid']}'>{$statusicons['getlast']}</a>" : "")
					  ."</span>";
			} else {
				$forumlastpost = getblankdate();
				$by = '';
			}

			$new='&nbsp;';

			if ($forum['numposts']) {
				// If we're logged in, check the result set
				if ($loguser['id'] && isset($forumnew[$forum['id']]) && $forumnew[$forum['id']] > 0) {
					$new = $statusicons['new'] ."<br>". generatenumbergfx((int)$forumnew[$forum['id']]);
				}
				// If not, mark posts made in the last hour as new
				else if (!$loguser['id'] && $forum['lastpostdate'] > time() - 3600) {
					$new = $statusicons['new'];
				}
			}
/*
			if ($log && $forum['lastpostdate'] > $postread[$forum['id']]) {
		$newcount	= $sql->resultq("SELECT COUNT(*) FROM `threads` WHERE `id` NOT IN (SELECT `tid` FROM `threadsread` WHERE `uid` = '$loguser[id]' AND `read` = 1) AND `lastpostdate` > '". $postread[$forum['id']] ."' AND `forum` = '$forum[id]'");
			}

			if ((($forum['lastpostdate'] > $postread[$forum['id']] and $log) or (!$log and $forum['lastpostdate']>time()-3600)) and $forum['numposts']) {
				$new = $statusicons['new'] ."<br>". generatenumbergfx($newcount);
			}
*/
		  $forumlist[$category['side']] .= "
			<tr>
				<td class='tdbg1 center'>$new</td>
				<td class='tdbg2'>
					<a href='forum.php?id={$forum['id']}'>".htmlspecialchars($forum['title'])."</a><br>
					<span class='fonts'>
						".xssfilters($forum['description'])."<br>
						$modlist
					</span>
				</td>
				<td class='tdbg1 center'>{$forum['numthreads']}</td>
				<td class='tdbg1 center'>{$forum['numposts']}</td>
				<td class='tdbg2 center'>
					<span class='lastpost nobr'>
						$forumlastpost $by
					</span>
				</td>
			</tr>
		  ";

			unset($forums[$forumplace]);
		}
	}
	
	// Split categories
	$fsep = "";
	if ($forumlist[0] && $forumlist[1]) $fsep = "<td></td>";
	if ($forumlist[0]) $forumlist[0] = "<td style='width: 49%' class='vatop'><table class='table'>{$forumheaders}{$forumlist[0]}</table></td>";
	if ($forumlist[1]) $forumlist[1] = "<td style='width: 49%' class='vatop'><table class='table'>{$forumheaders}{$forumlist[1]}</table></td>";
	
	
	?>
	<br>
	<table class='w' cellpadding=0 cellspacing=0 border=0>
		<tr>
			<?= $forumlist[0] . $fsep . $forumlist[1] ?>
		</tr>
	</table>
	<?php
	
	pagefooter();
	

function _collapse_toggle($cat, $hidden) {
	return "<div style='float: right'><a href='?cat={$cat}&toggle=1' title='Click here to ".($hidden ? "expand this section'>[+" : "collapse this section'>[-")."]</a></div>";
}