<?php

	const TILE_W = 8;
	const TILE_H = 8;
	
	$chars      = isset($_GET['n']) ? (string)$_GET['n'] : "";
	$min_chars  = isset($_GET['l']) ? (int)$_GET['l'] : 1;
	$file       = isset($_GET['f']) ? basename($_GET['f']) : "";
	if (!$file) 
		$file = 'numnes';
	
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
	
	$gfx = ImageCreateFromPNG("numgfx/{$file}.png");
	$img = ImageCreate($min_chars * TILE_W, TILE_H);
	
	// ???
	//ImageCopy($img, $gfx, 0, 0, 104, 0, 1, 1);
	
	// Print the characters one by one
	for ($i = 0; $i < $text_len; ++$i) {
		switch ($chars[$i]) {
			case '/': $d = 10; break;
			case 'N': $d = 11; break;
			case 'A': $d = 12; break;
			case '-': $d = 13; break;
			default: $d = (int)$chars[$i];
		}
		ImageCopy($img, $gfx, ($i + $l_offset) * TILE_W, 0, $d * TILE_W, 0, TILE_W, TILE_H);
	}
	
	Header('Content-type:image/png');
	
	// ??? - This looks wrong
	// $ctp = ($file == "numdeath");
	$ctp = false;
	
	ImageColorTransparent($img,$ctp);
	ImagePNG($img);
	ImageDestroy($img);