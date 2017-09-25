<?php
	$meta['noindex'] = true;
	
	// silly companion file to the "Make me a local mod!" link
	
	require "lib/function.php";
	
	if (!$loguser['id']) errorpage("You aren't allowed to do this.");
	
	$powerto = $sql->resultq("SELECT group_dest FROM powerups WHERE user = {$loguser['id']}");
	if ($powerto) {
		$sql->query("UPDATE users SET `group` = {$powerto}, `group_prev` = {$powerto} WHERE id = {$loguser['id']}");
		$sql->query("DELETE FROM powerups WHERE user = {$loguser['id']}");
		errorpage("Congratulations!<br>You've been promoted to {$grouplist[$powerto]['name']} <i>with style</i>!");
	} else {
		errorpage("You have no silly powerup notifications.");
	}
	