<?php

	const SCALE_X = 2; // 1
	const SCALE_Y = 20; // 1px to 20 posts
	const SECTOR_H = 50; // Height of a horizontal sector
	
	// Legend box with usernames (easily configurable just in case)
	const BOX_X = 60;
	const BOX_Y = 10;
	const BOX_W = 113;
	
	const USER_COUNT = 10; // Number of users to show
	
	require "lib/common.php";
 	
	// Get the first real registration date (cut down to days)
	$regdate = $sql->resultq("SELECT MIN(regdate) FROM users WHERE regdate > 0") or die("No users registered.");
	$regday     = floor($regdate / 86400);
	$regdate_ts = $regday * 86400; // Remove hour/min/sec info
	
	$days = floor((time() - $regdate_ts) / 86400); // Days the board has been opened

	// Base the maximum
	$max = ceil(($sql->resultq("SELECT MAX(posts) FROM users") + 1) / SECTOR_H) * SECTOR_H;
	define('IMAGE_Y', $max / SCALE_Y);
	define('IMAGE_X', $days * SCALE_X);

	$img = ImageCreateTrueColor(IMAGE_X, IMAGE_Y);
	$c['bg']  = ImageColorAllocate($img,  0,  0,  0); // Shadows
	$c['bg1'] = ImageColorAllocate($img,  0,  0, 60); // Month background
	$c['bg2'] = ImageColorAllocate($img,  0,  0, 80); // 
	$c['bg3'] = ImageColorAllocate($img, 40, 40,100); // New year vert. line
	$c['mk1'] = ImageColorAllocate($img, 60, 60,130); // Border (postcount indicator)
	$c['mk2'] = ImageColorAllocate($img, 80, 80,150); // Border (postcount indicator/legend box)
	
	//$c['bar'] = ImageColorAllocate($img,250,190, 40); // Not used?
	//$c['pt']  = ImageColorAllocate($img,250,250,250); //

	// Draw background
	for ($i = 0; $i < $days; ++$i) {
		$md = date('m-d', $regdate_ts + $i * 86400);
		if ($md == '01-01') { // New year?
			$num = 3;
		} else {
			$num = substr($md, 0, 2) % 2 + 1; // Alternate between months
		}
		ImageFilledRectangle($img, $i * SCALE_X, IMAGE_Y, ($i + 1) * SCALE_X - 2, 0, $c["bg$num"]);
	}

	// Postcount indicator for each sector; with separator lines
	$sect_x2 = SECTOR_H * 2; // yeah
	for ($y = IMAGE_Y - SECTOR_H; $y >= 0; $y -= SECTOR_H) {
		$color = ($y % $sect_x2) ? $c['mk1'] : $c['mk2']; // Start from mk1 and loop back and forth for each limit
		$posts = (IMAGE_Y - $y) * SCALE_Y;
		
		ImageLine($img, 0, $y, IMAGE_X, $y, $color);
		ImageString($img, 3, 3, $y + 1, $posts, $c['bg']);
		ImageString($img, 3, 2,     $y, $posts, $color);
	}

	// Get the 10 users with the most posts and assign each its own color
	$users  = array();
	$userq  = $sql->query("SELECT id, name FROM `users` ORDER BY `posts` DESC LIMIT 0, ".USER_COUNT);
	while ($u = $sql->fetch($userq))
		$users[$u['id']]     = array('name' => $u['name'], 'color' => imagecolorallocate($img, rand(100,255), rand(100,255), rand(100,255)));

	$z = count($users);
	const NAME_HEIGHT = 12;
	// Draw the legend background box
	imagerectangle(      $img, BOX_X + 1, BOX_Y + 1, BOX_X + BOX_W + 1, BOX_Y + 5 + $z * NAME_HEIGHT, $c['bg']);  // Shadow
	imagefilledrectangle($img, BOX_X    , BOX_Y    , BOX_X + BOX_W    , BOX_Y + 4 + $z * NAME_HEIGHT, $c['bg2']); // Background
	imagerectangle(      $img, BOX_X    , BOX_Y    , BOX_X + BOX_W    , BOX_Y + 4 + $z * NAME_HEIGHT, $c['mk2']); // Border
	
	$z	= 0;
	$data = getdata(array_keys($users));
	foreach($users as $uid => $userx) {
		// Draw the post total as a line
		drawdata($data[$uid], $userx['color']);
		// 10px Dash next to the name...
		imageline($img, BOX_X + 6, BOX_Y + 9 + $z * NAME_HEIGHT, BOX_X + 6 + 10, BOX_Y + 9 + $z * NAME_HEIGHT, $c['bg']);
		imageline($img, BOX_X + 5, BOX_Y + 8 + $z * NAME_HEIGHT, BOX_X + 5 + 10, BOX_Y + 8 + $z * NAME_HEIGHT, $userx['color']);
		// And the name proper...
		imagestring($img, 2, BOX_X + 21, BOX_Y + 2 + $z * NAME_HEIGHT, $userx['name'], $c['bg']);
		imagestring($img, 2, BOX_X + 20, BOX_Y + 1 + $z * NAME_HEIGHT, $userx['name'], $userx['color']);
		++$z;
	}
	
	// errorpage("check the error log");
	
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

function getdata($users) {
	global $sql, $regday;
	
	
	// Initialize user total
	$ucount = count($users);
	$total  = array();
	for ($i = 0; $i < $ucount; ++$i) {
		$total[$users[$i]] = 0;
	}
	
	$postdays = $sql->query("
		SELECT user, FLOOR(date / 86400) day, count(*) c
		FROM posts 
		WHERE user IN (".implode(',',$users).") 
		GROUP BY user, day 
		ORDER BY user, day
	");
	

	$resp  = array();
	// For every user, return the total
	// Since it's ordered already by day we can safely add to the total without issues
	while ($x = $sql->fetch($postdays)) {
		$total[$x['user']] += $x['c'];
		$resp[$x['user']][$x['day'] - $regday] = $total[$x['user']] / SCALE_Y;
	}
	return $resp;
}