<?php

	// file inclusion marker
	const INSTALL_FILE = true;
	
	// lockout after first install
	const INSTALL_LOCK = true;
	
	// enables logout / unban links
	const SETUP_DEBUG = false;
	
	// requires a valid SQL connection to use the installer when the board is already installed.
	const INSTALL_VALID_CONN = true;
	
	// path to the "db version" number, used by the upgrade tool
	const DBVER_PATH = "lib/dbsver.dat";
	
	// path to the config file, used to determine if the board is already installed
	const CONFIG_PATH = "lib/config.php";
	
	// path to the firewall file, used to determine if this is a private/local copy of the board or the git version
	const FIREWALL_PATH = "extensions/firewall.abx";
	
	// number of common pages before execution goes to the specific index_<xyz>.php
	const BASE_STEP = 2;
	
	// constants for the navigation buttons
	const BTN_NONE = 0;
	const BTN_NEXT = 0b1;
	const BTN_PREV = 0b10;
	
	
	define('INSTALLED', file_exists(CONFIG_PATH));
	define('PRIVATE_BUILD', file_exists(FIREWALL_PATH));