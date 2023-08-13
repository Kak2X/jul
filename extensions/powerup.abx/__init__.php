<?php

hook_add('header-title-rows', function() use ($extName) {
	global $sql, $loguser, $pwlnames;
	if ($loguser['id']) {
		$lolz = $sql->resultq("SELECT powl_dest FROM powerups WHERE user = {$loguser['id']}");
		if ($lolz) {
			return "<br><a href='{$extName}'>Make me a ".htmlspecialchars($pwlnames[$lolz])."!</a>";
		}
	}
	return "";
});