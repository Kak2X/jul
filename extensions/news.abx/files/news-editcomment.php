<?php

	require "lib/common.php";
	require "lib/news_function.php";
	
	$_GET['id']    = filter_int($_GET['id']);
	$_GET['post']  = filter_int($_GET['post']);
	$_GET['cqid']  = filter_int($_GET['cqid']);
	$_GET['act']   = filter_string($_GET['act']);
	
	if (!$loguser['id']) 
		news_errorpage("You aren't allowed to do this!");
	
	// Load comment
	if ($_GET['act']) {
		if (!$_GET['id']) 
			news_errorpage("No comment ID specified.");
		$c = $sql->fetchq("
			SELECT p.text, p.user, p.deleted, p.pid, p.moodid, p.date,
			       n.title, n.deleted ndeleted,
			       {$userfields} id
			FROM news_comments p
			LEFT JOIN users u ON p.user = u.id
			LEFT JOIN news  n ON p.pid  = n.id
			WHERE p.id = {$_GET['id']}");
		if (!$c) 
			news_errorpage("This comment does not exist.");
		else if (!$ismod && ($c['deleted'] || $n['deleted'] || !$c['pid'] || $loguser['id'] != $c['user']))
			news_errorpage("You aren't allowed to do this.");
	} else {
		$c = $sql->fetchq("SELECT n.title, n.deleted FROM news n WHERE n.id = {$_GET['post']}");
		if (!$c) 
			news_errorpage("This post does not exist.");
		else if (!$ismod && $c['deleted'])
			news_errorpage("You aren't allowed to do this.");
	}
	
	
	if (!$_GET['act']){
		
		$_POST['text']   = filter_string($_POST['text']);
		$_POST['moodid'] = filter_int($_POST['moodid']);
			
		// Has to send this
		if (isset($_POST['submit']) && $_GET['post']){
			check_token($_POST['auth']);
			
			if (!trim($_POST['text'])) 
				news_errorpage("Your comment was blank!");
			if (!$sql->resultq("SELECT COUNT(*) FROM news WHERE id = {$_GET['post']}")) 
				news_errorpage("You can't comment to a nonexisting post!");
			
			$lastcomment = $sql->resultq("SELECT date FROM news_comments WHERE user = {$loguser['id']} ORDER BY id DESC");
			if (time() - $lastcomment < 10) 
				news_errorpage("You are commenting too fast!");
			
			$values = [
				'pid'    => $_GET['post'],
				'user'   => $loguser['id'],
				'text'   => $_POST['text'],
				'moodid' => $_POST['moodid'],
				'date'   => time(),
			];
			$sql->queryp("INSERT INTO news_comments SET ".mysql::setplaceholders($values), $values);
			
			$id = $sql->insert_id();
			
			return header("Location: news.php?id={$_GET['post']}#$id");
		} else {
			news_header();
			
			// Save quoted text for later
			if ($_GET['cqid']) {
				$quote = $sql->fetchq("
					SELECT u.name, c.text 
					FROM news_comments c
					LEFT JOIN users u ON c.user = u.id
					WHERE c.id = {$_GET['cqid']} AND c.deleted = 0");
				if ($quote) {
					$_POST['text'] = "[quote={$quote['name']}]{$quote['text']}[/quote]\r\n";
				}
			}
			
			$links = array(
				[$xconf['page-title'] , actionlink("news.php")],
				[$c['title']          , actionlink("news.php?id={$_GET['post']}")],
				["New comment"        , null],
			);
			$barlinks = dobreadcrumbs($links); 
			
			$data = [
				'id' => 0,
				'text'     => $_POST['text'],
				'moodid'   => $_POST['moodid'],
			];
			
			print $barlinks;
			if (isset($_POST['preview'])) {
				print news_comment_preview($data);
				replytoolbar('nwedit', readsmilies());
			}
			
			print news_comment_editor($data, $_GET['post']).$barlinks;
		}
	}
	else if ($_GET['act'] == 'edit') {
		
		if (isset($_POST['submit']) || isset($_POST['preview'])) {
			$_POST['text']   = filter_string($_POST['text']);
			$_POST['moodid'] = filter_int($_POST['moodid']);
			
			if (isset($_POST['submit'])) {
				check_token($_POST['auth']);
				
				if (!trim($_POST['text'])) 
					news_errorpage("Your comment was blank!");
				if (!$sql->resultq("SELECT COUNT(*) FROM news_comments WHERE id = {$_GET['id']}")) 
					news_errorpage("You can't edit a nonexisting comment!");
				
				$values = [
					'text'         => $_POST['text'],
					'moodid'       => $_POST['moodid'],
					'lastedituser' => $loguser['id'],
					'lasteditdate' => time(),
				];
				$sql->queryp("UPDATE news_comments SET ".mysql::setplaceholders($values)." WHERE id = {$_GET['id']}", $values);
				
				return header("Location: news.php?id={$c['pid']}#{$_GET['id']}");
			}
		} else {
			$_POST['text']   = $c['text'];
			$_POST['moodid'] = $c['moodid'];
		}
		
		news_header();
		
		$links = array(
			[$xconf['page-title'] , actionlink("news.php")],
			[$c['title']          , actionlink("news.php?id={$c['pid']}")],
			["Edit comment"       , null],
		);
		$barlinks = dobreadcrumbs($links); 
		
		$data = [
			'id'       => $_GET['id'],
			'text'     => $_POST['text'],
			'moodid'   => $_POST['moodid'],
			'date'     => $c['date'],
			'userdata' => $c,
		];
		
		print $barlinks;
		if (isset($_POST['preview'])) {
			print news_comment_preview($data);
			replytoolbar('nwedit', readsmilies());
		}
		print news_comment_editor($data, $_GET['post']).$barlinks;
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
		$form_link = actionlink("news-editcomment.php?act=del&id={$_GET['id']}");
		$buttons   = array(
			[BTN_SUBMIT, $btntext],
			[BTN_URL   , "Cancel", actionlink("news.php?id={$c['pid']}#{$_GET['id']}")]
		);
		news_confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	else if ($_GET['act'] == 'erase' && $sysadmin) {
		if (confirmed($msgkey = 'erase-cm', TOKEN_SLAMMER)) {
			$sql->query("DELETE FROM news_comments WHERE id = {$_GET['id']}");
			return header("Location: news.php?id={$c['pid']}");
		}
		
		$title     = "Permanent Deletion";
		$message   = "Are you sure you want to <b>permanently DELETE</b> this comment from the database?";
		$form_link = actionlink("news-editcomment.php?act=erase&id={$_GET['id']}");
		$buttons   = array(
			[BTN_SUBMIT, "Delete comment"],
			[BTN_URL   , "Cancel", actionlink("news.php?id={$c['pid']}#{$_GET['id']}")]
		);
		news_confirm_message($msgkey, $message, $title, $form_link, $buttons, TOKEN_SLAMMER);
	}	
	
	news_footer();