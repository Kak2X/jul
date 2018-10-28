<?php

	// create image
	$img	= imagecreatetruecolor(300, 300);

	// we want it to have a transparent BG, no blending.
	imagealphablending($img, false);
	imagesavealpha($img, true);

	$bg				= imagecolorallocatealpha($img, 0, 0, 0, 127);

	$colors[0][0]	= imagecolorallocatealpha($img, 255, 255,   0,  90);
	$colors[0][1]	= imagecolorallocatealpha($img, 255, 255,   0, 105);
	$colors[0][2]	= imagecolorallocatealpha($img, 255, 255,   0, 120);

	$colors[1][0]	= imagecolorallocatealpha($img, 100, 100, 255,  90);
	$colors[1][1]	= imagecolorallocatealpha($img, 100, 100, 255, 105);
	$colors[1][2]	= imagecolorallocatealpha($img, 100, 100, 255, 120);

	$colors[2][0]	= imagecolorallocatealpha($img, 200, 200, 200,  80);
	$colors[2][1]	= imagecolorallocatealpha($img, 200, 200, 200, 100);
	$colors[2][2]	= imagecolorallocatealpha($img, 200, 200, 200, 115);

	imagefill($img, 0, 0, $bg);
	
	for ($i = 0; $i < 500; $i++) {
		$startype	= rand(0, 2);
		$x	= rand(2, 298);
		$y	= rand(2, 298);

		imagesetpixel($img, $x     , $y     , $colors[$startype][0]);

		if ($startype == 2) {
			imagesetpixel($img, $x     , $y + 1 , $colors[$startype][0]);
			imagesetpixel($img, $x     , $y - 1 , $colors[$startype][0]);
			imagesetpixel($img, $x - 1 , $y     , $colors[$startype][0]);
			imagesetpixel($img, $x + 1 , $y     , $colors[$startype][0]);

			imagesetpixel($img, $x + 2 , $y     , $colors[$startype][1]);
			imagesetpixel($img, $x - 2 , $y     , $colors[$startype][1]);
			imagesetpixel($img, $x     , $y - 2 , $colors[$startype][1]);
			imagesetpixel($img, $x     , $y + 2 , $colors[$startype][1]);

			imagesetpixel($img, $x + 1 , $y + 1 , $colors[$startype][2]);
			imagesetpixel($img, $x + 1 , $y - 1 , $colors[$startype][2]);
			imagesetpixel($img, $x - 1 , $y + 1 , $colors[$startype][2]);
			imagesetpixel($img, $x - 1 , $y - 1 , $colors[$startype][2]);
		} else {
			imagesetpixel($img, $x     , $y + 1 , $colors[$startype][1]);
			imagesetpixel($img, $x     , $y - 1 , $colors[$startype][1]);
			imagesetpixel($img, $x - 1 , $y     , $colors[$startype][1]);
			imagesetpixel($img, $x + 1 , $y     , $colors[$startype][1]);
		}
	}

	header("Content-type: image/png");
	imagepng($img);
	imagedestroy($img);

