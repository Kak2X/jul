<?php

// NoJS user switch
if (isset($_GET['go'])) {
	if (!isset($_GET['usel'])) $_GET['usel'] = 0;
	return header("Location: avatar.php?id={$_GET['usel']}");
}

require 'lib/function.php';




pageheader("Mood Avatar Preview", NULL, NULL, true); // Small header


$me = false;

$isadmin = has_perm('admin-actions');

// Build the select box options for the user selection
$users = $sql->query("
	SELECT u.id, u.name, COUNT(*) avcount
	FROM users u
	LEFT JOIN user_avatars a ON u.id = a.user
	".($isadmin ? "" : "WHERE a.hidden = 0")."
	GROUP BY u.id
");

$options = "";
while ($u = $sql->fetch($users)) {
	if ($u['id'] == $_GET['id']) {
		$selected = ' selected';
		$me       = $u;
	} else {
		$selected = "";
	}
	$options .= "<option value='{$u['id']}'$selected>{$u['id']}: {$u['name']} ({$u['avcount']})</option>";
}

$_GET['id'] = filter_int($_GET['id']);
if ($_GET['id']) {
	$avlist = getavatars($_GET['id'], true);
} else {
	$avlist = false;
}


// This user has no avatars (or no user was defined)
if ($avlist) {
	
	// Output the javascript right away
	echo include_js('avatars.js');
	?><noscript><style type="text/css">.hideme{display: none}</style></noscript><?php

	$ret = "
	<tr>
		<td class='tdbgh center' colspan=2>
			".getuserlink(NULL, $me['id'])."
		</td>
	</tr>
	<tr style='height: 400px'>
		<td class='tdbg1 b' style='width:200px'><div style='height: 400px; overflow-y: scroll'>Mood avatar list:<br>";
		
	$_GET['startnum'] = filter_int($_GET['startnum']);
	//if (!$_GET['startnum']) $_GET['startnum'] = 1;
	
	foreach($avlist as $file => $data) {
		if (!$isadmin && $data['hidden']) continue;
		
		
		$jsclick = "onclick='avatarpreview({$me['id']},{$file})'";
		$selected = (($file == $_GET['startnum']) ? ' checked' : '');
		$ret .= "
			<span class='hideme'>
				<input type='radio' name='moodid' value='{$file}' id='mood{$file}' tabindex='". (9000 + $file) ."' style=\"height: 12px;\" {$jsclick} {$selected}>
				<label for='mood{$file}' style=\"font-size: 12px;\">
					&nbsp;{$file}:&nbsp;{$data['title']}
				</label>
			</span>
			<noscript>&nbsp;{$file}:&nbsp;<a href='?id={$_GET['id']}&amp;startnum={$file}'>{$data['title']}</a></noscript><br>";
	}
	
	$ret .= "</div></td><td class='tdbg2 center' width=400px><img src=\"".avatarpath($_GET['id'], $_GET['startnum'])."\" id=prev></td></tr>";
} else {
	$ret = '';
}


?>
<center>
<table height=100% valign=middle>
	<tr>
		<td>
		
			<table class='table'>
				<tr style='height: 50px'>
					<td class='tdbgh center' colspan=2>
						<b>Preview mood avatar for user...</b>
						<br>
						<form>
							<select name="usel" onChange="parent.location='avatar.php?id='+this.options[this.selectedIndex].value" style="width:500px;">
								<option value=0>&lt;Select a user&gt;</option>
								<?=$options?>
							</select>
							<noscript><input type="submit" name="go" value="Go"></noscript>
						</form>
					</td>
				</tr>
				<?=$ret?>
			</table>
			
		</td>
	<tr>
</table>
</center>
</body>
</html>
<?php
pagefooter(); // DEBUG