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

	$forum = $sql->fetchq("SELECT * FROM forums WHERE id = {$thread['forum']}");
	
	if ($loguser['powerlevel'] < $forum['minpower'] || (!$forum && !$ismod)) // Broken forum
		errorpage("Sorry, but you are not allowed to do this in this restricted forum.", 'index.php' ,'return to the board', 0);
	
	if ($sql->resultq("SELECT 1 FROM forummods WHERE forum = {$forum['id']} and user = {$loguser['id']}"))
		$ismod = 1;
	
	if (!$ismod && ($thread['closed'] || $loguser['id'] != $post['user']))
		errorpage("You are not allowed to edit this post.", "thread.php?id=$threadid", "the thread", 0);
	
	$windowtitle = "{$config['board-name']} -- ".htmlspecialchars($forum['title']).": ".htmlspecialchars($thread['title'])." -- Editing Post";
	pageheader($windowtitle, $forum['specialscheme'], $forum['specialtitle']);

	$attachsel = array();
	
	
	
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
			
			
			$user 		= $sql->fetchq("SELECT posts, regdate FROM users WHERE id = {$loguser['id']}");
			$numposts 	= $user['posts'];
			$numdays 	= (ctime()-$user['regdate'])/86400;
			$message 	= doreplace($message,$numposts,$numdays,$loguser['id']);

			$edited 	= getuserlink($loguser);
			//$edited = str_replace('\'', '\\\'', getuserlink($loguser));
			
			// Mark the already confirmed attachment just for deletion (will be properly removed on submit)
			// Recycle the same query which will be used later in quikattach()
			$extrasize = 0;
			$attach = $sql->getarray("".
				"SELECT a.post, a.id, a.filename, a.size, a.views, a.is_image".
				"	FROM attachments a".
				"	WHERE a.post = {$id}", mysql::USE_CACHE);
			foreach ($attach as $x) {
				if (isset($_POST["removec{$x['id']}"])) {
					$attachsel[$x['id']] = 'checked';
				} else {
					$extrasize += $x['size']; // get_attachment_size() works for temp attachments only, so calculate the extra here
				}
			}
			
			// Process *TEMP* attachments removal
			$cnt = get_attachments_index($threadid, $post['user']);
			$list = array();
			for ($i = 0; $i < $cnt; ++$i) {
				if (filter_int($_POST["remove{$i}"])) {
					$list[] = $i;
				}
			}
			if (!empty($list)) {
				remove_temp_attachments($threadid, $post['user'], $list);
			}
			
			// Upload current attachment
			if (!filter_int($_POST["remove{$i}"]) && isset($_FILES["attachment{$i}"]) && !$_FILES["attachment{$i}"]['error']) {
				upload_attachment($_FILES["attachment{$i}"], $threadid, $post['user'], $i - count($list), $extrasize);
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
				
				if ($attachsel) {
					remove_attachments(array_keys($attachsel));
				}
				save_attachments($threadid, $post['user'], $id);
				errorpage("Post edited successfully.", "thread.php?pid=$id#$id", 'return to the thread', 0);
				
			} else {
				/*
					Edit preview
				*/
			
				loadtlayout();
				$ppost = $sql->fetchq("SELECT * FROM users WHERE id = {$post['user']}");
				//$head = stripslashes($head);
				//$sign = stripslashes($sign);
				//$message = stripslashes($message);
				$ppost['id']		= $post['id'];	// Required for .topbar preview
				$ppost['uid']		= $post['user'];
				$ppost['num']		= $post['num'];
				$ppost['date']		= $post['date'];
				$ppost['tagval']	= $post['tagval'];
				$ppost['noob']		= $post['noob'];
				
				$ppost['moodid']	= $moodid;
				$ppost['headtext']	= $head;
				$ppost['signtext']	= $sign;
				$ppost['text']		= $message;
				$ppost['options']	= $nosmilies . "|" . $nohtml;
				$ppost['act'] 		= $sql->resultq("SELECT COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." AND user = {$post['user']}");

				// Edited notice
				$ppost['edited']	= $edited;
				$ppost['editdate'] 	= ctime();
				
				// Attachments crap
				$ppost['attach'] = array();
				// Include in the preview anything which isn't marked as deleted
				foreach ($attach as $k => $val) {
					if (!isset($attachsel[$val['id']])) {
						$ppost['attach'][] = $attach[$k];
					}
				}
				// As well as the temp attachments
				$ppost['attach']    = array_merge($ppost['attach'], get_temp_attachments($threadid, $post['user']));
				/*
				echo "<pre>";
				print_r($ppost['attach']);
				die;*/
	
				if ($isadmin)
					$ip = " | IP: <a href='ipsearch.php?ip={$post['ip']}'>{$post['ip']}</a>";
				
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
					<?=moodlist($moodid)?>
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
					<input type='checkbox' name="nohtml"    id="nohtml"    value="1" <?=$selhtml   ?>><label for="nohtml">Disable HTML</label>
				</td>
				<?=quikattach($threadid, $post['user'], $post['id'], $attachsel)?>
			</tr>
		</table>
		</form>
		<?=$barlinks?>
		<?php
	}
	// Oh come on, noobing posts was/is a fun sport - Kak
	else if ($ismod && $action == 'noob') {
		check_token($_GET['auth'], 35);
			
		$sql->query("UPDATE `posts` SET `noob` = '1' - `noob` WHERE `id` = '$id'");
		errorpage("Post n00bed!", "thread.php?pid=$id#$id",'the post',0);
	}
  
	else if ($action == 'delete'){
		
		if (isset($_POST['reallydelete'])) {
			check_token($_POST['auth']);
			/*
			if ($loguserid == 1162) { // not like it matters since he's banned anyway <:3
				xk_ircsend("1|The jceggbert5 dipshit tried to delete another post: ". $id);
				errorpage("Thank you, {$loguser['name']}, for deleting the post.","thread.php?id=$threadid","return to the thread",0);
			}
			*/
			$sql->beginTransaction();
			$querycheck = array();
			$sql->query("DELETE FROM posts WHERE id = $id", false, $querycheck);
			$list = $sql->getresults("SELECT id FROM attachments WHERE post = {$id}");
			$p = $sql->fetchq("SELECT id,user,date FROM posts WHERE thread=$threadid ORDER BY date DESC");
			// TODO: This breaks when deleting the last post in a thread, since there is no id, user or date
			//       Check if $p exists and delete the entire thread if it doesn't
			$sql->query("UPDATE threads SET replies=replies-1, lastposter={$p['user']}, lastpostdate={$p['date']} WHERE id=$threadid", false, $querycheck);
			$sql->query("UPDATE forums SET numposts=numposts-1 WHERE id={$forum['id']}", false, $querycheck);
			
			if ($sql->checkTransaction($querycheck)) {
				remove_attachments($list, $id);
				errorpage("Thank you, {$loguser['name']}, for deleting the post.","thread.php?id=$threadid","return to the thread",0);
			} else {
				errorpage("An error occurred while deleting the post.");
			}
		}
	
		?>
		<form action='editpost.php?action=delete&id=<?=$id?>' method='POST'>
		<table class='table'>
			<tr>
				<td class='tdbg1 center'>
					Are you sure you want to <b>DELETE</b> this post?<br>
					<br>
					<input type='hidden' name=auth value="<?=generate_token()?>">
					<input type='submit' class=submit name=reallydelete value='Delete post'> - <a href='thread.php?pid=<?=$id?>#<?=$id?>'>Cancel</a>
				</td>
			</tr>
		</table>
		</form>
		<?php
	}

	pagefooter();
?>