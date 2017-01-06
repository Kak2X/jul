<?php
	require 'lib/function.php';

	// This file felt completely neglected (for obvious reasons)
	
	$id		= filter_int($_GET['id']);
	$page	= filter_int($_GET['page']);
	$forum	= filter_int($_GET['f']);
	//$action	= filter_string($_GET['action']);
	
	
	// Make sure the forum exists and we can access it
	if ($forum) {
		$fdata = $sql->fetchq("SELECT id, title, minpower FROM forums WHERE id = $forum");
		if (!$fdata || ($fdata['minpower'] && $fdata['minpower'] > $loguser['powerlevel']))
			errorpage("Couldn't enter the forum. You don't have access to this restricted forum.", 'index.php', 'the index page');
	}
	
	if($sql->resultq("SELECT 1 FROM forummods WHERE forum = $forum AND user = {$loguser['id']}"))
		$ismod = 1;
	$canpost = ($isadmin || ($ismod && $forum));
	
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
				<a href=index.php><?=$config['board-name']?></a><?=($forum ? " - <a href='forum.php?id={$fdata['id']}'>".htmlspecialchars($fdata['title'])."</a>" : "")?> - Announcements
			</td>
			<td class='fonts' align=right>
				<?=($canpost ? "<a href='newannouncement.php?id=$forum'>Post new announcement</a>" : "")?>
			</td>
		</tr>
	</table>
	<?php
	
	loadtlayout();
	
	$ppp	= isset($_GET['ppp']) ? ((int) $_GET['ppp']) : ($loguser['id'] ? $loguser['postsperpage'] : $config['default-ppp']);
	$ppp	= max(min($ppp, 500), 1); // yeah right
	$min 	= $ppp * $page;
	
	// NOTE: This does not work well for forum announcements
	// Accounting for forum announcements would require a separate table
	if ($loguser['id'] && !$forum) {
		$sql->query("UPDATE `users` SET `lastannouncement` = (SELECT MAX(`id`) FROM `announcements` WHERE `forum` = 0) WHERE `id` = '". $loguser['id'] ."'");
	}
	
	
	$act = $sql->getresultsbykey("SELECT user, COUNT(*) num FROM announcements WHERE date > ".(ctime() - 86400)." GROUP BY user");
	
	
	$ufields = userfields();
	
	$layouts = $sql->query("SELECT headid, signid FROM announcements WHERE forum = $forum LIMIT $min, $ppp");
	preplayouts($layouts);
	
	// The announcement system is basically a shrunk down version of the threads
	// This may or may not get replaced depending on :effort: and willigness to break the compatibility even more
	$anncs = $sql->query("
		SELECT 	a.*, a.title atitle, 0 num,
				u.id uid, u.name, $ufields, u.regdate
		FROM announcements a
		
		LEFT JOIN users u ON a.user = u.id
		WHERE a.forum = $forum
		ORDER BY a.id DESC
		LIMIT $min,$ppp
	");
	$annctotal = $sql->resultq("SELECT COUNT(*) FROM announcements WHERE forum = $forum");
	
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

	$annclist = "<table class='table'><tr><td class='tdbgh center' width=150>User</td><td class='tdbgh center' colspan=2>Announcement</td></tr>";
	for ($i = 0; $annc = $sql->fetch($anncs); ++$i) {
		$annclist .= '<tr>';
		//$annccount++;
		$bg = $i % 2 + 1;
		
		if ($canpost) {
		  $edit = "<a href='editannouncement.php?id={$annc['id']}'>Edit</a> | <a href='editannouncement.php?id={$annc['id']}&action=delete'>Delete</a> | <a href='editannouncement.php?id={$annc['id']}&action=noob&auth=".generate_token(35)."'>".($annc['noob'] ? "Un" : "")."n00b</a>";
		  if ($isadmin) $ip = " | IP: {$annc['ip']}";
		} else {
			$edit = '&nbsp;';
		}
		
		$annc['act'] = filter_int($act[$annc['user']]);
		
		$annc['text'] = "<center><b>{$annc['atitle']}</b></center><hr>{$annc['text']}";
		$annclist .= threadpost($annc,$bg);
	}
	
	echo "$pagelinks<table class='table'>$annclist</table>$pagelinks";
	
	pagefooter();

?>