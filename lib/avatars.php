<?php

function upload_avatar($file, $maxsize, $x, $y, $dest = false, $qdata = NULL){
	global $config;
	if (!$config['allow-avatar-storage']) return false;
	
	if (!$file['tmp_name'])
		errorpage("No file selected.");

	if (!$file['size']) 
		errorpage("This is an 0kb file");
	
	if ($file['size'] > $maxsize)
		errorpage("File size limit exceeded.");
	
	list($width, $height) = getimagesize($file['tmp_name']);
	
	if (!$width || !$height)
		errorpage("This isn't a supported image type.");
	
	if ($width > $x || $height > $y)
		errorpage("Maximum image size exceeded (Your image: {$width}x{$height} | Expected: {$x}x{$y}).");
	
	if (!$dest)	{
		return "data:".$file['type'].";base64,".base64_encode(file_get_contents($file['tmp_name']));
	} else {
		// New image? If so, add info to db (also account for editprofile)
		if ($qdata !== NULL) {
			save_avatar($qdata);
		}
		return move_uploaded_file($file['tmp_name'], $dest);
	}
}

function save_avatar($qdata) {
	global $sql;
	$sql->queryp("
		INSERT INTO users_avatars (user, file, title, hidden, weblink) VALUES (?,?,?,?,?)
		ON DUPLICATE KEY UPDATE title = VALUES(title), hidden = VALUES(hidden), weblink = VALUES(weblink)
	", $qdata);	
}

function delete_avatar($user, $file) {
	global $sql;
	$sql->query("DELETE from users_avatars WHERE user = {$user} AND file = {$file}");
	if ($file && $file != 'm') {
		$sql->query("UPDATE pm_posts SET moodid = 0 WHERE user = {$user}");
		$sql->query("UPDATE posts    SET moodid = 0 WHERE user = {$user}");
	}
	$path = avatar_path($user, $file);
	if (file_exists($path)) {
		unlink($path);
	}
}

const AVATARS_ALL = 0b1;
const AVATARS_NOHIDDEN = 0b10;
function get_avatars($user, $flags = 0) {
	global $sql, $config;
	if ($config['allow-avatar-storage']) {
		if (!$user) {
			return array();
		}
		return $sql->fetchq("
			SELECT file, title, hidden, weblink
			FROM users_avatars
			WHERE user = {$user}
			".($flags & AVATARS_ALL ? "" : " AND file != 0")."
			".($flags & AVATARS_NOHIDDEN ? " AND hidden = 0" : "")."
			ORDER by file ASC
		", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
	} else {
		// Source defines
		$moods = array("None", "neutral", "angry", "tired/upset", "playful", "doom", "delight", "guru", "hope", "puzzled", "whatever", "hyperactive", "sadness", "bleh", "embarrassed", "amused", "afraid");
		if ($flags & AVATAR_ALL == 0) {
			unset($moods[0]);
		}
		if ($user == 1) {
			$moods[99] = "special";
		}
		// dual compatibility
		$out = array();
		for ($i = 1; $i < 17; ++$i) {
			$out[$i] = ['title' => $moods[$i], 'hidden' => 0, 'weblink' => ''];
		}
		return $out;
	}
}

function prepare_avatars($sets) {
	global $sql;
	$res = array();
	while ($x = $sql->fetch($sets)) {
		$res[$x['user']][$x['moodid']] = $x['weblink'];
	}
	return $res;
}

function avatar_path($user, $file_id, $weblink = NULL) {return $weblink ? escape_attribute($weblink) : "userpic/{$user}/{$file_id}";}
function dummy_avatar($title, $hidden, $weblink = "") {return ['title' => $title, 'hidden' => $hidden, 'weblink' => $weblink];}
function set_mood_url_js($moodurl) { return "<script type='text/javascript'>setmoodav(\"{$moodurl}\")</script>"; }

// 0 -> side
// 1 -> inline
// Layout selecttor
function mood_layout($mode, $user, $sel = 0) {
	global $config, $loguser;
	
	if (!$mode) {
		return "
		<table style='border-spacing: 0px'>
			<tr>
				<td class='font nobr' style='max-width: 150px'>
					".($config['allow-avatar-storage'] ? "" : mood_list($user, $sel))."
				</td>
				<td>
					<img src='images/_.gif' id='prev'>
				</td>
			</tr>
		</table>";
	} else if ($config['allow-avatar-storage']) {
		return mood_list($user, $sel); // Select box on self-storage only
	}
	return "";
}

function mood_list($user, $sel = 0, $return = false) {
	global $config, $loguser;
	
	$moods = get_avatars($user, AVATARS_ALL);
	if ($return) {
		return array_column($moods, 'title');
	}
	if (!$moods) { // Will always return in stored avatar mode if logged out
		return "";
	}
	
	$c[$sel]	= " checked";
	$txt		= "";
	
	if ($config['allow-avatar-storage']) { // Self stored avatar mode
		// If no default avatar was defined, make sure the default option blanks the avatar (true option)
		if (isset($moods[0])) {
			$moods[0]['title'] = "-Normal avatar-";
			$default = '"'.escape_attribute($moods[$sel]['weblink']).'"';
		} else {
			$txt .= "<option value='0' onclick='newavatarpreview(0,0,true)'>-Normal avatar-</option>";
			// Selecting a non-default avatar should still set the real default
			$default = $sel ? '"'.escape_attribute($moods[$sel]['weblink']).'"' : 'true';
		}
		
		// Select box, with now auto av preview update
		foreach ($moods as $file => $data) {
			$jsclick = " onclick='newavatarpreview({$user},{$file},\"".escape_attribute($data['weblink'])."\")'";
			$txt .= 
			"<option value='{$file}'". filter_string($c[$file]) ."{$jsclick}>".
				htmlspecialchars($data['title']).
			"</option>\r\n";
		}
		
		$ret = "
		Avatar: <select name='moodid'>
			{$txt}
		</select><script>newavatarpreview({$user},{$sel},{$default})</script>";
		
	} else { // Numeric "good luck with hosting" avatar mode
	
		// fetch the mood url if we're using alt credentials (or are posting while logged out)
		if (!$user) {
			$moodurl = "";
		} else if ($user == $loguser['id']) {
			$moodurl = $loguser['moodurl'];
		} else {
			$moodurl = $sql->resultq("SELECT moodurl FROM users WHERE id = {$user}");
		}
		$moodurl    = escape_attribute($moodurl);

		
		// Choices display
		foreach ($moods as $num => $data) {
			$jsclick = ($user && $moodurl) ? "onclick='avatarpreview({$user},{$num})'" : "";
			$txt .= 
				"<input type='radio' name='moodid' value='{$num}'". filter_string($c[$num]) ." id='mood{$num}' tabindex='". (9000 + $num) ."' style='height: 12px' {$jsclick}>
				 <label for='mood{$num}'". filter_string($c[$num]) ." style='font-size: 12px'>&nbsp;{$num}:&nbsp;{$data['title']}</label><br>\r\n";
		}
		// Set the default image to start with
		if (!$sel || !$moodurl) {
			$startimg = 'images/_.gif';
		} else {
			$startimg = str_replace('$', $sel, $moodurl);
		}
		$ret = "<div class='b'>Mood avatar list:</div>".$txt.set_mood_url_js($moodurl);
	}
	
	return include_js('avatars.js').$ret;
}

// hopefully this will result in some consistency when asking just the minipic
// now using max-???? properties so it won't stretch when it's less than the minimum
function get_minipic($user, $url = "") {
	global $config;
	if ($config['allow-avatar-storage']) {
		if (has_minipic($user)) {
			return "<img style='max-width: {$config['max-minipic-size-x']}px;max-height: {$config['max-minipic-size-y']}px' src='".avatar_path($user, 'm')."' align='absmiddle'>";
		}
	} else if ($url) {
		return "<img style='max-width: {$config['max-minipic-size-x']}px;max-height: {$config['max-minipic-size-y']}px' src=\"".escape_attribute($url)."\" align='absmiddle'>";
	}
	
	return "";
}

function has_minipic($user) { return is_file(avatar_path($user, 'm')); }
function del_minipic($user) {
	if (has_minipic($user)) {
		return unlink(avatar_path($user, 'm'));
	} else {
		return false;
	}
}