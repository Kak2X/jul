<?php

	require 'lib/function.php';

	if (!$loguser['id']) {
		errorpage("You need to be logged in to block layouts.", 'login.php', 'log in (then try again)');
	}

	$_GET['id']     = filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);

	if ($_GET['action'] && (!$_GET['id'] || !valid_user($_GET['id']))) {
		errorpage("This user doesn't exist!");
	}

	pageheader("Blocked layouts");

	if ($_GET['action'] == 'block') {
		check_token($_GET['auth'], TOKEN_MGET);
		$layoutblocked = $sql->resultq("SELECT COUNT(*) FROM blockedlayouts WHERE user = {$loguser['id']} AND blocked = {$_GET['id']}");
		if ($layoutblocked) {
			$sql->query("DELETE FROM blockedlayouts WHERE user = {$loguser['id']} AND blocked = {$_GET['id']}");
			$verb = "unblocking";
		} else {
			$sql->query("INSERT INTO blockedlayouts (user, blocked) VALUES ({$loguser['id']}, {$_GET['id']})");
			$verb = "blocking";
		}
		
		errorpage("Thank you, {$loguser['name']}, for {$verb} a post layout.", filter_string($_SERVER['HTTP_REFERER']), 'the previous page', 0);
	} else if ($_GET['action'] == 'nuke' && $isadmin) {
		$message = "Are you sure you want to nuke this layout?";
		$form_link = "?action=nuke&id={$_GET['id']}";
		$buttons   = array(
			0 => ["NUKE IT"],
			1 => ["Cancel", "profile.php?id={$_GET['id']}"]
		);
		if (confirmpage($message, $form_link, $buttons)) {
			check_token($_POST['auth']);
			$text = addslashes("<hr>[<span class='font b' style='font-size: 10pt; color: f00'>ATTENTION</span>: Your layout has been removed for being terrible.]");
			//$text = addslashes("<hr><font face=\"Verdana\" style=font-size:10pt color=ff0000><b>[ATTENTION IDIOT: YOUR LAYOUT HAS BEEN NUKED FOR BEING <font color=ff8080>EXTREMELY BAD</font>. PLEASE READ THE <a href='announcement.php'>ANNOUNCEMENTS</a> BEFORE CREATING ANOTHER ATROCITY, OR YOU <i><font color=ff8080>WILL BE BANNED</font></i>.]</b></font>");
			$sql->query("UPDATE `users` SET `signature` = '{$text}', `bio` = '{$text}', `postheader` = '', `css` = '' WHERE `id` = '{$_GET['id']}'");
			errorpage("Bio, header, and signature fields nuked!", "profile.php?id={$_GET['id']}", 'the user\'s profile page', 0);
		}
	} else if ($_GET['action'] == 'view' && $isadmin) {
		$user = load_user($_GET['id']);
		$thisuser = getuserlink($user);
		
		$bylist = $blockedlist = "";
		// Layouts blocked by this user
		$blo = $sql->query("
			SELECT $userfields
			FROM blockedlayouts b
			INNER JOIN users u ON b.blocked = u.id
			WHERE b.user = {$_GET['id']}
			ORDER BY u.name ASC
		");
		if (!$sql->num_rows($blo)) {
			$blockedlist = "None.";
		} else while ($blocked = $sql->fetch($blo)) {
			$blockedlist .= getuserlink($blocked)."<br/>";
		}
		
		// Users blocking the layout
		$blby = $sql->query("
			SELECT $userfields
			FROM blockedlayouts b
			INNER JOIN users u ON b.user = u.id
			WHERE b.blocked = {$_GET['id']}
			ORDER BY u.name ASC
		");
		if (!$sql->num_rows($blby)) {
			$bylist = "None.";
		} else while ($by = $sql->fetch($blby)) {
			$bylist .= getuserlink($by)."<br/>";
		}
		
?>
	<table class="table">
		<tr>
			<td class='tdbgh center b'><?= $thisuser ?> blocked layouts by:</td>
			<td class='tdbgh center b'>Blocked <?= $thisuser ?>'s layouts:</td>
		<tr class="vatop">
			<td class='tdbg1'><?=$blockedlist?></td>
			<td class='tdbg1'><?=$bylist?></td>
		</tr>
	</table>
<?php

	} else {
		$blo = $sql->query("
			SELECT $userfields
			FROM blockedlayouts b
			INNER JOIN users u ON b.blocked = u.id
			WHERE b.user = {$loguser['id']}
			ORDER BY u.name ASC
		");
		$tokenstr    = "&auth=".generate_token(TOKEN_MGET);
		$blockedlist = "";
		if (!$sql->num_rows($blo)) {
			$blockedlist = "<tr><td class='tdbg1 center'>You currently have no layouts blocked. To block a user's layout, go to their profile and click 'Block layout' at the bottom.</td></tr>";
		} else for ($i = 0; $blocked = $sql->fetch($blo); ++$i) {
			$cell = ($i % 2)+1;
			$blockedlist .= "<tr><td class='tdbg{$cell} center'>".getuserlink($blocked)."</td><td class='tdbg{$cell} center' style='width: 100px'><a href='?action=block&id={$blocked['id']}{$tokenstr}'>Unblock</a></td></tr>";
		}
?>
	<center>
	<table class='table' style="width: 850px">
		<tr><td class='tdbgh center b' colspan=2>Blocked layouts</td>
		<?= $blockedlist ?>
	</table>
	</center>
<?php
	}

	pagefooter();