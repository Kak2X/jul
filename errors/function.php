<?php
	
	$ppos = strrpos($_SERVER['PHP_SELF'], '/');
	$path = substr($_SERVER['PHP_SELF'], 0, $ppos);
	
	// This is always called from <ab path>/errors, so we need to get over /lib
	require "../lib/config.php";