<?php

	function report_check_irc($id) {
		// Never report anything if it's globally disabled
		global $miscdata;
		if (!$miscdata['irc_enable'])
			return false;
		
		// Otherwise check if the specific channel is enabled
		static $db = null;
		if (!isset($db)) {
			global $sql;
			$db = $sql->getresultsbykey("SELECT id, enabled FROM irc_channels");
		}
		return isset($db[$id]) && $db[$id];
	}
	
	// because we're sending this ourselves instead of passing it to reporting.php, the discord equivalent to report_check_irc has to fetch the webhook id
	function report_get_discord_webhook($id) {
		// Never report anything if it's globally disabled
		global $miscdata;
		if (!$miscdata['discord_enable'])
			return null;
		
		// Otherwise check if the specific channel is enabled
		static $db = null;
		if (!isset($db)) {
			global $sql;
			$db = $sql->fetchq("SELECT id, webhook, enabled FROM discord_webhooks", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
		}
		if (!isset($db[$id]) || !$db[$id]['enabled'])
			return null;
		
		// All ok, return the non-null webhook
		return $db[$id]['webhook'];
	}

	// The messages which get reported to both IRC and Discord use the same channel ID constants.
	// This is all thanks to the unremovable default channels.
	function report_new_user($type, $user) {
		global $config;
		// Also show to public channel, but without the admin-only fluff
		irc_send(IRC_STAFF, "New {$type}: #".xk(12)."{$user['id']}".xk(11)." {$user['name']} ".xk()."(IP: ".xk(12)."{$user['ip']}".xk()."): {$config['board-url']}/?u={$user['id']}");
		irc_send(IRC_MAIN,  "New {$type}: #".xk(12)."{$user['id']}".xk(11)." {$user['name']}".xk()."): {$config['board-url']}/?u={$user['id']}");
		
		discord_send(IRC_STAFF, "New {$type}: #{$user['id']} **{$user['name']}** (IP: **{$user['ip']}**): <{$config['board-url']}/?u={$user['id']}>");
		discord_send(IRC_MAIN,  "New {$type}: #{$user['id']} **{$user['name']}**: <{$config['board-url']}/?u={$user['id']}>");
	}
	
	function report_post($type, $forum, $in) {
		global $config;
		irc_send($forum['ircchan'], "{$type} by ".xk(11)."{$in['user']}".xk()." (".xk(12)."{$forum['title']}: ".xk(11)."{$in['thread']}".xk()."): {$config['board-url']}/?p={$in['pid']}");
		discord_send($forum['discordwebhook'], "{$type} by **{$in['user']}** ({$forum['title']}: **{$in['thread']}**): <{$config['board-url']}/?p={$in['pid']}>");
	}
	
	function report_send($id, $msg, $disc_id = null, $disc_msg = null) {
		irc_send($id, $msg);
		discord_send(isset($disc_id) ? $disc_id : $id, isset($disc_msg) ? $disc_msg : preg_replace("/\x03(\d\d)?/i","", $msg));
	}
	
	// IRC Color code setup
	function xk($n = -1) {
		if ($n == -1) $k = "";
			else $k = str_pad($n, 2, 0, STR_PAD_LEFT);
		return "\x03". $k;
	}
	
	//
	// Raw send functions
	//

	
	function irc_send($id, $msg) {
		if (!report_check_irc($id))
			return;
		irc_raw_send($id, $msg);
	}

	// send a raw message to the irc bot, also bypassing the checks
	function irc_raw_send($id, $msg, $port = null) {
		// If $port isn't set, use the board's default
		if (!isset($port)) {
			static $cache = null;
			if (!isset($cache)) {
				global $sql;
				$cache = $sql->resultq("SELECT recvport FROM irc_settings");
			}
			$port = $cache;
		}
		// Send over the line
		if ($sock = fsockopen("localhost", $port)) {
			fwrite($sock, "{$id}|{$msg}");
			fclose($sock);
		}
	}
	
	// stripped down from https://gist.github.com/Mo45/cb0813cb8a6ebcd6524f6a36d4f8862c
	function discord_send($id, $msg) {
		$webhook = report_get_discord_webhook($id);
		if (!$webhook)
			return;
		
		$ch = curl_init("https://discord.com/api/webhooks/{$webhook}");
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(
		[
			'content' => $msg,
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_exec($ch);
		curl_close($ch);
	}
