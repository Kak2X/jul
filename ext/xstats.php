<?php

	chdir("..");
	require "lib/common.php";
	
	// now the file name isn't accurate anymore
	$user   = filter_int($_GET['u']);
	if (!$user)
		$user = 1;

	$img	= imagecreate(45, 37);
	$bg		= imagecolorallocate($img, 100, 100, 100);
	$num	= imagecreatefrompng("images/digitstiny.png");
	
	$xk		= $sql -> fetchq("SELECT * FROM `users` WHERE `id` = '{$user}'");
	if (!$xk)
		die("User not found.");
	$thread	= $sql -> resultq("SELECT COUNT(`id`) FROM `threads` WHERE `user` = '{$user}'");

	$exp	= calcexp($xk['posts'], (time() - $xk['regdate']) / 86400);
	$level	= calclvl($exp);
	$expt	= totallvlexp($level);
	$expl	= $expt - calcexpleft($exp);

	drawnum($img, $num,  0,  0 + ( 0 * 6), $thread       ,  9);
	drawnum($img, $num,  0,  0 + ( 1 * 6), $xk['posts']  ,  9);
	drawnum($img, $num,  0,  1 + ( 2 * 6), $level        ,  9);
	drawnum($img, $num,  0,  1 + ( 3 * 6), $expl         ,  9);
	drawnum($img, $num,  0,  1 + ( 4 * 6), "/". $expt    ,  9);
	drawnum($img, $num,  0,  1 + ( 5 * 6), $exp          ,  9);



	imagecolortransparent($img, $bg);
	header_content_type("image/png");
	imagepng($img);
	imagedestroy($img);
	imagedestroy($num);


	function drawnum($img, $num, $x, $y, $n, $l = 0, $z = false, $dx = 5, $dy = 6) {

		$p	= 0;

		if ($z) {
			$n	= str_pad($n, $l, "0", STR_PAD_LEFT);
		}

		if (strlen($n) > $l) $l = strlen($n);
		elseif (strlen($n) < $l) $p = $l - strlen($n);

		$o		= $p;

		$na		= str_split($n);
		foreach ($na as $digit) {
			$xd	= intval($digit);
			if ($digit == "/") $xd	= 10;
			if ($digit == " ") {
				$o++;
				continue;
			}

			imagecopy($img, $num, $x + $o * $dx, $y, $xd * $dx, 0, $dx, $dy);
			$o++;
		}

	}
