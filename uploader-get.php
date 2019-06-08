<?php
	$meta['noparams'] = true;
	
	require "lib/function.php";
	require "lib/uploader_function.php";
	
	if (!$config['uploader-all-origin'] && !SAME_ORIGIN) {
		print "<title>{$config['board-name']}</title>";
		print "To continue to the file, click <a href='?{$_SERVER['QUERY_STRING']}'>here</a>.";
		die;
	}
	
	$_GET['f']    = filter_string($_GET['f']);
	$_GET['info'] = filter_bool($_GET['info']);
	
	$file = $sql->fetchp("
		SELECT f.filename, f.size, f.mime, f.user user, f.private, f.id fileid, f.is_image,
		       c.user cuser, c.id catid, c.minpowerread
		FROM uploader_files f
		INNER JOIN uploader_cat c ON f.cat = c.id
		WHERE f.hash = ?
	", [$_GET['f']]);
	
	if (!$file || !can_read_category(['user' => $file['cuser'], 'minpowerread' => $file['minpowerread']]) || (!can_manage_category(['user' => $file['cuser']]) && !can_read_file($file)) || !file_exists("uploads/f/{$_GET['f']}")) {
		header("HTTP/1.1 404 Not Found");
		die("File not found.");
	}
	
	if ($isadmin && $_GET['info']) {
		echo "<pre>File display:\n\n";
		print_r($file);
		die;
	}
	
	// Update stats
	$sql->query("UPDATE uploader_cat SET downloads = downloads + 1 WHERE id = {$file['catid']}");
	$sql->query("UPDATE uploader_files SET downloads = downloads + 1 WHERE id = {$file['fileid']}");
	
	// Clear out any previous state
	if (ob_get_level()) ob_end_clean();
	
	// Set the correct headers to make this file downloadable
	header("Pragma: public");
	header("Cache-Control: public");
	header('Connection: Keep-Alive');
	if (!$file['is_image']) {
		// Display download box if it isn't an image
		header("Content-Disposition: attachment");
	}
	header("Content-Description: File Transfer");
	header("Content-Disposition: filename=\"{$file['filename']}\"");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: {$file['size']}");
	header("Content-type: {$file['mime']}");

	readfile("uploads/f/{$_GET['f']}");

	die;