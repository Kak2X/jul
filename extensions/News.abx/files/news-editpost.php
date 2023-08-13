<?php

	/*
		News editor
		Edits the contents in the news table
	*/
	
	require "lib/common.php";
	require "lib/news_function.php";
	
	if (!$canwrite)
		news_errorpage("You aren't allowed to edit posts.<br>Click <a href='".actionlink("news.php")."'>here</a> to return to the main page.");	
	
	$_GET['id']	= filter_int($_GET['id']);
	$action = "";
	$smilies = readsmilies();
	
	if (isset($_GET['edit'])){
		
		if (!$_GET['id']) 
			news_errorpage("No post ID specified.");
		
		$news = $sql->fetchq("
			SELECT n.*, $userfields uid
			FROM news n
			LEFT JOIN users u ON n.user = u.id
			WHERE n.id = {$_GET['id']}
		");
		
		if (!$news)
			news_errorpage("The post doesn't exist!");
		if (!$ismod && $loguser['id'] != $news['user'])
			news_errorpage("You have no permission to do this!");
		
		$_POST['title']     = isset($_POST['nname'])     ? $_POST['title']     : $news['title'];
		$_POST['text']      = isset($_POST['text'])      ? $_POST['text']      : $news['text'];
		$_POST['nosmilies'] = isset($_POST['nosmilies']) ? $_POST['nosmilies'] : $news['nosmilies'];
		$_POST['nohtml']    = isset($_POST['nohtml'])    ? $_POST['nohtml']    : $news['nohtml'];
		$_POST['moodid']    = isset($_POST['moodid'])    ? $_POST['moodid']    : $news['moodid'];
		
		if (!isset($_POST['tags']))
			$_POST['tags'] = $sql->getresults("SELECT tag FROM news_tags_assoc WHERE post = {$_GET['id']}");
		else
			$_POST['tags'] = array_map('intval', filter_array($_POST['tags']));
		
		$_POST['customtags'] = filter_string($_POST['customtags']);
		
		if (isset($_POST['submit'])){
			check_token($_POST['auth']);
			
			if (!$_POST['title'] || !$_POST['text'])
				news_errorpage("You have left one of the required fields blank!");
			$valid = (int)$sql->resultq("SELECT COUNT(*) FROM news_tags WHERE id IN (".implode(',', $_POST['tags']).")");
			if ($valid != count($_POST['tags']))
				news_errorpage("At least one invalid tag was selected.");
			
			$sql->beginTransaction();
			
			// Get a filtered list of new tags to enter
			$in = $sql->prepare("INSERT IGNORE INTO news_tags (title) VALUES (?)");
			$taglist = explode(",", $_POST['customtags']);
			foreach ($taglist as $tag) {
				if ($tag = trim($tag)) {
					$sql->execute($in, [$tag]);
					if ($lastid = $sql->insert_id()) // If the tag already exists, $lastid will be 0
						$_POST['tags'][] = $lastid;
				}
			}
				
			// Create the post
			$values = array(
				'title'        => $_POST['title'],
				'text'         => $_POST['text'],
				'lastedituser' => $loguser['id'],
				'lasteditdate' => ctime(),
				'moodid'       => $_POST['moodid'],
			);
			$sql->queryp("UPDATE news SET ".mysql::setplaceholders($values)." WHERE id = {$_GET['id']}", $values);
			
			// Assoc things
			$inassoc = $sql->prepare("INSERT IGNORE INTO news_tags_assoc (post, tag) VALUES ({$_GET['id']},?)");
			foreach ($_POST['tags'] as $key => $tagid) {
				$sql->execute($inassoc, [$tagid]);
			}
			// Remove de-selected
			$keeptags = implode(',', $_POST['tags']);
			$sql->query("DELETE FROM news_tags_assoc WHERE post = {$_GET['id']}".($keeptags ? " AND tag NOT IN ({$keeptags})" : ""));
			
			$sql->commit();
			
			return header("Location: news.php?id={$_GET['id']}");
		}
		
		
		$nosmilies_chk = $_POST['nosmilies'] ? " checked" : "";
		$nohtml_chk    = $_POST['nohtml']    ? " checked" : "";
		
		
		$windowtitle = "Edit post";
		news_header($windowtitle);
		
		$links = array(
			[$xconf['page-title'] , actionlink("news.php")],
			[$windowtitle         , NULL],
		);
		$barlinks = dobreadcrumbs($links); 
		
		$userid = $news['user'];
		
		print $barlinks;
		
		if (isset($_POST['preview'])) { 
			print news_post_preview([
				'id'        => $_GET['id'],
				'user'      => $news['user'],
				'date'      => $news['date'],
				'userdata'  => $news,
				'tags'      => $_POST['tags'],
				'newtags'   => $_POST['customtags'],
				'text'      => $_POST['text'],
				'title'     => $_POST['title'],
				'nosmilies' => $_POST['nosmilies'],
				'nohtml'    => $_POST['nohtml'],
				'moodid'    => $_POST['moodid'],
			]);		
		} 		
		
		$action = "&edit";
		
	}
	else if (isset($_GET['new'])){
		// ACTION : New news
		
		$_POST['title']      = filter_string($_POST['title']);
		$_POST['text']       = filter_string($_POST['text']);
		$_POST['tags']       = array_map('intval', filter_array($_POST['tags']));
		$_POST['customtags'] = filter_string($_POST['customtags']);
		
		$_POST['nosmilies']  = filter_int($_POST['nosmilies']);
		$_POST['nohtml']     = filter_int($_POST['nohtml']);
		$_POST['moodid']     = filter_int($_POST['moodid']);
		
		if (isset($_POST['submit'])){
			check_token($_POST['auth']);
			
			if (!$_POST['title'] || !$_POST['text'])
				news_errorpage("You have left one of the required fields blank!");
			// You don't always select an already existing tag
			if (count($_POST['tags']) > 0) {
				$valid = (int)$sql->resultq("SELECT COUNT(*) FROM news_tags WHERE id IN (".implode(',', $_POST['tags']).")");
				if ($valid != count($_POST['tags']))
					news_errorpage("At least one invalid tag was selected.");
			}
			
			$sql->beginTransaction();
			
			// Get a filtered list of new tags to enter
			$in = $sql->prepare("INSERT IGNORE INTO news_tags (title) VALUES (?)");
			$taglist = explode(",", $_POST['customtags']);
			foreach ($taglist as $tag) {
				if ($tag = trim($tag)) {
					$sql->execute($in, [$tag]);
					if ($lastid = $sql->insert_id()) // If the tag already exists, $lastid will be 0
						$_POST['tags'][] = $lastid;
				}
			}
				
			// Create the post
			$values = array(
				'title'  => $_POST['title'],
				'text'   => $_POST['text'],
				'user'   => $loguser['id'],
				'date'   => ctime(),
				'moodid' => $_POST['moodid'],
			);
			$sql->queryp("INSERT INTO news SET ".mysql::setplaceholders($values), $values);
			$id = $sql->insert_id();
			
			// Assoc things
			$inassoc = $sql->prepare("INSERT INTO news_tags_assoc (post, tag) VALUES ({$id},?)");
			foreach ($_POST['tags'] as $tagid) {
				$sql->execute($inassoc, [$tagid]);
			}
			
			$sql->commit();
			
			return header("Location: news.php?id=$id");
		}
		
		$nosmilies_chk = $_POST['nosmilies'] ? " checked" : "";
		$nohtml_chk    = $_POST['nohtml']    ? " checked" : "";
		
		
		$windowtitle = "New post";
		news_header($windowtitle);
		
		$links = array(
			[$xconf['page-title'] , actionlink("news.php")],
			[$windowtitle         , NULL],
		);
		$barlinks = dobreadcrumbs($links); 
		
		$userid = $loguser['id'];
		
		print $barlinks;
		
		if (isset($_POST['preview'])) { 
		
			// Save ourselves a query if we're (somehow) not needing the picture link
			print news_post_preview([
				'id'        => 0,
				'user'      => $loguser['id'],
				'userdata'  => $loguser,
				'tags'      => $_POST['tags'],
				'newtags'   => $_POST['customtags'],
				'text'      => $_POST['text'],
				'title'     => $_POST['title'],
				'nosmilies' => $_POST['nosmilies'],
				'nohtml'    => $_POST['nohtml'],
				'moodid'    => $_POST['moodid'],
			]);	
		}
		
		$action = "&new";
	}
	else if (isset($_GET['del'])){
		// ACTION: Hide/Unhide from normal users and guests
		if (!$_GET['id'])
			news_errorpage("No news ID specified.");
		// Sanity check. Don't allow this unless you're the news author or an admin
		$news = $sql->fetchq("SELECT user, deleted FROM news WHERE id = {$_GET['id']}");
		if (!$news)
			news_errorpage("The post doesn't exist!");
		if (!$ismod && $loguser['id'] != $news['user'])
			news_errorpage("You have no permission to do this!");
		
		if (confirmed($msgkey = 'del-news')) {
			$sql->query("UPDATE news SET deleted = 1 - deleted WHERE id = {$_GET['id']}");
			return header("Location: news.php");
		}
		
		$title = "Warning";
		if ($news['deleted']) {
			$message = "Do you want to undelete this post?";
			$btntext = "Yes";
		} else {
			$message = "Are you sure you want to <b>DELETE</b> this post?";
			$btntext = "Delete post";
		}
		$form_link = actionlink("news-editpost.php?del&id={$_GET['id']}");
		$buttons   = array(
			[BTN_SUBMIT, $btntext],
			[BTN_URL   , "Cancel", actionlink("news.php?id={$_GET['id']}")]
		);
		
		news_confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	else if (isset($_GET['erase']) && $sysadmin) {
		// ACTION: Delete from database
		if (!$_GET['id']) 
			news_errorpage("No post ID specified.");
		$news = $sql->resultq("SELECT COUNT(*) FROM news WHERE id = {$_GET['id']}");
		if (!$news)
			news_errorpage("The post doesn't exist!");
		
		if (confirmed($msgkey = 'era-news', TOKEN_SLAMMER)) {
			$sql->beginTransaction();
			$sql->query("DELETE FROM news WHERE id = {$_GET['id']}");
			$sql->query("DELETE FROM news_comments WHERE pid = {$_GET['id']}");
			$sql->query("DELETE FROM news_tags_assoc WHERE post = {$_GET['id']}");
			$sql->commit();
			return header("Location: news.php");
		}
		
		$title = "Permanent Deletion";
		$message = "Are you sure you want to <b>permanently DELETE</b> this post from the database?";
		$form_link = actionlink("news-editpost.php?erase&id={$_GET['id']}");
		$buttons       = array(
			[BTN_SUBMIT, "Delete post"],
			[BTN_URL   , "Cancel", actionlink("news.php?id={$_GET['id']}")]
		);
		news_confirm_message($msgkey, $message, $title, $form_link, $buttons, TOKEN_SLAMMER);
	}
	else {
		news_errorpage("No action specified.");
	}
	
?>
	<center>
	<form method='POST' action="<?=actionlink("news-editpost.php?id={$_GET['id']}{$action}")?>">
	
	<table class='table'>
		<tr><td class='tdbgh center b' colspan='3'>Create post</td></tr>		
		<tr>
			<td class='tdbg1 center b' style="width: 150px">Title:</td>
			<td class='tdbg2' colspan="2">
				<input type='text' name='title' style='width: 580px' value="<?= htmlspecialchars($_POST['title']) ?>">
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Message:</td>
			<td class="tdbg2 vatop" id='msgtd'>
				<textarea id='msgtxt' name='text' rows='21' class="w" style='resize:both' wrap='virtual' autofocus><?= htmlspecialchars($_POST['text']) ?></textarea>
			</td>
			<td class="tdbg2">
				<?=mood_layout(0, $userid, $_POST['moodid'])?>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'><b>Tags:</b><div class="fonts">hold CTRL to select multiple</div></td>
			<td class='tdbg2' colspan="2"><?= tag_select($_POST['tags'], $_POST['customtags']) ?></td>
		</tr>	
		<tr>
			<td class='tdbg1 center b'>Options:</td>
			<td class='tdbg2' colspan="2">
				<label><input type="checkbox" name="nosmilies" value=1 <?= $nosmilies_chk ?>> Disable smilies</label> &nbsp;
				<label><input type="checkbox" name="nohtml" value=1 <?= $nohtml_chk ?>> Disable HTML</label>
				| <?=mood_layout(1, $userid, $_POST['moodid'])?>
			</td>
		</tr>
		<tr>
			<td class='tdbg1'></td>
			<td class='tdbg2' colspan="2">
				<input type='submit' name='submit' value='Submit post'> &nbsp; 
				<input type='submit' name='preview' value='Preview post'><?= auth_tag() ?>
			</td>
		</tr>
	</table>
	</form>
	</center>
		
<?php
	print $barlinks;
	
	replytoolbar('msg', $smilies);
	news_footer();
	
	
	function tag_select($sel = array(), $custom = "") {
		global $sql;
		$tags     = load_news_tags();
		$selected = array_flip($sel);
		$out = "";
		foreach ($tags as $id => $data) {
			$out .= "<option value='{$id}' ".(isset($selected[$id]) ? "selected" : "").">".htmlspecialchars($data['title'])."</option>\r\n";
		}
		return 
		"<select multiple='multiple' name='tags[]' id='tags' style='min-width: 180px'>{$out}</select>".
		" - or for <i>new</i> only: ".
		"<input type='text' name='customtags' style='width: 250px' value=\"".htmlspecialchars($custom)."\">".
		" (comma separated)";
	}
