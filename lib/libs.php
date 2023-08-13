<?php
	require "lib/common.php";
	if ($config['compat-test']) {
		require "lib/abcompat.php";
	} else {
		die("This file requires the (half-assed) compatibility layer, which has been disabled.<br>Click <a href='index.php'>here</a> to return to the index.");
	}