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

	
	
	// Create / Edit / Delete a filter
	if (isset($_POST['save'])) {
		
		if (!$ismod) {
			errorpage("You aren't allowed to edit this forum's bans.");
		}
		
		$_POST['user']      = filter_int($_POST['user']);
		$_POST['expire']    = filter_int($_POST['expire']);
		$_POST['reason']    = filter_string($_POST['user']);
		
		if ($_GET['edit'] != -1 && !$sql->resultq("SELECT 1 FROM forumbans WHERE id = {$_GET['id']}")) {
			errorpage("You're trying to edit a nonexisting ban or the selected ban has already expired.");
		}
		$username = $sql->resultq("SELECT name FROM users WHERE id = {$_POST['user']}");
		if (!$username) {
			errorpage("This user doesn't exist.");
		}
		// All OK!
		$ircreason  = $_POST['reason'] ? " for the following reason: {$_POST['reason']}" : "";
		$ircmessage = xk(8) . $loguser['name'] . xk(7) ." added forum ban for ". xk(8) . $username . xk(7) ." in ". xk(8) . $forum['name'] . xk(7) . $ircreason .".";;
		forumban($_POST['user'], $_GET['forum'], $_POST['reason'], $ircmessage, IRC_STAFF, $_POST['expire'], $loguser['id']);
	}
	
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
	$sel = array();
	for ($i = 0; $ban = $sql->fetch($forumbans, PDO::FETCH_NAMED); ++$i) {
		if ($_GET['edit'] == $ban['id']) $sel = $ban;
		
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
		$windowtitle = "Editing forum bans";
		$addlink = "<tr><td class='tdbgc center' colspan=6><a href='?forum={$_GET['forum']}&edit=-1'>&lt;&lt; Add a new ban &gt;&gt;</td></tr>";
	} else {
		$windowtitle = "Forum bans";
	}
	
	pageheader("{$config['board-name']} -- {$windowtitle}");
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

	// Edit window
	if ($ismod && ($sel || $_GET['edit'] == -1)) {
?>
	<br>
	<center>
	<form method="POST" action="?forum=<?=$_GET['forum']?>&edit=<?=$_GET['edit']?>">
	<table class="table" style="max-width: 600px">
		<tr><td class="tdbgh center b" colspan=2>Add a new ban (mockup)</tr></td>
		<tr>
			<td class="tdbg1 center b">User</td>
			<td class="tdbg2">
				<select name="user" autofocus>
					<option value=0 selected>--- Select an user ---</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Ban duration</td>
			<td class="tdbg2">
				<select name="user" autofocus>
					<option value='0'>*Permanent</option>
					<option value='1'>1 hour</option>
					<option value='3'>3 hours</option>
					<option value='6'>6 hours</option>
					<option value='24'>1 day</option>
					<option value='72'>3 days</option>
					<option value='168'>1 week</option>
					<option value='336'>2 weeks</option>
					<option value='744'>1 month</option>
					<option value='1488'>2 months</option>
				</select>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Reason</td>
			<td class="tdbg2">
				<input type="text" name="reason" value="<?= htmlspecialchars("") ?>" maxlength=127 style="width: 450px">
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">&nbsp;</td>
			<td class="tdbg2">
				<input type="submit" class="submit" name="save" value="Save settings">
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