<?php
	require 'lib/function.php';

	$id		= filter_int($_GET['id']);
	$page	= filter_int($_GET['page']);
	$forum	= filter_int($_GET['f']);
	//$action	= filter_string($_GET['action']);
	
	
	// Make sure the forum exists and we can access it
	if ($forum) {
		$fdata = $sql->fetchq("SELECT id, title FROM forums WHERE id = $forum");
		if (!$fdata) {
			errorpage("No announcement forum defined.");
		}
	} else {
		$forum = $miscdata['announcementforum'];
		if (!$forum) {
			errorpage("No announcement forum defined.");
		}
	}
	
	$forumperm 	= get_forum_perm($forum, $loguser['id'], $loguser['group']);	
	if (!has_forum_perm('read', $forumperm)) {
		errorpage("Couldn't enter the forum. You don't have access to this restricted forum.", 'index.php', 'the index page');
	}
	$isadmin	= has_perm('forum-admin');
	$ismod 		= has_forum_perm('mod', $forumperm);
	$canthread	= has_forum_perm('thread', $forumperm);
	
	
	/*
	if ($action && !$canpost)
		errorpage("Silly user, you have no permission to do this!");
	if ($action && !$id)
		errorpage("No announcement specified.");
		*/
		
	$smilies = readsmilies();
	
	pageheader();
	
	
	?>
	<table width=100%>
		<tr>
			<td class='font'>
				<a href="index.php"><?=$config['board-name']?></a><?=(isset($fdata) ? " - <a href='forum.php?id={$fdata['id']}'>".htmlspecialchars($fdata['title'])."</a>" : "")?> - Announcements
			</td>
			<td class='fonts' align=right>
				<?=($canthread ? "<a href='newthread.php?id=$forum&a=1'>Post new announcement</a>" : "")?>
			</td>
		</tr>
	</table>
	<?php
	
	loadtlayout();
	
	$ppp	= isset($_GET['ppp']) ? ((int) $_GET['ppp']) : ($loguser['id'] ? $loguser['postsperpage'] : $config['default-ppp']);
	$ppp	= numrange($ppp, 1, 500); // yeah right
	$min 	= $ppp * $page;
	
	if ($loguser['id']) {
		$readdate = $sql->resultq("SELECT `readdate` FROM `forumread` WHERE `user` = '{$loguser['id']}' AND `forum` = '$forum' LIMIT 1");
		$thread = $sql->fetchq("SELECT id, firstpostdate FROM threads WHERE forum = $forum".(isset($fdata) ? " AND announcement = 1" : "")." ORDER BY firstpostdate DESC LIMIT 1");
		
		if ($loguser['id'] && $thread['firstpostdate'] > $readdate) {
			// Set only the first post as marked so announcement replies won't get marked as read 
			$sql->query("REPLACE INTO threadsread SET `uid` = '{$loguser['id']}', `tid` = '{$thread['id']}', `time` = '".($thread['firstpostdate']++)."', `read` = '1'");
		}
		
		$sql->query("INSERT INTO announcementread (user, forum, readdate) VALUES({$loguser['id']}, $forum, ".ctime().") 
		ON DUPLICATE KEY UPDATE readdate = VALUES(readdate)");
	}
	
	
	$act = $sql->fetchq("SELECT user, COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." GROUP BY user", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
	
	$ufields = userfields();
	$layouts = $sql->query("
		SELECT p.headid, p.signid, MIN(p.id) pid 
		FROM threads t
		LEFT JOIN posts p ON p.thread = t.id
		WHERE t.forum = $forum ".(isset($fdata) ? "AND t.announcement = 1" : "")."
		GROUP BY t.id
		LIMIT $min,$ppp
	");
	preplayouts($layouts);
	
	$anncs = $sql->query("
		SELECT t.title atitle, t.description adesc, MIN(p.id) pid, p.*, COUNT(p.id)-1 replies, u.id uid, u.name, $ufields, u.regdate
		FROM threads t
		LEFT JOIN posts p ON p.thread = t.id
		LEFT JOIN users u ON p.user   = u.id
		WHERE t.forum = $forum ".(isset($fdata) ? "AND t.announcement = 1" : "")."
		GROUP BY t.id
		ORDER BY p.date DESC
		LIMIT $min,$ppp
	");
	$annctotal = $sql->resultq("SELECT COUNT(*) FROM threads WHERE forum = $forum ".(isset($fdata) ? "AND announcement = 1" : ""));
	
	
	$pageend = (int)($annctotal / $ppp);
	$pagelinks = "Pages:";
	if ($annctotal < $ppp)
		$pagelinks = '';
	else for ($i = 0; $i <= $pageend; ++$i) {
		// restrict page range to sane values
		if ($i > 9 && $i < $pageend-9) {
			if ($i < $page-4) {
				$i = min($page-4, $pageend-9);
				$pagelinks .= " ...";
			}
			if ($i > $page+4) {
				$i = $pageend-9;
				$pagelinks .= " ...";
			}
		}
		
		if ($i == $page)
			$pagelinks	.= " ".($i + 1);
		else
			$pagelinks	.= " <a href='announcement.php?f=$forum&page=$i'>".($i + 1)."</a>";
	}

	$controls['quote'] = ''; // Quoting disabled for announcement page
	$controls['ip']    = '';
	$annclist = "<table class='table'><tr><td class='tdbgh center' style='width: 200px'>User</td><td class='tdbgh center' colspan=2>Announcement</td></tr>";
	for ($i = 0; $annc = $sql->fetch($anncs); ++$i) {
		$annclist .= '<tr>';
		$bg = $i % 2 + 1;
		
		$controls['edit'] = "<a href='thread.php?pid={$annc['id']}'>View replies</a> ({$annc['replies']}) | <a href='newreply.php?id={$annc['thread']}&postid={$annc['id']}'>Quote</a>";
		if ($ismod) {
		  $controls['edit'] .= " | <a href='editpost.php?id={$annc['id']}'>Edit</a> | <a href='editpost.php?id={$annc['id']}&action=delete'>Delete</a> | <a href='editpost.php?id={$annc['id']}&action=noob&auth=".generate_token(35)."'>".($annc['noob'] ? "Un" : "")."n00b</a>";
		  if ($isadmin) $controls['ip'] = " | IP: {$annc['ip']}";
		}
		
		$annc['act'] = filter_int($act[$annc['user']]);
		
		$annc['text'] = "<center><b>{$annc['atitle']}</b><div class='fonts'>{$annc['adesc']}</div></center><hr>{$annc['text']}";
		$annclist .= threadpost($annc,$bg,$controls,$forum);
		
	}
	
	echo "$pagelinks<table class='table'>$annclist</table>$pagelinks";
	
	pagefooter();

?>