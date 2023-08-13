<?php
	chdir("..");
	require "install/setup_function.php";
	
	$errors = [];
	set_error_handler('error_reporter');
	set_exception_handler('exception_reporter');
	
	// Detect IP bans if the board is already installed, we don't want assholes trying to force themselves in.
	if (INSTALLED) {
		require "lib/config.php";
		$sql = new mysql;
		if ($sql->connect($sqlhost, $sqluser, $sqlpass, $dbname)) {
			if (SETUP_DEBUG && isset($_GET['unban'])) {
				$sql->query("DELETE FROM ipbans WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");
				$sql->query("DELETE FROM failedlogins WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");
				die(header("Location: ?"));
			}
			$banned = $sql->fetchq("SELECT ip, reason FROM ipbans WHERE INSTR('{$_SERVER['REMOTE_ADDR']}',ip) = 1 AND (expire = 0 OR expire > ".ctime().")");
			if ($banned) {
				setupheader("Banned");
				print "
				<span class='c-error'>You are IP banned.</span>
				<br>
				<br>Reason: <span class='c-highlight'>{$banned['reason']}</span>
				<br>
				<br>You can't use the installer if you're IP banned.
				";
				setupfooter();
			}
		} else {
			$sql = null;
		}
	}	
	
	// Get the current step number and remove it out of the request (so it won't get saved/duplicated on submit)
	$step = filter_int($_POST['step']);
	unset($_POST['step']);
	
	// initialize button status
	$btn = $step > 0 ? BTN_PREV | BTN_NEXT : BTN_NEXT;