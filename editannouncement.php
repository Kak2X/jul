<?php

	// Forked from editpost.php, since good god what was in announcement.php was completely outdated
	require 'lib/function.php';
	
	$id 	= filter_int($_GET['id']);
	$action = filter_string($_GET['action']);
	
	$meta['noindex'] = true;
	
	if (!$loguser['id']) {
		errorpage("You are not logged in.",'login.php', 'log in (then try again)');
	}
	if (!$id) {	// You dummy
		errorpage("No post ID specified.",'index.php', 'return to the board');
	}

	$post     = $sql->fetchq("SELECT * FROM announcements WHERE id = $id");
	if (!$post) {
		errorpage("Announcement ID #{$id} doesn't exist.",'index.php', 'return to the board');
	}

	$forumid  = (int) $post['forum'];
	//$thread   = $sql->fetchq("SELECT forum, closed, title FROM threads WHERE id = $forumid");
	$options  = explode("|", $post['options']);

	$forum = $sql->fetchq("SELECT * FROM forums WHERE id = $forumid");
	
	if ((!$ismod && $forumid && !$forum) || ($loguser['powerlevel'] < $forum['minpower'])) // Broken forum / low powerlevel
		errorpage("Sorry, but you are not allowed to do this in this restricted forum.", 'index.php' ,'return to the board', 0);
	
	if ($sql->resultq("SELECT 1 FROM forummods WHERE forum = {$forumid} and user = {$loguser['id']}"))
		$ismod = 1;
	
	if (!$ismod)
		errorpage("You are not allowed to edit this announcement.", "announcement.php?f=$forumid", "the announcements", 0);
	
	
	$smilies = readsmilies();
	
	$windowtitle = "{$config['board-name']} --".($id ? " ".htmlspecialchars($forum['title']).":" : "")." Editing Announcement";
	pageheader($windowtitle, $forum['specialscheme'], $forum['specialtitle']);

	
	
	/*
		Editing a post?
	*/
	if (!$action) {
		
		if (isset($_POST['submit']) || isset($_POST['preview'])) {
			
			$message 	= filter_string($_POST['message'], true);
			$title 		= filter_string($_POST['title'], true);
			
			$head 		= filter_string($_POST['head'], true);
			$sign 		= filter_string($_POST['sign'], true);
			
			$nosmilies	= filter_int($_POST['nosmilies']);
			$nohtml		= filter_int($_POST['nohtml']);
			$moodid		= filter_int($_POST['moodid']);
			
			
			$user 		= $sql->fetchq("SELECT posts, regdate FROM users WHERE id = {$loguser['id']}");
			$numposts 	= $user['posts'];
			$numdays 	= (ctime()-$user['regdate'])/86400;
			$message 	= doreplace($message,$numposts,$numdays,$loguser['id']);

			$edited 	= getuserlink($loguser);
		
			if (isset($_POST['submit'])) {
				check_token($_POST['auth']);
			
				$headid = $sql->resultp("SELECT `id` FROM `postlayouts` WHERE `text` = ? LIMIT 1", [$head]);
				$signid = $sql->resultp("SELECT `id` FROM `postlayouts` WHERE `text` = ? LIMIT 1", [$sign]);
				if($headid) $head=''; else $headid=0;
				if($signid) $sign=''; else $signid=0;
				
				
				// There's only one query to check so we don't bother using a transaction
				$checkquery = array();
				
				$sql->queryp("UPDATE announcements SET `title` = :title, `headid` = :headid, `signid` = :signid, `moodid` = :moodid, `options` = :options, `headtext` = :headtext, `text` = :text, `signtext` = :signtext, `edited` = :edited, `editdate` = :editdate WHERE id = $id",
					[
						'title'		=> xssfilters($title),
						'text'		=> xssfilters($message),
						'headtext'	=> xssfilters($head),
						'signtext'	=> xssfilters($sign),
						
						'options'	=> $nosmilies . "|" . $nohtml,
						'edited'	=> $edited,
						'editdate' 	=> ctime(),
						
						'headid'	=> $headid,
						'signid'	=> $signid,
						'moodid'	=> $moodid,
						
					], $checkquery);
					
				if ($checkquery[0]) {
					errorpage("Announcement edited successfully.", "announcement.php?f=$forumid", 'the announcements', 0);
				} else {
					errorpage("An error occurred while editing the announcement.");
				}
					
				
			} else {
				/*
					Edit preview
				*/
			
				loadtlayout();
				$ppost = $sql->fetchq("SELECT * FROM users WHERE id = {$post['user']}");
				
				$ppost['id']		= $post['id'];	// Required for .topbar preview
				$ppost['uid']		= $post['user'];
				$ppost['num']		= 0;
				$ppost['date']		= $post['date'];
				$ppost['tagval']	= $post['tagval'];
				$ppost['noob']		= $post['noob'];
				
				$ppost['moodid']	= $moodid;
				$ppost['headtext']	= $head;
				$ppost['signtext']	= $sign;
				$ppost['text']		= "<center><b>$title</b></center><hr>$message";
				$ppost['options']	= $nosmilies . "|" . $nohtml;
				$ppost['act'] 		= $sql->resultq("SELECT COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." AND user = {$post['user']}");
				$ppost['piclink']   = $sql->resultq("SELECT weblink FROM users_avatars WHERE user = {$post['user']} AND file = {$moodid}");
				
				// Edited notice
				$ppost['edited']	= $edited;
				$ppost['editdate'] 	= ctime();

	
				if ($isadmin)
					$ip = " | IP: <a href='ipsearch.php?ip={$post['ip']}'>{$post['ip']}</a>";
				
				?>
				<table class='table'>
					<tr>
						<td class='tdbgh center'>
							Announcement preview
						</td>
					</tr>
				</table>
				<table class='table'>
				<?=threadpost($ppost,1,$forumid)?>
				</table>
				<br>
				<?php
			}
			
		} else {
			
			// If not, replace the default variables with the original ones from the thread
			
			$message 	= $post['text'];
			$title		= $post['title'];
			
			if(!$post['headid']) $head = $post['headtext'];
			else $head = $sql->resultq("SELECT text FROM postlayouts WHERE id = {$post['headid']}");
			if(!$post['signid']) $sign = $post['signtext'];
			else $sign = $sql->resultq("SELECT text FROM postlayouts WHERE id = {$post['signid']}");


			$nosmilies 	= $options[0];
			$nohtml		= $options[1];

			$moodid		= $post['moodid'];	
			
		}

		$selsmilies = $nosmilies ? "checked" : "";
		$selhtml    = $nohtml    ? "checked" : "";	
		
		sbr(1,$message);
		sbr(1,$head);
		sbr(1,$sign);
		
		$barlinks = "<span class='font'><a href='index.php'>{$config['board-name']}</a> ".($forumid ? "- <a href='forum.php?id=$forumid'>".htmlspecialchars($forum['title'])."</a> " : "")."- Edit Announcement</span>";

		?>
		<?=$barlinks?>
	<table class='table'>
		<body onload=window.document.REPLIER.message.focus()>
		<FORM ACTION="editannouncement.php?id=<?=$id?>" NAME=REPLIER METHOD=POST>
			<tr>
				<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
				<td class='tdbgh center' colspan=2>&nbsp;</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'><b>Announcement title:</b></td>
				<td class='tdbg2' colspan=2>
					<input type='text' name=title SIZE=70 MAXLENGTH=100 value="<?=htmlspecialchars($title)?>">
				</td>			
			</tr>			
			<tr>
				<td class='tdbg1 center'><b>Header:</td>
				<td class='tdbg2' width=800px valign=top>
					<textarea wrap=virtual name=head ROWS=8 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($head)?></textarea>
				<td class='tdbg2' width=* rowspan=3>
					<?=moodlayout(0, $post['user'], $moodid)?>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'>
					<b>Announcement:</b>
				</td>
				<td class='tdbg2' width=800px valign=top>
					<textarea wrap=virtual name=message ROWS=12 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($message)?></textarea>
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
					<input type='submit' class=submit name=submit VALUE="Edit announcement">
					<input type='submit' class=submit name=preview VALUE="Preview announcement">
				</td>
			</tr>
			
			<tr>
				<td class='tdbg1 center'>
					<b>Options:</b>
				</td>
				<td class='tdbg2' colspan=2>
					<input type='checkbox' name="nosmilies" id="nosmilies" value="1" <?=$selsmilies?>><label for="nosmilies">Disable Smilies</label> -
					<input type='checkbox' name="nohtml"    id="nohtml"    value="1" <?=$selhtml   ?>><label for="nohtml">Disable HTML</label> | 
					<?=moodlayout(1, $post['user'], $moodid)?>
				</td>
			</tr>
		</FORM>
	</table>
		<?=$barlinks?>
		<?php
	}
	else if ($action == 'noob') {
		check_token($_GET['auth'], 35);
			
		$sql->query("UPDATE `announcements` SET `noob` = '1' - `noob` WHERE `id` = '$id'");
		errorpage("Announcement n00bed!", "announcement.php?f=$forumid",'the announcements',0);
	}
  
	else if ($action == 'delete'){
		
		if (isset($_POST['reallydelete'])) {
			check_token($_POST['auth']);

			$sql->query("DELETE FROM announcements WHERE id = $id");
			errorpage("Announcement deleted.","announcement.php?f={$forumid}",'return to the announcements',0);
		}
	
		?>
		<form action='editannouncement.php?action=delete&id=<?=$id?>' method='POST'>
		<table class='table'>
			<tr>
				<td class='tdbg1 center'>
					Are you sure you want to <b>DELETE</b> this announcement?<br>
					<br>
					<input type='hidden' name=auth value="<?=generate_token()?>">
					<input type='submit' class=submit name=reallydelete value='Delete announcement'> - <a href='announcement.php?f=<?=$forumid?>#<?=$id?>'>Cancel</a>
				</td>
			</tr>
		</table>
		</form>
		<?php
	}

	pagefooter();
?>