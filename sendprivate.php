<?php
	require 'lib/function.php';

	$meta['noindex'] = true; // Never index
	
	$id 	= filter_int($_GET['id']); // Quoted PM
	$userid	= filter_int($_GET['userid']); // Who we're sending the PM to
	$id_u = $id ? "id=$id" : "";
	
	$smilies = readsmilies();
	
	if(!$loguser['id']) {
		errorpage("Can't send a private message, because you are not logged in.","index.php", 'return to the index page', 0);
	}
	if($loguser['powerlevel'] <= -2) {
		errorpage("You are permabanned and cannot send private messages.",'private.php','your private message box',0);
	}
	
	if($id) {
		$msg = $sql->fetchq("SELECT * FROM pmsgs WHERE id = $id");

		if (!$msg || $loguser['id'] != $msg['userto']) {
			errorpage("Can't reply to this private message, because it was not sent to you.",'private.php','your private message box',0);
		}
	}

	$bar = "<span class='font'><a href='index.php'>{$config['board-name']}</a> - <a href='private.php'>Private messages</a>";
	
	pageheader();
	
	if (isset($_POST['submit']) || isset($_POST['preview'])) {

		// Received variable
		$subject 	= filter_string($_POST['subject'], true);
		$message 	= filter_string($_POST['message'], true);
		$username 	= filter_string($_POST['username'], true);
		
		$moodid		= filter_int($_POST['moodid']);
		$postlist	= "";
		
		// Does the user actually exist?
		$username = filter_string($_POST['username'], true);
		
		$userid = checkusername($username);

		if ($userid == -1)
			errorpage("Couldn't send the message. You didn't enter an existing username to send the message to.",'private.php','your private message box',2);
		if (!$subject)
			errorpage("Couldn't send the message. You didn't enter a subject.",'private.php','your private message box',2);

		

		

	
		//$subject=str_replace('<','&lt;',$subject);

		$sign = $loguser['signature'];
		$head = $loguser['postheader'];
		
		$numdays 	 = (ctime()-$loguser['regdate'])/86400;
		$tags		 = array();
		$message 	 = doreplace($message,$loguser['posts'],$numdays,$loguser['id'],$tags);
		$tagval		 = json_encode($tags);
		$rsign 		 = doreplace($sign,$loguser['posts'],$numdays,$loguser['id']);
		$rhead 		 = doreplace($head,$loguser['posts'],$numdays,$loguser['id']);

		if (isset($_POST['submit'])) {
			check_token($_POST['auth']);
			
			$headid = getpostlayoutid($head);
			$signid = getpostlayoutid($sign);

			$querycheck = array();
			$sql->queryp("INSERT INTO pmsgs (userto,userfrom,date,ip,msgread,headid,signid,moodid,title,text,tagval) ".
						"VALUES (:userto,:userfrom,:date,:ip,:msgread,:headid,:signid,:moodid,:title,:text,:tagval)",
				[
					'userto'		=> $userid,
					'userfrom'		=> $loguser['id'],
					
					'date'			=> ctime(),
					'ip'			=> $_SERVER['REMOTE_ADDR'],
					'msgread'		=> 0,
					
					'headid'		=> $headid,
					'signid'		=> $signid,
					'moodid'		=> $moodid,
					
					'title'			=> xssfilters($subject),
					'text'			=> xssfilters($message),
					'tagval'		=> $tagval,
				], $querycheck);
			
			if ($querycheck[0])
				errorpage("Private message to $username sent successfully!",'private.php','your private message box',0);
			else
				errorpage("An error occurred while sending the PM.");
		} 
		else {
			loadtlayout();
			$ppost 		= $loguser;

			$ppost['uid']  		= $loguser['id'];
			$ppost['date'] 		= ctime();
			$ppost['headtext']	= $rhead;
			$ppost['signtext'] 	= $rsign;
			$ppost['text'] 		= $message;
					
			$ppost['moodid']	= $moodid;
			$ppost['text']		= $message;
			$ppost['options'] 	= "0|0";
			$ppost['num'] 		= 0;
			$ppost['noob'] 		= 0;
			
			$ppost['act'] 		= $sql->resultq("SELECT COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." AND user = {$userid}");
			if ($isadmin)
				$ip = " | IP: <a href='ipsearch.php?ip={$_SERVER['REMOTE_ADDR']}'>{$_SERVER['REMOTE_ADDR']}</a>";
			
			?>
			<table class='table'><tr><td class='tdbgh center'>Message preview</td></tr></table>
			<table class='table'><tr><td class='tdbg2'><b><?=$subject?></b></td></tr></table>
			<table class='table'><?=threadpost($ppost,1)?></table>
			<?php
		}

	} else {
		
		$postlist 	= "";
		$message 	= "";
		$subject	= "";
		$username	= "";
		$moodid		= 0;
		
		// Quoted PM
		if ($id) {
			
			$user 		= $sql->fetchq("SELECT id, name, posts FROM users WHERE id = {$msg['userfrom']}");
			$message 	= "[quote={$user['name']}]{$msg['text']}[/quote]\r\n";
			$username	= $user['name'];
			$subject	= "Re: {$msg['title']}";

			$postlist="
			<table class='table'>
				<tr>
					<td class='tdbgh center' width=150>User</td>
					<td class='tdbgh center'>Message</td>
				</tr>
				<tr>
					<td class='tdbg1' valign=top>
						<a href='profile.php?id={$user['id']}'>{$user['name']}</a><font class='fonts'><br>
						Posts: {$user['posts']}
					</td>
					<td class='tdbg1' valign=top>
						".doreplace2($msg['text'])."
					</td>
				<tr>
			</table>
			";
		}
				
		if ($userid) $username = $sql->resultq("SELECT name FROM users WHERE id = $userid");
	
	}
	
	
	
	?>
	<?=$bar?>
	<FORM ACTION="sendprivate.php?<?=$id_u?>" NAME=REPLIER METHOD=POST>
	<table class='table'>
		<body onload=window.document.REPLIER.message.focus()>
		<tr>
			<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
			<td class='tdbgh center' colspan=2>&nbsp;</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'><b>Send to:</b></td>
			<td class='tdbg2' colspan=2>
				<input type='text' name=username value="<?=htmlspecialchars($username)?>" size=25 maxlength=25>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'><b>Subject:</b></td>
			<td class='tdbg2' colspan=2>
				<input type='text' name=subject  value="<?=htmlspecialchars($subject)?>" size=60 maxlength=100>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'><b>Message:</b></td>
			<td class='tdbg2' style='width: 800px' valign=top>
				<textarea wrap=virtual name=message ROWS=21 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($message)?></textarea>
			</td>
			<td class='tdbg2' width=*>
				<?=moodlayout(0, $loguser['id'], $moodid)?>
			</td>
		</tr>
			
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2' colspan=2>
				<input type='hidden' name=auth VALUE="<?=generate_token()?>">
				<input type='submit' class=submit name=submit VALUE='Send message'>
				<input type='submit' class=submit name=preview VALUE='Preview message'>&nbsp;&nbsp;&nbsp;
				<?=moodlayout(1, $loguser['id'], $moodid)?>
			</td>
		</tr>
	</table>
	</FORM>
	<br><?=$postlist?>
	<?=$bar?>
	<?php
	
	
	pagefooter();

/*if($action=='delete' and $msg[userto]==$loguserid){
    mysql_query("DELETE FROM pmsgs WHERE id=$id");
    mysql_query("DELETE FROM pmsgs_text WHERE pid=$id");
    print "
      <td class='tdbg1 center'>Thank you, $loguser[name], for deleting the message.
      <br>".redirect('private.php','return to the private message box',0).</table>;
  } */

?>