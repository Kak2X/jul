<?php

require "lib/common.php";

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
	
	$values = array(
		'title' 			=> filter_string($_POST['forumtitle']),
		'description'		=> filter_string($_POST['description']),
		'catid' 			=> filter_int($_POST['catid']),
		'minpower' 			=> filter_int($_POST['minpower']),
		'minpowerthread' 	=> filter_int($_POST['minpowerthread']),
		'minpowerreply' 	=> filter_int($_POST['minpowerreply']),
		'forder' 			=> filter_int($_POST['forder']), 
		'specialscheme' 	=> get_scheme_opt($_POST['specialscheme']),
		'specialtitle' 		=> filter_string($_POST['specialtitle']),
		'hidden' 			=> filter_int($_POST['hideforum']),
		'pollstyle' 		=> filter_int($_POST['pollstyle']),
		'login' 			=> filter_int($_POST['login']),
		'attachmentmode'    => filter_int($_POST['attachmentmode']),
		'ircchan'			=> filter_int($_POST['ircchan']),
		'discordwebhook'	=> filter_int($_POST['discordwebhook']),
	);
	$qadd = mysql::setplaceholders($values);
	if ($_GET['id'] <= -1) {
		$sql->queryp("INSERT INTO `forums` SET $qadd, `lastpostid` = '0'", $values);
		$id	= $sql->insert_id();
		trigger_error("Created new forum \"{$values['title']}\" with ID $id", E_USER_NOTICE);
	} else {
		$sql->queryp("UPDATE `forums` SET $qadd WHERE `id` = '{$_GET['id']}'", $values);
		$id	= $_GET['id'];
		trigger_error("Edited forum ID $id", E_USER_NOTICE);
	}

	if (isset($_POST['edit'])) {
		return header("Location: ?id=". $id . $prevtext);
	} else {
		return header("Location: ?".substr($prevtext, 1));
	}
	
}
elseif (isset($_POST['delete'])) {
	#if (!$isadmin)
	#	die("You aren't an admin!");
	check_token($_POST['auth']);
	
	$id      = (int) $_GET['delete'];
	$mergeid = (int) $_POST['mergeid'];

	if ($id <= 0)
		errorpage("No forum selected to delete.");
	if ($mergeid <= 0 || $mergeid == $id)
		errorpage("No forum selected to merge to.");
	
	$sql->beginTransaction();
	$counts = $sql->fetchq("SELECT `numthreads`, `numposts` FROM `forums` WHERE `id`='$id'");
	$sql->query("UPDATE `threads` SET `forum`='$mergeid' WHERE `forum`='$id'");
	$sql->query("DELETE FROM `forummods` WHERE `forum`='$id'");
	$sql->query("DELETE FROM `forums` WHERE `id`='$id'");

	$lastthread = $sql->fetchq("SELECT * FROM `threads` WHERE `forum`='$mergeid' ORDER BY `lastpostdate` DESC LIMIT 1");
	$sql->query("UPDATE `forums` SET
		`numthreads`=`numthreads`+'{$counts['numthreads']}',
		`numposts`=`numposts`+'{$counts['numposts']}',
		`lastpostdate`='{$lastthread['lastpostdate']}',
		`lastpostuser`='{$lastthread['lastposter']}',
		`lastpostid`='{$lastthread['id']}'
	WHERE `id`='$mergeid'");

	$sql->commit();
	trigger_error("DELETED forum ID $id; merged into forum ID $mergeid", E_USER_NOTICE);
	return header("Location: ?$prevtext");
}
elseif (isset($_POST['catedit']) || isset($_POST['catedit2'])) {
	check_token($_POST['auth']);	

	$values = array(
		'name' 			=> filter_string($_POST['catname']),
		'minpower' 		=> filter_int($_POST['minpower']),
		'corder' 		=> filter_int($_POST['catorder']), 
		'side' 			=> filter_int($_POST['side']),
	);
	$qadd = mysql::setplaceholders($values);
	
	if ($_GET['catid'] <= -1) {
		$sql->queryp("INSERT INTO `categories` SET $qadd", $values);
		$id	= $sql->insert_id();
		trigger_error("Created new category \"".$values['name']."\" with ID $id", E_USER_NOTICE);
	} else {
		$sql->queryp("UPDATE `categories` SET $qadd WHERE `id` = '". $_GET['catid'] ."'", $values);
		$id	= $_GET['catid'];
		trigger_error("Edited category ID $id", E_USER_NOTICE);
	}

	if (isset($_POST['catedit'])) {
		return header("Location: ?catid=". $id . $prevtext);
	} else {
		return header("Location: ?".substr($prevtext, 1));
	}
}
elseif (isset($_POST['catdelete'])) {
	check_token($_POST['auth']);
	
	$id      = (int) $_GET['catdelete'];
	$mergeid = (int) $_POST['mergeid'];

	if ($id <= 0)
		errorpage("No category selected to delete.");
	if ($mergeid <= 0 || $mergeid == $id)
		errorpage("No category selected to merge to.");
	
	$sql->beginTransaction();
	$sql->query("UPDATE forums SET catid = $mergeid WHERE catid = $id");
	$sql->query("DELETE FROM categories WHERE id = $id");
	$sel->commit();
	trigger_error("DELETED category ID $id; merged into category ID $mergeid", E_USER_NOTICE);
	return header("Location: ?$prevtext");
}

$windowtitle = "Editing Forum List";

pageheader($windowtitle);

print adminlinkbar();


$attachmodes = array(-2 => 'Disallowed',
                     -1 => 'Normal');

foreach($pwlnames as $pwl => $pwlname) {
	if ($pwl < 0) continue;
	$powers[] = $pwlname;
	$attachmodes[] = "Require ".$pwlname;
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
				<?= int_select("mergeid", $forums, -1) ?>
			</td>
		</tr>
		<tr>
			<td class='tdbgc center'>
				<input type="submit" name="delete" value="DELETE FORUM"> or <a href="?">Cancel</a>
				<?= auth_tag() ?>
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
	$irc_channels = $sql->getresultsbykey("SELECT id, name FROM `irc_channels` ORDER by id");
	$discord_channels = $sql->getresultsbykey("SELECT id, name FROM `discord_webhooks` ORDER by id");
	
	if (!$forum) {
		$_GET['id'] = -1;
		$forum = array(
			'pollstyle'      => -1,
			'title'          => '',
			'description'    => '',
			'minpower'       =>  0,
			'minpowerthread' =>  0,
			'minpowerreply'  =>  0,
			'forder'         =>  0,
			'specialscheme'  => -1,
			'catid'          =>  1,
			'specialtitle'   => '',
			'hidden'         =>  0,
			'pollstyle'      => -1,
			'login'          =>  0,
			'attachmentmode' => -1,
			'ircchan'        => 0,
			'discordwebhook' => 0,
		);
	} else {
		if (!isset($categories[$forum['catid']]))
			$categories[$forum['catid']] = "Unknown category #" . $forum['catid'];
	}

?>
	<form method="post" action="?id=<?=$_GET['id']?><?=$prevtext?>">
	<table class='table'>
		<tr>
			<td class='tdbgh center' colspan=6>Editing <b><?=($_GET['id'] != -1 ? htmlspecialchars($forum['title']) : "a new forum")?></b></td>
		</tr>

		<tr>
			<td class='tdbgh center'>Forum Name</td>
			<td class='tdbg1' colspan='4'><input type="text" class="w" name="forumtitle" value="<?=htmlspecialchars($forum['title'])?>" maxlength="250"></td>
			<td class='tdbg1' width=10%>
				<label class="nobr"><input type="checkbox" name="hideforum" value="1"<?=($forum['hidden'] ? " checked" : "")?>> Hidden</label>
				<label class="nobr"><input type="checkbox" name="login" value="1"<?=($forum['login'] ? " checked" : "")?>> Login required</label>
			</td>
		</tr>

		<tr>
			<td class='tdbgh center' rowspan='5'>Description</td>
			<td class='tdbg1' rowspan='5' colspan='3'><textarea class='no-resize' name='description' rows='8'><?=escape_html($forum['description'])?></textarea></td>
			<td class='tdbgh center' colspan='2'>Minimum power needed...</td>
		</tr>

		<tr>
			<td class='tdbgh center' width='10%'>...to view the forum</td>
			<td class='tdbg1' width='23%'><?=int_select("minpower", $powers, $forum['minpower'])?></td>
		</tr>

		<tr>
			<td class='tdbgh center'>...to post a thread</td>
			<td class='tdbg1'><?=int_select("minpowerthread", $powers, $forum['minpowerthread'])?></td>
		</tr>

		<tr>
			<td class='tdbgh center'>...to reply</td>
			<td class='tdbg1'><?=int_select("minpowerreply", $powers, $forum['minpowerreply'])?></td>
		</tr>
		<tr>
			<td class='tdbgh center'>...to post attachments</td>
			<td class='tdbg1'><?=int_select("attachmentmode", $attachmodes, $forum['attachmentmode'])?></td>
		</tr>
		
		<tr>
			<td class='tdbgh center' >Special Scheme</td>
			<td class='tdbg1' width='24%'><?=doschemeList($forum['specialscheme'], 'specialscheme', SL_SHOWSPECIAL | SL_SHOWNONE)?></td>
			<td class='tdbgh center'>Forum order</td>
			<td class='tdbg1' width='23%'><input type="text" name="forder" maxlength="8" size="10" value="<?=($forum['forder'] ? $forum['forder'] : "0")?>" class="right"></td>
			<td class='tdbgh center' >Category</td>
			<td class='tdbg1' width='23%'><?=int_select("catid", $categories, $forum['catid'])?></td>
	
		</tr>
		<tr>
			<td class='tdbgh center' rowspan='3'>Custom header</td>
			<td class='tdbg1' colspan='3' rowspan='3'><textarea name="specialtitle" rows="3" class="no-resize"><?=escape_html($forum['specialtitle'])?></textarea></td>
			<td class='tdbgh center'>Poll Style</td>
			<td class='tdbg1'><?=int_select("pollstyle", $pollstyles, $forum['pollstyle'])?></td>
		</tr>
		
		<tr>
		
			<td class='tdbgh center'>IRC Channel</td>
			<td class='tdbg1'><?=int_select("ircchan", $irc_channels, $forum['ircchan'], "*** Disable ***")?></td>
		</tr>
		<tr>

			<td class='tdbgh center'>Discord Webhook</td>
			<td class='tdbg1'><?=int_select("discordwebhook", $discord_channels, $forum['discordwebhook'], "*** Disable ***")?></td>
		</tr>
		
		<tr>
			<td class='tdbgc center' colspan=6>
				<input type="submit" name="edit" value="Save and continue">&nbsp;<input type="submit" name="edit2" value="Save and close">
				<?= auth_tag() ?>
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
				<?= int_select("mergeid", $categories, -1) ?>
			</td>
		</tr>
		<tr>
			<td class='tdbgc center'>
				<input type="submit" name="catdelete" value="DELETE CATEGORY"> or <a href="?">Cancel</a>
				<?= auth_tag() ?>
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
		$category = [
			'name' => "",
			'corder' => 0,
			'minpower' => PWL_NORMAL,
			'side' => 0,
		];
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
			<td class='tdbg1' width='23%' colspan=2>
				<input type="text" name="catorder" maxlength="8" size="10" value="<?=($category['corder'] ? $category['corder'] : "0")?>" class="right">
			</td>
		</tr>
		<tr>
			<td class='tdbg2' colspan=2>&nbsp;</td>
			<td class='tdbgh center nobr'>Options</td>
			<td class='tdbg1'><label><input type="checkbox" name="side" value="1" <?=($category['side'] ? " checked" : "")?>> Right side</label></td>
			<td class='tdbgh center nobr'>Minimum power needed to view</td>
			<td class='tdbg1'><?=int_select("minpower", $powers, $category['minpower'])?></td>
		</tr>		
		<tr>
			<td class='tdbgc center' colspan=6>
				<input type="submit" name="catedit" value="Save and continue">&nbsp;<input type="submit" name="catedit2" value="Save and close">
				<?= auth_tag() ?>
			</td>
		</tr>

	</table></form><br>
<?php
}

// TODO: This could be merged with the index page logic, it's basically the same with minor changes.
//       print forum_index($editmode = false, $sel = -1, $power = null)
$forumheaders="
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
		SELECT id, name, side
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
	$catquery = $sql->query("SELECT id, name, side FROM categories ORDER BY corder, id");
}

$modquery = $sql->query("SELECT $userfields,m.forum FROM users u INNER JOIN forummods m ON u.id = m.user ORDER BY name");

$categories = $sql->fetchAll($catquery, PDO::FETCH_ASSOC);
$forums 	= $sql->fetchAll($forumquery, PDO::FETCH_ASSOC);
$mods 		= $sql->fetchAll($modquery, PDO::FETCH_ASSOC);

$forumlist = array(-1 => '', '','');
foreach ($categories as $category) {
	$forumlist[$category['side']] .= "<tr><td class='tdbgc center fonts nobr'><a href=admin-editforums.php?catid={$category['id']}$prevtext>Edit</a> / <a href=admin-editforums.php?catdelete={$category['id']}$prevtext>Delete</a></td><td class='tdbgc center' colspan=4><b>".htmlspecialchars($category['name'])."</b></td></tr>";

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
			$modlist = "(moderated by: $modlist)";

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

		$forumlist[$category['side']] .= "
		<tr>
			<td class='tdbg{$tc1} center fonts'><a href=admin-editforums.php?id={$forum['id']}$prevtext>Edit</a> / <a href=admin-editforums.php?delete={$forum['id']}$prevtext>Delete</a></td>
			<td class='tdbg{$tc2}'>
				<a href='forum.php?id={$forum['id']}'>".htmlspecialchars($forum['title'])."</a>$hidden<br>
				<span class='fonts'>
					".xssfilters($forum['description'])."<br>
					$modlist
				</span>
			</td>
			<td class='tdbg{$tc1} center'>{$forum['numthreads']}</td>
			<td class='tdbg{$tc1} center'>{$forum['numposts']}</td>
			<td class='tdbg{$tc2} center nobr'><span class='lastpost'>$forumlastpost</span>$by</td>
		</tr>";

		unset($forums[$forumplace]);
	}
}

// Leftover forums
if (!isset($preview) && count($forums)) {
	$forumlist[-1] .= "<tr><td class='tdbgc center' colspan=5><b><i>These forums are not associated with a valid category ID</i></b></td></tr>";

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
			$modlist = "(moderated by: $modlist)";

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

		$forumlist[-1].="
		<tr>
			<td class='tdbg{$tc1} center fonts'><a href=admin-editforums.php?id={$forum['id']}$prevtext>Edit</a> / <a href=admin-editforums.php?delete={$forum['id']}$prevtext>Delete</a></td>
			<td class='tdbg{$tc2}'>
				<a href='forum.php?id={$forum['id']}'>".htmlspecialchars($forum['title'])."</a>$hidden<br>
				<span class='fonts'>
					".xssfilters($forum['description'])."<br>
					$modlist
				</span>
			</td>
			<td class='tdbg{$tc1} center'>{$forum['numthreads']}</td>
			<td class='tdbg{$tc1} center'>{$forum['numposts']}</td>
			<td class='tdbg{$tc2} center nobr'><span class='lastpost'>$forumlastpost</span>$by</td>
		</tr>
		";
	}
}

// Split categories
$fsep = "";
if ($forumlist[0] && $forumlist[1]) $fsep = "";
if ($forumlist[0]) $forumlist[0] = "<td style='width: 50%' class='vatop'><table class='table'>{$forumheaders}{$forumlist[0]}</table></td>";
if ($forumlist[1]) $forumlist[1] = "<td style='width: 50%' class='vatop'><table class='table'>{$forumheaders}{$forumlist[1]}</table></td>";
if ($forumlist[-1]) $forumlist[-1] = "<br><table class='table'>{$forumheaders}{$forumlist[-1]}</table>";

print "<center><b>Preview forums with powerlevel:</b> "._previewbox()."</center>\n";
print "
<table class='table'>
	<tr><td class='tdbgc center' colspan=2>&lt; <a href='admin-editforums.php?id=-1$prevtext'>Create a new forum</a> &gt; &nbsp; &lt; <a href='admin-editforums.php?catid=-1$prevtext'>Create a new category</a> &gt;</td></tr>
</table>
<br>
<table class='w' cellpadding=0 cellspacing=0 border=0>
	{$forumlist[0]}{$fsep}{$forumlist[1]}
</table>
{$forumlist[-1]}";

pagefooter();

function _previewbox(){
	global $preview, $pwlnames;
	if ($_GET['id']) {
		$idtxt  = "id=" . $_GET['id'] . "&";
		$idtxt2 = "?id=" . $_GET['id'];
	} else {
		$idtxt = $idtxt2 = "";
	}
	
	$out = "";
	foreach ($pwlnames as $p => $lbl) {
		$out .= "<option value='admin-editforums.php?{$idtxt}preview={$p}' ".($preview === $p ? 'selected' : '') .">{$lbl}</option>";
	}

	return "<form><select onChange=parent.location=this.options[this.selectedIndex].value>
			<option value='admin-editforums.php{$idtxt2}'>Disable</option>
			{$out}
		</select></form>";
}