<?php
	$formcss = 1;
	$config['board-title'] = $config['board-name'];
	$tablebg1    = "111";
	$tablebg2    = "110";
	$tableheadbg = "222";
	$categorybg  = "333";
	
	$bgcolor = "000";
	$tableborder = $inputborder = "FFF";
	$textcolor = $tableheadtext = "AAA";
	$linkcolor  = 'FFFFFF';
	$linkcolor2 = 'F0F0F0';
	$linkcolor3 = 'FFFFFF';
	$linkcolor4 = 'FFFFFF';	
	
	$font = $font2 = $font3 = "FixedSys, Courier, monospace";
	
	$newpollpic = "New Poll";
	$newthreadpic = "New Thread";
	$newreplypic = "New Reply";
	$closedpic = "Thread Closed";
	$css_extra = "
	body, optgroup, option {
		/*background: linear-gradient(to bottom, rgb(0,0,0) 0vh,rgb(48,0,0) 15vh,rgb(74,0,0) 33vh,rgb(92,0,0) 46vh,rgb(92,0,0) 55vh,rgb(78,0,0) 66vh,rgb(48,0,0) 83vh,rgb(0,0,0) 100vh) fixed;
		*/
		filter: grayscale(100%) url('schemes/styles.svg#amber');
	}
	/* Text glow */
	body, optgroup, input, select, button, textarea {
		text-shadow: 0px 0px 1px #FA0;
	}
	/* Glowing border */
	.table, .tdbg1, .tdbg2, .tdbgc, .tdbgh {
		box-shadow: 0px 0px 2px 1px #FFF;
	}
	select, input, button, textarea {
		box-shadow: 0px 0px 2px 0.5px #FFF;
	}
	optgroup {
		border: 1px solid #000;
	}
	.table, .font, .fonts {
		font-size: 12px !important;
	}
	a, b, .b {
		font-weight: normal !important;
		text-decoration: underline !important;
		text-shadow: 0px 0px 1.5px #FFF;
		color: #fff !important;
	}
";