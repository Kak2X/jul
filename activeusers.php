<?php
	require 'lib/function.php';

	$windowtitle    = "{$config['board-name']} -- Active users";
	
	$_GET['tid']    = filter_int($_GET['tid']); // Thread ID filtering for 'post' type 
	$_GET['time']   = filter_int($_GET['time']);
	$_GET['type']   = filter_string($_GET['type']);
	
	// Pick defaults!
	if (!$_GET['time']) $_GET['time'] = 86400; // 1 day
	if (!$_GET['type']) $_GET['type'] = 'post';
	
	// Can't view sent/received PMs if we're not logged in
	if (($_GET['type'] == 'pm' || $_GET['type'] == 'pmt') && !$loguser['id']) {
		$_GET['type'] = 'post';
	}
	// Activity type selection
	$linklist[0] = "<a href=\"?type=post&time={$_GET['time']}\">posts made</a>";
	$linklist[1] = "<a href=\"?type=thread&time={$_GET['time']}\">new threads</a>";
	if ($loguser['id']) {
		$linklist[2] = "<a href=\"?type=pm&time={$_GET['time']}\">PMs sent</a>";
		$linklist[3] = "<a href=\"?type=pmt&time={$_GET['time']}\">new conversations</a>";
	}
	
	// Time boundary
	$qtime  = ctime() - $_GET['time'];
	
	$query 	= "SELECT $userfields, u.regdate, COUNT(*) AS cnt FROM users u";
	$endp   = "GROUP BY u.id ORDER BY cnt DESC";

	// Type-specific query & strings
	switch ($_GET['type']) {
		case 'post':
			$posters = $sql->query("
				{$query}
				LEFT JOIN posts p ON u.id = p.user 
				WHERE 1 ".
				($_GET['tid']  ? " AND p.thread = {$_GET['tid']}" : '').
				($_GET['time'] ? " AND p.date   > {$qtime}" : '')." 
				{$endp}
			");
			$desc        = "Most active posters"; // Table title
			$column      = "Posts"; // Column title for progress bar
			$column2     = "posts"; // Describes what the total is for in the table footer
			$stat        = "most active posters"; // Type description (top-left)
			$linklist[0] = strip_tags($linklist[0]); // disallow selection of selected type
			break;
			
		case 'thread':
			$posters = $sql->query("
				{$query} 
				LEFT JOIN threads t ON t.user = u.id ".
				($_GET['time'] ? "WHERE t.firstpostdate > {$qtime}" : '')." 
				{$endp}
			");
			$desc        = "Most active thread posters"; // Table title
			$column      = "Threads"; // Column title for progress bar
			$column2     = "threads"; // Describes what the total is for in the table footer
			$stat        = "most thread creators"; // Type description (top-left)
			$linklist[1] = strip_tags($linklist[1]); // disallow selection of selected type
			break;

		case 'pm':
			$posters = $sql->query("
				{$query} 
				LEFT JOIN pm_posts p ON u.id = p.user ".
				($_GET['time'] ? "WHERE p.date > {$qtime}" : "")." 
				{$endp}
			");
			$desc        = "PMs sent"; // Table title
			$column      = "PMs"; // Column title for progress bar
			$column2     = "PMs"; // Describes what the total is for in the table footer
			$stat        = "who sent the most messages"; // Type description (top-left)
			$linklist[2] = strip_tags($linklist[2]); // disallow selection of selected type
			break;
	
		case 'pmt':
			$posters = $sql->query("
				{$query} 
				LEFT JOIN pm_threads t ON u.id = t.user ".
				($_GET['time'] ? "WHERE t.firstpostdate > {$qtime}" : "")." 
				{$endp}
			");
			$desc        = "Conversations started"; // Table title
			$column      = "Threads"; // Column title for progress bar
			$column2     = "threads"; // Describes what the total is for in the table footer
			$stat        = "most conversation creators"; // Type description (top-left)
			$linklist[3] = strip_tags($linklist[3]); // disallow selection of selected type
			break;
			
		default: // No bonus!
			errorpage("Good job, you selected a nonexisting type.");
	}

	pageheader($windowtitle);
	
	// Time / Type selection
	?>
	<table class='w'>
		<tr>
			<td class='fonts' style='width: 50%'>
				Show <?=$stat?> in the:<br>
				<a href='?type=<?=$_GET['type']?>&time=3600'>last hour</a> - 
				<a href='?type=<?=$_GET['type']?>&time=86400'>last day</a> - 
				<a href='?type=<?=$_GET['type']?>&time=604800'>last week</a> - 
				<a href='?type=<?=$_GET['type']?>&time=2592000'>last 30 days</a> - 
				<a href='?type=<?=$_GET['type']?>&time=0'>from the beginning</a>
			</td>
			<td class='fonts right'>
				Most active users by:<br>
				<?=implode(" - ", $linklist)?>
			</td>
		</tr>
	</table>
	<?php 

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
	
	// An infinite time span doesn't require its own description
	$timespan = $_GET['time'] ? " during the last ". timeunits2($_GET['time']) : "";

	// Table title and column desc
	?>
	<table class='table'>
		<tr><td class='tdbgc center b' colspan=6><?=$desc?><?=$timespan?></td></tr>
		<tr>
			<td class='tdbgh center' style='width: 30px'>#</td>
			<td class='tdbgh center' colspan=2>Username</td>
			<td class='tdbgh center' style='width: 200px'>Registered on</td>
			<td class='tdbgh center' style='width: 130px' colspan=2><?=$column?></td>
	<?php

	$total  = 0;
	$oldcnt = NULL;
	for ($i = 1; $user = $sql->fetch($posters); ++$i) {
		if ($i == 1) $max = $user['cnt'];
		
		// If two people share <post/thread/..> count, they share rank
		// The rank jumps down once the count no longer matches
		if ($user['cnt'] != $oldcnt) $rank = $i;

		$oldcnt	= $user['cnt'];
		$ratio  = $user['cnt'] / $max * 100;
		
		print "
		<tr>
			<td class='tdbg1 center'>{$rank}</td>
			<td class='tdbg1 center' style='width: {$config['max-minipic-size-x']}px'>
				". get_minipic($user['id'], $user['minipic']) ."
			</td>
			<td class='tdbg2'>". getuserlink($user) ."</td>
			<td class='tdbg1 center'>". printdate($user['regdate']) ."</td>
			<td class='tdbg2 center b' style='width: 30px'>{$user['cnt']}</td>
			<td class='tdbg2 center' style='width: 100px'>
				". number_format($ratio, 1) ."%<br>
				". drawminibar($max, 3, $user['cnt']) ."
			</td>
		</tr>";

		$total	+= $user['cnt'];
	}

	?>
		<tr><td class='tdbgc center' colspan=6><?=($i - 1)?> users, <?=$total?> <?=$column2?></td></tr>
	</table>
	<?php

	pagefooter();
