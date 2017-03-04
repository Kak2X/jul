<?php
	require 'lib/function.php';
	
	$_GET['id'] 	= filter_int($_GET['id']);
	$_GET['forum'] 	= filter_int($_GET['forum']);
	$_GET['time'] 	= filter_int($_GET['time']);
	$_GET['page'] 	= filter_int($_GET['page']);
	$_GET['ppp'] 	= filter_int($_GET['ppp']);
	
	
	if (!$_GET['id']) {
		errorpage('No user specified.', 'return to the board', 'index.php');
	}
	
	$user = $sql->resultq("SELECT name FROM users WHERE id = {$_GET['id']}");


	if ($_GET['forum']) {
		$forumperm = get_forum_perm($_GET['forum'], $loguser['id'], $loguser['group']);
		if (!is_array($forumperm) || !has_forum_perm('read', $forumperm)) {
			errorpage("You don't have access to view posts in this forum.", 'index.php', 'return to the board');
		}
		$forum['title'] = $sql->resultq("SELECT title FROM forums WHERE id = {$_GET['forum']}");
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
 	if (!$_GET['ppp'])  $_GET['ppp'] = 50;
	$min = $_GET['ppp'] * $_GET['page'];

	$posts = $sql->query("
		SELECT p.id, p.thread, p.ip, p.date, p.num, t.title, pf.group{$loguser['group']} forumperm, pu.permset userperm
		FROM posts p 
		LEFT JOIN threads          t ON p.thread = t.id 
		LEFT JOIN forums           f ON t.forum  = f.id 
		LEFT JOIN perm_forums     pf ON f.id     = pf.id
		LEFT JOIN perm_forumusers pu ON f.id     = pu.forum AND pu.user = {$loguser['id']}
		WHERE p.user = {$_GET['id']}{$forumquery}{$timequery} AND (".has_perm('forum-admin')." OR !ISNULL(f.id)) 
		ORDER BY p.id DESC 
		LIMIT $min,{$_GET['ppp']}
	");
		
		
	$posttotal = $sql->resultq("
		SELECT COUNT(*) FROM posts p 
		LEFT JOIN threads t ON thread  = t.id
		LEFT JOIN forums  f ON t.forum = f.id
		WHERE p.user = {$_GET['id']}{$forumquery}{$timequery} AND (".has_perm('forum-admin')." OR !ISNULL(f.id))
	");

	// No scrollable cursors in PDO+MySQL
	//$posttotal=mysql_num_rows($posts);
	// Seek to page
	//if (!@mysql_data_seek($posts, $min)) $_GET['page'] = 0;

	$pagelinks = '<span class="fonts">Pages:';
	$forumlink = "";
	for($i = 0, $max = $posttotal/$_GET['ppp']; $i < $max; ++$i) {
		if ($i == $_GET['page']) {
			$pagelinks .= ' '.($i+1);
		} else {
			if ($_GET['ppp'] != 50) $postperpage = "&ppp={$_GET['ppp']}";
			if ($forumquery) $forumlink = "&forum={$_GET['forum']}";
			$pagelinks .=" <a href='postsbyuser.php?id={$_GET['id']}$postperpage$forumlink&page=$i'>".($i+1).'</a>';
		}
	}
	$pagelinks .= "</span>";
	
	pageheader("Listing posts by $user");
	
	$isadmin = has_perm('admin-actions');
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
		
		if (!has_forum_perm('read', $post))
			$threadlink = '(restricted)';
		else
			$threadlink = "<a href='thread.php?pid={$post['id']}#{$post['id']}'>".htmlspecialchars($post['title'])."</a>";

		if (!$post['num']) $post['num'] = '?';

		?>
		<tr>
			<td class='tdbg1 fonts center'><?=$post['id']?></td>
			<td class='tdbg1 fonts center'><?=$post['num']?></td>
			<td class='tdbg1 fonts center'><?=printdate($post['date'])?></td>
			<td class='tdbg1 fonts'>#<a href="thread.php?id=<?=$post['thread']?>"><?=$post['thread']?></a> - <?=$threadlink?>
			<?=($isadmin ? "</td><td class='tdbg1 fonts center'>{$post['ip']}" : "")?>
		</tr>
		<?php
	 }
?>	</table>
	<?=$pagelinks?>
<?php

	pagefooter();
	
?>