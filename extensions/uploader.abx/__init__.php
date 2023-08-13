<?php
// hookya initialization script

hook_add('header-links-2', function() use ($extName) {
	global $loguser;
	return " - <a href='{$extName}/uploader.php'>Uploader</a>".($loguser['id'] ? " (<a href='{$extName}/uploader.php?mode=u&user={$loguser['id']}'>My folders</a>)" : "");
});

hook_add('adminlinkbar', function() use ($extName) {
	adminlinkbar_add('File uploader', array(
		"{$extName}/uploader-countfix.php"   => "File Count Fix",
		"{$extName}/uploader-catman.php"     => "Folder Manager",
	));
});