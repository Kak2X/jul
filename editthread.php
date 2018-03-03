<?php
	require 'lib/function.php';
	

	//$trashid = $config['trash-forum'];
	
	$id 				= filter_int($_GET['id']);
	$_GET['action'] 	= filter_string($_GET['action']);
	$_POST['action'] 	= filter_string($_POST['action']);

	$thread  = $sql->fetchq("
		SELECT 	t.forum, t.title, t.description, t.icon, t.replies, t.lastpostdate, t.lastposter,
		        t.sticky, t.closed, t.announcement, t.user,	f.minpower, f.id valid, f.specialscheme, f.specialtitle
		FROM threads t
		LEFT JOIN forums f ON t.forum = f.id
		WHERE t.id = $id
	");
	
	
	// If the thread is in an invalid forum, don't bother checking if we're a local mod
	if ($thread && $thread['valid'] && !$ismod)
		$ismod = $sql->resultq("SELECT 1 FROM forummods WHERE forum = {$thread['forum']} and user = {$loguser['id']}");
	
	if (!$thread || (!$ismod && !$thread['valid']))
		$message = "This thread doesn't exist."; // or is in an invalid forum
	else if ($thread['minpower'] && $thread['minpower'] > $loguser['powerlevel'])
		$message = "This thread is in a restricted forum.";
	//else if (!$ismod && (($_GET['action'] != 'rename') || ($loguser['id'] != $thread['user'])))
	//	$message = "You aren't allowed to edit this thread.";
	else
		$message = NULL;
	
	if ($message) {
		if (!$ismod) errorpage("You aren't allowed to edit this thread.","thread.php?id={$id}",'the thread');
		else errorpage($message);
	}
	
	
	// Quickmod
	if ($ismod && substr($_GET['action'], 0, 1) == 'q') {
		check_token($_GET['auth'], 32);
		switch ($_GET['action']) {
			case 'qstick':   $update = 'sticky=1'; break;
			case 'qunstick': $update = 'sticky=0'; break;
			case 'qclose':   $update = 'closed=1'; break;
			case 'qunclose': $update = 'closed=0'; break;
			default: return header("Location: thread.php?id={$id}");
		}
		$sql->query("UPDATE threads SET {$update} WHERE id={$id}");
		return header("Location: thread.php?id={$id}");
	}
	elseif ($ismod && $_GET['action'] == 'trashthread') {
		
		pageheader(NULL, $thread['specialscheme'], $thread['specialtitle']);
		
		if (isset($_POST['dotrash'])) {
			
			check_token($_POST['auth'], 18);
			
			$sql->beginTransaction();
			$queryresults = array();
			
			$sql->query("UPDATE threads SET sticky=0, closed=1, forum={$config['trash-forum']} WHERE id='$id'", false, $queryresults);
			$numposts = $sql->resultq("SELECT COUNT(*) FROM posts WHERE thread = $id");
			$t1 = $sql->fetchq("SELECT lastpostdate,lastposter FROM threads WHERE forum={$thread['forum']} ORDER BY lastpostdate DESC LIMIT 1");
			$t2 = $sql->fetchq("SELECT lastpostdate,lastposter FROM threads WHERE forum={$config['trash-forum']} ORDER BY lastpostdate DESC LIMIT 1");
			$sql->queryp("UPDATE forums SET numposts=numposts-$numposts,numthreads=numthreads-1,lastpostdate=?,lastpostuser=? WHERE id={$thread['forum']}", array($t1['lastpostdate'],$t1['lastposter']), $queryresults);
			$sql->queryp("UPDATE forums SET numposts=numposts+$numposts,numthreads=numthreads+1,lastpostdate=?,lastpostuser=? WHERE id={$config['trash-forum']}", array($t2['lastpostdate'],$t2['lastposter']), $queryresults);

			
			// Yeah whatever
			if ($sql->checkTransaction($queryresults))
				errorpage("Thread successfully trashed.","thread.php?id=$id",'return to the thread');
			else
				errorpage("An error occurred while trashing the thread.");
			
			
		
		}			
		
		
		?>
		<table class='table'>
			<form action='editthread.php?action=trashthread&id=<?=$id?>' name='trashcompactor' method='POST'>
				<tr>
					<td class='tdbg1 center'>
						Are you sure you want to trash this thread?<br>
						<input type='hidden' value='trashthread' name='action'>
						<input type='hidden' name='auth' value='<?=generate_token(18)?>'>
						<input type='submit' name='dotrash' value='Trash Thread'> -- <a href='thread.php?id=<?=$id?>'>Cancel</a>
					</td>
				</tr>
			</form>
		</table>
		<?php
	}
	else if ($sysadmin && filter_bool($_POST['deletethread']) && $config['allow-thread-deletion']) {
		
		if (filter_bool($_POST['dodelete'])) {
			
			check_token($_POST['auth'], 18);
			
			// Double-confirm the checkbox 
			$confirm = filter_bool($_POST['reallysure']);
			if (!$confirm) return header("Location: thread.php?id=$id");
			
			
			$sql->beginTransaction();
			
			$queryresults = array();
			
			$sql->query("DELETE FROM threads WHERE id={$id}", false, $queryresults);
			$deleted = $sql->query("DELETE FROM posts WHERE thread = {$id}", false, $queryresults);
			$numdeletedposts = $sql->num_rows($deleted);
			
			// Update forum status
			$t1 = $sql->fetchq("SELECT lastpostdate, lastposter	FROM threads WHERE forum = {$thread['forum']} ORDER BY lastpostdate DESC LIMIT 1");
			$sql->queryp("UPDATE forums SET numposts=numposts-$numdeletedposts,numthreads=numthreads-1,lastpostdate=?,lastpostuser=? WHERE id={$thread['forum']}", array($t1['lastpostdate'],$t1['lastposter']), $queryresults);
			
			$result = $sql->checkTransaction($queryresults);
			
			$fname = $sql->resultq("SELECT title FROM forums WHERE id = {$thread['forum']}");			
			if ($result)
				errorpage("Thank you, {$loguser['name']}, for deleting the thread.", "forum.php?id={$thread['forum']}", $fname);
			else
				errorpage("An error occurred while deleting the thread.");
			
		} else {
			
			pageheader(NULL, $thread['specialscheme'], $thread['specialtitle']);	
			
			?>
			<table class='table'>
			<form action='editthread.php?id=<?=$id?>' method='post'>
				<tr>
					<td class='tdbg1 center'>
						<big><b>DANGER ZONE</b></big><br>
						<br>
						Are you sure you want to permanently <b>delete</b> this thread and <b>all of its posts</b>?<br>
						<br>
						<input type='checkbox' class=radio name='reallysure' value=1> <label for="reallysure">I'm sure</label><br>
						<input type='hidden' name=deletethread VALUE=1>
						<input type='hidden' name=auth value='<?=generate_token(18)?>'><br>
						<input type='submit' name='dodelete' value='Delete thread'> -- <a href='thread.php?id=<?=$id?>'>Cancel</a>
					</td>
				</tr>
			</form>
			</table>
			<?php
			
		}
	}
	else if ($_POST['action'] == 'editthread') {
		
		pageheader(NULL, $thread['specialscheme'], $thread['specialtitle']);	
		
		check_token($_POST['auth']);
		
		$iconid 		= filter_int($_POST['iconid']);
		$custposticon 	= filter_string($_POST['custposticon'], true);
		
		$posticons 			= file('posticons.dat');
			
		if ($custposticon)
			$icon = xssfilters($custposticon);
		else if (isset($posticons[$iconid]))
			$icon = trim($posticons[$iconid]);
		else
			$icon = "";
		
		$title = filter_string($_POST['subject'], true);
		if (!$title) errorpage("Couldn't edit the thread. You haven't entered a subject.");
		
		
		// Check if we can actually mod the forum we're trying to move this thread to
		$forummove 	= filter_int($_POST['forummove']);
		$destexists = $sql->fetchq("SELECT 1 FROM forums WHERE id = {$forummove}");
		$destmod    = $sql->resultq("SELECT 1 FROM forummods WHERE forum = {$forummove} and user = {$loguser['id']}");
			
		if ($ismod && $destexists && ($destmod || $loguser['powerlevel'] > 1)) {
			$forummove 	= filter_int($_POST['forummove']);
			$closed 	= filter_int($_POST['closed']);
			$sticky 	= filter_int($_POST['sticky']);
			$announcement = filter_int($_POST['announcement']);
		} else { // Nice try, but no
			$forummove	= $thread['forum'];
			$closed		= $thread['closed'];
			$sticky		= $thread['sticky'];
			$announcement = $thread['announcement'];
		}
		
		
		// Here we go
		$sql->beginTransaction();
		
		$data = [
			'title'        => htmlspecialchars($title),
			'description'  => xssfilters(filter_string($_POST['description'])),
			'icon'         => $icon,
			'forum'        => $forummove,
			'closed'       => $closed,
			'sticky'       => $sticky,
			'announcement' => $announcement,
		];
		$sql->queryp("UPDATE threads SET ".mysql::setplaceholders($data)." WHERE id = $id", $data);
		
		if ($forummove != $thread['forum']) {
			$numposts = $sql->resultq("SELECT COUNT(*) FROM posts WHERE thread = $id"); //$thread['replies'] + 1;
			$t1 = $sql->fetchq("SELECT lastpostdate,lastposter FROM threads WHERE forum = {$thread['forum']} ORDER BY lastpostdate DESC LIMIT 1");
			$t2 = $sql->fetchq("SELECT lastpostdate,lastposter FROM threads WHERE forum = $forummove ORDER BY lastpostdate DESC LIMIT 1");
			$sql->queryp("UPDATE forums SET numposts=numposts-$numposts,numthreads=numthreads-1,lastpostdate=?,lastpostuser=? WHERE id={$thread['forum']}", array($t1['lastpostdate'],$t1['lastposter']));
			$sql->queryp("UPDATE forums SET numposts=numposts+$numposts,numthreads=numthreads+1,lastpostdate=?,lastpostuser=? WHERE id=$forummove", array($t2['lastpostdate'],$t2['lastposter']));
		}
		
		$sql->commit();
		errorpage("Thank you, {$loguser['name']}, for editing the thread.","thread.php?id=$id",'return to the thread');
	}
	else {
		
		$posticonlist = dothreadiconlist(NULL, $thread['icon']);
		
		$check1[$thread['closed']]='checked=1';
		$check2[$thread['sticky']]='checked=1';
		$check3[$thread['announcement']]='checked=1';
		
		$forummovelist = doforumlist($thread['forum'], 'forummove'); // Return a pretty forum list
		
		
		if ($sysadmin && $config['allow-thread-deletion']) {
			$delthread = " <INPUT type=checkbox class=radio name=deletethread value=1> Delete thread";
		} else
			$delthread = "";
		
		pageheader(NULL, $thread['specialscheme'], $thread['specialtitle']);
		
		?>
		<FORM ACTION='editthread.php?id=<?=$id?>' NAME=REPLIER METHOD=POST>
			<table class='table'>
				<tr>
					<td class='tdbgh' width=150>&nbsp;</td>
					<td class='tdbgh'>&nbsp;</td>
				</tr>
				
				<tr>
					<td class='tdbg1 center'>
						<b>Thread title:</b>
					</td>
					<td class='tdbg2'>
						<input type='text' name=subject VALUE="<?=htmlspecialchars($thread['title'])?>" SIZE=40 MAXLENGTH=100>
					</td>
				</tr>
				<tr>
					<td class='tdbg1 center'>
						<b>Thread description:</b>
					</td>
					<td class='tdbg2'>
						<input type='text' name=description VALUE="<?=htmlspecialchars($thread['description'])?>" SIZE=100 MAXLENGTH=120>
					</td>
				</tr>
				
				<tr>
					<td class='tdbg1 center'>
						<b>Thread icon:</b>
					</td>
					<td class='tdbg2'>
						<?=$posticonlist?>
					</td>
				</tr>
				<?php
				
				/*
					Start of mod-only actions
				*/
		if ($ismod) {
			?>
				<tr>
					<td class='tdbg1 center' rowspan=3>&nbsp;</td>
					<td class='tdbg2'>
						<input type=radio class='radio' name=closed value=0 <?=filter_string($check1[0])?>> Open&nbsp; &nbsp;
						<input type=radio class='radio' name=closed value=1 <?=filter_string($check1[1])?>>Closed
					</td>
				</tr>
				<tr>
					<td class='tdbg2'>
						<input type=radio class='radio' name=sticky value=0 <?=filter_string($check2[0])?>> Normal&nbsp; &nbsp;
						<input type=radio class='radio' name=sticky value=1 <?=filter_string($check2[1])?>>Sticky
					</td>
				</tr>
				<tr>
					<td class='tdbg2'>
						<input type=radio class='radio' name=announcement value=0 <?=filter_string($check3[0])?>> Normal Thread&nbsp; &nbsp;
						<input type=radio class='radio' name=announcement value=1 <?=filter_string($check3[1])?>>Forum Announcement
					</td>
				</tr>
				
				<tr>
					<td class='tdbg1 center'>
						<b>Forum</b>
					</td>
					<td class='tdbg2'>
						<?=$forummovelist?><?=$delthread?>
					</td>
				</tr>
			<?php
		}
				/*
					End of mod-only actions
				*/		
		?>
				<tr>
					<td class='tdbg1'>&nbsp;</td>
					<td class='tdbg2'>
						<input type='hidden' name=action VALUE=editthread>
						<input type='hidden' name=auth value='<?=generate_token()?>'>
						<input type='submit' class=submit name=submit VALUE="Edit thread"></td></tr>
		</table></FORM>
		<?php
	}
	
	pagefooter();
	