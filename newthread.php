<?php

	// Just in case, allow caching to return safely without losing anything.
	$meta['cache'] = true;
	// Stop this insanity.  Never index newthread.
	$meta['noindex'] = true;
	
	require "lib/common.php";
	
	$_GET['id']           = filter_int($_GET['id']);
	$_GET['poll']         = filter_int($_GET['poll']);
	$submitted            = isset($_POST['submit']) || isset($_POST['preview']);
	
	load_forum($_GET['id']);
	check_forumban($_GET['id'], $loguser['id']);
	
	$windowtitle = htmlspecialchars($forum['title'])." -- New Thread";

	
	$smilies = readsmilies();
	
	$reply_error = "";
	if (isset($forum['error'])) // for admins
		$reply_error = "You cannot post new threads in invalid forums.<br>";
	else if ($banned || $loguser['powerlevel'] < $forum['minpowerthread'])
		$reply_error = "You aren't allowed to post new threads in this forum.<br>";
	else if ($_GET['id'] == $config['trash-forum'])
		$reply_error = "No. Stop that, you idiot.<br>";
	else if ($forum['pollstyle'] == '-2' && $_GET['poll'])
		$reply_error = "A for effort, but F for still failing.<br>";
	// Error out immediately if we didn't submit anything
	if ($reply_error && !$submitted)
		errorpage($reply_error);

	/*
		Variable initialization
	*/
	$_POST['username'] 	= filter_string($_POST['username']);
	$_POST['password'] 	= filter_string($_POST['password']);
	
	if ($_GET['poll']) {
		$_POST['chtext']     = filter_array($_POST['chtext']);   // Text for the choices
		$_POST['chcolor']    = filter_array($_POST['chcolor']);  // Choice color
		$_POST['remove']     = filter_array($_POST['remove']);   // Choices to remove from the list
		$_POST['count']      = filter_int($_POST['count']);      // Number of choices to show
		$_POST['doublevote'] = filter_int($_POST['doublevote']); // Multivote flag (default: 0)
		$_POST['question']   = filter_string($_POST['question']);
		$_POST['briefing']   = filter_string($_POST['briefing']);
	}
	$_POST['iconid'] 		= (isset($_POST['iconid']) ? (int) $_POST['iconid'] : -1); // 'None' should be the default value
	$_POST['custposticon'] 	= filter_string($_POST['custposticon']);
	
	$_POST['subject']       = filter_string($_POST['subject']);
	$_POST['description']   = filter_string($_POST['description']);
	$_POST['message']       = filter_string($_POST['message']);
	
	$_POST['moodid']        = filter_int($_POST['moodid']);
	$_POST['nosmilies']     = filter_int($_POST['nosmilies']);
	$_POST['nohtml']        = filter_int($_POST['nohtml']);
	$_POST['nolayout']      = filter_int($_POST['nolayout']);
	
	$_POST['stick'] = filter_int($_POST['stick']);
	$_POST['close'] = filter_int($_POST['close']);
	$_POST['tannc'] = (int) (filter_int($_POST['tannc']) || isset($_GET['a']));
	
	$error          = false;
	$login_error    = "";
	$postpreview    = "";
	$posticons      = file('posticons.dat');
	$iconpreview    = "";
	$userid         = $loguser['id'];
	$user           = $loguser;
	// Attachment preview stuff
	$input_tid      = "";
	$attach_key     = "";
	
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
		
		// some consistency with newreply.php
		if (!$login_error && !$reply_error) {
			if ($userid != $loguser['id']) {
				check_forumban($forum['id'], $userid);
				$ismod = ismod($forum['id'], $user);
				if ($user['powerlevel'] < $forum['minpowerreply']) // or banned
					$reply_error .= "You aren't allowed to post in this forum.<br>";
			} 
			if (!$reply_error) {
				if (!$_POST['message'])   
					$reply_error .= "You haven't entered a message.<br>";
				if (!$_POST['subject'])    
					$reply_error .= "You haven't entered a subject.<br>";
				if ($user['lastposttime'] > (time()-30))
					$reply_error .= "You are trying to post too rapidly.<br>";	
			}
		}
		
		/*// ---
		// lol i'm eminem
		if (strpos($_POST['message'] , '[Verse ') !== FALSE) {
			$error = "You aren't allowed to post in this forum.";
			$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Listen to some good music for a change.'");
			if ($userid != -1) //if ($_COOKIE['loguserid'] > 0)
				$sql->query("UPDATE `users` SET `powerlevel` = '-2' WHERE `id` = {$userid}");
			report_send(IRC_STAFF, xk(7) ."Auto-banned another Eminem wannabe with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) .".");
		}
		// ---*/
		
		$error = ($reply_error || $login_error);
		
		if (!$error) {
			// All OK!
			$can_attach = can_use_attachments($user, $forum['attachmentmode']);
			
			if ($can_attach) {
				$attach_key = "n{$_GET['id']}";
				$input_tid = process_attachments($attach_key, $userid, 0, ATTACH_INCKEY);
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
				if ($_GET['poll']) {
					$pollid = create_poll($_POST['question'], $_POST['briefing'], $_POST['chtext'], $_POST['chcolor'], $_POST['doublevote']) or throw new Exception("Failed to create poll.");
				} else {
					$pollid = 0;
				}
				
				if (!$ismod) {
					$_POST['close'] = 0;
					$_POST['stick'] = 0;
					$_POST['tannc'] = 0;
				}
				
				// Create thread
				$treq = new create_thread_req();
				$treq->vals = [
					// Base fields
					'title'             => $_POST['subject'],
					'description'       => $_POST['description'],
					'icon'              => $posticon,
					'forum'             => $forum['id'],
					'user'              => $user,
					// Flags
					'closed'            => $_POST['close'],
					'sticky'            => $_POST['stick'],
					'announcement'      => $_POST['tannc'],
					// Thread type
					'poll'              => $pollid,
				];
				// Additional fields
				hook_use('thread-create-fields', $treq);
				$treq->id = create_thread($treq) or throw new Exception("Failed to create thread.");
				
				// Create post
				$preq = new create_post_req();
				$preq->forum = $forum['id'];
				$preq->nolayout = $_POST['nolayout'];
				$preq->vals = array(
					// Base fields
					'thread'        => $treq->id,
					'user'          => $user,
					'ip'            => $_SERVER['REMOTE_ADDR'],
					'text'          => $_POST['message'],
					// Opt
					'moodid'        => $_POST['moodid'],
					// Flags
					'nosmilies'     => $_POST['nosmilies'],
					'nohtml'        => $_POST['nohtml'],
				);
				hook_use('thread-post-create-fields', $preq);
				$preq->id = create_post($preq) or throw new Exception("Failed to create post.");
				
				hook_use('thread-create-precommit', $treq, $preq, $pollid);
				
				// Goes after precommit since it touches files, so nothing bad happens if an extension fails
				if ($can_attach) {
					confirm_attachments($attach_key, $userid, $preq->id);
				}
				
				$sql->commit();
				
				$whatisthis = $_GET['poll'] ? "Poll" : "Thread";
				report_post("New ".strtolower($whatisthis), $forum, [
					'user'      => $user['name'],
					'thread'	=> $_POST['subject'],
					'pid'		=> $preq->id,
				]);
				
				errorpage("$whatisthis posted successfully!", "thread.php?id={$treq->id}", $_POST['subject'], 0);
				
			}
		}	
	}
	
	/*
		Main page below
	*/
	
	if ($_GET['poll']) {
		$c = 1;	// Choice (appareance)
		$d = 0; // Choice ID in array
		$choices = "";
		// Don't bother if the array is empty (ie: poll not previewed yet)
		if ($_POST['chtext']) {
			
			while (filter_string($_POST['chtext'][$c+$d]) || $c < $_POST['count']) {	// Allow a lower choice count to cut off the remainder of the choices
				
				if (isset($_POST['remove'][$c+$d])) // Count the choices and skip what's removed
					$d++;
				else {
					$choices .= "Choice $c: <input type='text' name=chtext[$c] SIZE=30 MAXLENGTH=255 VALUE=\"".htmlspecialchars($_POST['chtext'][$c+$d])."\"> &nbsp; ".
								"Color: <input type='text' name=chcolor[$c] SIZE=7 MAXLENGTH=25 VALUE=\"".htmlspecialchars(filter_string($_POST['chcolor'][$c+$d]))."\"> &nbsp; ".
								"<input type=checkbox name=remove[$c] value=1> Remove<br>";
					$c++;
				}
			}
		}
		
		$choices .= "Choice $c: <input type='text' name=chtext[$c] SIZE=30 MAXLENGTH=255> &nbsp ".
					"Color: <input type='text' name=chcolor[$c] SIZE=7 MAXLENGTH=25><br>".
					"<input type='submit' name=paction VALUE=\"Submit changes\"> and show ".
					"<input type='text' name=count size=4 maxlength=2 VALUE=\"".htmlspecialchars($_POST['count'] ? $_POST['count'] : $c)."\"> options";
		
		// Multivote selection
		$seldouble[$_POST['doublevote']] = 'checked';
	}
	
	$nosmilieschk 	= $_POST['nosmilies'] 	? " checked" : "";
	$nohtmlchk	 	= $_POST['nohtml'] 		? " checked" : "";
	$nolayoutchk 	= $_POST['nolayout'] 	? " checked" : "";
	if ($ismod) {
		$selsticky  = $_POST['stick'] ? "checked" : "";
		$selclosed  = $_POST['close'] ? "checked" : "";
		$seltannc   = $_POST['tannc'] ? "checked" : "";
	}

	if ($loguser['id']) {
		$_POST['username'] = $loguser['name'];
		$passhint = 'Alternate Login:';
		$altloginjs = !$login_error ? "<a href=\"#\" onclick=\"document.getElementById('altlogin').style.cssText=''; this.style.cssText='display:none'\">Use an alternate login</a>
			<span id=\"altlogin\" style=\"display:none\">" : "<span>"; // Always show in case of error
	} else {
		//$_POST['username'] = '';
		$passhint = 'Login Info:';
		$altloginjs = "<span>";
	}
	
	// Mixing _GET and _POST is bad. Put all _GET arguments here rather than sending them as hidden form values.
	$formlink = "newthread.php?id={$_GET['id']}";
	if ($_GET['poll']) {
		$formlink .= "&poll=1";
		//--
		$threadtype = "Poll";
	} else {
		$threadtype = "Thread";
	}

	if (isset($_POST['preview']) && !$error) {
		
		if ($posticon)
			$iconpreview = "<img src=\"".escape_attribute($posticon)."\" height=15 align=absmiddle>";
	
		// Preview a poll always in normal style
		$pollpreview = $_GET['poll'] ? preview_poll($_POST, $_GET['id']) : "";
		
		$preview_msg = $_POST['message'];
		if ($can_attach) {
			$preview_msg = replace_attachment_temp_tags($attach_key, $userid, $preview_msg);
		}
		
		// Threadpost
		$data = array(
			// Text
			'message' => $preview_msg,	
			#'head'    => "",
			#'sign'    => "",
			// Post metadata
			#'id'    => 0,
			'forum' => $_GET['id'],
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
	<table class='table'>
		<tr>
			<td class='tdbgh center'>
			{$threadtype} preview
			</td>
		</tr>
	</table>
	{$pollpreview}
	<table class='table'>
		<tr>
			<td class='tdbg2 center' style='width: 4%'>
				{$iconpreview}
			</td>
			<td class='tdbg1'>
				<b>".htmlspecialchars($_POST['subject'])."</b>
				<span class='fonts'><br>".htmlspecialchars($_POST['description'])."</span>
			</td>
		</tr>
	</table>
	".preview_post($user, $data, PREVIEW_NEW, NULL);	
			$autofocus[1] = 'autofocus'; // for 'message'
		} else {
			$postpreview = "";
			$autofocus[0] = 'autofocus'; // for 'subject'
		}

	pageheader($windowtitle);
		
	if ($forum_error)
		$forum_error = "<table class='table'>{$forum_error}</table>";
	
	$links = array(
		[$forum['title']  , "forum.php?id={$_GET['id']}"],
		["New thread"     , NULL],
	);
	$barlinks = dobreadcrumbs($links); 
	
	print $barlinks . $forum_error . $postpreview;
	// In case something happened, show a message *over the reply box*, to allow fixing anything important.
	if ($reply_error) {
		boardmessage("Couldn't preview or submit the thread. One or more errors occurred:<br><br>".$reply_error, "Error", false);
	}
	print "<br>";
?>

	<form method="POST" action="<?=$formlink?>" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
		<tr><td class='tdbgh center' colspan='2'>New thread</td></tr>	
		<tr>
			<tr>
				<td class='tdbg1 center b'><?=$passhint?></td>
				<td class='tdbg2'>
					<?=$altloginjs?>
						<!-- Hack around autocomplete, fake inputs (don't use these in the file) -->
						<input style="display:none;" type="text"     name="__f__usernm__">
						<input style="display:none;" type="password" name="__f__passwd__">
						<b>Username:</b> <input type='text' name=username VALUE="<?=htmlspecialchars($_POST['username'])?>" SIZE=25 MAXLENGTH=25 autocomplete=off>
						<b>Password:</b> <input type='password' VALUE="<?=htmlspecialchars($_POST['password'])?>" name=password SIZE=13 MAXLENGTH=64 autocomplete=off>
						<?= $login_error ?>
					</span>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'><?= $threadtype ?> icon:</td>
				<td class='tdbg2'>
					<?=dothreadiconlist($_POST['iconid'], $_POST['custposticon'])?>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'><?= $threadtype ?> title:</td>
				<td class='tdbg2'>
					<input type='text' name="subject" size="40" maxlength="100" value="<?=escape_attribute($_POST['subject'])?>" <?=filter_string($autofocus[0])?>>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'><?= $threadtype ?> description:</td>
				<td class='tdbg2'>
					<input type='text' name="description" size="100" maxlength="120" value="<?=escape_attribute($_POST['description'])?>">
				</td>
			</tr>
<?php if ($_GET['poll']) { ?>
			<tr>
				<td class='tdbg1 center b'>Question:</td>
				<td class='tdbg2'>
					<input type='text' name="question" size="100" maxlength="120" value="<?=escape_attribute($_POST['question'])?>">
				</td>
			</tr>			
			<tr>
				<td class='tdbg1 center b'>Briefing:</td>
				<td class='tdbg2' id="brieftd">
					<textarea id="brieftxt" name="briefing" rows="2"><?=escape_html($_POST['briefing'])?></textarea>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Multi-voting:</td>
				<td class='tdbg2'>
					<input type="radio" name="doublevote" value=0 <?=filter_string($seldouble[0])?>> Disabled &nbsp;&nbsp;
					<input type="radio" name="doublevote" value=1 <?=filter_string($seldouble[1])?>> Enabled
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Choices:</td>
				<td class='tdbg2'>
					<?=$choices?>
				</td>
			</tr>
<?php	} ?>
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
					<input type='submit' name="submit" value="Submit <?= lcfirst($threadtype) ?>">
					<input type='submit' name="preview" value="Preview <?= lcfirst($threadtype) ?>">
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
<?php if ($ismod) { ?>
			<tr>
				<td class='tdbg1 center b'>Moderator Options:</td>
				<td class='tdbg2'>
					<input type='checkbox' name='close' id='close' value="1" <?=$selclosed?>><label for='close'>Close</label> -
					<input type='checkbox' name='stick' id='stick' value="1" <?=$selsticky?>><label for='stick'>Sticky</label> - 
					<input type='checkbox' name='tannc' id='tannc' value="1" <?=$seltannc ?>><label for='tannc'>Forum announcement</label>
					<?= hook_print('thread-mod-opt') ?>
				</td>
			</tr>
<?php } ?>

			<?= quikattach($attach_key, $userid, $user, $forum['attachmentmode']) ?>
		</table>
		</form>
		<?= $barlinks ?>
<?php

	replytoolbar('msg', $smilies);
	if ($_GET['poll'])
		replytoolbar('brief', $smilies);

	pagefooter();
