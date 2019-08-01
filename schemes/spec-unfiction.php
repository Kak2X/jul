<?php
    $config['board-title'] = '<img src="schemes/spec-unfiction/unfiction.png">';
    $formcss 	= 0;
	$usebtn		= 1;
	
    $inputborder 	= '000000';
    $bgimage 		= '';
    $bgcolor		= 'bbbbbb';
    $textcolor		= '000000';
    $linkcolor 		= '660000';
    $linkcolor2		= '553333';
    $linkcolor3		= '990033';
    $linkcolor4		= '990033';
	
    $tablebg1 		= 'aaaaaa';
    $tablebg2 		= '999999';
    $categorybg 	= '005020';
    $tableheadtext	= 'ece6da';
    $tableheadbg	= '4b3617';
	
    //$tableheadbg='b07f36';
    $tableborder 	= '000000';
    # Extra CSS included at the bottom of a page
	$css_extra		= "
		textarea,input,select,button,.button{
		  border:		1px solid #a89;
		  background:	#fff;
		  color:		#000;
		  font:	10pt $font;
		  }
		input[type='radio'], .radio {
		  border:	none;
		  background: #fff0f8;
		  color:	#ffffff;
		  font:	10pt $font;}
		input[type=submit],input[type=button],button,.button{
		  border:	#000 solid 2px;
		  font:	10pt $font;}
		.button{color: #000 !important;}
		a {
/*			text-shadow: 0px 0px 3px #fff;
*/			}
		";