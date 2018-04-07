<?php
	$startingtime = microtime(true);
	
	// Set this right away to hopefully prevent fuckups
	ini_set("default_charset", "UTF-8");
	
	// UTF-8 time?
	header("Content-type: text/html; charset=utf-8'");

	// cache bad
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Pragma: no-cache');
	
	$errors = array();
	set_error_handler('error_reporter');
	set_exception_handler('exception_reporter');
		
	require 'lib/defines.php';
	require 'lib/config.php';
	require 'lib/mysql.php';
	require 'lib/layout.php';
	require 'lib/rpg.php';
	require 'lib/datetime.php';	
	
	$sql	= new mysql;


	$sql->connect($sqlhost, $sqluser, $sqlpass, $dbname) or
		die("<title>Damn</title>
			<body style=\"background: #000 url('images/bombbg.png'); color: #f00;\">
				<font style=\"font-family: Verdana, sans-serif;\">
				<center>
				<img src=\"images/mysqlbucket.png\" title=\"bought the farm, too\">
				<br><br><font style=\"color: #f88; size: 175%;\"><b>The MySQL server has exploded.</b></font>
				<br>
				<br><font style=\"color: #f55;\">Error: ". $sql->error ."</font>
				<br>
				<br><small>This is not a hack attempt; it is a server problem.</small>
			");
	//$sql->selectdb($dbname) or die("Another stupid MySQL error happened, panic<br><small>". mysql_error() ."</small>");

	// Just fetch now everything from misc that's going to be used on every page
	$miscdata = $sql->fetchq("SELECT disable, views, scheme, specialtitle, private, backup FROM misc");
	
	// Wait for the midnight backup to finish...
	if ($miscdata['backup'] || (int) date("Gi") < 1) {
		header("HTTP/1.1 503 Service Unavailable");
		$title 		= "{$config['board-name']} -- Temporarily down";
		
		if ((int)date("Gi") < 1) {
			$messagetitle = "It's Midnight Backup Time Again";
			$message 	  = "The daily backup is in progress. Check back in about a minute.";
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
	
	// Get the running script's filename
	$path = explode("/", $_SERVER['SCRIPT_NAME']);
	$scriptname = end($path);
	unset($path);
	
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
		
		if ($miscdata['private'] == 2) {
			do404();
		}
		
		header("HTTP/1.1 403 Forbidden");

		die("<title>Error</title>
			<body style=\"background: #000; color: #fff;\">
				<font style=\"font-family: Verdana, sans-serif;\">
				<center>
				Suspicious request detected (e.g. bot or malicious tool).
			");
	}
	
	function do404() {
		header("HTTP/1.1 404 Not Found");
		die;
	}
	
	// Delete expired bans
	$sql->query("
		UPDATE `users` SET 
		    `ban_expire` = 0, 
		    `powerlevel` = powerlevel_prev
		WHERE `ban_expire` != 0 AND 
		      `powerlevel` = '-1' AND
		      `ban_expire` < ".ctime()
	);
	
	$sql->query("DELETE FROM `ipbans`    WHERE `expire` != 0 AND `expire` < ".ctime());
	$sql->query("DELETE FROM `forumbans` WHERE `expire` != 0 AND `expire` < ".ctime());
	
	$loguser = array();

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
	
	if ($config['force-user-id']) {
		// Forcing the user id?
		$loguser = $sql->fetchq("SELECT * FROM `users` WHERE `id` = {$config['force-user-id']}");
		$loguser['lastip'] = $_SERVER['REMOTE_ADDR']; // since these now match, it will not update the lastip value on the db
	} else if (isset($_COOKIE['loguserid']) && isset($_COOKIE['logverify'])) {
		// Are we logged in?
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
	
	if ($loguser) {
		$loguser['tzoff'] = $loguser['timezone'] * 3600;
		
		if (!$loguser['dateformat'])
			$loguser['dateformat'] = $config['default-dateformat'];
		if (!$loguser['dateshort'])
			$loguser['dateshort'] = $config['default-dateshort'];
		
		// Load inventory
		$itemdb = getuseritems($loguser['id']);
		
		// Items effects which only affect the user go here
		if ($itemdb) {
			foreach($itemdb as $item) {
				switch ($item['effect']) {
					// New HTML comment display enable
					case 5: $hacks['comments'] = true; break;
				}
			}
		}
		
		if ($loguser['id'] == 1) {
			$hacks['comments'] = true;
		} //else {
			// Old HTML comment display enable
			//$hacks['comments'] = $sql->resultq("SELECT COUNT(*) FROM `users_rpg` WHERE `uid` = '{$loguser['id']}' AND `eq6` IN ('43', '71', '238')");
		//}
		
		// ?
		/*
		if ($loguser['viewsig'] >= 3)
			return header("Location: /?sec=1");
		*/
		// Normal+ can view the submessage
		if ($loguser['powerlevel'] >= 1) {
			$config['board-title'] .= $config['title-submessage'];
		}
		// Making sure Tina is always admin even if it's displayed as Normal user?
		/*
		if ($loguser['id'] == 175 && !$x_hacks['host'])
			$loguser['powerlevel'] = max($loguser['powerlevel'], 3);
		*/
		
	}
	else {
		$loguser = array(
			'id'			=> 0, // This is a much more useful value to default to
			'name'			=> '',
			'password'		=> '',
			'viewsig'		=> 1,
			'layout'        => 1, // Regular
			'powerlevel' 	=> 0,
			'signsep'		=> 0,
			'dateformat'	=> $config['default-dateformat'],
			'dateshort'		=> $config['default-dateshort'],
			'timezone'      => 0,
			'tzoff'			=> 0,
			'scheme'		=> 0,
			'title'			=> '',
			'hideactivity'	=> 0,
			'uploads_locked'=> 0,
			'pagestyle'     => 0,
		);	
	}
	
	if ($miscdata['private'] == 2 && !$loguser['id']) {
		do404();
	}

	if ($x_hacks['superadmin']) $loguser['powerlevel'] = 4;
	
	register_shutdown_function('error_printer', false, ($loguser['powerlevel'] == 4), $GLOBALS['errors']);

	
	
	$banned    = (int) ($loguser['powerlevel'] <  0);
	$issuper   = (int) ($loguser['powerlevel'] >= 1);
	$ismod     = (int) ($loguser['powerlevel'] >= 2);
	$isadmin   = (int) ($loguser['powerlevel'] >= 3);
	$sysadmin  = (int) ($loguser['powerlevel'] >= 4);
	
	
	$isfullmod = $ismod;
	// >_>
	$isChristmas = (date('n') == 12);
	
	// more >_>
	if ($loguser['uploads_locked']) {
		$config['allow-attachments'] = false;
	}
	
	// Doom timer setup
	//$getdoom = true;
	//require "ext/mmdoom.php";
	
	if ($miscdata['disable']) {
		if (!$sysadmin && $_SERVER['REMOTE_ADDR'] != $x_hacks['adminip']) {
			if ($miscdata['private'] == 2) {
				do404();
			}
			
			http_response_code(500);
			dialog(
				"We'll be back later.",
				"Down for maintenance",
				"{$config['board-name']} is offline for now"
			);
			
		} else {
			$config['title-submessage'] = "<br>(THE BOARD IS DISABLED)";
		}
		/*
		die("
		<title>Damn</title>
			<body style=\"background: #000 url('images/bombbg.png'); color: #f00;\">
				<font style=\"font-family: Verdana, sans-serif;\">
				<center>
				<br><font style=\"color: #f88; size: 175%;\"><b>The board has been taken offline for a while.</b></font>
				<br>
				<br><font style=\"color: #f55;\">This is probably because:
				<br>&bull; we're trying to prevent something from going wrong,
				<br>&bull; abuse of the forum was taking place and needs to be stopped,
				<br>&bull; some idiot thought it'd be fun to disable the board
				</font>
				<br>
				<br>The forum should be back up within a short time. Until then, please do not panic;
				<br>if something bad actually happened, we take backups often.
			");
			*/
	}
	
	
	$ipbanned = $torbanned = $isbot = 0;
	
	$bpt_flags = 0;
	
	// These extra variables are in control of the user. Nuke them if they're not valid IPs
	if (!($clientip    = filter_var(filter_string($_SERVER['HTTP_CLIENT_IP']),       FILTER_VALIDATE_IP))) $clientip    = "";
	if (!($forwardedip = filter_var(filter_string($_SERVER['HTTP_X_FORWARDED_FOR']), FILTER_VALIDATE_IP))) $forwardedip = "";	
	
	// Build the query to check if we're IP Banned
					  $checkips  = "INSTR('{$_SERVER['REMOTE_ADDR']}',ip) = 1";
	if ($forwardedip) $checkips .= " OR INSTR('$forwardedip',ip) = 1";
	if ($clientip)    $checkips .= " OR INSTR('$clientip',ip) = 1";

	$baninfo = $sql->fetchq("SELECT ip, expire FROM ipbans WHERE $checkips");
	if($baninfo) $ipbanned = 1;
	if($sql->resultq("SELECT COUNT(*) FROM tor WHERE `ip` = '{$_SERVER['REMOTE_ADDR']}' AND `allowed` = '0'")) $torbanned = 1;
					
	if ($_SERVER['HTTP_REFERER']) {
		$botinfo = $sql->fetchq("SELECT signature, malicious FROM bots WHERE INSTR('".addslashes(strtolower($_SERVER['HTTP_USER_AGENT']))."', signature) > 0");
		if ($botinfo) {
			$isbot = 1;
			if ($botinfo['malicious']) {
				$ipbanned = 1;
				if (!$sql->resultq("SELECT 1 FROM ipbans WHERE $checkips")) {
					ipban(
						$_SERVER['REMOTE_ADDR'],
						"Malicious bot.",
						xk(7) . "Auto IP Banned malicious bot with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ."."
					);
				}
			}
		}
	}
	
	/*
		Set up extra url info for referer/hit logging
	*/
	$url = $_SERVER['REQUEST_URI'];

	if($ipbanned) {
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
	
	if($loguser['id']) {
			
		if ($loguser['powerlevel'] <= 5 && !IS_AJAX_REQUEST) {
			
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

				// "Transfer" the IP bans just in case
				$oldban = $sql->fetchq("SELECT 1, reason FROM ipbans WHERE ip = '{$loguser['lastip']}'");
				if ($oldban){
					ipban(
						$_SERVER['REMOTE_ADDR'],  // IP to ban
						$oldban['reason'], // Copy over the ban reason
						"Previous IP address was IP banned - updated IP bans list.", // IRC Message 
						IRC_ADMIN // IRC Channel
					);
					die;
				}
				unset($oldban);
				
				// optionally force log out
				if ($config['force-lastip-match']) {
					setcookie('loguserid','', time()-3600, "/", $_SERVER['SERVER_NAME'], false, true);
					setcookie('logverify','', time()-3600, "/", $_SERVER['SERVER_NAME'], false, true);
					// Attempt to preserve current page
					die(header("Location: ?{$_SERVER['QUERY_STRING']}"));
				}
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
			$reason	= "Ban reason: {$loguser['title']}<br>If you think have been banned in error, please contact {$config['admin-name']}.";
		} else {
			$reason	= $sql->resultq("SELECT `reason` FROM ipbans WHERE $checkips");
			$reason	= ($reason ? "Reason: $reason" : "<i>(No reason given)</i>");
		}
		
		$expiration = (
			$baninfo['expire']
			? " until ".printdate($baninfo['expire']).".<br>That's ".timeunits2($baninfo['expire'] - ctime())." from now"
			: ""
		);
		
		$message = 	"You are banned from this board{$expiration}.".
					"<br>". $reason .
					"<br>".
					"<br>If you think you have been banned in error, please contact the administrator:".
					"<br>E-mail: {$config['admin-email']}";
		
		echo dialog($message, "Banned", $config['board-name']);
		
	}
	if ($torbanned) {
		$message = 	"You appear to be using a Tor proxy. Due to abuse, Tor usage is forbidden.".
					"<br>If you have been banned in error, please contact {$config['admin-name']}.".
					"<br>".
					"<br>E-mail: {$config['admin-email']}";
		
		echo dialog($message, "Tor is not allowed", $config['board-name']);
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
		$config['board-title']	= "<span style='font-size: 2em'>{$config['board-name']}</span>";
		$x_hacks['smallbrowse']	= true;
	}
	
	/*
		Other helpful stuff
	*/
	

	

//	$atempval	= $sql -> resultq("SELECT MAX(`id`) FROM `posts`");
//	if ($atempval == 199999 && $_SERVER['REMOTE_ADDR'] != "172.130.244.60") {
//		//print "DBG ". strrev($atempval);
//		require "dead.php";
//		die();
//	}

//  $hacks['noposts'] = true;

	// Doom timer setup
//	$getdoom	= true;
//	require "ext/mmdoom.php";

	// When a post milestone is reached, everybody gets rainbow colors for a day
	if (!$x_hacks['rainbownames']) {
		$x_hacks['rainbownames'] = ($sql->resultq("SELECT `date` FROM `posts` WHERE (`id` % 100000) = 0 ORDER BY `id` DESC LIMIT 1") > ctime()-86400);
	}
	
	// Private board option
	$allowedpages = ['register.php', 'login.php', 'faq.php'];
	if (!$loguser['id'] && $miscdata['private'] && !in_array($scriptname, $allowedpages)) {
		errorpage(
			"You need to <a href='login.php'>login</a> to browse this board.<br>".
			"If you don't have an account you can <a href='register.php'>register</a> one.<br><br>".
			"The Rules/FAQ are available <a href='faq.php'>here</a>."
		);
	}
	unset($allowedpages);
	
	/* we're not Jul, and the special sex->namecolor system got nuked anyway
	if (!$x_hacks['host'] && filter_int($_GET['namecolors'])) {
		//$sql->query("UPDATE `users` SET `sex` = '255' WHERE `id` = 1");
		//$sql->query("UPDATE `users` SET `name` = 'Ninetales', `powerlevel` = '3' WHERE `id` = 24 and `powerlevel` < 3");
		//$sql->query("UPDATE `users` SET `sex` = '9' WHERE `id` = 1");
		//$sql->query("UPDATE `users` SET `sex` = '10' WHERE `id` = 855");
		//$sql->query("UPDATE `users` SET `sex` = '7' WHERE `id` = 18");	# 7
		//$sql->query("UPDATE `users` SET `sex` = '99' WHERE `id` = 21"); #Tyty (well, not anymore)
		//$sql->query("UPDATE `users` SET `sex` = '9' WHERE `id` = 275");

		$sql->query("UPDATE `users` SET `sex` = '4' WHERE `id` = 41");
		$sql->query("UPDATE `users` SET `sex` = '6' WHERE `id` = 4");
		$sql->query("UPDATE `users` SET `sex` = '11' WHERE `id` = 92");
		$sql->query("UPDATE `users` SET `sex` = '97' WHERE `id` = 24");
		$sql->query("UPDATE `users` SET `sex` = '42' WHERE `id` = 45");	# 7
		$sql->query("UPDATE `users` SET `sex` = '8' WHERE `id` = 19");
		$sql->query("UPDATE `users` SET `sex` = '98' WHERE `id` = 1343"); #MilesH
		$sql->query("UPDATE `users` SET `sex` = '12' WHERE `id` = 1296");
		$sql->query("UPDATE `users` SET `sex` = '13' WHERE `id` = 1090");
		$sql->query("UPDATE `users` SET `sex` = '14' WHERE `id` = 6"); #mm88
		$sql->query("UPDATE `users` SET `sex` = '21' WHERE `id` = 1840"); #Sofi
		$sql->query("UPDATE `users` SET `sex` = '22' WHERE `id` = 20"); #nicole
		$sql->query("UPDATE `users` SET `sex` = '23' WHERE `id` = 50"); #Rena
		$sql->query("UPDATE `users` SET `sex` = '24' WHERE `id` = 2069"); #Adelheid/Stark/etc.

		$sql->query("UPDATE `users` SET `name` = 'Xkeeper' WHERE `id` = 1"); #Xkeeper. (Change this and I WILL Z-Line you from Badnik for a week.)

	}
*/

// For our convenience (read: to go directly into a query), at the cost of sacrificing the NULL return value
function filter_int(&$v) 		{ return (int) $v; }
function filter_float(&$v)		{ return (float) $v; }
function filter_bool(&$v) 		{ return (bool) $v; }
function filter_array (&$v)		{ return (array) $v; }
function filter_string(&$v) 	{ return (string) $v; }

function readsmilies($path = 'smilies.dat') {
	global $x_hacks;
	if ($x_hacks['host']) {
		$fpnt = fopen('smilies2.dat','r');
	} else {
		$fpnt = fopen($path,'r');
	}
	for ($i = 0; $smil[$i] = fgetcsv($fpnt, 300, ','); ++$i);
	$r = fclose($fpnt);
	return $smil;
}

function readpostread($userid) {
	global $sql;
	if (!$userid) return array();
	return $sql->getresultsbykey("SELECT forum, readdate FROM forumread WHERE user = $userid");
}

function dotags($msg, $user, &$tags = array()) {
	global $sql, $loguser;
	if (is_string($tags)) {
		$tags	= json_decode($tags, true);
	}

	if (empty($tags) && empty($user)) {
		// settags sent us here and we have nothing to go off of.
		// Shrug our shoulders, and move on.
		return $msg;
	}

	if (empty($tags)) {
		$tags	= array(
			'/me '			=> "*<b>". $user['username'] ."</b> ",
			'&date&'		=> date($loguser['dateformat'], ctime() + $loguser['tzoff']),
			'&numdays&'		=> floor($user['days']),

			'&numposts&'	=> $user['posts'],
			'&rank&'		=> getrank($user['useranks'], '', $user['posts'], 0),
			'&postrank&'	=> $sql->resultq("SELECT count(*) FROM `users` WHERE posts > {$user['posts']}") + 1,
			'&5000&'		=>  5000 - $user['posts'],
			'&10000&'		=> 10000 - $user['posts'],
			'&20000&'		=> 20000 - $user['posts'],
			'&30000&'		=> 30000 - $user['posts'],

			'&exp&'			=> $user['exp'],
			'&expgain&'		=> calcexpgainpost($user['posts'], $user['days']),
			'&expgaintime&'	=> calcexpgaintime($user['posts'], $user['days']),

			'&expdone&'		=> $user['expdone'],
			'&expdone1k&'	=> floor($user['expdone'] /  1000),
			'&expdone10k&'	=> floor($user['expdone'] / 10000),

			'&expnext&'		=> $user['expnext'],
			'&expnext1k&'	=> floor($user['expnext'] /  1000),
			'&expnext10k&'	=> floor($user['expnext'] / 10000),

			'&exppct&'		=> sprintf('%01.1f', ($user['lvllen'] ? (1 - $user['expnext'] / $user['lvllen']) : 0) * 100),
			'&exppct2&'		=> sprintf('%01.1f', ($user['lvllen'] ? (    $user['expnext'] / $user['lvllen']) : 0) * 100),

			'&level&'		=> $user['level'],
			'&lvlexp&'		=> calclvlexp($user['level'] + 1),
			'&lvllen&'		=> $user['lvllen'],
		);
	}

	$msg	= strtr($msg, $tags);
	return $msg;
}


function doreplace($msg, $posts, $days, $userid, &$tags = null) {
	global $tagval, $sql;

	$user	= $sql->fetchq("SELECT name, useranks FROM `users` WHERE `id` = $userid", PDO::FETCH_ASSOC, mysql::USE_CACHE);

	$userdata		= array(
		'id'		=> $userid,
		'username'	=> $user['name'],
		'posts'		=> $posts,
		'days'		=> $days,
		'useranks'	=> $user['useranks'],
		'exp'		=> calcexp($posts,$days)
	);

	$userdata['level']		= calclvl($userdata['exp']);
	$userdata['expdone']	= $userdata['exp'] - calclvlexp($userdata['level']);
	$userdata['expnext']	= calcexpleft($userdata['exp']);
	$userdata['lvllen']		= totallvlexp($userdata['level']);


	if (!$tags) {
		$tags	= array();
	}
	$msg	= dotags($msg, $userdata, $tags);

	return $msg;
}

function escape_codeblock($text) {
	/* Old code formatting
	$list  = array("[code]", "[/code]", "<", "\\\"" , "\\\\" , "\\'", "[", ":", ")", "_");
	$list2 = array("", "", "&lt;", "\"", "\\", "\'", "&#91;", "&#58;", "&#41;", "&#95;");

	// @TODO why not just use htmlspecialchars() or htmlentities()
	return "[quote]<code>". str_replace($list, $list2, $text[0]) ."</code>[/quote]";
	*/
	// Experimental (did you mean: insane) code block parser
	$text[0] = substr($text[0] , 6, -6);
	$len = strlen($text[0]);
	$intext = $escape = $noprint = false;
	$prev = $ret = '';
	for ($i = 0; $i < $len; ++$i) {
		
		$next = isset($text[0][$i+1]) ? $text[0][$i+1] : NULL;
		
		switch ($text[0][$i]) {
			case '(':
			case ')':
			case '[':
			case ']':
			case '{':
			case '}':
			case '=':
			case '<':
			case '>':
			case ':':
				if ($intext) break;
				$ret .= "<span style='color: #007700'>".htmlentities($text[0][$i])."</span>";
				$noprint = true;
				break;	

			case '+':
			case '-':
			case '&':
			case '|':
			case '!':
				if ($intext) break;
				$ret .= "<span style='color: #C0C0FF'>".htmlentities($text[0][$i])."</span>";
				$noprint = true;
				break;	
				
			// Accounts for /* , */
			case '*':
				if ($intext || $prev == '/' || $next == '/') break;
				$ret .= "<span style='color: #C0C0FF'>".htmlentities($text[0][$i])."</span>";
				$noprint = true;
				break;
				
			// Accounts for /* , */ , //
			case '/':
				if ($intext || $prev == '/' || $next == '/' || $prev == '*' || $next == '*') break;
				$ret .= "<span style='color: #C0C0FF'>".htmlentities($text[0][$i])."</span>";
				$noprint = true;
				break;
				
			case '"':
			case '\'':
				if ($escape || ($intext && $intext != $text[0][$i])) break;
				
				if (!$intext) {
					$ret .= "<span style='color: #DD0000'>";
					$intext = $text[0][$i];
				}
				else {
					$ret .= htmlentities($text[0][$i])."</span>";
					$intext = false;
					$noprint = true;
				}
				break;
				
			case '\\':
				if ($escape) break;
				$escape = $i;
				
		}
		
		if (!$noprint) 	$ret .= htmlspecialchars($text[0][$i]);
		else 			$noprint = false;
		
		$prev = $text[0][$i];
		
		// Escape effect lasts for only one character
		if ($escape && $escape != $i)
			$escape = false;
	}
	
	
	//	Comment lines
	$ret = preg_replace("'\/\*(.*?)\*\/'si", "<span style='color: #FF8000'>/*$1*/</span>",$ret); /* */
	$ret = preg_replace("'\/\/(.*?)\r?\n'i", "<span style='color: #FF8000'>//$1\r\n</span>",$ret); //
	
	//$ret = str_replace("\x09", "&nbsp;&nbsp;&nbsp;&nbsp;", $ret); // Tab
	
	//return "[quote]<code>$ret</code>[/quote]";
	return "[quote]<code style='background: #000 !important; color: #fff'>$ret</code>[/quote]";
}

function doreplace2($msg, $options='0|0', $nosbr = false){
	global $hacks;
	
	// options will contain smiliesoff|htmloff
	$options     = explode("|", $options);
	$smiliesoff  = $options[0];
	$htmloff     = $options[1];


	//$list = array("<", "\\\"" , "\\\\" , "\\'", "[", ":", ")", "_");
	//$list2 = array("&lt;", "\"", "\\", "\'", "&#91;", "&#58;", "&#41;", "&#95;");
	$msg=preg_replace_callback("'\[code\](.*?)\[/code\]'si", 'escape_codeblock',$msg);


	if ($htmloff) {
		$msg = str_replace("<", "&lt;", $msg);
		$msg = str_replace(">", "&gt;", $msg);
	}

	if (!$smiliesoff) {
		global $smilies;
		if (!$smilies) $smilies = readsmilies();
		for($s = 0; $smilies[$s][0]; ++$s){
			$smilie = $smilies[$s];
			$msg = str_replace($smilie[0], "<img src='$smilie[1]' align=absmiddle>", $msg);
		}
	}
	
	// Simple check for skipping BBCode replacements
	if (strpos($msg, "[") !== false){
		$msg=str_replace('[red]',	'<font color=FFC0C0>',$msg);
		$msg=str_replace('[green]',	'<font color=C0FFC0>',$msg);
		$msg=str_replace('[blue]',	'<font color=C0C0FF>',$msg);
		$msg=str_replace('[orange]','<font color=FFC080>',$msg);
		$msg=str_replace('[yellow]','<font color=FFEE20>',$msg);
		$msg=str_replace('[pink]',	'<font color=FFC0FF>',$msg);
		$msg=str_replace('[white]',	'<font color=white>',$msg);
		$msg=str_replace('[black]',	'<font color=0>'	,$msg);
		$msg=str_replace('[/color]','</font>',$msg);
		$msg=preg_replace("'\[quote=(.*?)\]'si", '<blockquote><font class=fonts><i>Originally posted by \\1</i></font><hr>', $msg);
		$msg=str_replace('[quote]','<blockquote><hr>',$msg);
		$msg=str_replace('[/quote]','<hr></blockquote>',$msg);
		$msg=preg_replace("'\[sp=(.*?)\](.*?)\[/sp\]'si", '<span style="border-bottom: 1px dotted #f00;" title="did you mean: \\1">\\2</span>', $msg);
		$msg=preg_replace("'\[abbr=(.*?)\](.*?)\[/abbr\]'si", '<span style="border-bottom: 1px dotted;" title="\\1">\\2</span>', $msg);
		$msg=str_replace('[spoiler]','<div class="fonts pstspl2"><b>Spoiler:</b><div class="pstspl1">',$msg);
		$msg=str_replace('[/spoiler]','</div></div>',$msg);
		$msg=preg_replace("'\[(b|i|u|s)\]'si",'<\\1>',$msg);
		$msg=preg_replace("'\[/(b|i|u|s)\]'si",'</\\1>',$msg);
		$msg=preg_replace("'\[img\](.*?)\[/img\]'si", '<img src=\\1>', $msg);
		$msg=preg_replace("'\[url\](.*?)\[/url\]'si", '<a href=\\1>\\1</a>', $msg);
		$msg=preg_replace("'\[url=(.*?)\](.*?)\[/url\]'si", '<a href=\\1>\\2</a>', $msg);
	}

	do {
		$msg	= preg_replace("/<(\/?)t(able|h|r|d)(.*?)>(\s+?)<(\/?)t(able|h|r|d)(.*?)>/si",
				"<\\1t\\2\\3><\\5t\\6\\7>", $msg, -1, $replaced);
	} while ($replaced >= 1);

	// Comment display
	if ($hacks['comments']) {
		$msg=str_replace("<!--", '<span style="color:#80ff80">&lt;!--', $msg);
		$msg=str_replace("-->", '--&gt;</span>', $msg);
	}

	if (!$nosbr) sbr(0,$msg);

	return $msg;
}


function settags($text, $tags) {

	if (!$tags) {
		return $text;
	} else {
		$text	= dotags($text, array(), $tags);
	}

	return $text;
}

/*
	dobreadcrumbs: create the navigation links at the top of the pagefooter
	$set: array in <label> => <link> format, where the if the link is NULL no link is printed
	$right: HTML printed on the right side
*/
function dobreadcrumbs($set, $right = "") {
	global $config;
	$out = "<a href='index.php'>{$config['board-name']}</a>";
	foreach ($set as $var => $link) {
		if ($link !== NULL) {
			$out .= " - <a href='{$link}'>".htmlspecialchars($var)."</a>";
		} else {
			$out .= " - ".htmlspecialchars($var);
		}
	}
	return "<table class='font w'><tr><td>{$out}</td><td class='fonts right'>{$right}</td></tr></table>";
}



function doforumlist($id, $name = '', $shownone = ''){
	global $loguser,$sql;
	
	if (!$name) {
		$forumlinks = "
		<table>
			<tr>
				<td class='font'>Forum jump: </td>
				<td>
					<form>
						<select onChange='parent.location=\"forum.php?id=\"+this.options[this.selectedIndex].value' style='position:relative;top:8px'>
		";
		$showhidden = 0;
	}
	else {
		$forumlinks = "";
		$showhidden = 1;
	}
	// (`c.minpower` <= $power OR `c.minpower` <= 0) is not really necessary but whatever
	$forums = $sql->query("
		SELECT f.id, f.title, f.catid, f.hidden, c.name catname
		FROM forums f
		
		LEFT JOIN categories c ON f.catid = c.id
		
		WHERE 	(c.minpower <= {$loguser['powerlevel']} OR !c.minpower)
			AND (f.minpower <= {$loguser['powerlevel']} OR !f.minpower)
			AND (!f.hidden OR {$loguser['powerlevel']} >= 4 OR $showhidden)
			AND !ISNULL(c.id)
			OR  f.id = $id
			
		ORDER BY f.catid, f.forder, f.id
	");
	
	$prev 	= NULL;	// In case the current forum is in an invalid category, the non-existing category name won't be printed
	
	while ($forum = $sql->fetch($forums)) {
		// New category
		if ($prev != $forum['catid']) {
			$forumlinks .= "</optgroup><optgroup label=\"{$forum['catname']}\">";
			$prev = $forum['catid'];
		}
		
		if ($forum['hidden']) {
			$forum['title'] = "({$forum['title']})";
		}
		
		$forumlinks .= "<option value={$forum['id']}".($forum['id'] == $id ? ' selected' : '').">{$forum['title']}</option>";
	}
	
	// Multi-use forum list
	if ($name) {
		if ($shownone) $forumlinks = "<option value=0>$shownone</option>$forumlinks";
		return "<select name='$name'>$forumlinks</select>";
	}
	$forumlinks .= "	</optgroup>
					</select>
				</form>
			</td>
		</tr>
	</table>";
	
	return $forumlinks;
}

// Note: -1 becomes NULL
function doschemeList($all = false, $sel = 0, $name = 'scheme'){
	global $sql;
	
	$schemes = $sql->query("SELECT * FROM schemes ".($all ? "ORDER BY special," : "WHERE special = 0 ORDER BY")." ord, id");
	
	if ($sel === NULL) $sel = '-1';
	$scheme[$sel] = "selected";
	
	$input 	= "";
	$prev	= 1; // Previous special value
	while($x = $sql->fetch($schemes)){
		// If we only fetch normal schemes don't bother separating between them.
		if ($all && $prev != $x['special']){
			$prev 	= $x['special'];
			$input .= "</optgroup><optgroup label='".($prev ? "Special" : "Normal")." schemes'>";
		}
		$input	.= "<option value='{$x['id']}' ".filter_string($scheme[$x['id']]).">{$x['name']}</option>";
	}
	return "<select name='$name'>".($all ? "<option value='-1' ".filter_string($scheme['-1']).">None</option>" : "")."$input</optgroup></select>";
}

// When it comes to this kind of code being repeated across files...
function dothreadiconlist($iconid = NULL, $customicon = '') {
	


	// Check if we have selected one of the default thread icons
	$posticons = file('posticons.dat');
	
	if (isset($iconid) && $iconid != -1)
		$selected = trim($posticons[$iconid]);
	else
		$selected = trim($customicon);
	
	
	$customicon = $selected;
	
	$posticonlist = "";
	
	for ($i = 0; isset($posticons[$i]);) {
		
		$posticons[$i] = trim($posticons[$i]);
		// Does the icon match?
		if($selected == $posticons[$i]){
			$checked    = 'checked=1';
			$customicon	= '';					// If so, blank out the custom icon
		} else {
			$checked    = '';
		}

		$posticonlist .= "<input type=radio class=radio name=iconid value=$i $checked>&nbsp;<img src='{$posticons[$i]}' HEIGHT=15 WIDTH=15>&nbsp; &nbsp;";

		$i++;
		if($i % 10 == 0) $posticonlist .= '<br>';
	}

	// Blank or set to None?
	if (!$selected || $iconid == -1) $checked = 'checked=1';
	
	$posticonlist .= 	"<br>".
						"<input type=radio class='radio' name=iconid value=-1 $checked>&nbsp; None &nbsp; &nbsp;".
						"Custom: <input type='text' name=custposticon VALUE=\"".htmlspecialchars($customicon)."\" SIZE=40 MAXLENGTH=100>";
	
	return $posticonlist;
}

function ctime(){global $config; return time() + $config['server-time-offset'];}
function cmicrotime(){global $config; return microtime(true) + $config['server-time-offset'];}

function getrank($rankset, $title, $posts, $powl, $bandate = NULL){
	global $hacks, $sql;
	$rank	= "";
	if ($rankset == 255) {   //special code for dots
		if (!$hacks['noposts']) {
			// Dot values - can configure
			$pr[5] = 5000;
			$pr[4] = 1000;
			$pr[3] =  250;
			$pr[2] =   50;
			$pr[1] =   10;

			if ($rank) $rank .= "<br>";
			$postsx = $posts;
			
			for ($i = max(array_keys($pr)); $i !== 0; --$i) {
				$dotnum[$i] = floor($postsx / $pr[$i]);		
				$postsx = $postsx - $dotnum[$i] * $pr[$i];	// Posts left
			}
			
			foreach($dotnum as $dot => $num) {
				for ($x = 0; $x < $num; ++$x) {
					$rank .= "<img src='images/dot". $dot .".gif' align='absmiddle'>";
				}
			}
			if ($posts >= 10) $rank = floor($posts / 10) * 10 ." ". $rank;
		}
	}
	else if ($rankset) {
		$posts %= 10000;
		$rank = $sql->resultq("
			SELECT text FROM ranks
			WHERE num <= $posts	AND rset = $rankset
			ORDER BY num DESC
			LIMIT 1
		", 0, 0, mysql::USE_CACHE);
	}

	$powerranks = array(
		-2 => 'Permabanned',
		-1 => 'Banned',
		//1  => '<b>Staff</b>',
		2  => '<b>Moderator</b>',
		3  => '<b>Administrator</b>'
	);

	// Separator
	if($rank && (in_array($powl, $powerranks) || $title)) $rank.='<br>';

	if($title)
		$rank .= $title;
	elseif (in_array($powl, $powerranks))
		$rank .= filter_string($powerranks[$powl]);
		
	// *LIVE* ban expiration date
	if ($bandate && $powl == -1) {
		$rank .= "<br>Banned until ".printdate($bandate)."<br>Expires in ".timeunits2($bandate-ctime());
	}

	return $rank;
}
/* there's no gunbound rank
function updategb() {
	global $sql;
	$hranks = $sql->query("SELECT posts FROM users WHERE posts>=1000 ORDER BY posts DESC");
	$c      = mysql_num_rows($hranks);

	for($i=1;($hrank=$sql->fetch($hranks)) && $i<=$c*0.7;$i++){
		$n=$hrank[posts];
		if($i==floor($c*0.001))    $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=3%'");
		elseif($i==floor($c*0.01)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=4%'");
		elseif($i==floor($c*0.03)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=5%'");
		elseif($i==floor($c*0.06)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=6%'");
		elseif($i==floor($c*0.10)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=7%'");
		elseif($i==floor($c*0.20)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=8%'");
		elseif($i==floor($c*0.30)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=9%'");
		elseif($i==floor($c*0.50)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=10%'");
		elseif($i==floor($c*0.70)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=11%'");
	}
}
*/

/*
	valid_user: return the ID of the user if it's valid; 0 otherwise
	$user - user id or name
*/
function valid_user($user) {
	global $sql;
	if (!$user) {
		return 0;
	} else if (is_numeric($user)) {
		return (int) $sql->resultq("SELECT id FROM users WHERE id = '{$user}'");
	} else {
		return (int) $sql->resultp("SELECT id FROM users WHERE name = ?", [$user]);
	}
}

function checkuser($name, $pass){
	global $hacks, $sql;

	if (!$name) return -1;
	//$sql->query("UPDATE users SET password = '".getpwhash($pass, 1)."' WHERE id = 1");
	$user = $sql->fetchp("SELECT id, password FROM users WHERE name = ?", [$name]);

	if (!$user) return -1;
	
	//if ($user['password'] !== getpwhash($pass, $user['id'])) {
	if (!password_verify(sha1($user['id']).$pass, $user['password'])) {
		// Also check for the old md5 hash, allow a login and update it if successful
		// This shouldn't impact security (in fact it should improve it)
		if (!$hacks['password_compatibility'])
			return -1;
		else {
			if ($user['password'] === md5($pass)) { // Uncomment the lines below to update password hashes
				$sql->query("UPDATE users SET `password` = '".getpwhash($pass, $user['id'])."' WHERE `id` = '$user[id]'");
				xk_ircsend("102|".xk(3)."Password hash for ".xk(9).$name.xk(3)." (uid ".xk(9).$user['id'].xk(3).") has been automatically updated.");
			}
			else return -1;
		}
	}
	
	return $user['id'];
}

function create_verification_hash($n,$pw) {
	$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
	$vstring = 'verification IP: ';

	$tvid = $n;
	while ($tvid--)
		$vstring .= array_shift($ipaddr) . "|";

	// don't base64 encode like I do on my fork, waste of time (honestly)
	return $n . hash('sha256', $pw . $vstring);
}

function generate_token($div = TOKEN_MAIN, $extra = "") {
	global $config, $loguser;
	return hash('sha256', $loguser['name'] . $_SERVER['REMOTE_ADDR'] . $config['salt-string'] . $div . $loguser['password']);
}

function check_token(&$var, $div = TOKEN_MAIN, $extra = "") {
	$res = (trim($var) == generate_token($div, $extra));
	if (!$res) errorpage("Invalid token.");
}

function auth_tag($div = TOKEN_MAIN) {
	return '<input type="hidden" name="auth" value="'.generate_token($div).'">';
}

function getpwhash($pass, $id) {
	return password_hash(sha1($id).$pass, PASSWORD_BCRYPT);
}
/*
function shenc($str){
	$l=strlen($str);
	for($i=0;$i<$l;$i++){
		$n=(308-ord($str[$i]))%256;
		$e[($i+5983)%$l]+=floor($n/16);
		$e[($i+5984)%$l]+=($n%16)*16;
	}
	for($i=0;$i<$l;$i++) $s.=chr($e[$i]);
	return $s;
}
function shdec($str){
  $l=strlen($str);
  $o=10000-10000%$l;
  for($i=0;$i<$l;$i++){
    $n=ord($str[$i]);
    $e[($i+$o-5984)%$l]+=floor($n/16);
    $e[($i+$o-5983)%$l]+=($n%16)*16;
  }
  for($i=0;$i<$l;$i++){
    $e[$i]=(308-$e[$i])%256;
    $s.=chr($e[$i]);
  }
  return $s;
}
function fadec($c1,$c2,$pct) {
  $pct2=1-$pct;
  $cx1[r]=hexdec(substr($c1,0,2));
  $cx1[g]=hexdec(substr($c1,2,2));
  $cx1[b]=hexdec(substr($c1,4,2));
  $cx2[r]=hexdec(substr($c2,0,2));
  $cx2[g]=hexdec(substr($c2,2,2));
  $cx2[b]=hexdec(substr($c2,4,2));
  $ret=floor($cx1[r]*$pct2+$cx2[r]*$pct)*65536+
	 floor($cx1[g]*$pct2+$cx2[g]*$pct)*256+
	 floor($cx1[b]*$pct2+$cx2[b]*$pct);
  $ret=dechex($ret);
  return $ret;
}
*/

function getuserlink($u = NULL, $id = 0, $urlclass = '', $useicon = false) {
	global $sql, $loguser, $userfields;
	
	if (!$u) {
		if ($id == $loguser['id']) {
			$u = $loguser;
		} else {
			$u = $sql->fetchq("SELECT $userfields FROM users u WHERE id = $id", PDO::FETCH_ASSOC, mysql::USE_CACHE);
		}
	}
	
	if ($id) {
		$u['id'] = $id;
	}
	// When the username is null it typically means the user has been deleted.
	// Print this so we don't just end up with a blank link.
	if ($u['name'] == NULL) {
		return "<span style='color: #FF0000'><b>[Deleted user]</b></span>";
	}
	
	$akafield		= htmlspecialchars($u['aka']);
	$alsoKnownAs	= ($u['aka'] && $u['aka'] != $u['name']) ? " title=\"Also known as: {$akafield}\"" : '';
	
	$u['name'] 		= htmlspecialchars($u['name'], ENT_QUOTES);
	
	if ($u['namecolor']) {
		if ($u['namecolor'] != 'rnbow' && is_birthday($u['birthday'])) { // Don't calculate birthday effect again
			$namecolor = 'rnbow';
		} else {
			$namecolor = $u['namecolor'];
		}
	} else {
		$namecolor = "";
	}
	
	$namecolor		= getnamecolor($u['sex'], $u['powerlevel'], $u['namecolor']);
	
	$minipic		= $useicon ? get_minipic($u['id'], filter_string($u['minipic'])) : "";
	
	return "{$minipic}<a style='color:#{$namecolor}' class='{$urlclass} nobr' href='profile.php?id={$u['id']}'{$alsoKnownAs}>{$u['name']}</a>";
}

function getnamecolor($sex, $powl, $namecolor = ''){
	global $nmcol, $x_hacks;

	// don't let powerlevels above admin have a blank color
	$powl = min(3, $powl);
	
	// Force rainbow effect on everybody
	if ($x_hacks['rainbownames']) $namecolor = 'rnbow';
	
	if ($powl < 0) // always dull drab banned gray.
		$output = $nmcol[0][$powl];
	else if ($namecolor) {
		switch ($namecolor) {
			case 'rnbow':
				// RAINBOW MULTIPLIER
				$stime = gettimeofday();
				// slowed down 5x
				$h = (($stime['usec']/25) % 600);
				if ($h<100) {
					$r=255;
					$g=155+$h;
					$b=155;
				} elseif($h<200) {
					$r=255-$h+100;
					$g=255;
					$b=155;
				} elseif($h<300) {
					$r=155;
					$g=255;
					$b=155+$h-200;
				} elseif($h<400) {
					$r=155;
					$g=255-$h+300;
					$b=255;
				} elseif($h<500) {
					$r=155+$h-400;
					$g=155;
					$b=255;
				} else {
					$r=255;
					$g=155;
					$b=255-$h+500;
				}
				$output = substr(dechex($r*65536+$g*256+$b),-6);
				break;
			case 'random':
				$nc 	= mt_rand(0,0xffffff);
				$output = str_pad(dechex($nc), 6, "0", STR_PAD_LEFT);
				break;
			case 'time':
				$z 	= max(0, 32400 - (mktime(22, 0, 0, 3, 7, 2008) - ctime()));
				$c 	= 127 + max(floor($z / 32400 * 127), 0);
				$cz	= str_pad(dechex(256 - $c), 2, "0", STR_PAD_LEFT);
				$output = str_pad(dechex($c), 2, "0", STR_PAD_LEFT) . $cz . $cz;
				break;
			default:
				$output = $namecolor;
				break;
		}
	}
	else $output = $nmcol[$sex][$powl];
	
	/* old sex-dependent name color 
	switch ($sex) {
		case 3:
			//$stime=gettimeofday();
			//$rndcolor=substr(dechex(1677722+$stime[usec]*15),-6);
			//$namecolor .= $rndcolor;
			$nc = mt_rand(0,0xffffff);
			$output = str_pad(dechex($nc), 6, "0", STR_PAD_LEFT);
			break;
			
		case 4:
			$namecolor .= "ffffff"; break;
			
		case 5:
			$z = max(0, 32400 - (mktime(22, 0, 0, 3, 7, 2008) - ctime()));
			$c = 127 + max(floor($z / 32400 * 127), 0);
			$cz	= str_pad(dechex(256 - $c), 2, "0", STR_PAD_LEFT);
			$output = str_pad(dechex($c), 2, "0", STR_PAD_LEFT) . $cz . $cz;
			break;
			
		case 6:
			$namecolor .= "60c000"; break;
		case 7:
			$namecolor .= "ff3333"; break;
		case 8:
			$namecolor .= "6688aa"; break;
		case 9:
			$namecolor .= "cc99ff"; break;
		case 10:
			$namecolor .= "ff0000"; break;
		case 11:
			$namecolor .= "6ddde7"; break;
		case 12:
			$namecolor .= "e2d315"; break;
		case 13:
			$namecolor .= "94132e"; break;
		case 14:
			$namecolor .= "ffffff"; break;
		case 21: // Sofi
			$namecolor .= "DC143C"; break;
		case 22: // Nicole
			$namecolor .= "FFB3F3"; break;
		case 23: // Rena
			$namecolor .= "77ECFF"; break;
		case 24: // Adelheid
			$namecolor .= "D2A6E1"; break;
		case 41:
			$namecolor .= "8a5231"; break;
		case 42:
			$namecolor .= "20c020"; break;
		case 99:
			$namecolor .= "EBA029"; break;
		case 98:
			$namecolor .= $nmcol[0][3]; break;
		case 97:
			$namecolor .= "6600DD"; break;
			
		default:
			$output = $nmcol[$sex][$powl];
			break;
	}*/

	return $output;
}

// Banner 0 = automatic ban
function ipban($ip, $reason, $ircreason = NULL, $destchannel = IRC_STAFF, $expire = 0, $banner = 0) {
	global $sql;
	if ($expire) {
		$expire = ctime() + 3600 * $expire;
	}
	$sql->queryp("
		INSERT INTO `ipbans` (`ip`,`reason`,`date`,`banner`,`expire`) 
		VALUES(?,?,?,?,?) ", [$ip, $reason, ctime(), $banner, $expire]);
	if ($ircreason !== NULL) {
		xk_ircsend("{$destchannel}|{$ircreason}");
	}
}

function userban($id, $reason = "", $ircreason = NULL, $expire = false, $permanent = false){
	global $sql;
	
	$new_powl		= $permanent ? -2 : -1;
	$expire         = $expire ? ctime() + 3600 * $expire : 0;
			
	$res = $sql->queryp("
		UPDATE users SET 
		    `powerlevel_prev` = `powerlevel`, 
		    `powerlevel`      = ?, 
		    `title`           = ?,
		    `ban_expire`      = ?,
		WHERE id = ?", [$new_powl, $reason, $expire, $id]);
		
	if ($ircreason !== NULL){
		xk_ircsend(IRC_STAFF."|{$ircreason}");
	}
}

function forumban($forum, $user, $reason = "", $ircreason = NULL, $destchannel = IRC_STAFF, $expire = 0, $banner = 0) {
	global $sql;
	
	if ($expire) {
		$expire = ctime() + 3600 * $expire;
	}
			
	$sql->queryp("
		INSERT INTO forumbans (user, forum, date, banner, expire, reason)
		VALUES(?,?,?,?,?,?)", [$user, $forum, ctime(), $banner, $expire, $reason]);
		
	if ($ircreason !== NULL){
		xk_ircsend("{$destchannel}|{$ircreason}");
	}
}

function check_forumban($forum, $user) {
	if ($wban = is_banned($forum, $user)) {
		$banner = ($wban['banner'] ? " by ".getuserlink($wban, $wban['uid']) : "");
		$reason = ($wban['reason'] ? $wban['reason'] : "<i>No reason given.</i>");
		errorpage("Sorry, but you have been banned{$banner} from posting in this forum.<br/>Reason: {$reason}");
	}
}

function is_banned($forum, $user) {
	global $sql, $userfields;
	return $sql->fetchq("
		SELECT f.id, f.banner, f.expire, f.reason, $userfields uid
		FROM forumbans f
		LEFT JOIN users u ON f.banner = u.id
		WHERE f.user = {$user} AND f.forum = {$forum}
	");
}

function onlineusers($forum = NULL, $thread = NULL){
	global $loguser, $config, $sql, $userfields, $isadmin, $ismod, $numon;

	// compat hax
	if ($config['onlineusers-on-thread']) {
		$l = "'<i>";
		$r = "</i>'";
	} else {
		$thread = NULL; // Force disable thread bar mode
		$l = $r = "";
	}
	
	if ($thread) {
		$check     = " AND lastthread = {$thread['id']}";
		$update    = "lastforum = {$forum['id']}, lastthread = {$thread['id']}"; // For online users update
		$location  = "reading {$l}" . htmlspecialchars($thread['title']) . $r; // "users currently in <thread>"
	} else if ($forum) {
		$check     = " AND lastforum = {$forum['id']}";
		$update    = "lastforum = {$forum['id']}, lastthread = 0";
		$location  = "in {$l}" . htmlspecialchars($forum['title']) . $r;  // "users currently in <forum>"
	} else {
		$check     = "";
		$update    = "lastforum = 0, lastthread = 0";
		$location  = "online"; // "users currently online"
	}
	
	
	if ($loguser['id']) {
		$sql->query("UPDATE users  SET {$update} WHERE id = {$loguser['id']}");
	} else {
		$sql->query("UPDATE guests SET {$update} WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");
	}
	
	$onlinetime		= ctime() - 300; // 5 minutes
	$onusers		= $sql->query("
		SELECT $userfields, hideactivity, (lastactivity <= $onlinetime) nologpost
		FROM users u
		WHERE (lastactivity > $onlinetime OR lastposttime > $onlinetime){$check} AND (".((int) $ismod)." OR !hideactivity)
		ORDER BY name
	");
	/*
		Online users
	*/	
	$onlineusers	= "";
	for ($numon = 0; $x = $sql->fetch($onusers); ++$numon) {
		
		if ($numon) $onlineusers .= ', ';

		/* if ((!is_null($hp_hacks['prefix'])) && ($hp_hacks['prefix_disable'] == false) && int($x['id']) == 5) {
			$x['name'] = pick_any($hp_hacks['prefix']) . " " . $x['name'];
		} */
		$minipic             = get_minipic($x['id'], $x['minipic']);
		$namelink            = getuserlink($x);
		//$onlineusers        .='<nobr>';
		
		if ($x['nologpost']) // Was the user posting without using cookies?
			$namelink="($namelink)";
			
		if ($x['hideactivity'])
			$namelink="[$namelink]";		
			
		if ($minipic)
			$namelink = "$minipic $namelink";
			
		$onlineusers .= "<span class='nobr'>{$namelink}</span>";
	}
	$p = ($numon ? ':' : '.');
	$s = ($numon != 1 ? 's' : '');
	
	/*
		Online guests
	*/
	$guests = $bpt_info = "";
	if (!$isadmin) {
		// Standard guest counter view
		$numguests = $sql->resultq("SELECT COUNT(*) FROM guests	WHERE date > {$onlinetime}{$check}");
	} else {
		// Detailed view of BPT (Bot/Proxy/Tor) flags
		$onguests = $sql->query("SELECT flags FROM guests WHERE date > {$onlinetime}{$check}");
		// Fill in the proper flag counters with the proper priority
		$pts = array_fill(0, 4, 0);
		for ($numguests = 0; $x = $sql->fetch($onguests); ++$numguests) {
			if      ($x['flags'] & BPT_TOR)         $pts[2]++;
			else if ($x['flags'] & BPT_IPBANNED)    $pts[0]++;
			else if ($x['flags'] & BPT_BOT)         $pts[3]++;
			//else if ($x['flags'] & BPT_PROXY)       $pts[1]++;
		}
		// Print out the flag info
		$specinfo = array(
			'IP banned', 
			'Prox'.($pts[1] == 1 ? 'ies' : 'y'), 
			'Tor banned', 
			'bot'.($pts[3] == 1 ? '' : 's')
		);
		//$guestcat = array();
		for ($i = 0; $i < 4; ++$i) {
			if ($pts[$i]) {
				$bpt_info .= ($bpt_info !== "" ? "" : ", ")."{$pts[$i]} {$specinfo[$i]}";
			}
		}
		if ($bpt_info !== "") {
			$bpt_info = "({$bpt_info})";
		}
		
		//$guests = $numguests ? " | <nobr>$numguests guest".($numguests>1?"s":"").($guestcat ? " (".implode(",", $guestcat).")" : "") : "";
	}
	
	if ($numguests) {
		$guests = "| $numguests guest" . ($numguests > 1 ? 's' : '') . $bpt_info;
	}
	
	return "$numon user$s currently $location$p $onlineusers $guests";
}

/* WIP
$jspcount = 0;
function jspageexpand($start, $end) {
	global $jspcount;

	if (!$jspcount) {
		echo '
			<script type="text/javascript">
				function pageexpand(uid,st,en)
				{
					var elem = document.getElementById(uid);
					var res = "";
				}
			</script>
		';
	}

	$entityid = "expand" . ++$jspcount;

	$js = "#todo";
	return $js;
}
*/

function redirect($url, $msg, $delay){
	global $config;
	if ($config['no-redirects']) {
		return "Click <a href=\"{$url}\">here</a> to be continue to {$msg}.";
	} else {
		if ($delay < 1) $delay = 1;
		return "You will now be redirected to <a href=$url>$msg</a>...<META HTTP-EQUIV=REFRESH CONTENT=$delay;URL=$url>";
	}
}

function postradar($userid){
	global $sql, $loguser, $userfields;
	if (!$userid) return "";
	
	$race = '';

	//$postradar = $sql->query("SELECT posts,id,name,aka,sex,powerlevel,birthday FROM users u RIGHT JOIN postradar p ON u.id=p.comp WHERE p.user={$userid} ORDER BY posts DESC", MYSQL_ASSOC);
	$postradar = $sql->query("
		SELECT u.posts, $userfields
		FROM postradar p
		INNER JOIN users u ON p.comp = u.id
		WHERE p.user = $userid
		ORDER BY posts DESC
	", PDO::FETCH_ASSOC);
	
	$rows = $sql->num_rows($postradar);
	
	if ($rows) {
		$race = 'You are ';

		function cu($a,$b) {
			global $hacks;

			$dif = $a-$b['posts'];
			if ($dif < 0)
				$t = (!$hacks['noposts'] ? -$dif : "") ." behind";
			else if ($dif > 0)
				$t = (!$hacks['noposts'] ?  $dif : "") ." ahead of";
			else
				$t = ' tied with';

			$namelink = getuserlink($b);
			$t .= " {$namelink}" . (!$hacks['noposts'] ? " ({$b['posts']})" : "");
			return "<nobr>{$t}</nobr>";
		}

		// Save ourselves a query if we're viewing our own post radar
		// since we already fetch all user fields for $loguser
		if ($userid == $loguser['id'])
			$myposts = $loguser['posts'];
		else
			$myposts = $sql->resultq("SELECT posts FROM users WHERE id = $userid");

		for($i = 0; $user2 = $sql->fetch($postradar); ++$i) {
			if ($i) 					$race .= ', ';
			if ($i && $i == $rows - 1) 	$race .= 'and ';
			$race .= cu($myposts, $user2);
		}
	}
	return $race;
}

/*
	load_user: load the userdata for a specified user
	$user - user id
	$all  - loads all the data; by default it's fetched only what's necessary to create an userlink
*/
function load_user($user, $all = false) {
	global $sql, $userfields;
	if (!$user) {
		return NULL;
	} else {
		return $sql->fetchq("SELECT ".($all ? "*" : $userfields)." FROM users u WHERE u.id = '{$user}'");
	}
}

function get_ppp($low = 1, $high = 500) {
	global $loguser, $config;
	$ppp = (isset($_GET['ppp']) ? ((int) $_GET['ppp']) : (($loguser['id']) ? $loguser['postsperpage'] : $config['default-ppp']));
	return max(min($ppp, $high), $low);
}

function get_tpp($low = 1, $high = 500) {
	global $loguser, $config;
	$tpp = (isset($_GET['tpp']) ? ((int) $_GET['tpp']) : (($loguser['id']) ? $loguser['threadsperpage'] : $config['default-tpp']));
	return max(min($tpp, $high), $low);
}

function squot($t, &$src){
	switch($t){
		case 0: $src=htmlspecialchars($src); break;
		case 1: $src=urlencode($src); break;
		case 2: $src=str_replace('&quot;','"',$src); break;
		case 3: $src=urldecode('%22','"',$src); break;
	}
/*  switch($t){
    case 0: $src=str_replace('"','&#34;',$src); break;
    case 1: $src=str_replace('"','%22',$src); break;
    case 2: $src=str_replace('&#34;','"',$src); break;
    case 3: $src=str_replace('%22','"',$src); break;
  }*/
}
function sbr($t, &$src){
	switch($t) {
		case 0: $src=str_replace("\n",'<br>',$src); break;
		case 1: $src=str_replace('<br>',"\n",$src); break;
	}
}
/*
function mysql_get($query){
  global $sql;
  return $sql->fetchq($query);
}
*/
/*
function sizelimitjs(){
	// where the fuck is this used?!
	return "";
  return '
	<script>
	  function sizelimit(n,x,y){
		rx=n.width/x;
		ry=n.height/y;
		if(rx>1 && ry>1){
		if(rx>=ry) n.width=x;
		else n.height=y;
		}else if(rx>1) n.width=x;
		else if(ry>1) n.height=y;
	  }
	</script>
  '; 
}*/

function loadtlayout(){
	global $loguser, $tlayout, $sql;
	$tlayout    = $loguser['layout'] ? $loguser['layout'] : 1;
	$layoutfile = $sql->resultq("SELECT file FROM tlayouts WHERE id = $tlayout");
	if (!$layoutfile) {
		errorpage("The thread layout you've been using has been removed by the administration.<br/>You need to <a href='editprofile.php'>choose a new one</a> before you'll be able to view threads.");
	}
	require "tlayouts/$layoutfile.php";
}

function errorpage($text, $redirurl = '', $redir = '', $redirtimer = 4) {
	if (!defined('HEADER_PRINTED')) pageheader();

	print "<table class='table'><tr><td class='tdbg1 center'>$text";
	if ($redir)
		print '<br>'.redirect($redirurl, $redir, $redirtimer);
	print "</table>";

	pagefooter();
}

function confirmpage($message, $form_link, $buttons = NULL, $token = TOKEN_MAIN) {
	
	// Get the indicator
	static $i = 0; // just in case
	$key = "nk_page_confirm".($i++);
	$status = filter_bool($_POST[$key]);
	
	if ($status) { // All ok; do not print anything (unless the token is bad, that is)
		check_token($_POST['auth'], $token);
		return "<input type='hidden' name='{$key}' value='1'>";
	}
	
	if ($buttons !== NULL) {
		$commands = "";
		for ($i = 0, $cnt = count($buttons); $i < $cnt; ++$i) {
			if ($i) {
				$commands .= " - ";
			}
			// Support both links and buttons
			if (!isset($buttons[$i][1])) {
				$commands .= "<input type='submit' class='submit' name='{$key}' value=\"{$buttons[$i][0]}\">";
			} else {
				$commands .= "<a href=\"{$buttons[$i][1]}\">{$buttons[$i][0]}</a>";
			}
		}
	} else {
		$commands = "<input type='submit' class='submit' name='{$key}' value='Yes'> - <a href='#' onclick='window.history.go(-1); return false;'>No</a>";
	}
	
	if (!defined('HEADER_PRINTED')) {
		pageheader();
	}	
?>
	<form method="POST" action="<?= $form_link ?>">
	<center>
	<div class="table center tdbg1" style="max-width: 600px">
		<?= $message ?><br/>
		<br/>
		<?= $commands ?><br/>
		<?= auth_tag($token) ?>
	</div>
	</center>
	</form>
<?php

	pagefooter();
	return false;
}

function admincheck() {
	global $isadmin;
	if (!$isadmin) {
		if (!defined('HEADER_PRINTED')) pageheader();
		
		?><table class='table'>
			<tr>
				<td class='tdbg1 center'>
					This feature is restricted to administrators.<br>
					You aren't one, so go away.<br>
					<?=redirect('index.php','return to the board',0)?>
				</td>
			</tr>
		</table><?php
		
		pagefooter();
	}
}

function adminlinkbar($sel = NULL) {
	global $isadmin;

	if (!$isadmin) return;

	if (!$sel) {
		// If no selection is passed, default to the current script
		global $scriptname;
		$sel = $scriptname;
	}

	$links	= array(
		array(
			'admin.php'	=> "Admin Control Panel",
		),
		array(
//			'admin-todo.php'        => "To-do list",
			'announcement.php'      => "Go to Announcements",
			'admin-editfilters.php' => "Edit Filters",
			'admin-editforums.php'  => "Edit Forum List",
			'admin-editmods.php'    => "Edit Forum Moderators",
			'admin-forumbans.php'    => "Edit Forum Bans",
		),
		array(
			'admin-threads.php'     => "ThreadFix",
			'admin-threads2.php'    => "ThreadFix 2",
			'admin-backup.php'      => "Board Backups",		
		),
		array(
			'admin-ipsearch.php'    => "IP Search",
			'admin-ipbans.php'      => "IP Bans",
			'admin-pendingusers.php'=> "Pending Users",
//			'admin-slammer.php'     => "EZ Ban Button",
			'admin-deluser.php'     => "Delete User",
		)
	);

	$r = "<div style='padding:0px;margins:0px;'>
			<table class='table'>
				<tr>
					<td class='tdbgh center b' style='border-bottom: 0'>
						Admin Functions
					</td>
				</tr>
			</table>";

	$total = count($links) - 1;
    foreach ($links as $rownum => $linkrow) {
		$c	= count($linkrow);
		$w	= floor(1 / $c * 100);

		$r .= "<table class='table'><tr>";
		$nb = ($rownum != $total) ? ";border-bottom: 0" : "";

		foreach($linkrow as $link => $name) {
			$cell = '1';
			if ($link == $sel) $cell = 'c';
			$r .= "<td class='tdbg{$cell} center nobr' style='padding: 1px 10px{$nb}' width=\"{$w}%\"><a href=\"{$link}\">{$name}</a></td>";
		}

		$r .= "</tr></table>";
	}
	$r .= "</div><br>";

	return $r;
}

function nuke_js($before, $after) {

	global $sql, $loguser;
	$sql->queryp("
		INSERT INTO `jstrap` SET
			`loguser`  =  {$loguser['id']},
			`ip`       = :ipaddr,
			`text`     = :source,
			`url`      = :url,
			`time`     = ".ctime().",
			`filtered` = :filtered",
		[
		 ':ipaddr'   => $_SERVER['REMOTE_ADDR'], 
		 ':url'      => $_SERVER['REQUEST_URI'],
		 ':source'   => $before,
		 ':filtered' => $after
		]
	);

}
function include_js($fn, $as_tag = false) {
	// HANDY JAVASCRIPT INCLUSION FUNCTION
	if ($as_tag) {
		// include as a <script src="..."></script> tag
		return "<script src='$fn' type='text/javascript'></script>";
	} else {
		return '<script type="text/javascript">'.file_get_contents("js/{$fn}").'</script>';
	}
}


function xssfilters($data, $validate = false){
	
	$diff = false;
	$orig = $data;
	
	// https://stackoverflow.com/questions/1336776/xss-filtering-function-in-php
	// Fix &entity\n;
	$data = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $data);
	$data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
	$data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
	$data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

	$temp = $data;
	// Remove any attribute starting with "on" or xmlns
	#$data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);
	do {
		$old_data	= $data;
		$data		= preg_replace('#(<[^>]+?[\x00-\x20"\'])(on|xmlns)([^>]*+)>#iu', '$1DISABLED_$2$3>', $data);
	} while ($old_data !== $data);
	
	// Remove javascript: and vbscript: protocols
	$data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
	$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
	$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);
	if ($data !== $temp) $diff = true;

	// Remove namespaced elements (we do not need them)
	$data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);
	
	$temp = $data;
	do {
	    // Remove really unwanted tags
	    $old_data = $data;
	    $data = preg_replace('#<(/*(?:applet|b(?:ase|gsound)|embed|frame(?:set)?|i(?:frame|layer)|layer|meta|object|script|title|xml)[^>]*+)>#i', '&lt;$1&gt;', $data);
	} while ($old_data !== $data);
	if ($data !== $temp) $diff = true;
	
	if ($diff) {
		nuke_js($orig, $data);
		if ($validate) return NULL;
	}
	
	return $data;
	
}
function dofilters($p, $f = 0){
	global $sql, $hacks;
	static $filters;
	
	// Note: these queries are not merged due to showposts (by user) functionality
	$filters = $sql->fetchq("
		SELECT method, source, replacement
		FROM filters
		WHERE enabled = 1 AND forum = 0
		ORDER BY id ASC
	", PDO::FETCH_ASSOC, mysql::FETCH_ALL | mysql::USE_CACHE);
	
	if ($f) {
		$filters = array_merge($filters, $sql->fetchq("
			SELECT method, source, replacement 
			FROM filters
			WHERE enabled = 1 AND forum = {$f}
		", PDO::FETCH_ASSOC, mysql::FETCH_ALL | mysql::USE_CACHE));
	}
	
	foreach($filters as $x) {
		switch ($x['method']) {
			case 0:
				$p = str_replace($x['source'], $x['replacement'], $p);
				break;
			case 1:
				$p = str_ireplace($x['source'], $x['replacement'], $p);
				break;
			case 2:
				$p = preg_replace("'{$x['source']}'si", $x['replacement'], $p); // Force 'si modifiers to prevent the 'e modifier from being used
				break;
		}
	}
	
	$p = xssfilters($p);

	$p = preg_replace("'\[youtube\]([a-zA-Z0-9_-]{11})\[/youtube\]'si", '<iframe src="https://www.youtube.com/embed/\1" width="560" height="315" frameborder="0" allowfullscreen="allowfullscreen"></iframe>', $p);

	
	return $p;
}

// Additional includes
require 'lib/avatars.php';
require 'lib/attachments.php';
require 'lib/threadpost.php';
require 'lib/thread.php';
require 'lib/pm.php';

// require 'lib/replytoolbar.php';

function replytoolbar() { return; }

function addslashes_array($data) {
	if (is_array($data)){
		foreach ($data as $key => $value){
			$data[$key] = addslashes_array($value);
		}
		return $data;
	} else {
		return addslashes($data);
	}
}


function xk_ircout($type, $user, $in) {
	global $config;
	
	// gone
	// return;
	# and back

	$indef = array(
		'pow'		=> 1,
		'fid'		=> 0,
		'id'		=> 0,
		//'pmatch'	=> 0,
		'ip'		=> 0,
		'forum'		=> 0,
		'thread'	=> 0,
		'pid'		=> 0,
	);
	
	$in = array_merge($indef, $in);
	
	// Public forums have dest 0, everything else 1
	$dest	= min(1, max(0, $in['pow']));
	
	// Posts in certain forums are reported elsewhere
	if ($in['fid'] == 99) {
		$dest	= 6;
	} elseif ($in['fid'] == 98) {
		$dest	= 7;
	}

	global $x_hacks;
	if ($x_hacks['host'] || !$config['irc-reporting']) return;

	
	
	if ($type == "user") {
		/* not usable
		if ($in['pmatch']) {
			$color	= array(8, 7);
			if		($in['pmatch'] >= 3) $color	= array(7, 4);
			elseif	($in['pmatch'] >= 5) $color	= array(4, 5);
			$extra	= " (". xk($color[1]) ."Password matches: ". xk($color[0]) . $in['pmatch'] . xk() .")";
		}
		*/
		$extra = "";
		xk_ircsend("1|New user: #". xk(12) . $in['id'] . xk(11) ." $user ". xk() ."(IP: ". xk(12) . $in['ip'] . xk() .")$extra: {$config['board-url']}/?u=". $in['id']);
		// Also show to public channel, but without the admin-only fluff
		xk_ircsend("0|New user: #". xk(12) . $in['id'] . xk(11) ." $user ". xk() .")$extra: {$config['board-url']}/?u=". $in['id']);
		
		
	} else {
//			global $sql;
//			$res	= $sql -> resultq("SELECT COUNT(`id`) FROM `posts`");
		xk_ircsend("$dest|New $type by ". xk(11) . $user . xk() ." (". xk(12) . $in['forum'] .": ". xk(11) . $in['thread'] . xk() ."): {$config['board-url']}/?p=". $in['pid']);

	}

}

function xk_ircsend($str) {
	global $config;
	// $str = <chan id>|<message>
	if (!$config['no-curl']) {
/*	
	$str = str_replace(array("%10", "%13"), array("", ""), rawurlencode($str));

	$str = html_entity_decode($str);
	

	$ch = curl_init();
	//curl_setopt($ch, CURLOPT_URL, "http://treeki.rustedlogic.net:5000/reporting.php?t=$str");
	curl_setopt($ch, CURLOPT_URL, "ext/reporting.php?t=$str");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // <---- HERE
	curl_setopt($ch, CURLOPT_TIMEOUT, 5); // <---- HERE
	$file_contents = curl_exec($ch);
	curl_close($ch);
*/
	}
	return true;
}

// IRC Color code setup
function xk($n = -1) {
	if ($n == -1) $k = "";
		else $k = str_pad($n, 2, 0, STR_PAD_LEFT);
	return "\x03". $k;
}

function formatting_trope($input) {
	$in		= "/[A-Z][^A-Z]/";
	$out	= " \\0";
	$output	= preg_replace($in, $out, $input);

	return trim($output);
}



function cleanurl($url) {
	$pos1 = $pos = strrpos($url, '/');
	$pos2 = $pos = strrpos($url, '\\');
	if ($pos1 === FALSE && $pos2 === FALSE)
		return $url;

	$spos = max($pos1, $pos2);
	return substr($url, $spos+1);
}

/* extra fun functions! */
function pick_any($array) {
	if (is_array($array)) {
		return $array[array_rand($array)];
	} elseif (is_string($array)) {
		return $array;
	}
}

function numrange($n, $lo, $hi) {
	return max(min($hi, $n), $lo);
}

function marqueeshit($str) {
	return "<marquee scrollamount='". mt_rand(1, 50) ."' scrolldelay='". mt_rand(1, 50) ."' direction='". pick_any(array("left", "right")) ."'>$str</marquee>";
}

// For some dumb reason a simple str_replace isn't enough under Windows
function strip_doc_root($file) {
	$root_path = $_SERVER['DOCUMENT_ROOT'];
	if (PHP_OS == 'WINNT') {
		$root_path = str_replace("/", "\\", $root_path);
	}
	return str_replace($root_path, "", $file);
}

function unescape($in) {

	$out	= urldecode($in);
	while ($out != $in) {
		$in		= $out;
		$out	= urldecode($in);
	}
	return $out;

}

// get the query string from optional parameters, if set
function opt_param($list) {
	$idparam = "";
	foreach ($list as $x) {
		if (isset($_GET[$x]) && $_GET[$x]) {
			$idparam .= (isset($one) ? "&" : "")."{$x}={$_GET[$x]}";
			$one      = true;
		}
	}
	return $idparam;
}

// extract values from queries using PDO::FETCH_NAMED
function array_column_by_key($array, $index){
	if (is_array($array)) {
		$output = array();
		foreach ($array as $key => $val) {
			if (is_array($array[$key])) {
				$output[$key] = filter_string($array[$key][$index]);
			}
		}
		return $output;
	} else {
		return NULL;
	}
}
// Modify userfields for PDO::FETCH_NAMED queries
function set_userfields($alias, $prealias = NULL) {
	global $userfields;
	if ($prealias) {
		// Get an array of fields
		$txt = str_replace("u.", "", $userfields);
		$set = array_map('trim', explode(',', $txt));
		$max = count($set);
		
		
		// Only insert placeholder'd elements
		$txt = "";
		for ($i = $j = 0; $i < $max; ++$i) {
			$tag = "{$alias}{$set[$i]}";
			if (isset($prealias[$tag])) {
				$txt .= ($j ? ", " : "") . ":{$tag} {$set[$i]}";
				++$j;
			}
		}
	} else {
		$txt = str_replace("u.", "{$alias}.", $userfields);
	}
	return $txt;
}

function preg_loop($before, $regex){
	$after = preg_replace("'{$regex}'", "", $before);
	while ($before != $after){
		$before = $after;
		$after = preg_replace("'{$regex}'", "", $before);
	}
	return $after;
}

function deletefolder($directory) {
	if (file_exists($directory)) {
		foreach(glob("{$directory}/*") as $f) unlink("$f");
		rmdir($directory);
	}
}

function escape_attribute($attr) {
	return str_replace(array('\'', '<', '>', '"'), array('%27', '%3C', '%3E', '%22'), $attr);
}

// $startrange === true -> print all pages
function pagelist($url, $elements, $ppp, $startrange = 9, $endrange = 9, $midrange = 4){
	$page    = filter_int($_GET['page']);
	$pages   = ceil($elements / $ppp);
	$pagelinks = "";
	if ($pages > 1) {
		$pagelinks = "Pages: ";
		for ($i = 0; $i < $pages; ++$i) {
			// restrict page range to sane values
			if ($startrange !== true && $i > $startrange && $i < $pages - $endrange) {
				// around the current page
				if ($i < $page - $midrange) {
					$i = min($page-$midrange, $pages-$endrange);
					$pagelinks .= " ...";
				}
				else if ($i > $page + $midrange) {
					$i = $pages-$endrange;
					$pagelinks .= " ...";
				}
			}
			
			$w = ($i == $page) ? "x" : "a";
			$pagelinks .= "<{$w} href=\"{$url}&page={$i}\">".($i + 1)."</{$w}> ";
		}
	}
	
	return $pagelinks;
}

function page_select($total, $ppp) {
	$page     = filter_int($_POST['page']);
	$pages    = ceil($total / $ppp);
	$pagectrl = "";
	for ($i = 0; $i < $pages;) {
		$selected = ($page == $i) ? " selected" : "";
		$pagectrl .= "<option value='{$i}'{$selected}>".(++$i)."</option>\r\n";
	}
	return "<select name='page'>{$pagectrl}</select>";
}

function ban_hours($name, $time = 0, $condition = true) {
		$val = ($condition && $time) ? ceil(($time - ctime()) / 3600) : 0;
		
		$selector = array(
			$val     => timeunits2($val*3600),
			0        => "*** Permanent ***",
			1        => "1 hour",
			3        => "3 hours",
			6        => "6 hours",
			24       => "1 day",
			72       => "3 days",
			168      => "1 week",
			336      => "2 weeks",
			774      => "1 month",
			1488     => "2 months",
			4464     => "6 months",
			89280    => "SA Ban",
		);
		ksort($selector); // Place the $val entry in the correct position
		
		$sel[$val] = " selected";
		
		// Fill out the select box
		$out = "";
		foreach($selector as $i => $x){
			$out .= "<option value=$i".filter_string($sel[$i]).">$x</option>";
		}
		return "<select name='{$name}'>{$out}</select>";
}

function user_select($name, $sel = 0, $condition = '1') {
	global $sql;
	$userlist = "";
	$users = $sql->query("SELECT `id`, `name`, `powerlevel` FROM `users` WHERE {$condition} ORDER BY `name`");
	while($x = $sql->fetch($users)) {
		$selected = ($x['id'] == $sel) ? " selected" : "";
		$userlist .= "<option value='{$x['id']}'{$selected}>{$x['name']} -- [{$x['powerlevel']}]</option>\r\n";
	}
	return "
	<select name='{$name}' size='1'>
		<option value='0'>Select a user...</option>
		{$userlist}
	</select>";
}

function generatenumbergfx($num, $minlen = 0, $size = 1) {
	global $numdir;

	$nw			= 8 * $size; //($double ? 2 : 1);
	$num		= (string) $num; // strval
	$len		= strlen($num);
	$gfxcode	= "";

	// Left-Padding
	if($minlen > 1 && $len < $minlen) {
		$gfxcode = "<img src='images/_.gif' style='width:". ($nw * ($minlen - $len)) ."px;height:{$nw}px'>";
	}

	for($i = 0; $i < $len; ++$i) {
		$code	= $num[$i];
		switch ($code) {
			case "/":
				$code	= "slash";
				break;
		}
		if ($code == " ") {
			$gfxcode .= "<img src='images/_.gif' style='width:{$nw}px;height:{$nw}px'>";
		} else if ($code == "i") { // the infinity symbol is just a rotated 8, right...?
			$gfxcode .= "<img src='numgfx/{$numdir}8.png' style='width:{$nw}px;height:{$nw}px;transform:rotate(90deg)'>";			
		} else {
			$gfxcode .= "<img src='numgfx/{$numdir}{$code}.png' style='width:{$nw}px;height:{$nw}px'>";
		}
	}
	return $gfxcode;
}

// Progress bar (for RPG levels, syndromes)
function drawprogressbar($width, $height, $done, $total, $images) {
	$on  = min(round($done / $total * $width), $width);
	$off = $width - $on;
	return  "<img src='{$images[0]}' style='height:{$height}px'>".
			"<img src='{$images[1]}' style='height:{$height}px;width:{$on}px'>".
			"<img src='{$images[2]}' style='height:{$height}px;width:{$off}px'>".
			"<img src='{$images[3]}' style='height:{$height}px'>";
}

// Single image progress bar (for comparisions like in activeusers.php)
function drawminibar($width, $height, $progress, $image = 'images/minibar.png') {
	$on = round($progress * 100 / $width);
	return "<img src='{$image}' style='float: left; width: {$on}%; height: {$height}px'>";
}


function adbox() {

	// no longer needed. RIP
	return "";

	global $loguser, $bgcolor, $linkcolor;

/*
	$tagline	= array();
	$tagline[]	= "Viewing this ad requires<br>ZSNES 1.42 or older!";
	$tagline[]	= "Celebrating 5 years of<br>ripping off SMAS!";
	$tagline[]	= "Now with 100% more<br>buggy custom sprites!";
	$tagline[]	= "Try using AddMusic to give your hack<br>that 1999 homepage feel!";
	$tagline[]	= "Pipe cutoff? In my SMW hack?<br>It's more likely than you think!";
	$tagline[]	= "Just keep giving us your money!";
	$tagline[]	= "Now with 97% more floating munchers!";
	$tagline[]	= "Tip: If you can beat your level without<br>savestates, it's too easy!";
	$tagline[]	= "Tip: Leave exits to level 0 for<br>easy access to that fun bonus game!";
	$tagline[]	= "Now with 100% more Touhou fads!<br>It's like Jul, but three years behind!";
	$tagline[]	= "Isn't as cool as this<br>witty subtitle!";
	$tagline[]	= "Finally beta!";
	$tagline[]	= "If this is blocking other text<br>try disabling AdBlock next time!";
	$tagline[]	= "bsnes sucks!";
	$tagline[]	= "Now in raspberry, papaya,<br>and roast beef flavors!";
	$tagline[]	= "We &lt;3 terrible Japanese hacks!";
	$tagline[]	= "573 crappy joke hacks and counting!";
	$tagline[]	= "Don't forget your RATS tag!";
	$tagline[]	= "Now with exclusive support for<br>127&frac12;Mbit SuperUltraFastHiDereROM!";
	$tagline[]	= "More SMW sequels than you can<br>shake a dead horse at!";
	$tagline[]	= "xkas v0.06 or bust!";
	$tagline[]	= "SMWC is calling for your blood!";
	$tagline[]	= "You can run,<br>but you can't hide!";
	$tagline[]	= "Now with 157% more CSS3!";
	$tagline[]	= "Stickers and cake don't mix!";
	$tagline[]	= "Better than a 4-star crap cake<br>with garlic topping!";
	$tagline[]	= "We need some IRC COPS!";

	if (isset($_GET['lolol'])) {
		$taglinec	= $_GET['lolol'] % count($tagline);
		$taglinec	= $tagline[$taglinec];
	}
	else
		$taglinec	= pick_any($tagline);
*/

	return "
<center>
<!-- Beginning of Project Wonderful ad code: -->
<!-- Ad box ID: 48901 -->
<script type=\"text/javascript\">
<!--
var pw_d=document;
pw_d.projectwonderful_adbox_id = \"48901\";
pw_d.projectwonderful_adbox_type = \"5\";
pw_d.projectwonderful_foreground_color = \"#$linkcolor\";
pw_d.projectwonderful_background_color = \"#$bgcolor\";
//-->
</script>
<script type=\"text/javascript\" src=\"http://www.projectwonderful.com/ad_display.js\"></script>
<noscript><map name=\"admap48901\" id=\"admap48901\"><area href=\"http://www.projectwonderful.com/out_nojs.php?r=0&amp;c=0&amp;id=48901&amp;type=5\" shape=\"rect\" coords=\"0,0,728,90\" title=\"\" alt=\"\" target=\"_blank\" /></map>
<table cellpadding=\"0\" border=\"0\" cellspacing=\"0\" width=\"728\" bgcolor=\"#$bgcolor\"><tr><td><img src=\"http://www.projectwonderful.com/nojs.php?id=48901&amp;type=5\" width=\"728\" height=\"90\" usemap=\"#admap48901\" border=\"0\" alt=\"\" /></td></tr><tr><td bgcolor=\"\" colspan=\"1\"><center><a style=\"font-size:10px;color:#$linkcolor;text-decoration:none;line-height:1.2;font-weight:bold;font-family:Tahoma, verdana,arial,helvetica,sans-serif;text-transform: none;letter-spacing:normal;text-shadow:none;white-space:normal;word-spacing:normal;\" href=\"http://www.projectwonderful.com/advertisehere.php?id=48901&amp;type=5\" target=\"_blank\">Ads by Project Wonderful! Your ad could be right here, right now.</a></center></td></tr></table>
</noscript>
<!-- End of Project Wonderful ad code. -->
</center>";
}

// for you-know-who's bullshit
function gethttpheaders() {
	$ret = '';
	foreach ($_SERVER as $name => $value) {
		if (substr($name, 0, 5) == 'HTTP_') {
			$name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
			if ($name == "User-Agent" || $name == "Cookie" || $name == "Referer" || $name == "Connection")
				continue; // we track the first three already, the last will always be "close"

			$ret .= "$name: $value\r\n";
		}
	}

	return $ret;
}

function error_reporter($type, $msg, $file, $line, $context) {
	
 	global $loguser, $errors;

	// They want us to shut up? (@ error control operator) Shut the fuck up then!
	if (!error_reporting())
		return true;
	

	switch($type) {
		case E_USER_ERROR:			$typetext = "User Error";   $irctypetext = xk(4) . "- Error";   break;
		case E_USER_WARNING:		$typetext = "User Warning"; $irctypetext = xk(7) . "- Warning"; break;
		case E_USER_NOTICE:			$typetext = "User Notice";  $irctypetext = xk(8) . "- Notice";  break;
		case E_ERROR:			 	$typetext = "Error"; 				break;
		case E_WARNING: 			$typetext = "Warning"; 				break;
		case E_NOTICE:				$typetext = "Notice"; 				break;
		case E_STRICT: 				$typetext = "Strict Notice";	 	break;
		case E_RECOVERABLE_ERROR:	$typetext = "Recoverable Error"; 	break;
		case E_DEPRECATED: 			$typetext = "Deprecated"; 			break;
		case E_USER_DEPRECATED: 	$typetext = "User Deprecated"; 		break;		
		default: $typetext = "Unknown type";
	}

	// Get the ACTUAL location of error for mysql queries
	if ($type == E_USER_NOTICE && substr($file, -9) === "mysql.php"){
		$backtrace = debug_backtrace();
		for ($i = 1; substr($backtrace[$i]['file'], -9) === "mysql.php"; ++$i);
		$file = "[Parent] ".$backtrace[$i]['file'];
		$line = $backtrace[$i]['line'];
		$func = get_class($backtrace[$i]['object']).' # '.$backtrace[$i]['function'];
		$args = $backtrace[$i]['args'];
	} else if (in_array($type, [E_USER_NOTICE,E_USER_WARNING,E_USER_ERROR,E_USER_DEPRECATED], true)) {
		// And do the same for custom thrown errors
		$backtrace = debug_backtrace();
		$file = "[Parent] ".filter_string($backtrace[2]['file']);
		$line = filter_int($backtrace[2]['line']);
		$func = filter_string($backtrace[2]['function']);
		$args = filter_string($backtrace[2]['args']);
	} else {
		$backtrace = debug_backtrace();
		$func = filter_string($backtrace[1]['function']);
		$args = filter_array($backtrace[1]['args']);
	}
	
	
	$file = strip_doc_root($file);
	
	// Without $irctypetext the error is marked as "local reporting only"
	if (isset($irctypetext)) {
		xk_ircsend("102|".($loguser['id'] ? xk(11) . $loguser['name'] .' ('. xk(10) . $_SERVER['REMOTE_ADDR'] . xk(11) . ')' : xk(10) . $_SERVER['REMOTE_ADDR']) .
				   " {$irctypetext}: ".xk()."({$file} #{$line}) {$msg}");
	}

	// Local reporting
	$errors[] = array($typetext, $msg, $func, $args, $file, $line);
	
	return true;
}

// Chooses what to do with unhandled exceptions
function exception_reporter($err) {
	global $config, $sysadmin;
	
	// Convert the exception to an error so the reporter can digest it
	$type = E_ERROR;
	$msg  = $err->getMessage() . "\n\n<span style='color: #FFF'>Stack trace:</span>\n\n". highlight_trace($err->getTrace());
	$file = $err->getFile();
	$line = $err->getLine();
	unset($err);
	error_reporter($type, $msg, $file, $line, NULL);
	
	// Should we display the debugging screen?
	if (!$sysadmin && !$config['always-show-debug']) {
		dialog(
			"Something exploded in the codebase <i>again</i>.<br>".
			"Sorry for the inconvenience<br><br>".
			"Click <a href='?".urlencode(filter_string($_SERVER['QUERY_STRING']))."'>here</a> to try again.",
			"Technical difficulties II", 
			"{$config['board-name']} -- Technical difficulties");
	} else {
		fatal_error("Exception", $msg, $file, $line);
	}
}

function highlight_trace($arr) {
	$out = "";
	foreach ($arr as $k => $v) {
		$out .= "<span style='color: #FFF'>{$k}</span><span style='color: #F44'>#</span> ".
		        "<span style='color: #0f0'>{$v['file']}</span>#<span style='color: #6cf'>{$v['line']}</span> ".
		        "<span style='color: #F44'>{$v['function']}<span style='color:#FFF'>(\n".print_r($v['args'], true)."\n)</span></span>\n";
	}
	//implode("<span style='color: #0F0'>,</span>", $v['args'])
	return $out;
}

function error_printer($trigger, $report, $errors){
	static $called = false; // The error reporter only needs to be called once
	
	if (!$called){
		$called = true;
		
		// Exit if we don't have permission to view the errors or there are none
		if (!$report || empty($errors)){
			return $trigger ? "" : true;
		}
		
		if ($trigger != false) { // called by printtimedif()
			//array($typetext, $msg, $func, $args, $file, $line);
			$cnt = count($errors);	
			$list = "<br>
			<table class='table'>
				<tr>
					<td class='tdbgh center b' colspan=4>
						Error list (Total: {$cnt})
					</td>
				</tr>
				<tr>
					<td class='tdbgh center' style='width: 20px'>&nbsp;</td>
					<td class='tdbgh center' style='width: 150px'>Error type</td>
					<td class='tdbgh center'>Function</td>
					<td class='tdbgh center'>Message</td>
				</tr>";
			
			for ($i = 0; $i < $cnt; ++$i) {
				$cell = ($i%2)+1;
				
				if ($errors[$i][2]) {
					$func = $errors[$i][2]."(".print_args($errors[$i][3]).")";
				} else {
					$func = "<i>(main)</i>";
				}
				
				$list .= "
					<tr>
						<td class='tdbg{$cell} center'>".($i+1)."</td>
						<td class='tdbg{$cell} center'>{$errors[$i][0]}</td>
						<td class='tdbg{$cell} center'>
							{$func}
							<div class='fonts'>{$errors[$i][4]}:{$errors[$i][5]}</div>
						</td>
						<td class='tdbg{$cell}'>{$errors[$i][1]}</td>						
					</tr>";
			}
				
			return $list."</table>";
			
		}
		else{
				extract(error_get_last());
				$ok = error_reporter($type, $message, $file, $line)[0];
				fatal_error($type, $message, $file, $line);				
		}
	}
	
	return true;
}

function print_args($args) {
	$res = "";
	foreach ($args as $val) {
		if (is_array($val)) {
			//$tmp = print_args($val);
			//$res .= ($res !== "" ? "," : "")."<span class='fonts'>[{$tmp}]</span>";
			$res .= ($res !== "" ? "," : "")."<span class='fonts'>[Array]</span>";
		} else {
			$res .= ($res !== "" ? "," : "")."<span class='fonts'>'{$val}'</span>";
		}
	}
	return $res;
}

