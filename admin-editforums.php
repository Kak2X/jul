<?php

require 'lib/function.php';

admincheck();

$preview  = isset($_GET['preview']) ? ((int) $_GET['preview']) : NULL;
$prevtext = isset($preview) ? "&preview=$preview" : "";
// Initialize/typecast these variables here so we won't get notices or other fun stuff
$_GET['id'] 		= filter_int($_GET['id']);
$_GET['delete'] 	= filter_int($_GET['delete']);
$_GET['catid'] 		= filter_int($_GET['catid']);
$_GET['catdelete'] 	= filter_int($_GET['catdelete']);


if (isset($_POST['edit']) || isset($_POST['edit2'])) {
	#if (!$isadmin) 
	#	die("You aren't an admin!");
	check_token($_POST['auth']);

	//$hidden = (($_POST['hidden']) ? 1 : 0);
	
	
	if (isset($_POST['specialscheme']) && $_POST['specialscheme'] == -1)
		$_POST['specialscheme'] = NULL;
	else
		$_POST['specialscheme'] = filter_int($_POST['specialscheme']);
	
	$qadd = "title=:title,description=:description,catid=:catid,minpower=:minpower,minpowerthread=:minpowerthread,".
			"minpowerreply=:minpowerreply,numthreads=:numthreads,numposts=:numposts,forder=:forder,".
			"specialscheme=:specialscheme,specialtitle=:specialtitle,hidden=:hidden,pollstyle=:pollstyle";
	
	$values = array(
		'title' 			=> xssfilters(filter_string($_POST['forumtitle'], true)),
		'description'		=> xssfilters(filter_string($_POST['description'], true)),
		'catid' 			=> filter_int($_POST['catid']),
		'minpower' 			=> filter_int($_POST['minpower']),
		'minpowerthread' 	=> filter_int($_POST['minpowerthread']),
		'minpowerreply' 	=> filter_int($_POST['minpowerreply']),
		'numthreads' 		=> filter_int($_POST['numthreads']),
		'numposts' 			=> filter_int($_POST['numposts']),
		'forder' 			=> filter_int($_POST['forder']), 
		'specialscheme' 	=> $_POST['specialscheme'],
		'specialtitle' 		=> xssfilters(filter_string($_POST['specialtitle'], true)),
		'hidden' 			=> filter_int($_POST['hideforum']),
		'pollstyle' 		=> filter_int($_POST['pollstyle'])
	);
	$querycheck = array();
	if ($_GET['id'] <= -1) {
		$sql->queryp("INSERT INTO `forums` SET $qadd, `lastpostid` = '0'", $values, $querycheck);
		if (!$querycheck[0]) errorpage("Could not add the forum.");
		$id	= $sql->insert_id();
		trigger_error("Created new forum \"".$values['forumtitle']."\" with ID $id", E_USER_NOTICE);
	} else {
		$sql->queryp("UPDATE `forums` SET $qadd WHERE `id` = '". $_GET['id'] ."'", $values, $querycheck);
		if (!$querycheck[0]) errorpage("Could not edit the forum.");
		$id	= $_GET['id'];
		trigger_error("Edited forum ID $id", E_USER_NOTICE);
	}

	if ($_POST['edit'])
		header("Location: ?id=". $id . $prevtext);
	else
		header("Location: ?".substr($prevtext, 1));

	die();
}
elseif (isset($_POST['delete'])) {
	#if (!$isadmin)
	#	die("You aren't an admin!");
	check_token($_POST['auth']);
	
	$id      = (int) $_GET['delete'];
	$mergeid = (int) $_POST['mergeid'];

	if ($id <= 0)
		errorpage("No forum selected to delete.");
	if ($mergeid <= 0)
		errorpage("No forum selected to merge to.");

	$querycheck = array();
	$sql->beginTransaction();
	$counts = $sql->fetchq("SELECT `numthreads`, `numposts` FROM `forums` WHERE `id`='$id'");
	$sql->query("UPDATE `threads` SET `forum`='$mergeid' WHERE `forum`='$id'", false, $querycheck);
	$sql->query("UPDATE `announcements` SET `forum`='$mergeid' WHERE `forum`='$id'", false, $querycheck);
	$sql->query("DELETE FROM `forummods` WHERE `forum`='$id'", false, $querycheck);
	$sql->query("DELETE FROM `forums` WHERE `id`='$id'", false, $querycheck);

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
	
	$qadd = "name=:name,minpower=:minpower,corder=:corder";
	
	$values = array(
		'name' 			=> xssfilters(filter_string($_POST['catname'], true)),
		'minpower' 		=> filter_int($_POST['minpower']),
		'corder' 			=> filter_int($_POST['catorder']), 
	);
	$querycheck = array();
	if ($_GET['catid'] <= -1) {
		$sql->queryp("INSERT INTO `categories` SET $qadd", $values, $querycheck);
		if (!$querycheck[0]) errorpage("Could not add the category.");
		$id	= $sql->insert_id();
		trigger_error("Created new category \"".$values['name']."\" with ID $id", E_USER_NOTICE);
	} else {
		$sql->queryp("UPDATE `forums` SET $qadd WHERE `id` = '". $_GET['catid'] ."'", $values, $querycheck);
		if (!$querycheck[0]) errorpage("Could not edit the category.");
		$id	= $_GET['catid'];
		trigger_error("Edited category ID $id", E_USER_NOTICE);
	}

	if ($_POST['catedit'])
		header("Location: ?id=". $id . $prevtext);
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

print adminlinkbar('admin-editforums.php');

foreach($pwlnames as $pwl => $pwlname) {
	if ($pwl < 0) continue;
	$powers[] = $pwlname;
}
$powers[] = '[no access]';

$pollstyles = array(-2 => 'Disallowed',
                    -1 => 'Normal',
                     0 => 'Force Regular',
                     1 => 'Force Influence');


if ($_GET['delete']) {
	#if (!$isadmin)
	#	errorpage("You aren't an admin!");	
	$forums = $sql->getresultsbykey("SELECT id, title FROM forums ORDER BY catid, forder");
	$forums[-1] = "Choose a forum to merge into...";
	
	if (isset($forums[$_GET['delete']])) {
		$fname = htmlspecialchars($forums[$_GET['delete']]);
		unset($forums[$_GET['delete']]);

	?>
	<form method="post" action="?delete=<?=$_GET['delete']?><?=$prevtext?>">
	<table class='table'>
		<tr><td class='tdbgh center'>Deleting <b><?=$fname?></b></td></tr>
		<tr>
			<td class='tdbgc center'>
				You are about to delete forum ID <b><?=$_GET['delete']?></b>.<br>
				<br>
				All announcements and threads will be moved to the forum below.<br>
				<?= dropdownList($forums, -1, "mergeid") ?>
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
	$forum = $sql->fetchq("SELECT * FROM `forums` WHERE `id` = '". $_GET['id'] . "'");
	if (!$forum) {
		$_GET['id'] = -1;
	} else {
		if (!isset($categories[$forum['catid']]))
			$categories[$forum['catid']] = "Unknown category #" . $forum['catid'];

		//if ($forum['specialscheme'] == NULL)
		//	$forum['specialscheme'] = '-1';
	}

?>
	<form method="post" action="?id=<?=$_GET['id']?><?=$prevtext?>">
	<table class='table'>
		<tr>
			<td class='tdbgh center' colspan=6>Editing <b><?=($forum ? htmlspecialchars($forum['title']) : "a new forum")?></b></td>
		</tr>

		<tr>
			<td class='tdbgh center'>Forum Name</td>
			<td class='tdbg1' colspan=4><input type="text" name="forumtitle" value="<?=htmlspecialchars($forum['title'])?>"  style="width: 100%;" maxlength="250"></td>
			<td class='tdbg1' width=10%><input type="checkbox" id="hideforums" name="hideforum" value="1"<?=($forum['hidden'] ? " checked" : "")?>> <label for="hideforums">Hidden</label></td>
		</tr>

		<tr>
			<td class='tdbgh center' rowspan=4>Description</td>
			<td class='tdbg1' rowspan=4 colspan=3><textarea wrap=virtual name=description ROWS=4 style="width: 100%; resize:none;"><?=htmlspecialchars($forum['description'])?></TEXTAREA></td>
			<td class='tdbgh center' colspan=2>Minimum power needed...</td>
		</tr>

		<tr>
			<td class='tdbgh center'>...to view the forum</td>
			<td class='tdbg1'><?=dropdownList($powers, $forum['minpower'], "minpower")?></td>
		</tr>

		<tr>
			<td class='tdbgh center'>...to post a thread</td>
			<td class='tdbg1'><?=dropdownList($powers, $forum['minpowerthread'], "minpowerthread")?></td>
		</tr>

		<tr>
			<td class='tdbgh center'>...to reply</td>
			<td class='tdbg1'><?=dropdownList($powers, $forum['minpowerreply'], "minpowerreply")?></td>
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

	</table></form><br>
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
			<td class='tdbgh center nobr'>Minimum power needed to view</td>
			<td class='tdbg1'><?=dropdownList($powers, $category['minpower'], "minpower")?></td>
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

$forumlist="
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
		SELECT f.*, $userfields uid
		FROM forums f
		LEFT JOIN users u ON f.lastpostuser = u.id
		WHERE (!minpower OR minpower <= $preview)
		AND (f.hidden = '0' OR $sysadmin) 
		ORDER BY catid, forder");
	$catquery = $sql->query("
		SELECT id, name
		FROM categories
		WHERE (!minpower OR minpower <= $preview)
		ORDER BY corder, id
	");
} else {
	$forumquery = $sql->query("
		SELECT f.*, $userfields uid 
		FROM forums f
		LEFT JOIN users u      ON f.lastpostuser = u.id
		LEFT JOIN categories c ON f.catid = c.id
		ORDER BY c.corder, f.catid, f.forder
	");
	$catquery = $sql->query("SELECT id, name FROM categories ORDER BY corder, id");
}

$modquery = $sql->query("SELECT $userfields,m.forum FROM users u INNER JOIN forummods m ON u.id = m.user ORDER BY name");

$categories = $sql->fetchAll($catquery, PDO::FETCH_ASSOC);
$forums 	= $sql->fetchAll($forumquery, PDO::FETCH_ASSOC);
$mods 		= $sql->fetchAll($modquery, PDO::FETCH_ASSOC);

$forumlist .= "<tr><td class='tdbgc center' colspan=5>&lt; <a href='admin-editforums.php?id=-1$prevtext'>Create a new forum</a> &gt; &nbsp; &lt; <a href='admin-editforums.php?catid=-1$prevtext'>Create a new category</a> &gt;</td></tr>";

foreach ($categories as $category) {
	$forumlist .= "<tr><td class='tdbgc center fonts nobr'><a href=admin-editforums.php?catid={$category['id']}$prevtext>Edit</a> / <a href=admin-editforums.php?catdelete={$category['id']}$prevtext>Delete</a></td><td class='tdbgc center' colspan=4><b>".htmlspecialchars($category['name'])."</b></td></tr>";

	foreach ($forums as $forumplace => $forum) {
		// loop over until we have reached the category this forum's in
		if ($forum['catid'] != $category['id'])
			continue;
		
		// Local mod list
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
		/*
		if($forum['lastpostdate']>$category['lastpostdate']){
			$category['lastpostdate']=$forum['lastpostdate'];
			$category['l']=$forumlastpost.$by;
		}
		*/
		
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

		unset($forums[$forumplace]);
	}
}

// Leftover forums
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
		/*
		if($forum['lastpostdate']>$category['lastpostdate']){
			$category['lastpostdate']=$forum['lastpostdate'];
			$category['l']=$forumlastpost.$by;
		}
		*/

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

print "<center><b>Preview forums with powerlevel:</b> ".previewbox()."</center>\n";
print "<table class='table'>$forumlist</table>";
pagefooter();

function dropdownList($links, $sel, $n) {
	$r	= "<select name=\"$n\">";

	foreach($links as $link => $name) {
		$r	.= "<option value=\"$link\"". ($sel == $link ? " selected" : "") .">$name</option>";
	}

	return $r ."</select>";
}

function previewbox(){
	global $preview;
	if ($_GET['id']) {
		$idtxt  = "id=" . $_GET['id'] . "&";
		$idtxt2 = "?id=" . $_GET['id'];
	} else {
		$idtxt = $idtxt2 = "";
	}

	return "<form><select onChange=parent.location=this.options[this.selectedIndex].value>
			<option value='admin-editforums.php{$idtxt2}' ".((!$preview || $preview < 0 || $preview > 4) ? 'selected' : '') ."'>Disable</option>
			<option value='admin-editforums.php?{$idtxt}preview=0' ".((isset($preview) && $preview == 0) ? 'selected' : '') .">Normal</option>
			<option value='admin-editforums.php?{$idtxt}preview=1' ".($preview == 1 ? 'selected' : '') .">Normal +</option>
			<option value='admin-editforums.php?{$idtxt}preview=2' ".($preview == 2 ? 'selected' : '') .">Moderator</option>
			<option value='admin-editforums.php?{$idtxt}preview=3' ".($preview == 3 ? 'selected' : '') .">Administrator</option>
			<option value='admin-editforums.php?{$idtxt}preview=4' ".($preview == 4 ? 'selected' : '') .">Administrator (hidden)</option>
		</select></form>";
}
?>
