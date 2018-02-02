<?php

	require "lib/function.php";
	
	pageheader("{$config['board-name']} -- Storage Area");
	
	if (!$issuper) {
		errorpage("Nein.");
	}
	
	errorpage("Coming soon!");