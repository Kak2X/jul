<?php

//----------------------------------------
// TODO: When the common board code gets split from lib/function.php, you can delete this file
//----------------------------------------

function filter_int(&$x) { return (int)$x; }
function filter_string(&$x) { return (string)$x; }
function v(&$x) { return $x; }

function save_vars($arr, $nested = "") {
	$out = "";
	foreach ($arr as $key => $val) {
		// Generate the associative key if needed (nests to config[something][dfgdsg]
		$name = ($nested) ? "{$nested}[{$key}]" : $key;
		if (is_array($val)) {
			$out .= save_vars($val, $name);
		} else {
			$out .= "<input type='hidden' name='{$name}' value=\"".htmlspecialchars($val)."\">";
		}
	}
	return $out;
}

function checkuser($name, $pass){
	global $sql;
	$user = $sql->fetchp("SELECT id, password FROM users WHERE name = ?", [$name]);
	if (!$user || !password_verify(sha1($user['id']).$pass, $user['password'])) {
		return -1;
	}
	return $user['id'];
}

function load_user($user, $all = false) {
	global $sql, $userfields;
	if (!$user) {
		return NULL;
	} else {
		return $sql->fetchq("SELECT ".($all ? "*" : $userfields)." FROM users u WHERE u.id = '{$user}'");
	}
}

function ctime(){global $config; return time() + $config['server-time-offset'];}

function create_verification_hash($n,$pw) {
	$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
	$vstring = 'verification IP: ';

	$tvid = $n;
	while ($tvid--)
		$vstring .= array_shift($ipaddr) . "|";

	// don't base64 encode like I do on my fork, waste of time (honestly)
	return $n . hash('sha256', $pw . $vstring);
}

// For some dumb reason a simple str_replace isn't enough under Windows
function strip_doc_root($file) {
	$root_path = $_SERVER['DOCUMENT_ROOT'];
	if (PHP_OS == 'WINNT') {
		$root_path = str_replace("/", "\\", $root_path);
	}
	return str_replace($root_path, "", $file);
}

function get_board_root() {
	static $root;
	if ($root === null) {
		// 16 is for removing "lib\function.php"
		// if you move this function elsewhere update the number
		$root = strip_doc_root(substr(__FILE__, 0, -16));
	}
	return $root;
}

function set_board_cookie($name, $value, $expire = 2147483647) {
	setcookie($name, $value, $expire, get_board_root(), $_SERVER['SERVER_NAME'], false, true);
}
function remove_board_cookie($name) {
	setcookie($name, '', time() - 3600, get_board_root(), $_SERVER['SERVER_NAME'], false, true);
}

function loginfail() {
	global $sql;
	$sql->queryp("INSERT INTO `failedlogins` SET `time` = :time, `username` = :user, `password` = :pass, `ip` = :ip",
	[
		'time'	=> ctime(),
		'user' 	=> $_POST['user'],
		'pass' 	=> $_POST['pass'],
		'ip'	=> $_SERVER['REMOTE_ADDR'],
	]);
	$fails = $sql->resultq("SELECT COUNT(`id`) FROM `failedlogins` WHERE `ip` = '". $_SERVER['REMOTE_ADDR'] ."' AND `time` > '". (ctime() - 1800) ."'");
	if ($fails >= 5) {
		$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."', `reason` = 'Send e-mail for password recovery'");
		return true;
	}
	return false;
}