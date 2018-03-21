<?php
	
require 'lib/function.php';

$meta['noindex'] = true;
if (!$loguser['id']) {
	errorpage("You need to be logged in to send private messages.", 'login.php', 'log in (then try again)');
}
if($loguser['powerlevel'] <= -2) {
	errorpage("You are permabanned and cannot send private messages.",'private.php','your private message box',0);
}
$barlinks = "<span class='font'><a href='index.php'>{$config['board-name']}</a> - <a href='private.php'>Private messages</a>";

$_GET['id'] = filter_int($_GET['id']);

if ($_GET['id']) {
	// Replying to a thread
	$_GET['id']         = filter_int($_GET['id']);
	$_GET['postid']     = filter_int($_GET['postid']);



	$thread = $sql->fetchq("SELECT closed, title, user, lastposter FROM pm_threads WHERE id = {$_GET['id']}");
	if (!$thread) {
		errorpage("Nice try. Next time, wait until someone makes the conversation <i>before</i> trying to reply to it.", "index.php", 'return to the index page', 0);
	}
	
	// Thread permissions for our sanity
	$mythread       = ($loguser['id'] == $thread['user'] && $config['allow-pmthread-edit']);
	$canreply       = ($isadmin || $mythread || $sql->resultq("SELECT COUNT(*) FROM pm_access WHERE user = {$loguser['id']} AND thread = {$_GET['id']}"));
	$closed			= (!$isadmin && !$mythread && $thread['closed']);
	
	if ($closed) {
		errorpage("Sorry, but this thread is closed, and no more replies can be posted in it.","thread.php?id={$_GET['id']}",$thread['title'],0);
	} else if (!$canreply) {
		errorpage("You are not allowed to post in this thread.","thread.php?id={$_GET['id']}",$thread['title'],0);
	}
	
	$ppp	= isset($_GET['ppp']) ? ((int) $_GET['ppp']) : ($loguser['id'] ? $loguser['postsperpage'] : $config['default-ppp']);
	$ppp	= max(min($ppp, 500), 1);

	
	$_POST['message']   = filter_string($_POST['message']);
	
	$_POST['moodid']    = filter_int($_POST['moodid']);
	$_POST['nosmilies'] = filter_int($_POST['nosmilies']);
	$_POST['nolayout']  = filter_int($_POST['nolayout']);
	$_POST['nohtml']    = filter_int($_POST['nohtml']);
	
	$_POST['close']     = filter_int($_POST['close']);
	
	// Attachment preview stuff
	$input_tid  = "";
	$attach_key = 'pm'.$_GET['id'];
	
	$loguser['id'] = $loguser['id'];
	if (isset($_POST['submit']) || isset($_POST['preview'])) {
		$error = NULL;
		if (!$_POST['message'])
			$error	= "You didn't enter anything in the post.";
		if ($loguser['lastpmtime'] > (ctime()-4))
			$error	= "You are posting too fast.";
		
		if ($error) { // This redirect is so fucking annoying
			errorpage("Couldn't enter the post. $error<br/>You can return to the previous page, or refresh to try again."); //  "thread.php?id={$_GET['id']}", htmlspecialchars($thread['title']), 0
		}
		
		// Process attachments removal
		if ($config['allow-attachments']) {
			process_temp_attachments($attach_key, $loguser['id']);
		}
				
		if (isset($_POST['submit'])) {
			check_token($_POST['auth']);
			
			// All OK
			//$numposts		= $loguser['posts']+ 1;

			$numdays          = (ctime() - $loguser['regdate']) / 86400;
			$tags             = array();
			$_POST['message'] = doreplace($_POST['message'],$loguser['posts'],$numdays,$loguser['id'], $tags);
			$tagval           = json_encode($tags);
			$currenttime      = ctime();
			
			$sql->beginTransaction();

			if ($_POST['nolayout']) {
				$headid = 0;
				$signid = 0;
			} else {
				$headid = getpostlayoutid($loguser['postheader']);
				$signid = getpostlayoutid($loguser['signature']);
			}
			
			$postdata = [
				'thread'			=> $_GET['id'],
				'user'				=> $loguser['id'],
				'date'				=> $currenttime,
				'ip'				=> $_SERVER['REMOTE_ADDR'],
				//'num'				=> $numposts,
				
				'headid'			=> $headid,
				'signid'			=> $signid,
				'moodid'			=> $_POST['moodid'],
				
				'text'				=> $_POST['message'],
				'tagval'			=> $tagval,
				'options'			=> $_POST['nosmilies'] . "|" . $_POST['nohtml'],
			 ];
			$sql->queryp("INSERT INTO `pm_posts` SET ".mysql::setplaceholders($postdata), $postdata);	 
			$pid = $sql->insert_id();
			if ($config['allow-attachments']) {
				save_attachments($attach_key, $loguser['id'], $pid, 'pm');
			}
			
			// Update statistics
			$modq = ($isadmin || $mythread) ? "`closed` = {$_POST['close']}," : "";
			$sql->query("UPDATE `pm_threads` SET $modq `replies` =  `replies` + 1, `lastpostdate` = '$currenttime', `lastposter` = '{$loguser['id']}' WHERE `id` = '{$_GET['id']}'");
			$sql->query("UPDATE `pm_threadsread` SET `read` = '0' WHERE `tid` = '{$_GET['id']}'");
			$sql->query("REPLACE INTO pm_threadsread SET `uid` = '{$loguser['id']}', `tid` = '{$_GET['id']}', `time` = ". ctime() .", `read` = '1'");
			$sql->query("UPDATE `users` SET `lastpmtime` = '$currenttime' WHERE `id` = '{$loguser['id']}'");

			$sql->commit();
			return header("Location: showprivate.php?pid=$pid#$pid");

		}
		
	}
	/*
		Main page
	*/
	
		
	$smilies = readsmilies();
	pageheader("Conversation: ".htmlspecialchars($thread['title'])." -- New Reply");
	$barlinks .= " - <a href='showprivate.php?id={$_GET['id']}'>".htmlspecialchars($thread['title'])."</a>";
	
	/*
		Previous posts in the conversation
	*/
	$postlist = thread_history($_GET['id'], $ppp + 1, true);	
	
	/*
		Quoting something?
	*/
	if ($_GET['postid']) {
		$post = $sql->fetchq("
			SELECT user, text, thread 
			FROM pm_posts 
			WHERE id = {$_GET['postid']} AND (".((int) $isadmin)." OR deleted = 0)
		");
		if ($post && $post['thread'] == $_GET['id']) { // Make sure the quote is in the same thread
			$post['text'] = str_replace('<br>','\n',$post['text']);
			$quoteuser = $sql->resultq("SELECT name FROM users WHERE id = {$post['user']}");
			$_POST['message'] = "[quote={$quoteuser}]{$post['text']}[/quote]\r\n";
			unset($post, $quoteuser);
		}
	}
	
	if (isset($_POST['preview'])) {
		$data = array(
			// Text
			'message' => $_POST['message'],	
			#'head'    => "",
			#'sign'    => "",
			// Post metadata
			#'id'      => 0,
			'forum'   => -1, // PM "Forum"
			#'ip'      => "",
			#'num'     => "",
			#'date'    => "",
			// (mod) Options
			'nosmilies' => $_POST['nosmilies'],
			'nohtml'    => $_POST['nohtml'],
			'nolayout'  => $_POST['nolayout'],
			'moodid'    => $_POST['moodid'],
			'noob'      => 0,
			// Attachments
			'attach_key' => $attach_key,
			#'attach_sel' => "",
		);
		print preview_post($loguser, $data);
	} else {
		$_POST['close'] = $thread['closed'];
	}
	
	$modoptions	= "";
	
	if ($isadmin || $mythread) {
		$selclosed = $_POST['close'] ? "checked" : "";
		
		$modoptions = 
		"<tr>
			<td class='tdbg1 center b'>
				Extra Options:
			</td>
			<td class='tdbg2' colspan=2>
				<input type='checkbox' name='close' id='close' value=1 $selclosed><label for='close'>Close</label>
			</td>
		</tr>";
	}
	
	$nosmilieschk   = $_POST['nosmilies'] ? "checked" : "";
	$nolayoutchk    = $_POST['nolayout']  ? "checked" : "";
	$nohtmlchk      = $_POST['nohtml']    ? "checked" : "";
	
	?>
	<?=$barlinks?>
	<form method="POST" action="?id=<?=$_GET['id']?>" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
		<tr>
			<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
			<td class='tdbgh center' colspan=2>&nbsp;</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Reply:</td>
			<td class='tdbg2' style='width: 800px' valign=top>
				<textarea wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" autofocus><?=htmlspecialchars($_POST['message'], ENT_QUOTES)?></textarea>
			</td>
			<td class='tdbg2' width=*>
				<?=mood_layout(0, $loguser['id'], $_POST['moodid'])?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2' colspan=2>
				<?= auth_tag() ?>
				<input type='submit' class=submit name=submit VALUE="Submit reply">
				<input type='submit' class=submit name=preview VALUE="Preview reply">
			</td>
		</tr>
	
		<tr>
			<td class='tdbg1 center b'>Options:</td>
			<td class='tdbg2' colspan=2>
				<input type='checkbox' name="nosmilies" id="nosmilies" value="1"<?=$nosmilieschk?>><label for="nosmilies">Disable Smilies</label> -
				<input type='checkbox' name="nolayout"  id="nolayout"  value="1"<?=$nolayoutchk ?>><label for="nolayout" >Disable Layout</label> -
				<input type='checkbox' name="nohtml"    id="nohtml"    value="1"<?=$nohtmlchk   ?>><label for="nohtml"   >Disable HTML</label> | 
				<?=mood_layout(1, $loguser['id'], $_POST['moodid'])?>
			</td>
		</tr>
		<?=$modoptions?>
		<?=quikattach($_GET['id'], $loguser['id'])?>
	</table>
	<br>
	<?=$postlist?>
	</form>
	<?=$barlinks?>
<?php

} 
else {
	// Creating a new thread
	pageheader("New Conversation");
	
	$smilies = readsmilies();

	/*
		Variable initialization (global)
	*/
	$posticons              = file('posticons.dat');
	$_POST['iconid']        = (isset($_POST['iconid']) ? (int) $_POST['iconid'] : -1); // 'None' should be the default value
	$_POST['custposticon']  = filter_string($_POST['custposticon']);
	$iconpreview = $posticon = "";
	
	$_POST['subject']       = filter_string($_POST['subject']);
	$_POST['description']   = filter_string($_POST['description']);
	$_POST['message']       = filter_string($_POST['message']);
	$_POST['users']         = filter_string($_POST['users']);
	$userlist  = array_filter(explode(';', $_POST['users']), 'trim');
	$destcount = count($userlist);
	
	$_POST['moodid']        = filter_int($_POST['moodid']);
	$_POST['nosmilies']     = filter_int($_POST['nosmilies']);
	$_POST['nohtml']        = filter_int($_POST['nohtml']);
	$_POST['nolayout']      = filter_int($_POST['nolayout']);
	$_POST['tclosed']       = filter_int($_POST['close']);
	$_POST['folder']        = isset($_GET['dir']) ? ((int) $_GET['dir']) : filter_int($_POST['folder']); // Convenience for links
	
	// Attachment preview stuff
	$input_tid   = "";
	$attach_key  = "nk";
	
	if (isset($_POST['preview']) || isset($_POST['submit'])) {
		// common threadpost / query requirements		
		if (!$_POST['message']) {
			$error = "You haven't entered a message.";
		} else if (!$_POST['subject']) {
			$error = "You haven't entered a subject.";
		} else if (!$destcount) {
			$error = "You haven't entered an existing username to send this conversation to.";
		} else if ($destcount > $config['pmthread-dest-limit']) {
			$error = "You have entered too many usernames.";
		} else if ($loguser['lastpmtime'] > (ctime()-30)) {
			$error	= "You are trying to post too rapidly.";
		} else if (
			!default_pm_folder($_POST['folder'], DEFAULTPM_DEFAULT) &&
			!$sql->resultq("SELECT COUNT(*) FROM pm_folders WHERE user = {$loguser['id']} AND folder = {$_POST['folder']}")
		) {
			$error = "You have selected a nonexisting folder.";
		} else {
			$error = "";
			foreach ($userlist as $x) {
				$x = trim($x);
				$valid    = $sql->resultp("SELECT id FROM users WHERE name = ? AND id != {$loguser['id']}", [$x]);
				if (!$valid) {
					$error .= "<li>{$x}";
				} else {
					$destid[$valid] = $valid; // no duplicates please
				}
			}
			if ($error) {
				$error = "The following users you've entered don't exist:<ul>{$error}</ul>";
			}
		}
		
		if ($error) { // This redirect is so fucking annoying
			errorpage("Couldn't enter the post. $error<br/>You can return to the previous page, or refresh to try again.");
		}
		
		// All OK!
		if ($config['allow-attachments']) {
			$attachids    = get_attachments_key("npmx", $loguser['id']); // Get the base key to identify the correct files
			$attach_id    = $attachids[0]; // Cached ID to safely reuse attach_key across requests
			$attach_key   = $attachids[1]; // String (base) key for file names
			$attach_count = process_temp_attachments($attach_key, $loguser['id']); // Process the attachments and return the post-processed total
			if ($attach_count) {
				// Some files are attached; reconfirm the key
				$input_tid = save_attachments_key($attach_id);
			} else {
				$attach_key = ""; // just in case
			}
		}
		
		if (isset($_POST['submit'])) {
			check_token($_POST['auth']);
			
			$currenttime 	  = ctime();
			$numdays          = ($currenttime - $loguser['regdate']) / 86400;
			$tags             = array();
			$_POST['message'] = doreplace($_POST['message'], $loguser['posts'], $numdays, $loguser['id'], $tags);
			$tagval           = json_encode($tags);
			
			if ($_POST['iconid'] != '-1' && isset($posticons[$_POST['iconid']])) {
				$posticon = $posticons[$_POST['iconid']];
			} else {
				$posticon = $_POST['custposticon'];
			}
			if ($_POST['nolayout']) {
				$headid = 0;
				$signid = 0;
			} else {
				$headid = getpostlayoutid($loguser['postheader']);
				$signid = getpostlayoutid($loguser['signature']);
			}
			if (!$isadmin && !$config['allow-pmthread-edit']) {
				$_POST['tclosed'] = 0;
			}
			
			
			$sql->beginTransaction();
			$sql->query("UPDATE `users` SET `lastpmtime` = '$currenttime' WHERE `id` = '{$loguser['id']}'");
			
			// Insert thread
			$vals = [
				'user'				=> $loguser['id'],
				
				'closed'			=> $_POST['tclosed'],
				
				'title'				=> xssfilters($_POST['subject']),
				'description'		=> xssfilters($_POST['description']),
				'icon'				=> $posticon,
				
				'replies'			=> 0,
				'firstpostdate'		=> $currenttime,
				'lastpostdate'		=> $currenttime,
				'lastposter'		=> $loguser['id'],
			];
			$sql->queryp("INSERT INTO `pm_threads` SET ".mysql::setplaceholders($vals), $vals);
			$tid = $sql->insert_id();
			
			// Insert post
			$vals = [
				'thread'			=> $tid,
				'user'				=> $loguser['id'],
				'date'				=> $currenttime,
				'ip'				=> $_SERVER['REMOTE_ADDR'],
				
				'headid'			=> $headid,
				'signid'			=> $signid,
				'moodid'			=> $_POST['moodid'],
				
				'text'				=> xssfilters($_POST['message']),
				'tagval'			=> $tagval,
				'options'			=> $_POST['nosmilies'] . "|" . $_POST['nohtml'],
			 ];
			$sql->queryp("INSERT INTO `pm_posts` SET ".mysql::setplaceholders($vals), $vals);
			$pid = $sql->insert_id();
			
			// Insert ACL
			$acl = $sql->prepare("INSERT INTO pm_access (thread, user, folder) VALUES (?,?,?)");
			foreach ($destid as $in) {
				$sql->execute($acl, [$tid, $in, PMFOLDER_MAIN]);
			}
			$sql->execute($acl, [$tid, $loguser['id'], $_POST['folder']]);
			$sql->commit();
			
			if ($config['allow-attachments']) {
				save_attachments($attach_key, $loguser['id'], $pid, 'pm');
			}
			
			errorpage("Conversation posted successfully!", "showprivate.php?id=$tid", $_POST['subject'], 0);
			
		}
		
	}
	
	/*
		Main page below
	*/
	$nosmilieschk 	= $_POST['nosmilies'] 	? " checked" : "";
	$nohtmlchk	 	= $_POST['nohtml'] 		? " checked" : "";
	$nolayoutchk 	= $_POST['nolayout'] 	? " checked" : "";

	if (isset($_POST['preview'])) {
		
		$iconpreview = "";
		if ($posticon) {
			$iconpreview = "<img src=\"".htmlspecialchars($posticon)."\" height=15 align=absmiddle>";
		}
		// Threadpost
		
		$data = array(
			// Text
			'message' => $_POST['message'],	
			#'head'    => "",
			#'sign'    => "",
			// Post metadata
			#'id'    => 0,
			'forum'   => -1, // PM "Forum"
			#'ip'    => "",
			#'num'   => "",
			#'date'  => "",
			// (mod) Options
			'nosmilies' => $_POST['nosmilies'],
			'nohtml'    => $_POST['nohtml'],
			'nolayout'  => $_POST['nolayout'],
			'moodid'    => $_POST['moodid'],
			'noob'      => 0,
			// Attachments
			'attach_key'  => $attach_key,
			#'attach_sel'  => "",
		);
?>
	<table class='table'><tr><td class='tdbgh center'>Conversation preview</td></tr></table>
	<table class='table' style='border-top: none !important'>
		<tr>
			<td class='tdbg2 center' style='width: 4%'>
				<?=$iconpreview?>
			</td>
			<td class='tdbg1'>
				<b><?=htmlspecialchars($_POST['subject'])?></b>
				<span class='fonts'><br><?=htmlspecialchars($_POST['description'])?></span>
			</td>
		</tr>
	</table>
	<?= preview_post($loguser, $data, PREVIEW_NEW, NULL) ?>
	<?php
	
		$autofocus[1] = 'autofocus'; // for 'message'
	} else {
		$autofocus[0] = 'autofocus'; // for 'subject'
	}
		
	$modoptions	= "";
	if ($isadmin || $config['allow-pmthread-edit']) {
		$selclosed = $_POST['tclosed'] ? "checked" : "";
		$modoptions = " - <input type='checkbox' name='close' id='close' value=1 $selclosed><label for='close'>Disable replies</label>";
	}		
	
?>

	<?=$barlinks?>
	<form method="POST" action="?" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
		<tr>
			<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
			<td class='tdbgh center' colspan=2>&nbsp;</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Thread icon:</td>
			<td class='tdbg2' colspan=2>
				<?=dothreadiconlist($_POST['iconid'], $_POST['custposticon'])?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Thread title:</td>
			<td class='tdbg2' colspan=2>
				<input type='text' name=subject SIZE=40 MAXLENGTH=100 VALUE="<?=htmlspecialchars($_POST['subject'])?>" <?=filter_string($autofocus[0])?>>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Thread description:</td>
			<td class='tdbg2' colspan=2>
				<input type='text' name=description SIZE=100 MAXLENGTH=120 VALUE="<?=htmlspecialchars($_POST['description'])?>">
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Other partecipants:</td>
			<td class='tdbg2' colspan=2>
				<input type='text' name=users SIZE=60 MAXLENGTH=100 VALUE="<?=htmlspecialchars($_POST['users'])?>">
				<span class='fonts'>Max <?= $config['pmthread-dest-limit'] ?> users allowed. Multiple users separated with a semicolon.</span>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Post:</td>
			<td class='tdbg2' style='width: 800px' valign=top>
				<textarea wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" <?=filter_string($autofocus[1])?>><?=htmlspecialchars($_POST['message'])?></textarea>
			</td>
			<td class='tdbg2' width=*>
				<?=mood_layout(0, $loguser['id'], $_POST['moodid'])?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2' colspan=2>
				<input type='hidden' name=action VALUE=postthread>
				<?= auth_tag() ?>
				<?= $input_tid ?>
				<input type='submit' class=submit name=submit VALUE="Submit thread">
				<input type='submit' class=submit name=preview VALUE="Preview thread">
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Options:</td>
			<td class='tdbg2' colspan=2>
				<input type='checkbox' name="nosmilies" id="nosmilies" value="1"<?=$nosmilieschk?>><label for="nosmilies">Disable Smilies</label> -
				<input type='checkbox' name="nolayout"  id="nolayout"  value="1"<?=$nolayoutchk ?>><label for="nolayout" >Disable Layout</label> -
				<input type='checkbox' name="nohtml"    id="nohtml"    value="1"<?=$nohtmlchk   ?>><label for="nohtml"   >Disable HTML</label> 
				<?= $modoptions ?> | 
				<?=mood_layout(1, $loguser['id'], $_POST['moodid'])?>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Save in:</td>
			<td class='tdbg2' colspan=2>
				<?= pm_folder_select('folder', $loguser['id'], $_POST['folder']) ?>
			</td>
		</tr>
		<?=quikattach($attach_key, $loguser['id'])?>
		</table>
		</form>
		<?=$barlinks?>
	<?php
	
}

pagefooter();
	