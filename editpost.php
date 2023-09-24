<?php
	// Stop this insanity.  Never index editpost...
	$meta['noindex'] = true;
	
	// (fat catgirl here)
	require "lib/common.php";


	
	$_GET['id'] 	= filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);
	$reply_error    = "";
	if (!$_GET['id']) // You dummy
		errorpage("No post ID specified.",'index.php', 'return to the board');
	if (!$loguser['id'])
		errorpage("You are not logged in.",'login.php', 'log in (then try again)');
	if ($loguser['editing_locked'] == 1)
		$reply_error .= "You are not allowed to edit your posts.";

	// When post editing is silently disabled (opt 2)
	if ($loguser['editing_locked'] > 1) {
		// Disable attachments and only allow read access to 'edit' and 'delete' modes 
		$config['allow-attachments'] = false;
		if ($_GET['action'] && $_GET['action'] != 'delete')
			$reply_error .= "You are not allowed to edit your posts.";
	}
	
	$post     = $sql->fetchq("SELECT * FROM posts WHERE id = {$_GET['id']}");
	if (!$post) {
		errorpage("Post ID #{$_GET['id']} doesn't exist.",'index.php', 'return to the board');
	}
	load_thread($post['thread']);
	check_forumban($forum['id'], $loguser['id']);
	$ismod = ismod($forum['id']);
	
	// Only applicable for the edit action, doesn't matter with others
	$submitted = isset($_POST['submit']) || isset($_POST['preview']);
	
	if (!$ismod) {
		if ($loguser['id'] != $post['user'])
			errorpage("This isn't one of your posts!");
		if ($thread['closed'])
			$reply_error .= "The thread is closed.<br>";
		if ($post['warned']) // no editing "evidence"
			$reply_error .= "This post has received a warning, you can't edit it.<br>";
	}
	if ($reply_error && !$submitted)
		errorpage("You are not allowed to edit this post:<br>{$reply_error}", "thread.php?pid={$_GET['id']}#{$_GET['id']}", 'return to the post');
	
	$windowtitle = htmlspecialchars($forum['title']).": ".htmlspecialchars($thread['title'])." -- ";


	
	/*
		Editing a post?
	*/
	if (!$_GET['action']) {
		$postpreview = "";
		$smilies     = readsmilies();
		$attachsel   = array();
		$attach_key  = "{$thread['id']}_{$_GET['id']}";
	
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
				$can_attach = can_use_attachments($loguser, $forum['attachmentmode']);

				if ($can_attach) {
					list($attachsel, $total) = process_attachments($attach_key, $post['user'], $_GET['id']); // Returns attachments marked for removal
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
						'moodid'	=> $moodid,
					);

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
					
					$create_rev = 
						   $post['text']     != $message 
						|| $post['headtext'] != $head || $post['headid'] != $headid 
						|| $post['signtext'] != $sign || $post['signid'] != $signid  
						|| $post['csstext']  != $css  || $post['cssid']  != $cssid;
						
					$flag_as_edited = $create_rev || ($can_attach && ($attachsel || $total));
					if ($flag_as_edited) {
						$pdata['editedby']= $loguser['id'];
						$pdata['editdate'] = time();
					}
					if ($create_rev) {
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
						confirm_attachments($attach_key, $post['user'], $_GET['id'], 0, $attachsel);
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
					$data = array(
						// Text
						'message' => $message,	
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
						// XFMod Options
						'highlighted'   => $highlighted,
						'highlighttext' => $highlighttext,
						'warned'        => $warned,
						'warntext'      => $warntext,
						// Attachments
						'attach_key' => $attach_key,
						'attach_sel' => $attachsel,
					);
					$postpreview = preview_post($post['user'], $data, PREVIEW_EDITED);
				}
			}
		} else {	
			// Replace the default variables with the original ones from the thread
			$message = $post['text'];
			list($head, $sign, $css) = getpostlayoutforedit($post);
			$nosmilies  = $post['nosmilies'];
			$nohtml     = $post['nohtml'];
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
		<form method="POST" ACTION="editpost.php?id=<?=$_GET['id']?>" enctype="multipart/form-data">
		<table class='table'>
			<tr><td class='tdbgh center' colspan='3'>Edit post</td></tr>
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
				<?=quikattach($attach_key, $post['user'], $loguser, ATTACH_REQ_DEFAULT, $post['id'], $attachsel)?>
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
		<?= $barlinks ?>
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
		$sql->query("UPDATE `posts` SET `noob` = '1' - `noob` WHERE `id` = '{$_GET['id']}'");
		die(header("Location: thread.php?pid={$_GET['id']}#{$_GET['id']}"));
		//errorpage("Post ".($post['noob'] ? "un" : "")."n00bed!", "thread.php?pid={$_GET['id']}#{$_GET['id']}",'the post',0);
	}
	else if ($ismod && $_GET['action'] == 'warn') {
		check_token($_GET['auth'], TOKEN_MGET);
		if ($post['warned'])
			$sql->query("UPDATE posts SET warned = 0 WHERE id = '{$_GET['id']}'");
		else {
			$message = filter_string($_GET['msg']); // no ui to set it yet, will stay null
			$sql->queryp("UPDATE posts SET warned = ?, warndate = ?, warntext = ? WHERE id = '{$_GET['id']}'", [1, time(), $message]);
		}
		die(header("Location: thread.php?pid={$_GET['id']}#{$_GET['id']}"));
		//errorpage("Post ".($post['warned'] ? "un" : "")."warned!", "thread.php?pid={$_GET['id']}#{$_GET['id']}",'the post',0);
	}
	else if ($ismod && $_GET['action'] == 'highlight') {
		check_token($_GET['auth'], TOKEN_MGET);
		if (!can_edit_highlight($post))
			errorpage("You aren't allowed to edit this post highlight.");
		if ($post['highlighted'])
			$sql->query("UPDATE posts SET highlighted = 0 WHERE id = '{$_GET['id']}'");
		else {
			$message = filter_string($_GET['msg']); // no ui to set it yet, will stay null
			$type = numrange(filter_int($_GET['type']), PHILI_MIN + 1, PHILI_MAX);
			$sql->queryp("UPDATE posts SET highlighted = ?, highlightdate = ?, highlighttext = ? WHERE id = '{$_GET['id']}'", [$type, time(), $message]);
		}
		die(header("Location: thread.php?pid={$_GET['id']}#{$_GET['id']}"));
		//errorpage("Post ".($post['highlighted'] ? "un" : "")."highlighted!", "thread.php?pid={$_GET['id']}#{$_GET['id']}",'the post',0);
	}
	else if ($_GET['action'] == 'delete') {
		if (confirmed($msgkey = 'delpost')) {
			if ($loguser['editing_locked']) {
				report_send(IRC_STAFF, "'{$loguser['name']}' tried to ".($post['deleted'] ? "un" : "")."delete post #{$_GET['id']}");
			} else {
				$data = [];
				if ($post['deleted']) {
					$data['deleted'] = 0;
					$data['deletedby'] = null;
					$data['deletereason'] = null;
				} else {
					$data['deleted'] = 1;
					$data['deletedby'] = $loguser['id'];
					$data['deletereason'] = filter_string($_POST['reason']);
				}
				$sql->queryp("UPDATE posts SET ".mysql::setplaceholders($data)." WHERE id = {$_GET['id']}", $data);
			}
			if ($post['deleted']) {
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for undeleting the post.","thread.php?pid={$_GET['id']}#{$_GET['id']}","return to the thread",0);
			} else {
				errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for deleting the post.","thread.php?pid={$_GET['id']}#{$_GET['id']}","return to the thread",0);
			}
		}
		
		pageheader();
		$barlinks = mklinks("Delete post"); 
		
		$form_link = "editpost.php?action=delete&id={$_GET['id']}";
		$title     = "Warning";
		if ($post['deleted']) {
			$message = "Do you want to undelete this post?";
			$btntext = "Yes";
		} else {
			$message = "
			Are you sure you want to <b>DELETE</b> this post?
			<br/>If you want to, you can specify the reason (will be publicly visible):
			<br/>
			<br/><input type='text' name='reason' maxlength='255' style='width: 400px'/>";
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
		
		confirm_message($msgkey, $message, $title, $form_link, $buttons, TOKEN_SLAMMER);
	}
	else {
		errorpage("No valid action specified.","thread.php?pid={$_GET['id']}#{$_GET['id']}","return to the post",0);
	}

	pagefooter();
	
	function mklinks($action) {
		global $forum, $thread, $post;
		$links = array(
			[$forum['title']    , "forum.php?id={$forum['id']}"],
			[$thread['title']   , "thread.php?id={$post['thread']}"],
			[$action            , NULL],
		);
		return dobreadcrumbs($links); 	
	}
	