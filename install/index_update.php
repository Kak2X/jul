<?php
if (!defined('INSTALL_FILE')) die;

if (!INSTALLED) {
	$windowtitle = "Not installed.";
	$output = "
	<span class='c-error'>You can only perform this action if the board is already installed.</span>";
	$error = true;
} else if (!updates_available()) {
	$windowtitle = "Updater";
	$output = "
	<span class='c-info'>The board structure is already updated.</span>
	<br>
	<br>You can return to the previous page.";
	$error = true;
}

if (!$error) {
	define('CURRENT_VERSION', get_current_db_version());
	define('AVAILABLE_VERSION', get_available_db_version());

	require "install\schema.php";
	require "lib\defines.php";
	require "lib\config.php";
	require "lib\classes\mysql.php";

	switch ($_POST['step']) {
		case BASE_STEP + 0:
			$windowtitle = "Board Configuration";
			$output = "
			This option will update your database, configuration file and folder structure to the latest version.
			<br>
			<br>
			<table class='table' style='margin: auto'>
				<colgroup>
					<col>
					<col style='width: 40px'>
				</colgroup>
				<tr><td class='tdbgh center b' colspan='2'>Version information</td></tr>
				<tr>
					<td class='tdbg1 center b'>Current version:</td>
					<td class='tdbg2 center c-info'>".CURRENT_VERSION."</td>
				</tr>
				<tr>
					<td class='tdbg1 center b'>Latest version:</td>
					<td class='tdbg2 center c-success'>".AVAILABLE_VERSION."</td>
				</tr>
			</table>
			<br>
			".get_version_table()."
			<br>Click '<span class='ok'>Next</span>' to start the upgrade process.
			";
			break;
			
		case BASE_STEP + 1:
			$windowtitle = "Upgrade Utility";
			$btn &= ~BTN_NEXT;
			
			$output = "
			Executing the upgrade steps...
			<br>";
			
			// Update scripts use the old-style printf way (and will continue to do so)
			// Buffer everything so we can have it appended to $output
			ob_start();
			
			for ($i = CURRENT_VERSION + 1; $i <= AVAILABLE_VERSION; ++$i) {
				print "<br><b>Revision {$i}</b>";
				reset_update_step();
				require "update/{$i}.php";
			}
			
			$output .= ob_get_contents()."
			<br>
			<br>Saving the configuration...";
			
			ob_end_clean();
			
			// Overwrite the config file				
			$configfile = generate_config(true); 
			$res = file_put_contents(CONFIG_PATH, $configfile);
			
			// Save the version info
			$res2 = file_put_contents(DBVER_PATH, AVAILABLE_VERSION);
			
			$output .= checkres($res && $res2)."
			<br>
			<br>Done!
			<br>
			<br>Click <a href='..'>here</a> to return to the board";
			
			$btn &= ~BTN_PREV;
			break;
	}
}

function get_version_table() {
	$out = "
	<table class='table version-table' style='margin: auto'>
		<colgroup>
			<col style='width: 40px'>
			<col>
		</colgroup>
		<tr><td class='tdbgh center b' colspan='2'>Summary of actions</td></tr>";
		
	for ($i = CURRENT_VERSION + 1; $i <= AVAILABLE_VERSION; ++$i) {
		$out .= "
		<tr>
			<td class='tdbg1 center vatop nobr b'>Revision {$i}</td>
			<td class='tdbg2'>".file_get_contents("update/{$i}.dat")."</td>
		</tr>";
	}
	
	$out .= "
	</table>";
	
	return $out;
}