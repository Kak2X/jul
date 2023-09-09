<?php
	
	$formcss		= 1;		# Makes form and inputs white on black, set to 0 if you want to custom style them (use css_extra below)
	
	# Banner; comment for default
	// I don't have a version without text. There're just lame photoshop effects, you can make your own banner.
	// $config['board-title']      = '<img src="schemes/ruggedb/banner2.gif">';

	# Page background color, background image, and text color
	$bgcolor		= '000000';
	$bgimage		= 'schemes/ruggedb/background.gif';
	$textcolor		= 'E0E0E0';	
	
	# Links
	$linkcolor		= '6ff2fe';	# Unvisited link
	$linkcolor2		= '3399ff'; # Visited
	$linkcolor3		= 'FFEA00'; # Active
	$linkcolor4		= 'FFFFFF'; # Hover
	
	$inputborder    = '0070C0'; # Border color for input elements
	$tableborder	= '0070C0'; # Border color for tables
	$tableheadtext	= 'FFFFFF'; # Table header text color
	$tableheadbg	= '0050A0 url("schemes/ruggedb/tdbgh.gif")';  # Table header background (you can use images)
	$categorybg		= '000060 url("schemes/ruggedb/tdbgc.gif")'; # Category BG
	$tablebg1		= '000036 url("schemes/ruggedb/tdbg1.gif")';   # Table cell 1 background
	$tablebg2		= '000060 url("schemes/ruggedb/tdbg2.gif")';   # Table cell 2 (the darker one, usually)
	
	# Font family
/*
	$font	= 'Verdana, Geneva, sans-serif'; // Main font
	$font2	= 'Verdana, Geneva, sans-serif'; // Small font
	$font3	= 'Tahoma, Verdana, Geneva, sans-serif'; // (unused?)
*/
	# Scrollbar colors...
/*
	$scr1			= 'aaaaff';	# top-left outer highlight
	$scr2			= '9999ee'; # top-left inner highlight
	$scr3			= '7777bb'; # middle face
	$scr4			= '555599'; # bottom-right inner shadow
	$scr5			= '444488'; # bottom-right outer shadow
	$scr6			= '000000'; # button arrows
	$scr7			= '000033'; # track
*/

	# Group colors     
/* I edited two colors just so they would show up a little better. 
   All of these follow the default color scheme, remove them if you wish. */	
	$nmcol = array(#  Permabanned		   Banned    Normal   Normal+  Moderator     Admin
	    0 => array('-2'=>'6a6a6a', '-1'=>'888888', '97ACEF', 'D8E8FE',  'AFFABE', 'FFEA95'), # Male
	    1 => array('-2'=>'767676', '-1'=>'888888', 'F185C9', 'FFB3F3',  'C762F2', 'f359b7'), # Female
	    2 => array('-2'=>'767676', '-1'=>'888888', '8c6bc9', 'EEB9BA',  '47B53C', 'F0C413'), # N/A
	);
	
	# Images for New Poll, New Thread etc.
	$newpollpic     = '<img src="schemes/ruggedb/status/newpoll.gif" align="absmiddle">';
	$newreplypic    = '<img src="schemes/ruggedb/status/newreply.gif" align="absmiddle">';
	$newthreadpic   = '<img src="schemes/ruggedb/status/newthread.gif" align="absmiddle">';

	# 'Powered by' image, if one is provided
	$poweredbypic = '<img src="schemes/ruggedb/poweredby.gif">';

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