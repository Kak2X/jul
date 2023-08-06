<?php

	const LOADER_DEBUG = false;
	
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
				$extName    = $url;
				$extFile    = "index.php";
				$fileType   = "php";
				$loadConfig = false;
				$bypassInit = false;
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
			$extFile    = "index.php";
			$fileType   = "php";
			$loadConfig = false;
			$bypassInit = false;
		// If the parameter matches this key, force load the config page, even if the extension is disabled.
		// this is important as the config page should always be accessible
		} else if ($extFile === "config") {
			$fileType   = "php";
			$loadConfig = true;
			$bypassInit = true;
		} else {
			$fileType   = substr($extFile, -3);
			$loadConfig = false;
			$bypassInit = $fileType !== "php";
		}
	} while (false);
		
	if (LOADER_DEBUG) {
		print "<pre>";
		print "\nRoot: {$root}\nBoard Url: {$boardurl}";
		print "\nURL: {$url}\nExtPos: {$extPos}\nParamPos: {$paramPos}";
		print "\nExt Name: {$extName}\nExt File: {$extFile}";
		print "\nExt Folder: ".(file_exists("extensions/{$extName}.abx") ? "OK" : "NG");
		print "\nExt Exists: ".(in_array($extName, array_map('trim', file("extensions/init.dat")), true) ? "OK" : "NG");
		print "\nLoadConfig: ".($loadConfig ? "TRUE" : "FALSE");
		print "\nBypassInit: ".($bypassInit ? "TRUE" : "FALSE");
		print "\nFile extension: {$fileType}";
		print "\nWill load: 'extensions/{$extName}.abx/".($loadConfig ? "__config__.php" : "files/{$extFile}")."'";
		print "</pre>";
	}
	
	// To save ourselves from trouble, if an extension is disabled we don't prevent access to the resources
	// only php files will trigger the 404 page
	
	
	if ($bypassInit || in_array($extName, array_map('trim', file("extensions/init.dat")), true)) {
		$loadPath = "extensions/{$extName}.abx/".($loadConfig ? "__config__.php" : "files/{$extFile}");
		if (file_exists($loadPath)) {
			if (is_dir($loadPath)) {
				header("Location: {$boardurl}/{$loadPath}");
				die;
			}
			// :(
			if ($fileType === "css") {
				header("Content-type: text/css");
			} else if ($fileType !== "php") {
				header("Content-type: ".mime_content_type($loadPath)."");
			}
			
			$scriptpath = "$extName/$extFile"; // For forms
			$meta['base'] = $boardurl."/"; // Enable base href tag to point to correct images/CSS
			unset($url, $extPos, $loadConfig, $fileType);
			require_once($loadPath);
			die;
		}
	}
	
	if (LOADER_DEBUG) {
		die("Page loading failed!");
	}
	
	// If we got here, something failed.
	header("HTTP/1.0 404 Not Found");
	require "errors/404.php";