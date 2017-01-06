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
	
	if (!$_GET['id'] && (!$_GET['posttime'] || $_GET['posttime'] > 2592000)) // All posts
		$_GET['posttime'] = 2592000;

	if ($_GET['posttime']) {
		$qstrings[] = "p.date > ".(ctime()-$_GET['posttime']);
		$during = ' during the last '.timeunits2($_GET['posttime']);
	} else {
		$during = "";
	}

	if (empty($qstrings)) $qwhere = '';
	else $qwhere = "WHERE ".implode(' AND ', $qstrings);

	$posters = $sql->query(
		"SELECT f.*, COUNT(p.id) AS cnt ".
		"FROM forums f ".
		"LEFT JOIN threads t ON f.id = t.forum ".
		"LEFT JOIN posts   p ON t.id = p.thread ".
		"{$qwhere} ".
		"GROUP BY f.id ".
		"ORDER BY cnt DESC");

	$userposts = $sql->resultq("SELECT COUNT(*) FROM posts p $qwhere");
	
	pageheader();

?>
	<span class="fonts">
		<a href='postsbyforum.php?id=<?=$_GET['id']?>&posttime=3600'>During last hour</a> |
		<a href='postsbyforum.php?id=<?=$_GET['id']?>&posttime=86400'>During last day</a> |
		<a href='postsbyforum.php?id=<?=$_GET['id']?>&posttime=604800'>During last week</a> |
		<a href='postsbyforum.php?id=<?=$_GET['id']?>&posttime=2592000'>During last 30 days</a>
		<?=((!$_GET['id']) ? "" : " | <a href='postsbyforum.php?id={$_GET['id']}&posttime=0'>Total</a>")?>
		<br>
	</span>
	<span class="font"> Posts <?=$by?> in forums<?=$during?>:</span>
	<table class='table'>
		<tr>
			<td class='tdbgh center' width=20>&nbsp;</td>
			<td class='tdbgh center' width=100>&nbsp;</td>
			<td class='tdbgh center'>Forum</td>
			<td class='tdbgh center' width=60>Posts</td>
			<td class='tdbgh center' width=80>Forum total</td>
		</tr>
<?php

	for ($i = 1; $f=$sql->fetch($posters); ++$i) {
		
		if ($f['minpower'] && $f['minpower'] > $loguser['powerlevel']) {
			$link="(restricted)";
			$viewall="(<s><b>view</b></s>)";
		} else {
			$link="<a href='forum.php?id=$f[id]'>$f[title]</a>";
			$timeid = ($_GET['posttime'] ? "&time={$_GET['posttime']}" : '');
			$viewall = ($_GET['id'] ? "(<a href='postsbyuser.php?id={$_GET['id']}&forum={$f['id']}{$timeid}'>View</a>)" : "");
		}

		?>
		<tr>
			<td class='tdbg2 center'><?=$i?></td>
			<td class='tdbg2 center'><?=$viewall?></td>
			<td class='tdbg1'><?=$link?></td>
			<td class='tdbg1 center'><b><?=$f['cnt']?></td>
			<td class='tdbg1 center'><?=$f['numposts']?></td>
		</tr>
		<?php
	}
?>	</table>
	<span class="font">Total: <?=$userposts?> posts</span>
<?php
	pagefooter();
?>