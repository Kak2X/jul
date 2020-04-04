<?php
// hookya initialization script

add_hook('header-links-2', function() use ($extName) {
	global $loguser;
	return " - <a href='{$extName}/uploader.php'>Uploader</a>".($loguser['id'] ? " (<a href='{$extName}/uploader.php?mode=u&user={$loguser['id']}'>My folders</a>)" : "");
});

add_hook('adminlinkbar', function() use ($extName) {
	adminlinkbar_add('File uploader', array(
		"{$extName}/uploader-countfix.php"   => "File Count Fix",
		"{$extName}/uploader-catman.php"     => "Folder Manager",
	));
});