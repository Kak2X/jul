<?php

require "lib/function.php";

$meta['noindex'] = true;

if (!$loguser['id']) {
	errorpage("You need to be logged in to edit your private message folders.", 'login.php', 'log in (then try again)');
}
//$config['pmthread-folder-limit'] = 4;
if ($config['pmthread-folder-limit'] < 0) {
	errorpage("The editing of custom folders has been disabled.", 'private.php', 'the private message box');
}
$windowtitle = "Private Message Folders";
$_GET['id']   = filter_int($_GET['id']);   // User
$_GET['edit'] = filter_int($_GET['edit']); // Folder edit
$_GET['del']  = filter_int($_GET['del']);  // Folder delete

// Viewing someone else?
if ($isadmin && $_GET['id']) {
	$valid      = $sql->resultq("SELECT 1 FROM users WHERE id = {$_GET['id']}");
	if (!$valid) {
		errorpage("This user doesn't exist!");
	}
	$u 			= $_GET['id'];
	$idparam 	= "id={$_GET['id']}";
} else {
	$u 			= $loguser['id'];
	$idparam 	= '';
}

// Endless folders are no fun
if ($config['pmthread-folder-limit']) {
	$limit = $sql->resultq("SELECT COUNT(*) FROM pm_folders WHERE user = {$u}");
	define('LIMIT_REACHED', $limit >= $config['pmthread-folder-limit']);
	unset($limit);
} else {
	define('LIMIT_REACHED', false);
}

if (isset($_POST['edit'])) { // Add or edit a folder
	check_token($_POST['auth']);
	$values = array(
		'title' 			=> xssfilters(filter_string($_POST['title'])),
		'ord'				=> filter_int($_POST['ord']),
	);
	if (!$values['title']) {
		errorpage("The folder name cannot be blank.");
	}
	
	$qadd = mysql::setplaceholders($values);
	$sql->beginTransaction();
	if ($_GET['edit'] <= -1) {
		if (LIMIT_REACHED) {
			errorpage("Go delete at least one folder before continuing, okay?", "?{$idparam}", "the folder editor");
		}
		$newid = ((int) $sql->resultq("SELECT MAX(folder) FROM pm_folders WHERE user = {$u}")) + 1;
		$sql->queryp("INSERT INTO `pm_folders` SET {$qadd}, folder = {$newid}, user = {$u}", $values);
	} else {
		$sql->queryp("UPDATE `pm_folders` SET {$qadd} WHERE `folder` = '{$_GET['edit']}' AND `user` = '{$u}'", $values);
	}
	$sql->commit();
	return header("Location: ?{$idparam}");
}
else if ($_GET['del']) { // Delete a folder and merge every PM in a new folder
	$folder = $sql->fetchq("SELECT folder, title FROM pm_folders WHERE user = {$u} AND folder = {$_GET['del']}");
	if (!$folder) {
		errorpage("This folder doesn't exist.");
	}
	$message = "
		You are about to delete the folder <b>".htmlspecialchars($folder['title']).".</b><br>
		<br>
		All private messages will be moved to the folder below.<br>
		".pm_folder_select('mergeid', $u, $_GET['del'], PMSELECT_MERGE);
	$form_link     = "?{$idparam}&del={$_GET['del']}";
	$buttons       = array(
		0 => ["Delete folder"],
		1 => ["Cancel", "?{$idparam}"]
	);
	if (confirmpage($message, $form_link, $buttons)) {	
		$_POST['mergeid'] = filter_int($_POST['mergeid']);
		$valid = $sql->resultq("SELECT COUNT(*) FROM pm_folders WHERE folder = {$_POST['mergeid']} AND folder != {$_GET['del']} AND user = {$u}");
		if (!default_pm_folder($_POST['mergeid'], DEFAULTPM_DEFAULT) && !$valid) {
			errorpage("No valid folder selected to merge to.");
		}
		$sql->beginTransaction();
		$sql->query("UPDATE `pm_access` SET `folder` = '{$_POST['mergeid']}' WHERE `folder` = '{$_GET['del']}' AND user = {$u}");
		$sql->query("DELETE FROM `pm_folders` WHERE `folder` = '{$_GET['del']}' AND user = {$u}");
		$sql->query("DELETE FROM `pm_foldersread` WHERE `folder` = '{$_GET['del']}' AND user = {$u}");
		$sql->commit();
		return header("Location: ?{$idparam}");
	}
}
else if ($_GET['edit']) { // Edit window
	$folder = $sql->fetchq("SELECT * FROM `pm_folders` WHERE `folder` = '{$_GET['edit']}' AND `user` = '{$u}'");
	if (!$folder) {
		$_GET['edit'] = -1;
		$folder = array('title' => '', 'ord' => 0);
		$editingWhat = "a new folder";
	} else {
		$editingWhat = htmlspecialchars($folder['title']);
	}
	
	pageheader($windowtitle." - Editing ".$editingWhat);
?>
	<center>
	<form method="post" action="?<?=$idparam?>&edit=<?=$_GET['edit']?>">
	<table class='table' style="max-width: 600px">
		<tr>
			<td class='tdbgh center' colspan=2>Editing <b><?=$editingWhat?></b></td>
		</tr>

		<tr>
			<td class='tdbg1 center b' style='width: 140px'>Folder Title:</td>
			<td class='tdbg2'><input type="text" name="title" value="<?=htmlspecialchars($folder['title'])?>"  size=48 maxlength=64></td>
		</tr>
		<tr id="ordtr" style="display: none">
			<td class='tdbg1 center b'>Reverse priority:</td>
			<td class='tdbg2'>
				<input type="text" class="right" name="ord" value="<?=$folder['ord']?>"  size=3 maxlength=3>
				<span class="fonts">Higher the value, further down the list the folder appears.</span>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class="tdbg2">
				<input type="submit" name="edit" value="Save settings">
				<?= auth_tag() ?>
			</td>
		</tr>

	</table>
	</form>
	</center>
<?php
} 
else {
	pageheader($windowtitle);
}

	$folders = $sql->query("
		SELECT f.folder, f.title, COUNT(a.id) pmnum
		FROM pm_folders f
		LEFT JOIN pm_access a ON f.folder = a.folder AND a.user = {$u}
		WHERE f.user = {$u}
		GROUP BY f.id
		ORDER BY f.ord, f.id ASC
	");
	$list = "";
	if (!$sql->num_rows($folders)) {
		$list .= "<tr><td class='tdbg1 center' colspan=3><i>No custom folders defined.</i></td></tr>";
	} else while ($x = $sql->fetch($folders)) {
		$cell = ($_GET['edit'] == $x['folder'] ? '1' : '2');
		$list .= "
		<tr>
			<td class='tdbg{$cell} center fonts'>
				<a href='?{$idparam}&edit={$x['folder']}'>Edit</a> - 
				<a href='?{$idparam}&del={$x['folder']}'>Delete</a>
			</td>
			<td class='tdbg{$cell}'>
				<a href='private.php?{$idparam}&dir={$x['folder']}'>".htmlspecialchars($x['title'])."</a>
			</td>
			<td class='tdbg{$cell} center'>{$x['pmnum']}</td>
		</tr>";
	}
	
	if ($u != $loguser['id']) {
		$users_p = htmlspecialchars($sql->resultq("SELECT `name` FROM `users` WHERE `id` = $u"))."'s p";
	} else {
		$users_p = "P";
	}
	
	if (LIMIT_REACHED) {
		$newtag = "&nbsp;"; //"";
		$newtag2 = "Max number of folders reached.";
	} else {
		$newtag = $newtag2 = "<a href='?{$idparam}&edit=-1'>&lt; Create a new folder &gt;</a>";
	}
?>
	<span class="font"><a href='index.php'><?=$config['board-name']?></a> - <a href="private.php?<?=$idparam?>"><?=$users_p?>rivate messages</a> - Custom folders</a>
	<table class="table">
		<tr>
			<td class="tdbgh center" style="width: 120px">&nbsp;</td>
			<td class="tdbgh center b">Folder Title</td>
			<td class="tdbgh center b" style="width: 50px">PMs</td>
		</tr>
		<tr><td class="tdbgc center" colspan=3><?= $newtag ?></td></tr>
		<?= $list ?>
		<tr><td class="tdbgc center" colspan=3><?= $newtag2 ?></td></tr>
	</table>
<?php

pagefooter();