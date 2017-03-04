<?php

	/**************************************************************************
	  PROTIP

	  You can leave values commented out to just let the default one take effect.

	**************************************************************************/
	
	$formcss		= 1;		# Makes form and inputs white on black, set to 0 if you want to custom style them (use css_extra below)
	$numcols		= 100;		# Width of text entry, just use css extra again

	# Banner; comment for default
	$config['board-title']		= '<img src="images/pointlessbannerv2.png" title="AMAZING!">';

	# Page background color, background image, and text color
	$bgcolor		= '000810';
	$bgimage		= 'images/f2/bg.png';
	$textcolor		= 'EEEEEE';	

	# Links
	$linkcolor		= 'B8DEFE';	# Unvisited link
	$linkcolor2		= '8BA8C0'; # Visited
	$linkcolor3		= 'CCE8FF'; # Active
	$linkcolor4		= 'CCE8FF'; # Hover

	$tableborder	= '000011'; # Border color for tables
	$tableheadtext	= '002549'; # Table header text color
	$tableheadbg	= '000921'; # Table header background (you can use images)
	$categorybg		= '002864'; # Category BG
	$tablebg1		= '001E4B'; # Table cell 1 background
	$tablebg2		= '001638'; # Table cell 2 (the darker one, usually)

	# Scrollbar colors...
	$scr1			= 'aaaaff';	# top-left outer highlight
	$scr2			= '9999ee'; # top-left inner highlight
	$scr3			= '7777bb'; # middle face
	$scr4			= '555599'; # bottom-right inner shadow
	$scr5			= '444488'; # bottom-right outer shadow
	$scr6			= '000000'; # button arrows
	$scr7			= '000033'; # empty space below the progress bar

	# Name colors for default groups
/*
	$grouplist[GROUP_PERMABANNED][MALE] 	= '6a6a6a';
	$grouplist[GROUP_PERMABANNED][FEMALE] 	= '767676';
	$grouplist[GROUP_PERMABANNED][N_A] 		= '767676';
	
	$grouplist[GROUP_BANNED][MALE] 			= '888888';
	$grouplist[GROUP_BANNED][FEMALE] 		= '888888';
	$grouplist[GROUP_BANNED][N_A] 			= '888888';
	
	$grouplist[GROUP_NORMAL][MALE] 			= '97ACEF';
	$grouplist[GROUP_NORMAL][FEMALE] 		= 'F185C9';
	$grouplist[GROUP_NORMAL][N_A] 			= '7C60B0';

	$grouplist[GROUP_SUPER][MALE] 			= 'D8E8FE';
	$grouplist[GROUP_SUPER][FEMALE] 		= 'FFB3F3';
	$grouplist[GROUP_SUPER][N_A] 			= 'EEB9BA';

	$grouplist[GROUP_MOD][MALE] 			= 'AFFABE';
	$grouplist[GROUP_MOD][FEMALE] 			= 'C762F2';
	$grouplist[GROUP_MOD][N_A] 				= '47B53C';

	$grouplist[GROUP_ADMIN][MALE] 			= 'FFEA95';
	$grouplist[GROUP_ADMIN][FEMALE] 		= 'C53A9E';
	$grouplist[GROUP_ADMIN][N_A] 			= 'F0C413';

	$grouplist[GROUP_SYSADMIN][MALE] 		= 'FFEA95';
	$grouplist[GROUP_SYSADMIN][FEMALE] 		= 'C53A9E';
	$grouplist[GROUP_SYSADMIN][N_A] 		= 'F0C413';	
*/
	
	# Images for New Poll, New Thread etc.
/*
	$newpollpic		= '<img src="images/newpoll.png" alt="New poll" align="absmiddle">';
	$newreplypic	= '<img src="images/newreply.png" alt="New reply" align="absmiddle">';
	$newthreadpic	= '<img src="images/newthread.png" alt="New thread" align="absmiddle">';
	$closedpic		= '<img src="images/threadclosed.png" alt="Thread closed" align="absmiddle">';
*/

	# Number graphics (leave these alone unless you know what you're doing)
/*	$numdir			= 'jul/';																# /numgfx/<dir>/ for number images
	$numfil			= 'numnes';																# numgfx graphic set
*/

	# Status icons for threads, should be self-explanatory
/*
	$statusicons['new']			= '<img src="images/ccs/new.png">';
	$statusicons['newhot']		= '<img src="images/ccs/newhot.png">';
	$statusicons['newhotoff']	= '<img src="images/ccs/newhotoff.png">';
	$statusicons['hot']			= '<img src="images/ccs/hot.png">';
	$statusicons['hotoff']		= '<img src="images/ccs/hotoff.png">';
	$statusicons['off']			= '<img src="images/ccs/off.png">';
	
	$statusicons['getnew']		= '<img src="images/getnew.png" title="Go to new posts" align="absmiddle">';
	$statusicons['getlast']		= '<img src="images/getlast.png" title="Go to last post" style="position:relative;top:1px">';

	$statusicons['sticky']			= 'Sticky:';
	$statusicons['poll']			= 'Poll:';
	$statusicons['stickypoll']		= 'Sticky poll:';
	$statusicons['ann']				= 'Announcement:';
	$statusicons['annsticky']		= 'Announcement - Sticky:';
	$statusicons['annpoll']			= 'Announcement - Poll:';
	$statusicons['annsticky'] 		= 'Announcement - Sticky:';
	$statusicons['annpoll']			= 'Announcement - Poll:';
	$statusicons['annstickypoll']	= 'Announcement - Sticky poll:';
*/

	# Extra CSS included at the bottom of a page

	$css_extra		= "
		.dummy	{display: none;}
		";
	
