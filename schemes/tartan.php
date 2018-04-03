<?php

	/**************************************************************************
	  PROTIP

	  You can leave values commented out to just let the default one take effect.

	**************************************************************************/
	
	$formcss		= 1;		# Makes form and inputs white on black, set to 0 if you want to custom style them (use css_extra below)
	$numcols		= 100;		# Width of text entry, just use css extra again

	# Page background color, background image, and text color
	$bgimage        = "schemes/tartan/tartan1.jpg";
	$bgcolor        = '100018'; 
	$textcolor		= 'ffffff';	
	
	$font 			= "Arial, 'Helvetica Neue', Helvetica, sans-serif";
	
	# Links
	$linkcolor		= 'BABECE';	# Unvisited link
	$linkcolor2		= '9EBAFE'; # Visited
	$linkcolor3		= 'FFEA00'; # Active
	$linkcolor4		= 'FFD8A0'; # Hover

	$tableborder	= '000000'; # Border color for tables
	//$tableheadtext	= '0B3708'; # Table header text color
	$tableheadbg	= '0054B0 url("schemes/tartan/tartan2.jpg")'; # Table header background (you can use images)
	$categorybg		= '13031a'; # Category BG
	$tablebg1		= '222222'; # Table cell 1 background
	$tablebg2		= '333333'; # Table cell 2 (the darker one, usually)
	$inputborder    = '777799';
	
	# Scrollbar colors...
	$scr1			= '0050AF'; # top-left outer highlight
	$scr2			= '00387F'; # top-left inner highlight
	$scr3			= '17AEFF'; # middle face
	$scr4			= '004090'; # bottom-right inner shadow
	$scr5			= '003070'; # bottom-right outer shadow
	$scr6			= '002750'; # button arrows
	$scr7			= '000000'; # track
/*
	# Group colors           
	$nmcol = array(#  Permabanned		   Banned    Normal   Normal+  Moderator     Admin
	    0 => array('-2'=>'6a6a6a', '-1'=>'888888', '97ACEF', 'D8E8FE',  'AFFABE', 'FFEA95'), # Male
	    1 => array('-2'=>'767676', '-1'=>'888888', 'F185C9', 'FFB3F3',  'C762F2', 'C53A9E'), # Female
	    2 => array('-2'=>'767676', '-1'=>'888888', '7C60B0', 'EEB9BA',  '47B53C', 'F0C413'), # N/A
	);
*/
	
	# Images for New Poll, New Thread etc.
	$newthreadpic = '<img src="schemes/tartan/status/newthread.jpg" align="absmiddle">';
	$newreplypic  = '<img src="schemes/tartan/status/newreply.jpg" align="absmiddle">';
	$newpollpic   = "New poll";
	$closedpic    = 'Thread closed';
	

	# Number graphics (leave these alone unless you know what you're doing)
	$numdir = 'num6/';
	$numfil = 'nummegaman';

	# Status icons for threads, should be self-explanatory
	/*
	$statusicons['new']			= '<img src="schemes/default_old/status/new.gif">';
	$statusicons['newhot']		= '<img src="schemes/default_old/status/hotnew.gif">';
	$statusicons['newoff']		= '<img src="schemes/default_old/status/off.gif">';
	$statusicons['newhotoff']	= '<img src="schemes/default_old/status/hotoff.gif">';
	$statusicons['hot']			= '<img src="schemes/default_old/status/hot.gif">';
	$statusicons['hotoff']		= '<img src="schemes/default_old/status/hotoff.gif">';
	$statusicons['off']			= '<img src="schemes/default_old/status/off.gif">';*/


	# Extra CSS included at the bottom of a page

	$css_extra		= "
";