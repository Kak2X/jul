<?php

require 'lib/function.php';

pageheader("{$config['board-name']} - EZ Ban Hammer");
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
	if (filter_string($_POST['auth']) != generate_token(18, $target_id)) {
		echo "The token does not match. Cannot continue.\n\n</div>".redirect("admin-slammer.php", 'the slammer (for another go)', 2);
		die;
	}
		
	echo "SLAM JAM:\n";
	
	$querycheck = array();
	$sql->beginTransaction();

	$sql->query("DELETE FROM threads WHERE user = '{$target_id}'", false, $querycheck); // LIMIT 50
	echo "Deleted threads.\n";

	//$sql->query("DELETE FROM posts_text WHERE pid IN (SELECT id FROM posts WHERE user = '{$target_id}') LIMIT 50");
	$sql->query("DELETE FROM posts WHERE user = '{$target_id}'", false, $querycheck); // LIMIT 50
	echo "Deleted posts.\n";
	
	// No PMs?
	$sql->query("DELETE FROM pmsgs WHERE userfrom = '{$target_id}' OR userto = '{$target_id}'", false, $querycheck);
	echo "Deleted private messages.\n";
	
	$sql->query("DELETE FROM users WHERE id = '{$target_id}' LIMIT 1", false, $querycheck);
	$sql->query("DELETE FROM users_avatars WHERE uid='{$target_id}'");
	$sql->query("DELETE FROM users_rpg WHERE uid = '{$target_id}' LIMIT 1", false, $querycheck);
	echo "Deleted user data.\n";
	
	$sql->query("DELETE FROM announcementread WHERE user = '{$target_id}'");
	$sql->query("DELETE FROM forumread WHERE user = '{$target_id}'");
	$sql->query("DELETE FROM threadsread WHERE uid = '{$target_id}'");
	$sql->query("DELETE FROM events WHERE user = '{$target_id}'", false, $querycheck);	
	echo "Deleted events.\n";
	


	if ($sql->checkTransaction($querycheck)) {
		echo "Success! Finishing job.\n";
		deletefolder("userpic/{$target_id}");
		
		// Altering a table implies an autocommit
		$new_maxid = intval($sql->resultq("SELECT id FROM users ORDER BY id DESC LIMIT 1"));
		$sql->query("ALTER TABLE users AUTO_INCREMENT = {$new_maxid}");
		echo "Max ID set to {$new_maxid}.\n";

		$sql->query("INSERT INTO `ipbans` SET `ip` = '". $uinfo['lastip'] ."', `date` = '". ctime() ."', `reason` = 'Thanks for playing!'");
		echo "Delivered IP ban to {$uinfo['lastip']}.\n";

		xk_ircsend("1|". xk(8) . $uinfo['name'] . xk(7). " (IP " . xk(8) . $uinfo['lastip'] . xk(7) .") is the latest victim of the new EZ BAN button(tm).");

		echo "\n</div>".redirect("admin-slammer.php", 'the slammer (for another go)', 2);
		die();
	} else {
		die("One of the queries has failed. Operation aborted.\n");
	}

}
else {
	
	$threads 	= $sql->getarraybykey("SELECT id, forum, title FROM threads WHERE user = '{$target_id}'", 'id');
	$posts 		= $sql->getarraybykey("SELECT id, thread FROM posts WHERE user = '{$target_id}'", 'id');

	$ct_threads = count($threads);
	$ct_posts   = count($posts);

	echo "Up on the chopping block today is \"{$uinfo['name']}\".\n\n";
	echo "Their last known IP address is \"{$uinfo['lastip']}\".\n\n";

	echo "They have made {$ct_threads} thread(s):\n";
	foreach ($threads as $th)
		echo "{$th['id']}: {$th['title']} (in forum {$th['forum']})\n";

	echo "\nThey have made {$ct_posts} post(s):\n";
	foreach ($posts as $po)
		echo "{$po['id']}: in thread {$po['thread']}\n";

	?>

	</div>Press the button?
	<form action="?" method="POST">
		<input type="hidden" name="knockout" value="<?=$target_id?>">
		<input type="hidden" name="auth" value="<?=generate_token(18, $target_id)?>">
		<input type="submit" value="DO IT DAMMIT">
	</form>
	<?php
}