<?php
	require "lib/common.php";

	$_GET['u'] = filter_int($_GET['u']);
	
	// Get timestamp of the *day* the user registered (remove hour/min/sec)
	$regdate = $sql->resultq("SELECT regdate FROM users WHERE id = {$_GET['u']}") or die("User doesn't exist.");
	$regday     = floor($regdate / 86400); // Day number
	$regdate_ts = $regday * 86400;         // Day timestamp
	
	$days = floor((time()-$regdate)/86400); // Account age (in days)
	if (!$days) die("Account age too low.");
	
	// A faster (?) way to group them; by using the day number instead of getting a date string
	$postdts = $sql->fetchq("
		SELECT FLOOR(date / 86400) day, COUNT(*) c 
		FROM posts 
		WHERE user = {$_GET['u']} 
		GROUP BY day 
		ORDER BY day
	", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
	
	if (!$postdts) die("No posts for this user.");
	
	// Place the results into an array with a saner format [<day number> => <count>]
	foreach ($postdts as $k_day => $posts) {
		$day = $k_day - $regday;
		$postdb[$day] = $posts;
	}
	unset($postdts, $k_day, $posts);

	$maxposts = max($postdb);
	$img = ImageCreateTrueColor($days, $maxposts);

	$c['bg']  = ImageColorAllocate($img,  0,  0,  0);
	$c['bg1'] = ImageColorAllocate($img,  0,  0, 80); // Month colors
	$c['bg2'] = ImageColorAllocate($img,  0,  0,130); //
	$c['bg3'] = ImageColorAllocate($img, 80, 80,250); // (New year)
	$c['mk1'] = ImageColorAllocate($img,110,110,160); // Horizontal Rulers
	$c['mk2'] = ImageColorAllocate($img, 70, 70,130); //
	$c['bar'] = ImageColorAllocate($img,240,190, 40); // Post count bar
	$c['pt1'] = ImageColorAllocate($img,250,250,250); // Average
	$c['pt2'] = ImageColorAllocate($img,240,230,220); // Average (over top of post bar)

	// Draw the background
	for ($i = 0; $i < $days; ++$i) {
		$md = date('m-d', $regdate_ts + $i * 86400);
		if ($md == '01-01') { // New year?
			$num = 3;
		} else {
			$num = substr($md, 0, 2) % 2 + 1; // Alternate between months
		}
		ImageLine($img,$i,$maxposts,$i,0,$c["bg$num"]);
	}
	
	// Draw the horizontal rulers (in offsets of Y 50px; alternate lines between 2 colors)
	for ($y = 50, $ct = 1; $y <= $maxposts; $y += 50, $ct++) {
		ImageLine($img, 0, $maxposts - $y, $days, $maxposts - $y, (($ct & 1) ? $c['mk2'] : $c['mk1']));
	}
	
	$total = 0;
	for($i = 0; $i < $days;++$i) {
		// Draw the number of posts (as vertical bars starting from the bottom)
		if (isset($postdb[$i])) {
			ImageLine($img, $i, $maxposts, $i, $maxposts - $postdb[$i], $c['bar']);
			$total += $postdb[$i];
		} else {
			$postdb[$i] = 0;
		}
		// Draw post average in relation to time
		$avg = (int)($total / ($i + 1));
		ImageSetPixel($img, $i, $maxposts - $avg, (($postdb[$i] >= $avg) ? $c['pt2'] : $c['pt1']));
	}

	//errorpage("check the error log");
	
	header_content_type("image/png");
	ImagePNG($img);
	ImageDestroy($img);