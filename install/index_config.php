<?php
if (!defined('INSTALL_FILE')) die;

if (!INSTALLED) {
	$windowtitle = "Not installed.";
	$output = "
	<span class='c-error'>You can only perform this action if the board is already installed.</span>";
	$error = true;
} else if (updates_available()) {
	$windowtitle = "Updates are available.";
	$output = "
	<span class='c-error'>You can only perform this action if the board configuration is updated.</span>";
	$error = true;
}

if (!$error) {
	switch ($step) {
		case BASE_STEP + 0:
			$windowtitle = "Board Configuration";
			$output = "
			You can change your configuration options here.
			<br>These will be written in the file <span class='c-highlight'>'".CONFIG_PATH."'</span>.
			<br>
			<br>You can edit these options later through the <span class='c-info'>Change configuration</span> option (or by editing the config file manually, like it's traditionally done).
			<br>
			<br>
			".get_config_sql_layout()."
			<br>
			<br>
			".get_config_layout();
			break;
			
		case BASE_STEP + 1:
			// Verify the validity of the SQL connection options before continuing
			// after this, we don't need it anymore, so we can close it manually
			$sql = new mysql();
			$dbinfo = $_POST['config']['__sql'];
			$validconn = $sql->connect($dbinfo['sqlhost'], $dbinfo['sqluser'], $dbinfo['sqlpass'], $dbinfo['dbname']);
			
			$output = "
				The board will now be configured.
				<br>";
			if ($validconn) {
				$windowtitle = "Ready";
			} else {
				$windowtitle = "Incorrect SQL credentials";
				$output .= "
				<br><span class='c-error'>However, there have been connection errors when verifying the SQL credentials:</span>
				<br>Details: <span class='c-highlight'>".$sql->error."</span>
				<br>
				<br>Saving the changes with these SQL credentials isn't recommended.";
			}
			$sql = null;
			$output .=	"
				<br>You can go back to review the choices, or click <span class='highlight'>'Next'</span> if you're sure you want to save the changes.";
				
			break;
		case BASE_STEP + 2:
			$windowtitle = "Reconfiguring";
			$output = "<span style='text-align: left'><pre>";

			// Setup for misc crap
			$output .= "Starting the reconfiguration process...";
			set_time_limit(0);
			$btn &= ~BTN_NEXT;
						
			$output .= "\nWriting settings to ".CONFIG_PATH."...";
			$configfile = generate_config();
			$res = file_put_contents(CONFIG_PATH, $configfile);
			$output .= checkres($res);
			
			$output .= "\nOperation completed successfully!\n";
			$output .= "\nClick <a href='..'>here</a> to return to the board.";
			
			$output .= "</pre></span>";
			break;
	}
}