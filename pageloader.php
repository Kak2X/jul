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
		
	
	
	do {
		// Parse the extension page format <extName>/<file> (ie: "news/new.php")
		$extPos = strpos($url, "/");
		if ($extPos === false) {
			// Fallback when omitting the slash (ie: news?debug=1)
			$extPos = strpos($url, "?");
			if ($extPos === false) {
				// double fallback. just use index page (ie: news)
				$extName = $url;
				$extFile = "index.php";
				break;
			}
			$paramPos = $extPos + 1;
		} else {
			$paramPos = strpos($url, "?", $extPos);
		}
		
		$extName = substr($url, 0, $extPos);
		$extFile = $paramPos !== false
			? substr($url, $extPos+1, $paramPos-($extPos+1))
			: substr($url, $extPos+1);

		// If the parameter wasn't passed, go by the index page (which is optional and may not exist)
		if (!$extFile) {
			$extFile = "index.php";
		}
	} while (false);
		
	if (isset($_GET['debug'])) {
		print "<pre>";
		print "\nRoot: {$root}\nBoard Url: {$boardurl}";
		print "\nURL: {$url}\nExtPos: {$extPos}\nParamPos: {$paramPos}";
		print "\nExt Name: {$extName}\nExt File: {$extFile}";
		print "\nExt Folder: ".(file_exists("extensions/{$extName}.abx") ? "OK" : "NG");
		print "\nExt Exists: ".(in_array($extName, file("extensions/init.dat"), true) ? "OK" : "NG");
		print "\nWill load: 'extensions/{$extName}.abx/files/{$extFile}'";
		print "</pre>";
	}
	
	if (in_array($extName, file("extensions/init.dat"), true)) {
		$loadPath = "extensions/{$extName}.abx/files/{$extFile}";
		if (file_exists($loadPath)) {
			if (is_dir($loadPath)) {
				die("Folder browsing isn't implemented yet. Sorry!");
			}
			// :(
			if (substr($loadPath, -3) === "css") {
				header("Content-type: text/css");
			}
			
			$scriptpath = "$extName/$extFile"; // For forms
			$meta['base'] = $boardurl."/"; // Enable base href tag to point to correct images/CSS
			unset($url, $extPos, $extFile);
			require_once($loadPath);
			die;
		}
	}
	
	// If we got here, something failed.
	header("HTTP/1.0 404 Not Found");
	require "errors/404.php";