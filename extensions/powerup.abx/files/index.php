<?php
	$meta['noindex'] = true;
	
	// silly companion file to the "Make me a local mod!" link
	require "lib/function.php";
	
	if (!$loguser['id']) {
		errorpage("You aren't allowed to do this.");
	}
	
	$powerto = $sql->resultq("SELECT powl_dest FROM powerups WHERE user = {$loguser['id']}");
	if ($powerto) {
		$sql->query("UPDATE users SET `powerlevel` = {$powerto}, `powerlevel_prev` = {$powerto} WHERE id = {$loguser['id']}");
		$sql->query("DELETE FROM powerups WHERE user = {$loguser['id']}");
		errorpage("Congratulations!<br>You've been promoted to {$pwlnames[$powerto]} <i>with style</i>!");
	} else {
		errorpage("You have no silly powerup notifications, go away.", 'index.php', 'the index page');
	}
	