<?php
	require "lib/function.php";
	require "lib/extension_mgmt.php";
	require "install/setup_defines.php";
	require "install/setup_layout.php";
	require "install/setup_schema.php";
	require "install/setup_mysql.php";
	
	// Validation reporting (usually a query result is passed here)
	function checkres($r) {
		return $r 
			? "<span class='c-success'>OK!</span>\n" 
			: "<span class='c-error'>ERROR!</span>\n";
	}
	function checkresmulti($r) {
		foreach ($r as $x) {
			if (!$x) return checkres(false);
		}
		return checkres(true);
	}
	
	// Utilities for db upgrades
	function get_available_db_version() {
		return count(glob("update/*.php", GLOB_NOSORT));
	}
	
	function get_current_db_version() {
		return (file_exists(DBVER_PATH) ? (int) file_get_contents(DBVER_PATH) : 0);
	}
	
	function updates_available() {
		return get_available_db_version() > get_current_db_version();
	}
	
	// To view and reset the upgrade step
	function update_step() {
		global $_updstp;
		print "<div class='center b'>[ Step ".(++$_updstp)." ]</div>";
	}
	function reset_update_step() {
		global $_updstp;
		$_updstp = 0;
	}
	
	function add_scheme($theme) {
		global $sql;
		$used = $sql->resultq("SELECT COUNT(*) FROM `schemes` WHERE id = '{$theme['id']}'");
		if ($used) {
			unset($theme['id']);
		}
		return $sql->queryp("INSERT INTO `schemes` SET ".mysql::setplaceholders($theme), $theme);
	}
	
	// Verifies the login cookies are set.
	// If not, it attempts to generate "cookies"  based on the credentials sent through _POST.
	// If the attempt is successful, the raw credentials are wiped from _POST, otherwise the user is blocked from proceeding.
	
	// This is basically the same thing as the normal cookie verification, except the cookies are stored in _POST.
	function verify_password() {
		// Logging out is really just reloading the fresh index page
		if (SETUP_DEBUG && isset($_GET['logout'])) {
			die(header("Location: ?"));
		}
		
		// Cookie verification
		$loguserid = filter_int($_POST['setupid']);
		$logverify 	= __($_POST['setupverify']);

		if ($loguserid && $logverify) {
			// Create a known good login cookie, verifying that it matches with the one provided in _POST
			$loguser    = $sql->fetchq("SELECT powerlevel, password FROM `users` WHERE `id` = $loguserid");
			$verifyid   = (int) substr($logverify, 0, 1);
			$verifyhash = create_verification_hash($verifyid, $loguser['password']);
			
			// If the hash matches, we're good
			if ($verifyhash === $logverify)
				return;
			// Otherwise, log out the user
			unset($_POST['setupid'], $_POST['setupverify']);
		}
		
		// Credentials verification
		
		$_POST['user'] 	= filter_string($_POST['user']);
		$_POST['pass'] 	= filter_string($_POST['pass']);
		// No password sent
		if (!trim($_POST['user']) || !trim($_POST['pass'])) {
			$btn = BTN_NEXT;
			$step = -1;
			return;
		} 
		
		// Invalid user or password
		$userid = checkuser($_POST['user'], $_POST['pass']);
		if ($userid == -1) {
			$windowtitle = "Bad credentials";
			$output = "
			<span class='c-warn'>Incorrect credentials entered.</span>
			<br>
			<br>";
			// If the username and password are both provided, those count towards the IP ban
			if (trim($_POST['user']) && trim($_POST['pass']) && _loginfail()) {
				_no_login(BTN_NONE);
				$output .= "<span class='c-error'>You are now IP banned for too many failed login attempts.</span>";
			} else {
				_no_login();
				$output .= "Return to the previous page and enter the correct login credentials.";
			}
			return;
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
			return;
		}
		
		// Save to _POST cookie (force /32 verification)
		$verify = create_verification_hash(4, $user['password']);

		$_POST['setupid'] = $userid;
		$_POST['setupverify'] = $verify;
		
		// And wipe out the plaintext
		unset($_POST['user'], $_POST['pass']);
		
	};

	function _no_login($btn_step = BTN_PREV) {
		global $error, $btn, $step;
		$step = 0;
		unset($_POST['setupid'], $_POST['setupverify']);
		$error = true;
		$btn = $btn_step;
	}

	function _loginfail() {
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