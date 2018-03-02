<?php

function imageupload($file, $maxsize, $x, $y, $dest = false, $qdata = NULL){
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
	unlink(avatarpath($user, $file));
}

const AVATARS_ALL = 0b1;
const AVATARS_NOHIDDEN = 0b10;
function getavatars($user, $flags = 0) {
	global $sql;
	return $sql->fetchq("
		SELECT file, title, hidden, weblink
		FROM users_avatars
		WHERE user = {$user}
		".($flags & AVATARS_ALL ? "" : " AND file != 0")."
		".($flags & AVATARS_NOHIDDEN ? " AND hidden = 0" : "")."
		ORDER by file ASC
	", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
}

function prepare_avatars($sets) {
	global $sql;
	$res = array();
	while ($x = $sql->fetch($sets)) {
		$res[$x['user']][$x['moodid']] = $x['weblink'];
	}
	return $res;
}

function avatarpath($user, $file_id, $weblink = NULL) {return $weblink ? htmlspecialchars($weblink) : "userpic/{$user}/{$file_id}";}
function dummy_avatar($title, $hidden, $weblink = "") {return ['title' => $title, 'hidden' => $hidden, 'weblink' => $weblink];}
function setmoodavjs($moodurl) { return "<script type='text/javascript'>setmoodav(\"{$moodurl}\")</script>"; }

// 0 -> side
// 1 -> inline
// Layout selecttor
function moodlayout($mode, $user, $sel = 0) {
	global $config;
	
	if (!$mode) {
		return "
		<table style='border-spacing: 0px'>
			<tr>
				<td class='nobr' style='max-width: 150px'>
					".($config['allow-avatar-storage'] ? "" : moodlist($user, $sel))."
				</td>
				<td>
					<img src='images/_.gif' id='prev'>
				</td>
			</tr>
		</table>";
	} else if ($config['allow-avatar-storage']) {
		return moodlist($user, $sel); // Select box on self-storage only
	}
	return "";
}

function moodlist($user, $sel = 0, $return = false) {
	global $config, $loguser;
	
	if ($config['allow-avatar-storage']) {
		// Self stored avatar mode
		$moods = getavatars($user, AVATARS_ALL);
		
		if ($return) {
			return array_column($moods, 'title');
		}
		
		$c[$sel] = "selected";
		$txt     = "";
		
		// If no default avatar was defined, make sure the default option blanks the avatar
		if (isset($moods[0])) {
			$moods[0]['title'] = "-Normal avatar-";
		} else {
			$txt .= "<option value='0' onclick='newavatarpreview(0,0,true)'>-Normal avatar-</option>";
			if (!$sel) $sel = "0, true"; // well it works
		}
		
		// Select box, with now auto av preview update
		foreach ($moods as $file => $data) {
			$txt .= 
			"<option value='{$file}' ".filter_string($c[$file])." onclick='newavatarpreview({$loguser['id']},{$file},\"".htmlspecialchars($data['weblink'])."\")'>".
				htmlspecialchars($data['title']).
			"</option>\n";
		}
		
		$ret = "
		Avatar: <select name='moodid'>
			{$txt}
		</select><script>newavatarpreview({$loguser['id']},{$sel},\"".htmlspecialchars($moods[$sel]['weblink'])."\")</script>";
	} else {
		
		// Numeric "good luck with hosting" avatar mode
		$moods = array("None", "neutral", "angry", "tired/upset", "playful", "doom", "delight", "guru", "hope", "puzzled", "whatever", "hyperactive", "sadness", "bleh", "embarrassed", "amused", "afraid");
		if ($loguser['id'] == 1) {
			$moods[99] = "special";
		}
		if ($return) {
			return $moods;
		}
		
		$moodurl    = htmlspecialchars($loguser['moodurl']);
		$c[$sel]	= " checked";
		$txt		= "";
		
		// Choices display
		$txt = "";
		foreach($moods as $num => $name) {
			$jsclick = (($loguser['id'] && $loguser['moodurl']) ? "onclick='avatarpreview({$loguser['id']},$num)'" : "");
			$txt .= 
				"<input type='radio' name='moodid' value='{$num}'". filter_string($c[$num]) ." id='mood{$num}' tabindex='". (9000 + $num) ."' style='height: 12px' {$jsclick}>
				 <label for='mood{$num}' ". filter_string($c[$num]) ." style='font-size: 12px'>&nbsp;{$num}:&nbsp;{$name}</label><br>\r\n";
		}
		// Set the default image to start with
		if (!$sel || !$loguser['id'] || !$loguser['moodurl'])
			$startimg = 'images/_.gif';
		else
			$startimg = str_replace('$', $sel, $moodurl);
	
		$ret = "<div class='font b'>Mood avatar list:</div>".$txt.setmoodavjs($loguser['moodurl']);
	}
	
	return include_js('avatars.js').$ret;
}

// hopefully this will result in some consistency when asking just the minipic
// now using max-???? properties so it won't stretch when it's less than the minimum
function get_minipic($user, $url = "") {
	global $config;
	if ($config['allow-avatar-storage']) {
		if (has_minipic($user)) {
			return "<img style='max-width: {$config['max-minipic-size-x']}px;max-height: {$config['max-minipic-size-y']}px' src='".avatarpath($user, 'm')."' align='absmiddle'>";
		}
	} else if ($url) {
		return "<img style='max-width: {$config['max-minipic-size-x']}px;max-height: {$config['max-minipic-size-y']}px' src=\"".htmlspecialchars($url)."\" align='absmiddle'>";
	}
	
	return "";
}

function has_minipic($user) { return is_file(avatarpath($user, 'm')); }
function del_minipic($user) {
	if (has_minipic($user)) {
		return unlink(avatarpath($user, 'm'));
	} else {
		return false;
	}
}