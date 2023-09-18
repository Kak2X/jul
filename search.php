<?php

	require "lib/common.php";
	require "lib/search.php";
	
	$_GET['id']	         = filter_string($_GET['id']); // Thread ID
	$_GET['hi']          = numrange(filter_int($_GET['hi']), PHILI_MIN, PHILI_MAX); // Highlight filter for Thread/User modes
	$_GET['user']        = filter_int($_GET['user']); // User ID (posts by user)
	$_GET['warn']        = filter_bool($_GET['warn']); // Warn filter for Thread/User modes
	$_GET['text']        = filter_string($_GET['text']); // Posts text
	$_GET['title']       = filter_string($_GET['title']); // Thread title (only makes sense in non-id mode)
	$_GET['wtxt']        = filter_string($_GET['wtxt']); // Highlight text
	$_GET['htxt']        = filter_string($_GET['htxt']); // Warning text
	$_GET['ipmask']      = filter_string($_GET['ipmask']); // IP Mask (admin-only)
	$_GET['forum']       = filter_int($_GET['forum']); // Forum filter (only makes sense in non-id mode)
	$_GET['date']        = filter_int($_GET['date'], 1); // Date mode 
	$_GET['datedays']    = filter_int($_GET['datedays'], 30); // Search in the last X days
	$datefrom            = fieldstotimestamp('f', '_GET'); // Date Range - From
	$dateto              = fieldstotimestamp('t', '_GET'); // Date Range - To
	$_GET['order']       = filter_int($_GET['order']); // Post order
	$_GET['dir']         = filter_int($_GET['dir']); // Post direction
		
	$breadcrumbs = dobreadcrumbs([
		["Search", null],
	]);

	pageheader("Search");
	print $breadcrumbs.post_search_table().$breadcrumbs;
	pagefooter();