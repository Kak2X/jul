<?php
	require 'lib/function.php';

	if (!$loguser['id']) {
		errorpage("You must be logged in to edit your post radar.",'index.php','return to the board',0);
	}

	// Login confirmed from here on out
	// Changes above form to save a redirect
	if (isset($_POST['submit1']) || isset($_POST['submit2'])) {
		check_token($_POST['auth']);
		$rem = filter_int($_POST['rem']);
		$add = filter_int($_POST['add']);
		
		//$user = $sql->resultq("SELECT name FROM users WHERE id = {$loguser['id']}");
		if ($rem) $sql->query("DELETE FROM postradar WHERE user = {$loguser['id']} and comp = $rem");
		if ($add) $sql->query("INSERT INTO postradar (user, comp) VALUES ({$loguser['id']}, $add)");
		
		if (isset($_POST['submit2'])) { // Save and finish
			errorpage("Thank you, {$loguser['name']}, for editing your post radar.",'index.php','return to the board',0);
		}
	}

	// Form
	// Include layout now so post radar on top of page is properly updated
	//require 'lib/layout.php';
	pageheader("Editing Post Radar");

	// Deletions before additions
	$users1 = $sql->query("SELECT p.comp, u.name, u.posts FROM postradar p LEFT JOIN users u ON u.id = p.comp AND user = {$loguser['id']}");

	$remlist = "";
	while ($user = $sql->fetch($users1)) {
		$remlist .= "<option value='{$user['comp']}'>{$user['name']} -- {$user['posts']} posts</option>";
		$idlist[] = $user['comp'];
	}

	// Remove those already added
	$qwhere = isset($idlist) ? "AND id NOT IN (". implode(",", $idlist).")" : "";

	// Additions
	$users1 = $sql->query("SELECT id,name,posts FROM users WHERE posts > 0 {$qwhere} ORDER BY name");

	$addlist = "";
	while ($user = $sql->fetch($users1)){
		$addlist .= "<option value={$user['id']}>{$user['name']} -- {$user['posts']} posts</option>";
	}

?>
<FORM ACTION=postradar.php NAME=REPLIER METHOD=POST>
<table class='table'>
	<tr><td class='tdbgh center'>&nbsp;</td><td class='tdbgh center'>&nbsp;</td></tr>
	<tr>
		<td class='tdbg1 center'><b>Add an user</td>
		<td class='tdbg2'>
			<select name=add>
				<option value=0 selected>Do not add anyone</option>
				<?=$addlist?>
			</select>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center'><b>Remove an user</td>
		<td class='tdbg2'>
			<select name=rem>
				<option value=0 selected>Do not remove anyone</option>
				<?=$remlist?>
			</select>
		</td>
	</tr>
	<tr><td class='tdbgh center'>&nbsp;</td><td class='tdbgh center'>&nbsp;</td></tr>
	<tr>
		<td class='tdbg1 center'>&nbsp;</td>
		<td class='tdbg2'>
			<?= auth_tag() ?>
			<input type='submit' class=submit name=submit1 VALUE="Submit and continue">
			<input type='submit' class=submit name=submit2 VALUE="Submit and finish">
		</td>
	</tr>
</table>
</FORM>
<?php

	pagefooter();
?>