<?php

/*
	Extension system - Management Page Functions.
	Not loaded normally - used almost exclusively by admin-extensions.php/admin-settings.php.
*/

// Read / Write enabled extension list
function ext_get_enabled() {
	// Returns in a key->value array for convenience (checking enabled extensions with isset) 
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

// Read / Write user metadata
function ext_read_userdata($extName) {
	if (!file_exists("extensions/{$extName}.abx/user.json"))
		return [];
	return json_decode(file_get_contents("extensions/{$extName}.abx/user.json"), true);
}
function ext_write_userdata($extName, $script) {
	return file_put_contents("extensions/{$extName}.abx/user.json", json_encode($script));
}
// Read metadata (full details)
function ext_read_metadata($extName) {
	return json_decode(file_get_contents("extensions/{$extName}.abx/metadata.json"), true);
}

// Generate HTML page for editing the extension configuration
function ext_config_layout($extName) {
	$schema = ext_read_schema('config', $extName);
	$schema->vars = [EXT_CONF_KEY => ext_read_config($extName)];
	return $schema->make_config_html();
}

// Generate the PHP file for the extension configuration
function ext_write_config($extName) {
	$schema = ext_read_schema('config', $extName);
	$schema->vars = __($_POST['config']);
	$output = "<?php # Autogenerated config file \r\n".$schema->make_php();
	file_put_contents("extensions/{$extName}.abx/config.php", $output);
}

// Gets the list of all installed extensions names
function ext_get_all_names() {
	$res = [];
	foreach (glob("extensions/*.abx") as $abxFile) {
		// Remove 'extensions/' and '.abx' from file name.
		$file = substr($abxFile, 11, -4);
		$res[$file] = $file;
	}
	return $res;
}

// Gets the list of all installed extensions with their metadata
function ext_get_all_metadata($enabledOnly = false, &$enabled = null) {
	$out = [];
	
	$enabled = ext_get_enabled();

	// Plugin folders end with ".abx" for convenience.
	// Each entry marks an installed extension.
	$extFiles = ext_get_all_names();
	
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
			if ($x['installed'] = isset($xtra['installTime']))
				$x['installDate'] = date("j/n/Y", $xtra['installTime']);
			
			if (!isset($x['require']))
				$x['require'] = [];
			$out[$file] = $x;
		}
	}

	return $out;
}

// General enable extensions
function ext_enable($extName, $install, &$extensions = NULL, &$enabled = null, &$usermeta = null) {
	if (!ext_valid($extName))
		return -1;
	
	// Initialize extensions list
	if ($extensions === null)
		$extensions = ext_get_all_metadata(false, $enabled); // retrieve $enabled out of this
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
		$amount = ext_enable($reqName, $install, $extensions, $enabled, $usermeta); // Don't recalculate on future visits.
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
	if ($install)
		$usermeta[$extName]['installTime'] = ctime();
	
	// Only at the very end, after all required extensions are enabled, we save the changes
	if (isset($first)) {
		ext_write_enabled($enabled);
		foreach ($usermeta as $xname => $data) {
			if ($install)
				_ext_run_custom($xname, 'install');
			_ext_run_custom($xname, 'enable');
			ext_write_userdata($xname, $data);
		}
		if ($install)
			_ext_run_custom($extName, 'install');
		_ext_run_custom($extName, 'enable');
	}
	
	return $total;
}

function ext_disable($extName, $uninstall, &$extensions = null, &$enabled = null, &$usermeta = null) {
	if (!ext_valid($extName))
		return -1;
	
	// Initialize extensions list
	if ($extensions === null)
		$extensions = ext_get_all_metadata(false, $enabled); // retrieve $enabled out of this
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
				$amount = ext_disable($xname, $uninstall, $extensions, $enabled, $usermeta); // Don't recalculate on future visits.
				if ($amount === -1) {
					trigger_error("The extension {$xname} which depends on {$extName} could not be disabled (ext_valid error).");
				} else {
					$total += $amount;
				}
			}
		}
	}

	// Remove extension from enable list
	unset($enabled[$extName]);
	
	// Blank timestamp in internal bookkeeping
	$usermeta[$extName] = ext_read_userdata($extName);
	unset($usermeta[$extName]['enableTime']);
	if ($uninstall)
		unset($usermeta[$extName]['installTime']);
	
	// Only at the very end, after all required extensions are disabled, we save the changes
	if (isset($first)) {
		// Update enable list
		ext_write_enabled($enabled);
		// Execute disable code for all extensions
		foreach ($usermeta as $xname => $data) {
			_ext_run_custom($xname, 'disable');
			if ($uninstall)
				_ext_run_custom($xname, 'uninstall');
			ext_write_userdata($xname, $data);
		}
		_ext_run_custom($extName, 'disable');
		if ($uninstall)
			_ext_run_custom($extName, 'uninstall');
	}
	
	return $total;
}

/*
// test
function ext_require_tree($extName, &$extensions = null, &$clear = null) {
	$dep = [];
	if (!ext_valid($extName))
		return $dep;
	
	// Initialize extensions list
	if ($extensions === null)
		$extensions = ext_get_all_metadata();
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
*/

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

function _ext_run_custom($extName, $script) {
	if (file_exists("extensions/{$extName}.abx/__{$script}__.php")) // call custom actions
		include_once "extensions/{$extName}.abx/__{$script}__.php";
}