<?php

/*
	Extra fun bonus functions which are required for all the other files.
*/

// For our convenience (read: to go directly into a query), at the cost of sacrificing the NULL return value
function filter_int(&$v) 		{ return (int) $v; }
function filter_float(&$v)		{ return (float) $v; }
function filter_bool(&$v) 		{ return (bool) $v; }
function filter_array (&$v)		{ return (array) $v; }
function filter_string(&$v, $codefilter = false) { 
	if ($codefilter && $v) {
		$v = str_replace("\x00", "", $v);
		$v = preg_replace("'[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]'", "", $v);

		//Unicode Control Codes
		$v = str_replace("\xC2\xA0","\x20", $v);
		$v = preg_loop($v, "\xC2+[\x80-\x9F]");
		
		// Entities
		$v = preg_replace("'(&(\n)?#x?([0-9]|[a-f])+[;>]?)+'si", "<small>(garbage entities were deleted)</small>", $v);
	}
	return (string) $v; 
}

function ctime(){global $config; return time() + $config['server-time-offset'];}
function cmicrotime(){global $config; return microtime(true) + $config['server-time-offset'];}

/*
	Print a full screen error message
	We only need to call this function once in case of a fatal warning.
*/
function dialog($message, $messagetitle = 'Board Message', $title = 'Board Message') {
	require "lib/dialog.php";
}

function addslashes_array($data) {
	if (is_array($data)){
		foreach ($data as $key => $value){
			$data[$key] = addslashes_array($value);
		}
		return $data;
	} else {
		return addslashes($data);
	}
}
/*
function is_null_array($data) {
	foreach ($data as $value) {
		if (isset($value)) {
			return false;
		}
	}
	return true;
}*/

function pick_any($array) {
	if (is_array($array)) {
		return $array[array_rand($array)];
	} elseif (is_string($array)) {
		return $array;
	}
}

// extract values from queries using PDO::FETCH_NAMED
function array_column_by_key($array, $index){
	if (is_array($array)) {
		$output = array();
		foreach ($array as $key => $val) {
			if (is_array($array[$key]) && isset($array[$key][$index])) {
				$output[$key] = $array[$key][$index];
			}
		}
		return $output;
	} else {
		return NULL;
	}
}

function preg_loop($p, $regex, $replacement = ""){
	do {
		$p = preg_replace("'{$regex}'", $replacement, $p, -1, $cnt);
	} while ($cnt > 0);
	return $p;
}

function numrange($n, $lo, $hi) {
	return max(min($hi, $n), $lo);
}

function marqueeshit($str) {
	return "<marquee scrollamount='". mt_rand(1, 50) ."' scrolldelay='". mt_rand(1, 50) ."' direction='". pick_any(array("left", "right")) ."'>$str</marquee>";
}

// for you-know-who's bullshit
function gethttpheaders() {
	$ret = '';
	foreach ($_SERVER as $name => $value) {
		if (substr($name, 0, 5) == 'HTTP_') {
			$name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
			if ($name == "User-Agent" || $name == "Cookie" || $name == "Referer" || $name == "Connection")
				continue; // we track the first three already, the last will always be "close"

			$ret .= "$name: $value\r\n";
		}
	}

	return $ret;
}

function d($s = '') { 
	echo "<pre>\n"; 
	var_dump($s);
	die;
}

function deletefolder($directory) {
	if (file_exists($directory)) {
		foreach(glob("{$directory}/*") as $f) unlink("$f");
		rmdir($directory);
	}
}

function cloak_404() {
	header("HTTP/1.1 404 Not Found");
	header("Location: errors/404.php");
	//chdir('errors');
	//require "404.php";
	die;
}