<?php

// NoJS user switch
if (isset($_GET['go'])) {
	if (!isset($_GET['usel'])) $_GET['usel'] = 0;
	return header("Location: avatar.php?id={$_GET['usel']}");
}

require "lib/common.php";

pageheader("Mood Avatar Preview", true, true); // Small header

$_GET['id'] = filter_int($_GET['id']);

// Admins get to see all of the avatars 
// not like it matters when you can just access the userpic folder directly, but :V
$flags = AVATARS_ALL | ($isadmin ? 0 : AVATARS_NOHIDDEN);
$moods = get_avatars($_GET['id'], $flags);

if ($config['allow-avatar-storage']) {
	// The user list
	$users = $sql->query("
		SELECT u.id, u.name, COUNT(*) avcount
		FROM users u
		INNER JOIN users_avatars a ON u.id = a.user
		".($isadmin ? "" : "WHERE a.hidden = 0")."
		GROUP BY u.id
	");
} else {
	// Build the select box options for the user selection
	$users = $sql->query("
		SELECT id, name, moodurl 
		FROM users 
		WHERE moodurl != '' 
		ORDER BY id ASC
	");
}


$me      = false;
$options = '';

while ($u = $sql->fetch($users)) {
	// Selected user found
	if ($u['id'] == $_GET['id']) {
		$me = $u;
		$selected = " selected";
	} else {
		$selected = "";
	}
	//if (!$config['allow-avatar-storage'] && strpos($u['moodurl'], '$') === FALSE)
	//	$fails = " (improper URL)";
	$options .= "\r\n  <option value='{$u['id']}'{$selected}>{$u['id']}: ".htmlspecialchars($u['name'])."</option>";
}

// The user was selected
if ($me && $moods) {
	
	$_GET['start'] = filter_int($_GET['start']);
	
	register_js("js/avatars.js");
	
	if ($config['allow-avatar-storage']) {
		$header_text  = count($moods)." avatars found";
	} else {
		if ($_GET['start'] < 1) {
			$_GET['start'] = 1;
		}
		$header_text = htmlspecialchars($moodurl);
	}
	
	// Mood avatar selection
	$txt     = "";
	$confirm = -1;
	foreach ($moods as $num => $x) {
		
		$url = $config['allow-avatar-storage'] ? avatar_path($me['id'], $num, $moods[$num]['weblink']) : str_replace('$', $num, $moodurl);
		$jsclick = "onclick='set_av(\"".escape_attribute($url)."\")'";
		if ($num == $_GET['start']) {
			$selected = ' checked';
			$confirm = $_GET['start']; // So no hidden or nonexisting avatars can be viewed
		} else {
			$selected = "";
		}
		
		$selected = ($num == $_GET['start']) ? ' checked' : '';
		$txt .= "
		<span class='js'>
			<input type='radio' name='moodid' value='{$num}' id='mood{$num}' tabindex='". (9000 + $num) ."' style='height: 12px' {$jsclick} {$selected}>
            <label for='mood{$num}' style='font-size: 12px'>
				&nbsp;{$num}:&nbsp;".htmlspecialchars($x['title'])."
			</label>
		</span>
		<noscript>&nbsp;{$num}:&nbsp;<a href='?id={$_GET['id']}&start={$num}#{$num}' id='{$num}'>".htmlspecialchars($x['title'])."</a></noscript><br>";
	}
	
	// Alternative header text
	if ($config['allow-avatar-storage']) {
		$startimg = $confirm != -1 ? avatar_path($me['id'], $confirm, $moods[$confirm]['weblink']) : "images/_.gif";
	}
	
	$ret = "<tr>
		<td class='tdbgh center' colspan=2>
			".htmlspecialchars($me['name']).": <i>{$header_text}</i>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 b center'>Mood avatar list:</td>
		<td class='tdbg2 center' style='width: 400px' rowspan='2'>
			<img src=\"".escape_attribute($startimg)."\" id=prev>
		</td>
	</tr>
	<tr>
		<td class='tdbg1'><div style='height: 400px; overflow-y: scroll'>{$txt}</div></td>
	</tr>
	";

} else {
	$ret = '';
}


?>
<script type="text/javascript">
	function change_user(id) {
		parent.location = 'avatar.php?id='+id;
	}
	function set_av(path) {
		document.getElementById('prev').src = path;
	}
</script>
<center>
<table class="tablevc">
	<tr>
		<td>
			<table class='table'>
				<tr style='height: 50px'>
					<td class='tdbgh center' colspan=2>
						<b>Preview mood avatar for user...</b>
						<br>
						<form>
							<select name="usel" onchange="change_user(this.options[this.selectedIndex].value)" style="width:500px;">
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