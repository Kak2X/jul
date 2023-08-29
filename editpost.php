<?php
	// (fat catgirl here)
	require "lib/common.php";

	// Stop this insanity.  Never index editpost...
	$meta['noindex'] = true;
	
	$_GET['id'] 	= filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);

	
	if (!$loguser['id']) {
		errorpage("You are not logged in.",'login.php', 'log in (then try again)');
	}
	if ($loguser['editing_locked'] == 1) {
		errorpage("You are not allowed to edit your posts.", 'index.php', 'return to the board');
	}
	if (!$_GET['id']) {	// You dummy
		errorpage("No post ID specified.",'index.php', 'return to the board');
	}

	$post     = $sql->fetchq("SELECT * FROM posts WHERE id = {$_GET['id']}");
	if (!$post) {
		errorpage("Post ID #{$_GET['id']} doesn't exist.",'index.php', 'return to the board');
	}
	load_thread($post['thread']);
	check_forumban($forum['id'], $loguser['id']);
	$ismod = ismod($forum['id']);
	if ($forum_error) {
		$forum_error = "<table class='table'>{$forum_error}</table>";
	}
	
	if (!$ismod && ($loguser['id'] != $post['user'] || $thread['closed']))
		errorpage("You are not allowed to edit this post.", "thread.php?pid={$_GET['id']}#{$_GET['id']}", 'return to the post');
	
	// When post editing is silently disabled (opt 2)
	if ($loguser['editing_locked']) {
		// Disable attachments and only allow read access to 'edit' and 'delete' modes 
		$config['allow-attachments'] = false;
		if ($_GET['action'] && $_GET['action'] != 'delete')
			errorpage("You are not allowed to edit your posts.", 'index.php', 'return to the board');
	}
	
	$windowtitle = htmlspecialchars($forum['title']).": ".htmlspecialchars($thread['title'])." -- Editing Post";
	pageheader($windowtitle, $forum['specialscheme'], $forum['specialtitle']);
	
	$links = array(
		[$forum['title']    , "forum.php?id={$forum['id']}"],
		[$thread['title']   , "thread.php?id={$post['thread']}"],
		["Edit post"        , NULL],
	);
	$barlinks = dobreadcrumbs($links); 

	
	/*
		Editing a post?
	*/
	if (!$_GET['action']) {
		$smilies    = readsmilies();
		$attachsel  = array();
		$attach_key = "{$thread['id']}_{$_GET['id']}";
	
		if (isset($_POST['submit']) || isset($_POST['preview'])) {
			
			$can_attach = can_use_attachments($loguser, $forum['attachmentmode']);
			
			$message 	= filter_string($_POST['message']);
			$head 		= filter_string($_POST['head']);
			$sign 		= filter_string($_POST['sign']);
			$css 		= filter_string($_POST['css']);
			
			$nosmilies	= filter_int($_POST['nosmilies']);
			$nohtml		= filter_int($_POST['nohtml']);
			$moodid		= filter_int($_POST['moodid']);

			if ($can_attach) {
				$attachsel = process_attachments($attach_key, $loguser['id'], $_GET['id']); // Returns attachments marked for removal
			}
			
			if (isset($_POST['submit'])) {
				
				// :^)
				if ($loguser['editing_locked']) {
					report_send(IRC_STAFF, "'{$loguser['name']}' tried to edit post #{$_GET['id']}");
					errorpage("Post edited successfully.", "thread.php?pid={$_GET['id']}#{$_GET['id']}", 'return to the thread', 0);
				}
				
				check_token($_POST['auth']);
				
				$gtopt = array(
					'mood' => $moodid,
				);
				$message 	= replace_tags($message, get_tags($loguser, $gtopt));
				$edited 	= getuserlink($loguser);
				
				/*
				if ($loguserid == 1162) {
					report_send(IRC_STAFF, "The jceggbert5 dipshit tried to edit another post: ". $_GET['id']);
					errorpage("");
				}
				
				if (($message == "COCKS" || $head == "COCKS" || $sign == "COCKS") || ($message == $head && $head == $sign)) {
					$sql->query("INSERT INTO `ipbans` SET `reason` = 'Idiot hack attempt', `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."'");
					errorpage("NO BONUS");
				}
				*/				
				// Check if we have already stored this layout, so we won't have to duplicate it
				if ($headid = getpostlayoutid($head, false)) $head = "";
				if ($signid = getpostlayoutid($sign, false)) $sign = "";
				if ($cssid  = getpostlayoutid($css,  false)) $css  = "";
					
				$sql->beginTransaction();
				
				// Post update data which does not trigger a new revision
				$pdata = array(
					'nosmilies' => $nosmilies,
					'nohtml'    => $nohtml,
					'edited'	=> $edited,
					'editdate' 	=> time(),
					'moodid'	=> $moodid,
				);
				
				if (
					   $post['text']     != $message 
					|| $post['headtext'] != $head || $post['headid'] != $headid 
					|| $post['signtext'] != $sign || $post['signid'] != $signid  
					|| $post['csstext']  != $css  || $post['cssid']  != $cssid
				) {
					
					// Old revisions are stored in their own containment area, and not in the same table
					$save = array(
						'pid'      => $_GET['id'],
						'revdate'  => $post['date'],   // Revision dated
						'revuser'  => ($post['revision'] > 1 ? $loguser['id'] : $post['user']), // Revision edited by
						'text'     => $post['text'],
						'headtext' => $post['headtext'],
						'signtext' => $post['signtext'],
						'csstext'  => $post['csstext'],
						'headid'   => $post['headid'],
						'signid'   => $post['signid'],
						'cssid'    => $post['cssid'],
						'revision' => $post['revision'],
					);
					$sql->queryp("INSERT INTO posts_old SET ".mysql::setplaceholders($save), $save);
					// The post update query now updates these as well
					$pdata['text']     = $message;
					$pdata['headtext'] = $head;
					$pdata['signtext'] = $sign;
					$pdata['csstext']  = $css;
					$pdata['headid']   = $headid;
					$pdata['signid']   = $signid;
					$pdata['cssid']    = $cssid;
					$pdata['revision'] = $post['revision'] + 1;
				}
				
				$sql->queryp("UPDATE posts SET ".mysql::setplaceholders($pdata)." WHERE id = {$_GET['id']}", $pdata);
				$sql->commit();
				
				if ($can_attach) {
					confirm_attachments($attach_key, $loguser['id'], $_GET['id'], 0, $attachsel);
				}
				
				report_post("Post edited", $forum, [
					'user'      => $loguser['name'],
					'thread'	=> $thread['title'],
					'pid'		=> $_GET['id'],
				]);
				
				errorpage("Post edited successfully.", "thread.php?pid={$_GET['id']}#{$_GET['id']}", 'return to the thread', 0);
				
			} else {
				/*
					Edit preview
				*/
				$preview_msg = $message;
				if ($can_attach) {
					$preview_msg = replace_attachment_temp_tags($attach_key, $loguser['id'], $preview_msg);
				}
				
				$data = array(
					// Text
					'message' => $preview_msg,	
					'head'    => $head,
					'sign'    => $sign,
					'css'     => $css,
					// Post metadata
					'id'      => $post['id'],
					'forum'   => $thread['forum'],
					'ip'      => $post['ip'],
					'num'     => $post['num'],
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
			
			$nosmilies  = $post['nosmilies'];
			$nohtml     = $post['nohtml'];
			$moodid		= $post['moodid'];
			//$user=$sql->fetchq("SELECT name FROM users WHERE id=$post[user]");		
			sbr(1, $head);
			sbr(1, $sign);
		}
		


		$selsmilies = $nosmilies ? "checked" : "";
		$selhtml    = $nohtml    ? "checked" : "";	
		
		?>
		
		<?= $barlinks . $forum_error ?>
		<form method="POST" ACTION="editpost.php?id=<?=$_GET['id']?>" enctype="multipart/form-data">
		<table class='table'>
			<tr><td class='tdbgh center' colspan='3'>Edit post</td></tr>
			<tr>
				<td class='tdbg1 center b' style='width: 150px'>Post:</td>
				<td class='tdbg2' id="msgtd"  style='width: 800px' valign="top">
					<textarea id="msgtxt" wrap=virtual name=message ROWS=12 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" autofocus><?=htmlspecialchars($message)?></textarea>
				</td>
				<td class='tdbg2'>
					<?=mood_layout(0, $post['user'], $moodid)?>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'>&nbsp;</td>
				<td class='tdbg2' colspan='2'>
					<?= auth_tag() ?>
					<input type='submit' class=submit name=submit VALUE="Edit post">
					<input type='submit' class=submit name=preview VALUE="Preview post">
				</td>
			</tr>	
			<tr>
				<td class='tdbg1 center b'>Options:</td>
				<td class='tdbg2' colspan='2'>
					<input type='checkbox' name="nosmilies" id="nosmilies" value="1" <?=$selsmilies?>><label for="nosmilies">Disable Smilies</label> -
					<input type='checkbox' name="nohtml"    id="nohtml"    value="1" <?=$selhtml   ?>><label for="nohtml">Disable HTML</label> | 
					<?=mood_layout(1, $post['user'], $moodid)?>
				</td>
				<?=quikattach($attach_key, $post['user'], $loguser, ATTACH_REQ_DEFAULT, $post['id'], $attachsel)?>
			</tr>
			<tr><td class='tdbgh center' colspan='3'>Edit layout specific to this post</td></tr>
			<tr>
				<td class='tdbg1 center b'>CSS:</td>
				<td class='tdbg2' valign='top' colspan='2'>
					<textarea wrap=virtual name=css ROWS=8 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=escape_html($css)?></textarea>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Header:</td>
				<td class='tdbg2' id="headtd" valign='top' colspan='2'>
					<textarea id="headtxt" wrap=virtual name=head ROWS=8 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=escape_html($head)?></textarea>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Signature:</td>
				<td class='tdbg2' id="signtd" valign='top' colspan='2'>
					<textarea id="signtxt" wrap=virtual name=sign ROWS=8 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=escape_html($sign)?></textarea>
				</td>
			</tr>
		</table>
		</form>
		<?= $barlinks ?>
		<?php
		
		replytoolbar('msg', $smilies);
		replytoolbar('head', $smilies);
		replytoolbar('sign', $smilies);
	}
	else if ($ismod && $_GET['action'] == 'noob') {
		check_token($_GET['auth'], TOKEN_MGET);
		$sql->query("UPDATE `posts` SET `noob` = '1' - `noob` WHERE `id` = '{$_GET['id']}'");
		errorpage("Post ".($post['noob'] ? "un" : "")."n00bed!", "thread.php?pid={$_GET['id']}#{$_GET['id']}",'the post',0);
	}
	else if ($_GET['action'] == 'delete') {
		if (confirmed($msgkey = 'delpost')) {
			if ($loguser['editing_locked']) {
				report_send(IRC_STAFF, "'{$loguser['name']}' tried to ".($post['deleted'] ? "un" : "")."delete post #{$_GET['id']}");
			} else {
				$sql->query("UPDATE posts SET deleted = 1 - deleted WHERE id = {$_GET['id']}");
			}
			if ($post['deleted']) {
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for undeleting the post.","thread.php?pid={$_GET['id']}#{$_GET['id']}","return to the thread",0);
			} else {
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for deleting the post.","thread.php?pid={$_GET['id']}#{$_GET['id']}","return to the thread",0);
			}
		}
		
		$form_link = "editpost.php?action=delete&id={$_GET['id']}";
		$title     = "Warning";
		if ($post['deleted']) {
			$message = "Do you want to undelete this post?";
			$btntext = "Yes";
		} else {
			$message = "Are you sure you want to <b>DELETE</b> this post?";
			$btntext = "Delete post";
		}
		$buttons = array(
			[BTN_SUBMIT, $btntext],
			[BTN_URL   , "Cancel", "thread.php?pid={$_GET['id']}#{$_GET['id']}"]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	else if ($_GET['action'] == 'erase' && $sysadmin && $config['allow-post-deletion']){
		
		$pcount  = $sql->resultq("SELECT COUNT(*) FROM posts WHERE thread = {$thread['id']}");
		if (confirmed($msgkey = 'erase-post', TOKEN_SLAMMER)) {
			$sql->beginTransaction();
			$sql->query("DELETE FROM posts WHERE id = {$_GET['id']}");
			$sql->query("DELETE FROM posts_old WHERE pid = {$_GET['id']}");
			
			if ($pcount <= 1) {
				// We have deleted the last remaining post from a thread
				$sql->query("DELETE FROM threads WHERE id = {$thread['id']}");
				// Update forum status
				$t1 = $sql->fetchq("SELECT lastpostdate, lastposter	FROM threads WHERE forum = {$thread['forum']} ORDER BY lastpostdate DESC LIMIT 1");
				$sql->queryp("UPDATE forums SET numposts=numposts-1,numthreads=numthreads-1,lastpostdate=?,lastpostuser=? WHERE id={$thread['forum']}", [filter_int($t1['lastpostdate']),filter_int($t1['lastposter'])]);
			} else {
				$p = $sql->fetchq("SELECT id,user,date FROM posts WHERE thread = {$thread['id']} ORDER BY date DESC");
				$sql->query("UPDATE threads SET replies=replies-1, lastposter={$p['user']}, lastpostdate={$p['date']} WHERE id={$thread['id']}");
				$sql->query("UPDATE forums SET numposts=numposts-1 WHERE id={$forum['id']}");
			}
			if ($config['allow-attachments']) {
				$list = $sql->getresults("SELECT id FROM attachments WHERE post = {$_GET['id']}");
				remove_attachments($list, $_GET['id']);
			}
			$sql->commit();
			
			if ($pcount <= 1) {
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for deleting the post and the thread.","forum.php?id={$thread['forum']}","return to the forum",0);
			} else {
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for deleting the post.","thread.php?id={$thread['id']}","return to the thread",0);
			}
		}
		
		$title     = "Warning";
		$message   = "Are you sure you want to <b>permanently DELETE</b> this post from the database?";
		if ($pcount <= 1) {
			$message .= "<br><span class='fonts'>You are trying to delete the last post in the thread. If you continue, the thread will be <i>deleted</i> as well.</span>";
		}
		$form_link = "editpost.php?action=erase&id={$_GET['id']}";
		$buttons   = array(
			[BTN_SUBMIT, "Delete post"],
			[BTN_URL   , "Cancel", "thread.php?pid={$_GET['id']}#{$_GET['id']}"]
		);
		
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	else {
		errorpage("No valid action specified.","thread.php?pid={$_GET['id']}#{$_GET['id']}","return to the post",0);
	}

	pagefooter();
	