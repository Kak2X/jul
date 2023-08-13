<?php

	const SCALE_X   = 1;
	const SCALE_Y   = 200; // 500
	
	const SECTOR_H  = 50;
	
	const THRESHOLD = 5000;
	
	//const HIGHLIGHT = '08-05-2009';
	
	// Legend box with usernames (easily configurable just in case)
	const BOX_X = 60;
	const BOX_Y = 10;
	const BOX_W = 143;
	
	require "lib/common.php";

	// Get the first registration date (without time info)
	$regdate    = $sql->resultq("SELECT MIN(`regdate`) FROM users");
	$regday     = floor($regdate / 86400); // Day number
	$regday_ts  = $regday * 86400;         // Day timestamp
	// $regday_ts2 = $regday_ts + 86400 // ?

	$days = ceil((ctime() - $regday_ts) / 86400); // Days the board has been opened
	
	
	$max   = ceil(($sql->resultq("SELECT COUNT(*) FROM `posts`") + 1) / THRESHOLD) * THRESHOLD;
	// $max = 5500;
	
	$alen				= (isset($_GET['len']) ? $_GET['len'] : 30);
	$alen				= min(max(7, $alen), 90);
	
	define('IMAGE_X', $days * SCALE_X);
	define('IMAGE_Y', $max / SCALE_Y);

	$img = ImageCreateTrueColor(IMAGE_X,IMAGE_Y);

	$c['bg'] =ImageColorAllocate($img,  0,  0,  0);
	$c['bg1']=ImageColorAllocate($img,  0,  0, 60);
	$c['bg2']=ImageColorAllocate($img,  0,  0, 80);
	$c['bg3']=ImageColorAllocate($img, 40, 40,100);
	$c['bg4']=ImageColorAllocate($img,100, 40, 40);
	$c['mk1']=ImageColorAllocate($img, 60, 60,130);
	$c['mk2']=ImageColorAllocate($img, 80, 80,150);
	$c['bar']=ImageColorAllocate($img,250,190, 40);
	$c['pt'] =ImageColorAllocate($img,250,250,250);
	
	// Draw background
	//$check = floor(mktime(0,0,0,substr(HIGHLIGHT,0,2),substr(HIGHLIGHT,3,2),substr(HIGHLIGHT,6,4)) / 86400) * 86400;
	for ($i = 0; $i < $days; ++$i) {
		$ts = $regday_ts + $i * 86400;
		//echo "{$ts} - {$check}<br>";
		$md = date('m-d', $ts);
		/*if ($ts == $check || $ts - $alen * 86400 == $check) {
			$num = 4; 
		} else */ if ($md == '01-01') { // New year?
			$num = 3;
		} else {
			$num = substr($md, 0, 2) % 2 + 1; // Alternate between months
		}
		ImageFilledRectangle($img, $i * SCALE_X, IMAGE_Y, ($i + 1) * SCALE_X - 2, 0, $c["bg$num"]);
	}
	
	// Postcount indicator for each sector; with separator lines
	$sect_x2   = SECTOR_H * 2; // yeah
	$digits    = strlen((string) $max);
	$r_padding = $digits * 7 + 2; // Font 3 is 7 px wide
	for ($y = IMAGE_Y - SECTOR_H; $y >= 0; $y -= SECTOR_H) {
		$color = ($y % $sect_x2) ? $c['mk1'] : $c['mk2']; // Start from mk1 and loop back and forth for each limit
		$posts = (IMAGE_Y - $y) * SCALE_Y;
		
		ImageLine($img, 0, $y, IMAGE_X, $y, $color);
		// On both sides here
		ImageString($img, 3, 3, $y + 1, $posts, $c['bg']);
		ImageString($img, 3, 2,     $y, $posts, $color);
		ImageString($img, 3, IMAGE_X - $r_padding + 1, $y + 1, sprintf("%{$digits}d", $posts), $c['bg']);
		ImageString($img, 3, IMAGE_X - $r_padding   ,     $y, sprintf("%{$digits}d", $posts), $color);
	}


	$users	= array(
		  0 => array('name' => "Total posts",                  'color' =>  imagecolorallocate($img, 255, 255, 255)),
		 -1 => array('name' => "$alen-day average x ".SCALE_Y, 'color' =>  0xFF8888),
	/*	 50 => array('name' => "Hyperhacker    ", 'color' =>  imagecolorallocate($img,  50, 255,  50)),
		 61 => array('name' => "E. Prime       ", 'color' =>  imagecolorallocate($img, 200, 200,   0)),
		 18 => array('name' => "Hiryuu         ", 'color' =>  imagecolorallocate($img, 255,  50,  50)),
		 17 => array('name' => "NightKev       ", 'color' =>  imagecolorallocate($img, 200,   0, 200)),
//		  5 => array('name' => "Hydrapheetz    ", 'color' =>  imagecolorallocate($img,  50,  50, 255)),
		  3 => array('name' => "cpubasic13     ", 'color' =>  imagecolorallocate($img,   0, 200, 255)),
		 52 => array('name' => "Shadic         ", 'color' =>  imagecolorallocate($img, 100,  50, 200)),
		 57 => array('name' => "Kles           ", 'color' =>  imagecolorallocate($img,  50, 200, 100)),
		 12 => array('name' => "Dorito         ", 'color' =>  imagecolorallocate($img, 200, 100,  50)),

		 36 => array('name' => "Erika          ", 'color' =>  imagecolorallocate($img, 220, 100, 170)),
		100 => array('name' => "Kas            ", 'color' =>  imagecolorallocate($img, 220, 170, 100)),
		117 => array('name' => "Rydain         ", 'color' =>  imagecolorallocate($img, 220, 220,  79)),
		118 => array('name' => "Aiya           ", 'color' =>  imagecolorallocate($img, 170, 150, 255)),
		175 => array('name' => "Tina           ", 'color' =>  imagecolorallocate($img, 255, 100, 255)),
		387 => array('name' => "Acmlm          ", 'color' =>  imagecolorallocate($img, 233, 190, 153)),
		 49 => array('name' => "Dr. Sophie     ", 'color' =>  imagecolorallocate($img, 193, 210, 233)),
	*/
//		  2 => array('name' => "Drag           ", 'color' =>  imagecolorallocate($img, 255,   0,   0)),

	);

	$z	= count($users);
	const NAME_HEIGHT = 12;
	// Draw the legend background box
	imagerectangle(      $img, BOX_X + 1, BOX_Y + 1, BOX_X + BOX_W + 1, BOX_Y + 5 + $z * NAME_HEIGHT, $c['bg']);  // Shadow
	imagefilledrectangle($img, BOX_X    , BOX_Y    , BOX_X + BOX_W    , BOX_Y + 4 + $z * NAME_HEIGHT, $c['bg2']); // Background
	imagerectangle(      $img, BOX_X    , BOX_Y    , BOX_X + BOX_W    , BOX_Y + 4 + $z * NAME_HEIGHT, $c['mk2']); // Border
	
	
	
	// Get the data for the real user IDs (id > 0) if they are present
	$realusers = array_filter(array_keys($users), function($v){return $v > 0;});
	$data = $realusers ? getdata($realusers) : array();
	// Get the total as well
	$data += getdata();
	
	$z = 0;
	foreach($users as $uid => $userx) {
		// Negative IDs are special and are handled separately
		if ($uid >= 0) {
			drawdata($data[$uid], $userx['color']);
		}
		// 10px Dash next to the name...
		imageline($img, BOX_X + 6, BOX_Y + 9 + $z * NAME_HEIGHT, BOX_X + 6 + 10, BOX_Y + 9 + $z * NAME_HEIGHT, $c['bg']);
		imageline($img, BOX_X + 5, BOX_Y + 8 + $z * NAME_HEIGHT, BOX_X + 5 + 10, BOX_Y + 8 + $z * NAME_HEIGHT, $userx['color']);
		// And the name proper...
		imagestring($img, 2, BOX_X + 21, BOX_Y + 2 + $z * NAME_HEIGHT, $userx['name'], $c['bg']);
		imagestring($img, 2, BOX_X + 20, BOX_Y + 1 + $z * NAME_HEIGHT, $userx['name'], $userx['color']);
		++$z;
	}

	// Draw the line for the average
	$average = getxdata();
	/*print "<pre>days = $days \n\n\n";
	print_r($data);
	print "\n\n------------------------\n\n";
	print_r($average);
	die();*/
	drawdata($average, $users[-1]['color']);
	
	//errorpage("check the error log");

	Header('Content-type:image/png');
	ImagePNG($img);
	ImageDestroy($img);


// Draw progression of user's postcount
function drawdata($p, $color) {
	global $days, $img;
	$oldy = IMAGE_Y; // We start from the bottom
	for ($i = 0; $i < $days; ++$i){
		if (!isset($p[$i])) { // If nothing was posted, we keep the previous value
			$y	= $oldy;
		} else {
			$y  = IMAGE_Y - $p[$i];
		}
		$x      = $i * SCALE_X;
		imageline($img, $x, $oldy, $x + SCALE_X - 1, $y, $color);
		$oldy   = $y;
	}
}

function getdata($users = NULL) {
	global $sql, $regday;
	
	if ($users !== NULL) { // Actual users in the list
		// Initialize user total
		$ucount = count($users);
		$total  = array();
		for ($i = 0; $i < $ucount; ++$i) {
			$total[$users[$i]] = 0;
		}
	
		$postdays = $sql->query("
			SELECT user, FLOOR(date / 86400) day, COUNT(*) c
			FROM posts 
			WHERE user IN (".implode(',',$users).") 
			GROUP BY user, day 
			ORDER BY user, day
		");
	} else {
		// Get total of every user regardless of user; so grab all the users as user 0 (which is the key for the total posts in $users)
		$total[0] = 0;
		$postdays = $sql->fetchq(
			"SELECT 0 user, FLOOR(date / 86400) day, COUNT(*) c ".
			"FROM posts ".
			"GROUP BY day ".
			"ORDER BY day "
		, PDO::FETCH_ASSOC, mysql::FETCH_ALL | mysql::USE_CACHE);
	}

	$resp  = array();
	// For every user, return the total
	// Since it's ordered already by day we can safely add to the total without issues
	//while ($x = $sql->fetch($postdays)) {
	foreach ($postdays as $x) {
		$total[$x['user']] += $x['c'];
		$resp[$x['user']][$x['day'] - $regday] = $total[$x['user']] / SCALE_Y;
	}
	
	return $resp;
}

function getxdata() {
	global $sql, $alen, $regday;

	// Recycle the old cached query for the total
	$nquery = $sql->fetchq(
		"SELECT 0 user, FLOOR(date / 86400) day, COUNT(*) c ".
		"FROM posts ".
		"GROUP BY day ".
		"ORDER BY day "
	, PDO::FETCH_ASSOC, mysql::FETCH_ALL | mysql::USE_CACHE);
	
	$xdata = array();
	
	// Initialize the totals
	$days        = array_column($nquery, 'day');
	$first_day   = min($days);
	$total       = array_fill($first_day, max($days) - $first_day, 0);
	
	//while ($n = $sql->fetch($nn)) {
	foreach ($nquery as $n) {
		$total[$n['day']] = $n['c'];
		
		$min              = max($first_day, $n['day'] - $alen); // Never check days before the start
		$real_day         = $n['day'] - $regday; // Offset the key appropriately for drawdata()
		$xdata[$real_day] = 0;
	
		// $<estimate>[<day>] = <posts in $alen days> / $alen;
		for ($i = $n['day']; $i > $min; --$i) {
			$xdata[$real_day] += $total[$i];
		}
		$xdata[$real_day] = $xdata[$real_day] / $alen / SCALE_Y;
	}
	return $xdata;
}
