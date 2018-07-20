<?php
	require 'lib/function.php';
	
	$_GET['id'] 	= filter_int($_GET['id']);
	$_GET['forum'] 	= filter_int($_GET['forum']);
	$_GET['time'] 	= filter_int($_GET['time']);
	$_GET['page'] 	= filter_int($_GET['page']);
	$_GET['ppp'] 	= filter_int($_GET['ppp']);
	
	const PBU_DEFAULT_PPP = 50;
	
	
	if (!$_GET['id']) {
		errorpage('No user specified.', 'return to the board', 'index.php');
	}
	
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
		$timequery = ' AND p.date > ' . (ctime()-$_GET['time']);
	} else {
		$timequery = $when = '';
	}
	
	if (!$_GET['page']) $_GET['page'] = 0;
 	if (!$_GET['ppp'])  $_GET['ppp'] = PBU_DEFAULT_PPP;
	$min = $_GET['ppp'] * $_GET['page'];

	$posts = $sql->query(
		 "SELECT p.id, p.thread, p.ip, p.date, p.num, p.deleted, t.title, f.minpower "
		."FROM posts p "
		."LEFT JOIN threads t ON thread  = t.id "
		."LEFT JOIN forums  f ON t.forum = f.id "
		."WHERE p.user = {$_GET['id']}{$forumquery}{$timequery} AND ($ismod OR !ISNULL(f.id)) "
		."ORDER BY p.id DESC "
		."LIMIT $min,{$_GET['ppp']}");
		
		
	$posttotal = $sql->resultq("
		SELECT COUNT(*) FROM posts p 
		LEFT JOIN threads t ON thread  = t.id
		LEFT JOIN forums  f ON t.forum = f.id
		WHERE p.user = {$_GET['id']}{$forumquery}{$timequery} AND ($ismod OR !ISNULL(f.id))
	");

	// No scrollable cursors in PDO+MySQL
	//$posttotal=mysql_num_rows($posts);
	// Seek to page
	//if (!@mysql_data_seek($posts, $min)) $_GET['page'] = 0;

	$postperpage = ($_GET['ppp'] != PBU_DEFAULT_PPP) ? "&ppp={$_GET['ppp']}" : "";
	$forumlink   = $forumquery ? "&forum={$_GET['forum']}" : "";
	$pagelinks = "<span class='fonts'>".pagelist("?id={$_GET['id']}{$postperpage}{$forumlink}", $posttotal, $_GET['ppp'], true)."</span>";
	
	pageheader("Listing posts by $user");
	
?>
<span class="font">Posts by <?=$user?> <?=$where?><?=$when?>: (<?=$posttotal?> posts found)</span>
<?php

?>
<table class="table">
	<tr>
		<td class='tdbgh fonts center' width=50>#</td>
		<td class='tdbgh fonts center' width=50>Post</td>
		<td class='tdbgh fonts center' width=130>Date</td>
		<td class='tdbgh fonts center'>Thread</td>
		<?=(($isadmin) ? "<td class='tdbgh fonts center' width=110>IP address</td>" : "")?>
	</tr>
<?php

	while(($post = $sql->fetch($posts)) && $_GET['ppp']--) {
		
		if ($post['minpower'] && $post['minpower'] > $loguser['powerlevel'])
			$threadlink = '(restricted)';
		else
			$threadlink = "<a href='thread.php?pid={$post['id']}#{$post['id']}'>".htmlspecialchars($post['title'])."</a>";
		
		$strike = ($post['deleted'] ? " style='text-decoration: line-through'" : "");

		if (!$post['num']) $post['num'] = '?';

		?>
		<tr>
			<td class='tdbg1 fonts center'<?=$strike?>><?=$post['id']?></td>
			<td class='tdbg1 fonts center'<?=$strike?>><?=$post['num']?></td>
			<td class='tdbg1 fonts center'><?=printdate($post['date'])?></td>
			<td class='tdbg1 fonts'<?=$strike?>>#<a href="thread.php?id=<?=$post['thread']?>"><?=$post['thread']?></a> - <?=$threadlink?>
			<?=($isadmin ? "</td><td class='tdbg1 fonts center'>{$post['ip']}" : "")?>
		</tr>
		<?php
	 }
?>	</table>
	<?=$pagelinks?>
<?php

	pagefooter();
	
?>