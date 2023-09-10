<?php

// Quick redirect to cancel out _POST if nothing was selected
if (isset($_POST['dodel']) && !isset($_POST['delcheck'])) {
	return header("Location: ?{$_SERVER['QUERY_STRING']}");
}

require "lib/common.php";
$meta['noindex'] = true;

if (!$loguser['id']) {
	errorpage("You need to be logged in to edit your private message folders.", 'login.php', 'log in (then try again)');
}
//$config['pmthread-folder-limit'] = 4;
if ($config['pmthread-folder-limit'] < 0) {
	errorpage("The editing of custom folders has been disabled.", 'private.php', 'the private message box');
}

$windowtitle = "Private Message Folders";
$_GET['id']        = filter_int($_GET['id']);          // User
$_GET['edit']      = filter_int($_GET['edit']);        // Folder edit
$_POST['delcheck'] = filter_array($_POST['delcheck']); // Folder delete

// Viewing someone else?
if (!$isadmin || !valid_user($_GET['id'])) {
	$u = $loguser['id'];
	$_GET['id'] = 0;
} else {
	$u = $_GET['id'];
}
$idparam = opt_param(['id']);

// Endless folders are no fun
if ($config['pmthread-folder-limit']) {
	$limit = $sql->resultq("SELECT COUNT(*) FROM pm_folders WHERE user = {$u}");
	define('_LIMIT_REACHED', $limit >= $config['pmthread-folder-limit']);
	unset($limit);
} else {
	define('_LIMIT_REACHED', false);
}

if (isset($_POST['edit'])) { // Add or edit a folder
	check_token($_POST['auth']);
	$_POST['title'] = filter_string($_POST['title']);
	$_POST['ord']   = filter_int($_POST['ord']);
	if (!$_POST['title']) {
		errorpage("The folder name cannot be blank.");
	}
	$sql->beginTransaction();
	if ($_GET['edit'] <= -1) {
		if (_LIMIT_REACHED) {
			errorpage("Go delete at least one folder before continuing, okay?", "?{$idparam}", "the folder editor");
		}
		create_pm_folder($_POST['title'], $u, $_POST['ord']);
	} else {
		edit_pm_folder($_GET['edit'], $_POST['title'], $u, $_POST['ord']);
	}
	$sql->commit();
	return header("Location: ?{$idparam}");
}
else if ($_POST['delcheck']) { // Delete folders and merge every PM in a new folder
	$_POST['delcheck'] = array_map('intval', $_POST['delcheck']);
	if (!valid_pm_folder($_POST['delcheck'], $u, true)) {
		errorpage("You have selected at least one invalid folder.", "?{$idparam}", "the folder editor");
	}
	
	if (confirmed($msgkey = 'del-folder')) {	
		$_POST['mergeid'] = filter_int($_POST['mergeid']);
		if (in_array($_POST['mergeid'], $_POST['delcheck']) || !valid_pm_folder($_POST['mergeid'], $u)) {
			errorpage("No valid folder selected to merge to.", "?{$idparam}", "the folder editor");
		}
		
		$sql->beginTransaction(); // All OK
		delete_pm_folder($_POST['delcheck'], $_POST['mergeid'], $u);
		$sql->commit();
		return header("Location: ?{$idparam}");
	}
	
	$delfield  = "";
	/*foreach ($_POST['delcheck'] as $x) {
		$delfield .= "<input type='hidden' name='delcheck[]' value='{$x}'>";
	}*/
	
	$title   = "Delete folder";
	$message = "{$delfield}
		You are about to delete ".count($_POST['delcheck'])." folder(s).<br>
		<br>
		All private messages will be moved to the folder below.<br>
		".pm_folder_select('mergeid', $u, $_POST['delcheck'], PMSELECT_MERGE);
	$form_link     = "?{$idparam}";
	$buttons       = array(
		[BTN_SUBMIT, "Delete folder"],
		[BTN_URL   , "Cancel", "?{$idparam}"]
	);
	confirm_message($msgkey, $message, $title, $form_link, $buttons);
}
pageheader($windowtitle);

$folders = get_pm_folder($u);
if (!$folders) { 
	$list = "<tr><td class='tdbg1 center' colspan=4><i>No custom folders defined.</i></td></tr>";
} else {
	$totals  = get_pm_count($u);
	$list = "";
	foreach ($folders as $x) {
		$cell = ($_GET['edit'] == $x['folder'] ? '1' : '2');
		$list .= "
		<tr>
			<td class='tdbg{$cell} center'>
				<a href='?{$idparam}&edit={$x['folder']}'>Edit</a>
			</td>
			<td class='tdbg{$cell}'>
				<a href='private.php?{$idparam}&dir={$x['folder']}'>".htmlspecialchars($x['title'])."</a>
			</td>
			<td class='tdbg{$cell} center'>".filter_int($totals[$x['folder']])."</td>
			<td class='tdbg{$cell} center'><input type='checkbox' name='delcheck[]' value='{$x['folder']}'></td>
		</tr>";
	}
}

if (_LIMIT_REACHED) {
	$newtag = "Max number of folders reached.";
} else {
	$newtag = "<a href='?{$idparam}&edit=-1'>&lt; Create a new folder &gt;</a>";
}

$users_p = ($u != $loguser['id']) ? load_user($u)['name']."'s p" : "P";
$links = array(
	["{$users_p}rivate messages", "private.php?{$idparam}"],
	["Manage folders", NULL],
);
$right = "<a href='newpmthread.php?'>New conversation</a> - Manage folders";
?>
	<form method="POST" action="?<?=$idparam?>">
	<?= dobreadcrumbs($links, $right) ?>
	<table class="table">
		<tr>
			<td class="tdbgh center" style="width: 110px">&nbsp;</td>
			<td class="tdbgh center b">Folder Title</td>
			<td class="tdbgh center b" style="width: 40px">PMs</td>
			<td class="tdbgh center b" style="width: 110px">Delete</td>
		</tr>
		<?= $list ?>
		<tr>
			<td class="tdbg2 center">&nbsp;</td>
			<td class="tdbg2 center" colspan=2><?= $newtag ?></td>
			<td class="tdbg2 center"><input type="submit" name="dodel" class="fonts" value="Delete selected"></td>
		</tr>
	</table>
	</form>
<?php

if ($_GET['edit']) { // Edit window

	if (!isset($folders[$_GET['edit']])) {
		$_GET['edit'] = -1;
		$folder = array('title' => '', 'ord' => 0);
		$editingWhat = "a new folder";
	} else {
		$folder = $folders[$_GET['edit']];
		$editingWhat = htmlspecialchars($folder['title']);
	}
	
?>
	<center>
	<form method="post" action="?<?=$idparam?>&edit=<?=$_GET['edit']?>">
	<table class='table' style="max-width: 600px">
		<tr><td class='tdbgh center' colspan=2>Editing <b><?=$editingWhat?></b></td></tr>
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

pagefooter();