<?php
	const _NUKE_DEFAULT_TITLE         = "Layout removed";
	const _NUKE_DEFAULT_MESSAGE       = "Sorry, but your layout has been removed for being egregiously bad or unreadable.";
	const _NUKE_DEFAULT_TITLE_COLOR   = "#FF0000";
	// uncomment for original text from 1.92.999... not like there's any reason to
	//const _NUKE_DEFAULT_TITLE         = "ATTENTION IDIOT";
	//const _NUKE_DEFAULT_MESSAGE       = "YOUR LAYOUT HAS BEEN NUKED FOR BEING <font color=ff8080>EXTREMELY BAD</font>. PLEASE READ THE <a href='announcement.php'>ANNOUNCEMENTS</a> BEFORE CREATING ANOTHER ATROCITY, OR YOU <i><font color=ff8080>WILL BE BANNED</font></i>.";
	
	
	require "lib/common.php";

	if (!$loguser['id']) {
		errorpage("You need to be logged in to block layouts.", 'login.php', 'log in (then try again)');
	}

	$_GET['id']     = filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);

	if ($_GET['action'] && (!$_GET['id'] || !($user = load_user($_GET['id'])))) {
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
		
		errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for {$verb} a post layout.", filter_string($_SERVER['HTTP_REFERER']), 'the previous page', 0);
	} else if ($_GET['action'] == 'nuke' && $isadmin) {
		if (confirmed($msgkey = 'del-layout')) {
			$_POST['title']   = filter_string($_POST['title']);
			$_POST['message'] = filter_string($_POST['message']);
			$_POST['color']   = filter_string($_POST['color']);
			$_POST['nukebio'] = filter_bool($_POST['nukebio']);
			
			$values = [
				"<hr>"
				.($_POST['title'] ? "[<span class='font b' style='font-size: 10pt; color: {$_POST['color']}'>{$_POST['title']}</span>]<br/>" : "")
				.($_POST['message'] ? $_POST['message'] : _NUKE_DEFAULT_MESSAGE)
			];
			
			// Optional bio nuking
			if ($_POST['nukebio']) {
				$values[1] = $values[0]; // we cannot reuse the same parameter
				$rmbioq    = " `bio` = ?,";
				$txt       = "Bio, header, and signature fields nuked!";
			} else {
				$rmbioq    = "";
				$txt       = "Header and signature fields nuked!";
			}
			
			$sql->queryp("UPDATE `users` SET `signature` = ?,{$rmbioq} `postheader` = '', `css` = '' WHERE `id` = '{$_GET['id']}'", $values);
			errorpage($txt, "profile.php?id={$_GET['id']}", 'the user\'s profile page', 0);
		}
		$title   = "Nuke Layout";
		$message = "
		This will replace ".getuserlink($user)."'s signature and (optionally) the bio field with the text customizable below.<br/>
		The CSS and post header fields will also be erased.<br/>
		You should only be doing this when dealing with particularly <i>bad layouts</i> by bad users.<br/>
		<br/>
		You can customize the message options, or leave the defaults in.<br/>
		<br/>
		<table class='table'>
			<tr>
				<td class='tdbgh center b' colspan='2'>Message options</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Title</td>
				<td class='tdbg2'><input type='text' name='title' style='width: 250px' value=\""._NUKE_DEFAULT_TITLE."\"> <input type='color' name='color' value=\""._NUKE_DEFAULT_TITLE_COLOR."\"></td>
			</tr>
			<tr>
				<td class='tdbg1 center b vatop'>Message</td>
				<td class='tdbg2'><textarea name='message' class='w' style='resize: vertical'>"._NUKE_DEFAULT_MESSAGE."</textarea></td>
			</tr>
			<tr>
				<td class='tdbg1 center b vatop'>Options</td>
				<td class='tdbg2'><label><input type='checkbox' name='nukebio' value='1'> Replace Bio</label></td>
			</tr>
		</table>
		<br/>
		If you are sure you want to continue, press 'NUKE IT'
		";
		$form_link = "?action=nuke&id={$_GET['id']}";
		$buttons   = array(
			[BTN_SUBMIT, "NUKE IT"],
			[BTN_URL   , "Cancel", "profile.php?id={$_GET['id']}"]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons);

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
			$blockedlist .= "<tr>
				<td class='tdbg{$cell} center' style='width:50px'>".($i+1)."</td>
				<td class='tdbg{$cell}'>".getuserlink($blocked)."</td>
				<td class='tdbg{$cell} center' style='width: 120px'><a href='postlayouts.php?id={$blocked['id']}'>View layout</a></td>
				<td class='tdbg{$cell} center' style='width: 90px'><a href='?action=block&id={$blocked['id']}{$tokenstr}'>Unblock</a></td>
			</tr>";
		}
?>
	<center>
	<table class='table' style="width: 850px">
		<tr><td class='tdbgh center b' colspan='4'>Blocked layouts</td>
		<?= $blockedlist ?>
	</table>
	</center>
<?php
	}

	pagefooter();