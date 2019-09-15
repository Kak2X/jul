<?php

	// Validation reporting (usually a query result is passed here)
	function checkres($r) {
		return $r 
			? "<span class='c-success'>OK!</span>\n" 
			: "<span class='c-error'>ERROR!</span>\n";
	}
	function checkresmulti($r) {
		foreach ($r as $x) {
			if (!$x) return checkres(false);
		}
		return checkres(true);
	}
	
	// Utilities for db upgrades
	function get_available_db_version() {
		return count(glob("update/*.php", GLOB_NOSORT));
	}
	
	function get_current_db_version() {
		return (file_exists(DBVER_PATH) ? (int) file_get_contents(DBVER_PATH) : 0);
	}
	
	function updates_available() {
		return get_available_db_version() > get_current_db_version();
	}
	
	// To view and reset the upgrade step
	function update_step() {
		global $_updstp;
		print "<div class='center b'>[ Step ".(++$_updstp)." ]</div>";
	}
	function reset_update_step() {
		global $_updstp;
		$_updstp = 0;
	}
	
	function add_scheme($theme) {
		global $sql;
		$used = $sql->resultq("SELECT COUNT(*) FROM `schemes` WHERE id = '{$theme['id']}'");
		if ($used) {
			unset($theme['id']);
		}
		return $sql->queryp("INSERT INTO `schemes` SET ".mysql::setplaceholders($theme), $theme);
	}