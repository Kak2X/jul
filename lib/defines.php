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