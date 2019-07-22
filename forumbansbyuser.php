<?php

	require "lib/function.php";

	$_GET['id'] = filter_int($_GET['id']);
	
	$user = $sql->fetchq("SELECT {$userfields} FROM users u WHERE u.id = {$_GET['id']}");
	if (!$user) {
		errorpage("This user doesn't exist!");
	}
	
	$forumbans = $sql->query("
		SELECT b.*, {$userfields} uid, f.title forumname, f.id validforum
		FROM forumbans b
		LEFT JOIN users  u ON b.banner = u.id
		LEFT JOIN forums f ON b.forum  = f.id
		WHERE b.user = {$_GET['id']} AND (!f.minpower OR f.minpower <= {$loguser['powerlevel']})
		ORDER BY b.date DESC
	");
	$modsets = $sql->getresults("SELECT forum FROM forummods WHERE user = {$loguser['id']}");
	
	pageheader("Forum bans to ".htmlspecialchars($user['name']));
		
	$txt     = "";
	for ($i = 0; $x = $sql->fetch($forumbans);) {
		
		if (!$x['validforum']) {
			if (!$isadmin) continue;
			$x['forumname'] = "(Invalid forum with ID #{$x['forum']})";
		}

		$bg = ($i++ % 2) + 1;
		
		// Don't show edit/delete links to non mods
		if ($ismod || isset($modsets[$x['forum']])) {
			$editlink = "<a href='admin-forumbans.php?forum={$x['forum']}&edit={$x['id']}'>Edit</a>";
		} else {
			$editlink = $i;
		}
		
		$txt .= "
		<tr>
			<td class='tdbg{$bg} center fonts' style='width: 60px'>{$editlink}</td>
			<td class='tdbg{$bg} center'><a href='forum.php?id={$x['forum']}'>{$x['forumname']}</a></td>
			<td class='tdbg{$bg} center'>".($x['reason'] ? $x['reason'] : "&mdash;")."</td>
			<td class='tdbg{$bg} center'>".getuserlink($x, $x['banner'])."</td>
			<td class='tdbg{$bg} center'>".printdate($x['date'])."</td>
			<td class='tdbg{$bg} center'>".($x['expire'] ? timeunits2($x['expire'] - ctime()) : "Permanent" )."</td>
		</tr>";
	}
	
?>
	<table class="table">
		<tr><td class="tdbgh center b" colspan=6>Forum bans for <?= getuserlink($user) ?> (Total: <?= $i ?>)</td></tr>
		<tr>
			<td class="tdbgc center" style="width: 10px">#</td>
			<td class="tdbgc center" style="width: 15%">Forum name</td>
			<td class="tdbgc center">Reason</td>
			<td class="tdbgc center" style="width: 15%">Banned by</td>
			<td class="tdbgc center" style="width: 200px">Date</td>
			<td class="tdbgc center" style="width: 300px">Ban duration</td>
		</tr>
		<?= $txt ?>
	</table>
<?php
	
	pagefooter();