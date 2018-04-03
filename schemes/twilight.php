<?php

	/**************************************************************************
	  PROTIP

	  You can leave values commented out to just let the default one take effect.

	**************************************************************************/
	
	$formcss		= 0;		# Makes form and inputs white on black, set to 0 if you want to custom style them (use css_extra below)
	$numcols		= 100;		# Width of text entry, just use css extra again

	# Page background color, background image, and text color
	$bgcolor		= '112E28';
	//$bgimage		= 'schemes/fragmentation2/bg.png';
	$textcolor		= 'FFF'; //'0B3708';	
	
	$font 			= 'Tahoma, Verdana, Geneva, sans-serif';
	
	# Links
	$linkcolor		= 'FFFFFF';	# Unvisited link
	$linkcolor2		= 'FFFFFF'; # Visited
	$linkcolor3		= 'FFFFFF'; # Active
	$linkcolor4		= 'FFFFFF'; # Hover
	/*
	$linkcolor		= '0B3708';	# Unvisited link
	$linkcolor2		= '0B3708'; # Visited
	$linkcolor3		= '0B3708'; # Active
	$linkcolor4		= 'FFD200'; # Hover
	*/
	
	# Banner; comment for default
	# $config['board-title']		= '<img src="schemes/aceboard/aceboardtitlepic.jpg">';
	
	$tableborder	= '000000'; # Border color for tables
	$tableheadtext	= 'FFFFFF'; # Table header text color
	$tableheadbg	= '406030'; # Table header background (you can use images)
	$categorybg		= '204018 url("schemes/twilight/categorybg.jpg");'; # Category BG
	$tablebg1		= '7FAC7B'; # Table cell 1 background
	$tablebg2		= '52814F'; # Table cell 2 (the darker one, usually)
	
	# Scrollbar colors...
	$scr1			= '336923'; # top-left outer highlight
	$scr2			= '2A501E'; # top-left inner highlight
	$scr3			= '214118'; # middle face
	$scr4			= '1F3B17'; # bottom-right inner shadow
	$scr5			= '1B2F15'; # bottom-right outer shadow
	$scr6			= 'ADFBBD'; # button arrows
	$scr7			= '001408'; # track

/*
	# Group colors           
	$nmcol = array(#  Permabanned		   Banned    Normal   Normal+  Moderator     Admin
	    0 => array('-2'=>'6a6a6a', '-1'=>'888888', '97ACEF', 'D8E8FE',  'AFFABE', 'FFEA95'), # Male
	    1 => array('-2'=>'767676', '-1'=>'888888', 'F185C9', 'FFB3F3',  'C762F2', 'C53A9E'), # Female
	    2 => array('-2'=>'767676', '-1'=>'888888', '7C60B0', 'EEB9BA',  '47B53C', 'F0C413'), # N/A
	);
*/
	
	# Images for New Poll, New Thread etc.
	$newthreadpic = '<img src="schemes/twilight/status/new-thread.gif" align=absmiddle>';
	$newreplypic  = '<img src="schemes/twilight/status/new-reply.gif" align=absmiddle>';
	$newpollpic   = '<img src="schemes/twilight/status/new-poll.gif" align=absmiddle>';
	$closedpic    = 'Thread closed';
	

	# Number graphics (leave these alone unless you know what you're doing)
	$numdir = 'numdig/';
	$numfil = 'numcircuit';

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
