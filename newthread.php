<?php
	require 'lib/function.php';
	
	
	$_GET['id']           = filter_int($_GET['id']);
	$_GET['poll']         = filter_int($_GET['poll']);
	
	// Stop this insanity.  Never index newthread.
	$meta['noindex'] = true;	
	const BLANK_KEY = "nk";

	// Detect all three (invalid forum, banned user, restricted forum) in one query
	$forum = $sql->fetchq("SELECT * FROM forums WHERE id = {$_GET['id']} AND {$loguser['powerlevel']} >= minpowerthread");
	if (!$forum) { // Stop right there
		if ($banned)
			errorpage("Sorry, but you are not allowed to post, because you are banned from this board.", "forum.php?id={$_GET['id']}",'return to the forum', 0);
		else 
			errorpage("Sorry, but you are not allowed to post in this restricted forum.", 'index.php' ,'return to the board', 0);
	}
	
	check_forumban($_GET['id'], $loguser['id']);
	
	$windowtitle = "{$forum['title']} -- New Thread";
	
	pageheader($windowtitle, $forum['specialscheme'], $forum['specialtitle']);
	
	$smilies = readsmilies();
	
	replytoolbar(1);


	if ($_GET['id'] == $config['trash-forum']) {
		errorpage("No. Stop that, you idiot.");
	}

	if ($forum['pollstyle'] == '-2' && $_GET['poll']) {
		errorpage("A for effort, but F for still failing.");
	}
	

	/*
		Variable initialization
	*/
	if ($_GET['poll']) {
		$_POST['chtext']    = filter_array($_POST['chtext']);   // Text for the choices
		$_POST['chcolor']   = filter_array($_POST['chcolor']);  // Choice color
		$_POST['remove']    = filter_array($_POST['remove']);   // Choices to remove from the list
		$_POST['count']     = filter_int($_POST['count']);      // Number of choices to show
		$_POST['mltvote']   = filter_int($_POST['mltvote']);    // Multivote flag (default: 0)
		$_POST['question']  = filter_string($_POST['question'], true);
		$_POST['briefing']  = filter_string($_POST['briefing'], true);
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
	
	
	$userid = $loguser['id'];	
	// Attachment preview stuff
	$input_tid  = "";
	$attach_key   = BLANK_KEY;
	
	if (isset($_POST['preview']) || isset($_POST['submit'])) {
		// check alternate login info
		$_POST['username'] = filter_string($_POST['username']);
		$_POST['password'] = filter_string($_POST['password']);
		
		if ($loguser['id'] && !$_POST['password']) {
			$user	= $loguser;
		} else {
			$userid 	= checkuser($_POST['username'],$_POST['password']);
			$user 		= $sql->fetchq("SELECT * FROM users WHERE id = '$userid'");
		}
		
		// some consistency with newreply.php
		$error = '';
		if ($userid == -1) {
			$error	= "You haven't entered your username and password correctly.";
		} else {
			check_forumban($_GET['id'], $userid);
			
			if (!$ismod) {
				$ismod = $sql->resultq("SELECT 1 FROM forummods WHERE forum = {$_GET['id']} and user = {$userid}");
			}
			
			if ($user['powerlevel'] < $forum['minpowerthread'])
				$error = "You aren't allowed to post in this forum.";
			else if (!$_POST['message'])   
				$error = "You haven't entered a message.";
			else if (!$_POST['subject'])    
				$error = "You haven't entered a subject.";
			else if ($user['lastposttime'] > (ctime()-30))
				$error	= "You are trying to post too rapidly.";	
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
		
		if ($error) {
			errorpage("Couldn't post the thread. $error", "forum.php?id={$_GET['id']}", $forum['title'], 2);
		}
		
		// All OK!
		
		if ($config['allow-attachments']) {
			$attachids   = get_attachments_key("n{$_GET['id']}", $userid); // Get the base key to identify the correct files
			$attach_id    = $attachids[0]; // Cached ID to safely reuse attach_key across requests
			$attach_key   = $attachids[1]; // String (base) key for file names
			$attach_count = process_temp_attachments($attach_key, $userid); // Process the attachments and return the post-processed total
			if ($attach_count) {
				// Some files are attached; reconfirm the key
				$input_tid = save_attachments_key($attach_id);
			} else {
				$attach_key = BLANK_KEY; // just in case
			}
			
		}
		
		// Needed for thread preview
		if ($_POST['iconid'] != '-1' && isset($posticons[$_POST['iconid']])) {
			$posticon = $posticons[$_POST['iconid']];
		} else {
			$posticon = $_POST['custposticon'];
		}
		

		//$postnum 		= $numposts;
		

		if (isset($_POST['submit'])) {
			#echo "key => {$attach_key}; count = {$attach_count}";
			#die;
			check_token($_POST['auth']);
			
			// Prepare tags / filters (CHANGEME)
			$numposts 		= $user['posts'] + 1;
			$numdays 		= (ctime() - $user['regdate']) / 86400;
			$tags			= array();
			$msg 			= doreplace($_POST['message'], $numposts, $numdays, $user['id'], $tags);
			$tagval			= json_encode($tags);
			
			if ($_POST['nolayout']) {
				$headid = 0;
				$signid = 0;
			} else {
				$headid = getpostlayoutid($user['postheader']);
				$signid = getpostlayoutid($user['signature']);
			}
			
			$currenttime 	= ctime();
			
			$sql->beginTransaction();
			
			// Process the poll data right away
			if ($_GET['poll']) {
				$sql->queryp("INSERT INTO `poll` (`question`, `briefing`, `closed`, `doublevote`) VALUES (:question, :briefing, :closed, :doublevote)",
					 [
						'question'			=> xssfilters($_POST['question']),
						'briefing'			=> xssfilters($_POST['briefing']),
						'closed'			=> 0,
						'doublevote'		=> $_POST['mltvote'],
					 ]);
				
				$pollid = $sql->insert_id();
				
				$addchoice = $sql->prepare("INSERT INTO `poll_choices` (`poll`, `choice`, `color`) VALUES (?,?,?)");
				
				for($c = 1; isset($_POST['chtext'][$c]); ++$c) {
					
					if (!$_POST['chtext'][$c] || !isset($_POST['chcolor'][$c])) {
						continue; // Just in case
					}
					$sql->execute($addchoice, array($pollid, $_POST['chtext'][$c], $_POST['chcolor'][$c]));
				}
					
			} else {
				$pollid = 0;
			}
			
			
			$sql->query("UPDATE `users` SET `posts` = posts + 1, `lastposttime` = '$currenttime' WHERE `id` = '{$user['id']}'");
			
			if (!$ismod) {
				$_POST['close'] = 0;
				$_POST['stick'] = 0;
				$_POST['tannc']   = 0;
			}
			
			// Insert thread
			$vals = [
				'forum'				=> $_GET['id'],
				'user'				=> $user['id'],
				
				'closed'			=> $_POST['close'],
				'sticky'			=> $_POST['stick'],
				'announcement'		=> $_POST['tannc'],
				
				'poll'				=> $pollid,
				
				'title'				=> xssfilters($_POST['subject']),
				'description'		=> xssfilters($_POST['description']),
				'icon'				=> $posticon,
				
				'views'				=> 0,
				'replies'			=> 0,
				'firstpostdate'		=> $currenttime,
				'lastpostdate'		=> $currenttime,
				'lastposter'		=> $user['id'],
			];
			
			$sql->queryp("INSERT INTO `threads` SET ".mysql::setplaceholders($vals), $vals);
			
			$tid = $sql->insert_id();
			
			// Insert post
			$vals = [
				'thread'			=> $tid,
				'user'				=> $user['id'],
				'date'				=> $currenttime,
				'ip'				=> $_SERVER['REMOTE_ADDR'],
				'num'				=> $numposts,
				
				'headid'			=> $headid,
				'signid'			=> $signid,
				'moodid'			=> $_POST['moodid'],
				
				'text'				=> xssfilters($msg),
				'tagval'			=> $tagval,
				'options'			=> $_POST['nosmilies'] . "|" . $_POST['nohtml'],
			 ];
			$sql->queryp("INSERT INTO `posts` SET ".mysql::setplaceholders($vals), $vals);
			
			$pid = $sql->insert_id();
			
			if ($config['allow-attachments']) {
				save_attachments($attach_key, $userid, $pid);
			}
			
			$sql->query("
				UPDATE `forums` SET
					`numthreads`   = `numthreads` + 1,
					`numposts`     = `numposts` + 1,
					`lastpostdate` = '$currenttime',
					`lastpostuser` = '$userid',
					`lastpostid`   = '$pid'
				WHERE id = {$_GET['id']}");

			
			
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
		$mltsel[$_POST['mltvote']] = 'checked';
	}
	
	$nosmilieschk 	= $_POST['nosmilies'] 	? " checked" : "";
	$nohtmlchk	 	= $_POST['nohtml'] 		? " checked" : "";
	$nolayoutchk 	= $_POST['nolayout'] 	? " checked" : "";

	$selsticky = $_POST['stick'] ? "checked" : "";
	$selclosed = $_POST['close'] ? "checked" : "";
	$seltannc  = $_POST['tannc'] ? "checked" : "";
	
	$links = array(
		$forum['title']  => "forum.php?id={$_GET['id']}",
		"New thread"      => NULL,
	);
	$barlinks = dobreadcrumbs($links); 

	if ($loguser['id']) {
		$_POST['username'] = $loguser['name'];
		$passhint = 'Alternate Login:';
		$altloginjs = "<a href=\"#\" onclick=\"document.getElementById('altlogin').style.cssText=''; this.style.cssText='display:none'\">Use an alternate login</a>
			<span id=\"altlogin\" style=\"display:none\">";
	} else {
		$_POST['username'] = '';
		$passhint = 'Login Info:';
		$altloginjs = "<span>";
	}
	
	// Mixing _GET and _POST is bad. Put all _GET arguments here rather than sending them as hidden form values.
	$formlink = "newthread.php?id={$_GET['id']}";
	if ($_GET['poll']) $formlink .= "&poll=1";
	
	
	

	if (isset($_POST['preview'])) {
		
		if ($posticon)
			$iconpreview = "<img src=\"".htmlspecialchars($posticon)."\" height=15 align=absmiddle>";
	
		$pollpreview = "";
		
		if ($_GET['poll']) {
			// Print out poll options
			$pchoices = "";
			for($c = 1; ($_POST['chtext'][$c]); ++$c) {
				
				if (!$_POST['chtext'][$c]) continue;
				// Just in case
				if (!isset($_POST['chtext'][$c])) $_POST['chtext'][$c] = "red";
				
				$pchoices .= 
					"<tr><td class='tdbg1' width=20%>".htmlspecialchars($_POST['chtext'][$c])."</td>".
					"<td class='tdbg2' width=60%><table cellpadding=0 cellspacing=0 width=50% bgcolor='{$_POST['chcolor'][$c]}'><td>&nbsp</table></td>".
					"<td class='tdbg1 center' width=20%><font> ? votes, ??.?%</tr>";
			}
			
			$mlt = ($_POST['mltvote'] ? 'enabled' : 'disabled');
			
			$pollpreview = 
				"<table class='table'>
				<tr>
					<td colspan=3 class='tbl tdbgc center'>
						<b>".htmlspecialchars($_POST['question'])."</b>
					</td>
				<tr>
					<td class='tdbg2 fonts' colspan=3>
						".xssfilters($_POST['briefing'])."
					</td>
				</tr>
				$pchoices
				<tr>
					<td class='tdbg2 fonts' colspan=3>
						Multi-voting is $mlt.
					</td>
				</tr>						
				</table>
				<br>";
			
		}
		
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
		
		$threadtype = ($_GET['poll'] ? 'poll' : 'thread');

			?>
	<table class='table'>
		<tr>
			<td class='tdbgh center'>
				<?=($_GET['poll'] ? 'Poll' : 'Thread')." preview"?>
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
		
	$modoptions	= "";
	
	if ($ismod) {
		
		$selsticky = $_POST['stick'] ? "checked" : "";
		$selclosed = $_POST['close'] ? "checked" : "";
		$seltannc  = $_POST['tannc']   ? "checked" : "";
		
		
		$modoptions = 
		"<tr>
			<td class='tdbg1 center'>
				<b>Moderator Options:</b>
			</td>
			<td class='tdbg2' colspan=2>
				<input type='checkbox' name='close' id='close' value=1 $selclosed><label for='close'>Close</label> -
				<input type='checkbox' name='stick' id='stick' value=1 $selsticky><label for='stick'>Sticky</label> - 
				<input type='checkbox' name='tannc' id='tannc' value=1 $seltannc ><label for='tannc'>Forum announcement</label>
			</td>
		</tr>";
	}		
	
?>

	<?=$barlink?>
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
						<b>Username:</b> <input type='text' name=username VALUE="<?=htmlspecialchars($_POST['username'])?>" SIZE=25 MAXLENGTH=25 autocomplete=off>

						<!-- Hack around autocomplete, fake inputs (don't use these in the file) -->
						<input style="display:none;" type="text"     name="__f__usernm__">
						<input style="display:none;" type="password" name="__f__passwd__">

						<b>Password:</b> <input type='password' name=password SIZE=13 MAXLENGTH=64 autocomplete=off>
					</span>
				</td>
			</tr>
			
		<?php
			
	/*
		New thread
	*/
	if (!$_GET['poll']) {
			
		?>
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
				<td class='tdbg1 center b'>Post:</td>
				<td class='tdbg2' style='width: 800px' valign=top>
					<?=replytoolbar(2)?>
					<textarea wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" <?=filter_string($autofocus[1])?>><?=htmlspecialchars($_POST['message'])?></textarea>
				</td>
				<td class='tdbg2' width=*>
					<?=mood_layout(0, $userid, $_POST['moodid'])?>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'>&nbsp;</td>
				<td class='tdbg2' colspan=2>
					<input type='hidden' name=action VALUE=postthread>
					<?= auth_tag() ?>
					<?= $input_tid ?>
					<input type='submit' class=submit name=submit VALUE="Submit thread">
					<input type='submit' class=submit name=preview VALUE="Preview thread">
				</td>
			</tr>
		<?php
	}
		
	/*
		New poll
	*/
	else {
			
		?>
			<tr>
				<td class='tdbg1 center b'>Poll icon:</td>
				<td class='tdbg2' colspan=2>
					<?=dothreadiconlist($_POST['iconid'], $_POST['custposticon'])?>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Poll title:</td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=subject SIZE=40 MAXLENGTH=100 VALUE="<?=htmlspecialchars($_POST['subject'])?>" <?=filter_string($autofocus[0])?>>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Poll description:</td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=description SIZE=100 MAXLENGTH=120 VALUE="<?=htmlspecialchars($_POST['description'])?>">
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Question:</td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=question SIZE=100 MAXLENGTH=120 VALUE="<?=htmlspecialchars($_POST['question'])?>">
				</td>
			</tr>			
			<tr>
				<td class='tdbg1 center b'>Briefing:</td>
				<td class='tdbg2' colspan=2>
					<textarea wrap=virtual name=briefing ROWS=2 COLS=<?=$numcols?> style="resize:vertical;"><?=htmlspecialchars($_POST['briefing'])?></TEXTAREA>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Multi-voting:</td>
				<td class='tdbg2' colspan=2>
					<input type=radio class='radio' name=mltvote value=0 <?=filter_string($mltsel[0])?>> Disabled &nbsp;&nbsp;
					<input type=radio class='radio' name=mltvote value=1 <?=filter_string($mltsel[1])?>> Enabled
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center b'>Choices:</td>
				<td class='tdbg2' colspan=2>
					<?=$choices?>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Post:</td>
				<td class='tdbg2' style='width: 800px' valign=top>
					<?=replytoolbar(2)?>
					<textarea wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"  <?=filter_string($autofocus[1])?>><?=htmlspecialchars($_POST['message'])?></textarea>
				</td>
				<td class='tdbg2' width=*>
					<?=mood_layout(0, $userid, $_POST['moodid'])?>
				</td>
			</tr>

			<tr>
				<td class='tdbg1 center'>&nbsp;</td><td class='tdbg2' colspan=2>
					<input type='hidden' name=action VALUE=postthread>
					<?= auth_tag() ?>
					<?= $input_tid ?>
					<input type='submit' class=submit name=submit VALUE="Submit poll">
					<input type='submit' class=submit name=preview VALUE="Preview poll">
				</td>
			</tr>
		<?php
	}
		
		
		?>
			<tr>
				<td class='tdbg1 center b'>Options:</td>
				<td class='tdbg2' colspan=2>
					<input type='checkbox' name="nosmilies" id="nosmilies" value="1"<?=$nosmilieschk?>><label for="nosmilies">Disable Smilies</label> -
					<input type='checkbox' name="nolayout"  id="nolayout"  value="1"<?=$nolayoutchk?> ><label for="nolayout" >Disable Layout</label> -
					<input type='checkbox' name="nohtml"    id="nohtml"    value="1"<?=$nohtmlchk?>   ><label for="nohtml"   >Disable HTML</label> | 
					<?=mood_layout(1, $userid, $_POST['moodid'])?>
				</td>
			</tr>
			<?=$modoptions?>
		<?=quikattach($attach_key, $userid)?>
		</table>
		</form>
		<?=$barlink?>
		<?=replytoolbar(4)?>
		<?php
	pagefooter();
