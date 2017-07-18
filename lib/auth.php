<?php

// Permission names are passed lowercase and with - instead of _ (ie: has_perm('bypass-lockdown') checks PERM_BYPASS_LOCKDOWN)
// The correct bitmask to search for is specified in the constant definition, so we do not need to worry about passing the bitmask field id manually.
function has_perm($permName) {
	global $loguser;
	$permArray = constant("PERM_" . str_replace("-", "_", strtoupper($permName)));
	return $loguser['permflags']['set'.$permArray[0]] & $permArray[1];
}

// Determines
function check_perm($permName, $group, $cache = NULL) {
	if ($cache === NULL) {
		$cache = load_perm(0, $group);
	}
	$permArray = constant("PERM_" . str_replace("-", "_", strtoupper($permName)));
	return $cache['set'.$permArray[0]] & $permArray[1];
}

// Forum permissions are 6 bits long (Read,Post,Edit,Delete,Thread,Mod)
// $permName can be any of those four words
// $sourceArr has to contain the key 'forumperm' which defines the bitset to check, and optionally a 'userperm' key as patch data.
// $noAllCheck is mostly used in the forum permission editor to display the real permission flags (for those who have all-forum-access perm)
function has_forum_perm($permName, $sourceArr, $noAllCheck = false) {
	if ($noAllCheck || !has_perm('all-forum-access')) {
		$permBit = constant("PERM_FORUM_" . strtoupper($permName));
		$check = isset($sourceArr['userperm']) ? $sourceArr['userperm'] : $sourceArr['forumperm'];
		return $check & $permBit;
	} else {
		return PERM_ALL_FORUM_ACCESS;
	}
}

// Gets the forum permission given a $forum ID, $user ID and $group ID
// For performance reasons (?) this is rarely used,
// as to save time these fields are directly fetched from most of the queries that get forum data
function get_forum_perm($forum, $user, $group){
	global $sql;
	return $sql->fetchq("
		SELECT pf.group{$group} forumperm, pu.permset userperm
		FROM forums f
		LEFT JOIN perm_forums     pf ON f.id    = pf.id
		LEFT JOIN perm_forumusers pu ON f.id    = pu.forum AND pu.user = {$user}
		WHERE f.id = {$forum}
	", PDO::FETCH_ASSOC, mysql::USE_CACHE);
}

// Generate the bitmask field names for a query
function perm_fields($talias = "", $falias = "", $fakevalue = NULL) {
	$pre 	= $talias ? "{$talias}." : ""; // Table alias
	if ($fakevalue !== NULL) {
		$pre = "$fakevalue $pre";	// Goat value to set all perm fields to
	}
	
	$out = "";
	// Thank you iteration performance (for whatever it's worth)
	if (!$falias) {
		for ($i = 0; $i < PERM_FIELDS_NUM; ++$i) {
			$out .= ($i ? " ," : "")."{$pre}set".($i+1);
		}
	} else {
		for ($i = 0; $i < PERM_FIELDS_NUM; ++$i) {
			$out .= ($i ? " ," : "")."{$pre}set".($i+1)." {$falias}".($i+1);
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
	global $sql;
	
	$setfields = perm_fields();
	
	$power = $sql->fetchq("SELECT {$setfields} FROM perm_groups WHERE id = {$group}");
	// Save a query if we're not logged in, since we wouldn't have proper perm_user bitmasks anyway.
	if ($user) {
		$userpower 	= $sql->fetchq("SELECT {$setfields} FROM perm_users WHERE id = {$user}");
		
		if (is_array($userpower)) {
			// Replace non-null bitmasks with this "patch data"
			foreach ($userpower as $set => $bitmask) {
				if (isset($bitmask)) {
					$power[$set] = $bitmask;
				}
			}
		}
	}
	return $power;
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
		// Also check for the old md5 hash, allow a login and update it if successful
		// This shouldn't impact security (in fact it should improve it)
		if (!$hacks['password_compatibility'])
			return -1;
		else {
			if ($user['password'] === md5($pass)) { // Uncomment the lines below to update password hashes
				$sql->query("UPDATE users SET `password` = '".getpwhash($pass, $user['id'])."' WHERE `id` = '{$user['id']}'");
				xk_ircsend("102|".xk(3)."Password hash for ".xk(9).$name.xk(3)." (uid ".xk(9).$user['id'].xk(3).") has been automatically updated.");
			}
			else return -1;
		}
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

function generate_token($div = 20, $extra = "") {
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
	return hash('sha256', $loguser['name'] . $_SERVER['REMOTE_ADDR'] . $config['salt-string'] . $extra . $loguser['password']);
	
}

function check_token(&$var, $div = 20, $extra = "") {
	$res = (trim($var) == generate_token($div, $extra));
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
