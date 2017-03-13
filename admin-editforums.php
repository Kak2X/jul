<?php

require 'lib/function.php';

admincheck('forum-admin');

$preview  = isset($_GET['preview']) ? ((int) $_GET['preview']) : NULL;
$prevtext = isset($preview) ? "&preview=$preview" : "";
// Initialize/typecast these variables here so we won't get notices or other fun stuff
$_GET['id'] 		= filter_int($_GET['id']);
$_GET['delete'] 	= filter_int($_GET['delete']);
$_GET['catid'] 		= filter_int($_GET['catid']);
$_GET['catdelete'] 	= filter_int($_GET['catdelete']);


if (isset($_POST['edit']) || isset($_POST['edit2'])) {
	check_token($_POST['auth']);
	
	// Duplicate prevention
	$forumtitle = xssfilters(filter_string($_POST['forumtitle'], true));
	if ($sql->resultp("SELECT 1 FROM forums WHERE title = ?", [$forumtitle])) {
		errorpage("Sorry, but a forum named like this already exists.");
	}
	
	$sql->beginTransaction();
	$querycheck = array();
	
	if (isset($_POST['specialscheme']) && $_POST['specialscheme'] == -1)
		$_POST['specialscheme'] = NULL;
	else
		$_POST['specialscheme'] = filter_int($_POST['specialscheme']);
	
	$qadd = $sql->setplaceholders("title","description","catid","numthreads","numposts","forder","specialscheme","specialtitle","hidden","pollstyle","custom","user");
	
	$values = array(
		'title' 			=> $forumtitle,
		'description'		=> xssfilters(filter_string($_POST['description'], true)),
		'catid' 			=> filter_int($_POST['catid']),
		'numthreads' 		=> filter_int($_POST['numthreads']),
		'numposts' 			=> filter_int($_POST['numposts']),
		'forder' 			=> filter_int($_POST['forder']), 
		'specialscheme' 	=> $_POST['specialscheme'],
		'specialtitle' 		=> xssfilters(filter_string($_POST['specialtitle'], true)),
		'hidden' 			=> filter_int($_POST['hideforum']),
		'pollstyle' 		=> filter_int($_POST['pollstyle']),
		'custom'			=> filter_int($_POST['custom']),
		'user'				=> filter_int($_POST['user'])
	);
	
	// Permsets
	$groups  = $sql->query("SELECT id FROM perm_groups");
	while ($x = $sql->fetch($groups, PDO::FETCH_NUM)) {
		$permSet[$x[0]] = filter_int($_POST['group'.$x[0].'r']) | filter_int($_POST['group'.$x[0].'p']) | filter_int($_POST['group'.$x[0].'e'])  | filter_int($_POST['group'.$x[0].'d']) | filter_int($_POST['group'.$x[0].'t']) | filter_int($_POST['group'.$x[0].'m']);
	}
	
	
	if ($_GET['id'] <= -1) {
		$sql->queryp("INSERT INTO `forums` SET $qadd, `lastpostid` = '0'", $values, $querycheck);
		$id	= $sql->insert_id();
		$sql->query("INSERT INTO perm_forums (id) VALUES ($id)", false, $querycheck);
		foreach ($permSet as $i => $val) {
			$sql->query("UPDATE perm_forums SET group$i = $val WHERE id = $id", false, $querycheck);
		}
		if ($sql->checkTransaction($querycheck)) {
			trigger_error("Created new forum \"".$values['title']."\" with ID $id", E_USER_NOTICE);
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
			trigger_error("Edited forum ID $id", E_USER_NOTICE);
		} else {
			errorpage("Could not edit the forum.");
		}
	}

	if ($_POST['edit'])
		header("Location: ?id=". $id . $prevtext);
	else
		header("Location: ?".substr($prevtext, 1));

	die();
}
elseif (isset($_POST['delete'])) {
	check_token($_POST['auth']);
	
	$id      = (int) $_GET['delete'];
	$mergeid = (int) $_POST['mergeid'];

	if ($id <= 0)
		errorpage("No forum selected to delete.");
	if ($mergeid <= 0 || $mergeid == $id)
		errorpage("No forum selected to merge to.");

	$querycheck = array();
	$sql->beginTransaction();
	$counts = $sql->fetchq("SELECT `numthreads`, `numposts` FROM `forums` WHERE `id`='$id'");
	$sql->query("UPDATE `threads` SET `forum`='$mergeid' WHERE `forum`='$id'", false, $querycheck);
	$sql->query("UPDATE `misc` SET `announcementforum` = '$mergeid' WHERE `announcementforum` = '$id'", false, $querycheck);
	$sql->query("DELETE FROM `forummods` WHERE `forum`='$id'", false, $querycheck);
	$sql->query("DELETE FROM `forums` WHERE `id`='$id'", false, $querycheck);
	$sql->query("DELETE FROM `perm_forums` WHERE `id`='$id'", false, $querycheck);
	$sql->query("DELETE FROM `perm_forumusers` WHERE `forum`='$id'", false, $querycheck);
	
	

	$lastthread = $sql->fetchq("SELECT * FROM `threads` WHERE `forum`='$mergeid' ORDER BY `lastpostdate` DESC LIMIT 1");
	$sql->query("UPDATE `forums` SET
		`numthreads`=`numthreads`+'{$counts['numthreads']}',
		`numposts`=`numposts`+'{$counts['numposts']}',
		`lastpostdate`='{$lastthread['lastpostdate']}',
		`lastpostuser`='{$lastthread['lastposter']}',
		`lastpostid`='{$lastthread['id']}'
	WHERE `id`='$mergeid'", false, $querycheck);

	if ($sql->checkTransaction($querycheck)) {
		trigger_error("DELETED forum ID $id; merged into forum ID $mergeid", E_USER_NOTICE);
		return header("Location: ?$prevtext");
	} else {
		errorpage("Could not delete the forum.");
	}
}
elseif (isset($_POST['catedit']) || isset($_POST['catedit2'])) {
	check_token($_POST['auth']);	
	
	$qadd = "name=:name,corder=:corder,showalways=:showalways";
	
	$values = array(
		'name' 			=> xssfilters(filter_string($_POST['catname'], true)),
		'corder' 		=> filter_int($_POST['catorder']), 
		'showalways' 	=> filter_int($_POST['showalways']), 
	);
	$querycheck = array();
	if ($_GET['catid'] <= -1) {
		$sql->queryp("INSERT INTO `categories` SET $qadd", $values, $querycheck);
		if (!$querycheck[0]) errorpage("Could not add the category.");
		$id	= $sql->insert_id();
		trigger_error("Created new category \"".$values['name']."\" with ID $id", E_USER_NOTICE);
	} else {
		$sql->queryp("UPDATE `categories` SET $qadd WHERE `id` = '". $_GET['catid'] ."'", $values, $querycheck);
		if (!$querycheck[0]) errorpage("Could not edit the category.");
		$id	= $_GET['catid'];
		trigger_error("Edited category ID $id", E_USER_NOTICE);
	}

	if ($_POST['catedit'])
		header("Location: ?catid=". $id . $prevtext);
	else
		header("Location: ?".substr($prevtext, 1));

	die();
}
elseif (isset($_POST['catdelete'])) {
	check_token($_POST['auth']);
	
	$id      = (int) $_GET['catdelete'];
	$mergeid = (int) $_POST['mergeid'];

	if ($id <= 0)
		errorpage("No category selected to delete.");
	if ($mergeid <= 0)
		errorpage("No category selected to merge to.");
	
	$querycheck = array();
	$sql->beginTransaction();
	$sql->query("UPDATE forums SET catid = $mergeid WHERE catid = $id", false, $querycheck);
	$sql->query("DELETE FROM categories WHERE id = $id", false, $querycheck);
	
	if ($sql->checkTransaction($querycheck)) {
		trigger_error("DELETED category ID $id; merged into category ID $mergeid", E_USER_NOTICE);
		return header("Location: ?$prevtext");
	} else {
		errorpage("Could not delete the category.");
	}
}

$windowtitle = "Editing Forum List";

pageheader($windowtitle);

print adminlinkbar();

$pollstyles = array(-2 => 'Disallowed',
                    -1 => 'Normal',
                     0 => 'Force Regular',
                     1 => 'Force Influence');


if ($_GET['delete']) {
	$fname = $sql->resultq("SELECT title FROM forums WHERE id = {$_GET['delete']}");
	
	if ($fname) {

	?>
	<form method="post" action="?delete=<?=$_GET['delete']?><?=$prevtext?>">
	<table class='table'>
		<tr><td class='tdbgh center'>Deleting <b><?=$fname?></b></td></tr>
		<tr>
			<td class='tdbgc center'>
				You are about to delete forum ID <b><?=$_GET['delete']?></b>.<br>
				<br>
				All announcements and threads will be moved to the forum below.<br>
				<?= doforumlist(0, 'mergeid', 'Choose a forum to merge into...', $_GET['delete']) ?>
			</td>
		</tr>
		<tr>
			<td class='tdbgc center'>
				<input type="submit" name="delete" value="DELETE FORUM"> or <a href="?">Cancel</a>
				<input type="hidden" name="auth" value="<?=generate_token()?>">
			</td>
		</tr>
	</table>
	</form>
	<br>
	<?php
	} else {
		errorpage("This forum doesn't exist.");
	}
}
else if ($_GET['id']) {
	$categories = $sql->getresultsbykey("SELECT id, name FROM categories ORDER BY id");
	$forum      = $sql->fetchq("SELECT * FROM `forums` WHERE `id` = '". $_GET['id'] . "'");
	$groups     = $sql->getresultsbykey("SELECT id, name FROM perm_groups ORDER BY ord ASC, id ASC");
	$users[0] 	= "None";
	$users      += $sql->getresultsbykey("SELECT id, name FROM users WHERE `group` NOT IN (".GROUP_BANNED.",".GROUP_PERMABANNED.")");
	
	if (!$forum) {
		$_GET['id'] = -1;
		// Initialize group permissions to 0
		foreach ($groups as $id => $title) {
			$perms['group'.$id] = 0;
		}
		$forum = array(
			'title' 		=> '',
			'description' 	=> '',
			'hidden' 		=> 0,
			'specialtitle' 	=> '',
			'specialcss' 	=> '',
			'pollstyle' 	=> -1,
			'custom'		=> 0,
			'user'			=> 0,
			'catid'			=> 0,
			'numthreads'	=> 0,
			'numposts'		=> 0,
			'forder'		=> 1,
			'specialscheme' => NULL,
		);
		// Load sample permissions from first defined forum
		$perms = $sql->fetchq("SELECT * FROM `perm_forums` ORDER BY id ASC LIMIT 1");
		unset($perms['id']);
	} else {
		if (!isset($categories[$forum['catid']]))
			$categories[$forum['catid']] = "Unknown category #" . $forum['catid'];
		$perms = $sql->fetchq("SELECT * FROM `perm_forums` WHERE `id` = '". $_GET['id'] . "'");
		unset($perms['id']);
	}
	
	$numGroups = count($perms);

?>
	<form method="post" action="?id=<?=$_GET['id']?><?=$prevtext?>">
	<table class='table'>
		<tr>
			<td class='tdbgh center' colspan=6>Editing <b><?=($forum ? htmlspecialchars($forum['title']) : "a new forum")?></b></td>
		</tr>

		<tr>
			<td class='tdbgh center'>Forum Name</td>
			<td class='tdbg1' colspan=4><input type="text" name="forumtitle" value="<?=htmlspecialchars($forum['title'])?>"  style="width: 100%;" maxlength="250"></td>
			<td class='tdbg1' width=10%>
				<input type="checkbox" id="hideforums" name="hideforum" value="1"<?=($forum['hidden'] ? " checked" : "")?>> <label for="hideforums">Hidden</label>
			</td>
		</tr>

		<tr>
			<td class='tdbgh center' rowspan=4>Description</td>
			<td class='tdbg1' rowspan=4 colspan=3><textarea wrap=virtual name=description ROWS=4 style="width: 100%; resize:none;"><?=htmlspecialchars($forum['description'])?></TEXTAREA></td>
			<td class='tdbgh center' colspan=2>Custom forum options</td>
		</tr>
		<tr>
			<td class='tdbgh center'>&nbsp;</td>
			<td class='tdbg1'><input type="checkbox" id="custom" name="custom" value="1"<?=($forum['custom'] ? " checked" : "")?>> <label for="custom">Custom forum</label></td>
		</tr>
		<tr>
			<td class='tdbgh center'>Forum owner</td>
			<td class='tdbg1'><?=dropdownList($users, $forum['user'], "user")?></td>
		</tr>
		<tr>
			<td class='tdbgc center'>&nbsp;</td>
			<td class='tdbgc'>&nbsp;</td>
		</tr>

		<tr>
			<td class='tdbgh center'  width='10%'>Number of Threads</td>
			<td class='tdbg1' width='24%'><input type="text" name="numthreads" maxlength="8" size="10" value="<?=($forum['numthreads'] ? $forum['numthreads'] : "0")?>" class="right"></td>
			<td class='tdbgh center'  width='10%'>Forum order</td>
			<td class='tdbg1' width='23%'><input type="text" name="forder" maxlength="8" size="10" value="<?=($forum['forder'] ? $forum['forder'] : "0")?>" class="right"></td>
			<td class='tdbgh center'  width='10%'>Poll Style</td>
			<td class='tdbg1' width='23%'><?=dropdownList($pollstyles, $forum['pollstyle'], "pollstyle")?></td>
		</tr>

		<tr>
			<td class='tdbgh center' >Number of Posts</td>
			<td class='tdbg1'><input type="text" name="numposts" maxlength="8" size="10" value="<?=($forum['numposts'] ? $forum['numposts'] : "0")?>" class="right"></td>
			<td class='tdbgh center' >Special Scheme</td>
			<td class='tdbg1'><?=doschemeList(true, $forum['specialscheme'], 'specialscheme')?></td>
			<td class='tdbgh center' >Category</td>
			<td class='tdbg1'><?=dropdownList($categories, $forum['catid'], "catid")?></td>
		</tr>
		
		<tr>
			<td class='tdbgh center'>Custom header</td>
			<td class='tdbg1' colspan=5><textarea wrap=virtual name=specialtitle ROWS=2 COLS=80 style="width: 100%; resize:none;"><?=htmlspecialchars($forum['specialtitle'])?></TEXTAREA></td>
		<tr>	
			<td class='tdbgc center' colspan=6>
				<input type="submit" name="edit" value="Save and continue">&nbsp;<input type="submit" name="edit2" value="Save and close">
				<input type="hidden" name="auth" value="<?=generate_token()?>">
			</td>
		</tr>

	</table>
	
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
?>
	</tr>
	</table>
	</form><br>
<?php
}
else if ($_GET['catdelete']) {
	$categories = $sql->getresultsbykey("SELECT id, name FROM categories ORDER BY corder");
	$categories[-1] = "Choose a category to merge into...";
	
	if (isset($categories[$_GET['catdelete']])) {
		$catname = htmlspecialchars($categories[$_GET['catdelete']]);
		unset($categories[$_GET['catdelete']]);

	?>
	<form method="post" action="?catdelete=<?=$_GET['catdelete']?><?=$prevtext?>">
	<table class='table'>
		<tr><td class='tdbgh center'>Deleting <b><?=$catname?></b></td></tr>
		<tr>
			<td class='tdbgc center'>
				You are about to delete category ID <b><?=$_GET['catdelete']?></b>.<br>
				<br>
				All forums will be moved to the category below.<br>
				<?= dropdownList($categories, -1, "mergeid") ?>
			</td>
		</tr>
		<tr>
			<td class='tdbgc center'>
				<input type="submit" name="catdelete" value="DELETE CATEGORY"> or <a href="?">Cancel</a>
				<input type="hidden" name="auth" value="<?=generate_token()?>">
			</td>
		</tr>
	</table>
	</form>
	<br>
	<?php
	} else {
		errorpage("This category doesn't exist.");
	}	
}
else if ($_GET['catid']) {
	
	$category = $sql->fetchq("SELECT * FROM `categories` WHERE `id` = '". $_GET['catid'] . "'");
	if (!$category) {
		$_GET['catid'] = -1;
	}

?>
	<form method="post" action="?catid=<?=$_GET['catid']?><?=$prevtext?>">
	<table class='table'>
		<tr>
			<td class='tdbgh center' colspan=6>Editing <b><?=($category ? htmlspecialchars($category['name']) : "a new category")?></b></td>
		</tr>

		<tr>
			<td class='tdbgh center'>Category Name</td>
			<td class='tdbg1' colspan=3><input type="text" name="catname" value="<?=htmlspecialchars($category['name'])?>"  style="width: 100%;" maxlength="250"></td>
			<td class='tdbgh center'  width='10%'>Category order</td>
			<td class='tdbg1' width='23%' colspan=2><input type="text" name="catorder" maxlength="8" size="10" value="<?=($category['corder'] ? $category['corder'] : "0")?>" class="right"></td>
		</tr>
		<tr>
			<td class='tdbg2' colspan=4>&nbsp;</td>
			<td class='tdbgh center nobr'>Show even if empty</td>
			<td class='tdbg1'><input type="checkbox" id="showalways" name="showalways" value="1"<?=($category['showalways'] ? " checked" : "")?>> <label for="showalways">Enabled</label></td>
		</tr>		
		<tr>
			<td class='tdbgc center' colspan=6>
				<input type="submit" name="catedit" value="Save and continue">&nbsp;<input type="submit" name="catedit2" value="Save and close">
				<input type="hidden" name="auth" value="<?=generate_token()?>">
			</td>
		</tr>

	</table></form><br>
<?php
}

$forumlist = "
	<tr>
		<td class='tdbgh center' width=90px>Actions</td>
		<td class='tdbgh center'>Forum</td>
		<td class='tdbgh center' width=80>Threads</td>
		<td class='tdbgh center' width=80>Posts</td>
		<td class='tdbgh center' width=15%>Last post</td>
	</tr>
";

if (isset($preview)) {
	
	$forumquery = $sql->query("
		SELECT 	f.*, c.id valid, pf.group{$preview} forumperm, $userfields uid
		FROM forums f
		
		LEFT JOIN users           u  ON f.lastpostuser = u.id
		LEFT JOIN categories      c  ON f.catid        = c.id
		LEFT JOIN perm_forums     pf ON f.id           = pf.id
		
		WHERE !f.custom AND (!f.hidden OR ".has_perm('display-hidden-forums').")
		ORDER BY c.corder, f.catid, f.forder, f.id
	");
	
} else {
	$forumquery = $sql->query("
		SELECT 	f.*, c.id valid, $userfields uid
		FROM forums f
		LEFT JOIN users           u  ON f.lastpostuser = u.id
		LEFT JOIN categories      c  ON f.catid        = c.id
		WHERE !f.custom
		ORDER BY c.corder, f.catid, f.forder, f.id
	");
}

$catquery = $sql->query("
	SELECT id, name, showalways
	FROM categories
	ORDER BY corder, id
");
$modquery = $sql->query("
	SELECT $userfields, f.id forum
	FROM forums f
	INNER JOIN perm_forumusers pu ON f.id    = pu.forum
	INNER JOIN users           u  ON pu.user = u.id
	WHERE !f.custom AND (pu.permset & ".PERM_FORUM_MOD.")
	ORDER BY u.name
");

$categories	= $sql->fetchAll($catquery);
$forums 	= $sql->fetchAll($forumquery);
$mods 		= $sql->fetchAll($modquery);

$forumlist  .= "<tr><td class='tdbgc center' colspan=5>&lt; <a href='admin-editforums.php?id=-1$prevtext'>Create a new forum</a> &gt; &nbsp; &lt; <a href='admin-editforums.php?catid=-1$prevtext'>Create a new category</a> &gt;</td></tr>";
$prevcat 	= NULL;


foreach ($categories as $category) {

	$forumin = "";
	foreach ($forums as $forumplace => $forum) {
		
		if (isset($preview) && !has_forum_perm('read', $forum, true)) {
			unset($forums[$forumplace]);
			continue;
		}
		
		// loop over until we have reached the category this forum's in
		if (!$forum['valid'] || $forum['catid'] != $category['id']) {
			continue;
		}
		
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

	  $forumin .= "
		<tr>
			<td class='tdbg{$tc1} center fonts'><a href=admin-editforums.php?id={$forum['id']}$prevtext>Edit</a> / <a href=admin-editforums.php?delete={$forum['id']}$prevtext>Delete</a></td>
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
	
	if ($forumin || !isset($preview) || $category['showalways']) {
		$forumlist .= "<tr><td class='tdbgc center fonts nobr'><a href=admin-editforums.php?catid={$forum['catid']}$prevtext>Edit</a> / <a href=admin-editforums.php?catdelete={$forum['catid']}$prevtext>Delete</a></td><td class='tdbgc center' colspan=4><b>".htmlspecialchars($category['name'])."</b></td></tr>";
	}
	$forumlist .= $forumin;
}

// Leftover invalid forums
if (!isset($preview) && count($forums)) {
	$forumlist .= "<tr><td class='tdbgc center' colspan=5><b><i>These forums are not associated with a valid category ID</i></b></td></tr>";

	foreach ($forums as $forum) {
		
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

		$forumlist.="
		<tr>
			<td class='tdbg{$tc1} center fonts'><a href=admin-editforums.php?id={$forum['id']}$prevtext>Edit</a> / <a href=admin-editforums.php?delete={$forum['id']}$prevtext>Delete</a></td>
			<td class='tdbg{$tc2}'>
				<a href='forum.php?id={$forum['id']}'>".htmlspecialchars($forum['title'])."</a>$hidden<br>
				<font class='fonts'>{$forum['description']}<br>$modlist
			</td>
			<td class='tdbg{$tc1} center'>{$forum['numthreads']}</td>
			<td class='tdbg{$tc1} center'>{$forum['numposts']}</td>
			<td class='tdbg{$tc2} center nobr'><span class='lastpost'>$forumlastpost</span>$by</td>
		</tr>
		";
	}
}

?>
<center><b>Preview forums with group:</b> <?=previewbox()?></center>
<table class='table'><?=$forumlist?></table>
</table>
<?php
pagefooter();

function dropdownList($links, $sel, $n) {
	$r	= "<select name=\"$n\">";

	foreach($links as $link => $name) {
		$r	.= "<option value=\"$link\"". ($sel == $link ? " selected" : "") .">$name</option>";
	}

	return $r ."</select>";
}

function previewbox(){
	global $preview, $grouplist;
	if ($_GET['id']) {
		$idtxt  = "id=" . $_GET['id'] . "&";
		$idtxt2 = "?id=" . $_GET['id'];
	} else {
		$idtxt = $idtxt2 = "";
	}
	
	$groupselect = "";
	foreach ($grouplist as $groupid => $arr) {
		$groupselect .= "<option value='admin-editforums.php?{$idtxt}preview={$groupid}' ".((isset($preview) && $preview == $groupid) ? 'selected' : '') .">{$arr['name']}</option>\n";
	}
	return "<form><select onChange=parent.location=this.options[this.selectedIndex].value>
			<option value='admin-editforums.php{$idtxt2}' ".((!$preview || $preview < 0 || $preview > 4) ? 'selected' : '') ."'>Disable</option>
			$groupselect
		</select></form>";
}
?>
