<?php

	// Fields necessary to generate userlinks
	$userfields = "u.name, u.displayname, u.aka, u.sex, u.powerlevel, u.birthday, u.namecolor, u.minipic, u.id";
	$userfields_array = ['name', 'displayname', 'aka', 'sex', 'powerlevel', 'birthday', 'namecolor', 'minipic', 'id'];
	
	// Line separators
	const SIGNSEP_DESC 	= array('Dashes','Line','Full horizontal line','None');
	const SIGNSEP	= array('<br>--------------------<br>',
					'<br>____________________<br>',
					'<br><hr>',
					'<br>'); // A single <br> specifically for this, so it can get ignored when closing divs/tables
	
	// Function constants
	const IRC_MAIN = 1;
	const IRC_STAFF = 2;
	const IRC_ADMIN = 3;
	
	const BPT_IPBANNED 	= 1;
	const BPT_PROXY 	= 2;
	const BPT_TOR 		= 4;
	const BPT_BOT 		= 8;
	
	// Common select list generator flags
	const SEL_DISABLED = 0b1;
	
	// Token list
	const TOKEN_MAIN         = 20;
	const TOKEN_LOGIN        = 10;
	const TOKEN_SHOP         = 14;
	const TOKEN_VOTE         = 16;
	const TOKEN_SLAMMER      = 18;
	const TOKEN_REGISTER     = 30;
	const TOKEN_MGET         = 35;
	const TOKEN_BANNER       = 45;
	const TOKEN_USERDEL      = 65;
	
	
	const PMFOLDER_MAIN  =  0;
	const PMFOLDER_ALL   = -1;
	const PMFOLDER_TO    = -2;
	const PMFOLDER_BY    = -3;
	const PMFOLDER_TRASH = -4;
	
	$pmfoldernames = array(
		PMFOLDER_MAIN  => "Default folder",
		PMFOLDER_ALL   => "All conversations",
		PMFOLDER_TO    => "Conversations you take part in",
		PMFOLDER_BY    => "Conversations created",
		PMFOLDER_TRASH => "The Trash&trade;"
	);
	
	const THREAD_OK = 1;
	const NO_THREAD = 0;
	const INVALID_THREAD = -1;
	const INVALID_FORUM  = -2;
	const NOT_AUTHORIZED = -3;
	
	const MODE_POST = 0;
	const MODE_PM   = 1;
	const MODE_ANNOUNCEMENT = 2;
	
	const ATTACH_REQ_DEFAULT = -1;
	const ATTACH_REQ_DISABLED = -2;
	
	const PWL_MIN = -2;
	const PWL_MAX = 4;
	
	const PWL_PERMABANNED = -2;
	const PWL_BANNED = -1;
	const PWL_NORMAL = 0;
	const PWL_SUPER = 1;
	const PWL_MOD = 2;
	const PWL_ADMIN = 3;
	const PWL_SYSADMIN = 4;
	
	$pwlnames = array(
		'-2'=>'Permabanned',
		'-1'=>'Banned', 
		'Normal', 
		'Normal +',
		'Moderator', 
		'Administrator',
		'Sysadmin'
	);
	
	const DATE_FORMATS = array('m-d-y h:i:s A', 'd/m/Y H:i:s', 'Y-m-d H:i:s');
	const DATE_SHORT_FORMATS  = array('m-d-y', 'd/m/Y', 'Y-m-d');
	
	const BOTCMD_RESTART = "-1";
	
	const PWARN_MIN = 0;
	const PWARN_MAX = 2;
	const PWARN_NONE = 0; // Not warned
	const PWARN_WARN = 1; // Warned - unread
	const PWARN_WARNREAD = 2; // Warned - read
	
	const PHILI_MIN = 0;
	const PHILI_MAX = 2;
	const PHILI_NONE = 0; // None
	const PHILI_LOCAL = 1; // Local to the thread
	const PHILI_SUPER = 2; // Hall of fame
	
	const LOGFAIL_IGNORE = 0;
	const LOGFAIL_TEMPBLOCK = 1;
	const LOGFAIL_IPBAN = 2;
	
	const CSS_UPLOAD_MAX = 16 * 1024;