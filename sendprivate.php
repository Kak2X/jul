<?php

// TODO: Something similar to the error handling in newthread / newreply?
$meta['cache'] = true;
	
require 'lib/function.php';

$meta['noindex'] = true;
if (!$loguser['id']) {
	errorpage("You need to be logged in to send private messages.", 'login.php', 'log in (then try again)');
}
if($loguser['powerlevel'] <= -2) {
	errorpage("You are permabanned and cannot send private messages.",'private.php','your private message box',0);
}

$_GET['id'] = filter_int($_GET['id']);

if ($_GET['id']) {
	// Replying to a thread
	$_GET['postid']     = filter_int($_GET['postid']);

	load_pm_thread($_GET['id']);
	if ($forum_error) {
		$forum_error = "<table class='table'>{$forum_error}</table>";
	}
	
	// Thread permissions for our sanity
	$mythread       = ($loguser['id'] == $thread['user'] && $config['allow-pmthread-edit']);
	$canreply       = ($isadmin || $access); // $mythread ||
	$closed			= (!$isadmin && !$mythread && $thread['closed']);
	
	if ($closed) {
		errorpage("Sorry, but this thread is closed, and no more replies can be posted in it.","thread.php?id={$_GET['id']}",$thread['title'],0);
	} else if (!$canreply) {
		errorpage("You are not allowed to post in this thread.","thread.php?id={$_GET['id']}",$thread['title'],0);
	}
	
	$ppp = get_ppp();
	
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
		else if ($loguser['lastpmtime'] > (ctime()-4))
			$error	= "You are posting too fast.";
		
		if ($error) { // This redirect is so fucking annoying
			errorpage("Couldn't enter the post. $error<br/>You can return to the previous page, or refresh to try again."); //  "thread.php?id={$_GET['id']}", htmlspecialchars($thread['title']), 0
		}
		
		//--
		$can_attach = can_use_attachments($loguser);
		
		// Process attachments removal
		if ($can_attach) {
			process_attachments($attach_key, $loguser['id'], 0, ATTACH_PM);
		}
				
		if (isset($_POST['submit'])) {
			check_token($_POST['auth']);
			if ($isadmin || $mythread) {
				$modq = array(
					'closed' => $_POST['close'],
				);
			} else {
				$modq = array();
			}
			//$modq = ($isadmin || $mythread) ? "`closed` = {$_POST['close']}," : ""; 
			$pid = create_pm_post($loguser, $_GET['id'], $_POST['message'], $_SERVER['REMOTE_ADDR'], $_POST['moodid'], $_POST['nosmilies'], $_POST['nohtml'], $_POST['nolayout'], $modq);
			if ($can_attach) {
				confirm_attachments($attach_key, $loguser['id'], $pid, ATTACH_PM);
			}
			$sql->commit();
			return header("Location: showprivate.php?pid=$pid#$pid");

		}
		
	}
	/*
		Main page
	*/
	
		
	$smilies = readsmilies();
	pageheader("Conversation: ".htmlspecialchars($thread['title'])." -- New Reply");
	
	/*
		Previous posts in the conversation
	*/
	$postlist = thread_history($_GET['id'], $ppp + 1);	
	
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
		print preview_post($loguser, $data, PREVIEW_PM);
	} else {
		$_POST['close'] = $thread['closed'];
	}
	
	$modoptions	= "";
	
	if ($isadmin || $mythread) {
		$selclosed = $_POST['close'] ? "checked" : "";
		
		$modoptions = 
		"<tr>
			<td class='tdbg1 center b'>Extra Options:</td>
			<td class='tdbg2' colspan=2>
				<input type='checkbox' name='close' id='close' value=1 $selclosed><label for='close'>Close</label>
			</td>
		</tr>";
	}
	
	$nosmilieschk   = $_POST['nosmilies'] ? "checked" : "";
	$nolayoutchk    = $_POST['nolayout']  ? "checked" : "";
	$nohtmlchk      = $_POST['nohtml']    ? "checked" : "";
	
	$links = array(
		["Private messages" , "private.php"],
		[$thread['title']   , "showprivate.php?id={$_GET['id']}"],
		["New reply"        , NULL],
	);
	$barlinks = dobreadcrumbs($links); 
	
	?>
	<?= $barlinks . $forum_error ?>
	<form method="POST" action="?id=<?=$_GET['id']?>" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
		<tr>
			<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
			<td class='tdbgh center' colspan=2>&nbsp;</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Reply:</td>
			<td class='tdbg2' id="msgtd" style='width: 800px' valign=top>
				<textarea id="msgtxt" wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" autofocus><?=htmlspecialchars($_POST['message'], ENT_QUOTES)?></textarea>
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
		<?=quikattach($_GET['id'], $loguser['id'], $loguser, ATTACH_REQ_DEFAULT)?>
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
	// Sendprivate link support, which I originally forgot to reimplement back (oops)
	$_GET['userid']         = filter_int($_GET['userid']);
	if ($_GET['userid']) {
		$_POST['users']     = $sql->resultq("SELECT name FROM users WHERE id = {$_GET['userid']}");
	} else {
		$_POST['users']     = filter_string($_POST['users']);
	}
	$userlist  = array_filter(explode(';', $_POST['users']), 'trim');
	
	$_POST['moodid']        = filter_int($_POST['moodid']);
	$_POST['nosmilies']     = filter_int($_POST['nosmilies']);
	$_POST['nohtml']        = filter_int($_POST['nohtml']);
	$_POST['nolayout']      = filter_int($_POST['nolayout']);
	$_POST['close']         = filter_int($_POST['close']);
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
		} else if ($loguser['lastpmtime'] > (ctime()-30)) {
			$error	= "You are trying to post too rapidly.";
		} else if (!valid_pm_folder($_POST['folder'], $loguser['id'])) {
			$error = "You have selected a nonexisting folder.";
		} else if (!($destid = valid_pm_acl($userlist, false, $error))) {
			$error = "The partecipants list cannot be processed.<br>{$error}";
		}
		
		if ($error) { // This redirect is so fucking annoying
			errorpage("Couldn't enter the post. $error<br/>You can return to the previous page, or refresh to try again.");
		}
		
		// All OK!
		$can_attach = can_use_attachments($loguser);
				
		if ($can_attach) {
			$attach_key = "npmx";
			$input_tid = process_attachments($attach_key, $loguser['id'], 0, ATTACH_PM | ATTACH_INCKEY);
		}
		
		// Needed for thread preview
		if ($_POST['iconid'] != '-1' && isset($posticons[$_POST['iconid']])) {
			$posticon = $posticons[$_POST['iconid']];
		} else {
			$posticon = $_POST['custposticon'];
		}
		
		if (isset($_POST['submit'])) {
			check_token($_POST['auth']);
			
			if (!$isadmin && !$config['allow-pmthread-edit']) {
				$_POST['close'] = 0;
			}
			
			$sql->beginTransaction();
			$tid = create_pm_thread($loguser, $_POST['subject'], $_POST['description'], $posticon, $_POST['close']);
			$pid = create_pm_post($loguser, $tid, $_POST['message'], $_SERVER['REMOTE_ADDR'], $_POST['moodid'], $_POST['nosmilies'], $_POST['nohtml'], $_POST['nolayout']);
			set_pm_acl($destid, $tid, false, $_POST['folder']); // and add yourself automatically
			
			if ($can_attach) {
				confirm_attachments($attach_key, $loguser['id'], $pid, ATTACH_PM);
			}
			$sql->commit();
			
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
			$iconpreview = "<img src=\"".escape_attribute($posticon)."\" height=15 align=absmiddle>";
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
			<td class='tdbg2 center' style='width: 4%'><?=$iconpreview?></td>
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
		$selclosed = $_POST['close'] ? "checked" : "";
		$modoptions = " - <input type='checkbox' name='close' id='close' value=1 $selclosed><label for='close'>Disable replies</label>";
	}		
	
	$links = array(
		["Private messages" , "private.php"],
		["New conversation" , NULL],
	);
	$barlinks = dobreadcrumbs($links); 
	
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
				<span class='fonts'><?= ($config['pmthread-dest-limit'] > 0 ? "Max {$config['pmthread-dest-limit']} users allowed. " : "") ?>Multiple users separated with a semicolon.</span>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Post:</td>
			<td class='tdbg2' id="msgtd" style='width: 800px' valign=top>
				<textarea id="msgtxt" wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" <?=filter_string($autofocus[1])?>><?=htmlspecialchars($_POST['message'])?></textarea>
			</td>
			<td class='tdbg2' width=*>
				<?=mood_layout(0, $loguser['id'], $_POST['moodid'])?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2' colspan=2>
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
		<?=quikattach($attach_key, $loguser['id'], $loguser, ATTACH_REQ_DEFAULT)?>
		</table>
		</form>
		<?=$barlinks?>
	<?php
	
}
		
replytoolbar('msg', $smilies);

pagefooter();
	