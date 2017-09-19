<?php

require "lib/function.php";

if (!$loguser['id']) {
	errorpage("You need to be logged in to edit your private message folders.", 'login.php', 'log in (then try again)');
}

$windowtitle = "{$config['board-name']} -- Private Message Folders";

$_GET['id']   = filter_int($_GET['id']);   // User
$_GET['edit'] = filter_int($_GET['edit']); // Folder edit
$_GET['del']  = filter_int($_GET['del']);  // Folder delete


// Viewing someone else?
if (has_perm('view-others-pms') && $_GET['id']) {
	$u 			= $_GET['id'];
	$idparam 	= "id={$_GET['id']}&";
} else {
	$u 			= $loguser['id'];
	$idparam 	= '';
}

if (isset($_POST['edit'])) {
	check_token($_POST['auth']);
	
	$querycheck = array();

	
	$qadd = mysql::setplaceholders("title","ord");
	$values = array(
		'title' 			=> xssfilters(filter_string($_POST['foldertitle'], true)),
		'ord'				=> filter_int($_POST['folderorder']),
	);

	if ($_GET['edit'] <= -1) {
		$sql->queryp("INSERT INTO `pmsg_folders` SET $qadd, user = $u", $values, $querycheck);
	} else {
		$sql->queryp("UPDATE `pmsg_folders` SET $qadd WHERE `id` = '{$_GET['edit']}' AND `user` = '$u'", $values, $querycheck);
	}
	
	if ($querycheck[0] !== false) {
		return header("Location: ?{$idparam}");
	} else {
		errorpage("Could not save the settings.");
	}

}
elseif (isset($_POST['del'])) {
	check_token($_POST['auth']);
	
	$_POST['mergeid'] = filter_int($_POST['mergeid']);

	if ($_GET['del'] <= 0)
		errorpage("No folder selected to delete.");
	if ($_POST['mergeid'] <= 0)
		errorpage("No folder selected to merge to.");
	$valid = $sql->resultq("SELECT 1 FROM pmsg_folders WHERE id = {$_POST['mergeid']} AND id != {$_GET['del']}");
	if (!$valid)
		errorpage("No valid folder selected to merge to.");
	
	$querycheck = array();
	$sql->beginTransaction();
	$sql->query("UPDATE `pmsgs` SET `folderto` = '{$_POST['mergeid']}' WHERE `folderto` = '{$_GET['del']}'", false, $querycheck);
	$sql->query("DELETE FROM `pmsg_folders` WHERE `id` = '{$_GET['del']}'", false, $querycheck);
	
	if ($sql->checkTransaction($querycheck)) {
		return header("Location: ?{$idparam}");
	} else {
		errorpage("Could not delete the folder.");
	}
}
else if ($_GET['del']) {
	$folders = $sql->fetchq("SELECT id, title FROM pmsg_folders WHERE user = {$u} ORDER BY ord, id ASC", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
	
	if (isset($folders[$_GET['del']])) {
		$fname = htmlspecialchars($folders[$_GET['del']]);
		unset($folders[$_GET['del']]);
		
		$dropdownList = "";
		foreach ($folders as $i => $x) {
			$dropdownList .= "<option value='$i'>".htmlspecialchars($x)."</option>\n\r";
		}

		pageheader($windowtitle . " - Deleting ".$fname);
	?>
	<form method="post" action="?del=<?=$_GET['del']?>">
	<table class='table'>
		<tr><td class='tdbgh center'>Deleting <b><?=$fname?></b></td></tr>
		<tr>
			<td class='tdbgc center'>
				You are about to delete the folder <b><?=$fname?></b>.<br>
				<br>
				All private messages will be moved to the folder below.<br>
				<select name='mergeid'>
					<option value='-1' selected>Choose a folder to merge into...</option>
					<?=$dropdownList?>
				</select>
			</td>
		</tr>
		<tr>
			<td class='tdbgc center'>
				<input type="submit" name="del" value="Delete folder"> or <a href="?">Cancel</a>
				<input type="hidden" name="auth" value="<?=generate_token()?>">
			</td>
		</tr>
	</table>
	</form>
	<br>
	<?php
		pagefooter();
	
	} else {
		errorpage("This folder doesn't exist.");
	}
}
else if ($_GET['edit']) {
	$folder = $sql->fetchq("SELECT * FROM `pmsg_folders` WHERE `id` = '". $_GET['edit'] . "'");
	if (!$folder) {
		$_GET['edit'] = -1;
		$folder = array('title' => '', 'ord' => 0);
		$editingWhat = "a new folder";
	} else {
		$editingWhat = htmlspecialchars($folder['title']);
	}
	
	pageheader($windowtitle." - Editing ".$editingWhat);
?>
	<form method="post" action="?<?=$idparam?>&edit=<?=$_GET['edit']?>">
	<table class='table'>
		<tr>
			<td class='tdbgh center' colspan=2>Editing <b><?=$editingWhat?></b></td>
		</tr>

		<tr>
			<td class='tdbgh center' style='width: 140px'>Folder Title</td>
			<td class='tdbg1'><input type="text" name="foldertitle" value="<?=htmlspecialchars($folder['title'])?>"  size=48 maxlength=64></td>
		</tr>
		<tr>
			<td class='tdbgh center' style='width: 140px'>Order</td>
			<td class='tdbg1'><input type="text" class="right" name="folderorder" value="<?=$folder['ord']?>"  size=3 maxlength=3></td>
		</tr>
		
		<tr>
			<td class='tdbgc center' colspan=2>
				<input type="submit" name="edit" value="Save settings">
				<input type="hidden" name="auth" value="<?=generate_token()?>">
			</td>
		</tr>

	</table>
	</form>
	<br>
<?php
} 
else {
	pageheader($windowtitle);
}
	$folders = $sql->fetchq("
		SELECT f.id, f.title, COUNT(p.id) pmnum
		FROM pmsg_folders f 
		LEFT JOIN pmsgs p ON f.id = p.folderto AND  p.userto = {$u}
		WHERE f.user = $u
		GROUP BY f.id
		ORDER BY f.ord, f.id ASC
	", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
	
	if ($u != $loguser['id'])
		$users_p = $sql->resultq("SELECT `name` FROM `users` WHERE `id` = $u")."'s p";
	else
		$users_p = "P";	
?>
	<span class="font"><a href='index.php'><?=$config['board-name']?></a> - <a href="private.php?<?=$idparam?>"><?=$users_p?>rivate messages</a> - Folder list</a>
	<table class="table">
		<tr>
			<td class="tdbgh center" style="width: 120px">&nbsp;</td>
			<td class="tdbgh center"><b>Folder Title</b></td>
			<td class="tdbgh center" style="width: 50px"><b>PMs</b></td>
		</tr>
		<tr>
			<td class="tdbgc center" colspan=3>
				<a href="?<?=$idparam?>&edit=-1">&lt; Create a new folder &gt;</a>
			</td>
		</tr>
<?php
	foreach ($folders as $fid => $x) {
		$cell = ($_GET['edit'] == $fid ? '1' : '2');
?>
		<tr>
			<td class="tdbg1 center fonts"><a href="?<?=$idparam?>&edit=<?=$fid?>">Edit</a> - <a href="?<?=$idparam?>&del=<?=$fid?>">Delete</a></td>
			<td class="tdbg<?=$cell?>"><a href="private.php?<?=$idparam?>&dir=<?=$fid?>"><?=htmlspecialchars($x['title'])?></a></td>
			<td class="tdbg1 center"><?=($x['pmnum'])?></td>
		</tr>
<?php
	}
?>
		<tr>
			<td class="tdbgc center" colspan=3>
				<a href="?<?=$idparam?>&edit=-1">&lt; Create a new folder &gt;</a>
			</td>
		</tr>
	</table>
<?php

pagefooter();