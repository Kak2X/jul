<?php

	chdir("../");
	require "lib/common.php";

	set_time_limit(0);
	ini_set("memory_limit", "256M");

	$mintime	= time() - (86400 * 30);
	
	$_GET['all'] = filter_bool($_GET['all']);
	$rangemin	= floor((int)$sql -> resultq("SELECT MIN(`time` / 3600) FROM `rendertimes`". ($_GET['all'] ? "" : "WHERE `time` > $mintime ")));
	$num		= $rangemin ? ceil(time() / 3600) - $rangemin : 1;

	$maxy			= 500;
	$scaley			= $maxy / 25;
	$scalex			= 3;
	$image			= imagecreatetruecolor($num * $scalex, $maxy);
	$col['bg']		= imagecolorallocate		($image,   0,   0,   0);
	$col['bg1']		= imagecolorallocate		($image,  30,   0,  30);
	$col['bg2']		= imagecolorallocate		($image,  50,   0,  50);
	$col['bg3']		= imagecolorallocatealpha	($image, 150,   0, 150, 90);
	$col['line']	= imagecolorallocate		($image, 255, 200,  50);
	$col['line2']	= imagecolorallocate		($image, 255, 100,  25);
	$col['line2f']	= imagecolorallocate		($image, 200,   0,   0);
	$col['line3']	= imagecolorallocatealpha	($image, 255, 100,  25, 100);
	$col['avgt']	= imagecolorallocate		($image, 255, 200, 100);
	$col['avgf']	= imagecolorallocatealpha	($image, 255,  50,  50, 90);
	$col['avgt2']	= imagecolorallocatealpha	($image, 255, 200, 100, 110);
	$col['avgf2']	= imagecolorallocatealpha	($image, 255,  50,  50, 110);
	$col['text']	= imagecolorallocate		($image, 255, 255, 255);
	$col['text2']	= imagecolorallocatealpha	($image, 255, 255, 255, 90);

	$alldata		= $sql -> query("SELECT FLOOR(`time` / 3600) AS time, ".
							"AVG( `rendertime` ) AS average, ".
							"MAX( `rendertime` ) AS max ".
							"FROM `rendertimes` ".
							"WHERE `page` = '/index.php'".
							($_GET['all'] ? "" : "AND `time` > $mintime ").
							"GROUP BY FLOOR(`time` / 3600)");

	$d	= floor($rangemin / 24) * 24 - 16;
	for ($i	= $d; $i <= ($rangemin + $num); $i+=24) {
		$x	= $i - $rangemin;
		if (floor($i / 24) % 2) {
			imagefilledrectangle($image, $x * $scalex, $maxy, ($x + 24) * $scalex - 1, 0, $col['bg2']);
		} else {
			imagefilledrectangle($image, $x * $scalex, $maxy, ($x + 24) * $scalex - 1, 0, $col['bg1']);
		}
		imageline($image, $x * $scalex, $maxy, $x * $scalex, 0, $col['bg3']);
		imagestring($image, 2, $x * $scalex + 10, 3, date("m/d", $i * 3600), $col['text2']);
	}
//	imagestring($image, 5, 5, 5, ($maxy / $scaley) ."sec", $col['text']); 

	for ($i	= 0; $i <= ($maxy / $scaley); $i++) {
		imagestring($image, 2, 3, $i * $scaley - 13, (($maxy / $scaley) - $i) ."s", $col['text2']);
		imageline($image, 0, $i * $scaley, $num * $scalex, $i * $scaley, $col['bg3']);	
	}


	$linedata = array();
	while ($data = $sql -> fetch($alldata)) {

		$x	= $data['time'] - $rangemin;
		$linedata[$x]['a']	= $data['average'];
		$linedata[$x]['m']	= $data['max'];
	}


	$averages[]	= 0;
	$averages[]	= $maxy;
	$averages2	= $averages;
	$averages2f = [];
	
	$avglen	= 12;
	$avgpos = $avgpos2 = 0;
	$overtop = false;
	for ($i = -1; $i <= $num; $i++) { 

		$oldavg		= $avgpos;
		$oldavg2	= $avgpos2;
		$avgtotal	= 0;
		$avgtotal2	= 0;
		$div		= 0;
		$avgofs		= $avglen;


		for ($avgpos = -$avgofs; $avgpos <= $avgofs; ++$avgpos) {
			$sinc		= sinc($avgpos/$avgofs);
			$thisavg	= filter_int($linedata[$i + $avgpos]['a']);
			$thisavg2	= filter_int($linedata[$i + $avgpos]['m']);
			if (($i + $avgpos) <= $num && ($i + $avgpos) >= 0) {
				$avgtotal	+= $thisavg * $sinc;
				$avgtotal2	+= $thisavg2 * $sinc;
				$div		+= $sinc;
			}
		}

		$avgpos		= $avgtotal / $div;
		$yposavg	= $maxy - ($avgpos * $scaley); 
		$avgpos2	= $avgtotal2 / $div;
		$yposavg2	= $maxy - ($avgpos2 * $scaley); 
		$xpos		= $i * $scalex;
		
		$averages[]	= $xpos;
		$averages[]	= round($yposavg);

		if ($overtop && $yposavg2 < 0) {
			$averages2[]	= $xpos;
			$averages2[]	= round($yposavg2);
			$averages2f[]	= round($yposavg2) + $maxy;
			$averages2f[]	= $xpos;

		} elseif (!$overtop && $yposavg2 < 0) {
			$averages2[]	= $xpos;
			$averages2[]	= round($yposavg2);
			$averages2f[]	= round($yposavg2) + $maxy;
			$averages2f[]	= $xpos;
			$averages2f[]	= $maxy;
			$averages2f[]	= $xpos - $scalex;
			$overtop		= true;

		} elseif ($overtop && $yposavg2 >= 0) {
			$averages2[]	= $xpos;
			$averages2[]	= round($yposavg2);
			$averages2f[]	= $maxy;
			$averages2f[]	= $xpos + $scalex;
			$overtop		= false;

		} elseif (!$overtop && $yposavg2 >= 0) {
			$averages2[]	= $xpos;
			$averages2[]	= round($yposavg2);
		}
	} 

	$averages[]	= $num * $scalex;
	$averages[]	= $maxy;
	$averages2[]= $num * $scalex;
	$averages2[]= $maxy;
	if ($averages2f) {
		$averages2f	= array_reverse($averages2f);
		$averages2	= array_merge($averages2, $averages2f);
	}

	drawfilledpolygon($image, $averages2, $col['avgt2'], $col['avgf2']);


	foreach ($linedata as $x => $nums) {

		$y	= (int)($maxy - ($nums['a'] * $scaley));
		$y2	= (int)($maxy - ($nums['m'] * $scaley));

//		imagefilledrectangle($image, $x * $scalex, $maxy, ($x + 1) * $scalex - 1, $y2, $col['line3']);

		if ($y < 0) {
			imagefilledrectangle($image, $x * $scalex, $maxy, ($x + 1) * $scalex - 2, $y, $col['line2']);
			imagefilledrectangle($image, $x * $scalex, $maxy, ($x + 1) * $scalex - 2, $y + $maxy, $col['line2f']);
			imagestring($image, 1, $x * $scalex - 41, 3, number_format($nums['a'], 2) ."sec", $col['line2']);
		} else {
			imagefilledrectangle($image, $x * $scalex, $maxy, ($x + 1) * $scalex - 2, $y, $col['line']);
		}

	}

	drawfilledpolygon($image, $averages, $col['avgt'], $col['avgf']);



	header_content_type("image/png");
	imagepng($image);
	imagedestroy($image);





	function sinc($x) {
		$ret	= ($x ? sin($x*pi())/($x*pi()) : 1);
		return $ret;
	}