<?php
	chdir("..");
	
	require "install\setup_function.php";
	require "install\setup_tempfunc.php";
	require "install\setup_defines.php";
	require "install\setup_layout.php";
	require "install\schema.php";
	require "lib\defines.php";
	require "lib\classes\mysql.php";
	require "install\mysql_setup.php";
	
	if (INSTALLED) {
		require "lib\config.php";
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
	
	// Get the current step number
	$_POST['step'] = filter_int($_POST['step']);
	
	// initialize button status
	$btn = $_POST['step'] > 0 ? BTN_PREV | BTN_NEXT : BTN_NEXT;