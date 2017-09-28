<?php
	require 'lib/function.php';

	if (!$loguser['id']) {
		errorpage("You must be logged in to edit your post radar.",'index.php','return to the board',0);
	}

	// Login confirmed from here on out
	// Changes above form to save a redirect
	if (isset($_POST['submit1']) || isset($_POST['submit2'])) {
		check_token($_POST['auth']);
		$_POST['rem']      = filter_int($_POST['rem']);
		$_POST['add']      = filter_int($_POST['add']);
		$_POST['automode'] = filter_int($_POST['automode']);
		
		if ($_POST['rem']) $sql->query("DELETE FROM postradar WHERE user = {$loguser['id']} and comp = {$_POST['rem']}");
		if ($_POST['add']) $sql->query("INSERT INTO postradar (user, comp) VALUES ({$loguser['id']}, {$_POST['add']})");
		if ($_POST['automode'] != $loguser['radar_mode']) {
			$sql->query("UPDATE users SET radar_mode = {$_POST['automode']} WHERE id = {$loguser['id']}");
		}
		
		if (isset($_POST['submit2'])) { // Save and finish
			errorpage("Thank you, {$loguser['name']}, for editing your post radar.",'index.php','return to the board',0);
		} else {
			// If we don't redirect them, we will still leave _POST information in our request
			// in other words, refreshing the page may give out warnings from the browser
			return header("Location: ?");
		}
	}

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
	
	// Layout auto selections
	$sel_auto = ($loguser['radar_mode'] == 1 ? 'checked' : '');

?>
<FORM method='POST' action='postradar.php'>
<table class='table'>
	<tr><td class='tdbgh center'>&nbsp;</td><td class='tdbgh center'>&nbsp;</td></tr>
	<tr>
		<td class='tdbg1 center b'>Add an user</td>
		<td class='tdbg2'>
			<select class='pr_dsel' name=add>
				<option value=0 selected>Do not add anyone</option>
				<?=$addlist?>
			</select>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Remove an user</td>
		<td class='tdbg2'>
			<select class='pr_dsel' name=rem>
				<option value=0 selected>Do not remove anyone</option>
				<?=$remlist?>
			</select>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Options</td>
		<td class='tdbg2'>
			<input type='checkbox' onchange='togglerows()' id='automode' name='automode' value='1' <?=$sel_auto?>>
			<label for='automode'>Use rank-based post radar</label>
			<div class='fonts'>This will disable the manual post radar selections.</div>
		</td>
	</tr>
	<tr><td class='tdbgh center'>&nbsp;</td><td class='tdbgh center'>&nbsp;</td></tr>
	<tr>
		<td class='tdbg1 center'>&nbsp;</td>
		<td class='tdbg2'>
			<input type='hidden' name=auth VALUE="<?=generate_token()?>">
			<input type='submit' class=submit name=submit1 VALUE="Submit and continue">
			<input type='submit' class=submit name=submit2 VALUE="Submit and finish">
		</td>
	</tr>
</table>
</FORM>
<script>
	var selectors = document.getElementsByClassName('pr_dsel');
	var checkbox  = document.getElementById('automode');
	function togglerows() {
		selectors[0].disabled = checkbox.checked;
		selectors[1].disabled = checkbox.checked;
	}
	togglerows();
</script>
<?php

	pagefooter();
?>