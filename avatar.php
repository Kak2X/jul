<?php

// NoJS user switch
if (isset($_GET['go'])) {
	if (!isset($_GET['usel'])) $_GET['usel'] = 0;
	return header("Location: avatar.php?id={$_GET['usel']}");
}

require 'lib/function.php';

pageheader("Mood Avatar Preview", NULL, NULL, true); // Small header


if ($config['allow-avatar-storage']) {
	
	// Determine if we're viewing an avatar set
	if ($_GET['id']) {
		// Admins get to see all of the avatars 
		// not like it matters when you can just access the userpic folder directly, but :V
		$flags = AVATARS_ALL | ($isadmin ? 0 : AVATARS_NOHIDDEN);
		$moods = getavatars($_GET['id'], $flags);
	} else {
		$moods = false;
	}
	
	// The user list
	$users = $sql->query("
		SELECT u.id, u.name, COUNT(*) avcount
		FROM users u
		INNER JOIN users_avatars a ON u.id = a.user
		".($isadmin ? "" : "WHERE a.hidden = 0")."
		GROUP BY u.id
	");

} else {
	$_GET['id'] = filter_int($_GET['id']);
	$moods	= array(1 => "neutral", "angry", "tired/upset", "playful", "doom", "delight", "guru", "hope", "puzzled", "whatever", "hyperactive", "sadness", "bleh", "embarrassed", "amused", "afraid");
	
	// compat. hack
	for ($i = 1; $i < 17; ++$i) {
		$moods[$i] = array('title' => $moods[$i], 'weblink' => '');
	}
	
	// Build the select box options for the user selection
	$users = $sql->query("
		SELECT id, name, moodurl 
		FROM users 
		WHERE moodurl != '' 
		ORDER BY id ASC
	");
}


$me = false;
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
	$options .= "\r\n  <option value='{$u['id']}'{$selected}>{$u['id']}: {$u['name']}</option>";
}

// The user was selected
if ($me && $moods) {
	
	$_GET['start'] = filter_int($_GET['start']);
	
	// Output the javascript right away
	print include_js('avatars.js').
	'<noscript><style type="text/css">.hideme{display: none}</style></noscript>';
	
	
	if ($config['allow-avatar-storage']) {
		$header_text  = count($moods)." avatars found";
		$avtype       = "new"; // use newavatarpreview()
	} else {
		if ($_GET['start'] < 1) {
			$_GET['start'] = 1;
		}
		// >_>
		if ($loguser['id'] == 1 && $me['id'] == 1) {
			$moods[99] = array('title' => "special", 'weblink' => '');
		}
		
		$moodurl  = htmlspecialchars($me['moodurl']);
		$startimg = str_replace('$', $_GET['start'], $moodurl);
		print setmoodavjs($moodurl);
		
		$header_text = $moodurl;
		$avtype = ""; // use avatarpreview()
	}
	

	
	// Mood avatar selection
	$txt     = "";
	$confirm = -1;
	foreach ($moods as $num => $x) {
		
		
		$jsclick = "onclick='{$avtype}avatarpreview({$me['id']},{$num},\"".escape_attribute($x['weblink'])."\")'";
		if ($num == $_GET['start']) {
			$selected = ' checked';
			$confirm = $_GET['start']; // So no hidden or nonexisting avatars can be viewed
		} else {
			$selected = "";
		}
		
		$selected = ($num == $_GET['start']) ? ' checked' : '';
		$txt .= "
		<span class='hideme'>
			<input type='radio' name='moodid' value='{$num}' id='mood{$num}' tabindex='". (9000 + $num) ."' style='height: 12px' {$jsclick} {$selected}>
            <label for='mood{$num}' style='font-size: 12px'>
				&nbsp;{$num}:&nbsp;{$x['title']}
			</label>
		</span>
		<noscript>&nbsp;{$num}:&nbsp;<a href='?id={$_GET['id']}&start={$num}'>{$x['title']}</a></noscript><br>";
	}
	
	// Alternative header text
	if ($config['allow-avatar-storage']) {
		$startimg = $confirm != -1 ? avatarpath($me['id'], $confirm, $moods[$confirm]['weblink']) : "images/_.gif";
	}
	
	$ret = "<tr>
		<td class='tdbgh center' colspan=2>
			{$me['name']}: <i>{$header_text}</i>
		</td>
	</tr>
	<tr style='height: 400px'>
		<td class='tdbg1' style='width: 200px'>
			<b>Mood avatar list:</b><br>
			{$txt}
		</td>
		<td class='tdbg2 center' width=400px>
			<img src=\"$startimg\" id=prev>
		</td>
	</tr>";

} else {
	$ret = '';
}


?>
<script type="text/javascript">
	function change_user(id) {
		parent.location = 'avatar.php?id='+id;
	}
</script>
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