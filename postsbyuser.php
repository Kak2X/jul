<?php
	require "lib/common.php";
	
	$_GET['id'] 	= filter_int($_GET['id']);
	$_GET['forum'] 	= filter_int($_GET['forum']);
	$_GET['time'] 	= filter_int($_GET['time']);
	$_GET['page'] 	= filter_int($_GET['page']);
	$_GET['ppp'] 	= filter_int($_GET['ppp']);
	$_GET['pm'] 	= filter_int($_GET['pm']);
	$_GET['warn'] 	= numrange(filter_int($_GET['warn']), PWARN_MIN, PWARN_MAX);
	
	const _PBU_DEFAULT_PPP = 50;
	
	
	if (!$_GET['id']) {
		errorpage('No user specified.', 'index.php', 'return to the board',);
	}
	if ($_GET['pm'] && !$isadmin && $_GET['id'] != $loguser['id'])
		errorpage("You can't do this.", 'index.php', 'return to the board');
	
	$user = $sql->resultq("SELECT name FROM users WHERE id = {$_GET['id']}");


	if ($_GET['forum']) {
		$forum = $sql->fetchq("SELECT title, minpower, login FROM forums WHERE id = {$_GET['forum']}");
		if (!can_view_forum($forum)) {
			errorpage("You don't have access to view posts in this forum.", 'index.php', 'return to the board');
		}
		$where 		= "in ".htmlspecialchars($forum['title']);
		$forumquery = " AND t.forum = {$_GET['forum']}";
	} else {
		$where 		= "on the board";
		$forumquery = '';
	}

	if ($_GET['time']) {
		$when = " over the past ".timeunits2($_GET['time']);
		$timequery = ' AND p.date > ' . (time()-$_GET['time']);
	} else {
		$timequery = $when = '';
	}
	
	if ($_GET['warn']) {
		if ($_GET['warn'] == 2)
			$warnquery = " AND p.warned = 1";
		else
			$warnquery = " AND p.warned > 0";
		$orderby   = "p.warndate DESC";
	} else {
		$warnquery = "";
		$orderby   = "p.id DESC";
	}
	
	
	if (!$_GET['page']) $_GET['page'] = 0;
 	if (!$_GET['ppp'])  $_GET['ppp'] = _PBU_DEFAULT_PPP;
	$min = $_GET['ppp'] * $_GET['page'];
	

	$posts = $sql->query(
		 "SELECT p.id, p.thread, p.ip, p.date, p.num, p.deleted, p.warned, p.warndate, p.warntext, t.title, f.minpower "
		."FROM posts p "
		."LEFT JOIN threads t ON thread  = t.id "
		."LEFT JOIN forums  f ON t.forum = f.id "
		."WHERE p.user = {$_GET['id']}{$forumquery}{$timequery}{$warnquery} AND ($ismod OR !ISNULL(f.id)) "
		."ORDER BY {$orderby} "
		."LIMIT $min,{$_GET['ppp']}");
		
		
	$posttotal = $sql->resultq("
		SELECT COUNT(*) FROM posts p 
		LEFT JOIN threads t ON thread  = t.id
		LEFT JOIN forums  f ON t.forum = f.id
		WHERE p.user = {$_GET['id']}{$forumquery}{$timequery}{$warnquery} AND ($ismod OR !ISNULL(f.id))
	");
	
	if ($_GET['pm']) {
		$pmposts = $sql->query(
			 "SELECT p.id, p.thread, p.ip, p.date, p.deleted, p.warned, p.warndate, p.warntext, t.title, a.id access "
			."FROM pm_posts p "
			."LEFT JOIN pm_threads t ON p.thread = t.id "
			."LEFT JOIN pm_access  a ON a.thread = t.id AND a.user = {$_GET['id']} "
			."WHERE p.user = {$_GET['id']}{$timequery}{$warnquery} AND ($isadmin OR !ISNULL(t.id)) "
			."ORDER BY {$orderby} "
			."LIMIT $min,{$_GET['ppp']}");
			
		//	d($pmposts->fetchAll());
		$pmposttotal = $sql->resultq("
			SELECT COUNT(*) FROM pm_posts p 
			LEFT JOIN pm_threads t ON p.thread  = t.id
			WHERE p.user = {$_GET['id']}{$timequery}{$warnquery} AND ($isadmin OR !ISNULL(t.id))
		");
	}


	pageheader("Listing posts by $user");
	print "<style>.pbu-table img { float: left }</style>";
	
	const WARNLBLS = ["", "Warnings | ", "Unread warnings | "];
	$warnlbl     = WARNLBLS[$_GET['warn']];
	$postperpage = ($_GET['ppp'] != _PBU_DEFAULT_PPP) ? "&ppp={$_GET['ppp']}" : "";
	$forumlink   = $forumquery ? "&forum={$_GET['forum']}" : "";
	$warnlink    = $warnquery ? "&warn={$_GET['warn']}" : "";
	$pmlink      = $_GET['pm'] ? "&pm=1" : "";
	
	$pagelinks   = "<div class='fonts'>".pagelist("?id={$_GET['id']}{$postperpage}{$forumlink}{$warnlink}{$pmlink}", $posttotal, $_GET['ppp'], true)."</div>";
		
	// Selected option isn't a link	
	$z = ['a','a','a'];
	$z[$_GET['warn']] = 'b';
	
	print "<div class='font'>
		{$warnlbl}Posts by {$user} {$where}{$when}: ({$posttotal} posts found)
		<span class='fonts' style='float: right'>
			<{$z[0]} href='?id={$_GET['id']}{$postperpage}{$forumlink}{$pmlink}&warn=0'>View all</{$z[0]}>
			- <{$z[1]} href='?id={$_GET['id']}{$postperpage}{$forumlink}{$pmlink}&warn=1'>View warnings</{$z[1]}>
			- <{$z[2]} href='?id={$_GET['id']}{$postperpage}{$forumlink}{$pmlink}&warn=2'>View unread</{$z[2]}>
		</span>
	</div>
	". drawtable($posts, "thread", "Post", false)."
	{$pagelinks}";

if ($_GET['pm']) {
	$pagelinks   = "<div class='fonts'>".pagelist("?id={$_GET['id']}{$postperpage}{$forumlink}{$warnlink}{$pmlink}", $pmposttotal, $_GET['ppp'], true)."</div>";
	print "<br><div class='font'>{$warnlbl}PMs by {$user} {$where}{$when}: ({$pmposttotal} PMs found)</div>
	". drawtable($pmposts, "showprivate", "PM", true)."
	{$pagelinks}";
}

	pagefooter();
	
function drawtable($posts, $page, $type, $ispm) {
	global $isadmin, $loguser, $warnpic;
	$res = "
<table class='table pbu-table'>
	<tr>
		<td class='tdbgh fonts center' style='width: 50px'>#</td>
		".($ispm ? "" : "<td class='tdbgh fonts center' style='width: 50px'>{$type}</td>")."
		<td class='tdbgh fonts center' style='width: 130px'>Date</td>
		<td class='tdbgh fonts center'>Thread</td>
		<td class='tdbgh fonts center' style='width: 130px'>Warn Date</td>
		<td class='tdbgh fonts center'>Warn Message</td>
		".(($isadmin) ? "<td class='tdbgh fonts center' width=110>IP address</td>" : "")."
	</tr>
";
	foreach ($posts as $post) {
		
		$noaccess = $ispm 
			? !$post['access']
			: $post['minpower'] && $post['minpower'] > $loguser['powerlevel'];
		if ($noaccess)
			$postlink = '(restricted)';
		else
			$postlink = "<a href='{$page}.php?pid={$post['id']}#{$post['id']}'>".escape_html($post['title'])."</a>";
		
		$strike = ($post['deleted'] ? " style='text-decoration: line-through'" : "");
		
		if ($post['warned']) {
			$warning = "<td class='tdbg1 fonts center'>".printdate($post['warndate'])."</td>";
			$unread = ($isadmin || $loguser['id'] == $_GET['id']) && $post['warned'] == PWARN_WARN ? "<span class='icon-16' title='Unread warning'>{$warnpic}</span>" : "";
			$threadlink = "<b>{$post['thread']}</b>"; // disable thread link for this
		} else {
			$warning = "<td class='tdbg1 fonts' colspan='2'></td>";
			$unread = "";
			$threadlink = "<a href='thread.php?id={$post['thread']}'>{$post['thread']}</a>";
		}
	
			
			
		$res .= "
		<tr>
			<td class='tdbg1 fonts center'{$strike}>$unread<a href='{$page}.php?pid={$post['id']}#{$post['id']}'>{$post['id']}</a></td>
			".($ispm ? "" : "<td class='tdbg1 fonts center'{$strike}>".($post['num'] ? $post['num'] : "?")."</td>")."
			<td class='tdbg1 fonts center'>".printdate($post['date'])."</td>
			<td class='tdbg1 fonts'{$strike}>#{$threadlink} - {$postlink}</td>
			<td class='tdbg1 fonts'>".($post['warned'] ? printdate($post['warndate']) : "")."</td>
			<td class='tdbg1 fonts'>".($post['warned'] ? dofilters($post['warntext']) : "")."</td>
			".($isadmin ? "<td class='tdbg1 fonts center'>".htmlspecialchars($post['ip'])."</td>" : "")."
		</tr>";
	}
	 
	return $res."</table>";
}
	