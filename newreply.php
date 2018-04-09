<?php
	// die("Disabled.");
	
	require 'lib/function.php';
	
	// Stop this insanity.  Never index newreply.
	$meta['noindex'] = true;
	
	$_GET['id']         = filter_int($_GET['id']);
	$_GET['postid']     = filter_int($_GET['postid']);
	
	if (!$_GET['id'])  {
		errorpage("No thread specified.", "index.php", 'return to the index page', 0);
	}
	
	$thread = $sql->fetchq("SELECT forum, closed, sticky, announcement, title, lastposter FROM threads WHERE id = {$_GET['id']}");

	if (!$thread) {
		errorpage("Nice try. Next time, wait until someone makes the thread <i>before</i> trying to reply to it.", "index.php", 'return to the index page', 0);
	}

	$forumid = (int) $thread['forum'];
	$forum   = $sql->fetchq("
		SELECT title, minpower, minpowerreply, id, specialscheme, specialtitle 
		FROM forums 
		WHERE id = {$forumid}
	");
	
	// Local mods
	if (!$ismod) {
		$ismod = $sql->resultq("SELECT 1 FROM forummods WHERE forum = {$forumid} and user = {$loguser['id']}");
	}
	
	check_forumban($forumid, $loguser['id']);
	
	// Thread permissions for our sanity
	$canviewforum 	= (!$forum['minpower'] || $loguser['powerlevel'] >= $forum['minpower']);
	$canreply		= ($loguser['powerlevel'] >= $forum['minpowerreply']);
	$closed			= (!$ismod && $thread['closed']);
	
	if ($closed) {
		errorpage("Sorry, but this thread is closed, and no more replies can be posted in it.","thread.php?id={$_GET['id']}",$thread['title'],0);
	} else if ($banned) {
		errorpage("Sorry, but you are banned from the board, and can not post.","thread.php?id={$_GET['id']}",$thread['title'],0);
	} else if (!$canreply || (!$forum && !$ismod)) { // Thread in broken forum = No
		errorpage("You are not allowed to post in this thread.","thread.php?id={$_GET['id']}",$thread['title'],0);
	}
	
	if (!$canviewforum) {
		$forum['title'] 	= '(restricted forum)';
		$thread['title'] 	= '(restricted thread)';
	}
	
	
	$ppp	= get_ppp();
	
	
	// register_globals!
	$_POST['username'] 	= filter_string($_POST['username']);
	$_POST['password'] 	= filter_string($_POST['password']);
	
	$_POST['message']	= filter_string($_POST['message']);
	
	$_POST['moodid']		= filter_int($_POST['moodid']);
	$_POST['nosmilies']	= filter_int($_POST['nosmilies']);
	$_POST['nolayout']	= filter_int($_POST['nolayout']);
	$_POST['nohtml']		= filter_int($_POST['nohtml']);
	
	$_POST['stick'] = filter_int($_POST['stick']);
	$_POST['close'] = filter_int($_POST['close']);
	$_POST['tannc']	 = filter_int($_POST['tannc']);
	
	$userid = $loguser['id'];
	if (isset($_POST['submit']) || isset($_POST['preview'])) {
		
		// Trying to post as someone else?
		if ($loguser['id'] && !$_POST['password']) {
			$user	= $loguser;
		} else {
			$userid 	= checkuser($_POST['username'], $_POST['password']);
			$user 		= $sql->fetchq("SELECT * FROM users WHERE id = '{$userid}'");
		}
		

		$error = '';
		if ($userid == -1) {
			$error	= "Either you didn't enter an existing username, or you haven't entered the right password for the username.";
		} else {
			check_forumban($forumid, $userid);
			
			//$user	   = $sql->fetchq("SELECT * FROM users WHERE id='$userid'");
			
			if (!$ismod) {
				$ismod = $sql->resultq("SELECT 1 FROM forummods WHERE forum = $forumid and user = {$userid}");
			}
			
			if ($thread['closed'] && !$ismod)
				$error	= 'The thread is closed and no more replies can be posted.';
			if ($user['powerlevel'] < $forum['minpowerreply']) // or banned
				$error	= 'Replying in this forum is restricted, and you are not allowed to post in this forum.';
			if (!$_POST['message'])
				$error	= "You didn't enter anything in the post.";
			if ($user['lastposttime'] > (ctime()-4))
				$error	= "You are posting too fast.";
			// Attachments check here
		}
		
		if ($error) {
			errorpage("Couldn't enter the post. $error", "thread.php?id={$_GET['id']}", htmlspecialchars($thread['title']), 0);
		}
		
		// Process attachments removal
		if ($config['allow-attachments']) {
			process_temp_attachments($_GET['id'], $userid);
		}
		
		// All OK
		if (isset($_POST['submit'])) {
			check_token($_POST['auth']);

			$numposts		= $user['posts'] + 1;
			$numdays		= (ctime() - $user['regdate']) / 86400;
			$tags			= array();
			$_POST['message']		= doreplace($_POST['message'],$numposts,$numdays,$user['id'], $tags);
			$tagval			= json_encode($tags);
			$currenttime	= ctime();
			
			$sql->beginTransaction();

			if ($_POST['nolayout']) {
				$headid = 0;
				$signid = 0;
			} else {
				$headid = getpostlayoutid($user['postheader']);
				$signid = getpostlayoutid($user['signature']);
			}
			
			$sql->queryp("INSERT INTO `posts` (`thread`, `user`, `date`, `ip`, `num`, `headid`, `signid`, `moodid`, `text`, `tagval`, `options`) ".
						 "VALUES              (:thread,  :user,  :date,  :ip,  :num,  :headid,  :signid,  :moodid,  :text,  :tagval,  :options)",
					 [
						'thread'			=> $_GET['id'],
						'user'				=> $user['id'],
						'date'				=> $currenttime,
						'ip'				=> $_SERVER['REMOTE_ADDR'],
						'num'				=> $numposts,
						
						'headid'			=> $headid,
						'signid'			=> $signid,
						'moodid'			=> $_POST['moodid'],
						
						'text'				=> $_POST['message'],
						'tagval'			=> $tagval,
						'options'			=> $_POST['nosmilies'] . "|" . $_POST['nohtml'],
						
					 ]);
					 
			$pid = $sql->insert_id();
			
			if ($config['allow-attachments']) {
				save_attachments($_GET['id'], $userid, $pid);
			}
			
			$modq = $ismod ? "`closed` = {$_POST['close']}, `sticky` = {$_POST['stick']}, announcement = {$_POST['tannc']}," : "";
			
			// Update statistics
			$sql->query("UPDATE `threads` SET $modq `replies` =  `replies` + 1, `lastpostdate` = '$currenttime', `lastposter` = '$userid' WHERE `id`='{$_GET['id']}'");
			$sql->query("UPDATE `forums` SET `numposts` = `numposts` + 1, `lastpostdate` = '$currenttime', `lastpostuser` ='$userid', `lastpostid` = '$pid' WHERE `id`='$forumid'");

			$sql->query("UPDATE `threadsread` SET `read` = '0' WHERE `tid` = '{$_GET['id']}'");
			$sql->query("REPLACE INTO threadsread SET `uid` = '$userid', `tid` = '{$_GET['id']}', `time` = ". ctime() .", `read` = '1'");

			$sql->query("UPDATE `users` SET `posts` = posts + 1, `lastposttime` = '$currenttime' WHERE `id` = '$userid'");

			$sql->commit();
			
			xk_ircout("reply", $user['name'], array(
				'forum'		=> $forum['title'],
				'fid'		=> $forumid,
				'thread'	=> str_replace("&lt;", "<", $thread['title']),
				'pid'		=> $pid,
				'pow'		=> $forum['minpower'],
			));

			return header("Location: thread.php?pid=$pid#$pid");

		}
		
	}
	/*
		Main page
	*/
	
		
	$smilies = readsmilies();
	
	$windowtitle = "{$config['board-name']} -- ".htmlspecialchars($forum['title']).": ".htmlspecialchars($thread['title'])." -- New Reply";
	pageheader($windowtitle, $forum['specialscheme'], $forum['specialtitle']);
	
	/*
		Previous posts in the thread
	*/
	$postlist = "";
	if ($canviewforum) {
		$postlist = thread_history($_GET['id'], $ppp + 1);
	}
	
	
	
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

	/*
		Quoting something?
	*/
	if ($_GET['postid']) {
		$post = $sql->fetchq("
			SELECT user, text, thread 
			FROM posts 
			WHERE id = {$_GET['postid']} AND (".((int) $ismod)." OR deleted = 0)
		");
		if ($post && $post['thread'] == $_GET['id']) { // Make sure the quote is in the same thread
			$post['text'] = str_replace('<br>','\n',$post['text']);
			$quoteuser = $sql->resultq("SELECT name FROM users WHERE id = {$post['user']}");
			$_POST['message'] = "[quote={$quoteuser}]{$post['text']}[/quote]\r\n";
			unset($post, $quoteuser);
		}
	}
	
	$links = array(
		$forum['title']  => "forum.php?id={$forumid}",
		$thread['title'] => "thread.php?id={$_GET['id']}",
		"New reply"      => NULL,
	);
	$barlinks = dobreadcrumbs($links); 
	
	if (isset($_POST['preview'])) {
		$data = array(
			// Text
			'message' => $_POST['message'],	
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
		print preview_post($user, $data);
	} else {
		$_POST['stick'] = $thread['sticky'];
		$_POST['close'] = $thread['closed'];
		$_POST['tannc'] = $thread['announcement'];
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
	
	$nosmilieschk   = $_POST['nosmilies'] ? "checked" : "";
	$nolayoutchk    = $_POST['nolayout']  ? "checked" : "";
	$nohtmlchk      = $_POST['nohtml']    ? "checked" : "";
	
	?>
	<?=$barlinks?>
	<form method="POST" action="newreply.php?id=<?=$_GET['id']?>" enctype="multipart/form-data" autocomplete=off>
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
					<b>Password:</b> <input type='password' name=password SIZE=13 MAXLENGTH=64 autocomplete=off>
				</span>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Reply:</td>
			<td class='tdbg2' style='width: 800px' valign=top>
				<textarea wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" autofocus><?=htmlspecialchars($_POST['message'], ENT_QUOTES)?></textarea>
			</td>
			<td class='tdbg2' width=*>
				<?=mood_layout(0, $userid, $_POST['moodid'])?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2' colspan=2>
				<?= auth_tag() ?>
				<input type='submit' class=submit name=submit VALUE="Submit reply">
				<input type='submit' class=submit name=preview VALUE="Preview reply">
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
		<?=$modoptions?>
		<?=quikattach($_GET['id'], $userid)?>
	</table>
	<br>
	<?=$postlist?>
	</form>
	<?=$barlinks?>
<?php
	
	
	pagefooter();
