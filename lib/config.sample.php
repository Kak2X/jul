<?php

	# Database info
	$sqlhost 	= 'localhost';
	$sqluser 	= 'root';
	$sqlpass 	= '';
	$dbname		= 'jul';
	
	$sqldebuggers = array('127.0.0.1'); # IPs allowed to view the SQL Debugger
	
	const BOARD_VERSION = '18/07/2017 b005.02';
	
	$config = array(
	
		// Layout
		'board-name'			=> "Acmlmboard",			# Title of board. Used in the title bar and as link labels.
		'board-title'			=> '<img src="images/sampletitle.png" title="The board owner is being lazy.">', 	# HTML code shown in the header. Typically this is an image.
		'title-submessage'		=> "This is a staff-only message", # Secondary message shown below the title. By default only the staff can see it.
		'board-url'				=> 'http://localhost/jul',# Non-HTTPS Board URL without last backslash (the origin check depends on this)
		'admin-email'			=> 'admin@something.com', # Email link shown on support pages (ie: IP Banned page)
		'admin-name'			=> '(admin name)', # Nickname shown on support pages
		'irc-servers'			=> array ( # List of selectable IRC servers in the IRC Chat page.
									1 => "irc.something.net",
									2 => "irc.example.org",
								),
		'irc-server-title'		=> "SomeIrcNetwork", # Title given to the IRC Server
		'irc-channels'			=> array ('#nkout', '#sample'), # Channels to AutoJoin once connected
		
		
		'footer-url'			=> 'http://localhost/', # URL linked in the footer, just above the board version info (or affiliate links, if enabled)
		'footer-title'			=> 'Special World', # Label given to the footer URL
		
									# List of affiliate links in a select box. Leave blank to hide it.
		'affiliate-links'		=> '<optgroup label="Forum affiliates">
										<option value="about:blank" selected="">The wonderful blank page</option>
									</optgroup>
									<optgroup label="Other Acmlmboards">
										<option value="about:blank">Nobody knows</option>
										<option value="about:blank">what to put here</option>
										<option value="about:blank">even though</option>
										<option value="about:blank">it should be obvious</option>
									</optgroup>',
		
		
		// Board options
		'deleted-user-id'		=> 2, # Self explainatory. Do not change unless you know what you're doing.
		'allow-thread-deletion' => false, # Permit complete thread deletion (which deletes a thread and all the posts in it from the database)
		'salt-string'			=> "sillysaltstring", # Salt string for the token. You DO want to change it.
		'enable-firewall'		=> false, # Left for backwards compatiblity - a firewall isn't provided	
		'irc-reporting'			=> false, # Left for backwards compatiblity - no IRC reporting is implemented yet.
		'show-ikachan'			=> false, # Display IkaChan overlay in every page.
		'allow-custom-forums'	=> false, # Allow users to create their own forums
		'backup-folder'			=> 'backups', # Directory containing backups. By default located in the board directory.
		
		// Debugging
		'enable-sql-debugger'	=> false, # Enable the SQL Debugger. Note that if enabled it may slow down query calls.
		'always-show-debug'		=> false, # Forcibly show the SQL and Error Debuggers regardless of user privileges.
		'force-user-id'			=> false, # Always sets the current user ID to this. False to disable.				
		'allow-rereggie'		=> false, # If set, it allows anyone to re-register at will. 
		
		// Defaults
		'server-time-offset' 	=> 0 * 3600, # Offset of dates compared to the server date. Change only when the board changes host and the new host is in a different time zone.
		'default-dateformat'	=> 'm-d-y', # PHP Date format. See the date() function from the PHP Manual for more details.
		'default-timeformat'	=> 'h:i:s A', # PHP Short date format
		'default-ppp'			=> 20, # Default number of posts shown in a thread page
		'default-tpp'			=> 50, # Default number of threads shown in a forum page
		
	);
	
	$hacks = array(
		'comments'					=> false, # Always show HTML comments (Set internally when an item has the "Show HTML Comments" effect)
		'noposts'					=> false, # Hides postcounts
		'password_compatibility'	=> false, # Convert old md5 hashes to the new format
	);
	
	$x_hacks = array(
		'host'			=> false,		# Board switch. Some features behave differently if set, but you should normally NEVER enable this.
		'adminip' 		=> '127.0.0.1',	# This IP is automatically set to Sysadmin group
		'mmdeath'		=> -1,			# [DISABLED - The code that handles this is commented out] Doomclock timer. (-1 to disable)
		'rainbownames' 	=> false, 		# Always have rainbow usernames (Set internally on new year)
		'superadmin'	=> false,		# Everybody is a Sysadmin
		'smallbrowse'	=> false,		# Mobile mode (Set internally when a mobile browser is detected)
	);
	
	/*
		Automatically generated variables start here
	*/
	
	// Are we using SSL?
	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
		$config['board-url'] = str_replace("http://", "https://", $config['board-url']);
	}
	$config['debug'] = ($config['always-show-debug'] || $x_hacks['superadmin'] || $x_hacks['adminip'] == $_SERVER['REMOTE_ADDR']);