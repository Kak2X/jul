<?php

	require "lib/function.php";
	
	$_GET['id'] = filter_int($_GET['id']);
	if ($isadmin && $_GET['id']) {
		$user = $sql->fetchq("SELECT {$userfields}, extrafields FROM users u WHERE id = {$_GET['id']}");
		if (!$user)
			errorpage("This user doesn't exist.");
	} else {
		$user = $loguser;
	}
	
	
	if (isset($_POST['submit'])) {
		$_POST['del']   = filter_array($_POST['del']);
		$_POST['title'] = filter_array($_POST['title']);
		$_POST['val']   = filter_array($_POST['val']);
		
		$fields = [];
		for ($i = 0, $c = count($_POST['title']); $i < $c; ++$i) {
			if (!filter_bool($_POST['del'][$i]) && ($_POST['title'][$i] || $_POST['val'][$i]))
				$fields[$_POST['title'][$i]] = filter_string($_POST['val'][$i]);
		}
		
		$sql->queryp("UPDATE users SET extrafields = ? WHERE id = ?", [json_encode($fields), $user['id']]);
		header("Location: ?id={$user['id']}");
		die;
		//errorpage("Extra fields updated!", "?id={$user['id']}", "the extra fields editor");
	}
	
	
	pageheader("Extra fields");
	
	$fields = json_decode($user['extrafields'], true);
	if (!is_array($fields))
		$fields = [];
	
?>
	<form method="POST" action="?id=<?=$_GET['id']?>">
	<table class="table">
		<tr>
			<td class="tdbgc center" colspan=3><?= getuserlink($user) ?>'s profile fields</td>
		</tr>
		<tr>
			<td class="tdbgh center" style="width: 10px">DEL</td>
			<td class="tdbgh center" style="width: 250px">Field title</td>
			<td class="tdbgh center">Field value</td>
		</tr>
	<?php
	$i = 0;
	foreach ($fields as $title => $val) { ?>
		<tr>
			<td class="tdbg1 center"><input type="checkbox" name="del[<?=$i?>]" value="1"></td>
			<td class="tdbg1 vatop"><input type="text" name="title[<?=$i?>]" class="w" style="resize: vertical" value="<?=htmlspecialchars($title)?>"></td>
			<td class="tdbg2"><textarea name="val[]" class="w" style="resize: vertical" rows="1"><?=htmlspecialchars($val)?></textarea></td>
		</tr>
<?php	++$i;
	} ?>
		<tr>
			<td class="tdbg1 center">-</td>
			<td class="tdbg1 vatop"><input type="text" name="title[<?=$i?>]" class="w" style="resize: vertical" value=""></td>
			<td class="tdbg2"><textarea name="val[<?=$i?>]" class="w" style="resize: vertical" rows="1"></textarea></td>
		</tr>
		<tr>
			<td class="tdbg1" colspan=2></td>
			<td class="tdbg2"><input type="submit" name="submit" value="Save changes"></td>
		</tr>
	</table>
	</form>
<?php
	
	pagefooter();