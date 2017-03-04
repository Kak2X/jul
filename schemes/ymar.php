<?php
	$formcss		= 0;		# formcss makes forms black with tableborder borders; using cssextra below is easier
	$numcols		= 100;		# same thing, more or less

	$bgimage		= 'images/ymar/beegee.png';
	$config['board-title']		= '<img src="images/ymar/title.jpg" title="Hello! (Image source: http://tinyurl.com/267s62v)">';	# comment this out for normal banner

	$bgcolor		= '84ace9';   
	$textcolor		= '000912';   

	$linkcolor		= '032335';	# Link
	$linkcolor2		= '470d0d'; # visited
	$linkcolor3		= '86bcef'; # active
	$linkcolor4		= '2c6ba6'; # hover

	$tableborder	= 'f4d9b3'; 
	$tableheadtext	= '1e1200';   
	$tableheadbg	= 'e9941c';   
#	$tableheadbg	= 'fffaa6 url(images/ymar/headbg.png?x=1)';

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
	
	$grouplist[GROUP_PERMABANNED]['namecolor0'] = '888888';
	$grouplist[GROUP_PERMABANNED]['namecolor1'] = '888888';
	$grouplist[GROUP_PERMABANNED]['namecolor2'] = '888888';
	
	$grouplist[GROUP_BANNED]['namecolor0'] = '888888';
	$grouplist[GROUP_BANNED]['namecolor1'] = '888888';
	$grouplist[GROUP_BANNED]['namecolor2'] = '888888';
	
	$grouplist[GROUP_NORMAL]['namecolor0'] = '0c4e8b';
	$grouplist[GROUP_NORMAL]['namecolor1'] = '662244';
	$grouplist[GROUP_NORMAL]['namecolor2'] = '32126d';

	$grouplist[GROUP_SUPER]['namecolor0'] = '2c7eca';
	$grouplist[GROUP_SUPER]['namecolor1'] = '884455';
	$grouplist[GROUP_SUPER]['namecolor2'] = '522c97';

	$grouplist[GROUP_MOD]['namecolor0'] = '0a5427';
	$grouplist[GROUP_MOD]['namecolor1'] = '910369';
	$grouplist[GROUP_MOD]['namecolor2'] = '4b9d15';

	$grouplist[GROUP_ADMIN]['namecolor0'] = '4e4400';
	$grouplist[GROUP_ADMIN]['namecolor1'] = '570040';
	$grouplist[GROUP_ADMIN]['namecolor2'] = '5f4b00';

	$grouplist[GROUP_SYSADMIN]['namecolor0'] = '4e4400';
	$grouplist[GROUP_SYSADMIN]['namecolor1'] = '570040';
	$grouplist[GROUP_SYSADMIN]['namecolor2'] = '5f4b00';

	$newthreadpic	= '<img src="images/ymar/newthread.png" align="absmiddle">';
	$newreplypic	= '<img src="images/ymar/newreply.png" align="absmiddle">';
	$newpollpic		= '<img src="images/ymar/newpoll.png" align="absmiddle">';
	$closedpic		= '<img src="images/ymar/threadclosed.png" align="absmiddle">';

#	$numdir			= 'ccs/';																# /numgfx/<dir>/ for number images | Kept css, looks nice
	$numdir			= 'ymar/';																# I wonder if this will look better
#	$numfil			= 'numpurple';															# numgfx graphic set

	# Status icons for threads, should be self-explanatory
	$statusicons['new']			= '<img src="images/ymar/new.png">';
	$statusicons['newhot']		= '<img src="images/ymar/newhot.png">';
	$statusicons['newoff']		= '<img src="images/ymar/newoff.png">';
	$statusicons['newhotoff']	= '<img src="images/ymar/newhotoff.png">';
	$statusicons['hot']			= '<img src="images/ymar/hot.png">';
	$statusicons['hotoff']		= '<img src="images/ymar/hotoff.png">';
	$statusicons['off']			= '<img src="images/ymar/off.png">';


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
	