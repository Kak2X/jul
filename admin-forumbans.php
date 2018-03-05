<?php

require 'lib/function.php';

$_GET['forum'] 		= filter_int($_GET['forum']);
$_GET['edit'] 		= filter_int($_GET['edit']);


if ($_GET['forum']) {
	
	$forum = $sql->fetchq("SELECT id, title FROM forums WHERE id = {$_GET['forum']}");
	if (!$forum) {
		errorpage("This forum doesn't exist.");
	}
	$ismod = $ismod || $sql->resultq("SELECT 1 FROM forummods WHERE forum = {$_GET['forum']} and user = {$loguser['id']}");
	#if (!$ismod) {
	#	errorpage("You aren't allowed to edit this forum's bans.");
	#}
	
	$forumbans = $sql->query("
		SELECT f.*, ".set_userfields('u1')." uid, ".set_userfields('u2')." uid
		FROM forumbans f
		LEFT JOIN users u1 ON f.user   = u1.id
		LEFT JOIN users u2 ON f.banner = u2.id
		WHERE f.forum = {$_GET['forum']}
		ORDER BY date ASC
	");
	
	// Ban list
	$txt = "";
	$i   = 1;
	for ($i = 0; $ban = $sql->fetch($forumbans, PDO::FETCH_NAMED); ++$i) {
		if ($_GET['edit'] == $ban['id']) $sel = $ban['id'];
		
		$bg = ($i % 2) + 1;
		if ($ismod) {
			$editlink = "<a href='?forum={$_GET['forum']}&edit={$ban['id']}'>Edit</a>";
		} else {
			$editlink = ($i+1);
		}
		
		$txt .= "
		<tr>
			<td class='tdbg{$bg} center fonts' style='width: 60px'>{$editlink}</td>
			<td class='tdbg{$bg} center'>".($ban['user'] ? getuserlink(array_column_by_key($ban, 0), $ban['user']) : "Autoban")."</td>
			<td class='tdbg{$bg} center'>".printdate($ban['date'])."</td>
			<td class='tdbg{$bg} center'>".getuserlink(array_column_by_key($ban, 1), $ban['banner'])."</td>
			<td class='tdbg{$bg} center'>".($ban['reason'] ? $ban['reason'] : "&mdash;")."</td>
			<td class='tdbg{$bg} center'>".($ban['expire'] ? timeunits2($ban['expire'] - ctime()) : "Permanent" )."</td>
		</tr>";
	}
	
	$addlink = "";
	if ($ismod) {
		$addlink = "<tr><td class='tdbgc center' colspan=6><a href='?forum={$_GET['forum']}&edit=-1'>&lt;&lt; Add a new ban &gt;&gt;</td></tr>";
	}
	
	pageheader("Editing forum bans");
	print adminlinkbar();
	
?>
	<table class="table">
		<tr><td class="tdbgh center b" colspan=6>Forum bans for <a href="forum.php?id=<?=$forum['id']?>?>"><?= htmlspecialchars($forum['title']) ?></a> (Total: <?= $i ?>)</td></tr>
		<tr>
			<td class="tdbgc center">#</td>
			<td class="tdbgc center">User</td>
			<td class="tdbgc center">Date</td>
			<td class="tdbgc center">Banned by</td>
			<td class="tdbgc center">Reason</td>
			<td class="tdbgc center">Ban duration</td>
		</tr>
		<?= $txt ?>
		<?= $addlink ?>
	</table>
<?php
	
	
	
} else {
	
	// Only mod or up can view the forum list
	if (!$ismod) {
		errorpage("Sorry, but you're not a global moderator.");
	}
	
	errorpage("Forum list not implemented.");
	
}

pagefooter();