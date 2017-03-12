<?php
	
function generatenumbergfx($num, $minlen=0, $double = false) {
	global $numdir;

	$nw			= 8 * ($double ? 2 : 1);
	$num		= (string) $num; // strval
	$len		= strlen($num);
	$gfxcode	= "";

	// Left-Padding
	if($minlen > 1 && $len < $minlen) {
		$gfxcode = "<img src='images/_.gif' width=". ($nw * ($minlen - $len)) ." height=$nw>";
	}

	for($i = 0; $i < $len; ++$i) {
		$code	= $num[$i];
		switch ($code) {
			case "/":
				$code	= "slash";
				break;
		}
		if ($code == " ") {
			$gfxcode.="<img src='images/_.gif' width=$nw height=$nw>";

		} else {
			$gfxcode.="<img src='numgfx/$numdir$code.png' width=$nw height=$nw>";

		}
	}
	return $gfxcode;
}