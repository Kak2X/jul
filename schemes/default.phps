<?php

	/**************************************************************************
	  PROTIP

	  You can leave values commented out to just let the default one take effect.

	**************************************************************************/
	
	$formcss		= 1;		# Styles input fields, set to 0 if you want to use system default.
	$usebtn			= 1;		# Allows rendering links using the .button CSS class as actual buttons. Only has effect when $formcss is set to 0, otherwise the effect is always enabled.
	$numcols		= 100;		# Width of text entry, just use css extra

	# Banner; comment for default
	$config['board-title']		= '<img src="schemes/fragmentation2/pointlessbannerv2-2.png" title="Illegal in 10 states!">';

	# Page background color, background image, and text color
	$bgcolor		= '000810';
	$bgimage		= 'schemes/fragmentation2/bg.png';
	$textcolor		= 'EEEEEE';	

	# Links
	$linkcolor		= 'B8DEFE';	# Unvisited link
	$linkcolor2		= '8BA8C0'; # Visited
	$linkcolor3		= 'CCE8FF'; # Active
	$linkcolor4		= 'CCE8FF'; # Hover

	$inputborder    = '000011'; # Border color for input elements
	$formtextcolor  = "000000"; # Text color for input elements
	$formcolor      = "FFFFFF"; # BG color for input elements
	$tableborder	= '000011'; # Border color for tables
	$tableheadtext	= '002549'; # Table header text color
	$tableheadbg	= '000921'; # Table header background (you can use images)
	$categorybg		= '002864'; # Category BG
	$tablebg1		= '001E4B'; # Table cell 1 background
	$tablebg2		= '001638'; # Table cell 2 (the darker one, usually)
	
	# Font family
	$font	= 'Verdana, Geneva, sans-serif'; // Main font
	$font2	= 'Verdana, Geneva, sans-serif'; // Small font
	$font3	= 'Tahoma, Verdana, Geneva, sans-serif'; // (unused?)

	# Scrollbar colors...
	$scr1			= 'aaaaff';	# top-left outer highlight
	$scr2			= '9999ee'; # top-left inner highlight
	$scr3			= '7777bb'; # middle face
	$scr4			= '555599'; # bottom-right inner shadow
	$scr5			= '444488'; # bottom-right outer shadow
	$scr6			= '000000'; # button arrows
	$scr7			= '000033'; # track

/*
	# Group colors           
	$nmcol = array(#  Permabanned		   Banned    Normal   Normal+  Moderator     Admin
	    0 => array('-2'=>'6a6a6a', '-1'=>'888888', '97ACEF', 'D8E8FE',  'AFFABE', 'FFEA95'), # Male
	    1 => array('-2'=>'767676', '-1'=>'888888', 'F185C9', 'FFB3F3',  'C762F2', 'C53A9E'), # Female
	    2 => array('-2'=>'767676', '-1'=>'888888', '7C60B0', 'EEB9BA',  '47B53C', 'F0C413'), # N/A
	);
*/
	
	# Images for New Poll, New Thread etc.
/*
	$newthreadpic	= '<img src="schemes/ccs/status/newthread.png" align="absmiddle">';
	$newreplypic	= '<img src="schemes/ccs/status/newreply.png" align="absmiddle">';
	$newpollpic		= '<img src="schemes/ccs/status/newpoll.png" align="absmiddle">';
	$closedpic		= '<img src="schemes/ccs/status/threadclosed.png" align="absmiddle">';
*/

	# 'Powered by' image, if one is provided
//	$poweredbypic   = '<img src="images/poweredby.gif">';

	# Number graphics (leave these alone unless you know what you're doing)
/*
	$numdir			= 'ccs/';																# /numgfx/<dir>/ for number images
	$numfil			= 'numpurple';															# numgfx graphic set
*/

	# Status icons for threads, should be self-explanatory
/*
	$statusicons['new']			= '<img src="schemes/default_old/status/new.gif">';
	$statusicons['newhot']		= '<img src="schemes/default_old/status/hotnew.gif">';
	$statusicons['newoff']		= '<img src="schemes/default_old/status/off.gif">';
	$statusicons['newhotoff']	= '<img src="schemes/default_old/status/hotoff.gif">';
	$statusicons['hot']			= '<img src="schemes/default_old/status/hot.gif">';
	$statusicons['hotoff']		= '<img src="schemes/default_old/status/hotoff.gif">';
	$statusicons['off']			= '<img src="schemes/default_old/status/off.gif">';
*/

	# Extra CSS included at the bottom of a page

	$css_extra		= "
		.tdbg1	{background: url('schemes/fragmentation2/bg1.png')}
		.tdbg2	{background: url('schemes/fragmentation2/bg2.png')}
		body, .tdbg1, .tdbg2	{background-attachment: fixed; background-position: top-left;}
		input, textarea, select	{border: 1px solid #008;}
		";
