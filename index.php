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

	require 'lib/function.php';

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
				$sql->query("INSERT INTO forumread (user, forum, readdate) VALUES ({$loguser['id']}, $id, ".ctime().')');
				break;
			case 'markallforumsread':
				$sql->query("DELETE FROM forumread WHERE user = {$loguser['id']}");
				$sql->query("DELETE FROM threadsread WHERE uid = {$loguser['id']}");
				$sql->query("INSERT INTO forumread (user, forum, readdate) SELECT {$loguser['id']}, id, ".ctime()." FROM forums");
				break;
		}
		
		header("Location: index.php");
		die;
	}

	// Move it after the auto-redirect actions, otherwise the redirect breaks
	pageheader();
		
	$postread = readpostread($loguser['id']);
	
	/*
		Birthday calculation
	*/

	$users1 = $sql->query("
		SELECT $userfields FROM users u
		WHERE birthday AND FROM_UNIXTIME(birthday, '%m-%d') = '".date('m-d',ctime() + $loguser['tzoff'])."'
		ORDER BY name
	");
	
	$blist	= "";
	
	for ($numbd = 0; $user = $sql->fetch($users1); ++$numbd) {
		$blist = $numbd ? ", " : "<tr><td class='tdbg2 center's colspan=5>Birthdays for ".date('F j', ctime() + $loguser['tzoff']).': ';
		
		$y = date('Y', ctime()) - date('Y', $user['birthday']);
		$userurl = getuserlink($user);
		$blist .= "$userurl ($y)"; 
	}
	
	/*
		Online users
	*/
	$onlinetime = ctime() - 300;	// 5 Minutes
	$onusers = $sql->query("
		SELECT $userfields, hideactivity, (lastactivity <= $onlinetime) nologpost
		FROM users u
		WHERE lastactivity > $onlinetime OR lastposttime > $onlinetime AND ($ismod OR !hideactivity)
		ORDER BY name
	");
	$numonline = $sql->num_rows($onusers);
	$tnumonline = ($numonline !=1 ? 's' : '' );

	$onlineusersa	= array();
	while($onuser = $sql->fetch($onusers)) {
		
		//$namecolor=explode("=", getnamecolor($onuser['sex'],$onuser['powerlevel']));
		//$namecolor=$namecolor[1];
		//$namelink="<a href=profile.php?id=$onuser[id] style='color: #$namecolor'>$onuser[name]</a>";

		$namelink = getuserlink($onuser);
		$minipic  = get_minipic($onuser['id'], $onuser['minipic']);
		
		// Posted using alternate credentials / without using cookies?
		if($onuser['nologpost']) {
			$namelink = "($namelink)";
		}		
		
		if($onuser['hideactivity'])
			$namelink="[$namelink]";	
		$onlineusersa[] = "{$minipic} $namelink";
	}

	$onlineusers = $onlineusersa ? ': '. implode(", ", $onlineusersa) : '';
	
	/*
		Online guests
	*/
	if (!$isadmin) {
		$numguests = $sql->resultq("SELECT COUNT(*) FROM guests WHERE date > $onlinetime");
		$onlineguests = $numguests ? " | <nobr>$numguests guest".($numguests>1?"s":"") : "";
	} else {
		// Detailed view of tor/proxy/bots
		$onguests = $sql->query("SELECT flags FROM guests WHERE date > $onlinetime");
		$ginfo = array_fill(0, 4, 0);
		for ($numguests = 0; $onguest = $sql->fetch($onguests); ++$numguests) {
			if      ($onguest['flags'] & BPT_TOR) 		$ginfo[2]++;
			else if ($onguest['flags'] & BPT_IPBANNED) 	$ginfo[0]++;
			else if ($onguest['flags'] & BPT_BOT) 		$ginfo[3]++;
			//if ($onguest['flags'] & BPT_PROXY) 		$ginfo[1]++;
		}
		$specinfo = array('IP banned', 'Proxy', 'Tor banned', 'bots');
		$guestcat = array();
		for ($i = 0; $i < 4; ++$i)
			if ($ginfo[$i])
				$guestcat[] = $ginfo[$i] . " " . $specinfo[$i];
		$onlineguests = $numguests ? " | <nobr>$numguests guest".($numguests>1?"s":"").($guestcat ? " (".implode(",", $guestcat).")" : "") : "";
	}

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
	if ($lastuser)
		$lastuserurl = getuserlink($lastuser);
	
	

	$posts = $sql->fetchq('
		SELECT 	(SELECT COUNT(*) FROM posts WHERE date>'.(ctime()-3600).')  AS h, 
				(SELECT COUNT(*) FROM posts WHERE date>'.(ctime()-86400).') AS d');

	$count = $sql->fetchq('
		SELECT 	(SELECT COUNT(*) FROM users)   AS u,
				(SELECT COUNT(*) FROM threads) AS t, 
				(SELECT COUNT(*) FROM posts)   AS p');

	$misc = $sql->fetchq('SELECT maxpostsday, maxpostshour, maxusers FROM misc');
	
	// Have we set a new record?
	if($posts['d'] > $misc['maxpostsday'])  $sql->query("UPDATE misc SET maxpostsday  = {$posts['d']}, maxpostsdaydate  = ".ctime());
	if($posts['h'] > $misc['maxpostshour']) $sql->query("UPDATE misc SET maxpostshour = {$posts['h']}, maxpostshourdate = ".ctime());
	if($numonline  > $misc['maxusers']) {
		$sql->queryp("UPDATE misc SET maxusers = :num, maxusersdate = :date, maxuserstext = :text",
			[
				'num'	=> $numonline,
				'date'	=> ctime(),
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
		$nthreads = $sql->resultq("SELECT COUNT(*) FROM `threads` WHERE `lastpostdate` > '". (ctime() - 86400) ."'");
		$nusers   = $sql->resultq("SELECT COUNT(*) FROM `users`   WHERE `lastposttime` > '". (ctime() - 86400) ."'");
		$tthreads = ($nthreads === 1) ? "thread" : "threads";
		$tusers   = ($nusers   === 1) ? "user" : "users";
		$statsblip	= "$nusers $tusers active in $nthreads $tthreads during the last day.";
	}
	
	?>
		<table class='table'>
			<tr>
				<td class='tdbg1 fonts center'>
					<table width=100%>
						<tr>
							<td class='fonts'>
								<?=$logmsg?>
							</td>
							<td align=right class='fonts'>
								<?=$count['u']?> registered users<br>
								Latest registered user: <?=$lastuserurl?>
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<?=$blist?>
			</tr>
			<tr>
				<td class='tdbg2 fonts center'>
					<?=$count['t']?> threads and <?=$count['p']?> posts in the board | <?=$statsblip?>
				</td>
			<tr>
				<td class='tdbg1 fonts center'>
					<?=$numonline?> user<?=$tnumonline?> currently online<?=$onlineusers?><?=$onlineguests?> 
				</td>
			</tr>
		</table>
	<?php

	// Displays total PMs along with unread unlike layout.php
	$new = '&nbsp;';
	$privatebox = '';
	if ($loguser['id']) {
		
		$pms = $sql->getresultsbykey("
			SELECT msgread, COUNT(*) num
			FROM pmsgs
			WHERE userto = {$loguser['id']}
			GROUP BY msgread
		");
		
		// 0 -> unread ; 1 -> read
		$totalpms = filter_int($pms[0]) + filter_int($pms[1]);

		if ($totalpms) {
			
			if ($pms[0]) $new = $statusicons['new'];

			$pmsg = $sql->fetchq("
				SELECT p.date, p.id pid, $userfields
				FROM pmsgs p
				INNER JOIN users u ON u.id = p.userfrom
				WHERE p.userto = {$loguser['id']}". (($pms[0]) ? " AND p.msgread = 0": "") ."
				ORDER BY p.id DESC
				LIMIT 1
			");

			$namelink = getuserlink($pmsg);
			$lastmsg = "<a href='showprivate.php?id={$pmsg['pid']}'>Last ". (($pms[0]) ? "unread " : "") ."message</a> from $namelink on ".printdate($pmsg['date']);
		} else {
			$lastmsg = "";
		}
		
		?><br>
			<table class='table'>
				<tr>
					<td class='tdbgh fonts center' colspan=2>
						Private messages
					</td>
				</tr>
				<tr>
					<td class='tdbg1 center'>
						<?=$new?>
					</td>
					<td class='tdbg2'>
						<a href='private.php'>Private messages</a> -- You have <?=$totalpms?> private messages (<?=(int) $pms[0]?> new). <?=$lastmsg?>
					</td>
				</tr>
			</table>
		<br>
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
	
	if($annc) {
		?>
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
					<a href="announcement.php"><?=$annc['atitle']?></a> -- Posted by <?=getuserlink($annc)?> on <?=printdate($annc['date'])?>
				</td>
			</tr>
		</table>
		<br>
		<?php
	}

// Hopefully this version won't break horribly if breathed on wrong
	$forumlist="
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
		WHERE (!f.minpower OR f.minpower <= {$loguser['powerlevel']})
		AND (!f.hidden OR $sysadmin)
		ORDER BY c.corder, f.catid, f.forder
	");
	$catquery = $sql->query("
		SELECT id, name
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
			if (!isset($postread[$forum['id']])) continue;
			$qadd[] = "(lastpostdate > '{$postread[$forum['id']]}' AND forum = '{$forum['id']}')\r\n";
		}
		
		if ($qadd)
			$qadd = "(".implode(' OR ', $qadd).")";
		else
			$qadd = "1";

		$forumnew = $sql->getresultsbykey("
			SELECT forum, COUNT(*) AS unread
			FROM threads t
			LEFT JOIN threadsread tr ON (tr.tid = t.id AND tr.uid = {$loguser['id']})
			WHERE (ISNULL(`read`) OR `read` != 1) AND $qadd
			GROUP BY forum
		");
		
	}

	// Category filtering
	$cat	= filter_int($_GET['cat']);
	
	foreach ($categories as $category) {
		
		$forumlist .= "
			<tr>
				<td class='tbl tdbgc center font' colspan=5>
					<a href='index.php?cat={$category['id']}'>".htmlspecialchars($category['name'])."</a>
				</td>
			</tr>";
		
		
		if($cat && $cat != $category['id'])
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
				$modlist = "<span class='fonts'>(moderated by: $modlist)</span>";

			
			
			
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
				else if (!$loguser['id'] && $forum['lastpostdate'] > ctime() - 3600) {
					$new = $statusicons['new'];
				}
			}
/*
			if ($log && $forum['lastpostdate'] > $postread[$forum['id']]) {
		$newcount	= $sql->resultq("SELECT COUNT(*) FROM `threads` WHERE `id` NOT IN (SELECT `tid` FROM `threadsread` WHERE `uid` = '$loguser[id]' AND `read` = 1) AND `lastpostdate` > '". $postread[$forum['id']] ."' AND `forum` = '$forum[id]'");
			}

			if ((($forum['lastpostdate'] > $postread[$forum['id']] and $log) or (!$log and $forum['lastpostdate']>ctime()-3600)) and $forum['numposts']) {
				$new = $statusicons['new'] ."<br>". generatenumbergfx($newcount);
			}
*/
		  $forumlist.="
			<tr>
				<td class='tdbg1 center'>$new</td>
				<td class='tdbg2'>
					<a href='forum.php?id={$forum['id']}'>".htmlspecialchars($forum['title'])."</a><br>
					<span class='fonts'>
						{$forum['description']}<br>
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

	?>
	<br>
	<table class='table'>
		<?=$forumlist?>
	</table>
	<?php
	
	pagefooter();
	
?>
