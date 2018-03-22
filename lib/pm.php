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
			$nosel  = "WHERE folder NOT IN (".implode(',', $sel).")";
			$sel    = -100;
		} else {
			$nosel  = "WHERE folder != {$sel}";
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
	$folders = $sql->query("SELECT folder, title FROM pm_folders {$nosel} ORDER BY ord ASC, id ASC");
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
		'title'     => xssfilters($title),
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
		'title'     => xssfilters($title),
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