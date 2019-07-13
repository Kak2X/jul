<?php

	require 'lib/function.php';

	$meta['noindex'] = true;
	
	if (!$loguser['id'])
		errorpage("You are not logged in.",'login.php', 'log in (then try again)');
	if ((!$isadmin && !$config['allow-pmthread-edit']) || $loguser['editing_locked'])
		errorpage("You are not allowed to edit your posts.", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}", 'return to the post');
	if (!$_GET['id'])
		errorpage("No post ID specified.",'index.php', 'return to the board');
	
	$_GET['id']     = filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);

	$post     = $sql->fetchq("SELECT * FROM pm_posts WHERE id = {$_GET['id']}");
	if (!$post) {
		errorpage("Post ID #{$_GET['id']} doesn't exist.",'index.php', 'return to the board');
	}
	
	load_pm_thread($post['thread']);
	if (!$isadmin && $post['user'] != $loguser['id']) {
		errorpage("You are not allowed to edit this post.", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}", 'the post');
	}
	pageheader("Private Messages: ".htmlspecialchars($thread['title'])." -- Editing Post");
	
	/*
		Editing a post?
	*/
	if (!$_GET['action']) {
		
		$smilies    = readsmilies();
		$attachsel  = array();
		$attach_key = "pm{$post['thread']}_{$_GET['id']}";
		
		if (isset($_POST['submit']) || isset($_POST['preview'])) {
			
			$can_attach = can_use_attachments($loguser);
			
			$message 	= filter_string($_POST['message']);
			$head 		= filter_string($_POST['head']);
			$sign 		= filter_string($_POST['sign']);
			$css 		= filter_string($_POST['css']);
			$nosmilies	= filter_int($_POST['nosmilies']);
			$nohtml		= filter_int($_POST['nohtml']);
			$moodid		= filter_int($_POST['moodid']);
			
			if ($can_attach) {
				$attachsel = process_attachments($attach_key, $loguser['id'], $_GET['id'], ATTACH_PM); // Returns attachments marked for removal
			}
			
			if (isset($_POST['submit'])) {
				check_token($_POST['auth']);
				
				$numdays 	= (ctime() - $loguser['regdate']) / 86400;
				$message 	= doreplace($message,$loguser['posts'],$numdays,$loguser['id']);
				$edited 	= getuserlink($loguser);
				

				if ($headid = getpostlayoutid($head, false)) $head = "";
				if ($signid = getpostlayoutid($sign, false)) $sign = "";
				if ($cssid  = getpostlayoutid($css,  false)) $css  = "";

				
				$data = [
					'text'		=> xssfilters($message),
					'headtext'	=> xssfilters($head),
					'signtext'	=> xssfilters($sign),
					'csstext'	=> xssfilters($css),
					
					
					'options'	=> $nosmilies . "|" . $nohtml,
					'edited'	=> $edited,
					'editdate' 	=> ctime(),
					
					'headid'	=> $headid,
					'signid'	=> $signid,
					'cssid'		=> $cssid,
					'moodid'	=> $moodid,		
				];
				$sql->beginTransaction();
				$sql->queryp("UPDATE pm_posts SET ".mysql::setplaceholders($data)." WHERE id = {$_GET['id']}", $data);
				$sql->commit();
				if ($can_attach) {
					confirm_attachments($attach_key, $loguser['id'], $_GET['id'], ATTACH_PM, $attachsel);
				}
				errorpage("Post edited successfully.", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}", 'return to the thread', 0);
				
			} else {
				/*
					Edit preview
				*/
				$data = array(
					// Text
					'message' => $message,	
					'head'    => $head,
					'sign'    => $sign,
					'css'     => $css,
					// Post metadata
					'id'      => $post['id'],
					'forum'   => -1,
					'ip'      => $post['ip'],
					//'num'     => $post['num'],
					'date'    => $post['date'],
					// (mod) Options
					'nosmilies' => $nosmilies,
					'nohtml'    => $nohtml,
					'nolayout'  => 0,
					'moodid'    => $moodid,
					'noob'      => $post['noob'],
					// Attachments
					'attach_key' => $attach_key,
					'attach_sel' => $attachsel,
					'attach_pm'  => true, // temp measure probably
				);
				print preview_post($loguser, $data, PREVIEW_EDITED);
			}
			
		} else {
			// Replace the default variables with the original ones from the thread

			$message = $post['text'];
			
			if(!$post['headid']) $head = $post['headtext'];
			else $head = $sql->resultq("SELECT text FROM postlayouts WHERE id = {$post['headid']}");
			if(!$post['signid']) $sign = $post['signtext'];
			else $sign = $sql->resultq("SELECT text FROM postlayouts WHERE id = {$post['signid']}");
			if(!$post['cssid'])  $css = $post['csstext'];
			else $css  = $sql->resultq("SELECT text FROM postlayouts WHERE id = {$post['cssid']}");
			
			$options    = explode("|", $post['options']);
			$nosmilies 	= $options[0];
			$nohtml		= $options[1];
			$moodid		= $post['moodid'];
			
			sbr(1, $head);
			sbr(1, $sign);
		}

		$selsmilies = $nosmilies ? "checked" : "";
		$selhtml    = $nohtml    ? "checked" : "";	
		
		$links = array(
			["Private messages" , "private.php"],
			[$thread['title']   , "showprivate.php?pid={$_GET['id']}#{$_GET['id']}"],
			["Edit post"        , NULL],
		);
		$barlinks = dobreadcrumbs($links); 
		
		if ($forum_error) {
			$forum_error = "<br><table class='table'>{$forum_error}</table>";
		}

		?>
		<?= $barlinks . $forum_error ?>
		<form method="POST" ACTION="?id=<?=$_GET['id']?>" enctype="multipart/form-data">
		<table class='table'>
			<tr>
				<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
				<td class='tdbgh center' colspan=2>&nbsp;</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>CSS:</td>
				<td class='tdbg2' style='width: 800px' valign=top>
					<textarea wrap=virtual name=css ROWS=8 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($css)?></textarea>
				</td>
				<td class='tdbg2' width=* rowspan=4>
					<?=mood_layout(0, $post['user'], $moodid)?>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Header:</td>
				<td class='tdbg2' id="headtd" style='width: 800px' valign=top>
					<textarea id="headtxt" wrap=virtual name=head ROWS=8 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($head)?></textarea>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Post:</td>
				<td class='tdbg2' id="msgtd"  style='width: 800px' valign=top>
					<textarea id="msgtxt" wrap=virtual name=message ROWS=12 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" autofocus><?=htmlspecialchars($message)?></textarea>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Signature:</td>
				<td class='tdbg2' id="signtd" style='width: 800px' valign=top>
					<textarea id="signtxt" wrap=virtual name=sign ROWS=8 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($sign)?></textarea>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'>&nbsp;</td>
				<td class='tdbg2' colspan=2>
					<?= auth_tag() ?>
					<input type='submit' class=submit name=submit VALUE="Edit post">
					<input type='submit' class=submit name=preview VALUE="Preview post">
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Options:</td>
				<td class='tdbg2' colspan=2>
					<input type='checkbox' name="nosmilies" id="nosmilies" value="1" <?=$selsmilies?>><label for="nosmilies">Disable Smilies</label> -
					<input type='checkbox' name="nohtml"    id="nohtml"    value="1" <?=$selhtml   ?>><label for="nohtml">Disable HTML</label> | 
					<?=mood_layout(1, $post['user'], $moodid)?>
				</td>
				<?=quikattach($attach_key, $post['user'], $loguser, ATTACH_REQ_DEFAULT, $post['id'], $attachsel, 'pm')?>
			</tr>
		</table>
		</form>
		<?=$barlinks?>
		<?php
		
		replytoolbar('msg', $smilies);
		replytoolbar('head', $smilies);
		replytoolbar('sign', $smilies);
	}
	else if ($isadmin && $_GET['action'] == 'noob') {
		check_token($_GET['auth'], TOKEN_MGET);
		$sql->query("UPDATE `pm_posts` SET `noob` = '1' - `noob` WHERE `id` = '{$_GET['id']}'");
		errorpage("Post ".($post['noob'] ? "un" : "")."n00bed!", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}",'the post',0);
	}
  
	else if ($_GET['action'] == 'delete'){
		if ($post['deleted']) {
			$message = "Do you want to undelete this post?";
			$btntext = "Yes";
		} else {
			$message = "Are you sure you want to <b>DELETE</b> this post?";
			$btntext = "Delete post";
		}
		$form_link = "?action=delete&id={$_GET['id']}";
		$buttons       = array(
			0 => [$btntext],
			1 => ["Cancel", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}"]
		);
		
		if (confirmpage($message, $form_link, $buttons)) {
			$sql->query("UPDATE pm_posts SET deleted = 1 - deleted WHERE id = {$_GET['id']}");
			if ($post['deleted']) {
				errorpage("Thank you, {$loguser['name']}, for undeleting the post.","showprivate.php?pid={$_GET['id']}#{$_GET['id']}","return to the thread",0);
			} else {
				errorpage("Thank you, {$loguser['name']}, for deleting the post.","showprivate.php?pid={$_GET['id']}#{$_GET['id']}","return to the thread",0);
			}
		}
	}
	else if ($_GET['action'] == 'erase' && $sysadmin && $config['allow-post-deletion']){
		
		$pcount  = $sql->resultq("SELECT COUNT(*) FROM pm_posts WHERE thread = {$post['thread']}");
		$message = "Are you sure you want to <b>permanently DELETE</b> this post from the database?";
		if ($pcount <= 1) {
			$message .= "<br><span class='fonts'>You are trying to delete the last post in the thread. If you continue, the thread will be <i>deleted</i> as well.</span>";
		}
		$form_link = "?action=erase&id={$_GET['id']}";
		$buttons       = array(
			0 => ["Delete post"],
			1 => ["Cancel", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}"]
		);
		
		if (confirmpage($message, $form_link, $buttons, TOKEN_SLAMMER)) {
			$sql->beginTransaction();
			$sql->query("DELETE FROM pm_posts WHERE id = {$_GET['id']}");
			
			
			if ($pcount <= 1) {
				// We have deleted the last remaining post from a thread
				$sql->query("DELETE FROM pm_threads WHERE id = {$post['thread']}");
				$sql->query("DELETE FROM pm_access WHERE thread = {$post['thread']}");
				$sql->query("DELETE FROM pm_threadsread WHERE tid = {$post['thread']}");
			} else {
				$p = $sql->fetchq("SELECT id,user,date FROM pm_posts WHERE thread = {$post['thread']} ORDER BY date DESC");
				$sql->query("UPDATE pm_threads SET replies=replies-1, lastposter={$p['user']}, lastpostdate={$p['date']} WHERE id={$post['thread']}");
			}
			
			if ($config['allow-attachments']) {
				$list = $sql->getresults("SELECT id FROM attachments WHERE pm = {$_GET['id']}");
				remove_attachments($list, $_GET['id']);
			}
			$sql->commit();
			if ($pcount <= 1) {
				errorpage("Thank you, {$loguser['name']}, for deleting the post and the thread.","private.php","return to the private message box",0);
			} else {
				errorpage("Thank you, {$loguser['name']}, for deleting the post.","showprivate.php?id={$post['thread']}","return to the thread",0);
			}
		}
	}
	else {
		errorpage("No valid action specified.","showprivate.php?id={$post['thread']}#{$post['thread']}","return to the post",0);
	}

	pagefooter();
	