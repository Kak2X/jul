<?php

require 'lib/function.php';

if (!$config['allow-custom-forums']) {
	return header("Location: index.php");
}

/*
	Version of admin-editforums specifically tailored for normal users to manage their own custom forums
*/

// Initialize/typecast these variables here so we won't get notices or other fun stuff
$_GET['id'] 		= filter_int($_GET['id']);

// Check post/day requirements for creating a new custom forum
$reqs = $sql->fetchq("SELECT postsforcustomforum, daysforcustomforum, maxcustomforums FROM misc");

// Requirements
$postreq    = ($loguser['posts'] >= $reqs['postsforcustomforum']);
$dayreq     = (ctime() - $reqs['daysforcustomforum'] * 86400 > $loguser['regdate']);
// Custom forum limit
$nolimits	= has_perm('bypass-custom-forum-limits');
$numforums	= $sql->resultq("SELECT COUNT(*) FROM forums WHERE custom = 1 AND user = {$loguser['id']}");
$numreq 	= ($nolimits || $numforums < $reqs['maxcustomforums']);

if (!$nolimits && (!has_perm('create-custom-forums') || !$postreq || !$dayreq)) {
	$reason = "";
	if (has_perm('create-custom-forums')) {
		if (!$postreq) $reason .= "<br>You are required to have {$reqs['postsforcustomforum']} posts.";
		if (!$dayreq)  $reason .= "<br>Your account needs to be {$reqs['daysforcustomforum']} days old.";
	}
	errorpage("Sorry, but you aren't allowed to manage custom forums.$reason");
}

if (isset($_POST['edit']) || isset($_POST['edit2'])) {
	check_token($_POST['auth']);
	
	// Not the owner or not custom
	$owner = $sql->resultq("SELECT user FROM forums WHERE id = {$_GET['id']} AND custom = 1");
	if ($_GET['id'] > -1 && $owner !== $loguser['id']) {
		errorpage("You aren't the owner of this forum!", 'index.php', 'the index');
	}

	if ($_GET['id'] <= -1 && !$numreq) {
		errorpage("Sorry, but you have reached the limit of custom forums per user.<br><br>Ask an administrator via PM if you want to delete or transfer ownership of one of your older forums.");
	}
	
	$sql->beginTransaction();
	$querycheck = array();
	
	$title = xssfilters(filter_string($_POST['specialtitle'], true));
	if (isset($_POST['specialcss'])) {
		$title .= "<style type='text/css'>".xssfilters(filter_string($_POST['specialcss'], true))."</style>";
	}
	
	$qadd = $sql->setplaceholders("title","description","specialtitle","hidden","pollstyle");
	$values = array(
		'title' 			=> xssfilters(filter_string($_POST['forumtitle'], true)),
		'description'		=> xssfilters(filter_string($_POST['description'], true)),
		'specialtitle' 		=> $title,
		'hidden' 			=> filter_int($_POST['hideforum']),
		'pollstyle' 		=> filter_int($_POST['pollstyle']),
	);
	
	
	// Permsets
	$groupids = array_keys($grouplist);
	$disallowed = array(GROUP_GUEST, GROUP_BANNED, GROUP_PERMABANNED);
	foreach ($groupids as $x) {
		if (!in_array($x, $disallowed)) {
			$permSet[$x] = filter_int($_POST['group'.$x.'r']) | filter_int($_POST['group'.$x.'p']) | filter_int($_POST['group'.$x.'e'])  | filter_int($_POST['group'.$x.'d']) | filter_int($_POST['group'.$x.'t']) | filter_int($_POST['group'.$x.'m']);
		}
	}
	// Guests and banned are only allowed read access at most 
	$permSet[GROUP_GUEST] 		= filter_int($_POST['allowguests'])      & PERM_FORUM_READ;
	$permSet[GROUP_BANNED] 		= filter_int($_POST['allowbanned'])     & PERM_FORUM_READ;
	$permSet[GROUP_PERMABANNED] = filter_int($_POST['allowpermabanned']) & PERM_FORUM_READ;
	
	if ($_GET['id'] <= -1) {
		// These should be only set when creating the forum
		// since they could override settings forced by an administrator
		$sql->queryp("
			INSERT INTO `forums` SET $qadd,
			`lastpostid`    = '0',
			`catid`         = '0',
			`forder`        = '0',
			`specialscheme` = NULL,
			`custom`	    = '1',
			`user`          = {$loguser['id']}", $values, $querycheck);
		$id	= $sql->insert_id();
		$sql->query("INSERT INTO perm_forums (id) VALUES ($id)", false, $querycheck);
		foreach ($permSet as $i => $val) {
			$sql->query("UPDATE perm_forums SET group$i = $val WHERE id = $id", false, $querycheck);
		}
		// Add ourselves to mod status here
		$sql->query("INSERT INTO perm_forumusers (forum,user,permset) VALUES ($id, {$loguser['id']}, ".PERM_FORUM_ALL.")", false, $querycheck);
		if ($sql->checkTransaction($querycheck)) {
			trigger_error("Created new custom forum \"".$values['title']."\" with ID $id", E_USER_NOTICE);
		} else {
			errorpage("Could not add the forum.");
		}
	} else {
		$sql->queryp("UPDATE `forums` SET $qadd WHERE `id` = '". $_GET['id'] ."'", $values, $querycheck);
		foreach ($permSet as $i => $val) {
			$sql->query("UPDATE perm_forums SET group$i = $val WHERE id = {$_GET['id']}", false, $querycheck);
		}
		if ($sql->checkTransaction($querycheck)) {
			$id	= $_GET['id'];
			trigger_error("Edited custom forum ID $id", E_USER_NOTICE);
		} else {
			errorpage("Could not edit the forum.");
		}
	}
	
	if ($_POST['edit'])
		header("Location: ?id=$id");
	else
		header("Location: ?");

	die;
}

pageheader("{$config['board-name']} -- Edit custom forums");

$pollstyles = array(-2 => 'Disallowed',
                    -1 => 'Normal',
                     0 => 'Force Regular',
                     1 => 'Force Influence');

if ($numreq)					 
	print quick_help("You have ".($nolimits ? "&infin;" : $reqs['maxcustomforums'] - $numforums)." custom forum".(!$nolimits && $reqs['maxcustomforums'] - $numforums == 1 ? "" : "s")." left.", "Notice");

if ($_GET['id']) {
	$forum      = $sql->fetchq("SELECT * FROM `forums` WHERE `id` = '". $_GET['id'] . "' AND custom = 1 AND user = {$loguser['id']}");
	// NOTE: This will filter out guests, banned and permabanned (group ids 6,7,8)
	$groups     = $sql->getresultsbykey("SELECT id, name FROM perm_groups WHERE id < ".GROUP_GUEST." OR id > ".GROUP_PERMABANNED." ORDER BY ord ASC, id ASC");
	if (!$forum) {
		$_GET['id'] = -1;
		// Load sample permissions from first defined forum
		$perms = $sql->fetchq("SELECT * FROM `perm_forums` ORDER BY id ASC LIMIT 1");
		unset($perms['id'], $perms['group'.GROUP_GUEST], $perms['group'.GROUP_BANNED], $perms['group'.GROUP_PERMABANNED]);
		$extraperms = array(1,1,1);
		$forum = array(
			'title' 		=> '',
			'description' 	=> '',
			'hidden' 		=> 0,
			'specialtitle' 	=> '',
			'specialcss' 	=> '',
			'pollstyle' 	=> -1,
		);
	} else {
		$perms = $sql->fetchq("SELECT * FROM `perm_forums` WHERE `id` = '". $_GET['id'] . "'");
		$extraperms = array(
			$perms['group'.GROUP_GUEST], // Only allow read permissions for these three groups
			$perms['group'.GROUP_BANNED],
			$perms['group'.GROUP_PERMABANNED]
		);
		unset($perms['id'], $perms['group'.GROUP_GUEST], $perms['group'.GROUP_BANNED], $perms['group'.GROUP_PERMABANNED]);
		
		if ($forum['specialtitle']) {
			$startcss = strpos($forum['specialtitle'], "<style"); //<style type='text/css'>
			$endcss = strrpos($forum['specialtitle'], "</style>");
			if ($startcss && $endcss) {
				$forum['specialcss'] 	= substr($forum['specialtitle'], $startcss + 23, $endcss);
				$forum['specialtitle'] 	= substr($forum['specialtitle'], 0, $startcss);
			} else {
				$forum['specialcss'] = "";
			}
		}
	}
	
	$numGroups = count($groups);

?>
	<form method="post" action="?id=<?=$_GET['id']?>">
	<table class='table'>
		<tr>
			<td class='tdbgh center' colspan=6>Editing <b><?=($_GET['id'] > -1 ? htmlspecialchars($forum['title']) : "a new forum")?></b></td>
		</tr>

		<tr>
			<td class='tdbgh center'>Forum Name</td>
			<td class='tdbg1' colspan=5><input type="text" name="forumtitle" value="<?=htmlspecialchars($forum['title'])?>"  style="width: 100%;" maxlength="250"></td>
		</tr>

		<tr>
			<td class='tdbgh center' rowspan=4>Description</td>
			<td class='tdbg1' rowspan=4 colspan=3><textarea wrap=virtual name=description ROWS=4 style="width: 100%; resize:none;"><?=htmlspecialchars($forum['description'])?></TEXTAREA></td>
			<td class='tdbgh center' colspan=2>Forum options</td>
		</tr>
		<tr>
			<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
			<td class='tdbg1' style="width: 10%"><input type="checkbox" id="hideforums" name="hideforum" value="1"<?=($forum['hidden'] ? " checked" : "")?>> <label for="hideforums">Hidden</label></td>
		</tr>
		<tr>
			<td class='tdbgh center'>Poll Style</td>
			<td class='tdbg1'><?=dropdownList($pollstyles, $forum['pollstyle'], "pollstyle")?></td>
		</tr>
		<tr>
			<td class='tdbgh center'>&nbsp;</td>
			<td class='tdbg1'>&nbsp;</td>
		</tr>
		
		<tr>
			<td class='tdbgh center'>Custom header</td>
			<td class='tdbg1' colspan=5><textarea wrap=virtual name=specialtitle ROWS=2 COLS=80 style="width: 100%; resize:none;"><?=htmlspecialchars($forum['specialtitle'])?></TEXTAREA></td>
		</tr>
		<tr>
			<td class='tdbgh center'>Custom CSS</td>
			<td class='tdbg1' colspan=5><textarea wrap=virtual name=specialcss ROWS=3 COLS=80 style="width: 100%; resize:vertical;"><?=htmlspecialchars($forum['specialcss'])?></TEXTAREA></td>
		</tr>
		<tr>
			<td class='tdbgc center' colspan=6>
				<input type="submit" name="edit" value="Save and continue">&nbsp;<input type="submit" name="edit2" value="Save and close">
				<input type="hidden" name="auth" value="<?=generate_token()?>">
			</td>
		</tr>

	</table>
	<br>
	<!--<?= quick_help("Global moderators and up can access your forum regardless of settings.","A note on group permissions") ?>-->
	<table class="table">
		<tr><td class="tdbgh center" colspan=42><b>Group Permissions [Read/Post/Edit/Delete/Thread/Mod]</b></td>
<?php
	$oneSet = false;
	while ($perms) {
?>		</tr><tr><?php
		
		// Divide in rows of 6
		$k = 0;
		foreach ($groups as $id => $name) {
			echo "<td class='tdbgh center' colspan=6><b>{$name}</b></td>".($oneSet ? "" : "<td class='tdbg2' rowspan=9999>&nbsp;</td>");
			unset($groups[$id]);
			++$k;
			if ($k == 6) break;
		}
		// Leftovers
		for (; $k < 6; ++$k) {
			echo "<td class='tdbgh center' colspan=6>&nbsp;</td>";
		}
			
	?>		</tr><tr><?php

		for ($i = 0; $i < 6; ++$i) {
	?>			<td class='tdbgc center'><b>R</b></td>
				<td class='tdbgc center'><b>P</b></td>
				<td class='tdbgc center'><b>E</b></td>
				<td class='tdbgc center'><b>D</b></td>
				<td class='tdbgc center'><b>T</b></td>
				<td class='tdbgc center'><b>M</b></td>
	<?php
		}
		
	?>		</tr><tr><?php

		$i = 0;
		foreach ($perms as $permName => $val) {
	?>			<td class='tdbg1 center'><input type="checkbox" name="<?=$permName.'r'?>" value="<?=PERM_FORUM_READ  ?>" <?= ($val & PERM_FORUM_READ   ? "checked" : "") ?>></td>
				<td class='tdbg1 center'><input type="checkbox" name="<?=$permName.'p'?>" value="<?=PERM_FORUM_POST  ?>" <?= ($val & PERM_FORUM_POST   ? "checked" : "") ?>></td>
				<td class='tdbg1 center'><input type="checkbox" name="<?=$permName.'e'?>" value="<?=PERM_FORUM_EDIT  ?>" <?= ($val & PERM_FORUM_EDIT   ? "checked" : "") ?>></td>
				<td class='tdbg1 center'><input type="checkbox" name="<?=$permName.'d'?>" value="<?=PERM_FORUM_DELETE?>" <?= ($val & PERM_FORUM_DELETE ? "checked" : "") ?>></td>
				<td class='tdbg1 center'><input type="checkbox" name="<?=$permName.'t'?>" value="<?=PERM_FORUM_THREAD?>" <?= ($val & PERM_FORUM_THREAD ? "checked" : "") ?>></td>
				<td class='tdbg1 center'><input type="checkbox" name="<?=$permName.'m'?>" value="<?=PERM_FORUM_MOD   ?>" <?= ($val & PERM_FORUM_MOD    ? "checked" : "") ?>></td>
	<?php	unset($perms[$permName]);
			++$i;
			if ($i == 6) break;
		}
		for (; $i < 6; ++$i) {
			echo "<td class='tdbg1 center' colspan=6>&nbsp;</td>";
		}
		$oneSet = true;
	}
	
	$permSet[GROUP_GUEST] 		= filter_int($_POST['allowguests']);
	$permSet[GROUP_BANNED] 		= filter_int($_POST['allowbannned']);
	$permSet[GROUP_PERMABANNED] = filter_int($_POST['allowpermabanned']);
?>
	</tr>
	</table>
	<table class='table' style='border-top: none'>
		<tr>
			<td class='tdbgh center'>Also assign read permissions to:</td>
			<td class='tdbg1'>
				<input type="checkbox" name="allowguests" value="<?=     PERM_FORUM_READ?>" <?= ($extraperms[0] & PERM_FORUM_READ ? "checked" : "") ?>> <?=$grouplist[GROUP_GUEST]['name']?>
				<input type="checkbox" name="allowbanned" value="<?=     PERM_FORUM_READ?>" <?= ($extraperms[1] & PERM_FORUM_READ ? "checked" : "") ?>> <?=$grouplist[GROUP_BANNED]['name']?>
				<input type="checkbox" name="allowpermabanned" value="<?=PERM_FORUM_READ?>" <?= ($extraperms[2] & PERM_FORUM_READ ? "checked" : "") ?>> <?=$grouplist[GROUP_PERMABANNED]['name']?>
			</td>
		</tr>
	</table>
	</form><br>
<?php
}

$forumlist = "
	<tr>
		<td class='tdbgh center' width=150px>Actions</td>
		<td class='tdbgh center'>Forum</td>
		<td class='tdbgh center' width=80>Threads</td>
		<td class='tdbgh center' width=80>Posts</td>
		<td class='tdbgh center' width=15%>Last post</td>
	</tr>
";

$forumquery = $sql->query("
	SELECT f.*, $userfields uid
	FROM forums f
	LEFT JOIN users           u  ON f.lastpostuser = u.id
	WHERE f.custom = 1 AND f.user = {$loguser['id']}
	ORDER BY f.title
");

$modquery = $sql->query("
	SELECT $userfields, f.id forum
	FROM forums f
	INNER JOIN perm_forumusers pu ON f.id    = pu.forum
	INNER JOIN users           u  ON pu.user = u.id
	WHERE f.custom = 1 AND f.user = {$loguser['id']} AND (pu.permset & ".PERM_FORUM_MOD.")
	ORDER BY u.name
");

$forums 	= $sql->fetchAll($forumquery);
$mods 		= $sql->fetchAll($modquery);

if ($numreq) {
	$forumlist .= "<tr><td class='tdbgc center' colspan=5>&lt; <a href='editcustomforums.php?id=-1'>Create a new forum</a> &gt;</td></tr>";
}

foreach ($forums as $forumplace => $forum) {
	
	$m = 0;
	$modlist = "";
	foreach ($mods as $modplace => $mod) {
		if ($mod['forum'] != $forum['id'])
			continue;
		
		// Increase the counter and add the userlink
		$modlist .= ($m++ ? ', ' : '').getuserlink($mod);
		unset($mods[$modplace]);	// Save time for the next loop
	}

	if ($m)
		$modlist = "<span class='fonts'>(moderated by: $modlist)</span>";

	if ($forum['numposts']) {
		$forumlastpost = printdate($forum['lastpostdate']);
		$by = "<span class='fonts'><br>by ". getuserlink($forum, $forum['uid']) . ($forum['lastpostid'] ? " <a href='thread.php?pid=". $forum['lastpostid'] ."#". $forum['lastpostid'] ."'>". $statusicons['getlast'] ."</a>" : "") ."</span>";
	} else {
		$forumlastpost = getblankdate();
		$by = '';
	}
	
	$hidden = $forum['hidden'] ? " <small><i>(hidden)</i></small>" : "";

	if ($_GET['id'] == $forum['id']) {
		$tc1	= 'h';
		$tc2	= 'h';
	} else {
		$tc1	= '1';
		$tc2	= '2';
	}

	$forumlist .= "
	<tr>
		<td class='tdbg{$tc1} center fonts'><a href=editcustomforums.php?id={$forum['id']}>Edit forum</a> / <a href=editcustomfilters.php?f={$forum['id']}>Edit filters</a></td>
		<td class='tdbg{$tc2}'>
			<a href='forum.php?id={$forum['id']}'>".htmlspecialchars($forum['title'])."</a>$hidden<br>
			<font class='fonts'>{$forum['description']}<br>$modlist
		</td>
		<td class='tdbg{$tc1} center'>{$forum['numthreads']}</td>
		<td class='tdbg{$tc1} center'>{$forum['numposts']}</td>
		<td class='tdbg{$tc2} center nobr'><span class='lastpost'>$forumlastpost</span>$by</td>
	</tr>
  ";

	unset($forums[$forumplace]);
}

print "<a href='index.php'>{$config['board-name']}</a> - My forums";
print "<table class='table'>$forumlist</table>";
pagefooter();

function dropdownList($links, $sel, $n) {
	$r	= "<select name=\"$n\">";

	foreach($links as $link => $name) {
		$r	.= "<option value=\"$link\"". ($sel == $link ? " selected" : "") .">$name</option>";
	}

	return $r ."</select>";
}