<?php
require "lib/function.php";

$_GET['id'] 	= filter_int($_GET['id']);
$_GET['copy'] 	= filter_int($_GET['copy']);	// Copy group ID
$_GET['mode'] 	= filter_int($_GET['mode']);	// Editing groups or users?
$_GET['del']	= isset($_GET['del']);			// Deletion flag
$_GET['f']		= filter_int($_GET['f']); 		// Show only a forum in the user permission editor. This is a purely visual change.


define('PE_EDITGROUP', 	$_GET['mode'] == 0);
define('PE_EDITUSER', 	$_GET['mode'] == 1);

// PermTable flags
const PT_READONLY  = 0b1;
const PT_CHECKPERM = 0b10;


// TODO: Convert to load_permlist(true);
// List of permissions on permission table for easy editing
// <permission name> -> <description>
const PT_PERMLIST = array(
	
	'Generic Permissions' => NULL,
	'sysadmin-actions'			=> "General Sysadmin actions", // SYSADMIN [Generic Sysadmin Perm]
	'admin-actions'				=> "General Admin actions", // ADMIN [Generic Admin Perm]
	'forum-admin'				=> "Forum administration", // ADMIN [Generic Admin Perm]
	'all-forum-access'			=> "General Global Mod actions", // MOD [Generic Mod Perm]	

	
	'Administrative Features' => NULL,
	'view-others-pms'			=> "View other user's PMs", // ADMIN
	'bypass-lockdown' 			=> "Can bypass board lockdown", // SYSADMIN
	'view-debugger' 			=> "Can view Error and SQL debuggers", // SYSADMIN
	'reregister'				=> "Can re-register ignoring restrictions", // ADMIN
	'logs-banner'				=> "Can ban users from the suspicious requests and online page", // SYSADMIN / Other whitelisted
	
	'Restricted pages' => NULL,
	'view-shitbugs'				=> "Can view suspicious requests log", // SUPER
	'use-shoped'				=> "Shop Editor access", // SUPER
	'use-shoped-hidden'			=> "Can edit hidden shop items",  // SYSADMIN / Whitelisted	
	
	'Restricted features' => NULL,
	'show-super-users'			=> "Can see 'Normal+' group", // ADMIN
	'show-hidden-user-activity'	=> "Show hidden users in online bar", // MOD
	'show-all-ranks'			=> "Show all ranks", // MOD
	'view-submessage'			=> "Can view the staff-only message", // SUPER
	'view-bpt-info'				=> "View Bot/Tor/Proxy info in online bar", // ADMIN (+ online.php ip sort)
	'display-hidden-forums' 	=> "Can view hidden forums in the index page", // ADMIN
	
	'Restricted profile options' => NULL,
	'has-always-title'			=> "Bypass custom title requirements", // SUPER
	'change-namecolor'			=> "Can change his own namecolor", // SUPER
	'select-secret-themes'		=> "Show secret themes in theme list", // ADMIN / Other whitelisted

	'Normal User Actions' => NULL,
	'edit-own-posts'			=> "Can edit his own posts", // NORMAL
	'edit-own-profile'			=> "Can edit his own profile", // NORMAL
	'view-online-page'			=> "Can view online users page", // Dedicated to the autistic shithead known as AlbertoCML	
	'send-pms'					=> "Can send PMs", // NORMAL
	'edit-own-events'			=> "Can edit his own events", // NORMAL
	'has-title'					=> "Custom title status", // NORMAL
	'create-custom-forums'		=> "Create custom forums",
	'bypass-custom-forum-limits'=> "Bypass custom forum limits/requirements",
	
	
	'News Engine' => NULL,
	'post-news' 				=> "Can post news", // ADMIN
	'news-admin' 				=> "Can moderate the news section", // ADMIN
	

);
		
$txt = "";

$windowtitle = "Permission Editor";

$isadmin = has_perm('admin-actions');

if (PE_EDITGROUP) {
	$windowtitle .= " - Editing groups";
	if (!has_perm('sysadmin-actions')) {
		errorpage("No.");
	}
} else {
	$windowtitle .= " - Editing users";
	if (!$isadmin && (!has_perm('create-custom-forums') || !$config['allow-custom-forums'])) {
		errorpage("Sorry, but you aren't allowed to manage custom forums.", 'index.php', 'the index');
	}
}
	
if (!$_GET['id']) {
	
	// We don't list users here.
	if (PE_EDITUSER) {
		errorpage("You have to select an user before editing their permissions.");
	}
	
	// Show group selection
	$txt = 
	"<table class='table w'>
		<tr>
			<td class='tdbgh center' style='width: 150px'>&nbsp;</td>
			<td class='tdbgh center'>&nbsp;</td>
			<td class='tdbgh center' style='width: 200px'>
				<b>Male</b>
			</td>
			<td class='tdbgh center' style='width: 200px'>
				<b>Female</b>
			</td>
			<td class='tdbgh center' style='width: 200px'>
				<b>N/A<!--It is a mystery--></b>
			</td>
		</tr>";
	$groups = $sql->query("SELECT * FROM perm_groups ORDER BY ord ASC, id ASC");
	for ($i = 0; $group = $sql->fetch($groups); ++$i) {
		$cell = $i % 2 ? 1 : 2;
		$deleteLink = $group['unremovable'] ? "" : " / <a href='?mode=0&id={$group['id']}&del'>Delete</a>"; // This is to ensure the default groups referenced by the codebase won't ever be deleted.
		$txt .= 
		"<tr>
			<td class='tdbg{$cell} center fonts'>
				<a href='?mode=0&id={$group['id']}'>Edit</a> / <a href='?mode=0&id=-1&copy={$group['id']}'>Copy</a>{$deleteLink}
			</td>
			<td class='tdbg{$cell}'></td>
			<td class='tdbg{$cell} center' style='font-weight: bold; color: #{$group['namecolor0']} !important'>{$group['name']}</td>
			<td class='tdbg{$cell} center' style='font-weight: bold; color: #{$group['namecolor1']} !important'>{$group['name']}</td>
			<td class='tdbg{$cell} center' style='font-weight: bold; color: #{$group['namecolor2']} !important'>{$group['name']}</td>
		</tr>";
	}
	$txt .= 
		"<tr>
			<td class='tdbgc center' colspan=5>
				<a href='?mode=0&id=-1'>&lt; Add a new group &gt;</a>
			</td>
		</tr>			
	</table>";
} 
else {
	
	
	if (isset($_POST['reset']) && $isadmin && PE_EDITUSER) {
		// Resetting user permfields to default by deleting userperm
		check_token($_POST['auth']);
		$sql->query("DELETE FROM perm_users WHERE id = {$_GET['id']}");
		header("Location: admin-editperms.php?mode={$_GET['mode']}&id={$_GET['id']}");
		die;
	} 
	else if ($_GET['del'] && PE_EDITGROUP) {
		
		$group = $sql->fetchq("SELECT name, unremovable FROM perm_groups WHERE id = {$_GET['id']}");
		
		if (!$group) 				errorpage("This group doesn't exist.");
		if ($group['unremovable']) 	errorpage("You can't remove default groups.");
		
		// Group deletion and move to default
		if (isset($_POST['dodelete'])) {
			check_token($_POST['auth']);
			
			$dest 	= filter_int($_POST['dest']);
			$valid 	= $sql->resultq("SELECT 1 FROM perm_groups WHERE id = $dest AND id != {$_GET['id']}");
			if (!$valid) {
				errorpage("You chose an invalid destination group.");
			}
			
			$sql->query("DELETE FROM perm_groups WHERE id = {$_GET['id']}");
			$sql->query("UPDATE users SET `group` = $dest WHERE `group` = {$_GET['id']}");
			$sql->query("ALTER TABLE `perm_forums` DROP `group{$_GET['id']}`;");
			header("Location: admin-editperms.php?mode={$_GET['mode']}");
			die;
		}
		
		// Listbox to choose destination group
		$groups = $sql->query("SELECT id, name FROM perm_groups WHERE id != {$_GET['id']} ORDER BY ord ASC, id ASC");
		$groupList = "";
		while ($x = $sql->fetch($groups)) {
			$groupList .= "<option value='{$x['id']}'>{$x['name']}</option>";
		}
		// NO BONUS
		if (!$groupList) {
			errorpage("You have to keep at least one group.");
		}
		
		$windowtitle .= " - Delete group '{$group['name']}";
		
		$txt = "
	<form method='POST' action='?mode={$_GET['mode']}&id={$_GET['id']}&del'>
		<table class='table'>
			<tr>
				<td class='tdbg1 center'>
					Are you sure you want to delete the group '{$group['name']}'?<br><br>
					Merge users to: <select name='dest'>{$groupList}</select><br><br>
					<input type='submit' class='submit' name='dodelete' value='Delete'> - <a href='?mode={$_GET['mode']}&id={$_GET['id']}'>Cancel</a>
					<input type='hidden' name=auth value='".generate_token()."'>
				</td>
			</tr>
		</table>
	</form>";
	
	}
	else if (isset($_POST['edit']) && $isadmin) {
		check_token($_POST['auth']);
		unset($_POST['edit'], $_POST['auth'], $_POST['reset']);
		
		// Mandatory fields.
		if (PE_EDITGROUP) {
			$_POST['name'] 			= xssfilters(filter_string($_POST['name'], true));
			$_POST['namecolor0'] 	= substr(xssfilters(filter_string($_POST['namecolor0'], true)), 0, 6);
			$_POST['namecolor1'] 	= substr(xssfilters(filter_string($_POST['namecolor1'], true)), 0, 6);
			$_POST['namecolor2'] 	= substr(xssfilters(filter_string($_POST['namecolor2'], true)), 0, 6);
			
			if (!$_POST['name'])
				errorpage("The group name can't be left blank.");
			if (!$_POST['namecolor0'] || !$_POST['namecolor1'] || !$_POST['namecolor2'])
				errorpage("You have left one of the group name colors blank.");
		} else {
			if ($_GET['id'] < 0)
				errorpage("Invalid ID given.");
			$permOrig = load_perm($_GET['id'], $sql->resultq("SELECT `group` FROM users WHERE id = {$_GET['id']}"));
		}
		
		// Initialize the output array which will be inserted to the database
		$permSet = array();
		for ($i = 0; $i < $miscdata['perm_fields']; ++$i) {
			$permSet[$i] = 0;
		}
		
		
		// Account for the number of arguments
		foreach ($_POST as $permName => $permVal) {
			$permDef = @constant("PERM_" . str_replace("-", "_", strtoupper($permName)));
			if ($permDef === NULL) { // Not a perm constant
				continue; 
			} else if (PE_EDITUSER && !$isadmin && !has_perm($permName)) { // If we can't change it, leave the orignal one
				$permSet[$permDef[0]-1] = $permSet[$permDef[0]-1] | ($permOrig['set'.$permDef[0]] & $permDef[1]);
			} else if ($permVal) { // Just in case we explicitly check if this permission bit is enabled
				$permSet[$permDef[0]-1] = $permSet[$permDef[0]-1] | $permDef[1];
			}
			
		}
		
		if (PE_EDITGROUP) {
			// Groups
			$fieldText = "";
			if ($_GET['id'] > -1) {
				// Update existing
				for ($i = 1; $i <= $miscdata['perm_fields']; ++$i) {
					$fieldText .= ($i != 1 ? ", " : "")."set{$i} = ?"; // {$permSet[$i]}
				}
				// Extra group info we need
				$setPerms = $sql->prepare("UPDATE perm_groups SET {$fieldText}, name = ?, ord = ?, namecolor0 = ?, namecolor1 = ?,namecolor2 = ? WHERE id = {$_GET['id']}");
			} else {
				// Insert new
				for ($i = 1; $i <= $miscdata['perm_fields']; ++$i) {
					$fieldText .= ($i != 1 ? ", " : "")."?"; // {$permSet[$i]}
				}
				$setPerms = $sql->prepare("
					INSERT INTO perm_groups (".perm_fields().", name, ord, namecolor0, namecolor1, namecolor2) 
					VALUES ({$fieldText},?,?,?,?,?)
				");
			}
			$permSet[] = $_POST['name'];
			$permSet[] = filter_int($_POST['ord']);
			$permSet[] = $_POST['namecolor0'];
			$permSet[] = $_POST['namecolor1'];
			$permSet[] = $_POST['namecolor2'];
		} else {
			// Users
			$fieldText = "";
			for ($i = 1; $i <= $miscdata['perm_fields']; ++$i) {
				$fieldText .= ($i != 1 ? ", " : "")."set{$i} = VALUES(set{$i})";
			}
			$setPerms = $sql->prepare("
				INSERT INTO perm_users (id, ".perm_fields().") 
				VALUES ({$_GET['id']}, ".implode(",", $permSet).")
				ON DUPLICATE KEY UPDATE {$fieldText}
			");
			// We don't need it anymore
			$permSet = array();
		}

		if ($sql->execute($setPerms, $permSet)) {

			if (PE_EDITGROUP) {
				if ($_GET['id'] <= -1) {
					$newid = $sql->insert_id();
					$sql->query("ALTER TABLE `perm_forums` ADD `group{$newid}` TINYINT(3) NOT NULL DEFAULT '0'");
					
					if ($_GET['copy']) {
						// Copy over the forums perm settings
						$sql->query("UPDATE perm_forums SET group{$newid} = group{$_GET['copy']}");
					}
				}
				errorpage("Permissions saved.", 'admin-editperms.php', 'the permission editor');
			} else {
				$pname = $sql->resultq("SELECT name FROM users WHERE id = {$_GET['id']}");
				errorpage("Permissions saved.", "profile.php?id={$_GET['id']}", "$pname's profile");
			}
		} else {
			errorpage("A MySQL error occurred. Check the MySQL debugger for more details.");
		}
	}
	else if (PE_EDITUSER && isset($_POST['editf'])) {
		check_token($_POST['auth']);
		$user = $sql->fetchq("SELECT id, `group` FROM users WHERE id = {$_GET['id']}");
		$perms = $sql->query("
			SELECT f.id, pf.group{$user['group']} forumperm, pu.permset userperm
			FROM forums f
			LEFT JOIN perm_forums     pf ON f.id    = pf.id
			LEFT JOIN perm_forumusers pu ON f.id    = pu.forum AND pu.user = {$user['id']}
			".($isadmin ? "" : "WHERE f.id IN (SELECT f.id FROM forums f WHERE f.user = {$loguser['id']} AND f.custom = 1)")."
			".($_GET['f'] ? "AND f.id = {$_GET['f']}" : "")."
		");
		while ($x = $sql->fetch($perms)) {
			$pset = filter_int($_POST["fperm{$x['id']}r"]) | filter_int($_POST["fperm{$x['id']}p"]) | filter_int($_POST["fperm{$x['id']}e"]) | filter_int($_POST["fperm{$x['id']}d"]) | filter_int($_POST["fperm{$x['id']}t"]) | filter_int($_POST["fperm{$x['id']}m"]);
			
			if ($pset == $x['forumperm'] && isset($x['userperm'])) {
				// Delete duplicate entry
				$sql->query("DELETE from perm_forumusers WHERE user = {$_GET['id']} AND  forum = {$x['id']}");
			} else if ($pset == $x['forumperm'] || $pset == $x['userperm']) {
				// Nothing changed, move on
				continue;
			} else if (!isset($x['userperm'])) {
				// No custom permset
				$sql->query("INSERT INTO perm_forumusers (user, forum, permset) VALUES ({$_GET['id']},{$x['id']},'$pset')");
			} else {
				// Permset already existing
				$sql->query("UPDATE perm_forumusers SET permset = '$pset' WHERE user = {$_GET['id']} AND forum = {$x['id']}");
			}
		}
		header("Location: ?mode={$_GET['mode']}&id={$_GET['id']}&f={$_GET['f']}");
		die;		
	}
	else if (PE_EDITUSER && isset($_POST['resetf'])) {
		check_token($_POST['auth']);
		$sql->query("
			DELETE from perm_forumusers 
			WHERE user = {$_GET['id']}".
			($_GET['f'] ? " AND forum = {$_GET['f']}" : "").
			(
				has_perm('sysadmin-actions') ? 
				"" : 
				" AND forum IN (SELECT f.id FROM forums f WHERE f.user = {$loguser['id']} AND f.custom = 1)"
			)
		);
		header("Location: ?mode={$_GET['mode']}&id={$_GET['id']}&f={$_GET['f']}");
		die;
	}
	else {
	
		if (PE_EDITGROUP) {
			$ptflags = 0;
			if ($_GET['copy']) {
				// Creating a new group mirroring another?
				$group = $sql->fetchq("SELECT name, ord, namecolor0, namecolor1, namecolor2, ".perm_fields()." FROM perm_groups WHERE id = {$_GET['copy']}");
				if (!$group) {
					errorpage("A group with ID #{$_GET['copy']} doesn't exist.");
				}
				$editingLabel = "a new group";
			} else if ($_GET['id'] > -1) {
				// Editing an existing group?
				$group = $sql->fetchq("SELECT name, ord, namecolor0, namecolor1, namecolor2, ".perm_fields()." FROM perm_groups WHERE id = {$_GET['id']}");
				if (!$group) {
					errorpage("A group with ID #{$_GET['id']} doesn't exist.");
				}
				$editingLabel = "group '<span style='color: #{$group['namecolor0']}'>{$group['name']}</span>'";
			} else {
				// Creating a new group?
				$group = array(
					'name' => '',
					'namecolor0' => '',
					'namecolor1' => '',
					'namecolor2' => '',
					'ord' => 0,
				);
				for ($i = 1; $i <= $miscdata['perm_fields']; ++$i) {
					$group["set$i"] = 0;
				}
				$editingLabel = "a new group";
			}
		} else {
			if (!has_perm('admin-actions')) {
				$ptflags = PT_READONLY;
			} else if (!has_perm('sysadmin-actions')) {
				$ptflags = PT_CHECKPERM;
			} else {
				$ptflags = 0;
			}
			// Editing user permissions?
			// (the perm_users entry is optional)
			$group = $sql->fetchq("
				SELECT 	$userfields,
						".perm_fields('p', 'groupset').",
						".perm_fields('q', 'userset')."
				FROM users u
				LEFT JOIN perm_groups p ON u.`group` = p.id
				LEFT JOIN perm_users  q ON u.`id` = q.id
				WHERE u.id = {$_GET['id']}
			");
			if (!$group) {
				errorpage("An user with ID #{$_GET['id']} doesn't exist.");
			}
			
			// Determine to use user permset or group default
			for ($k = 1; $k <= $miscdata['perm_fields']; $k++) {
				if (isset($group["userset$k"])) {
					$group["set$k"] = $group["userset$k"];
				} else {
					// Use default
					$group["set$k"] = $group["groupset$k"];
				}
				unset($group["userset$k"], $group["groupset$k"]);
			}
			
			$editingLabel = getuserlink($group);
			
			// Forum permissions tree
			$perms = $sql->query("
				SELECT 	f.id, f.title, f.catid, c.name catname,
						pf.group{$group['group']} forumperm, pu.permset userperm
				FROM forums f
				
				LEFT JOIN categories      c  ON f.catid = c.id
				LEFT JOIN perm_forums     pf ON f.id    = pf.id
				LEFT JOIN perm_forumusers pu ON f.id    = pu.forum AND pu.user = {$group['id']}
				WHERE ".($_GET['f'] ? "f.id = {$_GET['f']} AND " : "")."
				      ".($isadmin ? "1" : "f.id IN (SELECT f.id FROM forums f WHERE f.user = {$loguser['id']} AND f.custom = 1)")."
				      
				ORDER BY c.corder, f.catid, f.forder, f.id
			");
		}
		
		$txt = 	"<form method='POST' action='?id={$_GET['id']}&mode={$_GET['mode']}&copy={$_GET['copy']}&f={$_GET['f']}'>".
				"<input type='hidden' name=auth value='".generate_token()."'>";
		if ($isadmin) {
		$txt .= 
"<table class='w' style='padding: 0px; border-spacing: 0px'>
<tr>
	<td class='w' style='vertical-align: top'>
		<table class='table w'>
			<tr>
				<td class='tdbgh center' colspan=8>
					<b>Editing $editingLabel</b>
				</td>
			</tr>
			".(PE_EDITGROUP ? "
			<tr>
				<td class='tdbg1 center nobr'><b>Group name</b></td>
				<td class='tdbg2' colspan=3><input type='text' name='name' value='{$group['name']}' style='width: 250px'></td>
				<td class='tdbg1 center' colspan=2 rowspan=3><b>Group name color</b></td>
				<td class='tdbg1 center'><b>Male</b></td>
				<td class='tdbg2' colspan=2><input type='text' name='namecolor0' value='{$group['namecolor0']}' maxlength=6 size=6></td>
			</tr>
			<tr>
				<td class='tdbg1 center nobr'><b>Group order</b></td>
				<td class='tdbg2'><input type='text' name='ord' class='right' value='{$group['ord']}' maxlength=11 size=7></td>
				<td class='tdbg2' colspan=2>&nbsp;</td>
				<td class='tdbg1 center'><b>Female</b></td>
				<td class='tdbg2' colspan=2><input type='text' name='namecolor1' value='{$group['namecolor1']}' maxlength=6 size=6></td>
			</tr>
			<tr>
				<td class='tdbg2' colspan=4>&nbsp;</td>
				<td class='tdbg1 center'><b>N/A</b></td>
				<td class='tdbg2' colspan=2><input type='text' name='namecolor2' value='{$group['namecolor2']}' maxlength=6 size=6></td>
			</tr>
			" : "")."
			<tr>
				<td class='tdbgh fonts' style='width: 20%'>&nbsp;</td>
				<td class='tdbgh fonts' style='width: 5%'>&nbsp;</td>
				<td class='tdbgh fonts' style='width: 20%'>&nbsp;</td>
				<td class='tdbgh fonts' style='width: 5%'>&nbsp;</td>
				<td class='tdbgh fonts' style='width: 20%'>&nbsp;</td>
				<td class='tdbgh fonts' style='width: 5%'>&nbsp;</td>
				<td class='tdbgh fonts' style='width: 20%'>&nbsp;</td>
				<td class='tdbgh fonts' style='width: 5%'>&nbsp;</td>
			</tr>
			".permtable($ptflags)."
			<tr>
				<td class='tdbg2 center' colspan=8>
					<input type='submit' class='submit' name='edit' value='Set permissions'>
					".(PE_EDITUSER && has_perm('sysadmin-actions') ? "<input type='submit' class='submit' name='reset' value='Reset permissions'>" : "")."
				</td>
			</tr>
		</table>
	</td>";
		}
		if (PE_EDITUSER) {
			$txt .= (
				$isadmin ? 
				"<td style='vertical-align: top'><table class='table'>" : 
				"<center><table class='table' style='width: auto !important'><tr><td class='tdbgc center nobr' colspan=7><b>Editing $editingLabel's custom forum permissions</b></td></tr>").
			"<tr><td class='tdbgh center nobr' colspan=7><b>User permissions [Read/Post/Edit/Delete/Thread/Mod]</b></td></tr>
			<tr>
				<td class='tdbgh center ".($isadmin ? "w" : "")."'>&nbsp;</td>
				<td class='tdbgh center'><b>R</b></td>
				<td class='tdbgh center'><b>P</b></td>
				<td class='tdbgh center'><b>E</b></td>
				<td class='tdbgh center'><b>D</b></td>
				<td class='tdbgh center'><b>T</b></td>
				<td class='tdbgh center'><b>M</b></td>
			</tr>";
			$prevcat = NULL;
			while ($x = $sql->fetch($perms)) {
				if ($x['catid'] != $prevcat) {
					$txt .= "<tr><td class='tdbgc center' colspan=7><b>".($x['catname'] ? $x['catname'] : "Unknown Category ID #{$x['catid']}")."</b></td></tr>";
					$prevcat = $x['catid'];
				}
				if (isset($x['userperm'])) {
					$x['title'] = "<span style='border-bottom: 1px dotted #f00; font-weight: bold' title='Modified'>*</span>".$x['title'];
				} 
				$txt .= 
				"<tr>
					<td class='tdbg1 nobr'>{$x['title']}</td>
					<td class='tdbg2 center'><input type='checkbox' name='fperm{$x['id']}r' value=".PERM_FORUM_READ  .(has_forum_perm('read'  , $x, true) ? " checked" : "")."></td>
					<td class='tdbg2 center'><input type='checkbox' name='fperm{$x['id']}p' value=".PERM_FORUM_POST  .(has_forum_perm('post'  , $x, true) ? " checked" : "")."></td>
					<td class='tdbg2 center'><input type='checkbox' name='fperm{$x['id']}e' value=".PERM_FORUM_EDIT  .(has_forum_perm('edit'  , $x, true) ? " checked" : "")."></td>
					<td class='tdbg2 center'><input type='checkbox' name='fperm{$x['id']}d' value=".PERM_FORUM_DELETE.(has_forum_perm('delete', $x, true) ? " checked" : "")."></td>
					<td class='tdbg2 center'><input type='checkbox' name='fperm{$x['id']}t' value=".PERM_FORUM_THREAD.(has_forum_perm('thread', $x, true) ? " checked" : "")."></td>
					<td class='tdbg2 center'><input type='checkbox' name='fperm{$x['id']}m' value=".PERM_FORUM_MOD   .(has_forum_perm('mod'   , $x, true) ? " checked" : "")."></td>
				</tr>";
			}
			$txt .= "
			<tr>
				<td class='tdbg2 center' colspan=7>
					<input type='submit' class='submit' name='editf' value='Set permissions'>
					<input type='submit' class='submit' name='resetf' value='Reset permissions'>
				</td>
			";
			if ($isadmin) $txt .= "</tr></table></td>";
		}
		
		$txt .= "
</tr>
</table>
</center>
</form>";

	}
}


pageheader($windowtitle);

print adminlinkbar();
print $txt;

pagefooter();

function permtable($flags = 0) {
	global $group; // permformat import
	
	// In case we need a permission display for non-sysadmins
	$readOnly  = ($flags & PT_READONLY) ? "disabled readonly" : "";
	$checkPerm = (!$readOnly && ($flags & PT_CHECKPERM));
	
	$txt 	= "";
	$i 		= 0;
	
	foreach (PT_PERMLIST as $name => $desc) {
		// Four tds on each row
		if ($i % 4 == 0) {
			if ($i) {
				$txt .= "</tr><tr>";
			} else {
				$txt .= "<tr>";
			}
		}
		if ($desc !== NULL) {
			$permArray = constant("PERM_" . str_replace("-", "_", strtoupper($name)));
			
			if ($checkPerm) {
				// If we don't have the permission set, we aren't allowed to change it.
				// This is to make sure normal admins cannot set themselves permissions like 'sysadmin-actions'
				$readOnly = has_perm($name) ? "" : "disabled readonly";
			}

			$txt .= 
			"<td class='tdbg1'>{$desc}</td>".
			"<td class='tdbg2'>".		
				"<select name='{$name}' {$readOnly}>".
					"<option value=0 ".($permArray[1] & $group['set'.$permArray[0]] ? "" : "selected").">Disabled</option>".
					"<option value=1 ".($permArray[1] & $group['set'.$permArray[0]] ? "selected" : "").">Enabled</option>".
				"</select>".
			"</td>";
			++$i;
		} else {
			for(; $i % 4 != 0; ++$i) {
				$txt .= "<td class='tdbg2' colspan=2>&nbsp;</td>";
			}			
			$txt .= "</tr><tr><td class='tdbgc center fonts' colspan=8>$name</td>";
		}
	}
	// Leftover rows
	for(; $i % 4 != 0; ++$i) {
		$txt .= "<td class='tdbg2' colspan=2>&nbsp;</td>";
	}
	return $txt."</tr>";
}