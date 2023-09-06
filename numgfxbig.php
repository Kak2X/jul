<?php
	const TILE_W = 26;
	const TILE_H = 28;
	
	const TILES_IN_ROW = 5;
	
	$chars      = isset($_GET['n']) ? (string)$_GET['n'] : "";
	$min_chars  = isset($_GET['l']) ? (int)$_GET['l'] : 1;

	// Zero-padding option
	if (isset($_GET['z'])) {
		$chars	= str_pad($chars, $min_chars , "0", STR_PAD_LEFT);
	}
	
	// Always draw as many characters as the string length's
	$text_len = strlen($chars);
	
	if ($text_len > $min_chars) {
		// If the min width is less than that, make it match the string's length
		$min_chars = $text_len;
		$l_offset = 0;
	} else {
		// Otherwise, right align the string
		$l_offset = $min_chars - $text_len;
	}
	
	$img	= imagecreate(TILE_W * $min_chars, TILE_H);
	$bg		= imagecolorallocate($img, 5, 5, 5);
	$gfx	= imagecreatefrompng("images/digits.png");
	
	// Print the characters one by one
	for ($i = 0; $i < $text_len; ++$i) {
		$d = (int)$chars[$i];
		$y = floor($d / TILES_IN_ROW) * TILE_H;
		$x = ($d % TILES_IN_ROW) * TILE_W;
		imagecopy($img, $gfx, ($i + $l_offset) * TILE_W, 0, $x, $y, TILE_W, TILE_H);
	}
	
	imagecolortransparent($img, $bg);
	header("Content-type: image/png");
	imagepng($img);
	imagedestroy($img);
	imagedestroy($gfx);

