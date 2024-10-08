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
	
	list($is_image, $width, $height) = get_image_size($file['tmp_name']);
	
	if (!$is_image)
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
		$sql->query("UPDATE pm_posts SET moodid = 0 WHERE user = {$user} AND moodid = {$file}");
		$sql->query("UPDATE posts    SET moodid = 0 WHERE user = {$user} AND moodid = {$file}");
	}
	$path = avatar_path($user, $file);
	if (file_exists($path)) {
		unlink($path);
	}
}

const DAA_MINIPIC     = 0b1;
const DAA_SKIPSETMOOD = 0b10;
function delete_all_avatars($user, $flags = DAA_MINIPIC) {
	global $sql;
	$sql->query("DELETE from users_avatars WHERE user = {$user}");
	if ($flags & DAA_SKIPSETMOOD) {
		$sql->query("UPDATE pm_posts SET moodid = 0 WHERE user = {$user}");
		$sql->query("UPDATE posts    SET moodid = 0 WHERE user = {$user}");
	}
	
	$files    = glob(avatar_path($user, "*"), GLOB_NOSORT);
	foreach ($files as $pic) {
		unlink($pic);
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
		$moods = array("(default)", "neutral", "angry", "tired/upset", "playful", "doom", "delight", "guru", "hope", "puzzled", "whatever", "hyperactive", "sadness", "bleh", "embarrassed", "amused", "afraid");
		if (!($flags & AVATARS_ALL)) {
			unset($moods[0]);
			$i = 1;
		} else {
			$i = 0;
		}
		if ($user == 1) {
			$moods[99] = "special";
		}
		// dual compatibility
		$out = array();
		for (; $i < 17; ++$i) {
			$out[$i] = ['title' => $moods[$i], 'hidden' => 0, 'weblink' => ''];
		}
		return $out;
	}
}

// for threadpost & derivates
function prepare_avatar($post, &$picture, &$userpic) {
	global $config;
	if ($config['allow-avatar-storage']) {
		if ($post['piclink']) {
			$picture = escape_attribute($post['piclink']);
			$userpic = "<img class='avatar' src=\"{$picture}\">"; 
		} else if (file_exists(avatar_path($post['uid'], $post['moodid']))) {
			$picture = avatar_path($post['uid'], $post['moodid']);
			$userpic = "<img class='avatar' src=\"{$picture}\">"; 
		} else {
			$picture = $userpic = "";
		}
	} else {
		// $picture doesn't seem to be used...
		if ($post['moodid'] && $post['moodurl']) { // mood avatar
			$picture = str_replace('$', $post['moodid'], escape_attribute($post['moodurl']));
			$userpic = "<img class='avatar' src=\"{$picture}\">";
		} else if (isset($post['picture']) && $post['picture']) { // default avatar
			$picture = escape_attribute($post['picture']);
			$userpic = "<img class='avatar' src=\"{$picture}\">";
		} else { // null
			$picture = $userpic = "";
		}
	}
}

function get_weblink($user, $mood) {
	global $sql, $config;
	return $config['allow-avatar-storage'] ? $sql->resultq("SELECT weblink FROM users_avatars WHERE user = {$user} AND file = {$mood}") : "";
}

function avatar_path($user, $file_id, $weblink = NULL) {return $weblink ? escape_attribute($weblink) : "userpic/{$user}_{$file_id}";}
function dummy_avatar($title, $hidden, $weblink = "") {return ['title' => $title, 'hidden' => $hidden, 'weblink' => $weblink];}
function set_mood_url_js($moodurl) { return "<script type='text/javascript'>setmoodav(\"".escape_attribute($moodurl)."\")</script>"; }

function mood_preview() {
	return "<img src='images/_.gif' class='avatar-preview' id='prev'>";
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
	
	static $registered = false;
	if (!$registered) {
		$registered = true;
		register_js("js/avatars.js");
	}
	
	$c[$sel]	= " selected";
	$txt		= "";
	
	if ($config['allow-avatar-storage']) { // Self stored avatar mode
		// If no default avatar was defined, make sure the default option blanks the avatar (data-act='clear')
		if (isset($moods[0])) {
			$moods[0]['title'] = "-Normal avatar-";
		} else {
			$txt .= "<option value='0' ". filter_string($c[0])." data-act='clear'>-Normal avatar-</option>";
		}
		
		// Select box, with now auto av preview update
		foreach ($moods as $file => $data) {
			$img = escape_attribute($data['weblink']);
			$txt .= 
			"<option value='{$file}'". filter_string($c[$file]) ." data-f=\"{$img}\">"
				.htmlspecialchars($data['title']).
			" ({$file})</option>\r\n";
		}
	} else { // Numeric "good luck with hosting" avatar mode
	
		// fetch the mood url if we're using alt credentials (or are posting while logged out)
		if (!$user) {
			$moodurl = $avatar = "";
		} else if ($user == $loguser['id']) {
			$moodurl = $loguser['moodurl'];
			$picture = $loguser['picture'];
		} else {
			list($picture, $moodurl) = $sql->resultq("SELECT picture, moodurl FROM users WHERE id = {$user}");
		}
		$picture    = escape_attribute($picture);
		$moodurl    = escape_attribute($moodurl);
		
		foreach ($moods as $num => $data) {
			$img = $user && $num > 0 ? str_replace('$', $num, $moodurl) : $picture;
			$txt .= "<option value='{$num}'". filter_string($c[$num]) ." data-f=\"{$img}\" data-noas='1'>{$data['title']} ({$num})</option>\r\n";
		}
	}
	
	$ret = "
	Avatar: <select id='moodid' name='moodid' data-u='{$user}'>
		{$txt}
	</select>";
	
	return $ret;
}

function set_avatars_sql($query, $a = 'p', $mfix = false) {
	global $config;
	if (!$config['allow-avatar-storage']) {
		$query = str_replace("{%AVFIELD%}", ", NULL piclink", $query);
		$query = str_replace("{%AVJOIN%}", "", $query);
	} else if (!$mfix) {
		$query = str_replace("{%AVFIELD%}", ",v.weblink piclink", $query);
		$query = str_replace("{%AVJOIN%}", "LEFT JOIN users_avatars v ON {$a}.moodid = v.file AND v.user = {$a}.user", $query);
	} else {
		$query = str_replace("{%AVFIELD%}", ",v.weblink piclink", $query);
		$query = str_replace("{%AVJOIN%}", "LEFT JOIN users_avatars v ON 0 = v.file AND v.user = {$a}.user", $query);
	}
	return $query;
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