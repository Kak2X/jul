<?php

// Get the root directory similarly to how it's done in 1.B0-fix, except backlashes are converted to UNIX style.
/*
function get_root() {
	static $root;
	if ($root === null) {
		$root = getcwd();
		if (PHP_OS === "WINNT") {
			$root = str_replace("\\", "/", $root);
		}
	}
	return $root;
}

function get_board_url($root) {
	return str_replace($_SERVER['DOCUMENT_ROOT'], "", $root);
}*/

function fetch_root(&$root, &$boardurl) {
	// Get the root directory similarly to how it's done in 1.B0-fix, except backlashes are converted to UNIX style.
	$root = getcwd();
	if (PHP_OS === "WINNT") {
		$root = str_replace("\\", "/", $root);
	}
	// Get the file we tried to access
	$boardurl = str_replace($_SERVER['DOCUMENT_ROOT'], "", $root);
}

function get_current_script() {
	return basename($_SERVER['PHP_SELF']);
}