<?php
if (!defined('INSTALL_FILE')) die;

$_POST['noimport'] = filter_int($_POST['noimport']);

if ($step >= BASE_STEP + 1) {
	$dbinfo = $_POST['config']['sqlconfig'];
	$sql = new mysql_setup();
	// Attempt connection to the SQL server
	// this should not fail
	if (!$sql->connect($dbinfo['sqlhost'], $dbinfo['sqluser'], $dbinfo['sqlpass'])) {
		$windowtitle = "SQL connection error";
		$output = "
		<span class='c-error'>Couldn't connect to the MySQL server.</span>
		<br>
		<br>Reason: <span class='c-highlight'>".$sql->error."</span>";
		$error = true;
	} else if (!trim($dbinfo['dbname'])) {
		$windowtitle = "Uh no";
		$output = "
		<span class='c-highlight'>Successfully connected to the SQL server...</span>
		<br>
		<br>...but you didn't specify a database name.
		<br>";
		$error = true;
	} else {
		$db_exists = $sql->selectdb($dbinfo['dbname']);
	}
}

if (!$error) {
	switch ($step) {
		case BASE_STEP + 0:
			$windowtitle = "Server Credentials";
			$output = "";
			if (INSTALLED) {
				$output .= "
				<span class='c-error'>WARNING:</span>
				<br><span class='c-error'>You are trying to perform a fresh install of the board over an existing installation.</span>
				<br>This isn't supported (yet), so bugs may occur.
				<br>
				<br>"; 
			}
			$output .= "
			Please enter the SQL credentials.
			<br>The installer will attempt to connect to the specified server on the next page.
			<br>
			<br>Warning: the data in the database you choose will be deleted.
			<br>
			<br>
			".setup_get_config_layout(GCL_SQL);
			break;
			
		case BASE_STEP + 1:
			$windowtitle = "Connection";
			if ($db_exists) {
				$output = "
				<span class='c-success'>Successfully connected to the SQL server.</span>
				<br>
				<br>The database you've selected already exists.
				<br>
				<br>Remember that any data in the database will be <span class='c-error'>permanently deleted</span>.
				";
			} else {
				$output = "
				<span class='c-success'>Successfully connected to the SQL server.</span>
				<br>
				<br><span class='c-highlight'>The database you've selected doesn't exist.</span>
				<br>
				<br>The installer will try to create the database if possible. Chances are, however, that it won't have the permissions to do so.
				<br>You'll probably need to create the database manually.				
				";
			}
			$output .= "<br><br>Click '<span class='c-info'>Next</span>' to start editing the configuration options.";
			break;
		case BASE_STEP + 2:
			$windowtitle = "Board Configuration";
			$output = "
			You can edit a few board options here.
			<br>These options will be written in the file <span class='c-highlight'>'".CONFIG_PATH."'</span>.
			<br>
			<br>You can edit these options later through the <span class='c-info'>Change configuration</span> option (or by editing the config file manually, like it's traditionally done).
			<br>
			<br>
			".setup_get_config_layout(GCL_CONFIG);
			break;
			
		case BASE_STEP + 3:
			$windowtitle = "Ready";
			$output = "
				The board will now be installed.
				<br>
				<br>You can go back to review the choices, or click <span class='c-info'>'Next'</span> to start the installation.
				<br>
				<br>
				<br><span class='c-error'>NOTE</span>
				<br><span class='c-highlight'>It may be possible for the SQL queries in <span class='c-info'>install.sql</span> to take a long time.</span>
				<br><span class='c-highlight'>If that happens, the browser may time out the connection automatically.</span>
				<br>
				<br>If the page stops loading (browser time out), you can try this option:
				<br><label><input type='checkbox' name='noimport' value='1'".($_POST['noimport'] ? " checked" : "")."> Skip importing install.sql</label>
				<br>
				<br>If you choose to skip the phase, you will need to manually import the SQL file
				<br>under a tool like PhpMyAdmin.				
				";
			break;
		case BASE_STEP + 4:
			$windowtitle = "Installing";
			$output = "<span style='text-align: left'><pre>";

			// Setup for misc crap
			$output .= "Starting the install process...";
			set_time_limit(0);
			$btn &= ~BTN_NEXT;
			
			// Here we go
			if (!$db_exists) {
				$output .= "\nCreating the database '<span class=\"c-info\">".htmlspecialchars($dbinfo['dbname'])."</span>'...";
				try {
					$sql->query("CREATE DATABASE `".str_replace('`', '``', $dbinfo['dbname'])."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
					$output .= checkres(true);
				}
				catch (PDOException $x){
					// This is the big point of failure, when you don't have permissions to create the database
					// Stop before writing to the config file (which marks the board as installed)
					$output .= checkres(false);
					if ($x->getCode() == 42000) {
						$output .= "\nAccess denied. You will have to create the database manually.";
					} else {
						$output .= "\nDatabase creation error: ".$x->getMessage();
					}
					$output .= "</pre></span>";
					break;
				}
			}
			
			// Before attempting to do anything, actually select the database for real
			$output .= "\nConnecting to the database...";
			$dbok = $sql->selectdb($dbinfo['dbname']);
			$output .= checkres($dbok);
			if (!$dbok) {
				$output .= "\Could not connect to the database '".htmlspecialchars($dbinfo['dbname'])."'.";
				$output .= "</pre></span>";
				break;
			}
			if (!$_POST['noimport']) {
				$output .= "\nImporting SQL files...";
				$sql->import("install/install.sql");
				$output .= checkres(!$sql->errors);
				
				if ($sql->errors) {
					$output .= "\n".$sql->errors." queries have failed<!-- likely because whoever made install.sql fucked up -->.\nBroken queries:\n\n";
					$output .= implode("\n", $sql->q_errors);
					$output .= "\nIf you would like to retry, you can return to the previous page and try again.";
					$output .= "</pre></span>";
					break;
				}
			}
			
			// Save the current db version as we're doing a clean install
			$output .= "\nWriting version marker to ".DBVER_PATH."...";
			$res = file_put_contents(DBVER_PATH, get_available_db_version());
			$output .= checkres($res);
			
			// As the very last thing, write the config
			$output .= "\nWriting settings to ".CONFIG_PATH."...";
			$configfile = setup_generate_config();
			$res = file_put_contents(CONFIG_PATH, $configfile);
			$output .= checkres($res);
			
			// Write the default extension settings too, to avoid having to parse the settings json on every page
			$exts = ext_get_all_names();
			foreach ($exts as $xname) {
				$output .= "\nWriting extension settings for '{$xname}'...";
				$xconf = ext_read_config($xname, true); // required for setup_generate_ext_config
				setup_generate_ext_config($xname);
				$output .= checkres(true);
			}

			
			$output .= "\nOperation completed successfully!\n";
			if (!$_POST['noimport']) {
				$output .= "\nYou can now register <a href='../register.php'>here</a>.";
			} else {
				$output .= "\nHowever, the board isn't fully installed.";
				$output .= "\nTo finish the installation, you need to manually import <span class='c-info'>install.sql</span>\nin a tool such as <span class='c-info'>PhpMyAdmin</span>.";
			}
			
			$btn &= ~BTN_PREV;
			$output .= "</pre></span>";
			break;
	}
}