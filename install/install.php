<?php
/*
	An installer...
	...with the layout based from the installer in AB 1.92.08
		
	as usual, this file trusts you to not be an idiot (ie: filling in invalid board options)
*/

// 'The required libraries have not been defined'
if (!defined('OK_INSTALL')) {
	return header("Location: ./");
}

chdir("..");
require "install/function.php";
require "install/layout.php";
require "lib/defines.php";

const PAGE_HEADER = "Acmlmboard Installer";
const PAGE_FOOTER = "Acmlmboard Installer v1.7 (13-07-19)";

const RECONFIG      = true;
const VERSION_LOCK  = true;

const STEP_INTRO    = 0;
const STEP_SQLAUTH  = 1;
const STEP_SQLDB    = 2;
const STEP_CHECKDB  = 3;
const STEP_CONFIG   = 4;
const STEP_REVIEW   = 5;
const STEP_INSTALL  = 6;

define('PRIVATE_BUILD', file_exists("lib/firewall.php"));
define('INSTALLED',     file_exists(CONFIG_PATH));

$error = false;

// Grab defaults if they exist
if (INSTALLED) {
	require_once CONFIG_PATH;
	// to save the custom config options
	$_POST['inptconf'] = config_from_update()[1];
} else {
	$config['enable-sql-debugger'] = true;
}
require "lib/classes/mysql.php";
require "install/mysql_plus.php";

if (!isset($_POST['sqlhost'])) 	$_POST['sqlhost'] 	= filter_string($sqlhost);
if (!isset($_POST['sqlpass'])) 	$_POST['sqlpass'] 	= filter_string($sqlpass);
if (!isset($_POST['sqluser'])) 	$_POST['sqluser'] 	= filter_string($sqluser);
if (!isset($_POST['dbname'])) 	$_POST['dbname'] 	= filter_string($dbname);

$_POST['dropdb'] = filter_int($_POST['dropdb']);
$_POST['__chconfig'] = filter_bool($_POST['__chconfig']);

// Reconfig. option
$firststep = $_POST['__chconfig'] ? STEP_CONFIG : STEP_INTRO;
setupheader(PAGE_HEADER, "./", $firststep);

// When the board is already installed and versions don't match, warn the user
if (INSTALLED && VERSION_LOCK && get_available_db_version() > get_current_db_version()) {
	$error = true;
	$buttons = 0;
	?>
	<span class="highlight">Updates are available</span>
	<br>
	<br>There are pending updates to the database version.
	<br>You cannot use the installer until the database version is updated to the newest one.
	<br>
	<br>Click <a href="update.php">here</a> to run upgrade tool, then return to the installer.
	<br>
	<?php
}

// DB Connection starts from certain steps (auto-handles errors)
if ($_POST['step'] > STEP_SQLAUTH) {
	$sql = new mysql_plus;
	if (!$_POST['sqlhost'] || !$_POST['sqluser']) 
		$error = "Required fields missing.";
	else if (!$sql->connect($_POST['sqlhost'], $_POST['sqluser'], $_POST['sqlpass']))
		$error = $sql->error;
	
	if ($error) {
		$buttons = BTN_PREV;
		?>
		<span class='warn'>
			Error!<br>
			Couldn't connect to the MySQL server
		</span>
		<br>
		<span style='background: #000'><?= $error ?></span>
		<br>
		<br>Return to the previous page and enter correct login credentials to the SQL server.
		<br>
		<?php
	}
}
if ($_POST['step'] > STEP_SQLDB) {
	if (!$_POST['dbname']) {
		$error   = true;
		$buttons = BTN_PREV;
		?>
		<span class='warn'>Error!</span>
		<br>
		<span style='background: #000'>No database selected.</span>
		<br>
		<br>Return to the previous page and enter the correct database name.
		<br>
		<?php
	} else {
		$db  = $sql->selectdb($_POST['dbname']);
	}
}


if (!$error) {
	switch ($_POST['step']) {

		case STEP_INTRO:
			$whatbuild = PRIVATE_BUILD 
				? "As this is an internal version, please...<div class='warn'>DO NOT DISTRIBUTE !!</div>" 
				: "";
				
			?>
				Welcome to the Acmlmboard installer.
			<br><?= $whatbuild ?>
			<br>Please report all bugs to Kak or the <a href="https://github.com/Kak2X/jul/issues">Bug Tracker</a>.
			<br>
			<br>You will be prompted to enter the SQL database credentials in the next page.
			<br>
			<?php	
		break;
	
		case STEP_SQLAUTH:
			?>
			Please enter the SQL credentials.
			<br>The installer will attempt to connect to the specified server on the next page.
			<br>
			<br>
			<center>
			<table>
				<!-- autocomplete prevention -->
				<input style='display:none' type='text'     name='__f__usernm__'>
				<input style='display:none' type='password' name='__f__passwd__'>
				<tr>
					<td class='tdbg1'>SQL Host:</td>
					<td class='tdbg1'><input type='text' name='sqlhost' value="<?= htmlspecialchars($_POST['sqlhost']) ?>"></td>
				</tr>
				<tr>
					<td class='tdbg1'>SQL User:</td>
					<td class='tdbg1'><input type='text' name='sqluser' value="<?= htmlspecialchars($_POST['sqluser']) ?>"></td>
				</tr>
				<tr>
					<td class='tdbg1'>SQL Password:</td>
					<td class='tdbg1'><input type='password' name='sqlpass' value="<?= htmlspecialchars($_POST['sqlpass']) ?>"></td>
				</tr>
			</table>
			</center>
			<?php
			break;
	
	
		case STEP_SQLDB:	
			?>
				The connection was successful!
			<br>
			<br>Enter the name of the database you're going to use.
			<br>If it doesn't exist it will be created.
			<br>
			<br>NOTE: Creating a database will probably require root privileges, so it's recommended to specify an already existing empty database.
			<center>
			<table>
				<tr>
					<td class='tdbg1'>SQL Database:</td>
					<td class='tdbg1'><input type='text' name='dbname' value="<?= htmlspecialchars($_POST['dbname']) ?>"></td>
				</tr>
			</table>
			<?php
			break;
	

		case STEP_CHECKDB:		
			if ($db) {
				// DO YOU WANT TO DROP?!?!
				$dropdbsel[$_POST['dropdb']] = "checked";
				
				?>
				The database already exists. Select an action.
				<br>
				<center>
				<table class='sel'>
					<tr>
						<td><input type='radio' name='dropdb' value=0 <?= filter_string($dropdbsel[0]) ?>></td>
						<td>Use the existing database and update configuration</td>
					</tr>
					<tr>
						<td><input type='radio' name='dropdb' value=1 <?= filter_string($dropdbsel[1]) ?>></td>
						<td>Drop the database and reinstall</td>
					</tr>
					<tr>
						<td><input type='radio' name='dropdb' value=2 <?= filter_string($dropdbsel[2]) ?>></td>
						<td>Use the existing database and reinstall</td>
					</tr>
				</table>
				</center>
				<div class='warn'>
					WARNING: DROPPING THE DATABASE WILL PERMANENTLY DELETE ALL THE DATA!<br>
					IF YOU DON'T KNOW WHAT YOU'RE DOING MAKE SURE TO HAVE BACKUPS
				</div>
				<br>
				<?php	
			} else {
				?>
				The database '<?= htmlspecialchars($_POST['dbname']) ?>' you have specified doesn't seem to exist.
				<br>It will be created before importing the .SQL file.
				<br>
				<span class='highlight'>
					NOTE: The SQL user must have permissions to create tables, otherwise this won't work.
				</span>
				<br>
				<br>If this is correct you can continue; otherwise check the SQL Connection details.
				<?php
			}
			
		break;

		case STEP_CONFIG:		
			?>
			Board Options
			<br>Fill in the table. These options will be written in <span class='highlight'>'<?= CONFIG_PATH ?>'</span>
			<br>Please <i>be careful</i> and check the options before continuing.
			<br>
			<center>
			<table style='padding: 20px'>
				<tr><td style="width: 400px; display:block"></td><td></td></tr>
			<?php
				
				// too long to fit it in
				$defaultaffiliates = 
'<optgroup label="Forum affiliates">
	<option value="about:blank" selected="">something</option>
</optgroup>
<optgroup label="Other Acmlmboards">
	<option value="about:blank">just so you know</option>
	<option value="about:blank">usually there are</option>
	<option value="about:blank">no affiliates</option>
	<option value="about:blank">so you can remove this</option>
</optgroup>';
				
				$configvar = "config";
				print "
				".set_heading('Layout options')."
				".set_input(1,"board-name"      , "Board name"      , 250, "Not Jul")."
				".set_input(1,"board-title"     , "Header HTML"     , 550, "<img src='images/pointlessbannerv2.png' title='The testboard experience'>")."
				".set_input(1,"title-submessage", "Staff Submessage", 550, "")."
				".set_input(1,"board-url"       , "Header Link"     , 350, "http://localhost/board/")."
				".set_input(1,"footer-title"    , "Footer Text"     , 250, "The Internet")."
				".set_input(1,"footer-url"      , "Footer Link"     , 350, "http://localhost/")."
				".set_input(1,"admin-name"      , "Admin name"      , 250, "(admin name)")."
				".set_input(1,"admin-email"     , "Support email"   , 250, "herp@derp.welp")."
				".set_radio(2,'show-ikachan'    , 'Show Ikachan'    , 'No|Yes', 0)."
				".set_textarea(1,'affiliate-links' , 'Affiliates (HTML)' , STA_RESIZE, $defaultaffiliates)."
			
				".set_heading("Board options")."			
				".set_radio(2,'allow-thread-deletion' , 'Allow thread deletion'  , 'No|Yes'      , 0)."
				".set_radio(2,'allow-post-deletion'   , 'Allow post deletion'    , 'No|Yes'      , 0)."
				".set_radio(2,'enable-ratings'        , 'Enable user ratings'    , 'No|Yes'      , 0)."
				".set_radio(2,'enable-post-ratings'   , 'Enable post ratings'    , 'No|Yes'      , 0)."
				".set_radio(2,'onlineusers-on-thread' , 'Online users bar detail', 'Forum|Thread', 0)."
				".set_radio(2,'allow-pmthread-edit'   , 'Allow PM editing'       , 'No|Yes'      , 0)."
				".set_input(0,'pmthread-dest-limit'   , 'Max partecipants/conv.' , 50, 4)."
				".set_input(0,'pmthread-folder-limit' , 'Max PM folders/user'    , 50, 4)."
				".set_powl( 0,'view-super-minpower'   , 'Powerlevel required to view Normal+ users', 1)."
				
				".set_heading("Attachments")."	
				".set_radio(2,'allow-attachments'     , 'Enable attachments' , 'Disable|Enable', 0)."
				".set_radio(2,'attachments-all-origin', 'Bypass origin check', 'No|Yes', 0)."
				".set_radio(2,'hide-attachments'      , 'Hide attachments'   , 'No|Yes', 0)."
				".set_input(0,'attach-max-size'       , 'Max attachment size', 100, 2 * 1048576, 'bytes')."
				
				".set_heading("File uploader")."	
				".set_radio(2,'allow-uploader'          , 'Enable file uploader' , 'Disable|Enable', 0)."
				".set_radio(2,'uploader-all-origin'     , 'Bypass origin check'  , 'No|Yes', 0)."
				".set_input(0,'uploader-max-file-size'  , 'Max upload size'      , 100, 10 * 1048576, 'bytes')."
				".set_radio(2,'uploader-allow-file-edit', 'Allow normal users to edit uploads', 'Disable|Enable', 0)."
				
				".set_heading("Avatars")."	
				".set_radio(2,'allow-avatar-storage'  , 'Enable avatar upload' , 'Disable|Enable', 0)."
				".set_input(0,'avatar-limit'          , 'Avatar limit/user'    ,  50,         50)."
				".set_input(0,'max-minipic-size-x'    , 'Minipic max width'    ,  50,         16, 'px')."
				".set_input(0,'max-minipic-size-y'    , 'Minipic max height'   ,  50,         16, 'px')."
				".set_input(0,'max-minipic-size-bytes', 'Max minipic size'     , 100,  1024 * 20, 'bytes')."
				".set_input(0,'max-avatar-size-x'     , 'Minipic avatar width' ,  50,         70, 'px')."
				".set_input(0,'max-avatar-size-y'     , 'Minipic avatar height',  50,         70, 'px')."
				".set_input(0,'max-avatar-size-bytes' , 'Max avatar size'      , 100, 1024 * 300, 'bytes')."
				
				".set_heading("Defaults")."
				".set_input(0,'server-time-offset', 'Server time offset'       , 150, 0, 'seconds')."
				".set_input(1,'default-dateformat', 'Default date format'      , 150, 'm-d-y h:i:s A')."
				".set_input(1,'default-dateshort' , 'Default short date format', 150, 'm-d-y')."
				".set_input(0,'default-ppp'       , 'Default posts per page'   ,  50, 20, "posts")."
				".set_input(0,'default-tpp'       , 'Default threads per page' ,  50, 50, "threads")."
				
				".set_heading("Security")."	
				".(PRIVATE_BUILD ? set_radio(2,'enable-firewall', 'Enable firewall', 'Disable|Enable', 1) : "")."
				".set_input(1,'salt-string', 'Salt string', 175, 'change me!')."
				".set_radio(2,'force-lastip-match', 'Always log out on IP changes', 'No|Yes', 0)."
				".set_radio(2,'no-curl', 'Bypass proxy checks', 'No|Yes/Free host', 0)."
				
				".set_heading("IRC settings")."
				".set_radio(2,'irc-reporting'   , 'Enable IRC reporting'        , 'No|Yes', 1)."
				".set_input(1,'irc-server-title', "IRC Server title"            , 250, "A sample IRC server")."
				".set_input(3,'irc-servers'     , "IRC Servers (separated by ;)", 470, ["irc.sample.net","irc.test.com"])."
				".set_input(3,'irc-channels'    , "IRC channel list"            , 470, ["#xtest","#ytest"])."
				
				".set_heading("News engine")."
				".set_radio(2,'enable-news', 'Enable news', 'Disable|Enable', 0)."
				".set_input(1,"news-name", "News page name", 300, "News")."
				".set_input(1,"news-title", "News Header HTML", 500, "News page")."
				".set_input(0,'max-preview-length', 'Character limit in preview', 40, 500)."
				".set_powl(0,'news-write-perm', 'Powerlevel required to add news', 1)."
				".set_powl(0,'news-admin-perm', 'Powerlevel required to moderate news', 3)."
				
				".set_heading("Advanced (Do not change if you don't know what you're doing)")."
				".set_input(0,'trash-forum'       , 'Trash forum ID'       , 50, 3)."
				".set_input(0,'announcement-forum', 'Announcement forum ID', 50, 4)."
				".set_input(0,'deleted-user-id'   , 'Deleted user ID'      , 50, 2)."
				".set_input(1,'backup-folder'     , 'Backup folder (relative to board root)', 250, 'backups')."
				".set_input(0,'backup-threshold'  , 'Backup "New" threshold', 50, 15, 'days')."
				
				".set_heading("Development Options")."
				".set_radio(2,'always-show-debug'  , 'Always enable debuggers'   ,'No|Yes',1)."
				".set_radio(2,'allow-debug-dump'   , 'Allow mysqldump'           ,'No|Yes',1)."
				".set_input(0,'force-user-id'      , 'Force user ID'             , 60, 0)."					
				".set_radio(2,'allow-rereggie'     , 'Allow re-registrations'    ,'No|Yes',1)."
				".set_radio(2,'no-redirects'       , 'Disable META redirects'    ,'No|Yes',1)."
				".set_radio(2,'compat-test'        , 'Enable compatibility layer','No|Yes',1);
				
				$configvar = "hacks";
				print "
				".set_heading("Extra set #1")."
				".set_radio(2,'comments', 'Always show HTML comments'  , 'No|Yes', 0)."
				".set_radio(2,'noposts' , 'Hide postcounts (partially)', 'No|Yes', 0)."
				".set_radio(2,'password_compatibility', 'Auto-convert MD5 password to BCRYPT (disabled)', 'No|Yes', 0);
				
				$configvar = "x_hacks";
				print "
				".set_heading("Extra set #2")."
				".set_radio(2,'host'        , 'Alternate board mode'        , 'No|Yes', 0)."
				".set_input(1,'adminip'     , 'Sysadmin IP'                 ,      150, '127.0.0.1')."
				".set_input(1,'mmdeath'     , 'Doom timer (disabled)'       ,      150, '-1')."
				".set_radio(2,'rainbownames', 'Always use rainbow usernames', 'No|Yes', 0)."
				".set_radio(2,'superadmin'  , 'Admin board'                 , 'No|Yes', 0)."
				".set_radio(2,'smallbrowse' , 'Force mobile mode'           , 'No|Yes', 0)."
				";
			?>
			</table>
			</center>
			<?php
			break;
		
		case STEP_REVIEW:
		
			if (!$_POST['__chconfig']) {
			?>
				The board will now be configured.
				<div class='warn'>WARNING: IF YOU HAVE SELECTED TO DROP THE DATABASE, ALL DATA WILL BE DELETED</div>
				<br>
				<br>You can go back to review the choices, or click <span class='highlight'>'Next'</span> to start the installation.
				<br>
				<br>
				<?php
			} else {
				?>
				The board will now be configured.
				<br>
				<br>You can go back to review the choices, or click <span class='highlight'>'Next'</span> to save the changes.
				<br>
				<br>
				<?php
			}
			break;
	
		case STEP_INSTALL:
		
			$buttons = BTN_PREV;
			
			set_time_limit(0);
			
			//	Here we go
			
			print "<span style='text-align: left'><pre>";
			print "Attempting to install...";
			
			// Save the current db version on clean install
			// (after this, only update.php should touch the file)
			if (!INSTALLED) {
				file_put_contents(DBVER_PATH, get_available_db_version());
			}
			
			$configfile = generate_config(config_from_install());

			print "\nWriting settings to ".CONFIG_PATH."...";
			$res = file_put_contents(CONFIG_PATH, $configfile);
			print checkres($res);
			
			if ($_POST['__chconfig']) { // we are done here
				print "\nOperation completed successfully.\n";
				print "Click <a href='../admin.php'>here</a> to return to the admin control panel.";
				$buttons = 0;
				break;
			}
			
			// Decided to drop the database?
			if ($db && $_POST['dropdb'] == 1) {
				print "Dropping database `{$_POST['dbname']}`...\n";
				$sql->query("DROP DATABASE IF EXISTS `{$_POST['dbname']}`");
			}
			
			
			// If the database already exists, well, nothing actually happens.
			// So we do not bother checking that
			print "\nAttempting to create database if it doesn't exist...";
			try{
				$sql->query("CREATE DATABASE IF NOT EXISTS `{$_POST['dbname']}`; DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
				print checkres(true);
			}
			catch (PDOException $x){
				print checkres(false);
				if ($x->getCode() == 42000) {
					print "\nAccess denied. You have to create the database manually under phpMyAdmin.";
				} else {
					print "\nDatabase creation error: ".$x->getMessage();
				}
				break;
			}
		
		
			// Before attempting to do anything, actually select the database for real
			print "Attempting to connect to the database...";
			if (!$sql->selectdb($_POST['dbname'])) {
				print checkres(false);
				print "\Could not connect to the database '{$_POST['dbname']}'.";
				break;
			}
			
			// Only import if the database doesn't exist or we've dropped it (option 0 preserves it)
			if (!$db || $_POST['dropdb']) {
				print "\nImporting SQL files...";
				$sql->import("install/install.sql");			
			}

			
			
			
			if (!$sql->errors){
				print checkres(true);
				print "\nOperation completed successfully.\n";
				print "You can (and <i>should</i>) delete this file and register <a href='../register.php'>here</a>.";
				$buttons = 0;
			
			} else {
				print checkres(false);
				print "\n".$sql->errors." queries have failed.\nBroken queries:\n\n";
				print implode("\n", $sql->q_errors);
				print "\nPlease fix the problems that have occured. This may require dropping the partially-created tables, and trying again.";
				print "\n<span class='highlight'>NOTE:</span> it is possible the installation was still successful, especially if you have only received '<span class='warn'>Table already exists</span>' errors.";
				print "\nHowever, it is far more likely you will need to redo the installation.";
				print "\nIf you would like to retry, you can return to the previous page and try again.</pre></span>";
			}
			break;
		
		
	}
}

setupfooter(PAGE_FOOTER, $buttons);