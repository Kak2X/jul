<?php

function load_grouplist() {
	global $sql;
	return $sql->fetchq("SELECT id, name, hidden, subgroup, namecolor0, namecolor1, namecolor2, ".perm_fields()." FROM perm_groups", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
}

function get_subgroups($user) {
	global $sql;
	return $sql->fetchq("SELECT group_id, 1 FROM users_subgroups WHERE user = {$user}", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL | mysql::USE_CACHE);
}

// $new_groups is an array
function set_subgroups($user, $new_groups) {
	global $sql, $grouplist;
	$add = $sql->prepare("INSERT INTO users_subgroups (user, group_id) VALUES({$user},?)");
	$rem = $sql->prepare("DELETE FROM users_subgroups WHERE user = {$user} AND group_id = ?");
	$current  = get_subgroups($user);
	$priority = [-1, 0];
	
	foreach ($grouplist as $id => $data) {
		if (!$data['subgroup']) {
			continue;
		} else if (isset($new_groups[$id])) { // If the subgroup is present...
			if (!isset($current[$id])) { // and hasn't been already added...
				$sql->execute($add, [$id]); // add it now
			}
			// we also check the max priority value for later (subgroup field doubles as a priority value)
			if ($data['subgroup'] > $priority[0]) {
				$priority = [$data['subgroup'], $id];
			}
			
		} else { // remove the group
			if (isset($current[$id])) {
				$sql->execute($rem, [$id]);
			}
		}
	}
	
	// Subgroups are only ever updated here
	// this makes sure the cached "primary subgroup" value is always updated
	$sql->query("UPDATE users SET main_subgroup = {$priority[1]} WHERE id = {$user}");
}

// Permission names are passed here
// The correct bitmask to search for is specified in the perm definition, so we do not need to worry about passing the bitmask field id manually.
function has_perm($permName) {
	global $loguser, $permlist;
	//$permArray = constant("PERM_" . str_replace("-", "_", strtoupper($permName)));
	if (isset($permlist[$permName])) {
		$permArray = $permlist[$permName];
		return $loguser['permflags']['set'.$permArray[0]] & $permArray[1];
	} else {
		trigger_error("has_perm - Missing permission key: {$permName}", E_USER_WARNING);
	}
}

// Determines the permission fields for a different group (used in management pages)
function check_perm($permName, $user, $group, $cache = NULL) {
	global $permlist;
	if ($cache === NULL) {
		$cache = load_perm($user, $group);
	}
	//$permArray = constant("PERM_" . str_replace("-", "_", strtoupper($permName)));
	if (isset($permlist[$permName])) {
		$permArray = $permlist[$permName];
		return $cache['set'.$permArray[0]] & $permArray[1];
	} else {
		trigger_error("check_perm - Missing permission key: {$permName}", E_USER_WARNING);
	}
}

// The generic permission fields are now defined in the database (they used to be defined as array constants)
function load_permlist($desc = false) {
	global $sql;
	if (!$desc) {
		return $sql->fetchq("SELECT title, permset, permbit FROM perm_definitions ORDER BY permset ASC, permbit ASC", PDO::FETCH_UNIQUE | PDO::FETCH_NUM, mysql::FETCH_ALL);
	} else {
		return $sql->fetchq("SELECT title, permset, permbit, description, type FROM perm_definitions ORDER BY permset ASC, permbit ASC", PDO::FETCH_UNIQUE | PDO::FETCH_NUM, mysql::FETCH_ALL);
	}
}

// Forum permissions are 6 bits long (Read,Post,Edit,Delete,Thread,Mod)
// $permName can be any of those four words
// $permSet is the permission set
// $noAllCheck is mostly used in the forum permission editor to display the real permission flags (for those who have all-forum-access perm)
function has_forum_perm($permName, $permSet, $noAllCheck = false) {
	if ($noAllCheck || !has_perm('all-forum-access')) {
		$permBit = constant("PERM_FORUM_" . strtoupper($permName));
		return $permSet & $permBit;
	} else {
		return true;
	}
}

// Gets the forum permission given a $forum ID, $user ID and $group ID
function get_forum_perm($forum, $user, $group) {
	global $sql;
	
	// Save a query if we're not logged in, since we wouldn't have proper perm_user bitmasks anyway.
	if ($user) {
		
		// include subgroups perms as well
		$subgroups = get_subgroups($user);
		$subgroups_txt = "";
		foreach ($subgroups as $subgroup_id => $dummy) {
			$subgroups_txt .= "pf.group{$subgroup_id}, ";
		}
	
		$pquery = $sql->fetchq("
			SELECT pf.group{$group}, {$subgroups_txt} pu.permset userperm
			FROM perm_forums pf
			LEFT JOIN perm_forumusers pu ON pf.id = pu.forum AND pu.user = {$user}
			WHERE pf.id = {$forum}");
		
		// to allow calculate_perms to work on both global and forum permissions
		// the forum permission have to follow the global perm name conventions
		// (an array with keys 'set0', 'set1', 'set2', ...)
		// since there is only one permset for forum permissions, only set0 is filled
		$power = [];
		$power['set0'] = 0;
		foreach ($pquery as $x) {
			$power = calculate_perms($power, ['set0' => $x], 1); // there's only one permset, hence the 1
		}
	} else {
		// If we're not logged in, we inherit the (usually guest) permissions
		$power      = $sql->fetchq("SELECT pf.group{$group} FROM perm_forums WHERE id = {$forum}");
	}
	return $power;
}

function get_all_forum_perm($cur_forum, $user, $group, $showall = 0, $skip = 0) {
	global $sql;
	
	// Save a query if we're not logged in, since we wouldn't have proper perm_user bitmasks anyway.
	if ($user) {
		
		// include subgroups perms as well
		$subgroups = get_subgroups($user);
		$subgroups_txt = "";
		foreach ($subgroups as $subgroup_id => $dummy) {
			$subgroups_txt .= "pf.group{$subgroup_id}, ";
		}
	
		$pquery = $sql->fetchq("
			SELECT pf.id, pf.group{$group}, {$subgroups_txt} pu.permset userperm
			FROM forums f
			LEFT JOIN perm_forums     pf ON f.id = pf.id
			LEFT JOIN perm_forumusers pu ON f.id = pu.forum AND pu.user = {$user}
			WHERE (f.custom OR !ISNULL(f.catid)) 
			".($showall ? "" : "AND (!f.custom OR f.id = {$cur_forum}) AND !f.hidden")."
			  AND f.id != {$skip}
			ORDER BY pf.id
		",PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
		
		// For each forum requested ($key is forum id), 
		// calculate the respective permissions (everything else in the row)
		// each column is its own permission set
		$power = [];
		foreach ($pquery as $key => $pqrow) {
			$power[$key]['set0'] = 0;
			foreach ($pqrow as $x) {
				$power[$key] = calculate_perms($power[$key], ['set0' => $x], 1);
			}
		}
	} else {
		// If we're not logged in, we inherit the (usually guest) permissions
		$power      = $sql->fetchq("SELECT pf.id, pf.group{$group} FROM perm_forums ORDER BY pf.id", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
	}
	return $power;
}

// Generate the bitmask field names for a query
function perm_fields($talias = "", $falias = "", $fakevalue = NULL) {
	global $miscdata;
	$pre 	= $talias ? "{$talias}." : ""; // Table alias
	if ($fakevalue !== NULL) {
		$pre = "$fakevalue $pre";	// Goat value to set all perm fields to
	}
	
	$out = "";
	// Thank you iteration performance (for whatever it's worth)
	if (!$falias) {
		for ($i = 0; $i < $miscdata['perm_fields']; ++$i) {
			$out .= ($i ? ", " : "")."{$pre}set{$i}";
		}
	} else {
		for ($i = 0; $i < $miscdata['perm_fields']; ++$i) {
			$out .= ($i ? ", " : "")."{$pre}set{$i} {$falias}{$i}";
		}
	}
	return $out;
}

/*
	Permission system:
	
	The usergroups are defined in perm_groups. Any group ID has bitmasks for global forum options.
	These can be overridden by the bitmasks in perm_user if they aren't NULL.
*/
function load_perm($user, $group) {
	if ($user) {
		global $sql, $grouplist, $miscdata;
		
		// First pass -> primary group
		$power = get_perms_from_grouplist($group);
		
		// Second pass -> secondary groups
		$groups = get_subgroups($user);
		foreach ($groups as $id => $dummy) {
			$power = calculate_perms($power, $grouplist[$id], $miscdata['perm_fields']); 
		}
		
		// Third pass -> user specific overrides
		$userpower 	= $sql->fetchq("SELECT ".perm_fields()." FROM perm_users WHERE id = {$user}");
		if (is_array($userpower)) {
			$power = calculate_perms($power, $userpower, $miscdata['perm_fields']);
		}
		
		
	} else {
		// If we're not logged in, we inherit the (usually guest) permissions
		// the permission fields are always the last columns of $grouplist
		$power = get_perms_from_grouplist($group);
	}
	return $power;
}

// get the last n columns for a group (which are reserved for permission fields)
function get_perms_from_grouplist($group) {
	global $grouplist, $miscdata;
	return array_slice(
		$grouplist[$group], 
		count($grouplist[$group]) - $miscdata['perm_fields'],
		$miscdata['perm_fields']
	);
}

function calculate_perms($a, $b, $limit) {
	for ($i = 0; $i < $limit; ++$i) {
		if (!$b["set{$i}"]) {
			continue; // Don't waste time with empty perms defs
		} else if (!isset($a["set{$i}"])) {
			$a["set{$i}"] = 0; // Possibly the first call to calculate_perms() from another function
		}
		
		$j = 0b11;
		while (true) {
			switch($b["set{$i}"] & $j) {
				case 0:
					continue; // 0b00 -> don't change (default deny)
				case 1:
					$a["set{$i}"] != $j; // 0b01 -> deny
					break;
				case 3:
					$a["set{$i}"] |= $j; // 0b11 -> allow
					break;
			}
			
			if ($j == 0b110000000000000000000000000000) {
				break;
			} else {
				$j = $j << 2;
			}
		}
	}
	return $a;
}

// Returns an array of group IDs with groups hidden behind $group
// since a group can be set to be hidden behind another one
// (for example, by default the Normal+/Super group is displayed as Normal)
// $group -> a non-hidden group
// $subgroup -> counter to mark the number of subgroups. should be initialized to 0 beforehand
//              compare it to count($out) to check if there's a mix of groups & subgroups
function group_hidden($group, &$subgroup) {
	global $grouplist;
	// Add the element we're checking too (just in case)
	$out = array($group);
	if ($grouplist[$group]['subgroup']) $subgroup++;
	
	foreach ($grouplist as $id => $x) {
		if ($x['hidden'] == $group) {
			// We found a group taking the appearance of what we're checking
			$out[] = $id;
			if ($x['subgroup']) $subgroup++;
		}
	}
	return $out;
}

// Yeah ok
function user_fields($alias) {
	global $userfields;
	return str_replace('u.', $alias.'.', $userfields);
}

function ismod($fdata) {return ($fdata && has_forum_perm('mod', $fdata));}

function admincheck($perm = 'admin-actions') {
	if (!has_perm($perm)) {
		errorpage("This feature is restricted to administrators.<br>You aren't one, so go away.",'index.php','return to the board',0);
	}
}
	
// Only used to check if an user exists
function checkusername($name){
	global $sql;
	if (!$name) return -1;
	$u = $sql->resultp("SELECT id FROM users WHERE name = ?", [$name]);
	if (!$u) $u = -1;
	return $u;
}

function checkuser($name, $pass){
	global $hacks, $sql;

	if (!$name) return -1;
	// DEBUG LOGIN: $sql->query("UPDATE users SET password = '".getpwhash($pass, 1)."' WHERE id = 1");
	$user = $sql->fetchp("SELECT id, password FROM users WHERE name = ?", [$name]);

	if (!$user) return -1;
	
	if (!password_verify(sha1($user['id']).$pass, $user['password'])) {
		return -1;
		// Also check for the old md5 hash, allow a login and update it if successful
		// This shouldn't impact security (in fact it should improve it)
		/*if (!$hacks['password_compatibility'])
			return -1;
		else {
			if ($user['password'] === md5($pass)) { // Uncomment the lines below to update password hashes
				$sql->query("UPDATE users SET `password` = '".getpwhash($pass, $user['id'])."' WHERE `id` = '{$user['id']}'");
				xk_ircsend("102|".xk(3)."Password hash for ".xk(9).$name.xk(3)." (uid ".xk(9).$user['id'].xk(3).") has been automatically updated.");
			}
			else return -1;
		}*/
	}
	
	return $user['id'];
}

function create_verification_hash($n,$pw) {
	$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
	$vstring = 'verification IP: ';

	$tvid = $n;
	while ($tvid--)
		$vstring .= array_shift($ipaddr) . "|";

	// don't base64 encode like I do on my fork, waste of time (honestly)
	return $n . hash('sha256', $pw . $vstring);
}

function generate_token($div = TOKEN_MAIN) {
	global $config, $loguser;
	/* extra IP mangling not needed
	if (substr($_SERVER['REMOTE_ADDR'], 0, 2) == "::") {
		$ipaddr = array(127,0,0,1);
	} else if (strpos($_SERVER['REMOTE_ADDR'], ":") !== false) {
		$ipaddr = explode(':', $_SERVER['REMOTE_ADDR']);
	} else {
		$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
	}
	
	$n 		= count($ipaddr) - 2;
	$orig 	= $ipaddr[$n+1];
	
	for ($i = $n; $i >= 0; --$i) 
		$ipaddr[$i+1] = $ipaddr[$i+1] << ($ipaddr[$i] / $div);
	$ipaddr[0] = $ipaddr[0] << ($orig / $div);
	
	$ipaddr = implode('.', $ipaddr);
	*/
	return hash('sha256', $loguser['name'] . $_SERVER['REMOTE_ADDR'] . $config['salt-string'] . $div . $loguser['password']);
	
}

function check_token(&$var, $div = TOKEN_MAIN) {
	$res = (trim($var) == generate_token($div));
	if (!$res) errorpage("Invalid token.");
}

function getpwhash($pass, $id) {
	return password_hash(sha1($id).$pass, PASSWORD_BCRYPT);
}
/*
function shenc($str){
	$l=strlen($str);
	for($i=0;$i<$l;$i++){
		$n=(308-ord($str[$i]))%256;
		$e[($i+5983)%$l]+=floor($n/16);
		$e[($i+5984)%$l]+=($n%16)*16;
	}
	for($i=0;$i<$l;$i++) $s.=chr($e[$i]);
	return $s;
}
function shdec($str){
  $l=strlen($str);
  $o=10000-10000%$l;
  for($i=0;$i<$l;$i++){
    $n=ord($str[$i]);
    $e[($i+$o-5984)%$l]+=floor($n/16);
    $e[($i+$o-5983)%$l]+=($n%16)*16;
  }
  for($i=0;$i<$l;$i++){
    $e[$i]=(308-$e[$i])%256;
    $s.=chr($e[$i]);
  }
  return $s;
}
function fadec($c1,$c2,$pct) {
  $pct2=1-$pct;
  $cx1[r]=hexdec(substr($c1,0,2));
  $cx1[g]=hexdec(substr($c1,2,2));
  $cx1[b]=hexdec(substr($c1,4,2));
  $cx2[r]=hexdec(substr($c2,0,2));
  $cx2[g]=hexdec(substr($c2,2,2));
  $cx2[b]=hexdec(substr($c2,4,2));
  $ret=floor($cx1[r]*$pct2+$cx2[r]*$pct)*65536+
	 floor($cx1[g]*$pct2+$cx2[g]*$pct)*256+
	 floor($cx1[b]*$pct2+$cx2[b]*$pct);
  $ret=dechex($ret);
  return $ret;
}
*/
