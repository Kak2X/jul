<?php

	require "lib/common.php";
	require "lib/uploader_function.php";
	
	// Definitions for the min allowed permissions.
	const _READPERM_MIN = PWL_MIN;
	const _UPLOADPERM_MIN = PWL_BANNED;
	const _MANAGEPERM_MIN = PWL_SUPER;
	
	if (!$loguser['id'])
		errorpage("You need to be logged in to use this feature.");
	if ($loguser['uploader_locked'] || $banned)
		errorpage("Sorry, but you cannot access this feature.");
	
	$_GET['cat'] = filter_int($_GET['cat']);	
	$_GET['action'] = filter_string($_GET['action']);
	
	define('_NEW_CATEGORY', ($_GET['cat'] == -1));
	define('_MODE_USER', ($_GET['mode'] == 'u'));
	
	if (_NEW_CATEGORY && $_GET['action'] == 'delete')
		errorpage("What r u doin dude.");
	if (!$isadmin) {
		if (!$_GET['cat'] && !$_GET['user'])
			errorpage("You aren't allowed to manage shared folders.");
		if ($_GET['user'] != $loguser['id'])
			errorpage("You aren't allowed to manage other users' personal folders.");
	}
	
	// Filter by category
	if ($_GET['cat'] && !_NEW_CATEGORY) {
		load_uploader_category($_GET['cat']);
		if (!can_manage_category($cat))
			errorpage("You aren't allowed to edit this folder.");	
	}
	// Filter by user
	$user = uploader_load_user($_GET['user']);
	
	// This can only be the edit action
	if (isset($_POST['submit']) || isset($_POST['submit2'])) {
		check_token($_POST['auth']);
		
		$_POST['title']          = filter_string($_POST['title']);
		$_POST['user']           = filter_int($_POST['user']);
		$_POST['minpowerread']   = numrange(filter_int($_POST['minpowerread']), _READPERM_MIN, PWL_ADMIN);
		$_POST['minpowerupload'] = numrange(filter_int($_POST['minpowerupload']), _UPLOADPERM_MIN, PWL_ADMIN);
		$_POST['minpowermanage'] = numrange(filter_int($_POST['minpowermanage']), _MANAGEPERM_MIN, PWL_MOD);
		
		if (!trim($_POST['title']))
			errorpage("You need to specify a folder title.");
		
		// Two variations of the same check
		if (!$isadmin) {
			if (_NEW_CATEGORY) {
				if ($_POST['user'] != $loguser['id'])
					errorpage("You cannot create shared or other users' folders.");
			} else {
				if ($loguser['id'] != $cat['user'] && $_POST['user'] != $cat['user'])
					errorpage("You can't reassign this folder's ownership.");
			}
		}
		if ($_POST['user'] != 0 && !valid_user($_POST['user']))
			errorpage("This user you selected as owner doesn't exist.");
		
		if ($_POST['user'] || _uploader_private_file_confirmation($_GET['cat'])) {
			$values = array(
				'title'          => $_POST['title'],
				'description'    => filter_string($_POST['description']),
				'user'           => $_POST['user'],
				'ord'            => filter_int($_POST['ord']),
				'minpowerread'   => $_POST['minpowerread'],
				'minpowerupload' => $_POST['minpowerupload'],
				'minpowermanage' => $_POST['minpowermanage'],
			);
			
			$sql->beginTransaction();
			if (_NEW_CATEGORY) {
				$sql->queryp("INSERT INTO uploader_cat SET ".mysql::setplaceholders($values)."", $values);
				$_GET['cat'] = $sql->insert_id();
			} else {
				$sql->queryp("UPDATE uploader_cat SET ".mysql::setplaceholders($values)." WHERE id = {$_GET['cat']}", $values);
				if ($values['user'] == 0)
					$sql->query("UPDATE uploader_files SET private = 0 WHERE cat = {$_GET['cat']}");
			}
			$sql->commit();
		}
		
		if (isset($_POST['submit2']))
			return header("Location: {$baseparams}");
		else
			return header("Location: {$baseparams}&action=edit&cat={$_GET['cat']}");
		
	}

	
	pageheader("Uploader Manager");
	
	if ($isadmin && !isset($_GET['noadmin'])) {
		print adminlinkbar($scriptname, $_GET['mode'] ? "?mode={$_GET['mode']}" : "?", [
			actionlink(null,"?") => "Shared folders",
			actionlink(null,"?mode=u") => "Personal folders",
		]);
	}
	$links = uploader_breadcrumbs_links(null, $user, [["Manager", NULL]]);
	$barright = "";
	if ($isadmin) {
		if (_MODE_USER)
			$barright = uploader_user_select('user', $_GET['user'])." - <a href='".actionlink()."'>Manage shared folders</a>";
		else
			$barright = "<a href='".actionlink(null, "?mode=u")."'>Manage personal folders</a>";
	}
	$breadcrumbs = dobreadcrumbs($links, $barright);

	if ($_GET['action'] == 'edit') {
		
		if (_NEW_CATEGORY) {
			// Item edit
			$htitle = "New folder";
			$cat = [
				'title' => "",
				'description' => "",
				'private' => 0,
				'minpowerread' => PWL_MIN,
				'minpowermanage' => PWL_MOD,
				'ord' => 0,
			];
			if (_MODE_USER) {
				$cat['user'] = ($_GET['user'] ? $_GET['user'] : $loguser['id']);
				$cat['minpowerupload'] = PWL_MOD;
			} else {
				$cat['user'] = 0;
				$cat['minpowerupload'] = PWL_NORMAL;
			}
		} else {
			$htitle = "Editing folder '".htmlspecialchars($cat['title'])."'";
		}
		
		$selperm = ($isadmin || $loguser['id'] == $cat['user']) ? 0 : SEL_DISABLED;
		
?>
		<form method="POST" action="<?=actionlink(null, "{$baseparams}&action=edit&cat={$_GET['cat']}")?>">
		<table class="table">
			<tr><td class="tdbgh center b" colspan="4"><?= $htitle ?></tr>
			<tr>
				<td class="tdbg1 center b" style="width: 150px">Title</td>
				<td class="tdbg2"><input type="text" name="title" value="<?= htmlspecialchars($cat['title']) ?>" style="width: 300px"></td>
				<td class="tdbgh center b" colspan="2">Powerlevel required to...</td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Description</td>
				<td class="tdbg2"><input type="text" name="description" class="w" value="<?= htmlspecialchars($cat['description']) ?>"></td>
				<td class="tdbg1 center b">...view</td>
				<td class="tdbg2"><?= power_select('minpowerread', $cat['minpowerread'], _READPERM_MIN, PWL_ADMIN, $selperm) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Order</td>
				<td class="tdbg2"><input type="text" name="ord" value="<?= $cat['ord'] ?>" style="width: 70px"></td>
				<td class="tdbg1 center b">...upload</td>
				<td class="tdbg2"><?= power_select('minpowerupload', $cat['minpowerupload'], _UPLOADPERM_MIN, PWL_ADMIN, $selperm) ?></td>
			</tr>
			<tr>
<?php if ($isadmin) { ?>
				<td class="tdbg1 center b">Owner</td>
				<td class="tdbg2">
					<?= user_select('user', $cat['user'], '', '*** Shared folder ***')?>
					<div class="fonts">When someone is the owner of a folder, they will have full control over it, regardless of permissions outside of being banned.</div>
				</td>
<?php } else { ?>
				<td class="tdbg1"><input type="hidden" name="user" value="<?= $cat['user']?>" data-hah="don't try changing this. it won't work"></td>
				<td class="tdbg2 fonts">Note: you always have permission to upload files in your own folders.</td>
<?php }?>
				<td class="tdbg1 center b">...manage</td>
				<td class="tdbg2"><?= power_select('minpowermanage', $cat['minpowermanage'], _MANAGEPERM_MIN, PWL_MOD, $selperm) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b"><?= auth_tag() ?></td>
				<td class="tdbg2" colspan="3"><input type="submit" name="submit" value="Save and continue"> <input type="submit" name="submit2" value="Save and close"></td>
			</tr>
		</table>
		</form>
		<br>
<?php
	}
	else if ($_GET['action'] == 'delete') {		
		// Essentially what we're doing is moving files to another folder
		// ie: only needs upload permissions
		if (confirmed($msgkey = 'del-cat')) {
			$_POST['mergeid'] = filter_int($_POST['mergeid']);
			if ($_POST['mergeid'] == -1)
				errorpage("You forgot to select a directory.");
			
			$validcats = uploader_filter_cat($_GET['cat'], UCS_DEFAULT | UCS_UPLOADPERM);
			if (!isset($validcats[$_POST['mergeid']]))
				errorpage("You aren't allowed to move files to this category.");
			
			$allowprivate = $validcats[$_POST['mergeid']]['user']; // in user category
			if ($allowprivate || _uploader_private_file_confirmation($_GET['cat'])) {
				$sql->beginTransaction();
				$sql->query("UPDATE uploader_files SET cat = {$_POST['mergeid']}".(!$allowprivate ? ", private = 0" : "")." WHERE cat = {$_GET['cat']}");
				$sql->query("UPDATE uploader_cat SET files = files + {$cat['files']}, downloads = downloads + {$cat['downloads']} WHERE id = {$_POST['mergeid']}");
				$sql->query("DELETE FROM uploader_cat WHERE id = {$_GET['cat']}");
				$sql->commit();
				
				errorpage("The category has been deleted!", actionlink(null, $baseparams), "the uploader");
			}
			return header("Location: $baseparams");
		}
		
		$title   = "Delete Category";
		$message = "Are you sure you want to <b>delete</b> the category '".htmlspecialchars($cat['title'])."'?<br>".
		           "All files will be moved to the category below.<br>".
		           uploader_cat_select('mergeid', $_GET['cat'], UCS_DEFAULT | UCS_UPLOADPERM, "Choose a category to merge the files into...");
		$form_link = actionlink("uploader-catman.php{$baseparams}&action=delete&cat={$_GET['cat']}");
		$buttons   = array(
			[BTN_SUBMIT, "DELETE"],
			[BTN_URL   , "Cancel", actionlink(null, $baseparams)]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	
	if (_MODE_USER) {
		$where = ($_GET['user'] ? "c.user = {$_GET['user']}" : "c.user != 0");
	} else {
		$where = "c.user = 0".($isadmin ? "" : " AND minpowermanage >= {$loguser['powerlevel']}");
	}
	
	$cats = $sql->query("
		SELECT c.id, c.title, c.description, c.files, c.downloads, $userfields uid
		FROM uploader_cat c
		LEFT JOIN users u ON c.user = u.id
		WHERE {$where}
		ORDER BY c.ord ASC, c.id ASC
	");
	
	print $breadcrumbs;
?>
	<table class="table">
		<tr><td class="tdbgh center b" colspan="6">Folder manager</td></tr>
		<tr>
			<td class="tdbgh center b" style="width: 100px"></td>
			<td class="tdbgh center b">Title</td>
			<td class="tdbgh center b" style="width: 100px">By</td>
			<td class="tdbgh center b" style="width: 100px">Files</td>
			<td class="tdbgh center b" style="width: 100px">Downloads</td>
		</tr>
	
<?php	while ($x = $sql->fetch($cats)) { ?>

		<tr>
			<td class="tdbg2 center fonts nobr">
				<a href="<?=actionlink(null, "{$baseparams}&action=edit&cat={$x['id']}")?>">Edit</a> - <a href="<?=actionlink(null, "{$baseparams}&action=delete&cat={$x['id']}")?>">Delete</a>
			</td>
			<td class="tdbg1">
				<a href="<?=actionlink("uploader.php{$baseparams}&cat={$x['id']}")?>"><?= htmlspecialchars($x['title']) ?></a>
				<span class="fonts"><br/><?= htmlspecialchars($x['description']) ?></span>
			</td>
			<td class="tdbg2 center"><?= ($x['uid'] ? getuserlink($x, $x['uid']) : "<i>Shared</i>") ?></td>
			<td class="tdbg2 center"><?= $x['files'] ?></td>
			<td class="tdbg2 center"><?= $x['downloads'] ?></td>
		</tr>			

<?php	} ?>
		<tr><td class="tdbgc center" colspan="6"><a href="<?=actionlink(null, "{$baseparams}&action=edit&cat=-1")?>">Add a new folder</a></td></tr>
	</table>
<?php
	
	print $breadcrumbs;
	pagefooter();

function _uploader_private_file_confirmation($id) {
	global $sql, $baseparams;
	$uhoh = $sql->resultq("SELECT COUNT(*) FROM uploader_files WHERE cat = {$id} && private = 1");
	if ($uhoh && !confirmed($msgkey = 'prv-warn')) {
		$title = "Private files will become public";
		$message = "
			This folder contains {$uhoh} private file".($uhoh != 1 ? "s" : "").".<br>
			Because private files are not allowed in shared folders,<br>
			continuing will <b>make public all of the files</b>.<br>
			<br>
			Are you sure you want to continue?
		";
		$form_link = actionlink(null, "{$baseparams}&action={$_GET['action']}&cat={$_GET['cat']}");
		$buttons   = array(
			[BTN_SUBMIT, "Yes"],
			[BTN_URL   , "No", actionlink(null, $baseparams)]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	return true;
}