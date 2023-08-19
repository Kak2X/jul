<?php
	// Time slot definitions, with comments... but they don't seem to match near the end?
	$dccycletime = array(
		0 =>    0, // 00:00
		1 =>  360, // 06:00
		2 =>  420, // 07:00
		3 =>  720, // 12:00
		4 => 1120, // 17:00
		5 => 1180, // 18:00
	//	6 => 1439, // 23:59
		6 => 1440, // 23:59
	);
	$cycles		= count($dccycletime);
	
	
	// Color defs...
	$dccolors[0][0]	= array(  10,  10,  90 );	//  0:00 top
	$dccolors[0][1]	= array(  00,  00,  20 );
	$dccolors[1][0]	= array(  37,  34,  68 );	//  6:00 top
	$dccolors[1][1]	= array(  20,  20,  40 );
	$dccolors[2][0]	= array( 124, 138, 148 );	//  7:00 top
	$dccolors[2][1]	= array( 180,  94,  60 );
	$dccolors[3][0]	= array( 188, 214, 224 );	// 12:00 top
	$dccolors[3][1]	= array( 132, 166, 204 );
	$dccolors[4][0]	= array( 188, 214, 224 );	// 17:00 top
	$dccolors[4][1]	= array( 132, 166, 204 );
	$dccolors[5][0]	= array( 196, 138, 220 );	// 18:00 top
	$dccolors[5][1]	= array( 252,  98,  92 );	// bottom
	$dcblack        = array(   0,   0,   0 );
	$dcclouds       = array(  50,  50,  60 );
	$dcclouds2      = array(  20,  20,  20 );
	
	// Retrieve time information...
	$curtime    = getdate(ctime() + $loguser['tzoff']);
	$min        = $curtime['hours'] * 60 + $curtime['minutes'];
	if (isset($_GET['testtime']))
		$min = (int)$_GET['testtime'];

	// ...and use it to determine the correct time slot
	$pos        = 0;
	$cyclefound = false;
	while (!$cyclefound && $pos < $cycles) {
		if ($min >= $dccycletime[$pos] && $min < $dccycletime[$pos + 1]) {
			$cyclefound = true;
			$cycle      = $pos;
			$cycletime  = $dccycletime[$pos + 1] - $dccycletime[$pos];
			$totaltime  = $dccycletime[$pos];
		}
		$pos++;
	}

	$totalpct   = $min / 1440;
	$cyclepct   = ($min - $totaltime) / $cycletime;
	$cycletime2 = $min - $totaltime;

//	$cycle = 1;
//	$rainy	= true;
	$rainy	= defined('DC2_RAIN'); //($loguser['sitescheme'] == 1);
	$starry = defined('DC2_STARS'); //($loguser['sitescheme'] == 2);
	$anim   = defined('DC2_RAIN_ANIM'); //isset($_GET['animtest']);
	if ($rainy) {
		for ($i = 0; $i < 6; $i++) {
			$dccolors[$i][0] = colorgrad($dccolors[$i][0], $dcclouds, .75);
			$dccolors[$i][1] = colorgrad($dccolors[$i][1], $dcclouds2, .8);
		}
	}

//	$cycle  = floor($min / 360);				// current cycle (one cycle = 6 hours)
	$ncycle = ($cycle > 4 ? 0 : $cycle + 1);	// next cycle

	$cyclenum  = $cycle;
	$cyclenum2 = $ncycle;

	$cycle  = $dccolors[$cycle];
	$ncycle = $dccolors[$ncycle];

	
	
//	echo "<pre>";
//	print_r($cycle);
//	print_r($ncycle);

	$formcss = 1; 
	
	$linkcolor      = 'FFD040';
	$linkcolor2     = 'F0A020';
	$linkcolor3     = 'FFEA00';
	$linkcolor4     = 'FFFFFF';
	$textcolor      = 'E0E0E0';

	$font           = 'arial';
	$font2          = 'verdana';
	$font3          = 'tahoma';
	
	$newpollpic     = 'New poll';
	$newreplypic    = 'New reply';
	$newthreadpic   = 'New thread';
	$closedpic      = 'Thread closed';

	

	$basecol        = colorgrad($cycle[0], $ncycle[0], $cyclepct);
	$basecol2       = colorgrad($cycle[1], $ncycle[1], $cyclepct);


	$bgcolor        = htmlcolor($basecol2);

	$inputborder    = '000000';
	$tableborder    = '000000';	
	$tableheadtext  = 'ffffff';
	$tableheadbg    = htmlcolor(colorgrad($basecol,  $dcblack, .30));
	$categorybg     = htmlcolor(colorgrad($basecol,  $dcblack, .425));
	$tablebg1       = htmlcolor(colorgrad($basecol,  $dcblack, .55));
	$tablebg2       = htmlcolor(colorgrad($basecol2, $dcblack, .55));

	

	// Color gradient start/end
	$grad_r1        = $basecol[0];
	$grad_r2        = $basecol2[0];
	$grad_g1        = $basecol[1];
	$grad_g2        = $basecol2[1];
	$grad_b1        = $basecol[2];
	$grad_b2        = $basecol2[2];

	
	// Well, this didn't originally show...
//	$config['board-title']   = '<font class=fonts><font style=font-size:30px>The "Just-Us" League</font></font>';
	/*$config['board-title']  .= "
	<br><span class='fonts' style='font-size: 10px'>
		Time: {$min} ({$cyclenum}). ". floor($cyclepct * 100) ."% done ({$cycletime2}/{$cycletime}).
		".($rainy ? " It is currently raining." : "")."
	</span>";*/
	
	
	// Extra effects
	$weathertext1 = $weathertext2 = "";
	if ($rainy) {
		$weathertext1	= "background: url('schemes/dailycycle2/dc2_rainbg.php'); animation: rain 2s linear 0s infinite;";
		$weathertext2	= "background:	url('schemes/dailycycle2/dc2_rainhit.php'); animation: rainhit 2s linear 0s infinite;";
	}
	if ($starry && $cyclenum < 2) {
		$weathertext1	= "background: url('schemes/dailycycle2/dc2_starsbg.php') repeat;";
	}
	
	/* background:	#$bgcolor url('schemes/dailycycle2/dc2bg.php?r1=$grad_r1&r2=$grad_r2&g1=$grad_g1&g2=$grad_g2&b1=$grad_b1&b2=$grad_b2'); */
	$css_extra = "
	body {
		background: #$bgcolor linear-gradient(to bottom, rgb({$grad_r1},{$grad_g1},{$grad_b1}) 0vh,rgb({$grad_r2},{$grad_g2},{$grad_b2}) 100vh) fixed;
		background-repeat:	repeat-x;
		background-positon: bottom center;
		/* effect incompatible with auto-margin */
		margin:  0 0 0 0;
		padding: 0 0 0 0;
		max-width: unset;
	}
	.table	{
		empty-cells:	show;
		width:			100%;
		border-collapse: collapse;
	}
	.tdbg1, .tdbg2, .tdbgc, .tdbgh {
		border:		#$tableborder 1px solid;
	}
	.super	{	
		$weathertext1
		padding:	0 0 0 0;
		margin:		0 0 0 0;
		min-height:	100vh;
	}
	.super2	{	
		padding:	0 0 0 0;
		margin:		0 25 0 25;
	}
		
	/* :( */
	.table tr:first-child .tdbg1:before,
	.table tr:first-child .tdbg2:before,
	.table tr:first-child .tdbgc:before,
	.table tr:first-child .tdbgh:before
	{
		position: relative;
		display: block;
		content: '';
		width: 100%;
		
		padding:	0 0 0 0;
		margin:		0 0 0 0;
		height:		8px;
		margin-bottom: -8px;
		top: -10px;
		
		$weathertext2
/*		border-top:		1px solid #ffffff;
		border-left:	1px solid #ffffff;
		border-right:	1px solid #ffffff;
*/		}
";
	if ($anim) {
		$css_extra .= "
			/* Background movement effects (hardcoded to image size) */
			@keyframes rain {
				from	{background-position: 301px 0px;}
				to 		{background-position: 0px 301px;}
			}
			@keyframes rainhit { /*x 0px - 1500px;*/
				from	{background-position: 700px 0px;}
				to 		{background-position: 0px 0px;}
			}
		";
	}

	$body_extra = "<div class=\"super\"><div class=\"super2\">";
	
function colorgrad($start, $end, $pct) {
	for ($i = 0; $i < 3; $i++)
		$ret[$i] = floor(($start[$i] * (1 - $pct)) + ($end[$i] * $pct));
	return $ret;
}
function htmlcolor($col) {
	$ret = "";
	for ($i = 0; $i < 3; $i++)
		$ret .= str_pad(dechex($col[$i]), 2, "0", STR_PAD_LEFT);
	return $ret;
}