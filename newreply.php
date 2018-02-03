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
	
	$thread = $sql->fetchq("SELECT forum, closed, sticky, title, lastposter FROM threads WHERE id = $id");

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
		$cnt = get_attachments_index($id, $loguser['id']);
		$list = array();
		for ($i = 0; $i < $cnt; ++$i) {
			if (filter_int($_POST["remove{$i}"])) {
				$list[] = $i;
			}
		}
		if (!empty($list)) {
			remove_temp_attachments($id, $loguser['id'], $list);
		}
		
		// Upload current attachment
		// May need to get changed if an add row system is done (ala poll choices)
		if (!filter_int($_POST["remove{$i}"]) && isset($_FILES["attachment{$i}"]) && !$_FILES["attachment{$i}"]['error']) {
			upload_attachment($_FILES["attachment{$i}"], $id, $loguser['id'], $i);
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
			
			save_attachments($id, $loguser['id'], $pid);
			
			$modq = $ismod ? "`closed` = $tclosed, `sticky` = $tsticky," : "";
			
			// Update statistics
			$sql->query("UPDATE `threads` SET $modq `replies` =  `replies` + 1, `lastpostdate` = '$currenttime', `lastposter` = '$userid' WHERE `id`='$id'", false, $querycheck);
			$sql->query("UPDATE `forums` SET `numposts` = `numposts` + 1, `lastpostdate` = '$currenttime', `lastpostuser` ='$userid', `lastpostid` = '$pid' WHERE `id`='$forumid'", false, $querycheck);

			$sql->query("UPDATE `threadsread` SET `read` = '0' WHERE `tid` = '$id'", false, $querycheck);
			$sql->query("REPLACE INTO threadsread SET `uid` = '$userid', `tid` = '$id', `time` = ". ctime() .", `read` = '1'", false, $querycheck);

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
								".doreplace2(dofilters($post['text']), $post['options'])."
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
		
		loadtlayout();
		$ppost					= $user;
		$ppost['posts']++;
		$ppost['uid']			= $userid;
		$ppost['num']			= $numposts;
		$ppost['lastposttime']	= $currenttime;
		$ppost['date']			= $currenttime;
		$ppost['moodid']		= $moodid;
		$ppost['noob']			= 0;
		

		if ($nolayout) {
			$ppost['headtext'] = "";
			$ppost['signtext'] = "";
		} else {
			$ppost['headtext'] = $rhead;
			$ppost['signtext'] = $rsign;
		}

		$ppost['text']			= $message;
		$ppost['options']		= $nosmilies . "|" . $nohtml;
		$ppost['act'] 			= $sql->resultq("SELECT COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." AND user = {$user['id']}");
		$ppost['attach']		= get_temp_attachments($id, $loguser['id']);
		
		if ($isadmin)
			$ip = " | IP: <a href='ipsearch.php?ip={$_SERVER['REMOTE_ADDR']}'>{$_SERVER['REMOTE_ADDR']}</a>";
	/*	
		$chks = array("", "", "");
		if ($nosmilies) $chks[0] = "checked";
		if ($nolayout)  $chks[1] = "checked";
		if ($nohtml)    $chks[2] = "checked";
*/
		?>
		<table class='table'>
			<tr>
				<td class='tdbgh center'>
					Post preview
				</td>
			</tr>
		</table>
		<table class='table'>
			<?=threadpost($ppost,1)?>
		</table>
		<br>
		<?php
		
	} else {
		$tsticky = $thread['sticky'];
		$tclosed = $thread['closed'];
	}
	
	$modoptions	= "";
	
	if ($ismod) {
		
		$selsticky = $tsticky ? "checked" : "";
		$selclosed = $tclosed ? "checked" : "";
		
		$modoptions = 
		"<tr>
			<td class='tdbg1 center'>
				<b>Moderator Options:</b>
			</td>
			<td class='tdbg2' colspan=2>
				<input type='checkbox' name='close' id='close' value=1 $selclosed><label for='close'>Close</label> -
				<input type='checkbox' name='stick' id='stick' value=1 $selsticky><label for='stick'>Sticky</label>
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
				<?=moodlist($moodid)?>
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
				<input type='checkbox' name="nohtml"    id="nohtml"    value="1"<?=$nohtmlchk   ?>><label for="nohtml"   >Disable HTML</label>
			</td>
		</tr>
		<?=$modoptions?>
		<?=quikattach($id, $loguser['id'])?>
	</table>
	<br>
	<table class='table'><?=$postlist?></table>
	</form>
	<?=$barlinks?>
<?php
	
	
	pagefooter();

// This layout is completely stolen from the I3 Archive
// Just so you know
function quikattach($thread, $user) {
	global $config, $numdir;
	
	$cnt = get_attachments_index($thread, $user);
	// Existing attachments
	$out = "";
	$sizetotal = 0;
	for ($i = 0; $i < $cnt; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		$cell = ($i % 2) + 1;
		$size = filesize($path);
		$out .= "
		<tr>
			<td class='tdbg{$cell}'>
				".htmlspecialchars(file_get_contents("{$path}.dat"))."
			</td>
			<td class='tdbg{$cell}'>".sizeunits($size)."</td>
			<td class='tdbg{$cell}'>
				<input type='checkbox' name='remove{$i}' value=1>
				<label for='remove{$i}'>Remove</a><br>
			</td>";
		
		$sizetotal += $size;
	}
	
	return "".
"<tr>
	<td class='tdbg1 center'>
		<span class='b'>Quik-Attach:</span>
		<div class='fonts'>Preview for more options</div>
	</td>
	<td class='tdbg2' colspan=2>
		<table class='table' style='border: none !important; width: auto !important'>
			<tr><td class='tdbgh center b' colspan=3>Files to upload</td></tr>
			<tr>
				<td class='tdbgh center'>Filename</td>
				<td class='tdbgh center'>File size</td>
				<td class='tdbgh center'></td>
			</tr>
			{$out}
			<tr>
				<td class='tdbgc center b'>Total</td>
				<td class='tdbgc center b' colspan=2>
					".sizeunits($sizetotal)."/".sizeunits($config['attach-max-size'])."
				</td>
			</tr>
			<tr>
				<td class='tdbg2' colspan=3>
					<img src='images/{$numdir}bar-on.gif' style='height:8px; width:".ceil($sizetotal * 100 / $config['attach-max-size'])."%'>
				</td>
			</tr>
			<tr>
				<td colspan=3>
					<input type='file' class='w' name='attachment{$i}'>
				</td>
			</tr>
		</table>
		
	</td>
</tr>";
}

function attachdisplay($id, $filename, $size, $views, $is_image = false, $imgprev = NULL) {
	$size_txt = sizeunits($size);
	
	// id 0 is a magic value used for post previews
	$w = $id ? 'a' : 'b';
	
	
	if ($is_image) { // An image
		return 
		"<$w href='download.php?id={$id}'>".
		"<img src='".($imgprev !== NULL ? $imgprev : attachment_name($id, true))."' title='{$filename} - {$size_txt}, views: {$views}'>".
		"</$w>";
	} else { // Not an image
		return "<$w href='download.php?id={$id}'>{$filename}</$w> ({$size_txt}) - views: {$views}";
	}
}

// on preview, uploaded files are saved on temp/attach_<thread>_<user>_<i>
// once confirmed, they are simply identified by index

// Assumes to receive an array of elements fetched off the DB
function attachfield($list) {
	$out = "";
	$datalist = array();
	// Display images first
	foreach ($list as $k => $x) {
		if (!$x['is_image']) {
			$datalist[] = $k;
			continue;
		}
		if (!isset($x['imgprev'])) $x['imgprev'] = NULL; // and this, which is only passed on post previews
		$out .= attachdisplay($x['id'], $x['filename'], $x['size'], $x['views'], $x['is_image'], $x['imgprev']).
		" &nbsp; "; 
	}
	// And then leftover files
	foreach ($datalist as $i) {
		$x = $list[$i];
		$out .= "<br/>".attachdisplay($x['id'], $x['filename'], $x['size'], $x['views'], $x['is_image'], NULL);
	}
	
	return "<fieldset><legend>Attachments</legend>{$out}</fieldset>";
}

// Upload to the temp area
// file_id should be sequential
function upload_attachment($file, $thread, $user, $file_id) {
	global $config;
	
	if (!$file['size']) 
		errorpage("This is an 0kb file");
	if (get_attachments_size($thread, $user, $file['size']) > $config['attach-max-size'])
		errorpage("The file you're trying to upload is over the file size limit.");	
	
	$path = attachment_tempname($thread, $user, $file_id);
	// Preserve given filename to an identically named .dat file
	file_put_contents("{$path}.dat", $file['name']);
	
	// Move the file and THEN generate the thumbnail
	$res = move_uploaded_file($file['tmp_name'], $path);
	
	list($width, $height) = getimagesize($path);
	$is_image = ($width && $height);
	// Generate a thumbnail
	if ($is_image) {
		$src_image = imagecreatefromstring(file_get_contents($path));
		if ($src_image) {
			$dst_image = resize_image($src_image, 100, 100);
		}
		if (!$src_image || !$dst_image) {
			// source image not found or resize error
			$dst_image = imagecreatefrompng("images/thumbnailbug.png");
		}
		imagedestroy($src_image);
		imagepng($dst_image, "{$path}_t");
		imagedestroy($dst_image);
	}
	
	return $res;
}

// Check if any current attachments are in the temp folder
// and move them to the proper attachment folder and save to the DB
function save_attachments($thread, $user, $post_id) {
	global $sql;
	for ($i = 0; true; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		if (!file_exists($path)){
			break;
		}
		
		// Fill out extra metadata
		list($width, $height) = getimagesize("{$path}_t");
		$is_image = ($width && $height);
		
		
		$sqldata = [
			'post'     => $post_id,
			'user'     => $user,
			'mime'     => mime_content_type($path),
			'filename' => file_get_contents("{$path}.dat"),
			'size'     => filesize($path),
			'views'    => 0,
			'is_image' => $is_image,
		];
		
		$sql->queryp("INSERT INTO attachments SET ".mysql::setplaceholders($sqldata), $sqldata);
		
		$rowid = $sql->insert_id();
		
		// Move the thumbnail we previously generated off the temp folder
		if ($is_image) {
			rename("{$path}_t", attachment_name($rowid, true));
		}
		rename($path, attachment_name($rowid));
		unlink("{$path}.dat");
	}
}

// For attachdisplay
function get_temp_attachments($thread, $user) {
	$cnt = get_attachments_index($thread, $user);
	$res = array();
	for ($i = 0; $i < $cnt; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		$is_image = file_exists("{$path}_t"); // Can cheat this one
		$res[] = [
			'id'       => 0,
			'filename' => file_get_contents("{$path}.dat"),
			'size'     => filesize($path), // File size
			'views'    => 0,
			'is_image' => $is_image,
			'imgprev'  => $is_image ? "data:".mime_content_type("{$path}_t").";base64,".base64_encode(file_get_contents("{$path}_t")) : NULL, // Image preview hack
		];
	}
	return $res;
}

function remove_temp_attachments($thread, $user, $list) {
	$max = get_attachments_index($thread, $user); // Get this before it's too late
	// Remove attachments
	foreach ($list as $i) {
		$path = attachment_tempname($thread, $user, $i);
		unlink($path);
		unlink($path.'.dat');
		$del[$i] = true; // Removed elements
	}
	
	// Reorder the list since it's expected to not have any holes
	for ($i = $offset = 0; $i < $max; ++$i) {
		if (isset($del[$i])) {
			++$offset; // File deleted, add 1 to rename offset
		} else if ($offset) {
			$src_path  = attachment_tempname($thread, $user, $i);
			$dest_path = attachment_tempname($thread, $user, $i - $offset);
			
			rename($src_path, $dest_path); // Main file
			rename("{$src_path}.dat", "{$dest_path}.dat"); // Metadata
			if (file_exists("{$src_path}_t")) {
				rename("{$src_path}_t", "{$dest_path}_t"); // Thumbnail
			}

		}
	}
}

// Get the total size of all attachments uploaded in the temp area
function get_attachments_size($thread, $user, $extra = 0) {
	$size = $extra;
	for ($i = 0; true; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		if (!file_exists($path)) {
			return $size;
		}
		$size += filesize($path);
	}
}

function get_attachments_index($thread, $user) {
	for ($i = 0; true; ++$i) {
		if (!file_exists(attachment_tempname($thread, $user, $i))) {
			return $i;
		}
	}
}

function attachment_name ($id, $thumb = false) { return "attachments/".($thumb ? "t/{$id}.png" : "f/{$id}"); }
function attachment_tempname ($thread, $user, $file_id) { return "temp/attach_{$thread}_{$user}_{$file_id}"; }


function sizeunits($bytes) {
	static $sizes = ['B', 'KB', 'MB', 'GB'];
	for ($i = $sbar = 1; $i < 5; ++$i, $sbar *= 1024) { // $sbar defines the size multiplier
		if ($bytes < $sbar * 1024) {
			// only .00 is really worthless to know so cut that out
			return $qseconds = str_replace('.00', '', sprintf("%04.2f", $bytes / $sbar)).' '.$sizes[$i-1];
		}
	}
}

function resize_image($image, $max_width, $max_height) {
	// Determine thumbnail size based on the aspect ratio
	$width     = imagesx($image);
	$height    = imagesy($image);
	
	// Don't bother if the image is already under the limits
	if ($width <= $max_width && $height <= $max_height) {
		$dst_image = imagecreatetruecolor($width, $height);
		imagecopy($dst_image, $image, 0, 0, 0, 0, $width, $height);
	} else {
		$ratio     = $width / $height;
		if ($ratio > 1) { // width > height
			$n_width    = $max_width;
			$n_height   = round($height * $max_width / $width);
		} else {
			$n_width    = round($width * $max_height / $height);
			$n_height   = $max_height;
		}
		
		$dst_image = imagecreatetruecolor($n_width, $n_height);
		imagecopyresampled($dst_image, $image, 0, 0, 0, 0, $n_width, $n_height, $width, $height);
	}
	return $dst_image;
}