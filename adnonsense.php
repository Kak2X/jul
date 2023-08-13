<?php

//	die();
	require "lib/common.php";

	const IMAGE_X = 622; // Obligatory image def
	const IMAGE_Y = 22;
	const BAR_MONEY = 120; // A full bar represents this cash
	
	
	const BAR_W = IMAGE_X - IMAGE_Y; // 21 - 1
	const BAR_H = IMAGE_Y - 1;

	
	$_GET['m'] = filter_string($_GET['m']);
	
	//header("Cache-Control: max-age=43200");
	//header('Expires: '.gmdate('D, d M Y H:i:s', time() + 43200).' GMT');

	// This needs to have a transparent background
	$img	= imagecreatetruecolor(IMAGE_X, IMAGE_Y);	
	imagealphablending($img, false);
	imagesavealpha($img, true);
	imagefilledrectangle($img, 0, 0, IMAGE_X, IMAGE_X, imagecolorallocatealpha($img, 255, 0, 0, 127));

	
	$font	= imageloadfont("images/terminal6.gdf");

	// Draw the 4 borders
	//                  X1    Y1             X2     Y2
	imageline($img, BAR_H,     0, BAR_H + BAR_W,     0, 0x000000); // Top
	imageline($img,     0, BAR_H,         BAR_W, BAR_H, 0x000000); // Bottom
	imageline($img,     0, BAR_H,         BAR_H,     0, 0x000000); // Left (gets overwritten anyway)
	imageline($img, BAR_W, BAR_H, BAR_H + BAR_W,     0, 0x000000); // Right


	$data	= $sql->fetchq("SELECT `donations`, `ads`, `valkyrie` FROM `misc`");
	$bonusr	= 0;
	$bonusg	= 0;
	$bonusb	= 0;

	// Determine the color settings
	if ($_GET['m'] == "d") {
		$money	= $data['donations'];
		$text	= "Donations";
		$textc	= imagecolorallocatealpha($img,  80, 200,  80, 40);
		if ($money >= BAR_MONEY) {
			$money	-= BAR_MONEY;
			$bonusr	= -.5;
			$bonusg	= .1;
			$bonusb	= -.5;
			$bonusm	= BAR_MONEY;
		}
	} elseif ($_GET['m'] == "t") {
		$money	= $data['donations'] + $data['ads'];
		$text	= "Total";
		$textc	= imagecolorallocatealpha($img, 140, 140, 255, 40);
		if ($money >= BAR_MONEY) {
			$money	= min(BAR_MONEY, $data['donations']) + $data['ads'] - BAR_MONEY;	// Extra donations don't count towards extra funding
//			$money	= $data['donations'] + $data['adsense'] - BAR_MONEY;
//			$text	= "Extra!";
			$bonusr	= -.5;
			$bonusg	= -.5;
			$bonusb	= .3;
		}

	} elseif ($_GET['m'] == "v") {
		$money	= $data['valkyrie'];
		$text	= "VPS fund";
		$textc	= imagecolorallocatealpha($img, 140, 140, 255, 40);
		if ($money >= BAR_MONEY) {
			$money	-= BAR_MONEY;
			$bonusr	= -.5;
			$bonusg	= -.5;
			$bonusb	= .3;
		}
	} else {
		$money	= $data['ads'];
		$text	= "Ad rev.";
		$textc	= imagecolorallocatealpha($img, 255,  80,  80,  40);
		if ($money >= BAR_MONEY) {
			$money	-= BAR_MONEY;
			$bonusr	= .3;
			$bonusg	= -.3;
			$bonusb	= -.3;
			$bonusm	= BAR_MONEY;
		}
	}
	
	// Save some time by calculating the max here (so we don't need to draw the entirety of the grayscale bg bar)
	$max	= min(BAR_W, ($money / BAR_MONEY) * BAR_W);

	// Draw the back of the bar (if grayscale depends on filling the bar)
	for ($i = $max; $i < BAR_W; $i++) {
		$c	= floor($i / BAR_W * 100) + 27; // Gray progressively gets lighter
		if ($i % 50 == 0 && $i <= BAR_W - 3) {
			$c = floor($c * 0.8); // 50px Indicator
		} else if ($i >= BAR_W - 3) {
			$c = floor($c * (1.3 + ($i == 599 ? 0.4 : 0.0))); // Rightmost border
		} else if ($i <= 2) {
			$c = floor($c * (1.3 + ($i == 0 ? 0.4 : 0.0))); // Leftmost border
		}
		
		fillbar($img, $i, $c * (1 + $bonusr ), $c * (1 + $bonusg ), $c * (1 + $bonusb ));
	}
	
	// Draw the bar proper
	for ($i = 0; $i < $max; $i++) {
		if ($_GET['m'] == "d") {	// Donations use a different color scheme
			$r	=  20 + floor($i / BAR_W * 100);
			$g	=  80 + floor($i / BAR_W * 170);
			$b	=  25 + floor($i / BAR_W * 110);
		} else if ($_GET['m'] == "t" || $_GET['m'] == "v") {
			$b	= 100 + floor($i / BAR_W * 150);
			$g	=  20 + floor($i / BAR_W *  60);
			$r	=  25 + floor($i / BAR_W *  80);
		} else {
			$r	= 100 + floor($i / BAR_W * 150);
			$g	=  20 + floor($i / BAR_W *  60);
			$b	=  25 + floor($i / BAR_W *  80);
		}
		if ($i % 50 == 0 && $i <= $max - 3) { // Every 50 px, draw a dark indicator
			$r = floor($r * 0.8);
			$g = floor($g * 0.8);
			$b = floor($b * 0.8);
		} else if ($i >= $max - 3) { // Shading on the rightmost edge of the bar
			$r = floor($r * (1.3 + ($i == $max - 1 ? 0.4 : 0.0)));
			$g = floor($g * (1.3 + ($i == $max - 1 ? 0.4 : 0.0)));
			$b = floor($b * (1.3 + ($i == $max - 1 ? 0.4 : 0.0)));
		} else if ($i <= 2) { // Shading on the leftmost edge of the bar
			$r = floor($r * (1.3 + ($i == 0 ? 0.4 : 0.0)));
			$g = floor($g * (1.3 + ($i == 0 ? 0.4 : 0.0)));
			$b = floor($b * (1.3 + ($i == 0 ? 0.4 : 0.0)));
		}


		fillbar($img, $i, $r, $g, $b);
/*		imageline($img, $i + 1,  20, $i + 20,   1, 0x010101 * $c);
		imagesetpixel($img, $i + 20,  1, 0x010101 * min(255, floor($c * 1.7)));
		imagesetpixel($img, $i + 19,  2, 0x010101 * min(255, floor($c * 1.3)));
		imagesetpixel($img, $i +  2, 19, 0x010101 * min(255, floor($c * 1.3)));
		imagesetpixel($img, $i +  1, 20, 0x010101 * min(255, floor($c * 1.7)));
*/	}

	// Draw the money text
	$s	= sprintf("\$%01.2f", $money + $bonusm);
	// Is there enough space to print the money text in the filled bar?
	if ($max > 50) { // Yes
		$s	= str_pad($s, 7, " ", STR_PAD_LEFT);
		$x	= $max - 41;
	} else {
		$x	= $max + 12; // No, write outside
	}
	$y = BAR_H - 10; //12;
	imagestring($img, $font, $x + 1, $y + 1, $s, 0x000000);
	imagestring($img, $font, $x, $y, $s, 0xffffff);

	// And the bar description, which uses alpha blending
	imagealphablending($img, true);
	imagestring($img, $font, BAR_H, 3, $text, $textc);


	header("Content-type: image/png;");
	imagepng($img);
	imagedestroy($img);


	function fillbar($img, $i, $r, $g, $b) {
		$r	= min(255, $r);
		$g	= min(255, $g);
		$b	= min(255, $b);
		
		// Filled vertical bar height (also influences the x position
		$barch = BAR_H - 1;
		
		// Bulk of the bar 
		imageline($img, $i + 1,  $barch, $i + $barch,   1, imagecolorallocate($img, $r, $g, $b));
		// Shading effect on top...                                             R                          G                          B
		imagesetpixel($img, $i + $barch,  1, imagecolorallocate($img, min(255, floor($r * 1.7)), min(255, floor($g * 1.7)), min(255, floor($b * 1.7))));
		imagesetpixel($img, $i + $barch - 1,  2, imagecolorallocate($img, min(255, floor($r * 1.3)), min(255, floor($g * 1.3)), min(255, floor($b * 1.3))));
		// ...and on the bottom
		imagesetpixel($img, $i +  2, $barch - 1, imagecolorallocate($img, min(255, floor($r * 1.3)), min(255, floor($g * 1.3)), min(255, floor($b * 1.3))));
		imagesetpixel($img, $i +  1, $barch, imagecolorallocate($img, min(255, floor($r * 1.7)), min(255, floor($g * 1.7)), min(255, floor($b * 1.7))));
	}