<?php
	// Generic page loader
	require "lib/routing.php";

	// Get the root directory similarly
	fetch_root($root, $boardurl);
	$url = str_replace($boardurl."/", "", $_SERVER['REQUEST_URI']);

	/*
	// Alias of existing file in pages?
	if (file_exists("pages/{$url}.php")) {
	//	echo "\nOK";
		die;
	}*/
		
	// Parse the extension page format <extName>/<file> (ie: "News/new.php")
	$extPos = strpos($url, "/");
	$extName = basename(substr($url, 0, $extPos));
	if ($paramPos = strpos($url, "?", $extPos)) {
		$extFile = substr($url, $extPos+1, $paramPos-($extPos+1));
	} else {
		$extFile = substr($url, $extPos+1);
	}
	$extFile = basename($extFile);

	// If the parameter wasn't passed, go by the index page (which is optional and may not exist)
	if (!$extFile) {
		$extFile = "index.php";
	}
		
	if (isset($_GET['debug'])) {
		print "\nRoot: {$root}\nBoard Url: {$boardurl}";
		print "\nExt Name: {$extName}\nExt File: {$extFile}";
		print "\nExt Folder: ".(file_exists("extensions/{$extName}.abx") ? "OK" : "NG");
		print "\nExt Exists: ".(in_array($extName, file("extensions/init.dat"), true) ? "OK" : "NG");
		print "\nWill load: 'extensions/{$extName}.abx/files/{$extFile}'";
	}
	
	if (in_array($extName, file("extensions/init.dat"), true)) {
		$loadPath = "extensions/{$extName}.abx/files/{$extFile}";
		if (file_exists($loadPath)) {
			$scriptpath = "$extName/$extFile"; // For forms
			$meta['base'] = $boardurl."/"; // Enable base href tag to point to correct images/CSS
			unset($url, $extPos, $extName, $extFile);
			require_once($loadPath);
			die;
		}
	}

	// If we got here, something failed.
	header("HTTP/1.0 404 Not Found");
	require "errors/404.php";