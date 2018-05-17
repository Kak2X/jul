<?php
	const OK_INSTALL = true;

	// What are we doing here
	if (isset($_GET['sql'])) {
		return header("Location: install.sql");
	} else if (isset($_GET['chconfig']) && file_exists('../lib/config.php')) {
		// Skip to settings page, with the correct settings (only update config, no delete)
		$_POST = array(
			'step'       => 4 - 1,
			'stepcmd'    => 'Next',
			'dropdb'     => 0,
			'__chconfig' => true,
		);
	}
	
	require "install.php";