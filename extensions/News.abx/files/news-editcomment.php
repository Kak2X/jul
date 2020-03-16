<?php
	require "lib/function.php";
	require "lib/news_function.php";
	
	/*
		Note: this page only applies the comment actions.
		The forms are shown in the newsheader function
	*/
	
	$_GET['id']    = filter_int($_GET['id']);
	$_GET['post']  = filter_int($_GET['post']);
	$_GET['act']   = filter_string($_GET['act']);
	
	if (!$loguser['id']) 
		news_errorpage("You aren't allowed to do this!");
	
	// Load comment
	if ($_GET['act']) {
		if (!$_GET['id']) 
			news_errorpage("No comment ID specified.");
		$c = $sql->fetchq("SELECT text, user, deleted, pid FROM news_comments WHERE id = {$_GET['id']}");
		if (!$c) 
			news_errorpage("This comment does not exist.");
		else if (!$ismod && ($c['deleted'] || !$c['pid'] || $loguser['id'] != $c['user']))
			news_errorpage("You aren't allowed to do this.");
	}
	
	
	if (!$_GET['act']){
		
		// Has to send this
		if (isset($_POST['submit']) && $_GET['post']){
			check_token($_POST['auth']);
			
			$_POST['text'] = filter_string($_POST['text']);
			if (!trim($_POST['text'])) 
				news_errorpage("Your comment was blank!");
			if (!$sql->resultq("SELECT COUNT(*) FROM news WHERE id = {$_GET['post']}")) 
				news_errorpage("You can't comment to a nonexisting post!");
			
			$lastcomment = $sql->resultq("SELECT date FROM news_comments WHERE user = {$loguser['id']} ORDER BY id DESC");
			if (ctime() - $lastcomment < 10) 
				news_errorpage("You are commenting too fast!");
			
			$sql->queryp("INSERT INTO news_comments (pid, user, text, date) VALUES (?,?,?,?)",
			[$_GET['post'], $loguser['id'], $_POST['text'], ctime()]);
			
			$id = $sql->insert_id();
			return header("Location: {$extName}/news.php?id={$_GET['post']}#$id");
			
		} else {
			news_errorpage("I don't get what you're trying to do here.");
		}
		
	}
	
	else if ($_GET['act'] == 'edit'){
		
		if (isset($_POST['doedit'])){
			check_token($_POST['auth']);
			
			$_POST['text'] = filter_string($_POST['text']);
			if (!trim($_POST['text'])) 
				news_errorpage("Your comment was blank!");
			if (!$sql->resultq("SELECT COUNT(*) FROM news_comments WHERE id = {$_GET['id']}")) 
				news_errorpage("You can't edit a nonexisting comment!");
			
			$sql->queryp("
				UPDATE news_comments SET
					text         = ?,
					lastedituser = ?,
					lasteditdate = ?
				WHERE id = {$_GET['id']}",
			[$_POST['text'], $loguser['id'], ctime()]);
			
			return header("Location: news.php?id={$c['pid']}#{$_GET['id']}");
		} else {
			news_errorpage("I <i>still</i> don't get what you're trying to do here.");
		}		
	}
	
	else if ($_GET['act'] == 'del' && ($ismod || $c['user'] == $loguser['id'])) {
		if (confirmed($msgkey = 'del-cm')) {
			$sql->query("UPDATE news_comments SET deleted = 1 - deleted WHERE id = {$_GET['id']}");
			return header("Location: news.php?id={$c['pid']}#{$_GET['id']}");
		}
		if ($c['deleted']) {
			$message = "Do you want to undelete this comment?";
			$btntext = "Yes";
		} else {
			$message = "Are you sure you want to <b>DELETE</b> this comment?";
			$btntext = "Delete comment";
		}
		$title     = "Warning";
		$form_link = "{$extName}/news-editcomment.php?act=del&id={$_GET['id']}";
		$buttons   = array(
			[BTN_SUBMIT, $btntext],
			[BTN_URL   , "Cancel", "{$extName}/news.php?id={$c['pid']}#{$_GET['id']}"]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	else if ($_GET['act'] == 'erase' && $sysadmin) {
		if (confirmed($msgkey = 'erase-cm', TOKEN_SLAMMER)) {
			$sql->query("DELETE FROM news_comments WHERE id = {$_GET['id']}");
			return header("Location: news.php?id={$c['pid']}");
		}
		
		$title     = "Permanent Deletion";
		$message   = "Are you sure you want to <b>permanently DELETE</b> this comment from the database?";
		$form_link = "{$extName}/news-editcomment.php?act=erase&id={$_GET['id']}";
		$buttons   = array(
			[BTN_SUBMIT, "Delete comment"],
			[BTN_URL   , "Cancel", "{$extName}/news.php?id={$c['pid']}#{$_GET['id']}"]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons, TOKEN_SLAMMER);
	}	
	
	news_footer();