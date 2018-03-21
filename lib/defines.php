<?php

	// Fields necessary to generate userlinks
	$userfields = "u.name, u.aka, u.sex, u.powerlevel, u.birthday, u.namecolor, u.minipic, u.id";
	
	// Line separators
	$sepn 	= array('Dashes','Line','Full horizontal line','None');
	$sep	= array('<br><br>--------------------<br>',
					'<br><br>____________________<br>',
					'<br><br><hr>',
					'<br><br>');
	
	// Function constants
	const IRC_MAIN = 0;
	const IRC_STAFF = 1;
	const IRC_ADMIN = 102;
	
	const BPT_IPBANNED 	= 1;
	const BPT_PROXY 	= 2;
	const BPT_TOR 		= 4;
	const BPT_BOT 		= 8;
	
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
	
	
	const PMFOLDER_MAIN   = 0;
	const PMFOLDER_ALL   = -1;
	const PMFOLDER_TO    = -2;
	const PMFOLDER_BY    = -3;
	const PMFOLDER_TRASH = -4;
	
	
	$pwlnames = array(
		'-2'=>'Permabanned',
		'-1'=>'Banned', 
		'Normal', 
		'Normal +',
		'Moderator', 
		'Administrator',
		'Sysadmin'
	);