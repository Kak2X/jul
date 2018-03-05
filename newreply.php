<?php
	// die("Disabled.");
	
	require 'lib/function.php';
	
	// Stop this insanity.  Never index newreply.
	$meta['noindex'] = true;
	
	$id         = filter_int($_GET['id']);
	$quoteid    = filter_int($_GET['postid']);
	
	if (!$id)  {
		errorpage("No thread specified.", "index.php", 'return to the index page', 0);
	}
	
	$thread = $sql->fetchq("SELECT forum, closed, sticky, announcement, title, lastposter FROM threads WHERE id = $id");

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
		$ismod = $sql->resultq("
			SELECT 1 
			FROM forummods 
			WHERE forum = {$forumid} and user = {$loguser['id']}
		");
	}
	
	// Thread permissions for our sanity
	$canviewforum 	= (!$forum['minpower'] || $loguser['powerlevel'] >= $forum['minpower']);
	$canreply		= ($loguser['powerlevel'] >= $forum['minpowerreply']);
	$closed			= (!$ismod && $thread['closed']);
	
	if ($closed) {
		errorpage("Sorry, but this thread is closed, and no more replies can be posted in it.","thread.php?id=$id",$thread['title'],0);
	} else if ($banned) {
		errorpage("Sorry, but you are banned from the board, and can not post.","thread.php?id=$id",$thread['title'],0);
	} else if (!$canreply || (!$forum && !$ismod)) { // Thread in broken forum = No
		errorpage("You are not allowed to post in this thread.","thread.php?id=$id",$thread['title'],0);
	}
	
	if (!$canviewforum) {
		$forum['title'] 	= '(restricted forum)';
		$thread['title'] 	= '(restricted thread)';
	}
	
	
	$ppp	= isset($_GET['ppp']) ? ((int) $_GET['ppp']) : ($loguser['id'] ? $loguser['postsperpage'] : $config['default-ppp']);
	$ppp	= max(min($ppp, 500), 1);
	
	
	// register_globals!
	$username 	= filter_string($_POST['username'], true);
	$password 	= filter_string($_POST['password'], true);
	
	$message	= filter_string($_POST['message'], true);
	
	$moodid		= filter_int($_POST['moodid']);
	$nosmilies	= filter_int($_POST['nosmilies']);
	$nolayout	= filter_int($_POST['nolayout']);
	$nohtml		= filter_int($_POST['nohtml']);
	
	$tsticky = filter_int($_POST['stick']);
	$tclosed = filter_int($_POST['close']);
	$tannc	 = filter_int($_POST['tannc']);
	
	$userid = $loguser['id'];
	if (isset($_POST['submit']) || isset($_POST['preview'])) {
		
		// Trying to post as someone else?
		if ($loguser['id'] && !$password) {
			$userid = $loguser['id'];
			$user	= $loguser;
		} else {
			$userid 	= checkuser($username, $password);
			$user 		= $sql->fetchq("SELECT * FROM users WHERE id = '{$userid}'");
		}
		

		$error = '';
		if ($userid == -1) {
			$error	= "Either you didn't enter an existing username, or you haven't entered the right password for the username.";
		} else {
			
			$user	= $sql->fetchq("SELECT * FROM users WHERE id='$userid'");
			
			if ($user['powerlevel'] >= 2)
				$ismod = 1;
			else if ($sql->resultq("SELECT 1 FROM forummods WHERE forum = $forumid and user = {$user['id']}"))
				$ismod = 1;
			else
				$ismod = 0;
			
			if ($thread['closed'] && !$ismod)
				$error	= 'The thread is closed and no more replies can be posted.';
			if ($user['powerlevel'] < $forum['minpowerreply'])	// or banned
				$error	= 'Replying in this forum is restricted, and you are not allowed to post in this forum.';
			if (!$message)
				$error	= "You didn't enter anything in the post.";
			if ($user['lastposttime'] > (ctime()-4))
				$error	= "You are posting too fast.";
			// Attachments check here
		}
		
		if ($error) {
			errorpage("Couldn't enter the post. $error", "thread.php?id=$id", htmlspecialchars($thread['title']), 0);
		}
		
		// Process attachments removal
		if ($config['allow-attachments']) {
			process_temp_attachments($id, $userid);
		}
		
		// All OK

		$sign	= $user['signature'];
		$head	= $user['postheader'];
		
		$numposts		= $user['posts']+ 1;

		$numdays		= (ctime()-$user['regdate'])/86400;
		$tags			= array();
		$message		= doreplace($message,$numposts,$numdays,$user['id'], $tags);
		$tagval			= json_encode($tags);
		$rsign			= doreplace($sign,$numposts,$numdays,$user['id']);
		$rhead			= doreplace($head,$numposts,$numdays,$user['id']);
		$currenttime	= ctime();
		
		if (isset($_POST['submit'])) {
			
			check_token($_POST['auth']);
			
			$sql->beginTransaction();

			if ($nolayout) {
				$headid = 0;
				$signid = 0;
			} else {
				$headid = getpostlayoutid($head);
				$signid = getpostlayoutid($sign);
			}
			
			$sql->queryp("INSERT INTO `posts` (`thread`, `user`, `date`, `ip`, `num`, `headid`, `signid`, `moodid`, `text`, `tagval`, `options`) ".
						 "VALUES              (:thread,  :user,  :date,  :ip,  :num,  :headid,  :signid,  :moodid,  :text,  :tagval,  :options)",
					 [
						'thread'			=> $id,
						'user'				=> $user['id'],
						'date'				=> $currenttime,
						'ip'				=> $_SERVER['REMOTE_ADDR'],
						'num'				=> $numposts,
						
						'headid'			=> $headid,
						'signid'			=> $signid,
						'moodid'			=> $moodid,
						
						'text'				=> $message,
						'tagval'			=> $tagval,
						'options'			=> $nosmilies . "|" . $nohtml,
						
					 ]);
					 
			$pid = $sql->insert_id();
			
			if ($config['allow-attachments']) {
				save_attachments($id, $userid, $pid);
			}
			
			$modq = $ismod ? "`closed` = $tclosed, `sticky` = $tsticky, announcement = $tannc," : "";
			
			// Update statistics
			$sql->query("UPDATE `threads` SET $modq `replies` =  `replies` + 1, `lastpostdate` = '$currenttime', `lastposter` = '$userid' WHERE `id`='$id'");
			$sql->query("UPDATE `forums` SET `numposts` = `numposts` + 1, `lastpostdate` = '$currenttime', `lastpostuser` ='$userid', `lastpostid` = '$pid' WHERE `id`='$forumid'");

			$sql->query("UPDATE `threadsread` SET `read` = '0' WHERE `tid` = '$id'");
			$sql->query("REPLACE INTO threadsread SET `uid` = '$userid', `tid` = '$id', `time` = ". ctime() .", `read` = '1'");

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

		//$qppp = $ppp + 1;
		$posts = $sql->query("
			SELECT {$userfields}, u.posts, p.user, p.text, p.options, p.num
			FROM posts p
			LEFT JOIN users u ON p.user = u.id
			WHERE p.thread = $id
			ORDER BY p.id DESC
			LIMIT ".($ppp + 1)."
		");
		$i = 0;
		
		$postlist = 
		"<tr>
			<td class='tdbgh center' colspan=2 style='font-weight:bold'>
				Thread history
			</td>
		</tr>
		<tr>
			<td class='tdbgh center' width=150>User</td>
			<td class='tdbgh center'>Post</td>
		</tr>";
		
		
		if ($sql->num_rows($posts)) {
		
			while ($post = $sql->fetch($posts)) {
				
				$bg = ((($i++) & 1) ? 'tdbg2' : 'tdbg1');
				
				if ($ppp-- > 0){
					$postnum = ($post['num'] ? "{$post['num']}/" : '');
					$userlink = getuserlink($post);
					$postlist .=
						"<tr>
							<td class='tbl $bg' valign=top>
								{$userlink}
								<span class='fonts'><br>
									Posts: $postnum{$post['posts']}
								</span>
							</td>
							<td class='tbl $bg' valign=top>
								".doreplace2(dofilters($post['text'], $thread['forum']), $post['options'])."
							</td>
						</tr>";
				} else {
					$postlist .= "<tr><td class='tdbgh center' colspan=2>This is a long thread. Click <a href='thread.php?id=$id'>here</a> to view it.</td></tr>";
				}
			}
			
		} else {
			$postlist .= "<tr><td class='tdbg1 center' colspan=2><i>There are no posts in this thread.</i></td></tr>";
		}
	}
	
	
	
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

	/*
		Quoting something?
	*/
	if ($quoteid) {
		$post = $sql->fetchq("SELECT user, text, thread FROM posts WHERE id = $quoteid");
		$post['text'] = str_replace('<br>','\n',$post['text']);
		$quoteuser = $sql->resultq("SELECT name FROM users WHERE id = {$post['user']}");
		if($post['thread'] == $id) // Make sure the quote is in the same thread
			$message = "[quote={$quoteuser}]{$post['text']}[/quote]\r\n";
	}
	
	$barlinks = "<span class='font'><a href='index.php'>{$config['board-name']}</a> - <a href='forum.php?id=$forumid'>".htmlspecialchars($forum['title'])."</a> - ".htmlspecialchars($thread['title'])."</span>";

	if (isset($_POST['preview'])) {
		$data = array(
			// Text
			'message' => $message,	
			#'head'    => "",
			#'sign'    => "",
			// Post metadata
			#'id'      => 0,
			'forum'   => $thread['forum'],
			#'ip'      => "",
			#'num'     => "",
			#'date'    => "",
			// (mod) Options
			'nosmilies' => $nosmilies,
			'nohtml'    => $nohtml,
			'nolayout'  => $nolayout,
			'moodid'    => $moodid,
			'noob'      => 0,
			// Attachments
			'attach_key' => $id,
			#'attach_sel' => "",
		);
		print preview_post($user, $data);
	} else {
		$tsticky = $thread['sticky'];
		$tclosed = $thread['closed'];
		$tannc   = $thread['announcement'];
	}
	
	$modoptions	= "";
	
	if ($ismod) {
		
		$selsticky = $tsticky ? "checked" : "";
		$selclosed = $tclosed ? "checked" : "";
		$seltannc  = $tannc   ? "checked" : "";
		
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
	
	$nosmilieschk   = $nosmilies ? "checked" : "";
	$nolayoutchk    = $nolayout  ? "checked" : "";
	$nohtmlchk      = $nohtml    ? "checked" : "";
	
	?>
	<?=$barlinks?>
	<form method="POST" action="newreply.php?id=<?=$id?>" enctype="multipart/form-data" autocomplete=off>
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
					<!-- Hack around autocomplete, fake inputs (don't use these in the file) -->
					<input style="display:none;" type="text"     name="__f__usernm__">
					<input style="display:none;" type="password" name="__f__passwd__">
					<b>Username:</b> <input type='text' name=username VALUE="<?=htmlspecialchars($username)?>" SIZE=25 MAXLENGTH=25 autocomplete=off>
					<b>Password:</b> <input type='password' name=password SIZE=13 MAXLENGTH=64 autocomplete=off>
				</span>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Reply:</td>
			<td class='tdbg2' style='width: 800px' valign=top>
				<textarea wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" autofocus><?=htmlspecialchars($message, ENT_QUOTES)?></textarea>
			</td>
			<td class='tdbg2' width=*>
				<?=moodlayout(0, $userid, $moodid)?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2' colspan=2>
				<input type='hidden' name=auth value="<?=generate_token()?>">
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
				<?=moodlayout(1, $userid, $moodid)?>
			</td>
		</tr>
		<?=$modoptions?>
		<?=quikattach($id, $userid)?>
	</table>
	<br>
	<table class='table'><?=$postlist?></table>
	</form>
	<?=$barlinks?>
<?php
	
	
	pagefooter();
