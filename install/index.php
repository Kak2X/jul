<?php

require "setup_common.php";

$error = false;

$_POST['setup_mode'] = isset($_POST['setup_mode']) ? (int)$_POST['setup_mode'] : -1;

// for preventing access with invalid SQL credentials
if (INSTALLED && INSTALL_LOCK && INSTALL_VALID_CONN && $sql === null) {
	die("Could not connect to the MySQL server.");
}

// Disable password prompt
if (!INSTALLED && $step < 0) {
	$step = 0;
}
// don't bother asking for a superadmin password if the SQL credentials are invalid or no superadmin account exists
if (INSTALLED && INSTALL_LOCK && $sql != null && $step >= 0) {
	$usercount = $sql->resultq("SELECT * FROM users WHERE powerlevel >= ".PWL_SYSADMIN."");
	if ($usercount > 0) {
		require "install/setup_password.php";
	}
}

if (!$error && $step >= -1) {
	switch ($step) {
		case -1:
			$windowtitle = "Password required";
			$output = "
				Enter the credentials for a board account with the '<span class='c-info'>{$pwlnames[PWL_SYSADMIN]}</span>' power level.
				<br>Typically this is the account of the the first registered user.
				<br>
				<br>
				<table class='table' style='margin: auto'>
					<!-- autocomplete prevention -->
					<input style='display:none' type='text'><input style='display:none' type='password'>
					<tr><td class='tdbgh center b' colspan='2'>Credentials</td></tr>
					<tr>
						<td class='tdbg1 center b'>Username:</td>
						<td class='tdbg2'><input type='text' name='user' value=\"".htmlspecialchars($_POST['user'])."\"></td>
					</tr>
					<tr>
						<td class='tdbg1 center b'>Password:</td>
						<td class='tdbg2'><input type='password' name='pass' value=\"".htmlspecialchars($_POST['pass'])."\"></td>
					</tr>
				</table>";
			break;
		case 0:
			$windowtitle = "Welcome";
			$edition = PRIVATE_BUILD ? "Private" : "Standard";
			$output = "
			Welcome to the Acmlmboard installer.
			".(PRIVATE_BUILD ? "<br>As you seem to be using a private copy of the board, please <span class='c-error'>do not distribute</span> it!<br>" : "")."
			<br>Please report all bugs to Kak or the <a href='https://github.com/Kak2X/jul/issues'>Bug Tracker</a>.
			<br>
			<br>Click '<span class='c-info'>Next</span>' to continue.";
			break;
		case 1:
			$windowtitle = "What to do";
			
			$opt[$_POST['setup_mode']] = " checked";
			$output = "
					You can choose a few options here.
					<br>					
					<br><label><input type='radio' name='setup_mode' value='0'".__($opt[0]).(!INSTALLED ? "" : " readonly disabled")."> New installation</label>
					<br><span class='fonts'>Perform a fresh install of the board database and write a new configuration file.</span>
					<br>
					<br><label><input type='radio' name='setup_mode' value='1'".__($opt[1]).(INSTALLED && updates_available() ? "" : " readonly disabled")."> Perform upgrade</label>
					<br><span class='fonts'>Performs an upgrade of the database configuration to the newest version.<br>Only available when new update scripts are found in the update folder.</span>
					<br>
					<br><label><input type='radio' name='setup_mode' value='2'".__($opt[2]).(INSTALLED && !updates_available() ? "" : " readonly disabled")."> Change configuration</label>
					<br><span class='fonts'>Only updates the configuration file. Available when the board is already installed and updated.</span>";
			break;
		default:
			$modes = ["install", "update", "config"];
			if (!isset($modes[$_POST['setup_mode']])) {
				$error = true;
				$windowtitle = "Error";
				$output = "
					You didn't select any option in the previous page.
					<br>
					<br>To proceed, you have to select one. Return to the previous page.";
				break;
			}
			require "install/index_{$modes[$_POST['setup_mode']]}.php";
	}
}

// Disable next button for errors
if ($error) {
	$btn &= ~BTN_NEXT;
}


if (isset($_GET['ajax'])) {
	die(json_encode(['title' => $windowtitle, 'text' => $output, 'btn' => $btn, 'step' => $step, 'vars' => $_POST]));
}

setupheader($windowtitle, $btn);
print save_vars($_POST);
print $output;
setupfooter($btn);