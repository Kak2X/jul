<?php

	// Banner 0 = automatic ban
	function ipban($ip, $reason, $ircreason = NULL, $banner = 0) {
		global $sql;
		$sql->query("INSERT INTO `ipbans` SET `ip` = '{$ip}', `reason`='{$reason}', `date` = '". ctime() ."', `banner` = '{$banner}'");
		if ($ircreason !== NULL) {
			xk_ircsend("1|{$ircreason}");
		}
	}
	
	function userban() {
		// WIP
	}
