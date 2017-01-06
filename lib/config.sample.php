<?php

	$sqlhost 	= 'localhost';
	$sqluser 	= 'root';
	$sqlpass 	= '';
	$dbname		= 'jul';
	
	$sqldebuggers = array('127.0.0.1');
	
	const BOARD_VERSION = '05/01/2017 k001';
	
	$config = array(
	
		// Layout
		'board-name'			=> "Not Jul",
		'board-title'			=> "<h1>Not Jul<h1>",
		'title-submessage'		=> "",
		'board-url'				=> 'http://localhost/jul', // Non-HTTPS Board URL without last backslash (the origin check depends on this)
		'admin-email'			=> 'admin@something.com',
		'admin-name'			=> '(admin name)',
		
		
		'footer-url'			=> 'http://localhost/',
		'footer-title'			=> 'Special World',
		
		'affiliate-links'		=> '',
		/* '<optgroup label="Forum affiliates">
										<option value="about:blank" selected="">The wonderful blank page</option>
									</optgroup>
									<optgroup label="Other Acmlmboards">
										<option value="about:blank">Nobody knows</option>
										<option value="about:blank">what to put here</option>
										<option value="about:blank">even though</option>
										<option value="about:blank">it should be obvious</option>
									</optgroup>',*/
		
		
		// Board options
		'trash-forum'			=> 3,
		'deleted-user-id'		=> 2,
		'allow-thread-deletion' => true,
		'salt-string'			=> "sillysaltstring",		// Change me!
		'enable-firewall'		=> true,					
		'irc-reporting'			=> true,					// Report to IRC
		'show-ikachan'			=> true,
		
		// Debugging
		'enable-sql-debugger'	=> false,					// (stub to set option in mysql.php)
		'always-show-debug'		=> false,					// Always show error/query list regardless of powerlevel
		'force-user-id'			=> false,						
		'allow-rereggie'		=> false,
		
		// Defaults
		'server-time-offset' 	=> 3 * 3600,
		'default-dateformat'	=> 'm-d-y h:i:s A',
		'default-dateshort'		=> 'm-d-y',
		'default-ppp'			=> 20,
		'default-tpp'			=> 50,
		
	);
	
	$hacks = array(
		'comments'					=> false,	// Show HTML comments
		'noposts'					=> false,	// Apparently hides postcounts?
		'password_compatibility'	=> false,	// Convert old md5 hashes to the new format
	);
	
	$x_hacks = array(
		'host'			=> false,		// Board switch
		'adminip' 		=> '127.0.0.1',	// This IP receives powerlevel 4
		'mmdeath'		=> -1,			// Mega Mario doomclock timer? (<= 0 to disable)
		'rainbownames' 	=> false,		// Always rainbow usernames
		'superadmin'	=> false,		// Everybody gets powerlevel 4
		'smallbrowse'	=> false,		// Mobile mode
	);
	
	// Are we using SSL?
	if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off')
		$config['board-url'] = str_replace("http://", "https://", $config['board-url']);
?>