<?php
	$meta['cache'] = true;
		
	require "lib/common.php";

	$meta['noindex'] = true;
	if (!$loguser['id']) {
		errorpage("You need to be logged in to send private messages.", 'login.php', 'log in (then try again)');
	}
	if($loguser['powerlevel'] <= -2) {
		errorpage("You are permabanned and cannot send private messages.",'private.php','your private message box',0);
	}

	$submitted = isset($_POST['submit']) || isset($_POST['preview']);
	$_GET['id'] = filter_int($_GET['id']);

	// Replying to a thread
	$_GET['postid']     = filter_int($_GET['postid']);

	load_pm_thread($_GET['id']);
	
	// Thread permissions for our sanity
	$mythread       = ($loguser['id'] == $thread['user'] && $config['allow-pmthread-edit']);
	$canreply       = ($isadmin || $access); // $mythread ||
	$closed			= (!$isadmin && !$mythread && $thread['closed']);
	
	$error          = "";
	if ($closed) {
		$error .= "Sorry, but this thread is closed, and no more replies can be posted in it.<br>";
	} else if (!$canreply) {
		$error .= "You are not allowed to post in this thread.";
	}
	// Error out immediately if we didn't submit anything
	if ($error && !$submitted)
		errorpage($error);
	
	$_POST['message']   = filter_string($_POST['message']);
	$_POST['moodid']    = filter_int($_POST['moodid']);
	$_POST['nosmilies'] = filter_int($_POST['nosmilies']);
	$_POST['nolayout']  = filter_int($_POST['nolayout']);
	$_POST['nohtml']    = filter_int($_POST['nohtml']);
	$_POST['close']     = filter_int($_POST['close']);
	
	// Attachment preview stuff
	$input_tid  = "";
	$attach_key = 'pm'.$_GET['id'];
	if ($submitted) {
		
		if (!$error) {
			if (!trim($_POST['message']))
				$error .= "You didn't enter anything in the post.<br>";
			if ($loguser['lastpmtime'] > (time()-4))
				$error .= "You are posting too fast.<br>";
		}
		
		if (!$error) {
			$can_attach = can_use_attachments($loguser);
			
			// Process attachments removal
			if ($can_attach) {
				list($attachsel, $total) = process_attachments($attach_key, $loguser['id'], 0, ATTACH_PM);
			}
					
			if (isset($_POST['submit'])) {
				check_token($_POST['auth']);
				
				// Create post
				$preq = new create_pm_post_req();
				$preq->nolayout = $_POST['nolayout'];
				$preq->vals = array(
					// Base fields
					'thread'        => $_GET['id'],
					'user'          => $loguser,
					'ip'            => $_SERVER['REMOTE_ADDR'],
					'text'          => $_POST['message'],
					// Opt
					'moodid'        => $_POST['moodid'],
					// Flags
					'nosmilies'     => $_POST['nosmilies'],
					'nohtml'        => $_POST['nohtml'],
				);
				if ($isadmin || $mythread) {
					$preq->threadupdate = array(
						'closed' => $_POST['close'],
					);
				}
				$preq->id = create_pm_post($preq) or throw new Exception("Failed to create PM Post");
				if ($can_attach) {
					confirm_attachments($attach_key, $loguser['id'], $preq->id, ATTACH_PM);
				}
				$sql->commit();
				return header("Location: showprivate.php?pid={$preq->id}#{$preq->id}");
			}
		}
	}
	/*
		Main page
	*/
	
	$ppp      = get_ppp();
	$smilies  = readsmilies();	
	$postlist = thread_history($_GET['id'], $ppp + 1);	
	
	// Post preview
	if (!$error && isset($_POST['preview'])) {
		$preview_msg = $_POST['message'];
		if ($can_attach) {
			$preview_msg = replace_attachment_temp_tags($attach_key, $loguser['id'], $preview_msg);
		}
		$data = array(
			// Text
			'message' => $preview_msg,	
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
		$postpreview = preview_post($loguser, $data, PREVIEW_PM);
	} else {
		$_POST['close'] = $thread['closed'];
		$postpreview = "";
	}
	
	/*
		Quoting something?
	*/
	if ($_GET['postid']) {
		$post = $sql->fetchq("
			SELECT user, text, thread 
			FROM pm_posts 
			WHERE id = {$_GET['postid']}
			  AND thread = {$_GET['id']}
			  AND (".((int) $isadmin)." OR deleted = 0)
		");
		if ($post) {
			$post['text'] = str_replace('<br>','\n',$post['text']);
			$quoteuser = $sql->resultq("SELECT name FROM users WHERE id = {$post['user']}");
			$_POST['message'] = "[quote=\"{$quoteuser}\" id=\"{$_GET['postid']}\"]{$post['text']}[/quote]\r\n";
			unset($post, $quoteuser);
		}
	}
	
	$modoptions	= "";
	
	if ($isadmin || $mythread) {
		$selclosed = $_POST['close'] ? "checked" : "";
		$modoptions = 
		"<tr>
			<td class='tdbg1 center b'>Extra Options:</td>
			<td class='tdbg2'>
				<input type='checkbox' name='close' id='close' value=1 $selclosed><label for='close'>Close</label>
			</td>
		</tr>";
	}
	
	$nosmilieschk   = $_POST['nosmilies'] ? "checked" : "";
	$nolayoutchk    = $_POST['nolayout']  ? "checked" : "";
	$nohtmlchk      = $_POST['nohtml']    ? "checked" : "";
	
	pageheader("Conversation: ".htmlspecialchars($thread['title'])." -- New Reply");
		
	if ($forum_error)
		$forum_error = "<table class='table'>{$forum_error}</table>";
	
	$links = array(
		["Private messages" , "private.php"],
		[$thread['title']   , "showprivate.php?id={$_GET['id']}"],
		["New reply"        , NULL],
	);
	$barlinks = dobreadcrumbs($links); 
	
	print $barlinks . $forum_error;
	// In case something happened, show a message *over the reply box*, to allow fixing anything important.
	if ($error) {
		boardmessage("Couldn't preview or submit the reply. One or more errors occurred:<br><br>".$error, "Error", false);
	}
	print "<br>".$postpreview;
	
	?>
	<form method="POST" action="?id=<?=$_GET['id']?>" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
		<tr><td class='tdbgh center' colspan='2'>New reply</td></tr>
		<tr>
			<td class='tdbg1 center b avatar-preview-parent'>
				Reply:
				<?=mood_preview()?>
			</td>
			<td class='tdbg2 vatop' id="msgtd">
				<textarea id="msgtxt" name="message" rows="21" autofocus><?=htmlspecialchars($_POST['message'])?></textarea>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2'>
				<?= auth_tag() ?>
				<input type='submit' name="submit" value="Submit reply">
				<input type='submit' name="preview" value="Preview reply">
			</td>
		</tr>
	
		<tr>
			<td class='tdbg1 center b'>Options:</td>
			<td class='tdbg2'>
				<input type='checkbox' name="nosmilies" id="nosmilies" value="1"<?=$nosmilieschk?>><label for="nosmilies">Disable Smilies</label> -
				<input type='checkbox' name="nolayout"  id="nolayout"  value="1"<?=$nolayoutchk ?>><label for="nolayout" >Disable Layout</label> -
				<input type='checkbox' name="nohtml"    id="nohtml"    value="1"<?=$nohtmlchk   ?>><label for="nohtml"   >Disable HTML</label> | 
				<?=mood_list($loguser['id'], $_POST['moodid'])?>
			</td>
		</tr>
		<?=$modoptions?>
		<?=quikattach($attach_key, $loguser['id'], $loguser, ATTACH_REQ_DEFAULT)?>
	</table>
	<br>
	<?=$postlist?>
	</form>
	<?=$barlinks?>
<?php

	replytoolbar('msg', $smilies);

	pagefooter();