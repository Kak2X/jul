<?php

require 'lib/function.php';

pageheader("{$config['board-name']} - Forum Moderators");

admincheck();
print adminlinkbar();

if (isset($_POST['action'])) {
	check_token($_POST['auth']);
//  print "DEBUG: Asked to ".$action." a moderator of forum: ".${$action."modforum"}." and user: ".${$action."moduser"};
	switch($_POST['action']) {
		case "Remove Moderator":
			$removemod = filter_string($_POST['removemod']);
			$removemod 		= explode("|", $removemod);
			$removemoduser 	= filter_int($removemod[1]);
			$removemodforum = filter_int($removemod[0]);
			
			if (!$removemoduser || !$removemodforum)
				errorpage("Invalid options sent.");
			
			$group = $sql->resultq("SELECT `group` FROM users WHERE id = $removemoduser");
			$modset = $sql->fetchq("
				SELECT pf.group{$group} forumperm, pu.permset userperm
				FROM forums f
				LEFT JOIN perm_forums     pf ON f.id    = pf.id
				LEFT JOIN perm_forumusers pu ON f.id    = pu.forum AND pu.user = {$removemoduser}
				WHERE f.id = $removemodforum
			");
			
			if (isset($modset['userperm'])) {
				$sql->query("
					UPDATE perm_forumusers SET permset = ".(PERM_FORUM_NOTMOD)."
					WHERE user = $removemoduser AND forum = $removemodforum
				");
			} else {
				$sql->query("
					INSERT INTO perm_forumusers (user, forum, permset) VALUES
					($removemoduser, $removemodforum, ".($modset['forumperm'] & (PERM_FORUM_NOTMOD)).")
				");				
			}
			errorpage("You successfully deleted user $removemoduser from forum $removemodforum.","admin-editmods.php",'go back to Edit Mods',0);
		case "Add Moderator":
			$forum 	= filter_int($_POST['addmodforum']);
			$user 	= filter_int($_POST['addmoduser']);
			if (!$forum || !$user)
				errorpage("Invalid request.");
			
			$group = $sql->resultq("SELECT `group` FROM users WHERE id = $user");
			$modset = $sql->fetchq("
				SELECT pf.group{$group} forumperm, pu.permset userperm
				FROM forums f
				LEFT JOIN perm_forums     pf ON f.id    = pf.id
				LEFT JOIN perm_forumusers pu ON f.id    = pu.forum AND pu.user = {$user}
				WHERE f.id = $forum
			");
			
			if (isset($modset['userperm'])) {
				$sql->query("
					UPDATE perm_forumusers SET permset = ".(PERM_FORUM_NOTMOD | PERM_FORUM_MOD)."
					WHERE user = $user AND forum = $forum
				");
			} else {
				$sql->query("
					INSERT INTO perm_forumusers (user, forum, permset) VALUES
					($user, $forum, ".(PERM_FORUM_NOTMOD | PERM_FORUM_MOD).")
				");				
			}	
			errorpage("You successfully added user $user to forum $forum.","admin-editmods.php",'go back to Edit Mods',0);
		default:
			errorpage("No, doofus.");
	}
} else {
	//$forums = $sql->query("SELECT id, title, description, catid FROM forums ORDER BY catid, forder");
	$forums = $sql->query("
		SELECT f.id, f.title, f.catid, c.name catname
		FROM forums f
		LEFT JOIN categories c ON f.catid = c.id
		ORDER BY c.corder, f.catid, f.forder, f.id
	");
	$mods = $sql->fetchq("
		SELECT f.id forum, $userfields 
		FROM forums f
		INNER JOIN perm_forumusers pu ON f.id    = pu.forum
		INNER JOIN users           u  ON pu.user = u.id
		WHERE (pu.permset & ".PERM_FORUM_MOD.")
	", PDO::FETCH_GROUP, false, true);
	
	$fa = "";
	$forumselect 		= "<option value=\"0\">Select a forum...</option>\r\n";
	$forumselectforrem 	= "<option value=\"0|0\">Select a forum and moderator...</option>\r\n";
	$prevcat = NULL;
	// Create a list of local mods for each forum (to remove / view)
	while ($forum = $sql->fetch($forums)) {
		if ($prevcat != $forum['catid']) {
			$fa .= "<tr><td class='tdbgc center fonts' colspan=3><b>".($forum['catname'] ? $forum['catname'] : "Unknown Category ID #{$forum['catid']}")."</b></td></tr>";	
			$prevcat = $forum['catid'];
		}
		
		$forumselect .= "<option value=\"{$forum['id']}\">".htmlspecialchars($forum['title'])."</option>";
		if (isset($mods[$forum['id']])) {
			$modlist = "";
			$m = 0;
			foreach ($mods[$forum['id']] as $usermod) {
				$modlist .= ($m++ ? ", " : "") . getuserlink($usermod);
				$forumselectforrem .= "<option value=\"{$forum['id']}|{$usermod['id']}\">".htmlspecialchars($forum['title'])." -- {$usermod['name']}</option>\r\n";
			}
			$fa .= "
			<tr>
				<td class='tdbg2 center fonts'>{$forum['id']}</td>
				<td class='tdbg1 center fonts'>".htmlspecialchars($forum['title'])."</td>
				<td colspan=3 class='tdbg2 fonts'>{$modlist}</td>
			</tr>";
		}
	}
	// Create a list of Normal+ users we can add
	$userlist = "<option value='0'>Select a user...</option>\r\n";
	$users1 = $sql->query("
		SELECT u.id, u.name, u.`group`
		FROM users u
		INNER JOIN perm_groups p ON u.`group` = p.id
		WHERE p.id ".(isset($_POST['showall']) ? "!= ".GROUP_BANNED." AND p.id != ".GROUP_PERMABANNED."" : "= ".GROUP_SUPER)."
		ORDER BY p.id, u.name
	");
	$prevgroup = NULL;
	while($user = $sql->fetch($users1)) {
		if ($prevgroup != $user['group']) {
			if (isset($_POST['showall'])) {
				$userlist .= "</optgroup><optgroup label='".$grouplist[$user['group']]['name']."'>";
			}
			$prevgroup = $user['group'];
		}
		$userlist .= "<option value='{$user['id']}'>{$user['name']}</option>\r\n";
	}
	
	$authtag = "<input type='hidden' name='auth' value='".generate_token()."'>";

?>

<table class='table'>
	<tr>
		<td class='tbl tdbgh center fonts' width=50>ID</td>
		<td class='tbl tdbgh center fonts' width=30%>Forum Name</td>
		<td class='tbl tdbgh center fonts' width=65%>Moderators</td>
	</tr>
	<?=$fa?>
</table>

<form action="admin-editmods.php" method="POST">
<?=$authtag?>
<br>
<table class='table'>
	<tr><td class='tdbgh center' colspan="2">Add Moderator:</td></tr>
	<tr>
		<td class='tdbg1 center' width=15%>Forum:</td>
		<td class='tdbg2' width=85%>
			<select name="addmodforum" size="1"><?=$forumselect?></select>
		</td>
	</tr> 
	<tr>
		<td class='tdbg1 center' width=15%>User:</td>
		<td class='tdbg2' width=85%>
			<select name="addmoduser" size="1"><?=$userlist?></select>
			<?=(isset($_POST['showall']) ? 
				"<input type='submit' class='submit' name='hidesome' value='Show ".$grouplist[GROUP_SUPER]['name']." only'>" : 
				"<span class='fonts'>(note: this only shows ".$grouplist[GROUP_SUPER]['name'].")</span> <input type='submit' class='submit' name='showall' value='Show All'>")
			?>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center' width=15%>&nbsp;</td>
		<td class='tdbg2' width=85%>
			<input type='submit' class=submit name="action" value="Add Moderator">
		</td>
	</tr>
</table>
</form>
<?php

	if ($forumselectforrem) {
		?>
	<form action="admin-editmods.php" method="POST">
	<?=$authtag?>
	<table class='table'>
		<tr><td class='tdbgh center' colspan="2">Remove Moderator:</td></tr>
		<tr>
			<td class='tdbg1 center' width=15%>Forum and Moderator:</td>
			<td class='tdbg2' width=85%>
				<select name="removemod" size="1"><?=$forumselectforrem?></select>
			</td>
		</tr> 
		<tr>
			<td class='tdbg1 center' width=15%>&nbsp;</td>
			<td class='tdbg2' width=85%>
				<input type='submit' class=submit name="action" value="Remove Moderator">
			</td>
		</tr>
	</table>
	</form>
		<?php
	}
}

pagefooter();

?>