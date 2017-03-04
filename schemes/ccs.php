<?php
	$formcss		= 0;		# formcss makes forms black with tableborder borders; using cssextra below is easier
	$numcols		= 100;		# same thing, more or less

	$bgimage		= 'images/ccs/bg.png';
	$config['board-title']		= '<img src="images/ccs/banner.png" title="The Horrible Forced Scheme 4/01/2009">';	# comment this out for normal banner

	$bgcolor		= 'ffbbc5';   
	$textcolor		= '000000';   

	$linkcolor		= '905070';	# Link
	$linkcolor2		= '806070'; # visited
	$linkcolor3		= '905070'; # active
	$linkcolor4		= '000000'; # hover

	$tableborder	= 'ffffff'; 
	$tableheadtext	= '402030';   
	$tableheadbg	= 'ddaabe';   
	$categorybg		= 'ddaabe';   
	$tablebg1		= 'ffddee';   
	$tablebg2		= 'eeccdd';   

	# Scrollbar colors...
	$scr1			= 'ddbbcc';	# top-left outer highlight
	$scr2			= 'ddbbcc'; # top-left inner highlight
	$scr3			= 'ffffff'; # middle face
	$scr4			= 'ddbbcc'; # bottom-right inner shadow
	$scr5			= 'ddbbcc'; # bottom-right outer shadow
	$scr6			= '000000'; # button arrows
	$scr7			= '886677';

	$grouplist[GROUP_PERMABANNED]['namecolor0'] = '888888'; # M
	$grouplist[GROUP_PERMABANNED]['namecolor1'] = '888888'; # F
	$grouplist[GROUP_PERMABANNED]['namecolor2'] = '888888'; # N/A
	
	$grouplist[GROUP_BANNED]['namecolor0'] = '888888';
	$grouplist[GROUP_BANNED]['namecolor1'] = '888888';
	$grouplist[GROUP_BANNED]['namecolor2'] = '888888';
	
	$grouplist[GROUP_NORMAL]['namecolor0'] = '000066';
	$grouplist[GROUP_NORMAL]['namecolor1'] = '662244';
	$grouplist[GROUP_NORMAL]['namecolor2'] = '442266';

	$grouplist[GROUP_SUPER]['namecolor0'] = '333388';
	$grouplist[GROUP_SUPER]['namecolor1'] = '884455';
	$grouplist[GROUP_SUPER]['namecolor2'] = '554477';

	$grouplist[GROUP_MOD]['namecolor0'] = '227722';
	$grouplist[GROUP_MOD]['namecolor1'] = '992277';
	$grouplist[GROUP_MOD]['namecolor2'] = '336633';

	$grouplist[GROUP_ADMIN]['namecolor0'] = '8E8252';
	$grouplist[GROUP_ADMIN]['namecolor1'] = '6D1F58';
	$grouplist[GROUP_ADMIN]['namecolor2'] = '876D09';

	$grouplist[GROUP_SYSADMIN]['namecolor0'] = '8E8252';
	$grouplist[GROUP_SYSADMIN]['namecolor1'] = '6D1F58';
	$grouplist[GROUP_SYSADMIN]['namecolor2'] = '876D09';

	$newthreadpic	= '<img src="images/ccs/newthread.png" align="absmiddle">';
	$newreplypic	= '<img src="images/ccs/newreply.png" align="absmiddle">';
	$newpollpic		= '<img src="images/ccs/newpoll.png" align="absmiddle">';
	$closedpic		= '<img src="images/ccs/threadclosed.png" align="absmiddle">';

	$numdir			= 'ccs/';																# /numgfx/<dir>/ for number images
#	$numfil			= 'numpurple';															# numgfx graphic set

	# Status icons for threads, should be self-explanatory
	$statusicons['new']			= '<img src="images/ccs/new.png">';
	$statusicons['newhot']		= '<img src="images/ccs/newhot.png">';
	$statusicons['newoff']		= '<img src="images/ccs/newoff.png">';
	$statusicons['newhotoff']	= '<img src="images/ccs/newhotoff.png">';
	$statusicons['hot']			= '<img src="images/ccs/hot.png">';
	$statusicons['hotoff']		= '<img src="images/ccs/hotoff.png">';
	$statusicons['off']			= '<img src="images/ccs/off.png">';


	# Extra CSS included at the bottom of a page
	$css_extra		= "
		textarea,input,select{
		  border:		1px solid #a89;
		  background:	#fff;
		  color:		#000;
		  font:	10pt $font;
		  }
		input[type=\"radio\"], .radio {
		  border:	none;
		  background: #fff0f8;
		  color:	#ffffff;
		  font:	10pt $font;}
		.submit{
		  border:	#000 solid 2px;
		  font:	10pt $font;}
		a {
/*			text-shadow: 0px 0px 3px #fff;
*/			}
		";
	