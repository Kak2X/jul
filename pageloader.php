<?php

	/* 
		Extension Page Loader
		
		This file is used as 404 handler, to load otherwise inaccessible files inside the /extensions folder.
	*/
	const LOADER_DEBUG = false;
	
	// Only include what we need. 
	require "lib/routing.php";

	// Get the root directory relative to the htdocs root as normal
	fetch_root($root, $boardurl);
	
	// Get the relative URL by removing that aforemented root directory from $_SERVER['REQUEST_URI']
	// Allowed URL formats:
	// <extName>/[file][?<args>]
	// <extName>[?<args>]
	$_url = str_replace("{$boardurl}/", "", $_SERVER['REQUEST_URI']);
	
	
	// Remove arguments, if any
	$_paramPos = strpos($_url, "?");
	if ($_paramPos !== false) {
		$_url = substr($_url, 0, $_paramPos);
	}
	
	// Split URL parts
	$_parts = explode("/", $_url, 2); // leave "/" in the file name
	$_bypassInit = false;
	$_extSubDir = "files/";
	$extName = $_parts[0];
	
	if (count($_parts) === 1) {
		/*
		if (file_exists("pages/{$_parts[0]}.php")) {
			require "pages/{$_parts[0]}.php";
			die;
		}*/
		
		$extFile = "index.php";
		$_fileType = "php";
	} else {
		$extFile = $_parts[1];
		
		switch ($extFile) {
			// Blank filename -> index page
			case "":
				$extFile = "index.php";
				$_fileType = "php";
				break;
			// "config" -> configuration page. Must be always accessible.
			case "config":
				$extFile = "__config__.php";
				$_virtualFile = "config";
				$_extSubDir = "";
				$_fileType = "php";
				$_bypassInit = true;
				break;
			// Default behaviour
			default:
				if (in_array("..", explode("/", $extFile)))
					die("Loader failure.");
						
				// Detect file extension (type)
				$_typePos = strrpos($extFile, ".");
				$_fileType = $_typePos !== false ? substr($extFile, $_typePos+1) : "";
				
				// Only php files are disallowed to run if the extension is disabled
				$_bypassInit = $_fileType !== "php";
				break;
		}
	}
		
	if (LOADER_DEBUG) {
		print "<pre>";
		print "\nRoot: {$root}\nBoard Url: {$boardurl}";
		print "\nURL: {$_url}\nParamPos: ".(isset($_paramPos) ? $_paramPos : "-")."\nPart Count: ".count($_parts);
		print "\nExt Name: {$extName}\nExt File: {$extFile}\nExt SubDir: {$_extSubDir}\nVirtual File: ".(isset($_virtualFile) ? $_virtualFile : "");
		print "\nExt Exists: ".(file_exists("extensions/{$extName}.abx") ? "OK" : "NG");
		print "\nExt Enabled: ".(in_array($extName, array_map('trim', file("extensions/init.dat")), true) ? "OK" : "NG");
		print "\nBypassInit: ".($_bypassInit ? "TRUE" : "FALSE");
		print "\nFile extension: {$_fileType}\nTypePos: ".(isset($_typePos) ? $_typePos : "-");
		print "\nWill load: 'extensions/{$extName}.abx/{$_extSubDir}{$extFile}'";
		print "</pre>";
	}
	
	// To save ourselves from trouble, if an extension is disabled we don't prevent access to the resources
	// only php files will trigger the 404 page
	if ($_bypassInit || in_array($extName, array_map('trim', file("extensions/init.dat")), true)) {
		
		// Just in case, validate $extName if we bypassed the init file check.
		// In theory it's not needed.
		if ($_bypassInit) {
			$_allowedExt = glob("extensions/*.abx", GLOB_NOSORT);
			if (!in_array("extensions/{$extName}.abx", $_allowedExt))
				die("No.");
			unset($_allowedExt);
		}
		
		$loadPath = "extensions/{$extName}.abx/{$_extSubDir}{$extFile}";
		if (file_exists($loadPath)) {
			
			// If it's a folder, try to redirct us there.
			// This works alongside an htaccess rule to hide php files from there.
			if (is_dir($loadPath)) {
				header("Location: {$boardurl}/{$loadPath}");
				die;
			}
			
			// Non-php files are loaded as data
			if ($_fileType !== "php") {
				if ($_fileType === "css") // not autodetected correctly
					header("Content-type: text/css");
				else
					header("Content-type: ".mime_content_type($loadPath)."");
				
				readfile($loadPath);
				die;
			}
			
			$scriptname = $extFile;
			$scriptpath = "{$extName}/".(isset($_virtualFile) ? $_virtualFile : $extFile); // For relative links to the current extension (with actionlink)
			$meta['base'] = "{$boardurl}/"; // Enable base href tag to point to correct images/CSS
			
			// Explicitly destroy anything specific to the pageloader
			unset($_url, $_paramPos, $_parts, $_bypassInit, $_extSubDir, $_fileType, $_typePos, $_virtualFile);
			
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