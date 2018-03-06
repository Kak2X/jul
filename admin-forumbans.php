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
	
	$addlink = "";
	if ($ismod) {
		$windowtitle = "Editing forum bans";
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
	}
	
	pageheader("{$config['board-name']} -- {$windowtitle}");
	print adminlinkbar();
	
	// Ban list
	$forumbans = $sql->query("
		SELECT f.*, ".set_userfields('u1')." uid, ".set_userfields('u2')." uid
		FROM forumbans f
		LEFT JOIN users u1 ON f.user   = u1.id
		LEFT JOIN users u2 ON f.banner = u2.id
		WHERE f.forum = {$_GET['forum']}
		ORDER BY date ASC
	");
	
	$txt = "";
	$editban = array();
	for ($i = 0; $ban = $sql->fetch($forumbans, PDO::FETCH_NAMED); ++$i) {
		if ($_GET['edit'] == $ban['id']) {
			$editban = $ban;
		}
		$bg = ($i % 2) + 1;
		if ($ismod) {
			$editlink = "<input type='checkbox' name='delban[]' value='{$ban['id']}'> - <a href='?forum={$_GET['forum']}&edit={$ban['id']}'>Edit</a>";
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
	

	
?>
	<form method="POST" action="?forum=<?=$_GET['forum']?>&edit=<?=$_GET['edit']?>">
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
	</form>
<?php

	// Edit window
	if ($ismod && ($editban || $_GET['edit'] == -1)) {
		
		if ($_GET['edit'] == -1) {
			$editban = array(
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
	<form method="POST" action="?forum=<?=$_GET['forum']?>&edit=<?=$_GET['edit']?>">
	<table class="table" style="max-width: 600px">
		<tr><td class="tdbgh center b" colspan=2><?= $title ?></tr></td>
		<tr>
			<td class="tdbg1 center b">User</td>
			<td class="tdbg2">
				<?= user_select('user', $editban['user'], 'powerlevel < 2') ?>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Ban duration</td>
			<td class="tdbg2">
				<?= ban_hours('expire', $editban['expire']) ?>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Reason</td>
			<td class="tdbg2">
				<input type="text" name="reason" value="<?= htmlspecialchars($editban['reason']) ?>" maxlength=127 style="width: 450px">
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
	
	
	
} else {
	
	// Only mod or up can view the forum list
	if (!$ismod) {
		errorpage("Sorry, but you're not a global moderator.");
	}
	
	errorpage("Forum list not implemented.");
	
}

pagefooter();