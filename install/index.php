<?php

require "setup_function.php";

$error = false;
$_POST['setup_mode'] = filter_int($_POST['setup_mode']);

switch ($_POST['step']) {
	case 0:
		$windowtitle = "Welcome";
		$output = "
		Welcome to the Acmlmboard installer.<br>
		<br>
		<br>Please report all bugs to Kak or the <a href='https://github.com/Kak2X/jul/issues'>Bug Tracker</a>.
		<br>
		<br>Click '<span class='c-info'>Next</span>' to continue.";
		break;
	case 1:
		$windowtitle = "What to do";
		$edition = PRIVATE_BUILD ? "Private" : "Standard";
		$opt[$_POST['setup_mode']] = " checked";
		//$opts = mk_radio('setup_mode', ["New installation", "Perform upgrade", "Change configuration"], $_POST['setup_mode']);
		$output = "
				Edition: [<span class='c-highlight'>{$edition}</span>]
				<br>You can choose a few options here.
				<br>					
				<br><label><input type='radio' name='setup_mode' value='0'".v($opt[0])."> New installation</label>
				<br><span class='fonts'>Perform a fresh install of the board database and write a new configuration file.</span>
				<br>
				<br><label><input type='radio' name='setup_mode' value='1'".v($opt[1]).(INSTALLED && updates_available() ? "" : " readonly disabled")."> Perform upgrade</label>
				<br><span class='fonts'>Updates the board configuration. Only available when new update scripts are found in the update folder.</span>
				<br>
				<br><label><input type='radio' name='setup_mode' value='2'".v($opt[2]).(INSTALLED && !updates_available() ? "" : " readonly disabled")."> Change configuration</label>
				<br><span class='fonts'>Only updates the configuration file. Available when the board is already installed and updated.</span>ducks";
		break;
	default:
		$modes = ["install", "update", "config"];
		if (!isset($modes[$_POST['setup_mode']]))
			die("Invalid mode.");
		require "install/index_{$modes[$_POST['setup_mode']]}.php";
}

// Disable next button for errors
if ($error) {
	$btn &= ~BTN_NEXT;
}


if (isset($_GET['ajax'])) {
	die(json_encode(['title' => $windowtitle, 'text' => $output, 'btn' => $btn, 'vars' => $_POST]));
}

setupheader($windowtitle, $btn);
print save_vars($_POST);
print $output;
setupfooter($btn);