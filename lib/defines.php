<?php

	// Fields necessary to generate userlinks
	$userfields = "u.name, u.displayname, u.aka, u.sex, u.group, u.birthday, u.namecolor, u.minipic, u.id";
	
	// <posts required> => <color>, <text>, <bar image>
	$syndromes = array(
		75      => ['83F3A3', "'Reinfors Syndrome' ",                             'red.png'], // bar-onred.png
		100     => ['FFE323', "'Reinfors Syndrome' +",                            'red.png'],
		150     => ['FF5353', "'Reinfors Syndrome' ++",                           'red.png'],
		200     => ['CE53CE', "'Reinfors Syndrome' +++",                          'red.png'],
		250     => ['8E83EE', "'Reinfors Syndrome' ++++",                         'red.png'],
		300     => ['BBAAFF', "'Wooster Syndrome'!!",                             'red.png'],
		350     => ['FFB0FF', "'Wooster Syndrome' +!!",                           'red.png'],
		400     => ['FFB070', "'Wooster Syndrome' ++!!",                          'red.png'],
		450     => ['C8C0B8', "'Wooster Syndrome' +++!!",                         'red.png'],
		500     => ['A0A0A0', "'Wooster Syndrome' ++++!!",                        'special.gif'], // bar-onspecial.gif
		600     => ['C762F2', "'Anya Syndrome'!!!",                               'special.gif'],
		800     => ['62C7F2', "'Xkeeper Syndrome' +++++!!",                       'special.gif'],
		1000    => ['FFFFFF', "'Something higher than Xkeeper Syndrome' +++++!!", 'special.gif']
	);
	
	// Signature separators
	$sepn 	= array('Dashes','Line','Full horizontal line','None');
	$sep	= array('<br><br>--------------------<br>',
					'<br><br>____________________<br>',
					'<br><br><hr>',
					'<br><br>');
					
	// Token list
	const TOKEN_MAIN     = 20;
	const TOKEN_LOGIN    = 10;
	const TOKEN_REGISTER = 30;
	
	
	//const PERM_FIELDS_NUM = 1;	// Number of permission fields - needs to be the max bitmask set id (shown below)
	
	
	/*
		Permission definitions
		<permission name> = [<bitmask set>, <flag>]
	*/
	/*
	const PERM_BYPASS_LOCKDOWN 				= [1, 0b1]; // SYSADMIN
	const PERM_DISPLAY_HIDDEN_FORUMS 		= [1, 0b10]; // ADMIN
	const PERM_VIEW_DEBUGGER				= [1, 0b100]; // SYSADMIN
	const PERM_VIEW_SUBMESSAGE				= [1, 0b1000]; // SUPER
	const PERM_VIEW_BPT_INFO				= [1, 0b10000]; // ADMIN (+ online.php ip sort)
	const PERM_SHOW_HIDDEN_USER_ACTIVITY	= [1, 0b100000]; // MOD
	const PERM_SYSADMIN_ACTIONS				= [1, 0b1000000]; // SYSADMIN [Generic Sysadmin Perm]
	const PERM_ADMIN_ACTIONS				= [1, 0b10000000]; // ADMIN [Generic Admin Perm]
	const PERM_LOGS_BANNER					= [1, 0b100000000]; // SYSADMIN / Other whitelisted
	const PERM_USE_SHOPED					= [1, 0b1000000000]; // SUPER
	const PERM_FORUM_ADMIN					= [1, 0b10000000000]; // ADMIN
	const PERM_EDIT_OWN_POSTS				= [1, 0b100000000000]; // NORMAL
	const PERM_ALL_FORUM_ACCESS				= [1, 0b1000000000000]; // MOD [Generic Mod Perm]
	const PERM_EDIT_OWN_PROFILE				= [1, 0b10000000000000]; // NORMAL
	const PERM_HAS_TITLE					= [1, 0b100000000000000]; // NORMAL
	const PERM_HAS_ALWAYS_TITLE				= [1, 0b1000000000000000]; // SUPER
	const PERM_CHANGE_NAMECOLOR				= [1, 0b10000000000000000]; // SUPER
	const PERM_VIEW_ONLINE_PAGE				= [1, 0b100000000000000000]; // GUEST
	const PERM_SELECT_SECRET_THEMES			= [1, 0b1000000000000000000]; // ADMIN / Other whitelisted
	const PERM_EDIT_OWN_EVENTS				= [1, 0b10000000000000000000]; // NORMAL
	const PERM_SHOW_SUPER_USERS				= [1, 0b100000000000000000000]; // ADMIN
	const PERM_VIEW_OTHERS_PMS				= [1, 0b1000000000000000000000]; // ADMIN
	const PERM_SHOW_ALL_RANKS				= [1, 0b10000000000000000000000]; // MOD
	const PERM_REREGISTER					= [1, 0b100000000000000000000000]; // ADMIN
	const PERM_SEND_PMS						= [1, 0b1000000000000000000000000]; // NORMAL
	const PERM_VIEW_SHITBUGS				= [1, 0b10000000000000000000000000]; // SUPER
	const PERM_USE_SHOPED_HIDDEN			= [1, 0b100000000000000000000000000]; // SYSADMIN / Whitelisted
	const PERM_CREATE_CUSTOM_FORUMS			= [1, 0b1000000000000000000000000000]; // NORMAL (yes)
	const PERM_POST_NEWS					= [1, 0b10000000000000000000000000000]; // SUPER
	const PERM_NEWS_ADMIN					= [1, 0b100000000000000000000000000000]; // ADMIN
	const PERM_BYPASS_CUSTOM_FORUM_LIMITS	= [1, 0b1000000000000000000000000000000]; // Whitelisted
	*/
//  const PERM__________________			= [1, 0b00000000000000000000000000000000];
	
	
	/*
		Forum permissions
	*/
	const PERM_FORUM_READ 		= 0b11;
	const PERM_FORUM_POST 		= 0b1100;
	const PERM_FORUM_THREAD 	= 0b110000;
	const PERM_FORUM_EDIT		= 0b11000000;
	const PERM_FORUM_DELETE		= 0b1100000000;
	const PERM_FORUM_MOD 		= 0b110000000000;
	const PERM_FORUM_NOTMOD 	= 0b001111111111;
	const PERM_FORUM_ALL 		= 0b111111111111;
	
	
	/*
		Default unremovable groups
		They HAVE to match the entries in the perm_groups table
	*/
	const GROUP_NORMAL		= 1;
	const GROUP_SUPER		= 2;
	const GROUP_MOD			= 3;
	const GROUP_ADMIN		= 4;
	const GROUP_SYSADMIN 	= 5;
	const GROUP_GUEST		= 6;	// Separator
	const GROUP_BANNED		= 7;
	const GROUP_PERMABANNED = 8;
	
	/*
		Only use for color schemes
	*/
	const MALE 		= 'namecolor0';
	const FEMALE 	= 'namecolor1';
	const N_A 		= 'namecolor2';
	
	
	/*
		Bot / Tor / Proxy flags
		Used to set the flags for online guests table
	*/
	const BPT_IPBANNED 	= 1;
	const BPT_PROXY 	= 2;
	const BPT_TOR 		= 4;
	const BPT_BOT 		= 8;
	
	
	// The date display functions had an unreadable mess of FALSE,TRUE,FALSE so it had to be done
	# printdate
	const PRINT_TIME = 0b1;
	const PRINT_DATE = 0b10;
	# datetofields
	const DTF_DATE = 0b1;
	const DTF_TIME = 0b10;
	const DTF_NOLABEL = 0b100;
	
	
	// Assumptions of orig. xk_ircsend channel IDs based on the context they're used on
	const IRC_MAIN = 0;
	const IRC_STAFF = 1;
	const IRC_ADMIN = 102;
	