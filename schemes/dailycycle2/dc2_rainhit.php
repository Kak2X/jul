<?php

	// create image
	$img	= imagecreatetruecolor(300, 8);

	// we want it to have a transparent BG, no blending.
	imagealphablending($img, false);
	imagesavealpha($img, true);

	$bg				= imagecolorallocatealpha($img, 0, 0, 0, 127);

	$rains = imagecolorallocatealpha($img, 20, 20, 60, 70);

	imagefill($img, 0, 0, $bg);
	
	for ($i = 0; $i < 17; $i++) {

		$rx = rand(5, 299);
		$ry = 7;
		imageline($img, $rx-3, $ry, $rx+1, $ry, $rains);
		imageline($img, $rx-1, $ry-1, $rx, $ry-1, $rains);
		imagesetpixel($img, $rx-5, $ry-0,  $rains);
		imagesetpixel($img, $rx-3, $ry-2,  $rains);
		imagesetpixel($img, $rx-1, $ry-3,  $rains);
	}

	header("Content-type: image/png");
	imagepng($img);
	imagedestroy($img);

