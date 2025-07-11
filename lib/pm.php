<?php

/*
	default_pm_folder: returns if the PM folder is a default one (which has special properties)
	                   since these folders technically don't exist in the database,
	                   it's a good idea to check if we want to do something which requires missing folder features
	$folder - folder number
	$flags  - what to check
*/
const DEFAULTPM_DEFAULT = 0b1; // Check for default folders (no pm_folders but valid read entries)
const DEFAULTPM_GROUPS  = 0b10; // Check for simulated groups (no pm_folders or read entries)
function default_pm_folder($folder, $flags = DEFAULTPM_GROUPS) {
	$arr = [1 => [PMFOLDER_MAIN, PMFOLDER_TRASH], 2 => [PMFOLDER_ALL, PMFOLDER_TO, PMFOLDER_BY]];
	if ($flags == (DEFAULTPM_DEFAULT | DEFAULTPM_GROUPS)) { 
		return in_array($folder, $arr[1] + $arr[2]);
	}
	return in_array($folder, $arr[$flags]);
}

/*
	pm_folder_select: select box to switch between folders
	$name - name of the select box
	$user - user id
	$sel  - selected folder number
	        exception: if $flags has PMSELECT_MERGE it indicates the folders to hide.
	                   multiple folders can be hidden in this case if an array is passed
	$flags - specify select box features (see below)
*/
const PMSELECT_ALL     = 0b1; // Display simulated folder groups
const PMSELECT_JS      = 0b10; // Redirect when a different folder is selected. Provides Noscript failsafe.
const PMSELECT_MERGE   = 0b100; // Folder deletion mode (when asked to merge to another folder)
const PMSELECT_SHOWCNT = 0b1000; // Show PMs count and NEW indicators
function pm_folder_select($name, $user, $sel = 0, $flags = 0) {
	global $loguser, $sql, $pmfoldernames;
	
	$default = [ // Optgroup assignments
		'Groups'          => [PMFOLDER_ALL, PMFOLDER_BY, PMFOLDER_TO],
		'Default folders' => [PMFOLDER_MAIN, PMFOLDER_TRASH],
	];	
	if (!($flags & PMSELECT_ALL)) { // Hide "simulated" folders
		unset($default['Groups']);
	}

	$groups = $preopt = $nosel = $js = $prejs = $postjs = "";
	if ($flags & PMSELECT_MERGE) {
		$preopt = "<option value='-100' selected>Choose a folder to merge into...</option>";
		if (is_array($sel)) { // hack for multi delete (in order to hide multiple folders)
			$nosel  = "AND folder NOT IN (".implode(',', $sel).")";
			$sel    = -100;
		} else {
			$nosel  = "AND folder != {$sel}";
		}
	}
	if ($flags & PMSELECT_JS) {
		$idparam = ($loguser['id'] != $user) ? "id={$user}&" : "";
		$js = "onChange=\"parent.location='?{$idparam}dir='+this.options[this.selectedIndex].value\"";
		$prejs  = "<form method='GET' action='?{$idparam}' style='display: inline'>";
		$postjs = "<noscript> <input type='submit' value='Go'></noscript></form>";
	}
	if ($flags & PMSELECT_SHOWCNT) {
		// Calculate totals for each folder
		$totals = $sql->getresultsbykey("SELECT folder, COUNT(*) FROM pm_access WHERE user = {$user} GROUP BY folder");
		$totals[PMFOLDER_ALL] = array_sum($totals);
		if (!isset($totals[PMFOLDER_TRASH])) $totals[PMFOLDER_TRASH] = 0;
		if (!isset($totals[PMFOLDER_MAIN]))  $totals[PMFOLDER_MAIN]  = 0;
		$totals[PMFOLDER_BY] = $sql->resultq("SELECT COUNT(*) FROM pm_threads WHERE user = {$user}");
		$totals[PMFOLDER_TO] = $totals[PMFOLDER_ALL] - $totals[PMFOLDER_BY];
		// Unread indicators
		$unread = $sql->getresultsbykey("
			SELECT a.folder, COUNT(*) 
			FROM pm_threads t
			INNER JOIN pm_access       a ON t.id     = a.thread
			LEFT  JOIN pm_foldersread fr ON a.folder = fr.folder AND a.user = fr.user
			LEFT  JOIN pm_threadsread tr ON t.id     = tr.tid    AND tr.uid = {$user}
			WHERE a.user = {$user} 
			  AND (!tr.read OR tr.read IS NULL)			  
			  AND (fr.readdate IS NULL OR t.lastpostdate > fr.readdate)
			GROUP BY a.folder
		");
		$unread[PMFOLDER_ALL] = array_sum($unread);
	}
	$newtxt = $pmcount = "";
	foreach ($default as $optgroup => $data) {
		$groups .= "<optgroup label='{$optgroup}'>";
		foreach ($data as $id) {
			if ($flags & PMSELECT_SHOWCNT) {
				$pmcount = " ({$totals[$id]} PMs)";
				$newtxt  = filter_int($unread[$id]) ? "[{$unread[$id]} NEW] " : "";
			}
			$groups .= "<option value='{$id}' ".($sel == $id ? "selected" : "").">{$newtxt}{$pmfoldernames[$id]}{$pmcount}</option>";
		}
		$groups .= "</optgroup>";
	}
	$folders = $sql->query("SELECT folder, title FROM pm_folders WHERE user = {$user} {$nosel} ORDER BY ord ASC, id ASC");
	$custom = "";
	while ($x = $sql->fetch($folders)) {
		if ($flags & PMSELECT_SHOWCNT) {
			$pmcount = " (".filter_int($totals[$x['folder']])." PMs)";
			$newtxt  = filter_int($unread[$x['folder']]) ? "[{$unread[$x['folder']]} NEW] " : "";
		}
		$custom .= "<option value='{$x['folder']}' ".($sel == $x['folder'] ? "selected" : "").">{$newtxt}".htmlspecialchars($x['title'])."{$pmcount}</option>";
	}
	return "{$prejs}
	<select name='{$name}'{$js}>
		{$preopt}
		{$groups}
		<optgroup label='Custom folders'>{$custom}</optgroup>
	</select>{$postjs}";
}

/*
	valid_pm_folder: check if the given folder ID (or list) all exist
	$dir    - folder(s) to check. int or array
	$user   - user id
	$strict - only allow custom folders
	$qextra - extra query checks
*/
function valid_pm_folder($dir, $user, $strict = false, $qextra = "") {
	global $sql;
	if (default_pm_folder($dir, DEFAULTPM_GROUPS)) {
		return false; // Groups are disallowed
	} else if (default_pm_folder($dir, DEFAULTPM_DEFAULT)) { // If we have a default folder
		return (!$strict); // We're good if not in strict mode
	} else if (is_array($dir)) { // If a single ID is given, convert it to an array
		$idcheck = " IN (".implode(',', $dir).")";
		$match   = count($dir);
	} else {
		$idcheck = " = {$dir}";
		$match   = 1;
	}
	$valid = $sql->resultq("SELECT COUNT(*) FROM pm_folders WHERE user = {$user} AND folder{$idcheck} {$qextra}");
	return ($valid == $match);	
}

/*
	delete_pm_folder: delete the given folder ID(s)
	$dir    - folder(s) to delete. int or array
	$dest   - destination folder ID
	$user   - user id
*/
function delete_pm_folder($dir, $dest, $user) {
	global $sql;
	if (!is_array($dir)) { // If a single ID is given, convert it to an array
		$dir = [(int) $dir];
	}
	$movepm  = $sql->prepare("UPDATE `pm_access` SET `folder` = '{$dest}' WHERE `folder` = ? AND user = {$user}");
	$deldir  = $sql->prepare("DELETE FROM `pm_folders`     WHERE `folder` = ? AND user = {$user}");
	$delread = $sql->prepare("DELETE FROM `pm_foldersread` WHERE `folder` = ? AND user = {$user}");
	foreach ($dir as $del) {
		$sql->execute($movepm, [$del]);
		$sql->execute($deldir, [$del]);
		$sql->execute($delread, [$del]);
	}	
}	

/*
	create_pm_folder: create a single PM folder
	$title  - folder title
	$user   - user id
	$ord    - priority
*/
function create_pm_folder($title, $user, $ord = 0) {
	global $sql;
	$values = array(
		'title'     => $title,
		'ord'       => $ord,
		'folder'    => ((int) $sql->resultq("SELECT MAX(folder) FROM pm_folders WHERE user = {$user}")) + 1,
		'user'      => $user,
	);
	return $sql->queryp("INSERT INTO `pm_folders` SET ".mysql::setplaceholders($values), $values);
}

/*
	edit_pm_folder: edit a specified PM folder
	$folder - folder number (not ID)
	$title  - folder title
	$user   - user id
	$ord    - priority
*/
function edit_pm_folder($folder, $title, $user, $ord = 0) {
	global $sql;
	$values = array(
		'title'     => $title,
		'ord'       => $ord,
	);
	$sql->queryp("UPDATE `pm_folders` SET ".mysql::setplaceholders($values)." WHERE `folder` = '{$folder}' AND `user` = '{$user}'", $values);
}

/*
	get_pm_folder: fetch data for (custom) folders
	$user   - user id
	$folder - folder number. if not given, all folders are fetched
*/
function get_pm_folder($user, $folder = NULL) {
	global $sql, $pmfoldernames;
	if ($folder === NULL) {
		return $sql->fetchq("SELECT folder x, folder, title, ord FROM pm_folders WHERE user = {$user} ORDER BY ord ASC, id ASC", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
	} else if (default_pm_folder($folder, DEFAULTPM_DEFAULT | DEFAULTPM_GROUPS)) {
		return [$folder, $pmfoldernames[$folder], 0];  // Don't bother fetching if we're getting info for a default folder
	} else {
		return $sql->fetchq("SELECT folder, title, ord FROM pm_folders WHERE user = {$user} AND folder = {$folder}");
	}
}

/*
	get_pm_count: fetch PM count for folders
	$user   - user id
	$folder - folder number
*/
function get_pm_count($user, $folder = NULL) {
	global $sql;
	if ($folder !== NULL) {
		return (int) $sql->resultq("SELECT COUNT(*) FROM pm_access WHERE user = {$user} AND folder = {$folder}");
	} else {
		return $sql->getresultsbykey("SELECT folder, COUNT(*) FROM pm_access WHERE user = {$user} GROUP BY folder");
	}
}

function get_pm_thread_from_post($pid) {
	global $sql;
	// Linking to a post ID
	$id		= $sql->resultq("SELECT `thread` FROM `pm_posts` WHERE `id` = '{$pid}'");
	if (!$id) {
		errorpage("Couldn't find a post with ID #{$pid}. Perhaps it's been deleted?", "index.php", 'the index page');
	}
	return $id;
}
	
/*
	load_pm_thread: fetch PM data and handle errors automatically
	$id -  PM thread id
*/
function load_pm_thread($id) {
	global $sql, $loguser, $isadmin, $thread, $access, $forum_error;
	$error        = 0;
	$forum_error = "";
	
	$thread = $sql->fetchq("
		SELECT t.*, r.read tread, r.time treadtime, a.id aid, a.folder, f.readdate freadtime
		FROM pm_threads t
		LEFT JOIN pm_threadsread r ON t.id = r.tid AND r.uid = {$loguser['id']}
		LEFT JOIN pm_access      a ON t.id = a.thread  AND a.user = {$loguser['id']}
		LEFT JOIN pm_foldersread f ON a.folder = f.folder AND f.user = {$loguser['id']}
		WHERE t.id = {$id}
	");
	if (!$thread) {
		if (!$isadmin) {
			trigger_error("Accessed nonexistant PM thread number #{$id}", E_USER_NOTICE);
			notAuthorizedError('conversation');
		}

		$badposts = $sql->resultq("SELECT COUNT(*) FROM `pm_posts` WHERE `thread` = '{$id}'");
		if ($badposts <= 0) {
			errorpage("PM Thread ID #{$id} doesn't exist, and no posts are associated with the invalid thread ID.","index.php",'the index page');
		}

		// Admin can see and possibly remove bad posts
		$error = INVALID_THREAD;
		$thread = array(
			'id'           => $id,
			'closed'       => true,
			'replies'      => $badposts - 1,
			'title'        => "[ BAD PM THREAD ID #{$id} ]",
			'lastpostdate' => 0,
			'error'        => true,
		);
		$access = false;

	} else {
		//$access = $sql->fetchq("SELECT * FROM pm_access WHERE thread = {$id} AND user = {$loguser['id']}");
		if (!$thread['aid']) {
			if (!$isadmin) {
				trigger_error("Attempted to access PM thread {$id} in a restricted conversation (user's name: {$loguser['name']})", E_USER_NOTICE);
				notAuthorizedError('conversation');
			}
			$access = null;
		} else {
			$access = ['id' => $thread['aid'], 'thread' => $thread['id'], 'user' => $loguser['id'], 'folder' => $thread['folder']];
		}
	}
	if ($error) {
		switch ($error) {
			case INVALID_THREAD: $errortext='This PM thread does not exist, but posts exist that are associated with this invalid thread ID.'; break;
		}
		$forum_error = "<tr><td style='background:#cc0000;color:#eeeeee;text-align:center;font-weight:bold;'>{$errortext}</td></tr>";
	}
	load_layout();
}

/*
	valid_pm_acl: check if the user list is valid
	$userlist   - array with user names
	$allow_self - if false, the current user should not be present in the list
	$error      - contains the error text
*/
function valid_pm_acl($userlist, $allow_self, &$error) {
	global $config, $loguser;
	// Increase the limit to account ourselves
	$limit = ($allow_self && $config['pmthread-dest-limit'] > 0) ? $config['pmthread-dest-limit'] + 1 : $config['pmthread-dest-limit'];
	
	$destcount = count($userlist);
	if (!$destcount) {
		$error = "You haven't entered an existing username to send this conversation to.";
		return false;
	} else if ($config['pmthread-dest-limit'] && $destcount > $config['pmthread-dest-limit']) {
		$error = "You have entered too many usernames.";
		return false;
	}
	
	// Loop through the user list and report bad users
	$badusers = "";
	$badself  = false;
	foreach ($userlist as $x) {
		$x = trim($x);
		if (!$allow_self && $loguser['name'] == $x) { // $allow_self is true for admins (where they explicitly have to add themseles to the list), false for normal users
			$badself = true;
		} else if ($valid = valid_user($x)) {
			$destid[$valid] = $valid; // no duplicates please
		} else {
			$badusers .= "<li>".htmlspecialchars($x)."</li>";
		}
	}
	
	if ($badusers) {
		$error = "The following users you've entered don't exist:<ul>{$badusers}</ul>";
		if ($badself) $error .= "You are also automatically added as a partecipant. You can't add yourself manually";
	} else if ($badself) {
		$error .= "You are automatically added as a partecipant. You can't add yourself manually";
	} else {
		return $destid;
	}
	return false;
}

/*
	set_pm_acl: set access permissions for a thread
	$users - array of user IDs
	$thread - ID of the PM Thread
	$show_self - if false, the logged in user is automatically added to the ACL even though it's not in $users
	$self_folder - the folder the logged in user is moving the PM to. only has effect if $show_self is false
*/
function set_pm_acl($users, $thread, $show_self = false, $self_folder = PMFOLDER_MAIN) {
	global $sql, $loguser;
	
	// Remove users missing from the list...
	$noshow = $show_self ? 0 : $loguser['id']; //... (and account for lists omitting the logged in user)
	$sql->query("DELETE FROM pm_access WHERE thread = {$thread} AND user NOT in (".implode(',', $users).", {$noshow})");
	
	// Then add the users without touching the existing values
	$acl = $sql->prepare("INSERT IGNORE INTO pm_access (thread, user, folder) VALUES (?,?,?)");
	foreach ($users as $x) {
		$sql->execute($acl, [$thread, $x, PMFOLDER_MAIN]);
	}
	if (!$show_self) { // If $show_self is false, $users does not contain the logged in user, so we have to add ourselves manually
		$sql->execute($acl, [$thread, $loguser['id'], $self_folder]);
	}
			
}

function create_pm_thread($treq) {
	global $sql;
	// For consistency with create_post, allow both array and int args
	if (is_array($treq->vals['user'])) {
		$treq->vals['user'] = filter_int($treq->vals['user']['id']);
		if (!$treq->vals['user']) return 0;
	}
	$currenttime = time();
		
	// Additional fields
	$treq->vals['replies']			= 0;
	$treq->vals['firstpostdate']	= $currenttime;
	$treq->vals['lastpostdate']		= $currenttime;
	$treq->vals['lastposter']		= $treq->vals['user'];
	
	$sql->queryp("INSERT INTO `pm_threads` SET ".mysql::setplaceholders($treq->vals), $treq->vals);
	return $sql->insert_id();
}

function create_pm_post($preq) {
	global $sql;
	
	// $user consistency support
	$user = $preq->vals['user'];
	if (!is_array($user)) {
		$user = $sql->fetchq("SELECT id, posts, regdate, postheader, signature, css FROM users WHERE id = {$user}");
		if (!$user) return 0;
	} else {
		// If we're an array, the user id goes in the query
		$preq->vals['user'] = $user['id'];
	}
	
	// Tag support
	$tags = get_tags($user, [
		'mood'     => $preq->vals['moodid'],
		'numposts' => $user['posts'], // show the current count in PMs
	]);
	$preq->vals['text']     = replace_tags($preq->vals['text'], $tags);
	$preq->vals['tagval']   = json_encode($tags);
	
	// Post layout options
	if ($preq->nolayout) {
		$preq->vals['headid']      = 0;
		$preq->vals['signid']      = 0;
		$preq->vals['cssid']       = 0;
		$preq->vals['sidebarid']   = 0;
		$preq->vals['sidebartype'] = 0;
	} else {
		$preq->vals['headid']      = getpostlayoutid($user['postheader']);
		$preq->vals['signid']      = getpostlayoutid($user['signature']);
		$preq->vals['cssid']       = getpostlayoutid($user['css']);
		$preq->vals['sidebarid']   = getpostlayoutid($user['sidebar']);
		$preq->vals['sidebartype'] = $user['sidebartype'];
	}
	
	// Misc
	$currenttime = time();
	$preq->vals['date'] = $currenttime;


	$sql->queryp("INSERT INTO `pm_posts` SET ".mysql::setplaceholders($preq->vals), $preq->vals);
	$pid = $sql->insert_id();
	
	// Update statistics
	$sql->query("UPDATE `users` SET `lastpmtime` = '$currenttime' WHERE `id` = '{$user['id']}'");
	
	if ($sql->resultq("SELECT COUNT(*) FROM pm_posts WHERE thread = {$preq->vals['thread']}") > 1) {
		$modq = ($preq->threadupdate ? mysql::setplaceholders($preq->threadupdate)."," : "");
		$sql->queryp("UPDATE `pm_threads` SET {$modq} `replies` =  `replies` + 1, `lastpostdate` = '{$currenttime}', `lastposter` = '{$user['id']}' WHERE `id` = '{$preq->vals['thread']}'", $preq->threadupdate);
		$sql->query("UPDATE `pm_threadsread` SET `read` = '0' WHERE `tid` = '{$preq->vals['thread']}'");
	}
	$sql->query("REPLACE INTO pm_threadsread SET `uid` = '{$user['id']}', `tid` = '{$preq->vals['thread']}', `time` = '{$currenttime}', `read` = '1'");
	$sql->query("UPDATE `users` SET `lastpmtime` = '{$currenttime}' WHERE `id` = '{$user['id']}'");
	return $pid;
}

// request/response models that can be passed around to extensions
class create_pm_thread_req {
	// Query values
	public $vals;
	// Created thread ID (return)
	public $id;
}

class create_pm_post_req {
	// Query values
	public $vals;
	// Virtual "No layout" option (not a real post flag)
	public $nolayout;
	// Fields to update in the threads row, for mod actions
	public $threadupdate = [];
	// Created post ID (return)
	public $id;
}