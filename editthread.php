<?php
	require "lib/common.php";
	
	$meta['noindex'] = true;
		
	$_GET['id']         = filter_int($_GET['id']);
	$_GET['action']     = filter_string($_GET['action']);
	$_POST['action']    = filter_string($_POST['action']);

	load_thread($_GET['id']);
	check_forumban($forum['id'], $loguser['id']);
	$ismod = ismod($forum['id']);
	if ($forum_error) {
		$forum_error = "<table class='table'>{$forum_error}</table>";
	}

	if (!$loguser['id'])
		errorpage("You are not logged in.",'login.php', 'log in (then try again)');
	if ($loguser['editing_locked']) 
		errorpage("You are not allowed to edit your threads.", 'index.php', 'return to the board');
	if (!$ismod && ($loguser['id'] != $thread['user'] || $thread['closed']))
		errorpage("You are not allowed to edit this thread.", 'thread.php?id='.$_GET['id'], 'return to the thread');

	// Quickmod
	if ($ismod && substr($_GET['action'], 0, 1) == 'q') {
		check_token($_GET['auth'], TOKEN_MGET);
		
		// First attempt to let the extensions handle the actions
		$custom_actions_res = hook_use('thread-quickmod-act', $_GET['action']);
		if (!in_array(true, $custom_actions_res)) {
			// The extensions did nothing, so check the standard ones
			$update = "";
			switch ($_GET['action']) {
				case 'qstick':   $update = 'sticky=1'; break;
				case 'qunstick': $update = 'sticky=0'; break;
				case 'qclose':   $update = 'closed=1'; break;
				case 'qunclose': $update = 'closed=0'; break;
			}
			if ($update)
				$sql->query("UPDATE threads SET {$update} WHERE id={$_GET['id']}");
		}
		return header("Location: thread.php?id={$_GET['id']}");
	}
	else if ($ismod && $_GET['action'] == 'trashthread') {
		pageheader();
		
		if (confirmed($msgkey = 'trash', TOKEN_SLAMMER)) {		
			$sql->beginTransaction();
			move_thread($_GET['id'], $config['trash-forum'], $thread);
			$sql->commit();
			errorpage("Thread successfully trashed.","thread.php?id={$_GET['id']}",'return to the thread');
		}
		
		$title         = "Warning";
		$message       = "Are you sure you want to trash this thread?";
		$form_link     = "editthread.php?action=trashthread&id={$_GET['id']}";
		$buttons       = array(
			[BTN_SUBMIT, "Trash Thread"],
			[BTN_URL   , "Cancel", "thread.php?id={$_GET['id']}"]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons, TOKEN_SLAMMER);
	}
	else if ($sysadmin && filter_bool($_POST['deletethread']) && $config['allow-thread-deletion']) {
		pageheader();	

		if (confirmed($msgkey = 'erase-thread', TOKEN_SLAMMER)) {	
			// Double-confirm the checkbox 
			if (!filter_bool($_POST['reallysure'])) {
				errorpage("You haven't confirmed the choice.", "thread.php?id={$_GET['id']}", 'the thread');
			}
			
			$sql->beginTransaction();
			
			$sql->query("DELETE FROM threads WHERE id={$_GET['id']}");
			$sql->query("DELETE FROM posts_old WHERE pid IN (SELECT id FROM posts WHERE thread = {$_GET['id']})");
			$deleted = $sql->query("DELETE FROM posts WHERE thread = {$_GET['id']}");
			$numdeletedposts = $sql->num_rows($deleted);
			
			// Update forum status
			$t1 = $sql->fetchq("SELECT lastpostdate, lastposter	FROM threads WHERE forum = {$thread['forum']} ORDER BY lastpostdate DESC LIMIT 1");
			$sql->queryp("UPDATE forums SET numposts=numposts-$numdeletedposts,numthreads=numthreads-1,lastpostdate=?,lastpostuser=? WHERE id={$thread['forum']}", array((int) $t1['lastpostdate'], (int) $t1['lastposter']));
			
			if ($config['allow-attachments']) {
				$attachids = get_thread_attachments($_GET['id']);
				if ($attachids) {
					remove_attachments($attachids);
				}
			}
			$sql->commit();
			$fname = $sql->resultq("SELECT title FROM forums WHERE id = {$thread['forum']}");			
			errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for deleting the thread.", "forum.php?id={$thread['forum']}", $fname);
			
		}
		
		$title   = "<big>DANGER ZONE</big>";
		$message = "
			Are you sure you want to permanently <b>delete</b> this thread and <b>all of its posts</b>?<br>
			<br>
			<label><input type='checkbox' name='reallysure' value='1'> I'm sure</label>
		";
		$form_link     = "editthread.php?id={$_GET['id']}";
		$buttons       = array(
			[BTN_SUBMIT, "Delete thread"],
			[BTN_URL   , "Cancel", "thread.php?id={$_GET['id']}"]
		);
		
		confirm_message($msgkey, $message, $title, $form_link, $buttons, TOKEN_SLAMMER);
	}
	else {
		pageheader();
		
		$links = array(
			[$forum['title']    , "forum.php?id={$forum['id']}"],
			[$thread['title']   , "thread.php?id={$_GET['id']}"],
			["Edit thread"      , NULL],
		);
		$barlinks = dobreadcrumbs($links); 
		
		if (isset($_POST['submit'])) {
			check_token($_POST['auth']);
			
			$_POST['iconid'] 		= filter_int($_POST['iconid']);
			$_POST['custposticon'] 	= filter_string($_POST['custposticon']);
			
			$posticons 			= file('posticons.dat');
				
			if ($_POST['custposticon'])
				$icon = $_POST['custposticon'];
			else if (isset($posticons[$_POST['iconid']]))
				$icon = trim($posticons[$_POST['iconid']]);
			else
				$icon = "";
			
			$_POST['subject'] = filter_string($_POST['subject']);
			if (!$_POST['subject']) 
				errorpage("Couldn't edit the thread. You haven't entered a subject.");

			if ($ismod) {
				$_POST['forummove']    = filter_int($_POST['forummove']);
				$_POST['closed']       = filter_int($_POST['closed']);
				$_POST['sticky']       = filter_int($_POST['sticky']);
				$_POST['announcement'] = filter_int($_POST['announcement']);
			} else {
				$_POST['forummove']    = $thread['forum'];
				$_POST['closed']       = $thread['closed'];
				$_POST['sticky']       = $thread['sticky'];
				$_POST['announcement'] = $thread['announcement'];
			}
			
			// Here we go
			$sql->beginTransaction();
			
			$data = [
				'title'        => $_POST['subject'],
				'description'  => filter_string($_POST['description']),
				'icon'         => $icon,
				'closed'       => $_POST['closed'],
				'sticky'       => $_POST['sticky'],
				'announcement' => $_POST['announcement'],
			];
			hook_use_ref('thread-edit-act', $data);
			$sql->queryp("UPDATE threads SET ".mysql::setplaceholders($data)." WHERE id = {$_GET['id']}", $data);
			
			if ($_POST['forummove'] != $thread['forum']) {
				move_thread($_GET['id'], $_POST['forummove'], $thread);
			}
			
			$sql->commit();
			errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for editing the thread.","thread.php?id={$_GET['id']}",'return to the thread');
		}
		
		$posticonlist = dothreadiconlist(NULL, $thread['icon']);
		
		$check1[$thread['closed']]='checked=1';
		$check2[$thread['sticky']]='checked=1';
		$check3[$thread['announcement']]='checked=1';
		
		$forummovelist = doforumlist($thread['forum'], 'forummove'); // Return a pretty forum list
		
		
		if ($sysadmin && $config['allow-thread-deletion']) {
			$delthread = " <input type=checkbox name=deletethread value=1> Delete thread";
		} else
			$delthread = "";
		
		?>
		<?= $barlinks . $forum_error ?>
		<form method='POST' action='?id=<?=$_GET['id']?>'>
		<table class='table'>
			<tr>
				<td class='tdbgh' style='width: 150px'>&nbsp;</td>
				<td class='tdbgh'>&nbsp;</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Thread title:</td>
				<td class='tdbg2'>
					<input type='text' name='subject' value="<?=htmlspecialchars($thread['title'])?>" SIZE=40 MAXLENGTH=100>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Thread description:</td>
				<td class='tdbg2'>
					<input type='text' name=description value="<?=htmlspecialchars($thread['description'])?>" SIZE=100 MAXLENGTH=120>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Thread icon:</td>
				<td class='tdbg2'><?= $posticonlist ?></td>
			</tr>
<?php	if ($ismod) { ?>
			<tr>
				<td class='tdbg1 center' rowspan='3'>&nbsp;</td>
				<td class='tdbg2'>
					<input type=radio name=closed value=0 <?=filter_string($check1[0])?>> Open&nbsp; &nbsp;
					<input type=radio name=closed value=1 <?=filter_string($check1[1])?>>Closed
				</td>
			</tr>
			<tr>
				<td class='tdbg2'>
					<input type=radio name=sticky value=0 <?=filter_string($check2[0])?>> Normal&nbsp; &nbsp;
					<input type=radio name=sticky value=1 <?=filter_string($check2[1])?>>Sticky
				</td>
			</tr>
			<tr>
				<td class='tdbg2'>
					<input type=radio name=announcement value=0 <?=filter_string($check3[0])?>> Normal Thread&nbsp; &nbsp;
					<input type=radio name=announcement value=1 <?=filter_string($check3[1])?>>Forum Announcement
				</td>
			</tr>
<?php	} ?>
		<?= hook_print('thread-edit-form-flag') ?>
<?php	if ($ismod) { ?>
			<tr>
				<td class='tdbg1 center b'>Forum</td>
				<td class='tdbg2'><?= $forummovelist . $delthread ?></td>
			</tr>
<?php	} ?>

			
			<tr>
				<td class='tdbg1'>&nbsp;</td>
				<td class='tdbg2'>
					<?= auth_tag() ?>
					<input type='submit' name='submit' VALUE="Edit thread">
				</td>
			</tr>
		</table>
		</form>
		<?= $barlinks ?>
		<?php
	}
	
	pagefooter();
	