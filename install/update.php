<?php

const PAGE_HEADER = "Upgrade Utility";
const PAGE_FOOTER = "Upgrade Utility v1.0 (13-07-19)";

const STEP_INTRO = 0;
const STEP_PASSWORD = 1;
const STEP_OVERVIEW = 2;
const STEP_UPGRADE = 3;

//-----------------

chdir("..");

require "lib/defines.php";
require "lib/config.php";
require "lib/classes/mysql.php";
require "install/function.php";
require "install/layout.php";
require "install/mysql_plus.php";

if (!file_exists(CONFIG_PATH))
	die("You can't execute the db structure update tool if the board isn't installed.");

//-----------------
$error = false;

$sql = new mysql;
$sql->connect($sqlhost, $sqluser, $sqlpass, $dbname) or
	die("Couldn't connect to the MySQL server.<hr/>". $sql->error);

$banned = $sql->fetchq("SELECT ip, reason FROM ipbans WHERE INSTR('{$_SERVER['REMOTE_ADDR']}',ip) = 1");
if ($banned)
	die("You are IP banned.<br><br>Reason: {$banned['reason']}");

//-----------------



$_POST['user'] 	= filter_string($_POST['user']);
$_POST['pass'] 	= filter_string($_POST['pass']);

// Determine the db version this codebase expects and the current one
define('AVAILABLE_VERSION', get_available_db_version());
define('CURRENT_VERSION', get_current_db_version());

setupheader(PAGE_HEADER, "?");

// Special
if (CURRENT_VERSION >= AVAILABLE_VERSION) {
	$buttons = 0;
	$error = true;
	?>
		The board structure is already updated.
		<br>
		<br>Click <a href="..">here</a> to return to the board.
	<?php
} else if ($_POST['step'] > STEP_PASSWORD) {
	// Required login step
	$userid = checkuser($_POST['user'], $_POST['pass']);
	if ($userid == -1) {
		$error = true;
		
		// give some bit of leeway if the fields weren't filled in
		if ($_POST['user'] && $_POST['pass'] && loginfail()) {
			$buttons = 0;
			?>
			<span class='warn'>
				Error!<br>
				Incorrect credentials.
			</span>
			<br>
			<br><span class="warn">You are now IP banned for too many failed login attempts.</span>
			<br>
			<br>
			<?php
			
		} else {
			$buttons = BTN_PREV;
			?>
			<span class='warn'>
				Error!<br>
				Incorrect credentials.
			</span>
			<br>
			<br>Return to the previous page and enter correct login credentials.
			<br>
			<?php
		}
	} else {
		$user = load_user($userid);
		if ($user['powerlevel'] < PWL_SYSADMIN) {
			$buttons = BTN_PREV;
			$error = true;
			?>
			<span class='warn'>
				Error!
			</span>
			<br>
			<br>This account is not a super administrator.
			<br>
			<?php
		}

	}

}


if (!$error) {
	switch ($_POST['step']) {
		case STEP_INTRO:
			?>
				Welcome to the Board Upgrade utility.
				<br>
				<br>This will update your database, configuration file and folder structure
				<br>to the latest version.
				<br>
				<br>You will be prompted to enter your admin credentials in the next page.
				<br>
			<?php
			break;
		case STEP_PASSWORD:
			?>
			Enter the credentials for a board account with the '<span class="highlight"><?= $pwlnames[PWL_SYSADMIN] ?></span>' power level.
			<br>Typically this matches with the first registered user.
			<br>
			<br>
			<center>
			<table>
				<!-- autocomplete prevention -->
				<input style='display:none' type='text'     name='__f__usernm__'>
				<input style='display:none' type='password' name='__f__passwd__'>
				<tr>
					<td class='tdbg1'>Username:</td>
					<td class='tdbg1'><input type='text' name='user' value="<?= htmlspecialchars($_POST['user']) ?>"></td>
				</tr>
				<tr>
					<td class='tdbg1'>Password:</td>
					<td class='tdbg1'><input type='password' name='pass' value="<?= htmlspecialchars($_POST['pass']) ?>"></td>
				</tr>
			</table>
			</center>
			<?php
			break;
		case STEP_OVERVIEW:
			?>
			<br/>
			<center>
				<table>
					<tr>
						<td>You are currently on version</td>
						<td>:</td>
						<td class="highlight center" style="width: 40px"><?= CURRENT_VERSION ?></td>
					</tr>
					<tr>
						<td>The latest available version is</td>
						<td>:</td>
						<td class="ok center"><?= AVAILABLE_VERSION ?></td>
					</tr>
				</table>
			</center>
			<br/>
			<table class="table update-table">
			<?php
				print set_heading("Summary of actions");
				for ($i = CURRENT_VERSION + 1; $i <= AVAILABLE_VERSION; ++$i) {
					print set_text("Revision {$i}", file_get_contents("update/{$i}.dat"), "vatop", "left");
				}
			?>
			</table>
			
			<br/>Click '<span class="ok">Next</span>' to start the upgrade process.
			
			<br/>
			<?php		
			break;
		case STEP_UPGRADE:
			$buttons = 0;
			?>
			<div class="left">
				Executing the upgrade steps...
				<br/>
				<?php
					for ($i = CURRENT_VERSION + 1; $i <= AVAILABLE_VERSION; ++$i) {
						print "<br><b>Revision {$i}</b>";
						require "update/{$i}.php";
					}
				?>	
				<br/>
				<br/>Saving the configuration...
				<?php
					// Overwrite the config file				
					$configfile = generate_config(config_from_update()); 
					file_put_contents(CONFIG_PATH, $configfile);
					
					// Save the version info
					file_put_contents(DBVER_PATH, AVAILABLE_VERSION);

					
					print checkres(true);
				?>
				<br/>
				<br/>Done!
				<br/>
				<br/>Click... 
				<br/> -> <a href="..">here</a> to return to the board
				<br/> -> <a href="install.php">here</a> to go to the installer
			</div>
			<?php

			break;
	}
}

setupfooter(PAGE_FOOTER, $buttons);

//----------------------------------------
// TODO: When the common board code gets split from lib/function.php, you can use the normal checkuser function
//----------------------------------------

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