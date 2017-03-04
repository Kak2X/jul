<?php
	require 'lib/function.php';

	$_GET['id'] = filter_int($_GET['id']);
	
	$qstrings = array();

	if ($_GET['id']) {
		$qstrings[] = "p.user = {$_GET['id']}";
		$by = 'by '.$sql->resultq("SELECT name FROM users WHERE id = {$_GET['id']}");
	} else {
		$by = "";
	}

	$_GET['posttime'] = isset($_GET['posttime']) ? (int) $_GET['posttime'] : 86400;
	
	if (!$_GET['id'] && (!$_GET['posttime'] || $_GET['posttime'] > 2592000)) // 30 days max
		$_GET['posttime'] = 2592000;

	if ($_GET['posttime']) {
		$qstrings[] = "p.date > ".(ctime()-$_GET['posttime']);
		$during = ' during the last '.timeunits2($_GET['posttime']);
	} else {
		$during = "";
	}
	
	if (empty($qstrings)) $qwhere = '1';
	else $qwhere = implode(' AND ', $qstrings);	
	
	$posters = $sql->query("
		SELECT 	t.id, t.replies, t.title, t.forum, 
				f.title ftitle, COUNT(p.id) cnt, 
				pf.group{$loguser['group']} forumperm, pu.permset userperm
		FROM threads t 
		
		LEFT JOIN forums           f ON f.id = t.forum 
		LEFT JOIN posts            p ON t.id = p.thread 
		LEFT JOIN perm_forums     pf ON f.id = pf.id
		LEFT JOIN perm_forumusers pu ON f.id = pu.forum AND pu.user = {$loguser['id']}
		
		WHERE {$qwhere} AND (".has_perm('forum-admin')." OR !ISNULL(f.id)) 
		GROUP BY t.id 
		ORDER BY cnt DESC, t.firstpostdate DESC 
		LIMIT 1000
	");

	pageheader();

?>
	<span class="fonts">
		<a href='postsbythread.php?id=<?=$_GET['id']?>&posttime=3600'>During last hour</a> |
		<a href='postsbythread.php?id=<?=$_GET['id']?>&posttime=86400'>During last day</a> |
		<a href='postsbythread.php?id=<?=$_GET['id']?>&posttime=604800'>During last week</a> |
		<a href='postsbythread.php?id=<?=$_GET['id']?>&posttime=2592000'>During last 30 days</a>
		<?=((!$_GET['id']) ? "" : " | <a href='postsbythread.php?id={$_GET['id']}&posttime=0'>Total</a>")?>
		<br>
	</span>
	<span class="font"> Posts <?=$by?> in threads<?=$during?>:</span>
	<table class='table'>
		<tr>
			<td class='tdbgh center' width=30>&nbsp;</td>
			<td class='tdbgh center' width=300>Forum</td>
			<td class='tdbgh center'>Thread</td>
			<td class='tdbgh center' width=70>Posts</td>
			<td class='tdbgh center' width=90>Thread total</td>
		</tr>
<?php

	for ($i = 1; $t=$sql->fetch($posters); ++$i) {
		
		if (!has_forum_perm('read', $t)) {
			$forum  = '(restricted forum)';
			$thread = '(private thread)';
		} else {
			$forum  = "<a href='forum.php?id={$t['forum']}'>".htmlspecialchars($t['ftitle'])."</a>";
			$thread = "<a href='thread.php?id={$t['id']}'>".htmlspecialchars($t['title'])."</a>";
		}

		?>
		<tr>
			<td class='tdbg2 center'><?=$i?></td>
			<td class='tdbg2 center'><?=$forum?></td>
			<td class='tdbg1'><?=$thread?></td>
			<td class='tdbg1 center' style='font-weight:bold;'><b><?=$t['cnt']?></td>
			<td class='tdbg1 center'><?=($t['replies']+1)?></td>
		</tr>
		<?php
	}
?>	</table>
<?php

	pagefooter();
