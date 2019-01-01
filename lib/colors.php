<?php

	/*
		Here lie the default strings / text colors
	*/
	
	global 	$pwlnames, $nmcol, $statusicons,
			$newpollpic, $newreplypic, $newthreadpic, $closedpic, $nopollpic, $poweredbypic, $numdir, $numfil;
			
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
	$tableheadtext = "";

	$font	= 'Verdana, Geneva, sans-serif';
	$font2	= 'Verdana, Geneva, sans-serif';
	$font3	= 'Tahoma, Verdana, Geneva, sans-serif';
	
	for ($i = 1; $i < 8; ++$i) {
		${'scr'.$i} = 'auto';
	}

	$newpollpic		= '<img src="schemes/default/status/newpoll.png" alt="New poll" align="absmiddle">';
	$newreplypic	= '<img src="schemes/default/status/newreply.png" alt="New reply" align="absmiddle">';
	$newthreadpic	= '<img src="schemes/default/status/newthread.png" alt="New thread" align="absmiddle">';
	$closedpic		= '<img src="schemes/default/status/threadclosed.png" alt="Thread closed" align="absmiddle">';
	$nopollpic      = '<img src="schemes/default/status/nopolls.png" alt="No more fucking polls" align="absmiddle">';
	$poweredbypic   = '<img src="images/poweredbyacmlm.gif">';
	$numdir			= 'jul/';

	$statusicons = array(
		'new'			=> '<img src="schemes/default/status/new.gif">',
		'newhot'		=> '<img src="schemes/default/status/hotnew.gif">',
		'newoff'		=> '<img src="schemes/default/status/off.gif">',
		'newhotoff'		=> '<img src="schemes/default/status/hotoff.gif">',
		'hot'			=> '<img src="schemes/default/status/hot.gif">',
		'hotoff'		=> '<img src="schemes/default/status/hotoff.gif">',
		'off'			=> '<img src="schemes/default/status/off.gif">',

		'getnew'		=> '<img src="schemes/default/status/getnew.png" title="Go to new posts" align="absmiddle">',
		'getlast'		=> '<img src="schemes/default/status/getlast.png" title="Go to last post" style="position:relative;top:1px">',

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
	if ($loguser['powerlevel'] < $config['view-super-minpower']) {
		$nmcol[0][1]	= $nmcol[0][0];
		$nmcol[1][1]	= $nmcol[1][0];
		$nmcol[2][1]	= $nmcol[2][0];
	}
	//$nmcol[0][4]		= "#ffffff";

