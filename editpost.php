<?php
	// (fat catgirl here)
	require 'lib/function.php';

	// Stop this insanity.  Never index editpost...
	$meta['noindex'] = true;
	
	$id 	= filter_int($_GET['id']);
	$action = filter_string($_GET['action']);

	
	if (!$loguser['id']) {
		errorpage("You are not logged in.",'login.php', 'log in (then try again)');
	}
	if ($loguser['editing_locked'] == 1) {
		errorpage("You are not allowed to edit your posts.", 'index.php', 'return to the board');
	}
	if (!$id) {	// You dummy
		errorpage("No post ID specified.",'index.php', 'return to the board');
	}

	$post     = $sql->fetchq("SELECT * FROM posts WHERE id = $id");
	if (!$post) {
		errorpage("Post ID #{$id} doesn't exist.",'index.php', 'return to the board');
	}

	$threadid = $post['thread'];
	$thread   = $sql->fetchq("SELECT forum, closed, title FROM threads WHERE id = $threadid");
	$options  = explode("|", $post['options']);

	//$thread['title'] = str_replace(array('<', '>'),array('&lt;','&gt;'),$thread['title']);

	$smilies = readsmilies();

	$forum      = $sql->fetchq("SELECT * FROM forums WHERE id = {$thread['forum']}");
	
	if ($loguser['powerlevel'] < $forum['minpower'] || (!$forum && !$ismod)) // Broken forum
		errorpage("Sorry, but you are not allowed to do this in this restricted forum.", 'index.php' ,'return to the board', 0);
	
	check_forumban($forum['id'], $loguser['id']);
	
	if ($sql->resultq("SELECT 1 FROM forummods WHERE forum = {$forum['id']} and user = {$loguser['id']}"))
		$ismod = 1;
	
	if (!$ismod && ($thread['closed'] || $post['deleted'] || $loguser['id'] != $post['user']))
		errorpage("You are not allowed to edit this post.", "thread.php?id=$threadid", "the thread", 0);
	
	$windowtitle = "{$config['board-name']} -- ".htmlspecialchars($forum['title']).": ".htmlspecialchars($thread['title'])." -- Editing Post";
	pageheader($windowtitle, $forum['specialscheme'], $forum['specialtitle']);

	$attachsel = array();
	$attach_key = "{$threadid}_{$id}";
	
	
/*	print "<font> - ". ($forum['minpower'] <= $loguser['powerlevel'] ? "" : "Restricted thread") ."
";
*/
	
	/*
		Editing a post?
	*/
	if (!$action) {
		
		if (isset($_POST['submit']) || isset($_POST['preview'])) {
			
			$message 	= filter_string($_POST['message'], true);
			$head 		= filter_string($_POST['head'], true);
			$sign 		= filter_string($_POST['sign'], true);
			
			$nosmilies	= filter_int($_POST['nosmilies']);
			$nohtml		= filter_int($_POST['nohtml']);
			$moodid		= filter_int($_POST['moodid']);
			
			
			$user 		= $sql->fetchq("SELECT * FROM users WHERE id = {$loguser['id']}");
			$numposts 	= $user['posts'];
			$numdays 	= (ctime()-$user['regdate'])/86400;
			$message 	= doreplace($message,$numposts,$numdays,$loguser['id']);

			$edited 	= getuserlink($loguser);
			//$edited = str_replace('\'', '\\\'', getuserlink($loguser));
			

			if ($config['allow-attachments']) {
				$savedata  = process_saved_attachments($id);
				$extrasize = $savedata['size'];
				$attachsel = $savedata['del'];
				process_temp_attachments($attach_key, $loguser['id'], $extrasize);
			}
			
			if (isset($_POST['submit'])) {
				check_token($_POST['auth']);
				/*
				if ($loguserid == 1162) {
					xk_ircsend("1|The jceggbert5 dipshit tried to edit another post: ". $id);
					errorpage("");
				}
				*/
				if (($message == "COCKS" || $head == "COCKS" || $sign == "COCKS") || ($message == $head && $head == $sign)) {
					$sql->query("INSERT INTO `ipbans` SET `reason` = 'Idiot hack attempt', `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."'");
					errorpage("NO BONUS");
				}
			
				$headid = $sql->resultp("SELECT `id` FROM `postlayouts` WHERE `text` = ? LIMIT 1", [$head]);
				$signid = $sql->resultp("SELECT `id` FROM `postlayouts` WHERE `text` = ? LIMIT 1", [$sign]);
				if($headid) $head=''; else $headid=0;
				if($signid) $sign=''; else $signid=0;
				
				
				$sql->beginTransaction();
				
				$sql->queryp("UPDATE posts SET `headid` = :headid, `signid` = :signid, `moodid` = :moodid, `options` = :options, `headtext` = :headtext, `text` = :text, `signtext` = :signtext, `edited` = :edited, `editdate` = :editdate WHERE id = $id",
					[
						'text'		=> xssfilters($message),
						'headtext'	=> xssfilters($head),
						'signtext'	=> xssfilters($sign),
						
						'options'	=> $nosmilies . "|" . $nohtml,
						'edited'	=> $edited,
						'editdate' 	=> ctime(),
						
						'headid'	=> $headid,
						'signid'	=> $signid,
						'moodid'	=> $moodid,
						
					]);
				$sql->commit();
				
				if ($config['allow-attachments']) {
					if ($attachsel) {
						remove_attachments(array_keys($attachsel));
					}
					save_attachments($attach_key, $post['user'], $id);
				}
				
				errorpage("Post edited successfully.", "thread.php?pid=$id#$id", 'return to the thread', 0);
				
			} else {
				/*
					Edit preview
				*/
				$data = array(
					// Text
					'message' => $message,	
					'head'    => $head,
					'sign'    => $sign,
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
					// Attachments
					'attach_key' => $id,
					'attach_sel' => $attachsel,
				);
				print preview_post($user, $data, PREVIEW_EDITED);
			}
			
		} else {
			
			// If not, replace the default variables with the original ones from the thread
			
			$message = $post['text'];
			
			if(!$post['headid']) $head = $post['headtext'];
			else $head = $sql->resultq("SELECT text FROM postlayouts WHERE id = {$post['headid']}");
			if(!$post['signid']) $sign = $post['signtext'];
			else $sign = $sql->resultq("SELECT text FROM postlayouts WHERE id = {$post['signid']}");


			$nosmilies 	= $options[0];
			$nohtml		= $options[1];

			$moodid		= $post['moodid'];
			//$user=$sql->fetchq("SELECT name FROM users WHERE id=$post[user]");		
			
		}

		$selsmilies = $nosmilies ? "checked" : "";
		$selhtml    = $nohtml    ? "checked" : "";	
		
		sbr(1,$message);
		sbr(1,$head);
		sbr(1,$sign);
		
		$barlinks = "<span class='font'><a href='index.php'>{$config['board-name']}</a> - <a href='forum.php?id={$forum['id']}'>".htmlspecialchars($forum['title'])."</a> - <a href='thread.php?pid=$id#$id'>".htmlspecialchars($thread['title'])."</a> - Edit post";

		?>
		<?=$barlinks?>
		<form method="POST" ACTION="editpost.php?id=<?=$id?>" enctype="multipart/form-data">
		<table class='table'>
			<tr>
				<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
				<td class='tdbgh center' colspan=2>&nbsp;</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'><b>Header:</td>
				<td class='tdbg2' width=800px valign=top>
					<textarea wrap=virtual name=head ROWS=8 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($head)?></textarea>
				<td class='tdbg2' width=* rowspan=3>
					<?=mood_layout(0, $post['user'], $moodid)?>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'>
					<b>Post:</b>
				</td>
				<td class='tdbg2' width=800px valign=top>
					<textarea wrap=virtual name=message ROWS=12 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;" autofocus><?=htmlspecialchars($message)?></textarea>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'>
					<b>Signature:</b>
				</td>
				<td class='tdbg2' width=800px valign=top>
					<textarea wrap=virtual name=sign ROWS=8 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($sign)?></textarea>
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'>&nbsp;</td>
				<td class='tdbg2' colspan=2>
					<input type='hidden' name=auth VALUE="<?=generate_token()?>">
					<input type='submit' class=submit name=submit VALUE="Edit post">
					<input type='submit' class=submit name=preview VALUE="Preview post">
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'>
					<b>Options:</b>
				</td>
				<td class='tdbg2' colspan=2>
					<input type='checkbox' name="nosmilies" id="nosmilies" value="1" <?=$selsmilies?>><label for="nosmilies">Disable Smilies</label> -
					<input type='checkbox' name="nohtml"    id="nohtml"    value="1" <?=$selhtml   ?>><label for="nohtml">Disable HTML</label> | 
					<?=mood_layout(1, $post['user'], $moodid)?>
				</td>
				<?=quikattach($attach_key, $post['user'], $post['id'], $attachsel)?>
			</tr>
		</table>
		</form>
		<?=$barlinks?>
		<?php
	}
	// Oh come on, noobing posts was/is a fun sport - Kak
	else if ($ismod && $action == 'noob') {
		check_token($_GET['auth'], TOKEN_NOOB);
		$sql->query("UPDATE `posts` SET `noob` = '1' - `noob` WHERE `id` = '$id'");
		errorpage("Post ".($post['noob'] ? "un" : "")."n00bed!", "thread.php?pid=$id#$id",'the post',0);
	}
  
	else if ($action == 'delete'){
		if ($post['deleted']) {
			$message = "Do you want to undelete this post?";
			$btntext = "Yes";
		} else {
			$message = "Are you sure you want to <b>DELETE</b> this post?";
			$btntext = "Delete post";
		}
		$form_link = "editpost.php?action=delete&id={$id}";
		$buttons       = array(
			0 => [$btntext],
			1 => ["Cancel", "thread.php?pid={$id}#{$id}"]
		);
		
		if (confirmpage($message, $form_link, $buttons)) {
			$sql->query("UPDATE posts SET deleted = 1 - deleted WHERE id = {$id}");
			if ($post['deleted']) {
				errorpage("Thank you, {$loguser['name']}, for undeleting the post.","thread.php?pid=$id#$id","return to the thread",0);
			} else {
				errorpage("Thank you, {$loguser['name']}, for deleting the post.","thread.php?pid=$id#$id","return to the thread",0);
			}
		}
	}
	else if ($action == 'erase' && $sysadmin && $config['allow-post-deletion']){
		
		$pcount  = $sql->resultq("SELECT COUNT(*) FROM posts WHERE thread = {$threadid}");
		$message = "Are you sure you want to <b>permanently DELETE</b> this post from the database?";
		if ($pcount <= 1) {
			$message .= "<br><span class='fonts'>You are trying to delete the last post in the thread. If you continue, the thread will be <i>deleted</i> as well.</span>";
		}
		$form_link = "editpost.php?action=erase&id={$id}";
		$buttons       = array(
			0 => ["Delete post"],
			1 => ["Cancel", "thread.php?pid={$id}#{$id}"]
		);
		
		if (confirmpage($message, $form_link, $buttons, TOKEN_SLAMMER)) {
			$sql->beginTransaction();
			$sql->query("DELETE FROM posts WHERE id = $id");
			$list = $sql->getresults("SELECT id FROM attachments WHERE post = {$id}");
			
			if ($pcount <= 1) {
				// We have deleted the last remaining post from a thread
				$sql->query("DELETE FROM threads WHERE id = {$threadid}");
				// Update forum status
				$t1 = $sql->fetchq("SELECT lastpostdate, lastposter	FROM threads WHERE forum = {$thread['forum']} ORDER BY lastpostdate DESC LIMIT 1");
				$sql->queryp("UPDATE forums SET numposts=numposts-1,numthreads=numthreads-1,lastpostdate=?,lastpostuser=? WHERE id={$thread['forum']}", [filter_int($t1['lastpostdate']),filter_int($t1['lastposter'])]);
			
			} else {
				$p = $sql->fetchq("SELECT id,user,date FROM posts WHERE thread = {$threadid} ORDER BY date DESC");
				$sql->query("UPDATE threads SET replies=replies-1, lastposter={$p['user']}, lastpostdate={$p['date']} WHERE id=$threadid");
				$sql->query("UPDATE forums SET numposts=numposts-1 WHERE id={$forum['id']}");
			}
			
			$sql->commit();
			remove_attachments($list, $id);
			if ($pcount <= 1) {
				errorpage("Thank you, {$loguser['name']}, for deleting the post and the thread.","forum.php?id={$thread['forum']}","return to the forum",0);
			} else {
				errorpage("Thank you, {$loguser['name']}, for deleting the post.","thread.php?id=$threadid","return to the thread",0);
			}
		}
	}
	else {
		errorpage("No valid action specified.","thread.php?id={$threadid}#{$threadid}","return to the post",0);
	}

	pagefooter();
	