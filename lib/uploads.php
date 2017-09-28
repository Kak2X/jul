<?php

function imageupload($file, $maxsize, $x, $y, $dest = false, $qdata = NULL){
	global $config;
	if (!$config['allow-image-uploads']) return false;
	
	if (!$file['tmp_name'])
		errorpage("No file selected.");

	if (!$file['size']) 
		errorpage("This is an 0kb file");
	
	if ($file['size'] > $maxsize)
		errorpage("File size limit exceeded.");
	
	list($width, $height) = getimagesize($file['tmp_name']);
	
	if (!$width || !$height)
		errorpage("This isn't a supported image type.");
	
	if ($width > $x || $height > $y)
		errorpage("Maximum image size exceeded (Your image: {$width}x{$height} | Expected: {$x}x{$y}).");
	
	if (!$dest)	{
		return "data:".$file['type'].";base64,".base64_encode(file_get_contents($file['tmp_name']));
	} else {
		// New image? If so, add info to db
		if ($qdata !== NULL) {
			global $sql;
			$sql->queryp("INSERT INTO user_avatars (user, file, title, hidden) VALUES (?,?,?,?)", $qdata);
		}
		return move_uploaded_file($file['tmp_name'], $dest);
	}
}

function fileupload($file, $filename, $private, $dest) {
	// WIP
	global $config, $loguser;
	if (!$config['allow-file-uploads']) return false;
	$sql->queryp("INSERT INTO file_uploads (hash, name, user, private) VALUES (?,?,?,?)", [file_hash($filename, $loguser), $filename, $loguser['id'], $private]);
	move_uploaded_file($file['tmp_name'], $dest);
	return $sql->insert_id();
}

function file_hash($filename, $logdata) {
	return hash('sha256', $filename . $loguser['password'] . $loguser['name']);
}

function sizeunits($bytes) {
	static $sizes = ['B', 'KB', 'MB', 'GB'];
	for ($i = $sbar = 1; $i < 5; ++$i, $sbar *= 1024) { // $sbar defines the size multiplier
		if ($bytes < $sbar * 1024) {
			// only .00 is really worthless to know so cut that out
			return $qseconds = str_replace('.00', '', sprintf("%04.2f", $bytes / $sbar)).' '.$sizes[$i-1];
		}
	}
}