<?php
	//error_reporting(0);
//	die("D'oh");
	header("Cache-Control: no-cache");

	if (isset($_GET['z'])) {
?><style>
	body { background: #111122; } 
	img { image-rendering: -moz-crisp-edges; width: 100% }
	table, td { vertical-align: middle; height: 100%; width: 100% }
</style>
<META HTTP-EQUIV=REFRESH CONTENT=1;URL="?z=<?=rand(0,9999)?>"><table><td><img src="newyear.php">
<?php
		
		die;
	}
	
	$hour_offset = 3600 * (isset($_GET['to']) ? (int)$_GET['to'] : 0);

//	require "lib/function.php";

	// Autopick next year, unless we're on Jan 1st
	$current_date = getdate(time() + $hour_offset);
	$year = $current_date['year'] + ($current_date['mday'] == 1 && $current_date['mon'] == 1 ? 0 : 1);

	$bombday		= mktime(0, 0,  0,  1, 1, $year);
	$time			= microtime(true) + $hour_offset;
	$left			= $bombday - $time;
	$left			= max(0, $left);
//	$left			= rand(0, 86400);
	$barpct			= min(64, floor($left / 3600 * 64));
	$bpad			= (64 - $barpct) / 2;
//	$hours			= str_pad(floor(($left) / 3600), 2, "o", STR_PAD_LEFT);
//	$mins			= str_pad(floor(($left % 3600) / 60), 2, "0", STR_PAD_LEFT);
	$mins			= str_pad(floor(($left) / 60), 3, "o", STR_PAD_LEFT);
	$secs			= floor(($left - floor($left / 60) * 60) * 100) / 100;
	$secs			= explode(".", $secs);
	$secs			= implode("\"", array(str_pad($secs[0], 2, "0", STR_PAD_LEFT), str_pad(count($secs) < 2 ? 0: $secs[1], 2, "0", STR_PAD_RIGHT)));

	$teststring		= "$mins'$secs";

//	$teststring		= "00d00:00:00.01";

	$image			= imagecreate(64, 9);
	$imagenum		= imagecreatefrompng("../images/digits8.png");
	$bg				= imagecolorallocate($image, 255, 0, 255);
	$black			= imagecolorallocate($image,   0, 0, 0);

	if ($left > 3600) {
		$barcol			= imagecolorallocate($image, 100, 255, 100);
	} elseif ($left >= 1800) {
		$barcolr		= 50 + 205 * ($left / 1800);
		$barcol			= imagecolorallocate($image, 100, 255, $barcolr);
	} else {
		$barcolg		= 0 + 255 * ($left / 1800 );
		$barcol			= imagecolorallocate($image, 0, $barcolg, 255);
		imagecolorset($imagenum, 2, $barcolg, $barcolg / 2 + 128, 255);
		imagecolorset($imagenum, 3, 0, $barcolg / 4, $barcolg / 1.5);
	}

	imageline($image, $bpad + 1, 8, $barpct+1 + $bpad, 8, $black);
	imageline($image, $bpad, 7, $barpct + $bpad, 7, $barcol);


	$nums			= str_split($teststring);

	$ofs			= 5;

	foreach ($nums as $n) {

		$of		= 6;

		switch ($n) {
			case ":":
				$w	= 2;
				$p	= 81;
				$of	= 2;
				break;
			case "\"":
				$w	= 4;
				$p	= 77;
				$of	= 4;
				break;
			case "'":
				$w	= 2;
				$p	= 77;
				$of	= 2;
				break;
			case "o":
				$w	= 7;
				$p	= 70;
				$of	= 7;
				break;
			case " ":
				$w	= 0;
				break;
			default:
				$w	= 7;
				$p	= 7 * $n;
				$of	= 7;
				//$ofs++;
				break;
		}

		if ($w) imagecopymerge($image, $imagenum, $ofs, 0, $p, 0, $w, 7, 100);
		
		$ofs	+= $of;
	}

	imagecolortransparent($image, $bg);
	header("Content-type: image/png");
	imagepng($image);
	imagedestroy($image);
	imagedestroy($imagenum);
