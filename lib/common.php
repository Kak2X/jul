<?php
	
	// Set this right away to hopefully prevent fuckups
	ini_set("default_charset", "UTF-8");
	
	// UTF-8 time?
	header("Content-type: text/html; charset=utf-8'");

	// cache bad
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Pragma: no-cache');
	
	$startingtime = microtime(true);
	
	$errors = array();
	set_error_handler('error_reporter');
	set_exception_handler('exception_reporter');	
	
	// Attempt to connect or give up
	$sql	= new mysql;
	$sql->connect($sqlhost, $sqluser, $sqlpass, $dbname);
	if (!$sql->connection) {
		if ($config['always-show-debug'] || $x_hacks['superadmin'] || $x_hacks['adminip'] == $_SERVER['REMOTE_ADDR']) {
			fatal_error("Connection Error", $sql->error, "<!--", "-->");
		} else {
			dialog("This board is experiencing technical difficulties.<br><br>Click <a href='?".urlencode($_SERVER['QUERY_STRING'])."'>here</a> to try again.", "Oops", $config['board-name']);
		}
	}
	
	// Just fetch now everything from misc that's going to be used on every page
	$miscdata = $sql->fetchq("SELECT disable, views, scheme, specialtitle, announcementforum, backup FROM misc");

	// Wait for the midnight backup to finish...
	if ($miscdata['backup'] || (int)date("Gi") < 5) {
		header("HTTP/1.1 503 Service Unavailable");
		$title 		= "{$config['board-name']} -- Temporarily down";
		
		if ((int)date("Gi") < 5) {
			$messagetitle = "It's Midnight Backup Time Again";
			$message 	  = "The daily backup is in progress. Check back in about five minutes.";
		} else {
			$messagetitle = "Backup Time";
			$message 	  = "A backup is in progress. Please check back in a couple of minutes.";
		}
		if ($config['irc-servers']) {
			$message .= "<br>
						<br>Feel free to drop by IRC:
						<br><b>{$config['irc-servers'][1]}</b> &mdash; <b>".implode(", ", $config['irc-channels'])."</b>";
		}
		dialog($message, $messagetitle, $title);
	}
	
	if (file_exists("lib/firewall.php") && $config['enable-firewall']) {
		require 'lib/firewall.php';
	} else {
		$die = 0;
	}
	

	if ($die || filter_int($_GET['sec'])) {
		if ($die) {
			$sql->query("INSERT INTO `minilog` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `time` = '". ctime() ."', `banflags` = '$banflags'");

			if ($_COOKIE['loguserid'] > 0) {
				$newid	= 0;
			} elseif (!$_COOKIE['loguserid'])
				$newid	= 0 - ctime();

			if (isset($newid)) {
				setcookie('loguserid',$newid,2147483647);
			}

		}
		
		header("HTTP/1.1 403 Forbidden");

		?>
		<title>Error</title>
		<body style="background: #000; color: #fff;">
			<span style="font-family: Verdana, sans-serif; text-align: center">
				Suspicious request detected (e.g. bot or malicious tool).
		<?php
		die;
	}




	// determine if the current request is an ajax request, currently only a handful of libraries
	// set the x-http-requested-with header, with the value "XMLHttpRequest"
	if (isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) == "xmlhttprequest") {
		define("IS_AJAX_REQUEST", true); // ajax request!
	} else {
		define("IS_AJAX_REQUEST", false);
	}
	
	// determine the origin of the request
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : filter_string($_SERVER['HTTP_REFERER']);
	if ($origin && (parse_url($origin, PHP_URL_HOST) == parse_url($config['board-url'], PHP_URL_HOST))) {
		define("SAME_ORIGIN", true);
	} else {
		define("SAME_ORIGIN", false);
	}

	
	// Just making sure.  Don't use this anymore.
	// (This is backup code to auto update passwords from cookies.)
	/*
	if (filter_int($_COOKIE['loguserid']) && filter_string($_COOKIE['logpassword'])) {
		$loguserid = intval($_COOKIE['loguserid']);

		$passinfo = $sql->fetchq("SELECT name,password FROM `users` WHERE `id`='$loguserid'");
		$logpassword = shdec($_COOKIE['logpassword']);

		// Passwords match
		if ($passinfo['password'] === md5($logpassword)) {
			$logpwenc = getpwhash($logpassword, $loguserid);
			$sql->query("UPDATE users SET `password` = '{$logpwenc}' WHERE `id` = '{$loguserid}'");
			xk_ircsend("102|".xk(3)."Password hash for ".xk(9).$passinfo['name'].xk(3)." (uid ".xk(9).$loguserid.xk(3).") has been automatically updated (from cookie).");

			$verify = create_verification_hash(0, $logpwenc);
			setcookie('logverify',$verify,2147483647, "/", $_SERVER['SERVER_NAME'], false, true);
			$_COOKIE['logverify'] = $verify; // above only takes effect after next page load

			unset($verify);
		}
		setcookie('logpassword','', time()-3600, "/", $_SERVER['SERVER_NAME'], false, true);
		unset($passinfo);
	}
	$logpassword = null;
	$logpwenc = null;
	*/
	
	// Update timed user bans
	$sql->query("
		UPDATE `users`
		SET `ban_expire` = 0, `group` = ".GROUP_NORMAL."
		WHERE `ban_expire` != 0 AND `group` = ".GROUP_BANNED." AND `ban_expire` < ".ctime());
	
	// Update timed IP Bans before attempting to check if we're IP Banned
	$sql->query("DELETE FROM `ipbans` WHERE `expire` != 0 AND `expire` < ".ctime());
		
	$loguser = array();
	
	if(isset($_COOKIE['loguserid']) && isset($_COOKIE['logverify'])) {
		$loguserid 	= (int) $_COOKIE['loguserid'];
		$loguser 	= $sql->fetchq("SELECT * FROM `users` WHERE `id` = $loguserid");

		$logverify 	= $_COOKIE['logverify'];
		$verifyid 	= (int) substr($logverify, 0, 1);

		$verifyhash = create_verification_hash($verifyid, $loguser['password']);

		// Compare what we just created with what the cookie says, assume something is wrong if it doesn't match
		if ($verifyhash !== $logverify)
			$loguser = NULL;
		
		unset($loguserid, $logverify, $verifyid, $verifyhash);
	}
	
	if ($config['force-user-id'])
		$loguser = $sql->fetchq("SELECT * FROM `users` WHERE `id` = {$config['force-user-id']}");

	
	if ($loguser) {
		
		$loguser['tzoff'] = $loguser['timezone'] * 3600;
		
		if (!$loguser['dateformat'])
			$loguser['dateformat'] = $config['default-dateformat'];
		if (!$loguser['dateshort'])
			$loguser['dateshort'] = $config['default-dateshort'];
		
		// Load inventory
		$itemdb = getuseritems($loguser['id']);
		
		// Determine special effects from inventory items
		// NOTE: This only counts for effects only the logged in user can see.
		//       (read: not applicable for effects that force the gender)
		if ($itemdb) {
			foreach($itemdb as $item) {
				if ($item['effect'] == 5) $hacks['comments'] = true;
			}
		}
		
		if ($loguser['id'] == 1) {
			$hacks['comments'] = true;
		}
	}
	else {
		$loguser = array(
			'id'			=> 0, // This is a much more useful value to default to
			'name'			=> '',
			'password'		=> '',
			'viewsig'		=> 1,
			'group' 		=> GROUP_GUEST,
			'signsep'		=> 0,
			'dateformat'	=> $config['default-dateformat'],
			'dateshort'		=> $config['default-dateshort'],
			'tzoff'			=> 0,
			'scheme'		=> 0,
			'title'			=> '',
			'hideactivity'	=> 0,
		);	
	}	
	
	if ($x_hacks['superadmin']) $loguser['group'] = GROUP_SYSADMIN;
	
	
	/*
		Permission setup
	*/
	$loguser['permflags'] = load_perm($loguser['id'], $loguser['group']);
	
	register_shutdown_function('error_printer', false, has_perm('view-debugger'), $GLOBALS['errors']);
	
	// Still define this for custom "You are banned" messages
	$banned    = (int) ($loguser['group'] == GROUP_BANNED || $loguser['group'] == GROUP_PERMABANNED);
	
	if ($miscdata['disable']) {
		if (!has_perm('bypass-lockdown') && $_SERVER['REMOTE_ADDR'] != $x_hacks['adminip']) {
			http_response_code(500);
			dialog("We'll be back later.", "Down for maintenance", "{$config['board-name']} is offline for now");
		} else {
			$config['title-submessage'] .= ($config['title-submessage'] ? "<br>" : "")."(THE BOARD IS DISABLED)";
		}
	}
	
	// >_>
	$isChristmas = (date('n') == 12);
	
	$ipbanned = $torbanned = $isbot = 0;
	
	$bpt_flags = 0;
	
	if (!($clientip    = filter_var(filter_string($_SERVER['HTTP_CLIENT_IP']),       FILTER_VALIDATE_IP))) $clientip    = "";
	if (!($forwardedip = filter_var(filter_string($_SERVER['HTTP_X_FORWARDED_FOR']), FILTER_VALIDATE_IP))) $forwardedip = "";	
	
	// Build the query to check if we're IP Banned
					  $checkips  = "INSTR('{$_SERVER['REMOTE_ADDR']}',ip) = 1";
	if ($forwardedip) $checkips .= " OR INSTR('$forwardedip',ip) = 1";
	if ($clientip)    $checkips .= " OR INSTR('$clientip',ip) = 1";

	if($sql->resultq("SELECT COUNT(*) FROM ipbans WHERE $checkips")) $ipbanned = 1;
	if($sql->resultq("SELECT COUNT(*) FROM tor WHERE `ip` = '{$_SERVER['REMOTE_ADDR']}' AND `allowed` = '0'")) $torbanned = 1;

	
	$botinfo = $sql->fetchq("SELECT signature, malicious FROM bots WHERE INSTR('".addslashes(strtolower($_SERVER['HTTP_USER_AGENT']))."', signature) > 0");
	if ($botinfo) {
		$isbot = 1;
		if ($botinfo['malicious']) {
			$ipbanned = 1;
			if (!$sql->resultq("SELECT 1 FROM ipbans WHERE $checkips")) {
				$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `reason`='Malicious bot.', `date` = '". ctime() ."', `banner` = '1'");
				xk_ircsend("1|Auto IP Banned malicious bot with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) .".");
			}
		}
	}
	
	$url = $_SERVER['REQUEST_URI'];

	if ($ipbanned) {
		$url .= ' (IP banned)';
		$bpt_flags = $bpt_flags & BPT_IPBANNED;
	}

	if ($torbanned) {
		$url .= ' (Tor proxy)';
		$bpt_flags = $bpt_flags & BPT_TOR;
		$sql->query("UPDATE `tor` SET `hits` = `hits` + 1 WHERE `ip` = '{$_SERVER['REMOTE_ADDR']}'");
	}
	
	if ($isbot) {
		$url .= ' (Bot)';
		$bpt_flags = $bpt_flags & BPT_BOT;
	}
	

	if ($origin && !SAME_ORIGIN) {
		$sql->queryp("INSERT INTO referer (time, url, ref, ip) VALUES (:time,:url,:ref,:ip)",
		[
			'time' => ctime(),
			'url'	=> $url,
			'ref'	=> $_SERVER['HTTP_REFERER'],
			'ip'	=> $_SERVER['REMOTE_ADDR']
		]);
	}

	$sql->query("DELETE FROM guests WHERE ip = '{$_SERVER['REMOTE_ADDR']}' OR date < ".(ctime() - 300));
	
	if ($loguser['id']) {
			
		if (!IS_AJAX_REQUEST) {
			
			$influencelv = calclvl(calcexp($loguser['posts'], (ctime() - $loguser['regdate']) / 86400));

			// Alart #defcon?
			if ($loguser['lastip'] != $_SERVER['REMOTE_ADDR']) {
				// Determine IP block differences
				$ip1 = explode(".", $loguser['lastip']);
				$ip2 = explode(".", $_SERVER['REMOTE_ADDR']);
				for ($diff = 0; $diff < 3; ++$diff)
					if ($ip1[$diff] != $ip2[$diff]) break;
				if ($diff == 0) $color = xk(4);	// IP completely different
				else            $color = xk(8); // Not all blocks changed
				$diff = "/".($diff+1)*8;

				xk_ircsend("102|". xk(7) ."User {$loguser['name']} (id {$loguser['id']}) changed from IP ". xk(8) . $loguser['lastip'] . xk(7) ." to ". xk(8) . $_SERVER['REMOTE_ADDR'] .xk(7). " ({$color}{$diff}" .xk(7). ")");
			}


			$sql->queryp("
				UPDATE users
				SET lastactivity = :lastactivity, lastip = :lastip, lasturl = :lasturl ,lastforum = :lastforum, influence = :influence
				WHERE id = {$loguser['id']}",
				[
					'lastactivity' 	=> ctime(),
					'lastip' 		=> $_SERVER['REMOTE_ADDR'],
					'lasturl' 		=> $url,
					'lastforum'		=> 0,
					'influence'		=> $influencelv,
				]);
			
		}

	} else {
		$sql->queryp("
			INSERT INTO guests (ip, date, useragent, lasturl, lastforum, flags) VALUES (:ip, :date, :useragent, :lasturl, :lastforum, :flags)",
			[
				'ip'			=> $_SERVER['REMOTE_ADDR'],
				'date'			=> ctime(),
				'useragent'		=> $_SERVER['HTTP_USER_AGENT'],
				'lasturl'		=> $url,
				'lastforum'		=> 0,
				'flags'			=> $bpt_flags,
			]);
	}
	
	
	if ($ipbanned) {
		if ($loguser['title'] == "Banned; account hijacked. Contact admin via PM to change it.") {
			$reason	= "Your account was hijacked; please contact {$config['admin-name']} to reset your password and unban your account.";
		} elseif ($loguser['title']) {
			$reason	= "Ban reason: ". $loguser['title'] ."<br>If you think have been banned in error, please contact {$config['admin-name']}.";
		} else {
			$reason	= $sql->resultq("SELECT `reason` FROM ipbans WHERE $checkips");
			$reason	= ($reason ? "Reason: $reason" : "<i>(No reason given)</i>");
		}
		
		$message = 	"You are banned from this board.".
					"<br>". $reason .
					"<br>".
					"<br>If you think you have been banned in error, please contact the administrator:".
					"<br>E-mail: {$config['admin-email']}";
		
		dialog($message, "Banned", $config['board-name']);
		
	}
	if ($torbanned) {
		$message = 	"You appear to be using a Tor proxy. Due to abuse, Tor usage is forbidden.".
					"<br>If you have been banned in error, please contact {$config['admin-name']}.".
					"<br>".
					"<br>E-mail: {$config['admin-email']}";
		
		dialog($message, "Tor is not allowed", $config['board-name']);
	}
	
	/*
		View milestones
	*/

	$views = $miscdata['views'] + 1;
	
	if (!$isbot && !IS_AJAX_REQUEST) {
		
		// Don't increment the view counter for bots
		$sql->query("UPDATE misc SET views = views + 1");
		
		// Log hits close to a milestone
		if($views%10000000>9999000 || $views%10000000<1000) {
			$sql->query("INSERT INTO hits VALUES ($views ,{$loguser['id']}, '{$_SERVER['REMOTE_ADDR']}', ".ctime().")");
		}
		
		// Print out a message to IRC whenever a 10-million-view milestone is hit
		if (
			 $views % 10000000 >  9999994 ||
			($views % 10000000 >= 9991000 && $views % 1000 == 0) || 
			($views % 10000000 >= 9999900 && $views % 10 == 0) || 
			($views > 5 && $views % 10000000 < 5)
		) {
			// View <num> by <username/ip> (<num> to go)
			xk_ircsend("0|View ". xk(11) . str_pad(number_format($views), 10, " ", STR_PAD_LEFT) . xk() ." by ". ($loguser['id'] ? xk(11) . str_pad($loguser['name'], 25, " ") : xk(12) . str_pad($_SERVER['REMOTE_ADDR'], 25, " ")) . xk() . ($views % 1000000 > 500000 ? " (". xk(12) . str_pad(number_format(1000000 - ($views % 1000000)), 5, " ", STR_PAD_LEFT) . xk(2) ." to go" . xk() .")" : ""));
		}
	}

	// Dailystats update in one query
	$sql->query("INSERT INTO dailystats (date, users, threads, posts, views) " .
	             "VALUES ('".date('m-d-y',ctime())."', (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM threads), (SELECT COUNT(*) FROM posts), $views) ".
	             "ON DUPLICATE KEY UPDATE users=VALUES(users), threads=VALUES(threads), posts=VALUES(posts), views=$views");
	

	$specialscheme = "";
	
	// "Mobile" layout
	$smallbrowsers	= array("Nintendo DS", "Android", "PSP", "Windows CE", "BlackBerry", "Mobile");
	if ( (str_replace($smallbrowsers, "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) || filter_int($_GET['mobile'])) {
		$loguser['layout']		= 2;
		$loguser['viewsig']		= 0;
		$config['board-title']				= "<span style='font-size: 2em'>{$config['board-name']}</span>";
		$x_hacks['smallbrowse']	= true;
	}
	
	/*
		Other helpful stuff
	*/
	
	// filename of running script
	$path = explode("/", $_SERVER['SCRIPT_NAME']);
	$scriptname = end($path);
	unset($path);
	
	// group names
	// we have to do this since they can be added or removed
	$grouplist = $sql->fetchq("SELECT id, name, namecolor0, namecolor1, namecolor2 FROM perm_groups", PDO::FETCH_UNIQUE, false, true);
	

//	$atempval	= $sql -> resultq("SELECT MAX(`id`) FROM `posts`");
//	if ($atempval == 199999 && $_SERVER['REMOTE_ADDR'] != "172.130.244.60") {
//		//print "DBG ". strrev($atempval);
//		require "dead.php";
//		die();
//	}

/*
	// Doom timer setup
	$getdoom	= true;
	require "ext/mmdoom.php";
*/
	// When a post milestone is reached, everybody gets rainbow colors for a day
	if (!$x_hacks['rainbownames']) {
		$x_hacks['rainbownames'] = ($sql->resultq("SELECT `date` FROM `posts` WHERE (`id` % 100000) = 0 ORDER BY `id` DESC LIMIT 1") > ctime()-86400);
	}