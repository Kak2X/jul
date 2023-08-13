<?php

require "lib/common.php";

pageheader("EZ Ban Hammer");
echo "<div style='white-space:pre;'>";

admincheck();
//print adminlinkbar('admin-slammer.php');

$target_id = $sql->resultq('SELECT id FROM users ORDER BY id DESC LIMIT 1');
$uinfo = $sql->fetchq("SELECT name, lastip FROM users WHERE id = '{$target_id}'");

$_POST['knockout'] = filter_int($_POST['knockout']);

if ($_POST['knockout'] && $_POST['knockout'] != $target_id) {
	echo "Whoops! Someone else took that user to the slammer before you did.\n";
	echo "\n</div>".redirect("admin-slammer.php", 'the slammer (for another go)', 2);
	die();
}
else if ($_POST['knockout']) {
	check_token($_POST['auth'], TOKEN_SLAMMER);
	
	echo "SLAM JAM:\n";
	
	$sql->beginTransaction();
	
	$sql->query("DELETE FROM userratings WHERE userrated = '{$target_id}' OR userfrom = '{$target_id}'");
	$sql->query("DELETE FROM posts_ratings WHERE user = '{$target_id}' OR post IN (SELECT id FROM threads WHERE user = '{$target_id}') OR post IN (SELECT id FROM posts WHERE user = '{$target_id}')");
	$sql->query("DELETE FROM pm_ratings WHERE user = '{$target_id}' OR post IN (SELECT id FROM pm_threads WHERE user = '{$target_id}') OR post IN (SELECT id FROM pm_posts WHERE user = '{$target_id}')");
	echo "Deleted ratings.\n";
	
	//$sql->query("DELETE FROM posts_text WHERE pid IN (SELECT id FROM posts WHERE user = '{$target_id}') LIMIT 50");
	$sql->query("DELETE FROM posts_old WHERE pid IN (SELECT id FROM threads WHERE user = '{$target_id}') OR pid IN (SELECT id FROM posts WHERE user = '{$target_id}')");
	$sql->query("DELETE FROM posts WHERE user = '{$target_id}' OR id IN (SELECT id FROM threads WHERE user = '{$target_id}')"); // LIMIT 50
	$sql->query("DELETE FROM news          WHERE user = '{$target_id}'");
	$sql->query("DELETE FROM news_comments WHERE user = '{$target_id}'");
	echo "Deleted posts.\n";
	
	$sql->query("DELETE FROM threads WHERE user = '{$target_id}'"); // LIMIT 50
	echo "Deleted threads.\n";
	
	// No PMs?
	$sql->query("DELETE FROM pm_posts WHERE user = '{$target_id}' OR id IN (SELECT id FROM pm_threads WHERE user = '{$target_id}')");
	$sql->query("DELETE FROM pm_threads WHERE user = '{$target_id}'");
	$sql->query("DELETE FROM pm_folders WHERE user = '{$target_id}'");
	echo "Deleted private messages.\n";
	
	$sql->query("DELETE FROM users WHERE id = '{$target_id}' LIMIT 1");
	$sql->query("DELETE FROM users_avatars WHERE id='{$target_id}'");
	$sql->query("DELETE FROM users_rpg WHERE uid = '{$target_id}' LIMIT 1");
	$sql->query("DELETE FROM postradar WHERE user = '{$target_id}' OR comp = '{$target_id}'");
	echo "Deleted user data.\n";
	
	$sql->query("DELETE FROM announcementread WHERE user = '{$target_id}'");
	$sql->query("DELETE FROM forumread WHERE user = '{$target_id}'");
	$sql->query("DELETE FROM threadsread WHERE uid = '{$target_id}'");
	$sql->query("DELETE FROM pm_threadsread WHERE uid = '{$target_id}'");
	$sql->query("DELETE FROM pm_foldersread WHERE user = '{$target_id}'");
	echo "Deleted postread data.\n";
	
	$sql->query("DELETE FROM events WHERE user = '{$target_id}'");	
	$sql->query("DELETE FROM users_comments WHERE userfrom = '{$target_id}' OR userto = '{$target_id}'");	
	echo "Deleted misc data.\n";
	
	$sql->commit();
	echo "Success! Finishing job.\n";
	delete_all_avatars($target_id, DAA_MINIPIC | DAA_SKIPSETMOOD);
	echo "Deleted userpics.\n";
	
	$attachids = $sql->getresults("SELECT id FROM attachments WHERE user = '{$target_id}'");
	if ($attachids) {
		remove_attachments($attachids);
	}
	echo "Deleted attachments.\n";
	
	// Altering a table implies an autocommit
	$new_maxid = intval($sql->resultq("SELECT id FROM users ORDER BY id DESC LIMIT 1"));
	$sql->query("ALTER TABLE users AUTO_INCREMENT = {$new_maxid}");
	echo "Max ID set to {$new_maxid}.\n";

	ipban($uinfo['lastip'], "Thanks for playing!");
	echo "Delivered IP ban to ".htmlspecialchars($uinfo['lastip']).".\n";

	xk_ircsend("1|". xk(8) . $uinfo['name'] . xk(7). " (IP " . xk(8) . $uinfo['lastip'] . xk(7) .") is the latest victim of the new EZ BAN button(tm).");

	echo "\n</div>".redirect("admin-slammer.php", 'the slammer (for another go)', 2);
	die();
	
} else {
	
	$threads 	= $sql->getarraybykey("SELECT id, forum, title FROM threads WHERE user = '{$target_id}'", 'id');
	$posts 		= $sql->getarraybykey("SELECT id, thread FROM posts WHERE user = '{$target_id}'", 'id');

	$ct_threads = count($threads);
	$ct_posts   = count($posts);

	echo "Up on the chopping block today is \"".htmlspecialchars($uinfo['name'])."\".\n\n";
	echo "Their last known IP address is \"".htmlspecialchars($uinfo['lastip'])."\".\n\n";

	echo "They have made {$ct_threads} thread(s):\n";
	foreach ($threads as $th)
		echo "{$th['id']}: ".htmlspecialchars($th['title'])." (in forum {$th['forum']})\n";

	echo "\nThey have made {$ct_posts} post(s):\n";
	foreach ($posts as $po)
		echo "{$po['id']}: in thread {$po['thread']}\n";

	?>

	</div>Press the button?
	<form action="?" method="POST">
		<input type="hidden" name="knockout" value="<?=$target_id?>">
		<?= auth_tag(TOKEN_SLAMMER) ?>
		<input type="submit" value="DO IT DAMMIT">
	</form>
	<?php
}