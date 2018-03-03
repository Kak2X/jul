<?php
	require 'lib/function.php';

	// This file felt completely neglected (for obvious reasons)
	
	$id		= filter_int($_GET['id']);
	$page	= filter_int($_GET['page']);
	$forum	= filter_int($_GET['f']);
	//$action	= filter_string($_GET['action']);
	
	
	// Make sure the forum exists and we can access it
	if ($forum) {
		$fdata = $sql->fetchq("SELECT id, title,minpower FROM forums WHERE id = $forum");
		if (!$fdata || $fdata['minpower'] && $fdata['minpower'] > $loguser['powerlevel']) {
			errorpage("Couldn't enter the forum. You don't have access to this restricted forum.", 'index.php', 'the index page');
		}
	} else {
		$fdata = NULL;
		$forum = $config['announcement-forum'];
		if (!$forum) {
			errorpage("No announcement forum defined.");
		}
	}
	
	if($sql->resultq("SELECT 1 FROM forummods WHERE forum = $forum AND user = {$loguser['id']}"))
		$ismod = 1;
	$canthread = ($isadmin || ($ismod && $forum));

		
	$smilies = readsmilies();
	
	pageheader();
	
	
	?>
	<table width=100%>
		<tr>
			<td class='font'>
				<a href=index.php><?=$config['board-name']?></a><?=($fdata ? " - <a href='forum.php?id={$fdata['id']}'>".htmlspecialchars($fdata['title'])."</a>" : "")?> - Announcements
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
	
	// Set better last read date
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
	
	// Syndrome detection
	$act = $sql->getresultsbykey("SELECT user, COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." GROUP BY user");
	
	
	$ufields = userfields();
	$layouts = $sql->query("
		SELECT p.headid, p.signid, MIN(p.id) pid 
		FROM threads t
		LEFT JOIN posts p ON p.thread = t.id
		WHERE t.forum = $forum ".(isset($fdata) ? "AND t.announcement = 1" : "")."
		GROUP BY t.id
		ORDER BY p.date DESC
		LIMIT $min,$ppp
	");
	preplayouts($layouts);
	
	$avatars = $sql->query("
		SELECT p.user, p.moodid, v.weblink, MIN(p.id) pid 
		FROM threads t
		LEFT JOIN posts         p ON t.id     = p.thread
		LEFT JOIN users_avatars v ON p.moodid = v.file
		WHERE t.forum = $forum AND v.user = p.user ".(isset($fdata) ? "AND t.announcement = 1" : "")."
		GROUP BY t.id
		ORDER BY p.date DESC
		LIMIT $min,$ppp
	");
	$avatars = prepare_avatars($avatars);
	
	$showattachments = $config['allow-attachments'] || !$config['hide-attachments'];
	if ($showattachments) {
		$attachments = $sql->fetchq("
			SELECT p.id post, a.id, a.filename, a.size, a.views, a.is_image, MIN(p.id) pid
			FROM threads t
			LEFT JOIN posts p ON p.thread = t.id
			LEFT JOIN attachments a ON p.id = a.post
			WHERE t.forum = $forum ".(isset($fdata) ? "AND t.announcement = 1" : "")." AND a.id IS NOT NULL
			GROUP BY t.id
			ORDER BY p.date DESC
			LIMIT {$min},{$ppp}
		", PDO::FETCH_GROUP, mysql::FETCH_ALL);
	}
	
	// Get every first post for every (announcement) thread in the forum
	$anncs = $sql->query("
		SELECT t.title atitle, t.description adesc, MIN(p.id) pid, p.*,
		       COUNT(p.id)-1 replies, u.id uid, u.name, $ufields, u.regdate
		FROM threads t
		LEFT JOIN posts p ON p.thread = t.id
		LEFT JOIN users u ON p.user   = u.id
		WHERE t.forum = $forum ".(isset($fdata) ? "AND t.announcement = 1" : "")."
		GROUP BY t.id
		ORDER BY p.date DESC
		LIMIT $min,$ppp
	");
	$annctotal = $sql->resultq("SELECT COUNT(*) FROM threads WHERE forum = $forum ".(isset($fdata) ? "AND announcement = 1" : ""));
	
	
	$pagelinks = pagelist("?f=$forum&ppp={$ppp}", $annctotal, $ppp);

	$annclist = "
	<table class='table'>
		<tr>
			<td class='tdbgh center' style='width: 200px'>User</td>
			<td class='tdbgh center' colspan=2>Announcement</td>
		</tr>";
		
	for ($i = 0; $annc = $sql->fetch($anncs); ++$i) {
		$annclist .= '<tr>';
		$bg = $i % 2 + 1;
		
		if ($showattachments && isset($attachments[$annc['id']])) {
			$annc['attach'] = $attachments[$annc['id']];
		}
		
		$edit = "<a href='thread.php?pid={$annc['id']}'>View replies</a> ({$annc['replies']}) | <a href='newreply.php?id={$annc['thread']}&postid={$annc['id']}'>Quote</a>";
		if ($canthread) {
		  $edit .= " | <a href='editpost.php?id={$annc['id']}'>Edit</a> | <a href='editpost.php?id={$annc['id']}&action=delete'>Delete</a> | <a href='editpost.php?id={$annc['id']}&action=noob&auth=".generate_token(TOKEN_NOOB)."'>".($annc['noob'] ? "Un" : "")."n00b</a>";
		  if ($isadmin) $ip = " | IP: {$annc['ip']}";
		} else {
			$edit = '&nbsp;';
		}
		
		$annc['act'] = filter_int($act[$annc['user']]);
		$annc['text'] = "<center><b>{$annc['atitle']}</b><div class='fonts'>{$annc['adesc']}</div></center><hr>{$annc['text']}";
		$annc['piclink'] = filter_string($avatars[$annc['user']][$annc['moodid']]);
		$annclist .= threadpost($annc,$bg,$id);
	}
	
	echo "$pagelinks<table class='table'>$annclist</table>$pagelinks";
	
	pagefooter();
	