<?php

function imageupload($file, $maxsize, $x, $y, $dest = false){
	global $config;
	if (!$config['allow-image-uploads']) return false;
	
	if (!$file['tmp_name'])
		errorpage("No file selected.");

	if ($file['size'] > $maxsize)
		errorpage("File size limit exceeded.");
	
	list($width, $height) = getimagesize($file['tmp_name']);
	
	if (!$width || !$height)
		errorpage("This isn't a supported image type.");
	
	if ($width > $x || $height > $y)
		errorpage("Maximum image size exceeded (Your image: $width*$height | Expected: $x*$y).");
	
	if (!$dest)	return "data:".$file['type'].";base64,".base64_encode(file_get_contents($file['tmp_name']));
	else return move_uploaded_file($file['tmp_name'], $dest);
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