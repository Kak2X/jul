<?php
	require 'lib/function.php';

	$windowtitle	= "{$config['board-name']} -- Active users";
	
	$tid	= filter_int($_GET['tid']); // Thread ID filtering 
	$type 	= filter_string($_GET['type']);
	
	if (($type == 'pm' || $type == 'pms') && !$loguser['id'])
		$type = NULL;

	if (!isset($_GET['time']))
		$time = 86400;
	else
		$time = (int) $_GET['time'];

	$query 	= "SELECT $userfields, u.regdate, COUNT(*) AS cnt FROM users u";
	$endp 	= " GROUP BY u.id ORDER BY cnt DESC";

	$linklist[0] = "<a href=\"?time=$time\">posts made</a>";
	$linklist[1] = "<a href=\"?type=thread&time=$time\">new threads</a>";
	if ($loguser['id']) {
		$linklist[2] = "<a href=\"?type=pms&time=$time\">PMs sent by you</a>";
		$linklist[3] = "<a href=\"?type=pm&time=$time\">PMs sent to you</a>";
	}

	if ($type == 'thread')	{
		$posters	= $sql->query("$query LEFT JOIN threads t ON t.user = u.id"
			.($time ? " WHERE t.firstpostdate > '". (ctime() - $time) ."'" : '')
			.$endp);
		$desc		= "Most active thread posters";
		$column		= "Threads";
		$column2	= "threads";
		$stat = "most thread creators";
		$linklist[1] = "new threads";

	} elseif ($type == 'pm') {
		$posters	= $sql->query("$query LEFT JOIN pmsgs p ON p.userto = {$loguser['id']}"
			.($time ? " AND p.date> '". (ctime() - $time) ."' AND" : ' WHERE')
			." p.userfrom = u.id$endp");
		$desc		= "PMs recieved from";
		$column		= "PMs";
		$column2	= "PMs";
		$stat = "most message senders";
		$linklist[3] = "PMs sent to you";

	} elseif ($type == 'pms') {
		$posters	= $sql->query("$query LEFT JOIN pmsgs p ON p.userfrom = {$loguser['id']}"
			.($time ? " WHERE p.date> '". (ctime() - $time) ."' AND" : ' WHERE')
			." p.userto = u.id$endp");
		$desc		= "PMs sent to";
		$column		= "PMs";
		$column2	= "PMs";
		$stat = "who you've sent the most messages to";
		$linklist[2] = "PMs sent by you";

	} else {
		$posters	= $sql->query("$query LEFT JOIN posts p ON u.id = p.user WHERE 1"
			.($tid ? " AND p.thread='$tid'" : '')
			.($time ? " AND p.date> '". (ctime() - $time) ."'" : '')
			.$endp);
		$desc		= "Most active posters";
		$column		= "Posts";
		$column2	= "posts";
		$stat = "most active posters";
		$linklist[0] = "posts made";
		$type = '';
	}

	$link = '<a href='.(($type) ? "?type={$type}&" : '?').'time';
	
	pageheader($windowtitle);
	?>
	<table style='width: 100%'>
		<tr>
			<td class='fonts' width=50%>
				Show <?=$stat?> in the:<br>
				<?=$link?>=3600>last hour</a> - <?=$link?>=86400>last day</a> - <?=$link?>=604800>last week</a> - <?=$link?>=2592000>last 30 days</a> - <?=$link?>=0>from the beginning</a>
			</td>
			<td width=50% class='fonts right'>
				Most active users by:<br>
				<?=implode(" - ", $linklist)?>
			</td>
		</tr>
	</table>
	<?php 

	if ($time)
		$timespan = " during the last ". timeunits2($time);
	else
		$timespan = "";

/*
	if ($loguser["powerlevel"] >= 1) {
		// Xk will hate me for using subqueries.
			// No, I'll just hate you for adding this period
			// It's like a sore.
			// Also, uh, interesting I guess. The more you know.
		$pcounts        = $sql -> query("
			SELECT
				(SELECT sum(u.posts) FROM users AS u WHERE u.powerlevel >= 1) AS posts_staff,
				(SELECT sum(u.posts) FROM users AS u WHERE u.powerlevel = 0) AS posts_users,
				(SELECT sum(u.posts) FROM users AS u WHERE u.powerlevel = -1) AS posts_banned");

		$pcounts = $sql->fetch($pcounts);
		print "
		<table class='table'>
		<tr><td class='tdbgh center' colspan=2>Staff vs. Normal User Posts</tr>
		<tr><td class='tdbg1 center'>$pcounts[posts_staff]</td><td class='tdbg1 center'>$pcounts[posts_users]</td></tr>
		<tr><td class='tdbg2 center' colspan=2>The ratio for staff posts to normal user posts is ".round($pcounts["posts_staff"]/$pcounts["posts_users"],3).".</td></tr>
		<tr><td class='tdbg2 center' colspan=2>Not included were the ".abs($pcounts[posts_banned])." posts shat out by a collective of morons. Depressing.</td></tr>
		</table>
		<br>
		";
	}
*/

	?>
	<table class='table'>
		<tr><td class='tdbgc center' colspan=6><b><?=$desc?><?=$timespan?></b></td></tr>
		<tr>
			<td class='tdbgh center' width=30>#</td>
			<td class='tdbgh center' colspan=2>Username</td>
			<td class='tdbgh center' width=200>Registered on</td>
			<td class='tdbgh center' width=130 colspan=2>$column</td>
	<?php

	$total = 0;
	$oldcnt = NULL;
	for ($i = 1; $user = $sql->fetch($posters); ++$i) {
		if ($i == 1) $max = $user['cnt'];
		if ($user['cnt'] != $oldcnt) $rank = $i;
		$oldcnt	= $user['cnt'];
		$ulink = getuserlink($user);
		print "
			<tr>
				<td class='tdbg1 center'>$rank</td>
				<td class='tdbg1 center' width=16>". get_minipic($user['id']) ."</td>
				<td class='tdbg2'>{$ulink}</td>
				<td class='tdbg1 center'>".printdate($user['regdate'])."</td>
				<td class='tdbg2 center' width=30><b>". $user['cnt'] ."</b></td>
				<td class='tdbg2 center' width=100>". number_format($user['cnt'] / $max * 100, 1) ."%<br><img src=images/minibar.png width=\"". number_format($user['cnt'] / $max * 100) ."%\" align=left height=3> </td>
			</tr>";

		$total	+= $user['cnt'];
	}

	?>
		<tr><td class='tdbgc center' colspan=6><?=($i - 1)?> users, <?=$total?> <?=$column2?></td></tr>
	</table>
	<?php

	pagefooter();
