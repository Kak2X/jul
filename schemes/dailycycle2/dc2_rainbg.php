<?php

	// create image
	$img	= imagecreatetruecolor(300, 300);

	// we want it to have a transparent BG, no blending.
	imagealphablending($img, false);
	imagesavealpha($img, true);

	$bg				= imagecolorallocatealpha($img, 0, 0, 0, 127);

	$rains = imagecolorallocatealpha($img, 20, 20, 60, 60);

	imagefill($img, 0, 0, $bg);
	
	for ($i = 0; $i < 270; $i++) {

		$rx = rand(0, 300);
		$ry = rand(0, 300);
	//	$dx = rand(1, 3);
		$dy = rand(5, 6);
		imageline($img, $rx, $ry, $rx + 3, $ry - $dy, $rains);
	}

	header("Content-type: image/png");
	imagepng($img);
	imagedestroy($img);
