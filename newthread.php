<?php

	// Just in case, allow caching to return safely without losing anything.
	$meta['cache'] = true;
	
	require 'lib/function.php';
	
	
	$_GET['id']           = filter_int($_GET['id']);
	$_GET['poll']         = filter_int($_GET['poll']);
	
	// Stop this insanity.  Never index newthread.
	$meta['noindex'] = true;	

	load_forum($_GET['id']);
	check_forumban($_GET['id'], $loguser['id']);
	
	$windowtitle = "{$forum['title']} -- New Thread";
	pageheader($windowtitle, $forum['specialscheme'], $forum['specialtitle']);
	
	$smilies = readsmilies();
	
	if (isset($forum['error']))
		errorpage("You cannot post new threads in invalid forums.");
	if ($banned || $loguser['powerlevel'] < $forum['minpowerthread'])
		errorpage("You aren't allowed to post new threads in this forum.");
	if ($_GET['id'] == $config['trash-forum'])
		errorpage("No. Stop that, you idiot.");
	if ($forum['pollstyle'] == '-2' && $_GET['poll'])
		errorpage("A for effort, but F for still failing.");
	
	if ($forum_error) {
		$forum_error = "<table class='table'>{$forum_error}</table>";
	}

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
	$posticons		= file('posticons.dat');
	$iconpreview    = "";
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
	$_POST['tfeat'] = filter_int($_POST['tfeat']);
	
	$error       = false;
	$login_error = "";
	$reply_error = "";
	$postpreview = "";
	
	$userid = $loguser['id'];
	$user   = $loguser;
	
	// Attachment preview stuff
	$input_tid  = "";
	$attach_key = "";
	
	if (isset($_POST['preview']) || isset($_POST['submit'])) {
		// check alternate login info
		$_POST['username'] = filter_string($_POST['username']);
		$_POST['password'] = filter_string($_POST['password']);
		
		$error = '';
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
		if (!$error) {
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
				if ($user['lastposttime'] > (ctime()-30))
					$reply_error .= "You are trying to post too rapidly.<br>";	
			}
		}
		
		/*// ---
		// lol i'm eminem
		if (strpos($_POST['message'] , '[Verse ') !== FALSE) {
			$error = "You aren't allowed to post in this forum.";
			$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."', `reason` = 'Listen to some good music for a change.'");
			if ($userid != -1) //if ($_COOKIE['loguserid'] > 0)
				$sql->query("UPDATE `users` SET `powerlevel` = '-2' WHERE `id` = {$userid}");
			xk_ircsend("1|". xk(7) ."Auto-banned another Eminem wannabe with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) .".");
		}
		// ---*/
		
		$error = ($reply_error || $login_error);
		//if ($error) {
		//	errorpage("Couldn't post the thread. $error", "forum.php?id={$_GET['id']}", $forum['title'], 2);
		//}
		
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
					$pollid = create_poll($_POST['question'], $_POST['briefing'], $_POST['chtext'], $_POST['chcolor'], $_POST['doublevote']);
				} else {
					$pollid = 0;
				}
				
				if (!$ismod) {
					$_POST['close'] = 0;
					$_POST['stick'] = 0;
					$_POST['tannc'] = 0;
					$_POST['tfeat'] = 0;
				}
				$tid = create_thread($user, $forum['id'], $_POST['subject'], $_POST['description'], $posticon, $pollid, $_POST['close'], $_POST['stick'], $_POST['tannc'], $_POST['tfeat']);
				$pid = create_post($user, $forum['id'], $tid, $_POST['message'], $_SERVER['REMOTE_ADDR'], $_POST['moodid'], $_POST['nosmilies'], $_POST['nohtml'], $_POST['nolayout']);
				
				if ($can_attach) {
					confirm_attachments($attach_key, $userid, $pid);
				}		
				
				$whatisthis = $_GET['poll'] ? "Poll" : "Thread";
				
				$sql->commit();
				xk_ircout(strtolower($whatisthis), $user['name'], array(
					'forum'		=> $forum['title'],
					'fid'		=> $forum['id'],
					'thread'	=> str_replace("&lt;", "<", $_POST['subject']),
					'pid'		=> $pid,
					'pow'		=> $forum['minpower'],
				));
				
				errorpage("$whatisthis posted successfully!", "thread.php?id=$tid", $_POST['subject'], 0);
				
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
								"<input type=checkbox class=radio name=remove[$c] value=1> Remove<br>";
					$c++;
				}
			}
		}
		
		$choices .= "Choice $c: <input type='text' name=chtext[$c] SIZE=30 MAXLENGTH=255> &nbsp ".
					"Color: <input type='text' name=chcolor[$c] SIZE=7 MAXLENGTH=25><br>".
					"<input type='submit' class=submit name=paction VALUE=\"Submit changes\"> and show ".
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
		$seltfeat   = $_POST['tfeat'] ? "checked" : "";
	}
	
	$links = array(
		[$forum['title']  , "forum.php?id={$_GET['id']}"],
		["New thread"     , NULL],
	);
	$barlinks = dobreadcrumbs($links); 

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
			$iconpreview = "<img src=\"".htmlspecialchars($posticon)."\" height=15 align=absmiddle>";
	
		// Preview a poll always in normal style
		$pollpreview = $_GET['poll'] ? preview_poll($_POST, $_GET['id']) : "";
		
		// Threadpost
		$data = array(
			// Text
			'message' => $_POST['message'],	
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

			?>
	<table class='table'>
		<tr>
			<td class='tdbgh center'>
				<?= $threadtype ?> preview
			</td>
		</tr>
	</table>
	<?=$pollpreview?>
	<table class='table'>
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
	<?= preview_post($user, $data, PREVIEW_NEW, NULL) ?>
		<?php
			$autofocus[1] = 'autofocus'; // for 'message'
		} else {
			$autofocus[0] = 'autofocus'; // for 'subject'
		}

		
	print $barlinks . $forum_error;
	// In case something happened, show a message *over the reply box*, to allow fixing anything important.
	if ($reply_error) {
		boardmessage("Couldn't preview or submit the thread. One or more errors occurred:<br><br>".$reply_error, "Error", false);
	}
	print "<br>";
?>

	<form method="POST" action="<?=$formlink?>" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
			<tr>
				<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
				<td class='tdbgh center' colspan=2>&nbsp;</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>
					<?=$passhint?>
				</td>
				<td class='tdbg2' colspan=2>
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
				<td class='tdbg2' colspan=2>
					<?=dothreadiconlist($_POST['iconid'], $_POST['custposticon'])?>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'><?= $threadtype ?> title:</td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=subject SIZE=40 MAXLENGTH=100 VALUE="<?=htmlspecialchars($_POST['subject'])?>" <?=filter_string($autofocus[0])?>>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'><?= $threadtype ?> description:</td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=description SIZE=100 MAXLENGTH=120 VALUE="<?=htmlspecialchars($_POST['description'])?>">
				</td>
			</tr>
<?php if ($_GET['poll']) { ?>
			<tr>
				<td class='tdbg1 center b'>Question:</td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=question SIZE=100 MAXLENGTH=120 VALUE="<?=htmlspecialchars($_POST['question'])?>">
				</td>
			</tr>			
			<tr>
				<td class='tdbg1 center b'>Briefing:</td>
				<td class='tdbg2' id="brieftd" colspan=2>
					<textarea wrap=virtual id="brieftxt" name=briefing ROWS=2 COLS=<?=$numcols?> style="resize:vertical;"><?=htmlspecialchars($_POST['briefing'])?></TEXTAREA>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Multi-voting:</td>
				<td class='tdbg2' colspan=2>
					<input type=radio class='radio' name=doublevote value=0 <?=filter_string($seldouble[0])?>> Disabled &nbsp;&nbsp;
					<input type=radio class='radio' name=doublevote value=1 <?=filter_string($seldouble[1])?>> Enabled
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Choices:</td>
				<td class='tdbg2' colspan=2>
					<?=$choices?>
				</td>
			</tr>
<?php	} ?>
			<tr>
				<td class='tdbg1 center b'>Post:</td>
				<td class='tdbg2 vatop' style='width: 800px' id='msgtd'>
					<textarea id='msgtxt' wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" <?=filter_string($autofocus[1])?>><?=htmlspecialchars($_POST['message'])?></textarea>
				</td>
				<td class='tdbg2' width=*>
					<?=mood_layout(0, $userid, $_POST['moodid'])?>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'>&nbsp;</td>
				<td class='tdbg2' colspan=2>
					<?= auth_tag() ?>
					<?= $input_tid ?>
					<input type='submit' class=submit name=submit VALUE="Submit <?= lcfirst($threadtype) ?>">
					<input type='submit' class=submit name=preview VALUE="Preview <?= lcfirst($threadtype) ?>">
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Options:</td>
				<td class='tdbg2' colspan=2>
					<input type='checkbox' name="nosmilies" id="nosmilies" value="1"<?=$nosmilieschk?>><label for="nosmilies">Disable Smilies</label> -
					<input type='checkbox' name="nolayout"  id="nolayout"  value="1"<?=$nolayoutchk ?>><label for="nolayout" >Disable Layout</label> -
					<input type='checkbox' name="nohtml"    id="nohtml"    value="1"<?=$nohtmlchk   ?>><label for="nohtml"   >Disable HTML</label> | 
					<?=mood_layout(1, $userid, $_POST['moodid'])?>
				</td>
			</tr>
<?php if ($ismod) { ?>
			<tr>
				<td class='tdbg1 center b'>Moderator Options:</td>
				<td class='tdbg2' colspan=2>
					<input type='checkbox' name='close' id='close' value="1" <?=$selclosed?>><label for='close'>Close</label> -
					<input type='checkbox' name='stick' id='stick' value="1" <?=$selsticky?>><label for='stick'>Sticky</label> - 
					<input type='checkbox' name='tannc' id='tannc' value="1" <?=$seltannc ?>><label for='tannc'>Forum announcement</label> - 
					<input type='checkbox' name='tfeat' id='tfeat' value="1" <?=$seltfeat ?>><label for='tfeat'>Featured</label>
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
