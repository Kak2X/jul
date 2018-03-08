<?php

	$sqlhost 	= 'localhost';
	$sqluser 	= 'root';
	$sqlpass 	= '';
	$dbname		= 'julpower';
	
	$sqldebuggers = array('127.0.0.1');
	
	const BOARD_VERSION = 'v1.92 (8/3/2018)';
	
	$config = array(
	
		// Layout
		'board-name'			=> "Not Jul",
		'board-title'			=> '<img src="images/pointlessbannerv2.png" title="The testboard experience">',
		'title-submessage'		=> "",
		'board-url'				=> 'http://localhost/jul', // Non-HTTPS Board URL without last backslash (the origin check depends on this)
		'admin-email'			=> 'admin@something.com',
		'admin-name'			=> '(admin name)',
		'irc-servers'			=> array ( // List of IRC servers. The first one is the 'preferred' option
									1 => "irc.badnik.zone",
									2 => "irc.rustedlogic.net",
									3 => "irc.tcrf.net",
								),
		'irc-server-title'		=> "BadnikZONE", 
		'irc-channels'			=> array ('#tcrf', '#x'),
		
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
		'announcement-forum'    => 4,
		'deleted-user-id'		=> 2,
		'allow-thread-deletion' => true, // Allow permenent deletion of threads...
		'allow-post-deletion'   => true, // ... or posts
		'salt-string'			=> "sillysaltstring",		// Change me!
		'enable-firewall'		=> true,					
		'irc-reporting'			=> true,					// Report to IRC
		'show-ikachan'			=> true,
		'backup-folder'			=> 'backups',				// Folder containing backups, relative to the ab root
		'backup-threshold'		=> 15,						// Days before a backup is considered old
		'force-lastip-match'	=> false,					// Force logout on IP changes

		
		// File uploads
		'allow-attachments'     => true,                    // Enables the attachment feature
		'hide-attachments'      => false,                   // Do not show attachments in threads (only works when allow-attachments is false)
		'attach-max-size'       => 2 * 1048576,	// 2 MB 	// Max size for attachments
		
		'allow-avatar-storage' 	=> true,                    // Enables the board-storage of avatars and minipic. If disabled, it will use the vanilla Jul avatar system.
		'avatar-limit'			=> 64, // set false to disable
		'max-minipic-size-x'	=> 16,
		'max-minipic-size-y'	=> 16,
		'max-minipic-size-bytes'=> 10240,
		'max-avatar-size-x'		=> 200,
		'max-avatar-size-y'		=> 200,
		'max-avatar-size-bytes' => 102400,
		
		
		// Debugging
		'enable-sql-debugger'	=> true,					// (stub to set option in mysql.php)
		'always-show-debug'		=> true,					// Always show error/query list regardless of powerlevel
		'force-user-id'			=> false,						
		'allow-rereggie'		=> false,
		'no-redirects'          => true,                    // Stop auto redirect on error messages
		
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
