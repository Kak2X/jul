<?php

// -------------
// hooks

$_hooks = [];

function add_hook($key, $var) {
	global $_hooks, $extName;
	$_hooks[$key][] = [$var, $extName];
}

function load_hook($key) {
	global $_hooks, $extName, $extConfig, $xconf;
	if (!isset($_hooks[$key])) 
		return;
	// save the current extName for later
	$oname = $extName;
	$oconf = $xconf;
	$args = func_get_args();
	foreach ($_hooks[$key] as $callback) {
		// restore the $extName used when the hook was created
		// this guarantees the correct behaviour for things like actionlink outside of ext-specific pages
		$extName = $callback[1];
		$xconf   = $extConfig[$extName];
		// execute the callback
		$callback[0](...$args);
	}
	// and restore the original
	$extName = $oname;
	$xconf   = $oconf;
}

function load_hook_ref($key, &$var) {
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

function print_hook($key) {
	global $_hooks, $extName, $extConfig, $xconf;
	if (!isset($_hooks[$key])) 
		return "";
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

// --------------
// extra
function actionlink($url = null, $args = "") {
	global $scriptpath, $extName;
	if ($url !== null)
		return "{$extName}/{$url}";
	return $scriptpath.$args;
}

// ------------------
// extension system

function ext_init() {
	global $extConfig, $extName;
	// needs to be the global variable so add_hook can save the proper $extName
	$oname = $extName;
	foreach (ext_get_enabled(false) as $extName) {
		$extName = rtrim($extName);
		$xconf = ext_read_config($extName);
		include_once "extensions/{$extName}.abx/__init__.php";
		$extConfig[$extName] = $xconf;
	}
	$extName = $oname;
}
// Get the enabled extensions
function ext_get_enabled($assoc = true) {
	if (!$assoc)
		return file("extensions/init.dat");
	// Special isset convenience format
	$h = fopen("extensions/init.dat", 'r');
	$res = [];
	while (($line = fgets($h)) !== false) {
		$line = rtrim($line); // is this even necessary?
		$res[$line] = $line;
	}
	return $res;
}

function ext_write_enabled($script) {
	return file_put_contents("extensions/init.dat", implode("\n", $script));
}

function ext_get_all($assoc = true) {
	$source = glob("extensions/*.abx");
	if (!$assoc) {
		foreach ($source as $k => $abxFile) {
			// Remove 'extensions/' and '.abx' from file name.
			$file = substr($abxFile, 11, -4);
			$source[$k] = $file;
		}
		return $source;
	}
	$res = [];
	foreach ($source as $abxFile) {
		// Remove 'extensions/' and '.abx' from file name.
		$file = substr($abxFile, 11, -4);
		$res[$file] = $file;
	}
	return $res;
}

// Bookkeeping metadata
function ext_read_userdata($extName) {
	if (!file_exists("extensions/{$extName}.abx/user.json"))
		return [];
	return json_decode(file_get_contents("extensions/{$extName}.abx/user.json"), true);
}
function ext_write_userdata($extName, $script) {
	return file_put_contents("extensions/{$extName}.abx/user.json", json_encode($script));
}
// Extension page layout
function ext_read_metadata($extName) {
	return json_decode(file_get_contents("extensions/{$extName}.abx/metadata.json"), true);
}

function ext_read_settingspage($extName) {
	return json_decode(file_get_contents("extensions/{$extName}.abx/settings.page.json"), true);
}

function ext_config_layout($extName) {
	global $_oxconf;
	$schema    = ext_read_settingspage($extName);
	$_oxconf   = ext_read_config($extName); // passthrough
	return get_config_layout($schema, '_ext_output_input');
}

// Extension configuration
function ext_read_config($extName) {
	if (file_exists("extensions/{$extName}.abx/config.php")) {
		include "extensions/{$extName}.abx/config.php";
	} else {
		// if the configuration is missing, fall back to all the default settings specified in the settings page
		$opt = ext_read_settingspage($extName);
		$xconf = [];
		foreach ($opt['xconf'] as $_)
		foreach ($_ as $key => $x) {
			$xconf[$key] = $x['default'];
		}
	}

	return $xconf;
}

function _ext_config_input($varname, $key, $data) {
	$value = __($_POST['config'][$varname][$key]);
	return _get_input($value, $varname, $key, $data, true);
}
function _ext_output_input($varname, $key, $data) {
	global $_oxconf;
	// for later, maybe
	// $value = isset($_POST['config'][$varname][$key]) ? $_POST['config'][$varname][$key] : $_oxconf[$key];
	return input($varname, $key, $data, $_oxconf[$key]);
}

function ext_write_config($extName) {
	$opts = ext_read_settingspage($extName);
	$output = "<?php # Autogenerated config file \r\n".generate_config($opts, '_ext_config_input');
	file_put_contents("extensions/{$extName}.abx/config.php", $output);
}

function _ext_run_custom($extName, $script) {
	if (file_exists("extensions/{$extName}.abx/__{$script}__.php")) // call custom actions
		include_once "extensions/{$extName}.abx/__{$script}__.php";
}

// get full ext info
function ext_list($enabledOnly = false, &$enabled = null) {
	$out = [];
	
	$enabled = ext_get_enabled();

	// Plugin folders end with ".abx" for convenience.
	// Each entry marks an installed extension.
	$extFiles = ext_get_all(false);
	
	// Iterate through the found files,
	foreach ($extFiles as $file) {
		// If the extension is enabled, it should be in the list
		$ena = isset($enabled[$file]);
		
		// Don't skip if the entire list is requested (ie: extension settings)
		if ($ena || !$enabledOnly) {
			// Extension metadata in .json files.
			$x = ext_read_metadata($file);
			// Get user-specific metadata
			$xtra = ext_read_userdata($file);
			
			// Add fake metadata for completion
			$x['file'] = $file;
			if ($x['enabled'] = $ena)
				$x['enableDate'] = printdate($xtra['enableTime']);
			//if ($x['installed'] = isset($xtra['installTime']))
			//	$x['installDate'] = date("j/n/Y", $xtra['installTime']);
			
			if (!isset($x['require']))
				$x['require'] = [];
			$out[$file] = $x;
		}
	}

	return $out;
}

// General enable extensions
function ext_enable($extName, &$extensions = NULL, &$enabled = null, &$usermeta = null) {
	if (!ext_valid($extName))
		return -1;
	
	// Initialize extensions list
	if ($extensions === null)
		$extensions = ext_list(false, $enabled); // retrieve $enabled out of this
	if ($usermeta === null) {
		$usermeta = [];
		$first = true;
	}
	
	
	// Check if the extension is actually on the list (and if it's enabled)
	// This also guarantees each extension is only processed once
	if (!isset($extensions[$extName]) || $extensions[$extName]['enabled'])
		return 0;
	
	// Cascade effect for all other extensions which are required
	$total = 0;
	foreach ($extensions[$extName]['require'] as $reqName) {
		$amount = ext_enable($reqName, $extensions, $enabled, $usermeta); // Don't recalculate on future visits.
		if ($amount === -1) { // Abort on loading error
			return -1;
		}
		$total += $amount;
	}
	
	// Add extensions to enable list
	$enabled[] = $extName;
	
	// Set timestamp in internal bookkeeping
	$usermeta[$extName] = ext_read_userdata($extName);
	$usermeta[$extName]['enableTime'] = ctime();
	
	// Only at the very end, after all required extensions are enabled, we save the changes
	if (isset($first)) {
		ext_write_enabled($enabled);
		foreach ($usermeta as $xname => $data) {
			_ext_run_custom($xname, 'enable');
			ext_write_userdata($xname, $data);
		}
		_ext_run_custom($extName, 'enable');
	}
	
	return $total;
}

function ext_disable($extName, &$extensions = null, &$enabled = null, &$usermeta = null) {
	if (!ext_valid($extName))
		return -1;
	
	// Initialize extensions list
	if ($extensions === null)
		$extensions = ext_list(false, $enabled); // retrieve $enabled out of this
	if ($usermeta === null) {
		$usermeta = [];
		$first = true;
	}
	
	// Check if the extension is actually on the list (and if it's disabled)
	// This also guarantees each extension is only processed once
	if (!isset($extensions[$extName]) || !$extensions[$extName]['enabled'])
		return 0;
	
	$total = 0;
	foreach ($extensions as $xname => $x) {
		// Loop through all extensions to check which depend on this
		foreach ($x['require'] as $req) {
			if ($req == $extName) {
				$amount = ext_disable($xname, $extensions, $enabled, $usermeta); // Don't recalculate on future visits.
				if ($amount === -1) {
					trigger_error("Disabling 'require' extension {$xname} failed (ext_valid error).");
				} else {
					$total += $amount;
				}
			}
		}
	}
	
	//if ($enabled !== null)
	//	$enabled = self::ReadEnabledExtensions();

	// Remove extension from enable list
	unset($enabled[$extName]);
	
	// Blank timestamp in internal bookkeeping
	$usermeta[$extName] = ext_read_userdata($extName);
	unset($usermeta[$extName]['enableTime']);
	
	// Only at the very end, after all required extensions are disabled, we save the changes
	if (isset($first)) {
		ext_write_enabled($enabled);
		foreach ($usermeta as $xname => $data) {
			_ext_run_custom($xname, 'disable');
			ext_write_userdata($xname, $data);
		}
		_ext_run_custom($extName, 'disable');
	}
	
	return $total;
}

// test
function ext_require_tree($extName, &$extensions = null, &$clear = null) {
	$dep = [];
	if (!ext_valid($extName))
		return $dep;
	
	// Initialize extensions list
	if ($extensions === null)
		$extensions = ext_list();
	if ($clear === null)
		$clear = [];
	
	if (!isset($extensions[$extName]) || isset($clear[$extName]))
		return $dep;
	$clear[$extName] = true;
	
	foreach ($extensions[$extName]['require'] as $reqName) {
		$dep[$reqName] = ext_require_tree($reqName, $extensions, $clear);
	}
	
	return $dep;
}


// Utility methods
function ext_valid($extName) {
	$real = basename($extName);
	return (
		   $real // No blank
		&& $extName == $real // bullshit hack attempt
		&& valid_filename($real) // prevent invalid chars
		&& file_exists("extensions/{$real}.abx") // extension exists?
	);
}