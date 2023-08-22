<?php

// -------------
/*
	Extension system - Base Hooks.
	
	There are three types of hooks, which are all handled similarly:
	- Generic hook:   They don't modify existing variables and may return something.
	                  Their results are returned to an array, in case the calling code needs to do anything with them.
	- Reference hook: They modify a single value passed by reference.
	                  Never returns anything.
	- Print hook:     Special version of the generic hook that always returns strings.
	                  The final result is concatenated.
					  
	All of the three get added with the generic hook_add.
	
	
	The hooks are all stored in a global variable $_hooks
					  
	Currently, there are no explicit priority rules. Whichever plugin was enabled first gets loaded first.              
			
	Important variables:
	- $extName: Name of the currently active extension. Mostly used to build URLs. Created by pageloader.
	- $extConfig: Configuration for all enabled extensions, indexed by extension name.
	- $xconf: "Read-only" configuration for the currently active extension.
	          Used exactly like $config, except for accessing the extension settings in its pages/hooks.
*/

$_hooks = [];

function hook_add($key, $var) {
	global $_hooks, $extName;
	$_hooks[$key][] = [$var, $extName];
}

function hook_use($key) {
	global $_hooks, $extName, $extConfig, $xconf;
	if (!isset($_hooks[$key])) 
		return [];
	// Push the current extName for later
	$oname = $extName;
	$oconf = $xconf;
	// Get all args to pass over to the callback
	$args = func_get_args();
	$res = [];
	foreach ($_hooks[$key] as $callback) {
		// Restore the $extName used when the hook was created
		// This guarantees the correct behaviour for things like actionlink calls in __init__.php
		$extName = $callback[1];
		$xconf   = $extConfig[$extName];
		// Execute the callback, saving the result just in case it returns anything
		$res[] = $callback[0](...$args);
	}
	// Pop them out to restore the original.
	$extName = $oname;
	$xconf   = $oconf;
	return $res;
}

function hook_use_ref($key, &$var) {
	global $_hooks, $extName, $extConfig, $xconf;
	if (!isset($_hooks[$key])) 
		return;
	$oname = $extName;
	$oconf = $xconf;
	$args = func_get_args();
	foreach ($_hooks[$key] as $callback) {
		$extName = $callback[1];
		$xconf   = $extConfig[$extName];
		$callback[0](...$args);
	}
	$var = $args[1];
	$extName = $oname;
	$xconf   = $oconf;
}

function hook_print($key, $default = "") {
	global $_hooks, $extName, $extConfig, $xconf;
	if (!isset($_hooks[$key])) 
		return $default;
	$oname = $extName;
	$oconf = $xconf;
	$args = func_get_args();
	$out = "";
	foreach ($_hooks[$key] as $callback) {
		$extName = $callback[1];
		$xconf   = $extConfig[$extName];
		$out .= $callback[0](...$args);
	}
	$extName = $oname;
	$xconf   = $oconf;
	return $out;
}

// ------------------
/*
	Extension system - Loader.
*/

const EXT_CONF_KEY = 'xconf';

function ext_init() {
	global $extConfig, $extName;
	// needs to be the global variable so hook_add can save the proper $extName
	$oname = $extName;
	foreach (file("extensions/init.dat") as $extName) {
		$extName = rtrim($extName);
		$xconf = ext_read_config($extName);
		include_once "extensions/{$extName}.abx/__init__.php";
		$extConfig[$extName] = $xconf;
	}
	$extName = $oname;
}

function ext_read_config($extName, $forceDefault = false) {
	if (file_exists("extensions/{$extName}.abx/config.php") && !$forceDefault) {
		include "extensions/{$extName}.abx/config.php";
	} else {
		// if the configuration page doesn't exist, fall back to all the default settings specified in the settings page
		$opt = ext_read_schema(null, $extName);
		$xconf = [];
		foreach ($opt->schema[EXT_CONF_KEY] as $_)
		foreach ($_['fields'] as $key => $x) {
			$xconf[$key] = $x['default'];
		}
	}

	return $xconf;
}

function ext_read_schema($name, $extName) {
	return new schema($name, "extensions/{$extName}.abx/settings.page.json");
}

function actionlink($url = null, $args = "") {
	global $scriptpath, $extName;
	if ($url !== null)
		return "{$extName}/{$url}";
	return $scriptpath.$args;
}