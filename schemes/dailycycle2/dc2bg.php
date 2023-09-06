<?php
	//die;
	
	$_GET['length'] = isset($_GET['length']) ? (int)$_GET['length'] : 0;	
	if (!$_GET['length'])		$_GET['length'] = 255;
	if ($_GET['length'] > 255)	$_GET['length'] = 255;
	
	$keys = ['r1','r2','g1','g2','b1','b2'];
	foreach ($keys as $k)
		$_GET[$k] = isset($_GET[$k]) ? numrange($_GET[$k], 0, 255) : mt_rand(0, 255);
	
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
	header("Content-type: image/png");
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

function numrange($n, $lo, $hi) {
	return max(min($hi, (int)$n), $lo);
}

