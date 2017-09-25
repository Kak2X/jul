<?php
	
function generatenumbergfx($num, $minlen=0, $size = 1) {
	global $numdir;

	$nw			= 8 * $size; //($double ? 2 : 1);
	$num		= (string) $num; // strval
	$len		= strlen($num);
	$gfxcode	= "";

	// Left-Padding
	if($minlen > 1 && $len < $minlen) {
		$gfxcode = "<img src='images/_.gif' style='width:". ($nw * ($minlen - $len)) ."px;height:{$nw}px'>";
	}

	for($i = 0; $i < $len; ++$i) {
		$code	= $num[$i];
		switch ($code) {
			case "/":
				$code	= "slash";
				break;
		}
		if ($code == " ") {
			$gfxcode .= "<img src='images/_.gif' style='width:{$nw}px;height:{$nw}px'>";
		} else if ($code == "i") { // the infinity symbol is just a rotated 8, right...?
			$gfxcode .= "<img src='numgfx/{$numdir}8.png' style='width:{$nw}px;height:{$nw}px;transform:rotate(90deg)'>";			
		} else {
			$gfxcode .= "<img src='numgfx/{$numdir}{$code}.png' style='width:{$nw}px;height:{$nw}px'>";
		}
	}
	return $gfxcode;
}

function drawprogressbar($width, $height, $progress, $images) {
	$on = floor($progress / 100 * $width);
	$off = $width - $on;
	return "<img src='{$images['left']}' style='height:{$height}px'>".
			"<img src='{$images['on']}' style='height:{$height}px;width:{$on}px'>".
			"<img src='{$images['off']}' style='height:{$height}px;width:{$off}px'>".
			"<img src='{$images['right']}' style='height:{$height}px'>";
}