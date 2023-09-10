<?php
	$meta['noindex'] = true;
	
	require "lib/common.php";

	$_GET['id']     = filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);
	$reply_error    = "";
	if (!$loguser['id'])
		errorpage("You are not logged in.",'login.php', 'log in (then try again)');
	if (!$_GET['id'])
		errorpage("No post ID specified.",'index.php', 'return to the board');
	if ((!$isadmin && !$config['allow-pmthread-edit']) || $loguser['editing_locked'])
		$reply_error .= "You are not allowed to edit your posts.";

	$post     = $sql->fetchq("SELECT * FROM pm_posts WHERE id = {$_GET['id']}");
	if (!$post) {
		errorpage("Post ID #{$_GET['id']} doesn't exist.",'index.php', 'return to the board');
	}
	
	load_pm_thread($post['thread']);
	
	// Only applicable for the edit action, doesn't matter with others
	$submitted = isset($_POST['submit']) || isset($_POST['preview']);
	
	if (!$isadmin) {
		if ($loguser['id'] != $post['user'])
			errorpage("This isn't one of your posts!");
		if ($thread['closed'])
			$reply_error .= "The thread is closed.<br>";
		if ($post['warned']) // no editing "evidence"
			$reply_error .= "This post has received a warning, you can't edit it.<br>";
	}
	if ($reply_error && !$submitted)
		errorpage("You are not allowed to edit this post:<br>{$reply_error}", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}", 'return to the post');
	
	$windowtitle = "Private Messages: ".htmlspecialchars($thread['title'])." -- ";
	
	/*
		Editing a post?
	*/
	if (!$_GET['action']) {
		$postpreview = "";
		$smilies     = readsmilies();
		$attachsel   = array();
		$attach_key  = "pm{$post['thread']}_{$_GET['id']}";
		
		if ($submitted) {

			$message 	= filter_string($_POST['message']);
			$head 		= filter_string($_POST['head']);
			$sign 		= filter_string($_POST['sign']);
			$css 		= filter_string($_POST['css']);
			$nosmilies	= filter_int($_POST['nosmilies']);
			$nohtml		= filter_int($_POST['nohtml']);
			$moodid		= filter_int($_POST['moodid']);
			
			//--
			if ($ismod) {			
				$warned			= numrange(filter_int($_POST['warned']), PWARN_MIN, PWARN_MAX);
				$warntext		= filter_string($_POST['warntext']);
				$highlighted	= numrange(filter_int($_POST['highlighted']), PHILI_MIN, PHILI_MAX);
				$highlighttext	= filter_string($_POST['highlighttext']);
			} else {
				$warned			= $post['warned'];
				$warntext		= $post['warntext'];
				$highlighted	= $post['highlighted'];
				$highlighttext	= $post['highlighttext'];
			}
			//--
			
			if (!$reply_error) {
				if (!trim($_POST['message']))
					$reply_error .= "You didn't enter anything in the post.<br>";
				if ($ismod) {
					if (!can_edit_highlight($post, $highlighted, $highlighttext))
						$reply_error .= "You aren't allowed to change this featured content.<br>";
					if ($highlighted == PHILI_SUPER && !trim($highlighttext))
						$reply_error .= "Featured posts must contain an highlight text.<br>";
				}
			}
			
			if (!$reply_error) {
				$can_attach = can_use_attachments($loguser);
				
				if ($can_attach) {
					list($attachsel, $total) = process_attachments($attach_key, $post['user'], $_GET['id'], ATTACH_PM); // Returns attachments marked for removal
				}
				
				if (isset($_POST['submit'])) {
					check_token($_POST['auth']);
					
					if (!trim($_POST['message']))
						errorpage("You didn't enter anything in the post.");
					if ($ismod) {
						if (!can_edit_highlight($post, $highlighted, $highlighttext))
							errorpage("You aren't allowed to change this featured content.");
						if ($post['highlighted'] == PHILI_SUPER && !trim($highlighttext))
							errorpage("Featured posts must contain an highlight text.");
					}
					
					$gtopt = array(
						'mood' => $moodid,
					);
					$message 	= replace_tags($message, get_tags($loguser, $gtopt));
					$edited 	= getuserlink($loguser);
					

					if ($headid = getpostlayoutid($head, false)) $head = "";
					if ($signid = getpostlayoutid($sign, false)) $sign = "";
					if ($cssid  = getpostlayoutid($css,  false)) $css  = "";

					
					$pdata = [
						'text'		=> $message,
						'headtext'	=> $head,
						'signtext'	=> $sign,
						'csstext'	=> $css,
						
						'nosmilies' => $nosmilies,
						'nohtml'	=> $nohtml,
						
						'headid'	=> $headid,
						'signid'	=> $signid,
						'cssid'		=> $cssid,
						'moodid'	=> $moodid,		
					];
					
					// Copied from editpost, which actually used post revisions
					$create_rev = 
						   $post['text']     != $message 
						|| $post['headtext'] != $head || $post['headid'] != $headid 
						|| $post['signtext'] != $sign || $post['signid'] != $signid  
						|| $post['csstext']  != $css  || $post['cssid']  != $cssid;
						
					$flag_as_edited = $create_rev || ($can_attach && ($attachsel || $total));
					if ($flag_as_edited) {
						// This now only gets updated when a revision is added
						$pdata['edited']	    = $edited;
						$pdata['editdate'] 	= time();
					}
					
					//--
					if ($ismod) {
						$pdata['warned'] = $warned;
						$pdata['warntext'] = $warntext;
						// Only update the date when switching 
						if (!$post['warned'] && $warned != $post['warned'])
							$pdata['warndate'] = time();
						
						$pdata['highlighted'] = $highlighted;
						$pdata['highlighttext'] = $highlighttext;
						// Only update the date when going from nohighlight->highlight.
						if (!$post['highlighted'] && $highlighted != $post['highlighted'])
							$pdata['highlightdate'] = time();
					}
					//--
					$sql->beginTransaction();
					$sql->queryp("UPDATE pm_posts SET ".mysql::setplaceholders($pdata)." WHERE id = {$_GET['id']}", $pdata);
					$sql->commit();
					if ($can_attach) {
						confirm_attachments($attach_key, $post['user'], $_GET['id'], ATTACH_PM, $attachsel);
					}
					errorpage("Post edited successfully.", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}", 'return to the thread', 0);
					
				} else {
					/*
						Edit preview
					*/
					$preview_msg = $message;
					if ($can_attach) {
						$preview_msg = replace_attachment_temp_tags($attach_key, $post['user'], $preview_msg);
					}
					
					$data = array(
						// Text
						'message' => $preview_msg,	
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
						// XFMod Options
						'highlighted'   => $highlighted,
						'highlighttext' => $highlighttext,
						'warned'        => $warned,
						'warntext'      => $warntext,
						// Attachments
						'attach_key' => $attach_key,
						'attach_sel' => $attachsel,
						'attach_pm'  => true, // temp measure probably
					);
					$postpreview = preview_post($post['user'], $data, PREVIEW_EDITED);
				}
			}
		} else {
			// Replace the default variables with the original ones from the thread
			$message = $post['text'];
			list($head, $sign, $css) = getpostlayoutforedit($post);
			$nosmilies 	= $post['nosmilies'];
			$nohtml		= $post['nohtml'];
			$moodid		= $post['moodid'];
			
			$warned			= $post['warned'];
			$warntext		= $post['warntext'];
			$highlighted	= $post['highlighted'];
			$highlighttext	= $post['highlighttext'];
		}

		$selsmilies = $nosmilies ? "checked" : "";
		$selhtml    = $nohtml    ? "checked" : "";	
		$hreadonly = !can_edit_highlight($post);
		
		pageheader($windowtitle."Editing Post");
		
		if ($forum_error)
			$forum_error = "<table class='table'>{$forum_error}</table>";
		
		$barlinks = mklinks("Edit post"); 
		
		print $barlinks . $forum_error;
		// In case something happened, show a message *over the reply box*, to allow fixing anything important.
		if ($reply_error) {
			boardmessage("Couldn't preview or submit the reply. One or more errors occurred:<br><br>".$reply_error, "Error", false);
		}
		print "<br>".$postpreview;
		?>
		<form method="POST" ACTION="?id=<?=$_GET['id']?>" enctype="multipart/form-data">
		<table class='table'>
			<tr><td class='tdbgh center' colspan='2'>Edit post</td></tr>
			<tr>
				<td class='tdbg1 center b avatar-preview-parent'>
					Post:
					<?=mood_preview()?>
				</td>
				<td class='tdbg2 vatop' id="msgtd">
					<textarea id="msgtxt" name="message" rows="12" autofocus><?=htmlspecialchars($message)?></textarea>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'>&nbsp;</td>
				<td class='tdbg2'>
					<?= auth_tag() ?>
					<input type='submit' name="submit" VALUE="Edit post">
					<input type='submit' name="preview" VALUE="Preview post">
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Options:</td>
				<td class='tdbg2'>
					<input type='checkbox' name="nosmilies" id="nosmilies" value="1" <?=$selsmilies?>><label for="nosmilies">Disable Smilies</label> -
					<input type='checkbox' name="nohtml"    id="nohtml"    value="1" <?=$selhtml   ?>><label for="nohtml">Disable HTML</label> | 
					<?=mood_list($post['user'], $moodid)?>
				</td>
				<?=quikattach($attach_key, $post['user'], $loguser, ATTACH_REQ_DEFAULT, $post['id'], $attachsel, 'pm')?>
			</tr>
<?php if ($ismod) { ?>
			<tr><td class="tdbgh center" colspan="2">Moderator options</td></tr>
			<tr>
				<td class="tdbg1 center b" rowspan="2">Warning:</td>
				<td class="tdbg2" valign="top">
					Type: <?= input_html("warned", $warned, ['input' => 'select', 'options' => [PWARN_NONE => "None", PWARN_WARN => "Warned", PWARN_WARNREAD => "Warned (read)"]]) ?>
				</td>
			</tr>
			<tr>
				<td class="tdbg2 vatop" id="warntd">
					<textarea id="warntxt" name="warntext"><?=escape_html($warntext)?></textarea>
				</td>
			</tr>
			<tr>
				<td class="tdbg1 center b" rowspan="2">Highlight:</td>
				<td class="tdbg2" valign="top">
					Type: <?= highlight_type_select("highlighted", $highlighted, $hreadonly) ?>
				</td>
			</tr>
			<tr>
				<td class="tdbg2 vatop" id="hilitd">
					<textarea id="hilitxt" name="highlighttext" <?=($hreadonly ? "readonly" : "")?>><?=escape_html($highlighttext)?></textarea>
				</td>
			</tr>
<?php } ?>
			<tr><td class='tdbgh center' colspan='2'>Edit layout specific to this post</td></tr>
			<tr>
				<td class='tdbg1 center b'>CSS:</td>
				<td class='tdbg2 vatop'>
					<textarea name="css" rows="8"><?=escape_html($css)?></textarea>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Header:</td>
				<td class='tdbg2 vatop' id="headtd">
					<textarea id="headtxt" name="head" rows="8"><?=escape_html($head)?></textarea>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Signature:</td>
				<td class='tdbg2 vatop' id="signtd">
					<textarea id="signtxt" name="sign" rows="8"><?=escape_html($sign)?></textarea>
				</td>
			</tr>
		</table>
		</form>
		<?=$barlinks?>
		<?php
		
		replytoolbar('msg', $smilies);
		replytoolbar('head', $smilies);
		replytoolbar('sign', $smilies);
		if ($ismod) {
			replytoolbar('warn', $smilies);
			if (!$hreadonly)
				replytoolbar('hili', $smilies);
		}
	}
	else if ($ismod && $_GET['action'] == 'noob') {
		check_token($_GET['auth'], TOKEN_MGET);
		$sql->query("UPDATE `pm_posts` SET `noob` = '1' - `noob` WHERE `id` = '{$_GET['id']}'");
		die(header("Location: showprivate.php?pid={$_GET['id']}#{$_GET['id']}"));
		//errorpage("Post ".($post['noob'] ? "un" : "")."n00bed!", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}",'the post',0);
	}
	else if ($ismod && $_GET['action'] == 'warn') {
		check_token($_GET['auth'], TOKEN_MGET);
		if ($post['warned'])
			$sql->query("UPDATE pm_posts SET warned = 0 WHERE id = '{$_GET['id']}'");
		else {
			$message = filter_string($_GET['msg']); // no ui to set it yet, will stay null
			$sql->queryp("UPDATE pm_posts SET warned = ?, warndate = ?, warntext = ? WHERE id = '{$_GET['id']}'", [1, time(), $message]);
		}
		die(header("Location: showprivate.php?pid={$_GET['id']}#{$_GET['id']}"));
		//errorpage("Post ".($post['warned'] ? "un" : "")."warned!", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}",'the post',0);
	}
	else if ($ismod && $_GET['action'] == 'highlight') {
		check_token($_GET['auth'], TOKEN_MGET);
		if (!can_edit_highlight($post))
			errorpage("You aren't allowed to edit this post highlight.");
		if ($post['highlighted'])
			$sql->query("UPDATE pm_posts SET highlighted = 0 WHERE id = '{$_GET['id']}'");
		else {
			$message = filter_string($_GET['msg']); // no ui to set it yet, will stay null
			$type = numrange(filter_int($_GET['type']), PHILI_MIN + 1, PHILI_MAX);
			$sql->queryp("UPDATE pm_posts SET highlighted = ?, highlightdate = ?, highlighttext = ? WHERE id = '{$_GET['id']}'", [$type, time(), $message]);
		}
		die(header("Location: showprivate.php?pid={$_GET['id']}#{$_GET['id']}"));
		//errorpage("Post ".($post['highlighted'] ? "un" : "")."highlighted!", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}",'the post',0);
	}
	else if ($_GET['action'] == 'delete') {
		if (confirmed($msgkey = 'delete')) {
			$sql->query("UPDATE pm_posts SET deleted = 1 - deleted WHERE id = {$_GET['id']}");
			if ($post['deleted']) {
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for undeleting the post.","showprivate.php?pid={$_GET['id']}#{$_GET['id']}","return to the thread",0);
			} else {
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for deleting the post.","showprivate.php?pid={$_GET['id']}#{$_GET['id']}","return to the thread",0);
			}
		}
		
		pageheader($windowtitle."Deleting Post");
		$barlinks = mklinks("Delete post");
		
		if ($post['deleted']) {
			$message = "Do you want to undelete this post?";
			$btntext = "Yes";
		} else {
			$message = "Are you sure you want to <b>DELETE</b> this post?";
			$btntext = "Delete post";
		}
		$title     = "Warning";
		$form_link = "?action=delete&id={$_GET['id']}";
		$buttons   = array(
			[BTN_SUBMIT, $btntext],
			[BTN_URL   , "Cancel", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}"]
		);
		
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	else if ($_GET['action'] == 'erase' && $sysadmin && $config['allow-post-deletion']){
		
		$pcount  = $sql->resultq("SELECT COUNT(*) FROM pm_posts WHERE thread = {$post['thread']}");
		if (confirmed($msgkey = 'erase')) {
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
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for deleting the post and the thread.","private.php","return to the private message box",0);
			} else {
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for deleting the post.","showprivate.php?id={$post['thread']}","return to the thread",0);
			}
		}
		pageheader($windowtitle."Erasing Post");
		$barlinks = mklinks("Erase post");
		
		$title   = "Permanent deletion";
		$message = "Are you sure you want to <b>permanently DELETE</b> this post from the database?";
		if ($pcount <= 1) {
			$message .= "<br><span class='fonts'>You are trying to delete the last post in the thread. If you continue, the thread will be <i>deleted</i> as well.</span>";
		}
		$form_link = "?action=erase&id={$_GET['id']}";
		$buttons       = array(
			[BTN_SUBMIT, "Delete post"],
			[BTN_URL   , "Cancel", "showprivate.php?pid={$_GET['id']}#{$_GET['id']}"]
		);
		
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	else {
		errorpage("No valid action specified.","showprivate.php?id={$post['thread']}#{$post['thread']}","return to the post",0);
	}

	pagefooter();
	
	function mklinks($action) {
		global $thread;
		$links = array(
			["Private messages" , "private.php"],
			[$thread['title']   , "showprivate.php?pid={$_GET['id']}#{$_GET['id']}"],
			[$action            , NULL],
		);
		return dobreadcrumbs($links); 	
	}