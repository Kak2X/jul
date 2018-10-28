<?php
	die;
	
	$_GET['length'] = isset($_GET['length']) ? (int)$_GET['length'] : 0;	
	if (!$_GET['length'])		$_GET['length'] = 255;
	if ($_GET['length'] > 255)	$_GET['length'] = 255;
	
	$maxlen	= 512;
	$img	= ImageCreatetruecolor(8, $_GET['length']);
	$img2	= imagecreatetruecolor(8, $maxlen);
	for ($x = 0; $x <= $_GET['length']; $x++) {

		$px = $x / $_GET['length'];
		$rx = calc($_GET['r1'], $_GET['r2'], $px);		
		$gx = calc($_GET['g1'], $_GET['g2'], $px);		
		$bx = calc($_GET['b1'], $_GET['b2'], $px);		

		$colors[$x] = imagecolorallocate($img, $rx, $gx, $bx);
		imageline($img, 0, $x, 7, $x,  $colors[$x]);
	}

	imagecopyresampled($img2, $img, 0, 0, 0, 0, 8, $maxlen, 8, 255);
	Header("Content-type:image/png");
	ImagePNG($img2);
	imagedestroy($img);
	imagedestroy($img2);

function calc ($c1, $c2, $p) {
	// c1 : start
	// c2 : end
	// p  : % to end
	
	$c = ($c2 * $p) + ($c1 * (1 - $p));
	return $c;
}

