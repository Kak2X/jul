<?php
	// Update the set of colors every 4 hours
	if (isset($_GET['modtime']))
		$modtime 	= $_GET['modtime'];
	else
		$modtime	= getdate(time() + $loguser['tzoff'])['hours'] % 6;
	
	switch ($modtime) {
		case 0:
			$bgcolor	= "#060306";
			$bgimage	= "schemes/crystalcycle/hiddenpurple.png";
			$border		= "#0A020A";
			$col1		= "rgba(41, 0, 83, 0.85)";
			$col2		= "rgba(13, 0, 27, 0.85)";
			$col3		= "rgba(32, 0, 63, 0.85)";
			$col4		= "rgba(23, 0, 47, 0.85)";
			$col5		= "rgba(18, 0, 35, 0.85)";
			break;
		case 1:
			$bgcolor	= "#050303";
			$bgimage	= "schemes/crystalcycle/hiddenred.png";
			$border		= "#120200";
			$col1		= "rgba(100, 24, 0, 0.85)";
			$col2		= "rgba(34, 6, 0, 0.85)";
			$col3		= "rgba(76, 19, 0, 0.85)";
			$col4		= "rgba(56, 14, 0, 0.85)";
			$col5		= "rgba(42, 11, 0, 0.85)";
			break;
		case 2:
			$bgcolor	= "#060603";
			$bgimage	= "schemes/crystalcycle/hiddenyellow.png";
			$border		= "#0A0A02";
			$col1		= "rgba(100, 92, 0, 0.85)";
			$col2		= "rgba(34, 23, 0, 0.85)";
			$col3		= "rgba(76, 66, 0, 0.85)";
			$col4		= "rgba(56, 49, 0, 0.85)";
			$col5		= "rgba(42, 34, 0, 0.85)";
			break;
		case 3:
			$bgcolor	= "#030705";
			$bgimage	= "schemes/crystalcycle/hiddengreen.png";
			$border		= "#001202";
			$col1		= "rgba(0, 100, 24, 0.85)";
			$col2		= "rgba(0, 34, 6, 0.85)";
			$col3		= "rgba(0, 76, 19, 0.85)";
			$col4		= "rgba(0, 56, 14, 0.85)";
			$col5		= "rgba(0, 42, 11, 0.85)";
			break;
		case 4:
			$bgcolor	= "#040507";
			$bgimage	= "schemes/crystalcycle/hiddencyan.png";
			$border		= "#020A0A";
			$col1		= "rgba(0, 83, 41, 0.85)";
			$col2		= "rgba(0, 27, 13, 0.85)";
			$col3		= "rgba(0, 63, 32, 0.85)";
			$col4		= "rgba(0, 47, 23, 0.85)";
			$col5		= "rgba(0, 35, 18, 0.85)";
			break;
		default:
			$bgcolor	= "#06060b";
			$bgimage	= "schemes/crystalcycle/hiddenblue.png";
			$border		= "#000212";
			$col1		= "rgba(0, 24, 100, 0.85)";
			$col2		= "rgba(0, 6, 34, 0.85)";
			$col3		= "rgba(0, 19, 76, 0.85)";
			$col4		= "rgba(0, 14, 56, 0.85)";
			$col5		= "rgba(0, 11, 42, 0.85)";
			break;
	}
	
	$formcss		= 1;		# Styles input fields, set to 0 if you want to use system default.
	$hashpfx		= false;    // TEMPORARY HACK
	
	$textcolor		= '#EEEEEE'; # Main text color
	# Links
	$linkcolor		= '#FFD040'; # Unvisited link
	$linkcolor2		= '#F0A020'; # Visited
	$linkcolor3		= '#FFEA00'; # Active
	$linkcolor4		= '#FFFFFF'; # Hover

	$inputborder    = $border; # Border color for input elements
	$formtextcolor  = "#FFFFFF"; # Text color for input elements
	$formcolor      = $col2; # BG color for input elements
	$tableborder	= $border; # Border color for tables
	$tableheadtext	= '#EEEEEE'; # Table header text color
	$tableheadbg	= $col2; # Table header background (you can use images)
	$categorybg		= $col1; # Category BG
	$tablebg1		= $col3; # Table cell 1 background
	$tablebg2		= $col4; # Table cell 2 (the darker one, usually)

	# Scrollbar colors...
	//$scr1			= '#aaaaff'; # top-left outer highlight
	//$scr2			= '#9999ee'; # top-left inner highlight
	//$scr3			= '#7777bb'; # middle face
	//$scr4			= '#555599'; # bottom-right inner shadow
	//$scr5			= '#444488'; # bottom-right outer shadow
	//$scr6			= '#000000'; # button arrows
	//$scr7			= '#000033'; # track
	
	// "background-attachment: fixed;" was not here before
	$css_extra		= "
		body {
			background-attachment: fixed;
			background-position: bottom; 
			background-repeat: repeat-x;
		}
		input[type=radio],input[type=checkbox] {
			background: $col2;
			border: 1px solid $border;
			color: #B7B7B7;
		}
		input[type=submit], input[type=button], button, .button {	
			border: 1px solid $border;
			background: $col1;
			color: #EEEEEE;
		}
	";
	
	if (isset($_GET['rot'])) {
		$css_extra .= "
			.tdbg1, .tdbg2 {
				animation: tdbg1to2 2s linear 0s infinite;
			}
			.tdbg1 {
				animation-direction: alternate;
			}
			.tdbg2 {
				animation-direction: alternate-reverse;
			}
			.tdbgh, .tdbgc {
				animation: tdbghto2c 4s linear 1s infinite;
			}
			.tdbgh {
				animation-direction: alternate;
			}
			.tdbgc {
				animation-direction: alternate-reverse;
			}	
			@keyframes tdbg1to2 {
				0%	 {background: $tablebg1}
				100% {background: $tablebg2}
			}
			@keyframes tdbghto2c {
				0%	 {background: $tableheadbg}
				100% {background: $categorybg}
			}
		";
	}