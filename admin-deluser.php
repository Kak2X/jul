<?php

	require 'lib/function.php';

	$allowedusers	= array(
		$x_hacks['adminip'],		// Xkeeper
		//"24.234.157.232",			// also me	
	);
	
	const SHOW_ALL    = -20;
	const SHOW_BANNED = -10;

	if (!in_array($_SERVER['REMOTE_ADDR'], $allowedusers) && !$sysadmin && $loguser['id'] != 1) errorpage("Nein.");
  
	pageheader();
	
	print adminlinkbar();
	
	// Explicitly check if the 'dodelete' button was pressed
	// Since the forms have been merged to preserve sort options between deletions
	if (isset($_POST['dodelete']) && filter_array($_POST['deluser'])) {
		
		check_token($_POST['auth'], TOKEN_USERDEL);
		
		$dellist = array_keys($_POST['deluser']);
		$delusercnt = 0;
		$delusertext = "";

		$sql->beginTransaction();
		
		foreach($dellist as $key => $id) {
			
			// Although this is a sysadmin-only tool, filter that id properly just in case
			$dellist[$key] = (int) $dellist[$key];
			$id = $dellist[$key];

			$user = $sql->fetchq("SELECT $userfields FROM users u WHERE u.id = $id");
			
			if (!$user) {
				$dellist = array(); // Post-delete refresh likely; exit immediately
				break;
			}

			$id		= $user['id'];
			$name 	= $user['name'];
			
			// Backup the user's data just in case
			$sql->query("INSERT INTO `delusers` ( SELECT * FROM `users` WHERE `id` = '$id' )");
			
			// Since we're moving all posts to the deleted user account, include the user's name as a "signature"
			$namecolor = getnamecolor($user['sex'],$user['powerlevel'],$user['namecolor']);
			$line = addslashes("<br><br>===================<br>[Posted by <span style='color:#$namecolor'><b>$name</b></span>]<br>");
			$sql->query("UPDATE posts SET user     = {$config['deleted-user-id']}, headid = 0, signid = 0, signtext = CONCAT_WS('','$line',signtext) WHERE user = $id");
			$sql->query("UPDATE pmsgs SET userfrom = {$config['deleted-user-id']}, headid = 0, signid = 0, signtext = CONCAT_WS('','$line',signtext) WHERE userfrom = $id");
			
			
			$sql->query("UPDATE threads     SET user         = {$config['deleted-user-id']} WHERE user=$id");
			$sql->query("UPDATE threads     SET lastposter   = {$config['deleted-user-id']} WHERE lastposter=$id");
			$sql->query("UPDATE forums      SET lastpostuser = {$config['deleted-user-id']} WHERE lastpostuser=$id");
			$sql->query("UPDATE events      SET user         = {$config['deleted-user-id']} WHERE user=$id");
			$sql->query("UPDATE pmsgs       SET userto       = {$config['deleted-user-id']} WHERE userto=$id");
			$sql->query("UPDATE attachments SET user         = {$config['deleted-user-id']} WHERE user=$id");
			$sql->query("UPDATE users       SET posts        = -1 * (SELECT COUNT(*) FROM posts WHERE user = {$config['deleted-user-id']}) WHERE id = {$config['deleted-user-id']}");
			
			$sql->query("DELETE FROM forummods WHERE user=$id");
			$sql->query("DELETE FROM userratings WHERE userrated=$id OR userfrom=$id");
			$sql->query("DELETE FROM pollvotes WHERE user=$id");
			$sql->query("DELETE FROM users WHERE id=$id");
			$sql->query("DELETE FROM users_avatars WHERE uid=$id");
			$sql->query("DELETE FROM users_rpg WHERE uid=$id");
			
			$delusertext .= "\r\n<tr><td class='tdbg1 center' width=120>$id</td><td class='tdbg2'><span style='color:#$namecolor'><b>{$user['name']}</b></span></td></tr>";
			$delusercnt++;
			
		}
		$sql->commit();
		
		// Since we're sure the queries have succeeded, now delete the userpic folders
		// Not done on post-delete refresh
		foreach($dellist as $id) {
			deletefolder("userpic/{$id}");
		}

		?>
		<table class='table'>
			<tr>
				<td class='tdbgc center' colspan=2>
					<b><?=$delusercnt?> user<?=($delusercnt != 1 ? "s" : "")?> deleted.</b>
				</td>
			</tr>
			<?=$delusertext?>
		</table>
		<br>
		<?php
	}


	// Layout config for easy add/removal
	$sort_types = array(
		0 => ['lastactivity', 'Last activity'],
		1 => ['regdate', 'Registration date'],
		2 => ['posts', 'Posts'],
		3 => ['threads', 'Threads'],
		4 => ['power', 'Group'],
		5 => ['lastip', 'IP address'],
	);
	
	// Variable fetching
	$_POST['searchname']        = filter_string($_POST['searchname']);
	$_POST['searchip']          = filter_string($_POST['searchip']);
	$_POST['maxposts']          = filter_int($_POST['maxposts']);
	
	// Display only banned users by default
	if (!isset($_POST['sortpower'])) {
		$_POST['sortpower'] = SHOW_BANNED;
	} else {
		$_POST['sortpower'] = filter_int($_POST['sortpower']);
	}
	$_POST['sortord']           = filter_int($_POST['sortord']);
	$_POST['sorttype']          = numrange(filter_int($_POST['sorttype']), 0, count($sort_types) - 1);
	
	$powersel[$_POST['sortpower']]       = 'selected';
	$sortsel[$_POST['sorttype']]         = 'selected';
	$ordsel[$_POST['sortord']]           = 'checked';

 
 ?>
<form method="POST" action="?">
<table class="table">
	<tr><td class="tdbgh center" colspan=2>Sort Options</td></tr>
	<tr><td class="tdbg1 center b" style="width: 300px">User Search:</td>
		<td class="tdbg2"><input type="text" name="searchname" size=30 maxlength=25 value="<?=htmlspecialchars($_POST['searchname'])?>"></td></tr>
	<tr><td class="tdbg1 center b">IP Search:</td>
		<td class="tdbg2"><input type="text" name="searchip"   size=30 maxlength=32 value="<?=htmlspecialchars($_POST['searchip'])?>"></td></tr>
	<tr><td class="tdbg1 center b">Show users with less than:</td>
		<td class="tdbg2"><input type='text' name="maxposts"   size=15 maxlength=9  value="<?=htmlspecialchars($_POST['maxposts'])?>"> posts</td></tr>
	<tr><td class="tdbg1 center b">Powerlevel:</td>
		<td class="tdbg2">
			<select name="sortpower">
				<option value='<?= SHOW_ALL    ?>' <?=filter_string($powersel[SHOW_ALL])    ?>>* Any powerlevel</option>
				<option value='<?= SHOW_BANNED ?>' <?=filter_string($powersel[SHOW_BANNED]) ?>>* All banned (default)</option>
<?php			foreach ($pwlnames as $groupid => $groupname) { ?>
					<option value=<?= $groupid ?> <?= filter_string($groupsel[$groupid]) ?>><?= $groupname ?></option>
<?php			} ?> 
			</select>
		</td>
	</tr>
	<tr><td class="tdbg1 center b">Sort by:</td>
		<td class="tdbg2">
			<select name="sorttype">
<?php			foreach ($sort_types as $sort_id => $x) { ?>
					<option value=<?= $sort_id ?> <?=filter_string($sortsel[$sort_id])?>> <?= $x[1] ?> </option>
<?php			} ?> 
			</select>, 
			<input type="radio" name="sortord" value="0" <?=filter_string($ordsel[0])?>> Descending&nbsp;&nbsp;
			<input type="radio" name="sortord" value="1" <?=filter_string($ordsel[1])?>> Ascending
		</td>
	</tr>
	<tr>
		<td class="tdbg1 center">&nbsp;</td>
		<td class="tdbg2">
			<input type="submit" name="setfilter" value="Apply filters">
		</td>
	</tr>
</table>

<?php

	// WHERE Clause
	$sqlwhere	= array();
	$values		= array();

	if ($_POST['maxposts']) {
		$sqlwhere[] = "`posts` <= :posts";
		$values['posts']        = $_POST['maxposts'];
	}
	if ($_POST['searchip']) {
		$sqlwhere[] = "`lastip` LIKE :searchip";
		$values['searchip']     = "{$_POST['searchip']}%";
	}
	if ($_POST['searchname']) {
		$sqlwhere[] = "`name` LIKE :searchname";
		$values['searchname']   = "%{$_POST['searchname']}%";
	}

	if ($_POST['sortpower'] == SHOW_BANNED) { // Special handler for all banned
		$sqlwhere[] = "(`powerlevel` < 0) ";
	} else if ($_POST['sortpower'] != SHOW_ALL) {
		$sqlwhere[] = "`powerlevel` = :powerlevel";
		$values['powerlevel'] = $_POST['sortpower'];
	}
	
	$wheretxt = $sqlwhere ? "WHERE ". implode(" AND ", $sqlwhere) : "";
	
	// ORDER Clause
	$sortfield = $sort_types[$_POST['sorttype']][0];
	$sortorder = $_POST['sortord'] ? "ASC" : "DESC";
	
	$users = $sql->queryp("
		SELECT u.*, COUNT(t.id) threads 
		FROM users u 
		LEFT JOIN threads t ON u.id = t.user
		{$wheretxt}
		GROUP BY u.id
		ORDER BY {$sortfield} {$sortorder}", $values);
	$usercount	= $sql->num_rows($users);

	// User results / selection table
?>

<table class='table'>
	<tr><td class="tdbgc center b" colspan=9><?=$usercount?> user(s) found.</td></tr>
	<tr>
		<td class="tdbgh center">&nbsp;</td>
		<td class="tdbgh center">Name</td>
		<td class="tdbgh center">Posts</td>
		<td class="tdbgh center">Threads</td>
		<td class="tdbgh center" style="width: 200px">Regdate</td>
		<td class="tdbgh center" style="width: 200px">Last post</td>
		<td class="tdbgh center" style="width: 200px">Last activity</td>
		<td class="tdbgh center">Last URL</td>
		<td class="tdbgh center">IP</td>
	</tr>
	<?php
	while ($user = $sql->fetch($users)) {
		$userlink = getuserlink($user);
		
		if($user['lastposttime']) $lastpost	= printdate($user['lastposttime'], true);
			else $lastpost		= '-';
		if($user['lastactivity'] != $user['regdate']) $lastactivity	= printdate($user['lastactivity']);
			else $lastactivity	= '-';
		if($user['regdate']) $regdate = printdate($user['regdate'], true);
			else $regdate		= '-';

		// Padding of numbers to gray 0
		$textid	= str_pad($user['id'], 5, "x", STR_PAD_LEFT);
		$textid	= str_replace("x", "<font color=#606060>0</font>", $textid);
		$textid	= str_replace("</font><font color=#606060>", "", $textid);

		?>
	<tr>
		<td class="tdbg1 center"><input type="checkbox" name="deluser[<?=$user['id']?>]" value="1"></td>
		<td class="tdbg2"><?= $textid ?> - <?= $userlink ?></td>
		<td class="tdbg1 center"><?= $user['posts'] ?></td>
		<td class="tdbg1 center"><?= $user['threads'] ?></td>
		<td class="tdbg1 center"><?= $regdate ?></td>
		<td class="tdbg1 center"><?= $lastpost ?></td>
		<td class="tdbg1 center"><?= $lastactivity ?></td>
		<td class="tdbg2"><?= $user['lasturl'] ?>&nbsp;</td>
		<td class="tdbg2 center"><?= $user['lastip'] ?></td>
	</tr>
		<?php
	}

  ?>
	<tr>
		<td class="tdbg1" colspan=9>
			<input type="submit" name="dodelete" value="Submit">
			<?= auth_tag(TOKEN_USERDEL) ?>
		</td>
	</tr>
</table>
</form>
<?php

  pagefooter();