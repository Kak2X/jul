<?php
// hookya initialization script

hook_add('header-links-2', function() use ($extName) {
	global $loguser;
	return " - <a href='{$extName}/uploader.php'>Uploader</a>".($loguser['id'] ? " (<a href='{$extName}/uploader.php?mode=u&user={$loguser['id']}'>My folders</a>)" : "");
});

hook_add('adminlinkbar', function() use ($extName) {
	adminlinkbar_add('File uploader', array(
		"{$extName}/uploader-countfix.php"   => "File Count Fix",
		[
			"Folder Manager" => [
				"{$extName}/uploader-catman.php" => "Shared folders",
				"{$extName}/uploader-catman.php?mode=u" => "Personal folders",
			],
		],
	));
});

hook_add('profile-options', function($_, &$options) use ($extName) {
	$options[0]["View personal folders"] = ["{$extName}/uploader.php?mode=u&user={$_GET['id']}"];
});

hook_add('user-registering', function($_, $userid) {
	global $sql;
	// Automatically create a personal folder for the user upon registration
	$values = [
		'title'          => "My Files",
		'description'    => "",
		'user'           => $userid,
		'ord'            => 0,
		'minpowerread'   => PWL_MIN,
		'minpowerupload' => PWL_ADMIN,
		'minpowermanage' => PWL_ADMIN,
	];
	$sql->queryp("INSERT INTO uploader_cat SET ".mysql::setplaceholders($values)."", $values);
});		