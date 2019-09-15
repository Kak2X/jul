<?php
if (!defined('INSTALL_FILE')) die;

//--

do {
	if (SETUP_DEBUG && isset($_GET['logout'])) {
		remove_board_cookie('setupid');
		remove_board_cookie('setupverify');
		die(header("Location: ?"));
	}
	
	// Cookie verification
	$loguserid = filter_int($_COOKIE['setupid']);
	$logverify 	= v($_COOKIE['setupverify']);

	if ($loguserid && $logverify) {
		$loguser    = $sql->fetchq("SELECT powerlevel, password FROM `users` WHERE `id` = $loguserid");
		$verifyid   = (int) substr($logverify, 0, 1);
		$verifyhash = create_verification_hash($verifyid, $loguser['password']);
		
		if ($verifyhash !== $logverify) {
			remove_board_cookie('setupid');
			remove_board_cookie('setupverify');
		} else {
			break;
		}
	}
	
	
	$_POST['user'] 	= filter_string($_POST['user']);
	$_POST['pass'] 	= filter_string($_POST['pass']);
	// No password sent
	if (!trim($_POST['user']) || !trim($_POST['pass'])) {
		$btn = BTN_NEXT;
		$_POST['step'] = -1;
		break;
	} 
	
	// Invalid user or password
	$userid = checkuser($_POST['user'], $_POST['pass']);
	if ($userid == -1) {
		$windowtitle = "Bad credentials";
		$output = "
		<span class='c-warn'>Incorrect credentials entered.</span>
		<br>
		<br>";
		if (trim($_POST['user']) && trim($_POST['pass']) && loginfail()) {
			_no_login(0);
			$output .= "<span class='c-error'>You are now IP banned for too many failed login attempts.</span>";
		} else {
			_no_login();
			$output .= "Return to the previous page and enter the correct login credentials.";
		}
		break;
	}
	
	// Bad user powerlevel
	$user = load_user($userid, true);
	if ($user['powerlevel'] < PWL_SYSADMIN) {
		_no_login();
		$windowtitle = "Bad account";
		$output = "
		<span class='c-warn'>Error!</span>
		<br>
		<br>This account is not a super administrator.";
		break;
	}
	
	// Save to cookie (force /32 verification)
	$verify = create_verification_hash(4, $user['password']);

	set_board_cookie('setupid', $userid);
	set_board_cookie('setupverify', $verify);
	unset($_POST['user'], $_POST['pass']);
	
} while (false);

function _no_login($btn_step = BTN_PREV) {
	global $error, $btn;
	$_POST['step'] = 0;
	remove_board_cookie('setupid');
	remove_board_cookie('setupverify');
	$error = true;
	$btn = $btn_step;
}