<?php
	require 'lib/function.php';
	
	
	$id 		= filter_int($_GET['id']);
	$poll 		= filter_int($_GET['poll']);
	
	
	// Stop this insanity.  Never index newthread.
	$meta['noindex'] = true;	

	// Detect all three (invalid forum, banned user, restricted forum) in one query
	$forum = $sql->fetchq("SELECT * FROM forums WHERE id = $id AND {$loguser['powerlevel']} >= minpowerthread");
	if (!$forum) { // Stop right there
		if ($banned)
			errorpage("Sorry, but you are not allowed to post, because you are banned from this board.", "forum.php?id=$id",'return to the forum', 0);
		else 
			errorpage("Sorry, but you are not allowed to post in this restricted forum.", 'index.php' ,'return to the board', 0);
	}
	
	$windowtitle = "{$config['board-name']} -- {$forum['title']} -- New Thread";
	
	pageheader($windowtitle, $forum['specialscheme'], $forum['specialtitle']);
	
	$smilies = readsmilies();
	
	replytoolbar(1);


	if ($id == $config['trash-forum']) {
		errorpage("No. Stop that, you idiot.");
	}

	if ($forum['pollstyle'] == '-2' && $poll) {
		errorpage("A for effort, but F for still failing.");
	}
	

	/*
		Variable initialization (global)
	*/
	if ($poll) {
		
		// register_globals!
		$chtext 	= filter_array($_POST['chtext']);	// Text for the choices
		$chcolor 	= filter_array($_POST['chcolor']);	// Choice color
		$remove 	= filter_array($_POST['remove']);	// Choices to remove from the list
		$count 		= filter_int($_POST['count']);		// Number of choices to show
		$mltvote 	= filter_int($_POST['mltvote']);	// Multivote flag (default: 0)
		
		$question	= filter_string($_POST['question'], true);
		$briefing	= filter_string($_POST['briefing'], true);
		
	}
	
	$posticons		= file('posticons.dat');
	$iconid 		= (isset($_POST['iconid']) ? (int) $_POST['iconid'] : -1); // 'None' should be the default value
	$custposticon 	= filter_string($_POST['custposticon']);


	// Determine the correct icon URL

	
	// register_globals!!
	$subject		= filter_string($_POST['subject'], true);
	$description 	= filter_string($_POST['description'], true);
	$message 		= filter_string($_POST['message'], true);
	
	$moodid			= filter_int($_POST['moodid']);
	
	$nosmilies		= filter_int($_POST['nosmilies']);
	$nohtml			= filter_int($_POST['nohtml']);
	$nolayout		= filter_int($_POST['nolayout']);
	
	$iconpreview    = "";
	
	// Attachment preview stuff
	$input_tid  = "";
	$tid_temp   = "nx";
	
	if (isset($_POST['preview']) || isset($_POST['submit'])) {
		// common threadpost / query requirements
		
		$username = filter_string($_POST['username'], true);
		$password = filter_string($_POST['password'], true);
		
		//print "<br><table class='table'>";
		if ($loguser['id'] && !$password) {
			$userid = $loguser['id'];
			$user	= $loguser;
		} else {
			$userid 	= checkuser($username,$password);
			$user 		= $sql->fetchq("SELECT * FROM users WHERE id = '$userid'");
		}
		
		if(!$user || $user['powerlevel'] < 0) $userid = -1;

		// can't be posting too fast now
		$limithit 		= $user['lastposttime'] < (ctime()-30);
		// can they post in this forum?
		$authorized 	= $user['powerlevel'] >= $forum['minpowerthread'];
		// does the forum exist?
		$forumexists 	= $forum['id'];

		// ---
		// lol i'm eminem
		if (strpos($message , '[Verse ') !== FALSE) {
			$authorized = false;
			$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."', `reason` = 'Listen to some good music for a change.'");
			if ($loguser['id']) //if ($_COOKIE['loguserid'] > 0)
				$sql->query("UPDATE `users` SET `powerlevel` = '-2' WHERE `id` = {$loguser['id']}");
			xk_ircsend("1|". xk(7) ."Auto-banned another Eminem wannabe with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) .".");
		}
		// ---
		
		if($userid != -1 && $subject && $message && $forumexists && $authorized && $limithit) {
			
			// as soon as the first file is properly attached, mark the temp key as confirmed
			// and do the same for each next iteration of the script
			// meaning that on next iterations of this script, the current key value will be used
			if (isset($_POST['tidconfirm'])) {
				$attach_id  = filter_int($_POST['attach_id']);
				$input_tid .= "<input type='hidden' name='tidconfirm' value=1>";
			} else {
				$attach_id  = get_attachments_newthread("n{$id}_", $loguser['id']);
			}
			$input_tid .= "<input type='hidden' name='attach_id' value='{$attach_id}'>";
			$tid_temp   = "n{$id}_{$attach_id}";
			
			// Process attachments removal
			$cnt = get_attachments_index($tid_temp, $loguser['id']);	
			$list = array();
			for ($i = 0; $i < $cnt; ++$i) {
				if (filter_int($_POST["remove{$i}"])) {
					$list[] = $i;
				}
			}

			if (!empty($list)) {
				remove_temp_attachments($tid_temp, $loguser['id'], $list);
			}
			

			
			// Upload current attachment
			if (!filter_int($_POST["remove{$i}"]) && isset($_FILES["attachment{$i}"]) && !$_FILES["attachment{$i}"]['error']) {
				upload_attachment($_FILES["attachment{$i}"], $tid_temp, $loguser['id'], $i - count($list));
				$input_tid .= "<input type='hidden' name='tidconfirm' value=1>";
			} else if (count($list) == $cnt) {
				// if every attachment is removed and no extra one is getting uploaded, unconfirm the key
				// this is to prevent any funny shit
					$tid_temp = "nx";
					$input_tid = "";
			}
			
			// The thread preview also needs this for threadpost()
			
			$msg 			= $message;
			// squot(0,$msg);
			$sign			= $user['signature'];
			$head 			= $user['postheader'];
			
			$numposts 		= $user['posts'] + 1;
			$numdays 		= (ctime()-$user['regdate']) / 86400;
			
			$tags			= array();
			
			$msg 			= doreplace($msg, $numposts, $numdays, $user['id'], $tags);
			$rsign 			= doreplace($sign, $numposts, $numdays, $user['id']);
			$rhead 			= doreplace($head, $numposts, $numdays, $user['id']);
			
			
			$tagval			= json_encode($tags);
			
			if ($iconid != '-1' && isset($posticons[$iconid])) {
				$posticon = $posticons[$iconid];
			} else {
				$posticon = $custposticon;
			}
			
			$currenttime 	= ctime();
			//$postnum 		= $numposts;
			

			if (isset($_POST['submit'])) {
				check_token($_POST['auth']);
				
				$sql->beginTransaction();
				
				$querycheck = array();
				
				// Process the poll data right away
				if ($poll) {
					$sql->queryp("INSERT INTO `poll` (`question`, `briefing`, `closed`, `doublevote`) VALUES (:question, :briefing, :closed, :doublevote)",
						 [
							'question'			=> xssfilters($question),
							'briefing'			=> xssfilters($briefing),
							'closed'			=> 0,
							'doublevote'		=> $mltvote,
						 ], $querycheck);
					
					$pollid = $sql->insert_id();
					
					$addchoice = $sql->prepare("INSERT INTO `poll_choices` (`poll`, `choice`, `color`) VALUES (?,?,?)");
					
					for($c = 1; isset($chtext[$c]); ++$c) {
						
						if (!$chtext[$c] || !isset($chcolor[$c]))
							continue; // Just in case
						$querycheck[] = $sql->execute($addchoice, array($pollid, $chtext[$c], $chcolor[$c]));
					}
						
				} else {
					$pollid = 0;
				}
				
				
				$sql->query("UPDATE `users` SET `posts` = posts + 1, `lastposttime` = '$currenttime' WHERE `id` = '{$user['id']}'", false, $querycheck);
				
				if ($nolayout) {
					$headid = 0;
					$signid = 0;
				} else {
					$headid = getpostlayoutid($head);
					$signid = getpostlayoutid($sign);
				}
				
				$sql->queryp("INSERT INTO `threads` (`forum`, `user`, `views`, `closed`, `sticky`, `title`, `description`, `icon`, `replies`, `firstpostdate`, `lastpostdate`, `lastposter`, `poll`) ".
							 "VALUES                (:forum,  :user,  :views,  :closed,  :sticky,  :title,  :description,  :icon,  :replies,  :firstpostdate,  :lastpostdate,  :lastposter,  :poll)",
						 [
							'forum'				=> $id,
							'user'				=> $user['id'],
							
							'closed'			=> 0,
							'sticky'			=> 0,
							
							'poll'				=> $pollid,
							
							'title'				=> xssfilters($subject),
							'description'		=> xssfilters($description),
							'icon'				=> $posticon,
							
							'views'				=> 0,
							'replies'			=> 0,
							'firstpostdate'		=> $currenttime,
							'lastpostdate'		=> $currenttime,
							'lastposter'		=> $user['id'],
							
						 ], $querycheck);
				
				$tid = $sql->insert_id();
				
				$sql->queryp("INSERT INTO `posts` (`thread`, `user`, `date`, `ip`, `num`, `headid`, `signid`, `moodid`, `text`, `tagval`, `options`) ".
							 "VALUES              (:thread,  :user,  :date,  :ip,  :num,  :headid,  :signid,  :moodid,  :text,  :tagval,  :options)",
						 [
							'thread'			=> $tid,
							'user'				=> $user['id'],
							'date'				=> $currenttime,
							'ip'				=> $_SERVER['REMOTE_ADDR'],
							'num'				=> $numposts,
							
							'headid'			=> $headid,
							'signid'			=> $signid,
							'moodid'			=> $moodid,
							
							'text'				=> xssfilters($msg),
							'tagval'			=> $tagval,
							'options'			=> $nosmilies . "|" . $nohtml,
							
						 ], $querycheck);
						 
				$pid = $sql->insert_id();
				
				save_attachments($tid_temp, $loguser['id'], $pid);
				
				$sql->query("
					UPDATE `forums` SET
						`numthreads`   = `numthreads` + 1,
						`numposts`     = `numposts` + 1,
						`lastpostdate` = '$currenttime',
						`lastpostuser` = '$userid',
						`lastpostid`   = '$pid'
					WHERE id = $id", false, $querycheck);

				
				
				$whatisthis = $poll ? "Poll" : "Thread";
				
				if ($sql->checkTransaction($querycheck)) {
					
					xk_ircout(strtolower($whatisthis), $user['name'], array(
						'forum'		=> $forum['title'],
						'fid'		=> $forum['id'],
						'thread'	=> str_replace("&lt;", "<", $subject),
						'pid'		=> $pid,
						'pow'		=> $forum['minpower'],
					));
					
					errorpage("$whatisthis posted successfully!", "thread.php?id=$tid", $subject, 0);
					
				} else {
					errorpage("An error occurred while creating the ".strtolower($whatisthis).".");
				}
				
			}
		}
		else {
			
			$reason = "You haven't entered your username and password correctly.";
			if (!$limithit) 	$reason = "You are trying to post too rapidly.";
			if (!$message) 		$reason = "You haven't entered a message.";
			if (!$subject) 		$reason = "You haven't entered a subject.";
			if (!$authorized) 	$reason = "You aren't allowed to post in this forum.";
			errorpage("Couldn't post the thread. $reason", "forum.php?id=$id", $forum['title'], 2);
		}		
		
	}
	
	/*
		Main page below
	*/
	
	if ($poll) {
		$c = 1;	// Choice (appareance)
		$d = 0; // Choice ID in array
		$choices = "";
		// Don't bother if the array is empty (ie: poll not previewed yet)
		if ($chtext) {
			
			while (filter_string($chtext[$c+$d]) || $c < $count) {	// Allow a lower choice count to cut off the remainder of the choices
				
				if (isset($remove[$c+$d])) // Count the choices and skip what's removed
					$d++;
				else {
					$choices .= "Choice $c: <input type='text' name=chtext[$c] SIZE=30 MAXLENGTH=255 VALUE=\"".htmlspecialchars($chtext[$c+$d])."\"> &nbsp; ".
								"Color: <input type='text' name=chcolor[$c] SIZE=7 MAXLENGTH=25 VALUE=\"".htmlspecialchars(filter_string($chcolor[$c+$d]))."\"> &nbsp; ".
								"<input type=checkbox class=radio name=remove[$c] value=1> Remove<br>";
					$c++;
				}
			}
		}
		
		$choices .= "Choice $c: <input type='text' name=chtext[$c] SIZE=30 MAXLENGTH=255> &nbsp ".
					"Color: <input type='text' name=chcolor[$c] SIZE=7 MAXLENGTH=25><br>".
					"<input type='submit' class=submit name=paction VALUE=\"Submit changes\"> and show ".
					"<input type='text' name=count size=4 maxlength=2 VALUE=\"".htmlspecialchars($count ? $count : $c)."\"> options";
		
		// Multivote selection
		$mltsel[$mltvote] = 'checked';
	}
	
	$nosmilieschk 	= $nosmilies 	? " checked" : "";
	$nohtmlchk	 	= $nohtml 		? " checked" : "";
	$nolayoutchk 	= $nolayout 	? " checked" : "";

	$forumlink = "<span class='font'><a href='index.php'>{$config['board-name']}</a> - <a href='forum.php?id=$id'>".htmlspecialchars($forum['title'])."</a></span>";

	if ($loguser['id']) {
		$username = $loguser['name'];
		$passhint = 'Alternate Login:';
		$altloginjs = "<a href=\"#\" onclick=\"document.getElementById('altlogin').style.cssText=''; this.style.cssText='display:none'\">Use an alternate login</a>
			<span id=\"altlogin\" style=\"display:none\">";
	} else {
		$username = '';
		$passhint = 'Login Info:';
		$altloginjs = "<span>";
	}
	
	// Mixing _GET and _POST is bad. Put all _GET arguments here rather than sending them as hidden form values.
	$formlink = "newthread.php?id=$id";
	if ($poll) $formlink .= "&poll=1";
	
	
	

	if (isset($_POST['preview'])) {
		
		if ($posticon)
			$iconpreview = "<img src = \"".htmlspecialchars($posticon)."\" height=15 align=absmiddle>";
	
		$pollpreview = "";
		
		if($poll) {
			// Print out poll options
			$pchoices = "";
			for($c = 1; ($chtext[$c]); ++$c) {
				
				if (!$chtext[$c]) continue;
				// Just in case
				if (!isset($chtext[$c])) $chtext[$c] = "red";
				
				$pchoices .= 
					"<tr><td class='tdbg1' width=20%>".htmlspecialchars($chtext[$c])."</td>".
					"<td class='tdbg2' width=60%><table cellpadding=0 cellspacing=0 width=50% bgcolor='{$chcolor[$c]}'><td>&nbsp</table></td>".
					"<td class='tdbg1 center' width=20%><font> ? votes, ??.?%</tr>";
			}
			
			$mlt = ($mltvote ? 'enabled' : 'disabled');
			
			$pollpreview = 
				"<table class='table'>
				<tr>
					<td colspan=3 class='tbl tdbgc center'>
						<b>".htmlspecialchars($question)."</b>
					</td>
				<tr>
					<td class='tdbg2 fonts' colspan=3>
						".xssfilters($briefing)."
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
		
		loadtlayout();
		
		$ppost = $user;
		$ppost['uid']			= $userid;
		$ppost['num']			= $numposts;
		$ppost['posts']++;
		$ppost['lastposttime']	= $currenttime;
		$ppost['date']			= $currenttime;
		$ppost['tagval']		= $tagval;
		$ppost['noob']			= 0;
		if ($nolayout) {
			$ppost['headtext'] = "";
			$ppost['signtext'] = "";
		}
		else {
			$ppost['headtext']	= $rhead;
			$ppost['signtext']	= $rsign;
		}
		
		$ppost['moodid']		= $moodid;
		$ppost['text']			= $message;
		$ppost['options'] 		= $nosmilies . "|" . $nohtml;
		$ppost['act'] 			= $sql->resultq("SELECT COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." AND user = {$user['id']}");
		$ppost['attach']		= get_temp_attachments($tid_temp, $loguser['id']);
		
		if ($isadmin)
			$ip = " | IP: <a href='ipsearch.php?ip={$_SERVER['REMOTE_ADDR']}'>{$_SERVER['REMOTE_ADDR']}</a>";
		$threadtype = ($poll ? 'poll' : 'thread');
			
			?>
	<table class='table'>
		<tr>
			<td class='tdbgh center'>
				<?=($poll ? 'Poll' : 'Thread')." preview"?>
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
				<b><?=htmlspecialchars($subject)?></b>
				<span class='fonts'><br><?=htmlspecialchars($description)?></span>
			</td>
		</tr>
	</table>
	<table class='table'>
		<?=threadpost($ppost,1,$id)?>
	</table>
			<?php
			$autofocus[1] = 'autofocus'; // for 'message'
		} else {
			$autofocus[0] = 'autofocus'; // for 'subject'
		}
		
		
	
?>
		
	<?=$forumlink?>
	<form method="POST" action="<?=$formlink?>" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
			<tr>
				<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
				<td class='tdbgh center' colspan=2>&nbsp;</td>
			</tr>
			<tr>
				<td class='tdbg1 center'>
					<b><?=$passhint?></b>
				</td>
				<td class='tdbg2' colspan=2>
					<?=$altloginjs?>
						<b>Username:</b> <input type='text' name=username VALUE="<?=htmlspecialchars($username)?>" SIZE=25 MAXLENGTH=25 autocomplete=off>

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
	if (!$poll) {
			
		?>
			<tr>
				<td class='tdbg1 center'><b>Thread icon:</b></td>
				<td class='tdbg2' colspan=2>
					<?=dothreadiconlist($iconid, $custposticon)?>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'><b>Thread title:</b></td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=subject SIZE=40 MAXLENGTH=100 VALUE="<?=htmlspecialchars($subject)?>" <?=filter_string($autofocus[0])?>>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'><b>Thread description:</b></td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=description SIZE=100 MAXLENGTH=120 VALUE="<?=htmlspecialchars($description)?>">
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'><b>Post:</b></td>
				<td class='tdbg2' style='width: 800px' valign=top>
					<?=replytoolbar(2)?>
					<textarea wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" <?=filter_string($autofocus[1])?>><?=htmlspecialchars($message)?></textarea>
				</td>
				<td class='tdbg2' width=*>
					<?=moodlist($moodid)?>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'>&nbsp;</td>
				<td class='tdbg2' colspan=2>
					<input type='hidden' name=action VALUE=postthread>
					<input type='hidden' name=auth   VALUE="<?=generate_token()?>">
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
				<td class='tdbg1 center'><b>Poll icon:</b></td>
				<td class='tdbg2' colspan=2>
					<?=dothreadiconlist($iconid, $custposticon)?>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'><b>Poll title:</b></td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=subject SIZE=40 MAXLENGTH=100 VALUE="<?=htmlspecialchars($subject)?>" <?=filter_string($autofocus[0])?>>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'><b>Poll description:</b></td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=description SIZE=100 MAXLENGTH=120 VALUE="<?=htmlspecialchars($description)?>">
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'><b>Question:</b></td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=question SIZE=100 MAXLENGTH=120 VALUE="<?=htmlspecialchars($question)?>">
				</td>
			</tr>			
			<tr>
				<td class='tdbg1 center'><b>Briefing:</b></td>
				<td class='tdbg2' colspan=2>
					<textarea wrap=virtual name=briefing ROWS=2 COLS=<?=$numcols?> style="resize:vertical;"><?=htmlspecialchars($briefing)?></TEXTAREA>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'><b>Multi-voting:</b></td>
				<td class='tdbg2' colspan=2>
					<input type=radio class='radio' name=mltvote value=0 <?=filter_string($mltsel[0])?>> Disabled &nbsp;&nbsp;
					<input type=radio class='radio' name=mltvote value=1 <?=filter_string($mltsel[1])?>> Enabled
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'><b>Choices:</b></td>
				<td class='tdbg2' colspan=2>
					<?=$choices?>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'><b>Post:</b></td>
				<td class='tdbg2' style='width: 800px' valign=top>
					<?=replytoolbar(2)?>
					<textarea wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"  <?=filter_string($autofocus[1])?>><?=htmlspecialchars($message)?></textarea>
				</td>
				<td class='tdbg2' width=*>
					<?=moodlist($moodid)?>
				</td>
			</tr>

			<tr>
				<td class='tdbg1 center'>&nbsp;</td><td class='tdbg2' colspan=2>
					<input type='hidden' name=action VALUE=postthread>
					<input type='hidden' name=auth VALUE="<?=generate_token()?>">
					<?= $input_tid ?>
					<input type='submit' class=submit name=submit VALUE="Submit poll">
					<input type='submit' class=submit name=preview VALUE="Preview poll">
				</td>
			</tr>
		<?php
	}
		
		
		?>
			<tr>
				<td class='tdbg1 center'><b>Options:</b></td>
				<td class='tdbg2' colspan=2>
					<input type='checkbox' name="nosmilies" id="nosmilies" value="1"<?=$nosmilieschk?>><label for="nosmilies">Disable Smilies</label> -
					<input type='checkbox' name="nolayout"  id="nolayout"  value="1"<?=$nolayoutchk?> ><label for="nolayout" >Disable Layout</label> -
					<input type='checkbox' name="nohtml"    id="nohtml"    value="1"<?=$nohtmlchk?>   ><label for="nohtml"   >Disable HTML</label>
				</td>
			</tr>
		<?=quikattach($tid_temp, $loguser['id'])?>
		</table>
		</form>
		<?=$forumlink?>
		<?=replytoolbar(4)?>
		<?php
	pagefooter();
