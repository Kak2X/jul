<?php

	// Just in case, allow caching to return safely without losing anything.
	$meta['cache'] = true;
	// Stop this insanity.  Never index newreply.
	$meta['noindex'] = true;
	
	require "lib/common.php";
	
	
	$_GET['id']         = filter_int($_GET['id']);
	$_GET['postid']     = filter_int($_GET['postid']);
	$submitted          = isset($_POST['submit']) || isset($_POST['preview']);
	
	load_thread($_GET['id']);
	check_forumban($forum['id'], $loguser['id']);
	$ismod = ismod($forum['id']);
	
	// load_thread takes care of view permissions, but the reply permissions still need to be checked
	$reply_error = "";
	if (!$ismod && $thread['closed']) {
		$reply_error = "The thread is closed and no more replies can be posted.<br>";
	} else if ($loguser['powerlevel'] < $forum['minpowerreply'] || $banned) {
		$reply_error = "Replying in this forum is restricted, and you are not allowed to post in this forum.<br>";
	}
	// Error out immediately if we didn't submit anything
	if ($reply_error && !$submitted)
		errorpage($reply_error, "thread.php?id={$_GET['id']}", htmlspecialchars($thread['title']), 0);
	
	$_POST['username'] 	= filter_string($_POST['username']);
	$_POST['password'] 	= filter_string($_POST['password']);
	
	$_POST['message']	= filter_string($_POST['message']);
	
	$_POST['moodid']    = filter_int($_POST['moodid']);
	$_POST['nosmilies']	= filter_int($_POST['nosmilies']);
	$_POST['nolayout']	= filter_int($_POST['nolayout']);
	$_POST['nohtml']    = filter_int($_POST['nohtml']);
	
	$_POST['stick']     = filter_int($_POST['stick']);
	$_POST['close']     = filter_int($_POST['close']);
	$_POST['tannc']     = filter_int($_POST['tannc']);
	
	$error       = false;
	$login_error = "";
	$postpreview = "";
	$attach_key  = $_GET['id'];	
	$userid      = $loguser['id'];
	$user        = $loguser;
	
	if ($submitted) {
		// Trying to post as someone else?
		if (!$loguser['id'] || $_POST['password']) {
			$userid = checkuser($_POST['username'], $_POST['password']);
			if ($userid == -1) {
				$login_error = " <strong style='color: red;'>* Invalid username or password.</strong>";
			} else {
				$user 	= load_user($userid, true);
			}
		}
		
		if (!$login_error && !$reply_error) {
			if ($userid != $loguser['id']) {
				check_forumban($forum['id'], $userid);
				$ismod = ismod($forum['id'], $user);
				if ($thread['closed'] && !$ismod)
					$reply_error .= 'The thread is closed and no more replies can be posted.<br>';
				if ($user['powerlevel'] < $forum['minpowerreply']) // or banned
					$reply_error .= 'Replying in this forum is restricted, and you are not allowed to post in this forum.<br>';
			}
			if (!trim($_POST['message']))
				$reply_error .= "You didn't enter anything in the post.<br>";
			if ($user['lastposttime'] > (time()-4))
				$reply_error .= "You are posting too fast.<br>";
		}
		
		$error = ($reply_error || $login_error);
		
		if (!$error) {
			$can_attach = can_use_attachments($user, $forum['attachmentmode']);
			
			// Process attachments removal
			if ($can_attach) {
				process_attachments($attach_key, $userid);
			}
			
			// All OK
			if (isset($_POST['submit'])) {
				check_token($_POST['auth']);
				$sql->beginTransaction();
				
				$preq = new create_post_req();
				$preq->forum = $forum['id'];
				$preq->nolayout = $_POST['nolayout'];
				$preq->vals = [
					// Base fields
					'thread'        => $thread['id'],
					'user'          => $user,
					'ip'            => $_SERVER['REMOTE_ADDR'],
					'text'          => $_POST['message'],
					// Opt
					'moodid'        => $_POST['moodid'],
					// Flags
					'nosmilies'     => $_POST['nosmilies'],
					'nohtml'        => $_POST['nohtml'],
				];
				if ($ismod) {
					$preq->threadupdate = [
						'closed'       => $_POST['close'],
						'sticky'       => $_POST['stick'],
						'announcement' => $_POST['tannc'],
					];
				}
				hook_use('post-create-fields', $preq);
				$preq->id = create_post($preq);
				hook_use('post-create-precommit', $preq);
				
				if ($can_attach) {
					confirm_attachments($attach_key, $userid, $preq->id);
				}
				$sql->commit();
				
				report_post("New reply", $forum, [
					'user'      => $user['name'],
					'thread'	=> $thread['title'],
					'pid'		=> $preq->id,
				]);
				
				return header("Location: thread.php?pid={$preq->id}#{$preq->id}");

			}
		}
		
	} else {
		// Use existing thread options
		$_POST['stick'] = $thread['sticky'];
		$_POST['close'] = $thread['closed'];
		$_POST['tannc'] = $thread['announcement'];
	}
	
	/*
		Main page
	*/
	
	$ppp      = get_ppp();	
	$smilies  = readsmilies();
	$postlist = thread_history($_GET['id'], $ppp + 1, $forum['id']);
	
	// Post preview
	if (!$error && isset($_POST['preview'])) {
		
		$preview_msg = $_POST['message'];
		if ($can_attach) {
			$preview_msg = replace_attachment_temp_tags($attach_key, $userid, $preview_msg);
		}
		
		$data = array(
			// Text
			'message' => $preview_msg,	
			#'head'    => "",
			#'sign'    => "",
			// Post metadata
			#'id'      => 0,
			'forum'   => $thread['forum'],
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
			'attach_key' => $_GET['id'],
			#'attach_sel' => "",
		);
		$postpreview = preview_post($user, $data);
	}
	
	// Login text stuff
	if ($loguser['id']) {
		$_POST['username'] = $loguser['name'];
		$passhint = 'Alternate Login:';
		$altloginjs = !$login_error ? "<a href=\"#\" onclick=\"document.getElementById('altlogin').style.cssText=''; this.style.cssText='display:none'\">Use an alternate login</a>
			<span id=\"altlogin\" style=\"display:none\">" : "<span>"; // Always show in case of error
	} else {
		$passhint = 'Login Info:';
		$altloginjs = "<span>";
	}

	/*
		Quoting something?
	*/
	if ($_GET['postid']) {
		$post = $sql->fetchq("
			SELECT user, text, thread 
			FROM posts 
			WHERE id = {$_GET['postid']} 
			  AND thread = {$_GET['id']}
			  AND (".((int) $ismod)." OR deleted = 0)
		");
		if ($post) {
			$post['text'] = str_replace('<br>','\n',$post['text']);
			$quoteuser = $sql->resultq("SELECT name FROM users WHERE id = {$post['user']}");
			$_POST['message'] = "[quote={$quoteuser}]{$post['text']}[/quote]\r\n";
			unset($post, $quoteuser);
		}
	}
	
	$modoptions	= "";
	if ($ismod) {
		$selsticky = $_POST['stick'] ? "checked" : "";
		$selclosed = $_POST['close'] ? "checked" : "";
		$seltannc  = $_POST['tannc'] ? "checked" : "";
		
		$modoptions = 
		"<tr>
			<td class='tdbg1 center'>
				<b>Moderator Options:</b>
			</td>
			<td class='tdbg2'>
				<input type='checkbox' name='close' id='close' value=1 $selclosed><label for='close'>Close</label> -
				<input type='checkbox' name='stick' id='stick' value=1 $selsticky><label for='stick'>Sticky</label> - 
				<input type='checkbox' name='tannc' id='tannc' value=1 $seltannc ><label for='tannc'>Forum announcement</label>
				".hook_print('thread-mod-opt')."
			</td>
		</tr>";
	}
	
	$nosmilieschk   = $_POST['nosmilies'] ? "checked" : "";
	$nolayoutchk    = $_POST['nolayout']  ? "checked" : "";
	$nohtmlchk      = $_POST['nohtml']    ? "checked" : "";
	
	$windowtitle = htmlspecialchars($forum['title']).": ".htmlspecialchars($thread['title'])." -- New Reply";
	pageheader($windowtitle);
		
	if ($forum_error)
		$forum_error = "<table class='table'>{$forum_error}</table>";
	
	$links = array(
		[$forum['title']  , "forum.php?id={$forum['id']}"],
		[$thread['title'] , "thread.php?id={$_GET['id']}"],
		["New reply"      , NULL],
	);
	$barlinks = dobreadcrumbs($links); 
	
	print $barlinks . $forum_error;
	// In case something happened, show a message *over the reply box*, to allow fixing anything important.
	if ($reply_error) {
		boardmessage("Couldn't preview or submit the reply. One or more errors occurred:<br><br>".$reply_error, "Error", false);
	}
	print "<br>".$postpreview;
	
	?>
	<form method="POST" action="newreply.php?id=<?=$_GET['id']?>" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
		<tr><td class='tdbgh center' colspan='2'>New reply</td></tr>	
		<tr>
			<td class='tdbg1 center b'><?=$passhint?></td>
			<td class='tdbg2'>
					<?=$altloginjs?>
					<!-- Hack around autocomplete, fake inputs (don't use these in the file) -->
					<input style="display:none;" type="text"     name="__f__usernm__">
					<input style="display:none;" type="password" name="__f__passwd__">
					<b>Username:</b> <input type='text' name=username VALUE="<?=htmlspecialchars($_POST['username'])?>" SIZE=25 MAXLENGTH=25 autocomplete=off>
					<b>Password:</b> <input type='password' name=password VALUE="<?=htmlspecialchars($_POST['password'])?>" SIZE=13 MAXLENGTH=64 autocomplete=off>
					<?= $login_error ?>
				</span>
			</td>
		</tr>
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
				<?=mood_list($userid, $_POST['moodid'])?>
			</td>
		</tr>
		<?=$modoptions?>
		<?=quikattach($_GET['id'], $userid, $user, $forum['attachmentmode'])?>
	</table>
	<br>
	<?=$postlist?>
	</form>
	<?=$barlinks?>
<?php
	
	replytoolbar('msg', $smilies);
	
	pagefooter();
