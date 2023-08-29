<?php

// Uploader-specific functions
function can_manage_category($cat) {
	global $isadmin, $loguser;
	return $loguser['id'] && $loguser['powerlevel'] >= 0 && ($isadmin // not banned & logged in
		|| ($cat['minpowermanage'] <= $loguser['powerlevel']) // Minpower check
		|| ($cat['user'] && $cat['user'] == $loguser['id']) // folder owner
	);
}

function can_read_category($cat) {
	global $isadmin, $loguser;
	return $isadmin // admin
	|| $loguser['powerlevel'] >= $cat['minpowerread'] // minpower check
	|| ($cat['user'] && $cat['user'] == $loguser['id']); // folder owner
}

function can_upload_in_category($cat) {
	global $isadmin, $loguser;
	return $loguser['id'] && ($isadmin || $cat['user'] == $loguser['id'] || $cat['minpowerupload'] <= $loguser['powerlevel']);
}

function can_read_file($file) {
	global $isadmin, $loguser;
	return ($isadmin || !$file['private'] || $loguser['id'] == $file['user']);
}

function can_edit_file($file) {
	global $isadmin, $loguser;
	return $loguser['id'] && ($isadmin || ($loguser['id'] == $file['user'] && $loguser['powerlevel'] >= 0));
}

function load_uploader_category($id, $fields = "c.*") {
	global $sql, $isadmin, $loguser, $cat;
	// Check the category to be sure. Private categories are a thing, you know.
	// Also reading the username off here to avoid having to query it later
	$cat = $sql->fetchq("
		SELECT {$fields}, u.name username
		FROM uploader_cat c
		LEFT JOIN users u ON c.user = u.id
		WHERE c.id = {$id}");
	if (!$cat) {
		if (!$isadmin) {
			errorpage("You aren't allowed to access this folder.");
		} else if (!($left = $sql->resultq("SELECT COUNT(*) FROM uploader_files WHERE cat = {$id}"))) {
			errorpage("The folder specified doesn't exist.");
		} else {
			$cat = [
				'id' => $id,
				'title' => "Invalid folder #{$id}",
				'user' => $loguser['id'],
				'username' => $loguser['name'],
			];
			return;
		}
	}
	if (!can_read_category($cat)) {
		errorpage("You aren't allowed to access this folder.");
	}
}
function load_uploader_file($hash, $fields = "*") {
	global $sql, $isadmin, $file, $cat;
	$file = $sql->fetchp("SELECT {$fields} FROM uploader_files WHERE hash = ?", [$hash]);
	
	if ($isadmin && !$file)
		errorpage("This file doesn't exist.");
	
	if (!$file)
		errorpage("You aren't allowed to access this file.");

	load_uploader_category($file['cat']);
	
	if (!can_manage_category($cat) && !can_read_file($file))
		errorpage("You aren't allowed to access this file.");
}

function get_category_perms($cat) {
	return [
		'allow-private-files' => $cat['user']
	];
}
function validate_file_options($cat, $file) {
	$perms = get_category_perms($cat);
	if (!$perms['allow-private-files'] && $file['private'])
		errorpage("You aren't meant to upload private files in public folder, you know.");	
}

function uploader_barright($cat = null, $user = null) {
	global $loguser, $isadmin;
	$barright = "<a href='".actionlink("uploader-catbyuser.php")."'>Folders by user</a>";
	// Lock out management options when not logged in
	if ($loguser['id'] && !$loguser['uploader_locked']) {
		if ($isadmin)
			$barright .= " - <a href='".actionlink("uploader-catman.php")."'>Manage shared folders</a>";
		
		// $obj can be either $user (uploader.php) or $cat (uploader-cat.php)
		// detect which one it is by checking for $cat['username']
		if ($cat || $user) {
			
			if ($cat) {
				$userid   = $cat['user'];
				$username = $cat['username'];
			} else {
				$userid   = $user['id'];
				$username = $user['name'];
			}
			
			if (($isadmin && $userid) || $userid == $loguser['id']) {
				$barright .= " - <a href='".actionlink("uploader-catman.php?mode=u&user={$userid}")."'>Manage ".htmlspecialchars($username)."'s folders</a>";
			}
		}	
	}
	return $barright;
}

function uploader_breadcrumbs_links($cat = null, $user = null, $extra = null) {
		
	// Base link
	$links = array(
		["Uploader", actionlink("uploader.php")]
	);
		
	if (!$cat) { // Pages before selecting a folder
		global $scriptpath, $extName;
		
		// Link to user folder group list
		if ($user || $scriptpath == "{$extName}/uploader-catbyuser.php")
			$links[] = ["Personal folders", actionlink("uploader-catbyuser.php")];
		
		if ($user)
			$links[] = [$user['name'], null];
	} else {
		// We don't need $user at all here
		
		// User folders add two parts straight away
		if ($cat['user']) {
			$links[] = ["Personal folders", actionlink("uploader-catbyuser.php")];
			$links[] = [$cat['username'], actionlink("uploader.php?mode=u&user={$cat['user']}")];
		}
		
		// Category name
		$links[] = [$cat['title'], actionlink("uploader-cat.php?cat={$cat['id']}")];
	}
	
	// Merge any custom parts
	if ($extra !== null)
		$links = array_merge($links, $extra);
	
	// Last section is always unselectable, null out its link
	$links[count($links)-1][1] = null;
	
	return $links;
}

const UCS_SHARED     = 0b1;
const UCS_PERSONAL   = 0b10;
const UCS_OTHERS     = 0b100;
const UCS_DEFAULT    = UCS_SHARED | UCS_PERSONAL;
const UCS_READPERM   = 0b1000;
const UCS_UPLOADPERM = 0b10000;
const UCS_MANAGEPERM = 0b100000;
const UCS_NOSELECTED = 0b1000000;
function uploader_cat_select($name, $sel = -1, $flags = UCS_DEFAULT, $none = "") {
	$cats = uploader_filter_cat($sel, $flags);
	
	// Build the select list, grouped by user order (with shared categories being the first)
	$out = "<select name='{$name}'>";
	if ($none)
		$out .= "<option value='-1'>".htmlspecialchars($none)."</option>";
	$last = -1;
	//while ($x = $sql->fetch($cats)) {
	foreach ($cats as $id => $x) {
		if ($last != $x['user']) {
			$optlabel = ($x['user'] ? htmlspecialchars($x['username'], ENT_QUOTES) : "*** Shared folders ***");
			$out .= "</optgroup><optgroup label=\"{$optlabel}\">";
			$last = $x['user'];
		}
		$out .= "<option value='{$id}'".($sel == $id ? " selected" : "")." data-allowprivate=\"{$x['user']}\">".htmlspecialchars($x['title'])."</option>\n";
	}
	$out .= "</optgroup></select>";
	return $out;
}

const UUS_JS = 0b10;
function uploader_user_select($name, $sel = 0, $flags = UUS_JS) {
	global $loguser, $sql;
	
	$users = $sql->query("
		SELECT u.id, u.name 
		FROM users u
		INNER JOIN uploader_cat c ON u.id = c.user
		GROUP BY u.id
		ORDER BY u.name ASC
	");
	$opts = "";
	while ($x = $sql->fetch($users)) {
		$opts .= "<option value='{$x['id']}' ".($sel == $x['id'] ? "selected" : "").">".htmlspecialchars($x['name'])."</option>\r\n";
	}
	
	// Javascript autoredirect
	$js = $prejs = $postjs = "";
	if ($flags & UUS_JS) {
		$js = "onChange=\"parent.location='".actionlink(null, "?mode=u&user=")."'+this.options[this.selectedIndex].value\"";
		$prejs  = "<form method='GET' action='?' style='display: inline'>";
		$postjs = "<noscript> <input type='hidden' name='mode' value='u'><input type='submit' value='Go'></noscript></form>";
	}
	
	return "{$prejs}
	Select user: <select name='{$name}'{$js}>
		<option value='0'>*** All personal folders ***</option>
		{$opts}
	</select>{$postjs}";
}

function uploader_filter_cat($sel = -1, $flags = UCS_DEFAULT) {
	global $sql, $loguser, $isadmin;
	
	// Which users to search categories from
	$search = "";
	if (!($flags & (UCS_SHARED | UCS_PERSONAL | UCS_OTHERS))) {
		if ($flags & UCS_SHARED)
			$search .= "c.user = 0";
		if ($flags & UCS_PERSONAL)
			$search .= ($search ? " OR" : "")." c.user = {$loguser['id']}";
		if ($flags & UCS_OTHERS)
			$search .= ($search ? " OR" : "")." c.user > 0";
	}
	
	// Which permissions are required
	$perm = "";
	if (!$isadmin) {
		if ($flags & UCS_READPERM)
			$perm .= "(!c.minpowerread || {$loguser['powerlevel']} >= c.minpowerread)";
		if ($flags & UCS_UPLOADPERM)
			$perm .= ($perm ? " AND" : "")." {$loguser['powerlevel']} >= c.minpowerupload";
		if ($flags & UCS_MANAGEPERM)
			$perm .= ($perm ? " AND" : "")." {$loguser['powerlevel']} >= c.minpowermanage";
	}
	if ($flags & UCS_NOSELECTED) // ie: when deleting a category, don't show the current one in the move list
		$perm .= ($perm ? " AND" : "")." c.id != {$sel}";
		
	// Merge the conditions for the full WHERE
	$condition = "";
	if ($search || $perm) {
		if ($search)
			$condition .= "({$search})";
		if ($perm)
			$condition .= ($condition ? " AND " : "")."({$perm})";
		$condition = "WHERE {$condition}";
	}
	
	return $sql->getarraybykey("
		SELECT c.id, c.title, c.user, u.name username
		FROM uploader_cat c 
		LEFT JOIN users u ON c.user = u.id
		{$condition} 
		ORDER BY c.user, c.ord, c.id
	", 'id', mysql::USE_CACHE | mysql::FETCH_ALL);
}

function upload_file($file, $user, $opt) {
	global $sql, $xconf;
	
	// Check for the default PHP error indicator in $file['error']
	upload_error($file);
	
	//--
	// Just-in-case validation (probably can be removed)
	if (!$file['size']) 
		errorpage("This is a 0kb file");
	if ($file['size'] > $xconf['max-file-size'])
		errorpage("The file you're trying to upload is over the file size limit.");	
	
	//--
	
	// Image detection
	list($is_image, $width, $height) = get_image_size($file['tmp_name']);
	
	// Create the SQL entry
	$sqldata = [
		'cat'          => $opt['cat'],
		'user'         => $user['id'],
		//'lastedituser' => $user['id'],
		'filename'     => $opt['filename'],
		'description'  => $opt['desc'],
		'private'      => $opt['private'],
		
		'mime'         => mime_content_type($file['tmp_name']),
		'size'         => $file['size'],
		'date'         => time(),
		'downloads'    => 0,
		
		'width'        => (int) $width,
		'height'       => (int) $height,
		'is_image'     => (int) $is_image,
	];
	
	$sql->beginTransaction();
	$sql->query("UPDATE uploader_cat SET files = files + 1, lastfiledate = '".time()."', lastfileuser = '{$user['id']}' WHERE id = {$opt['cat']}");
	$sql->queryp("INSERT INTO uploader_files SET ".mysql::setplaceholders($sqldata), $sqldata);
	
	// Now that we know the file id, create the file hash
	$file_id = $sql->insert_id();
	$hash = get_upload_hash($user['posts'], $user['id'], $file_id + 1);
	$sql->queryp("UPDATE uploader_files SET hash = ? WHERE id = '{$file_id}'", [$hash]);
	
	// Move the file to the new location
	$path = uploads_name($file_id);
	$res = move_uploaded_file($file['tmp_name'], $path);
	
	// Generate a thumbnail, if necessary
	if ($is_image) {
		save_thumbnail($path, uploads_name($file_id, true), 150, 150);
	}
	
	$sql->commit();
	return true;
}

function reupload_file($file, $orig_file, $user, $opt) {
	global $sql, $xconf;
	
	$sqldata = [];
	//--
	// Validation
	$reupload = !upload_error($file, true);
	if ($reupload) {
		// An actual file is being reuploaded
		if (!$file['size']) 
			errorpage("This is a 0kb file");
		if ($file['size'] > $xconf['max-file-size'])
			errorpage("The file you're trying to upload is over the file size limit.");	
		
		// Image detection
		list($is_image, $width, $height) = get_image_size($file['tmp_name']);
		
		$sqldata = [	
			'mime'         => mime_content_type($file['tmp_name']),
			'size'         => $file['size'],
			
			'width'        => (int) $width,
			'height'       => (int) $height,
			'is_image'     => (int) $is_image,
		];

		$path = uploads_name($orig_file['id']);
		$res = move_uploaded_file($file['tmp_name'], $path);
		
		// Generate a thumbnail, if necessary
		if ($is_image) {
			save_thumbnail($path, uploads_name($orig_file['id'], true), 150, 150);
		} else if ($orig_file['is_image']) { // Delete the current thumbnail if the file doesn't have it anymore
			unlink(uploads_name($orig_file['id'], true));
		}
	}
	
	// Special options by default replaced when reuploading the file (unless the override box is checked)
	// Obviously these are always overridden if a file isn't being reuploaded
	if (!$reupload || $opt['override']) {
		if (isset($opt['mime']))
			$sqldata['mime'] = $opt['mime'];
		if (isset($opt['is_image']))
			$sqldata['is_image'] = $opt['is_image'];
	}	

	// "this was edited" metadata
	$sqldata['lastedituser'] = $user['id'];
	$sqldata['lasteditdate'] = time();
	// Edited fields
	$sqldata['description']  = $opt['desc'];
	$sqldata['private']      = $opt['private'];
	$sqldata['cat']          = (int) $opt['cat'];
	$sqldata['filename']     = $opt['filename'];
	
	
	$sql->beginTransaction();
	$sql->queryp("UPDATE uploader_files SET ".mysql::setplaceholders($sqldata)." WHERE id = {$orig_file['id']}", $sqldata);
	if ($orig_file['cat'] != $sqldata['cat']) {
		//--
		// fix counts
		$oinfo = $sql->fetchq("SELECT id, date, user FROM uploader_files WHERE cat = {$orig_file['cat']} ORDER BY id DESC LIMIT 1");
		$dinfo = $sql->fetchq("SELECT id, date, user FROM uploader_files WHERE cat = {$sqldata['cat']} ORDER BY id DESC LIMIT 1");
		if (!$oinfo)
			$oinfo = ['id' => null, 'date' => 0, 'user' => 0];
		if (!$dinfo)
			$dinfo = ['id' => null, 'date' => 0, 'user' => 0];
		$sql->query("UPDATE uploader_cat SET files = files - 1, downloads = downloads - '{$orig_file['downloads']}', lastfiledate = '{$oinfo['date']}', lastfileuser = '{$oinfo['user']}' WHERE id = '{$orig_file['cat']}'");
		$sql->query("UPDATE uploader_cat SET files = files + 1, downloads = downloads + '{$orig_file['downloads']}', lastfiledate = '{$dinfo['date']}', lastfileuser = '{$dinfo['user']}' WHERE id = '{$sqldata['cat']}'");
		
		//--
	}
	$sql->commit();
	
	return true;
}

function delete_upload($file) {
	global $sql;
	$sql->beginTransaction();
	$sql->query("UPDATE uploader_cat SET files = files - 1, downloads = downloads - {$file['downloads']} WHERE id = {$file['cat']}");
	$sql->query("DELETE FROM uploader_files WHERE id = {$file['id']}");
	$sql->commit();
	
	if (uploads_encode_hash($file['hash']) != $file['hash']) {
		trigger_error("Attempted deletion of file with invalid hash \"{$file['hash']}\"", E_USER_WARNING);
		return;
	}
	
	$filepath = uploads_name($file['id']);
	$thumbpath = uploads_name($file['id'], true);
	unlink($filepath);
	if (file_exists($thumbpath))
		unlink($thumbpath);
}

// This is meant as a one way hash
// We can mt_rand the shit out of it
function get_upload_hash($posts, $user_id, $prefix) {
	$hash = password_hash(mt_rand(0, PHP_INT_SIZE)."-{$posts}-{$user_id}", PASSWORD_BCRYPT);
	return "{$prefix}_".uploads_encode_hash($hash);
}
function uploads_encode_hash($hash) {
	return strtr($hash, array(
		'/'  => 'b',
		'\\' => 'f',
		'.'  => '-',
	));
}

function uploads_name($file, $thumb = false) {
	global $extName;
	return "extensions/{$extName}.abx/uploads/".($thumb ? "t/{$file}.png" : "f/{$file}");
}