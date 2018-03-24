<?php
	$formcss		= 0;		# formcss makes forms black with tableborder borders; using cssextra below is easier
	$numcols		= 100;		# same thing, more or less

	$bgimage		= 'schemes/ymar/beegee.png';
	$config['board-title']		= '<img src="schemes/ymar/title.jpg" title="Hello! (Image source: http://tinyurl.com/267s62v)">';	# comment this out for normal banner

	$bgcolor		= '84ace9';   
	$textcolor		= '000912';   

	$linkcolor		= '032335';	# Link
	$linkcolor2		= '470d0d'; # visited
	$linkcolor3		= '86bcef'; # active
	$linkcolor4		= '2c6ba6'; # hover

	$tableborder	= 'f4d9b3'; 
	$tableheadtext	= '1e1200';   
	$tableheadbg	= 'e9941c';   
#	$tableheadbg	= 'fffaa6 url(schemes/ymar/status/headbg.png?x=1)';

	$categorybg		= 'e9941c';   
	$tablebg1		= 'ffc082';   
	$tablebg2		= 'f0b269';   

	# Scrollbar colors... Not sure if I got these right, feel free to tweak
	$scr1			= 'ddd1bb';	# top-left outer highlight
	$scr2			= 'ddd1bb'; # top-left inner highlight
	$scr3			= 'ffffff'; # middle face
	$scr4			= 'ddcdbb'; # bottom-right inner shadow
	$scr5			= 'ddcdbb'; # bottom-right outer shadow
	$scr6			= '000000'; # button arrows
	$scr7			= '8e6f3e';

	#								 Banned    Normal   Normal+   Moderator   Admin
	$nmcol[0]		= array('-1' => '888888', '0c4e8b', '2c7eca', '0a5427', '4e4400', );	# M
	$nmcol[1]		= array('-1' => '888888', '662244', '884455', '910369', '570040', );	# F
	$nmcol[2]		= array('-1' => '888888', '32126d', '522c97', '4b9d15', '5f4b00', );	# N/A

	$newthreadpic	= '<img src="schemes/ymar/status/newthread.png" align="absmiddle">';
	$newreplypic	= '<img src="schemes/ymar/status/newreply.png" align="absmiddle">';
	$newpollpic		= '<img src="schemes/ymar/status/newpoll.png" align="absmiddle">';
	$closedpic		= '<img src="schemes/ymar/status/threadclosed.png" align="absmiddle">';

#	$numdir			= 'ccs/';																# /numgfx/<dir>/ for number images | Kept css, looks nice
	$numdir			= 'ymar/';																# I wonder if this will look better
#	$numfil			= 'numpurple';															# numgfx graphic set

	# Status icons for threads, should be self-explanatory
	$statusicons['new']			= '<img src="schemes/ymar/status/new.png">';
	$statusicons['newhot']		= '<img src="schemes/ymar/status/newhot.png">';
	$statusicons['newoff']		= '<img src="schemes/ymar/status/newoff.png">';
	$statusicons['newhotoff']	= '<img src="schemes/ymar/status/newhotoff.png">';
	$statusicons['hot']			= '<img src="schemes/ymar/status/hot.png">';
	$statusicons['hotoff']		= '<img src="schemes/ymar/status/hotoff.png">';
	$statusicons['off']			= '<img src="schemes/ymar/status/off.png">';


	# Extra CSS included at the bottom of a page
	$css_extra		= "
		textarea,input,select{
		  border:		1px solid #a15c18;
		  background:	#fff;
		  color:		#000;
		  font:	10pt $font;
		  }
		input[type=\"radio\"], .radio {
		  border:	none;
		  background: #ecd7b2;
		  color:	#000000;
		  font:	10pt $font;}
		.submit{
		  border:	#000 solid 2px;
		  font:	10pt $font;}
		a {
/*			text-shadow: 0px 0px 3px #fff;
*/			}
		";
	