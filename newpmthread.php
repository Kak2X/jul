<?php
	$meta['cache'] = true;
		
	require "lib/common.php";
	load_layout();
	
	$meta['noindex'] = true;
	if (!$loguser['id']) {
		errorpage("You need to be logged in to send private messages.", 'login.php', 'log in (then try again)');
	}
	if($loguser['powerlevel'] <= -2) {
		errorpage("You are permabanned and cannot send private messages.",'private.php','your private message box',0);
	}
	
	/*
		Variable initialization (global)
	*/
	$submitted = isset($_POST['submit']) || isset($_POST['preview']);
	$_GET['id'] = filter_int($_GET['id']);
	$_POST['iconid']        = (isset($_POST['iconid']) ? (int) $_POST['iconid'] : -1); // 'None' should be the default value
	$_POST['custposticon']  = filter_string($_POST['custposticon']);
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
	
	$smilies     = readsmilies();
	$posticons   = file('posticons.dat');
	$iconpreview = $posticon = "";
	$error       = "";
	
	// Attachment preview stuff
	$input_tid   = "";
	$attach_key  = "nk";
	if ($submitted) {
		// common threadpost / query requirements		
		if (!$_POST['message'])
			$error .= "You haven't entered a message.<br>";
		if (!$_POST['subject'])
			$error .= "You haven't entered a subject.<br>";
		if ($loguser['lastpmtime'] > (time()-30))
			$error .= "You are trying to post too rapidly.<br>";
		if (!valid_pm_folder($_POST['folder'], $loguser['id']))
			$error .= "You have selected a nonexisting folder.<br>";
		if (!($destid = valid_pm_acl($userlist, false, $aclerror)))
			$error .= "<hr>The partecipants list cannot be processed:<br>{$aclerror}<hr>";
		
		if (!$error) {
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
				
				$sql->beginTransaction();
				
				// Create thread
				$treq = new create_pm_thread_req();
				$treq->vals = [
					// Main
					'user'				=> $loguser,
					'title'				=> $_POST['subject'],
					'description'		=> $_POST['description'],
					'icon'				=> $posticon,
					// Flags
					'closed'			=> !$isadmin && !$config['allow-pmthread-edit'] ? 0 : $_POST['close'],
				];
				$treq->id = create_pm_thread($treq) or throw new Exception("Failed to create PM Thread");
				
				// Create post
				$preq = new create_pm_post_req();
				$preq->nolayout = $_POST['nolayout'];
				$preq->vals = array(
					// Base fields
					'thread'        => $treq->id,
					'user'          => $loguser,
					'ip'            => $_SERVER['REMOTE_ADDR'],
					'text'          => $_POST['message'],
					// Opt
					'moodid'        => $_POST['moodid'],
					// Flags
					'nosmilies'     => $_POST['nosmilies'],
					'nohtml'        => $_POST['nohtml'],
				);
				$preq->id = create_pm_post($preq) or throw new Exception("Failed to create PM Post");
				set_pm_acl($destid, $treq->id, false, $_POST['folder']); // and add yourself automatically
				
				if ($can_attach) {
					confirm_attachments($attach_key, $loguser['id'], $preq->id, ATTACH_PM);
				}
				$sql->commit();
				
				errorpage("Conversation posted successfully!", "showprivate.php?id={$treq->id}", $_POST['subject'], 0);
			}
		}
	}
	
	/*
		Main page below
	*/
	$nosmilieschk 	= $_POST['nosmilies'] 	? " checked" : "";
	$nohtmlchk	 	= $_POST['nohtml'] 		? " checked" : "";
	$nolayoutchk 	= $_POST['nolayout'] 	? " checked" : "";
	$modoptions	= "";
	if ($isadmin || $config['allow-pmthread-edit']) {
		$selclosed = $_POST['close'] ? "checked" : "";
		$modoptions = " - <input type='checkbox' name='close' id='close' value=1 $selclosed><label for='close'>Disable replies</label>";
	}	
	
	if (!$error && isset($_POST['preview'])) {
		
		$iconpreview = "";
		if ($posticon) {
			$iconpreview = "<img src=\"".escape_attribute($posticon)."\" height=15 align=absmiddle>";
		}
		$preview_msg = $_POST['message'];
		if ($can_attach) {
			$preview_msg = replace_attachment_temp_tags($attach_key, $loguser['id'], $preview_msg);
		}
		
		// Threadpost
		$data = array(
			// Text
			'message' => $preview_msg,	
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
		
		$postpreview = "
	<table class='table'><tr><td class='tdbgh center'>Conversation preview</td></tr></table>
	<table class='table' style='border-top: none !important'>
		<tr>
			<td class='tdbg2 center' style='width: 4%'>{$iconpreview}</td>
			<td class='tdbg1'>
				<b>".htmlspecialchars($_POST['subject'])."</b>
				<span class='fonts'><br>".htmlspecialchars($_POST['description'])."</span>
			</td>
		</tr>
	</table>
	".preview_post($loguser, $data, PREVIEW_NEW, NULL);
		$autofocus[1] = 'autofocus'; // for 'message'
	} else {
		$postpreview = "";
		$autofocus[0] = 'autofocus'; // for 'subject'
	}
	
	// Creating a new thread
	pageheader("New Conversation");
	
	$links = array(
		["Private messages" , "private.php"],
		["New conversation" , NULL],
	);
	$barlinks = dobreadcrumbs($links); 
	
	print $barlinks . $postpreview;
	// In case something happened, show a message *over the reply box*, to allow fixing anything important.
	if ($error) {
		boardmessage("Couldn't preview or submit the thread. One or more errors occurred:<br><br>".$error, "Error", false);
	}
	print "<br>";
?>
	<form method="POST" action="?" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
		<tr><td class='tdbgh center' colspan='2'>New conversation</td></tr>	
		<tr>
			<td class='tdbg1 center b'>Thread icon:</td>
			<td class='tdbg2'>
				<?=dothreadiconlist($_POST['iconid'], $_POST['custposticon'])?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Thread title:</td>
			<td class='tdbg2'>
				<input type='text' name="subject" size="40" maxlength="100" value="<?=escape_attribute($_POST['subject'])?>" <?=filter_string($autofocus[0])?>>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Thread description:</td>
			<td class='tdbg2'>
				<input type='text' name="description" size="100" maxlength="120" value="<?=escape_attribute($_POST['description'])?>">
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Other partecipants:</td>
			<td class='tdbg2' colspan=2>
				<input type='text' name="users" size="60" maxlength="100" value="<?=escape_attribute($_POST['users'])?>">
				<span class='fonts'><?= ($config['pmthread-dest-limit'] > 0 ? "Max {$config['pmthread-dest-limit']} users allowed. " : "") ?>Multiple users separated with a semicolon.</span>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b avatar-preview-parent'>
				Post:
				<?=mood_preview()?>
			</td>
			<td class='tdbg2 vatop' id="msgtd">
				<textarea id="msgtxt" name="message" rows="21" <?=filter_string($autofocus[1])?>><?=htmlspecialchars($_POST['message'])?></textarea>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2'>
				<?= auth_tag() ?>
				<?= $input_tid ?>
				<input type='submit' name="submit" value="Submit thread">
				<input type='submit' name="preview" value="Preview thread">
			</td>
		</tr>
			
		<tr>
			<td class='tdbg1 center b'>Options:</td>
			<td class='tdbg2'>
				<input type='checkbox' name="nosmilies" id="nosmilies" value="1"<?=$nosmilieschk?>><label for="nosmilies">Disable Smilies</label> -
				<input type='checkbox' name="nolayout"  id="nolayout"  value="1"<?=$nolayoutchk ?>><label for="nolayout" >Disable Layout</label> -
				<input type='checkbox' name="nohtml"    id="nohtml"    value="1"<?=$nohtmlchk   ?>><label for="nohtml"   >Disable HTML</label> 
				<?= $modoptions ?> | 
				<?=mood_list($loguser['id'], $_POST['moodid'])?>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Save in:</td>
			<td class='tdbg2'>
				<?= pm_folder_select('folder', $loguser['id'], $_POST['folder']) ?>
			</td>
		</tr>
		<?=quikattach($attach_key, $loguser['id'], $loguser, ATTACH_REQ_DEFAULT)?>
		</table>
		</form>
		<?=$barlinks?>
	<?php
	
	replytoolbar('msg', $smilies);

	pagefooter();
	