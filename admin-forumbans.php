<?php

require 'lib/function.php';

$_GET['forum'] 		= filter_int($_GET['forum']);
$_GET['edit'] 		= filter_int($_GET['edit']);

// Process edit actions right away
if ($_GET['forum']) {
	
	// Make sure the forum is valid (and that we have access to it)
	$forum = $sql->fetchq("SELECT id, title, minpower FROM forums WHERE id = {$_GET['forum']}");
	if (!$forum || ($forum['minpower'] && $loguser['powerlevel'] < $forum['minpower'])) {
		errorpage("Couldn't access the forum. Either it doesn't exist or you don't have access to it.", 'index.php', 'the index page', 0);
	}
	$ismod = $ismod || $sql->resultq("SELECT 1 FROM forummods WHERE forum = {$_GET['forum']} and user = {$loguser['id']}");

	
	if (isset($_POST['dodel'])) {
		// Delete (multiple) bans
		check_token($_POST['auth']);
		if (!$ismod) {
			errorpage("You aren't allowed to edit this forum's bans.");
		}
		
		// Make sure we're deleting forum bans off the correct forum
		if (!empty($_POST['delban'])){
			$del = $sql->prepare("DELETE FROM forumbans WHERE id = ? AND forum = {$_GET['forum']}");
			foreach ($_POST['delban'] as $ban) {
				$sql->execute($del, [$ban]);
			}
		}
		return header("Location: ?forum={$_GET['forum']}");
		
	} else if (isset($_POST['save'])) {
		// Create / Edit a ban
		check_token($_POST['auth']);
		if (!$ismod) {
			errorpage("You aren't allowed to edit this forum's bans.");
		}
		
		if ($_GET['edit'] != -1) {
			$data = $sql->fetchq("SELECT * FROM forumbans WHERE id = {$_GET['edit']}");
			if (!$data) {
				errorpage("You're trying to edit a nonexisting ban or the selected ban has already expired.");
			}
		}
		
		$_POST['user']      = filter_int($_POST['user']);
		$_POST['expire']    = filter_int($_POST['expire']);
		$_POST['reason']    = filter_string($_POST['reason']);
		
		$user = $sql->fetchq("SELECT name, powerlevel FROM users WHERE id = {$_POST['user']}");
		if (!$user) {
			errorpage("This user doesn't exist.");
		} else if ($user['powerlevel'] >= 2) {
			errorpage("uh no");
		}
		
		// All OK!
		if ($_GET['edit'] == -1) {
			$ircreason  = $_POST['reason'] ? " for the following reason: {$_POST['reason']}" : "";
			$ircmessage = xk(8) . $loguser['name'] . xk(7) ." added forum ban for ". xk(8) . $user['name'] . xk(7) ." in ". xk(8) . $forum['title'] . xk(7) . $ircreason .".";;
			forumban($_GET['forum'], $_POST['user'], $_POST['reason'], $ircmessage, IRC_STAFF, $_POST['expire'], $loguser['id']);
		} else {
			$values = array(
				'user'   => $_POST['user'],
				'expire' => ctime() + 3600 * $_POST['expire'],
				'reason' => $_POST['reason'],
			);
			$sql->queryp("UPDATE forumbans SET ".mysql::setplaceholders($values)." WHERE id = {$_GET['edit']}", $values);
			xk_ircsend(IRC_STAFF."|". xk(8) . $loguser['name'] . xk(7) ." updated the forum ban for ". xk(8) . $user['name'] . xk(7) ." in ". xk(8) . $forum['title'] . xk(7) .".");
		}
		return header("Location: ?forum={$_GET['forum']}");
	}
} else {
	// Stop non global-mods immediately from using the forum list feature
	if (!$isfullmod) {
		errorpage("No forum selected.");
	}

}



// Topmost select box to change the forum bans we're editing
if ($isfullmod) {
	$forumlist = "
	<form method='GET'>
	<div class='font center'>
		Forum list: ".doforumlist($_GET['forum'], $name = 'forum', $shownone = '--- Select a forum ---')."
		<input type='submit' class='submit' value='Go'>
	</div>
	</form>";
} else {
	$forumlist = "";
}





if (!$_GET['forum']) {
	pageheader("Forum bans");
	print adminlinkbar().$forumlist;
	errorpage("Select a forum from the list above to edit its banned users.");
} else {

	if ($ismod) {
		$windowtitle = "Editing forum bans";
		// Deletion / addition actions at the bottom of the table
		$addlink = "
		<tr>
			<td class='tdbgc'>
				<input type='submit' class='submit' style='padding: 0px; font-size: 10px' name='dodel' value='Delete selected'>
				".auth_tag()."
			</td>
			<td class='tdbgc center' colspan=5>
				<a href='?forum={$_GET['forum']}&edit=-1'>&lt;&lt; Add a new ban &gt;&gt;</a>
			</td>
		</tr>";
	} else {
		$windowtitle = "Forum bans";
		$addlink = $forumlist = "";
	}
	
	pageheader("{$windowtitle} - {$forum['title']}");
	print adminlinkbar().$forumlist;
	
	//--
	$_POST['searchreason']      = filter_string($_POST['searchreason']);
	$_POST['searchuser']        = filter_int($_POST['searchuser']);
	$_POST['page']              = filter_int($_POST['page']);

	$formlink = "?forum={$_GET['forum']}";
	if (!isset($_GET['ppp'])) {
		$ppp = 100;
	} else {
		$ppp = max(min(((int) $_GET['ppp']), 500), 1);
		$formlink .= "&ppp={$ppp}";
	}
	
	
	// Query values
	$outres = array();
	$qsearch = "";
	if ($_POST['searchreason']) {
		$outres['reason'] = "%{$_POST['searchreason']}%";
		$qsearch .= " AND f.reason LIKE :reason";
	}
	if ($_POST['searchuser']) {
		//$userid   = $sql->resultp("SELECT id FROM users WHERE name = ?", [$_POST['searchuser']]);
		$qsearch .= " AND f.user = {$_POST['searchuser']}";
	}
	//--
	
	// Ban list
	$total  = $sql->resultp("SELECT COUNT(*) FROM forumbans f WHERE f.forum = {$_GET['forum']} {$qsearch}", $outres);
	$_POST['page'] = numrange($_POST['page'], 0, ceil($total / $ppp) - 1); // Restrict to real values
	
	$forumbans = $sql->queryp("
		SELECT f.*, ".set_userfields('u1').", ".set_userfields('u2')."
		FROM forumbans f
		LEFT JOIN users u1 ON f.user   = u1.id
		LEFT JOIN users u2 ON f.banner = u2.id
		WHERE f.forum = {$_GET['forum']} {$qsearch}
		ORDER BY f.date DESC
		LIMIT ".($_POST['page'] * $ppp).",$ppp
	", $outres);
	
	$txt     = "";
	$ban     = array();
	for ($i = 0; $x = $sql->fetch($forumbans); ++$i) {
		$bg = ($i % 2) + 1;
		
		// Confirm the ban we're viewing
		if ($_GET['edit'] == $x['id']) {
			$ban = $x;
		}
		
		// Don't show edit/delete links to non mods
		if ($ismod) {
			$editlink = "<input type='checkbox' name='delban[]' value='{$x['id']}'> - <a href='?forum={$_GET['forum']}&edit={$x['id']}'>Edit</a>";
		} else {
			$editlink = ($i+1);
		}
		
		$txt .= "
		<tr>
			<td class='tdbg{$bg} center fonts' style='width: 60px'>{$editlink}</td>
			<td class='tdbg{$bg} center'>".getuserlink(get_userfields($x, 'u1'), $x['user'])."</td>
			<td class='tdbg{$bg} center'>".($x['reason'] ? $x['reason'] : "&mdash;")."</td>
			<td class='tdbg{$bg} center'>".($x['banner'] ? getuserlink(get_userfields($x, 'u2'), $x['banner']) : "Autoban")."</td>
			<td class='tdbg{$bg} center'>".printdate($x['date'])."</td>
			<td class='tdbg{$bg} center'>".($x['expire'] ? timeunits2($x['expire'] - ctime()) : "Permanent" )."</td>
		</tr>";
	}
	
?>
	<center>
	<form method="POST" action="<?=$formlink?>">
	<table class="table" style="max-width: 800px">
		<tr>
			<td class='tdbgh' style='width: 130px'>&nbsp;</td>
			<td class='tdbgh'>&nbsp;</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>
				Search user:
			</td>
			<td class='tdbg2'>
				<?= user_select('searchuser', $_POST['searchuser'], 'powerlevel < 2') ?>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>
				Search reason:
			</td>
			<td class='tdbg2'>
				<input type='text' name='searchreason' size=72 value="<?= htmlspecialchars($_POST['searchreason']) ?>">
			</td>
		</tr>
		<tr id="pagetr">
			<td class='tdbg1 center b'>
				Display page:
			</td>
			<td class='tdbg2'>
				<?= page_select($total, $ppp) ?>
			</td>
		</tr>
		<tr>
			<td class='tdbg2'></td>
			<td class='tdbg2'>
				<input type='submit' class='submit' name='dosearch' value='Search'>
			</td>
		</tr>
	</table>
	</center>
	</form>
	
	<br>	
	
	<form method="POST" action="<?=$formlink?>&edit=<?=$_GET['edit']?>">
	<table class="table">
		<tr><td class="tdbgh center b" colspan=6>Forum bans for <a href="forum.php?id=<?=$forum['id']?>?>"><?= htmlspecialchars($forum['title']) ?></a> (Total: <?= $i ?>)</td></tr>
		<tr>
			<td class="tdbgc center" style="width: 10px">#</td>
			<td class="tdbgc center" style="width: 15%">User</td>
			<td class="tdbgc center">Reason</td>
			<td class="tdbgc center" style="width: 15%">Banned by</td>
			<td class="tdbgc center" style="width: 200px">Date</td>
			<td class="tdbgc center" style="width: 300px">Ban duration</td>
		</tr>
		<?= $txt ?>
		<?= $addlink ?>
	</table>
	</form>
<?php

	// Add / edit a forum ban
	if ($ismod && ($ban || $_GET['edit'] == -1)) {
		
		if ($_GET['edit'] == -1) {
			$ban = array(
				'user' => 0,
				'expire' => 0,
				'reason' => '',
			);
			$title = "Add a new ban";
		} else {
			$title = "Edit ban";
		}
		
?>
	<br>
	<center>
	<form method="POST" action="<?=$formlink?>>&edit=<?=$_GET['edit']?>">
	<table class="table" style="max-width: 600px">
		<tr><td class="tdbgh center b" colspan=2><?= $title ?></tr></td>
		<tr>
			<td class="tdbg1 center b">User</td>
			<td class="tdbg2">
				<?= user_select('user', $ban['user'], 'powerlevel < 2') ?>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Ban duration</td>
			<td class="tdbg2">
				<?= ban_hours('expire', $ban['expire']) ?>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Reason</td>
			<td class="tdbg2">
				<input type="text" name="reason" value="<?= htmlspecialchars($ban['reason']) ?>" maxlength=127 style="width: 450px">
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">&nbsp;</td>
			<td class="tdbg2">
				<input type="submit" class="submit" name="save" value="Save settings">
				<?= auth_tag() ?>
			</td>
		</tr>
		
		
	</table>
	</form>
	</center>
<?php
	}
}

pagefooter();