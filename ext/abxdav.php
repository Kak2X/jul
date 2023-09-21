<?php

	if (substr(php_sapi_name(), 0, 3) != 'cli') {
		die("Command-line only.");
	}
	
	if (!file_exists("avatars"))
		die("Current directory must be the ABXD 'data' folder.");
	
	if (!file_exists("userpic"))
		mkdir("userpic");
	
	
	foreach (glob("avatars/*") as $f) {
		$f = basename($f);
		if ($f == "keep.txt")
			continue;
		
		print $f."\r\n";
		if (strpos($f, "_") === false) {
			copy("avatars/$f", "userpic/{$f}_0");
		} else {
			copy("avatars/$f", "userpic/{$f}");
		}
	}
	
	foreach (glob("minipics/*") as $f) {
		$f = basename($f);
		if ($f == "keep.txt")
			continue;
		
		print $f."\r\n";
		copy("minipics/$f", "userpic/{$f}_m");
	}