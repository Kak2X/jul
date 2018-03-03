<?php

	/*
		Here lie the default strings / text colors
	*/
	
	global 	$pwlnames, $nmcol, $statusicons,
			$newpollpic, $newreplypic, $newthreadpic, $closedpic, $numdir, $numfil;
			
	$nmcol = array(
		0 	 => array('-2'=>     '6a6a6a', '-1'=>'888888', '97ACEF',   'D8E8FE',   'AFFABE',        'FFEA95'),
		1 	 => array('-2'=>     '767676', '-1'=>'888888', 'F185C9',   'FFB3F3',   'C762F2',        'C53A9E'),
		2 	 => array('-2'=>     '767676', '-1'=>'888888', '7C60B0',   'EEB9BA',   '47B53C',        'F0C413')
	);
	
	$linkcolor	='FFD040';
	$linkcolor2 ='F0A020';
	$linkcolor3 ='FFEA00';
	$linkcolor4 ='FFFFFF';
	$textcolor	='E0E0E0';

	$font	='verdana';
	$font2	='verdana';
	$font3	='tahoma';

	$newpollpic		= '<img src="images/newpoll.png" alt="New poll" align="absmiddle">';
	$newreplypic	= '<img src="images/newreply.png" alt="New reply" align="absmiddle">';
	$newthreadpic	= '<img src="images/newthread.png" alt="New thread" align="absmiddle">';
	$closedpic		= '<img src="images/threadclosed.png" alt="Thread closed" align="absmiddle">';
	$numdir			= 'jul/';

	$statusicons = array(
		'new'			=> '<img src="images/new.gif">',
		'newhot'		=> '<img src="images/hotnew.gif">',
		'newoff'		=> '<img src="images/off.gif">',
		'newhotoff'		=> '<img src="images/hotoff.gif">',
		'hot'			=> '<img src="images/hot.gif">',
		'hotoff'		=> '<img src="images/hotoff.gif">',
		'off'			=> '<img src="images/off.gif">',

		'getnew'		=> '<img src="images/getnew.png" title="Go to new posts" align="absmiddle">',
		'getlast'		=> '<img src="images/getlast.png" title="Go to last post" style="position:relative;top:1px">',

		'sticky'		=> 'Sticky:',
		'poll'			=> 'Poll:',
		'stickypoll'	=> 'Sticky poll:',
		'ann'			=> 'Announcement:',
		'annsticky'		=> 'Announcement - Sticky:',
		'annpoll'		=> 'Announcement - Poll:',
		'annsticky' 	=> 'Announcement - Sticky:',
		'annpoll'		=> 'Announcement - Poll:',
		'annstickypoll'	=> 'Announcement - Sticky poll:',
	);
	
	//$schemetime	= -1; // mktime(9, 0, 0) - time();
	
	$numfil = 'numnes';
	
	


	// Hide Normal+ to non-admins
	if ($loguser['powerlevel'] < 3) {
		$nmcol[0][1]	= $nmcol[0][0];
		$nmcol[1][1]	= $nmcol[1][0];
		$nmcol[2][1]	= $nmcol[2][0];
	}
	//$nmcol[0][4]		= "#ffffff";

