<?php
	
	require 'lib/function.php';
	
	$meta['noindex'] = true;
	
	$_GET['id']         = filter_int($_GET['id']);
	$_GET['action']     = filter_string($_GET['action']);
	$_POST['action']    = filter_string($_POST['action']);

	
	if (!$loguser['id']) {
		errorpage("You are not logged in.",'login.php', 'log in (then try again)');
	}
	if ((!$isadmin && !$config['allow-pmthread-edit'] && $_GET['action'] != 'movethread') || $loguser['editing_locked'] == 1) {
		errorpage("You are not allowed to edit your threads.", "showprivate.php?id={$_GET['id']}", 'return to the conversation');
	}
	
	load_pm_thread($_GET['id']);
	
	// If thread deletion is enabled and we're sysadmins, bring up confirmation box to trigger post deletion.
	// We simply redirect to the deletethread action.
	if (isset($thread['error'])) {
		if (!$config['allow-thread-deletion'] || !$sysadmin) {
			errorpage("You can not edit broken PM threads.", "showprivate.php?id={$_GET['id']}", 'the thread');
		}
		$message   = "It's impossible to edit a broken PM thread.<br>You have to delete the invalid posts, or merge them to another thread.";
		$form_link = "?id={$_GET['id']}";
		$buttons   = array(
			0 => ["Delete all posts"],
			1 => ["Cancel", "showprivate.php?id={$_GET['id']}"]
		);
		if (confirmpage($message, $form_link, $buttons)) {
			$_POST['deletethread'] = true;
		}
	} else if (!$isadmin && $thread['user'] != $loguser['id']) {
		errorpage("You are not allowed to do this for this conversation.", "private.php", 'your private message box');
	}
	
	if ($sysadmin && filter_bool($_POST['deletethread']) && $config['allow-thread-deletion']) {
		$message = "
			<big><b>DANGER ZONE</b></big><br>
			<br>
			Are you sure you want to permanently <b>delete</b> this thread and <b>all of its posts</b>?<br>
			This will remove the conversation from the inbox of all partecipants<br>
			<br><input type='hidden' name='deletethread' value=1>
			<input type='checkbox' class='radio' name='reallysure' id='reallysure' value=1> <label for='reallysure'>I'm sure</label>
		";
		$form_link     = "?id={$_GET['id']}";
		$buttons       = array(
			0 => ["Delete thread"],
			1 => ["Cancel", "showprivate.php?id={$_GET['id']}"]
		);
		
		if (confirmpage($message, $form_link, $buttons, TOKEN_SLAMMER)) {	
			// Double-confirm the checkbox 
			if (!filter_bool($_POST['reallysure'])) {
				errorpage("You haven't confirmed the choice.", "showprivate.php?id={$_GET['id']}", 'the thread');
			}
			$sql->beginTransaction();
			$attachids = get_saved_attachments_ids($sql->getresults("SELECT id FROM pm_posts WHERE thread = {$_GET['id']}"), 'pm');
			//errorpage(print_r($attachids, true)." attachments oy");
			$sql->query("DELETE FROM pm_posts WHERE thread = {$_GET['id']}");	
			$sql->query("DELETE FROM pm_threads WHERE id = {$_GET['id']}");
			$sql->query("DELETE FROM pm_access WHERE thread = {$_GET['id']}");
			$sql->query("DELETE FROM pm_threadsread WHERE tid = {$_GET['id']}");	
			$sql->commit();
			if ($attachids) {
				remove_attachments($attachids);
			}
			errorpage("Thank you, {$loguser['name']}, for deleting the thread.", "private.php", "your private message box");
			
		}
	}
	else if ($_GET['action'] == 'movethread') {
		if (!$access) {
			errorpage("You don't have access to the thread, so you can't move it to the other folders.", "showprivate.php?id={$_GET['id']}", 'the thread');
		}
		$message = "
			Where do you want to move this thread?<br>
			<br>
			New folder: ".pm_folder_select('folder', $loguser['id'], $access['folder'])."
		";
		$form_link     = "?id={$_GET['id']}&action=movethread";
		$buttons       = array(
			0 => ["Move thread"],
			1 => ["Cancel", "showprivate.php?id={$_GET['id']}"]
		);
		
		if (confirmpage($message, $form_link, $buttons, TOKEN_SLAMMER)) {	
			// Double-confirm the checkbox 
			$_POST['folder'] = filter_int($_POST['folder']);
			if (valid_pm_folder($_POST['folder'], $loguser['id'])) {
				$sql->query("UPDATE pm_access SET folder = {$_POST['folder']} WHERE thread = {$_GET['id']} AND user = {$loguser['id']}");
			} else {
				errorpage("Invalid folder selected.");
			}
			errorpage("Thank you, {$loguser['name']}, for moving the thread.", "private.php", "your private message box");
		}
	}
	else if ($isadmin && substr($_GET['action'], 0, 1) == 'q') { // Quickmod
		check_token($_GET['auth'], TOKEN_MGET);
		switch ($_GET['action']) {
			//case 'qstick':   $update = 'sticky=1'; break;
			//case 'qunstick': $update = 'sticky=0'; break;
			case 'qclose':   $update = 'closed=1'; break;
			case 'qunclose': $update = 'closed=0'; break;
			default: return header("Location: showprivate.php?id={$_GET['id']}");
		}
		$sql->query("UPDATE pm_threads SET {$update} WHERE id={$_GET['id']}");
		return header("Location: showprivate.php?id={$_GET['id']}");
	}
	else if ($isadmin && $_GET['action'] == 'trashthread') {
		if (!$access) {
			errorpage("You don't have access to the thread, so you can't move it to the trash folder.", "showprivate.php?id={$_GET['id']}", 'the thread');
		}
		
		$message       = "Are you sure you want to trash this thread?";
		$form_link     = "?action=trashthread&id={$_GET['id']}";
		$buttons       = array(
			0 => ["Trash Thread"],
			1 => ["Cancel", "showprivate.php?id={$_GET['id']}"]
		);
		if (confirmpage($message, $form_link, $buttons, TOKEN_SLAMMER)) {		
			$sql->query("UPDATE pm_access SET folder = '".PMFOLDER_TRASH."' WHERE thread = '{$_GET['id']}' AND user = {$loguser['id']}");
			errorpage("Thread successfully trashed.","showprivate.php?id={$_GET['id']}",'return to the thread');
		}
	}
	else {
		
		// We are increasing the limit since admins can edit the ACL
		// This is because the thread owner (which usually is $loguser['id'] if we're not admins) is normally omitted from the list
		// If we're admins (which can peek on otherwise restricted threads) this can be no longer true, so admins get the full list without omissions.
		if ($isadmin) {
			$config['pmthread-dest-limit']++;
		}
		
		if (isset($_POST['submit'])) {
			check_token($_POST['auth']);
			
			$_POST['subject']       = filter_string($_POST['subject']);
			$_POST['custposticon']  = filter_string($_POST['custposticon']);
			$_POST['iconid']        = filter_int($_POST['iconid']);
			$_POST['closed']        = filter_int($_POST['closed']);
			$_POST['users']         = filter_string($_POST['users']);
			$userlist  = array_filter(explode(';', $_POST['users']), 'trim');
			
			if (!$_POST['subject']) {
				errorpage("Couldn't edit the thread. You haven't entered a subject.");
			}
			$posticons 		= file('posticons.dat');
			if ($_POST['custposticon']) {
				$icon = xssfilters($_POST['custposticon']);
			} else if (isset($posticons[$_POST['iconid']])) {
				$icon = trim($posticons[$_POST['iconid']]);
			} else {
				$icon = "";
			}
			
			//-- User validation --
			$destid = valid_pm_acl($userlist, $isadmin, $error);
			if ($error) {
				errorpage("The partecipants list cannot be processed.<br>{$error}");
			}
			
			$sql->beginTransaction();
			$data = [
				'title'        => htmlspecialchars($_POST['subject']),
				'description'  => xssfilters(filter_string($_POST['description'])),
				'icon'         => $icon,
				'closed'       => $_POST['closed'],
			];
			$sql->queryp("UPDATE pm_threads SET ".mysql::setplaceholders($data)." WHERE id = {$_GET['id']}", $data);
			set_pm_acl($destid, $_GET['id'], $isadmin, PMFOLDER_MAIN);
			$sql->commit();
			errorpage("Thank you, {$loguser['name']}, for editing the thread.","showprivate.php?id={$_GET['id']}",'return to the thread');
		}
		
		$check1[$thread['closed']]='checked=1';
		if ($sysadmin && $config['allow-thread-deletion']) {
			$delthread = " <input type='checkbox' class='radio' name='deletethread' value=1> Delete thread";
		} else
			$delthread = "";
		
		//--
		$accesslist = $sql->getresults("
			SELECT u.name 
			FROM pm_access a
			INNER JOIN users u ON a.user = u.id
			WHERE a.thread = {$_GET['id']}".($isadmin ? "" : " AND a.user != {$loguser['id']}")."
		");
		//--
		pageheader();
		
		$links = array(
			"Private messages" => "private.php",
			$thread['title']   => "showprivate.php?id={$_GET['id']}",
			"Edit thread"      => NULL,
		);
		$barlinks = dobreadcrumbs($links); 
		$other_p  = $isadmin ? "P" : "Other p";
		
		?>
		<?= $barlinks ?>
		<form method="POST" action='?id=<?=$_GET['id']?>'>
		<table class='table'>
			<tr>
				<td class='tdbgh' style="width: 150px">&nbsp;</td>
				<td class='tdbgh'>&nbsp;</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Thread title:</td>
				<td class='tdbg2'>
					<input type='text' name=subject VALUE="<?=htmlspecialchars($thread['title'])?>" SIZE=40 MAXLENGTH=100>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Thread description:</td>
				<td class='tdbg2'>
					<input type='text' name=description VALUE="<?=htmlspecialchars($thread['description'])?>" SIZE=100 MAXLENGTH=120>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'><?=$other_p?>artecipants:</td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=users SIZE=60 MAXLENGTH=100 VALUE="<?=implode('; ', $accesslist)?>">
					<span class='fonts'>Max <?= $config['pmthread-dest-limit'] ?> users allowed. Multiple users separated with a semicolon.</span>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Thread icon:</td>
				<td class='tdbg2'><?= dothreadiconlist(NULL, $thread['icon']) ?></td>
			</tr>
<?php if ($isadmin) { ?>
			<tr>
				<td class='tdbg1 center'>&nbsp;</td>
				<td class='tdbg2'>
					<input type=radio class='radio' name=closed value=0 <?=filter_string($check1[0])?>> Open&nbsp; &nbsp;
					<input type=radio class='radio' name=closed value=1 <?=filter_string($check1[1])?>> Closed&nbsp; &nbsp;
					<?= $delthread ?>
				</td>
			</tr>
<?php } ?>
			<tr>
				<td class='tdbg1'>&nbsp;</td>
				<td class='tdbg2'>
					<input type='hidden' name='action' value='editthread'>
					<?= auth_tag() ?>
					<input type='submit' name='submit' value="Edit thread">
				</td>
			</tr>
		</table>
		</form>
		<?= $barlinks ?>
		<?php
	}
	
	pagefooter();
	