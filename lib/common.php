<?php
	$startingtime = microtime(true);

	// button page handler
	if (isset($_POST['pageb'])) {
		return header("Location: {$_SERVER['REQUEST_URI']}&page=".($_POST['pageb']-1));
	}

	// Set this right away to hopefully prevent fuckups
	ini_set("default_charset", "UTF-8");
	
	// PHP 8.2...
	if (!isset($_SERVER['HTTP_USER_AGENT']))
		$_SERVER['HTTP_USER_AGENT'] = "";

	require 'lib/function.php';

	$errors = array();
	set_error_handler('error_reporter');
	set_exception_handler('exception_reporter');


	if (!file_exists('lib/config.php')) {
		die("Configuration file missing. Please run the <a href='install'>installer</a>.");
	}
	require 'lib/config.php';

	if ($config['timezone']) {
		date_default_timezone_set($config['timezone']);
	}

	$scriptname = basename($_SERVER['PHP_SELF']);
	// If not previously defined, calculate the root page and script path.
	if (!isset($root)) {
		fetch_root($root, $boardurl);
		$scriptpath = $scriptname;
	} else {
		// Otherwise, we're definitely running an extension script. Load the current extension config.
		$xconf = ext_read_config($extName);
	}

	// Determine if to show conditionally the MySQL query list.
	if ($config['always-show-debug'] || in_array($_SERVER['REMOTE_ADDR'], $config['sqldebuggers'])) {
		if (toggle_board_cookie($_GET['debugsql'], 'debugsql')) {
			$params = preg_replace('/\&?debugsql(=[0-9]+)/i','', $_SERVER['QUERY_STRING']);
			die(header("Location: ?{$params}"));
		}

		if (filter_int($_COOKIE['debugsql']))
			mysql::$debug_on = true; // applies for all connections using the mysql class
	}


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
	$miscdata = $sql->fetchq("SELECT disable, views, scheme, specialtitle, private, backup, defaultscheme, attntitle, attntext, irc_enable, discord_enable FROM misc");

	// Wait for the midnight backup to finish...
	if ($miscdata['backup']) { // || (int) date("Gi") < 1) {
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
	
	// determine the origin of the request
	$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : filter_string($_SERVER['HTTP_REFERER']);
	
	$runtime = [
		// determine if the current request is an ajax request,
		// XMLHttpRequest functions helpfully set the x-http-requested-with header with the value "XMLHttpRequest".
		//
		// this feature has been in disuse even before the 2015 jul repository got published to github, 
		// with many of the files not even being uploaded -- the only parts remaining are (now unused) 
		// utilities in js/useful.js and the server-side code for the AJAX latestposts.
		//
		// more could be done with this one day
		'ajax-request' => isset($_SERVER["HTTP_X_REQUESTED_WITH"]) && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) == "xmlhttprequest",
		// for file downloads mostly
		'same-origin' => $origin && (parse_url($origin, PHP_URL_HOST) == parse_url($config['board-url'], PHP_URL_HOST)),
		// if 0, tells the error/query logger executed as shutdown function to not print anything.
		// reaching the pagefooter will always re-set this to 1 before the script ends, but it can be also
		// if it's 2, the script will die in content pages (ie: status.php) before attempting to set the content type,
		// to prevent the binary data from being visible.
		'show-log' => isset($_GET['eof_log']) ? (int)$_GET['eof_log'] : 0,
	];

	ext_init();

	// Execute anything to do as soon as the extension system loads
	hook_use('init');
	
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
			report_send(IRC_ADMIN, .xk(3)."Password hash for ".xk(9).$passinfo['name'].xk(3)." (uid ".xk(9).$loguserid.xk(3).") has been automatically updated (from cookie).");

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
			'viewsig'		=> 1, // Enabled, with history
			'layout'        => 1, // Regular
			'powerlevel' 	=> 0,
			'postsperpage'  => 20,
			'signsep'		=> 3, // Hide separator for guests, since post layouts are enabled
			'dateformat'	=> $config['default-dateformat'],
			'dateshort'		=> $config['default-dateshort'],
			'timezone'      => 0,
			'tzoff'			=> 0,
			'scheme'		=> $miscdata['defaultscheme'],
			'fontsize'		=> 0,
			'title'			=> '',
			'hideactivity'	=> 0,
			'uploads_locked'  => 0,
			'uploader_locked' => 0,
			'pagestyle'     => 0,
			'pollstyle'     => 0,
			'splitcat'      => 0,
			'posttool'      => 1, // Sure, why not
		);
		
		if ($miscdata['private'] == 2) {
			do404();
		}
	}



	if ($x_hacks['superadmin']) $loguser['powerlevel'] = 4;


	$banned    = (int) ($loguser['powerlevel'] <  0);
	$issuper   = (int) ($loguser['powerlevel'] >= 1);
	$ismod     = (int) ($loguser['powerlevel'] >= 2);
	$isadmin   = (int) ($loguser['powerlevel'] >= 3);
	$sysadmin  = (int) ($loguser['powerlevel'] >= 4);
	$isfullmod = $ismod;
	
	// >_>
	if ($loguser['uploads_locked']) {
		$config['allow-attachments'] = false;
	}

	// Support for stupid shit
	if (file_exists("lib/hacks.php")) {
		require "lib/hacks.php";
	}
	
	// Moved down here so hacks.php can affect the powerlevel check
	register_shutdown_function('eof_printer');

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
	$checkips .= " AND (expire = 0 OR expire > ".time().")";

	$baninfo = $sql->fetchq("SELECT ip, expire FROM ipbans WHERE $checkips");
	if($baninfo) $ipbanned = 1;

	if($sql->resultq("SELECT COUNT(*) FROM tor WHERE `ip` = '{$_SERVER['REMOTE_ADDR']}' AND `allowed` = '0'")) $torbanned = 1;

	if ($_SERVER['HTTP_REFERER']) {
		$botinfo = $sql->fetchq("SELECT signature, malicious FROM bots WHERE INSTR('".addslashes(strtolower($_SERVER['HTTP_USER_AGENT']))."', signature) > 0 ORDER BY malicious DESC");
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

	if ($origin && !$runtime['same-origin']) {
		$sql->queryp("INSERT INTO referer (time, url, ref, ip) VALUES (:time,:url,:ref,:ip)",
		[
			'time' => time(),
			'url'	=> $url,
			'ref'	=> $_SERVER['HTTP_REFERER'],
			'ip'	=> $_SERVER['REMOTE_ADDR']
		]);
	}
	
	// Alert the admin channel for IP changes, instead of just writing these out in the open, on ipchanges.log
	if ($loguser['id'] && $loguser['powerlevel'] <= 5 && !$runtime['ajax-request'] && $loguser['lastip'] != $_SERVER['REMOTE_ADDR']) {
		// Determine IP block differences
		$ip1 = explode(".", $loguser['lastip']);
		$ip2 = explode(".", $_SERVER['REMOTE_ADDR']);
		for ($diff = 0; $diff < 3; ++$diff)
			if ($ip1[$diff] != $ip2[$diff]) break;
		if ($diff == 0) $color = xk(4);	// IP completely different
		else            $color = xk(8); // Not all blocks changed
		$diff = "/".($diff+1)*8;

		report_send(
			IRC_ADMIN, xk(7)."User {$loguser['name']} (id {$loguser['id']}) changed from IP ".xk(8)."{$loguser['lastip']}".xk(7)." to ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7)." ({$color}{$diff}".xk(7).")",
			IRC_ADMIN, "User {$loguser['name']} (id {$loguser['id']}) changed from IP **{$loguser['lastip']}** to **{$_SERVER['REMOTE_ADDR']}** (**{$diff}**)"
		);

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
			remove_board_cookie('loguserid');
			remove_board_cookie('logverify');
			// Attempt to preserve current page
			die(header("Location: ?{$_SERVER['QUERY_STRING']}"));
		}
	}

	if ($ipbanned) {
		if ($loguser['title'] == "Banned; account hijacked. Contact admin via PM to change it.") {
			$reason	= "Your account was hijacked; please contact {$config['admin-name']} to reset your password and unban your account.";
		// causes problems when getting ip banned while logged in
		//} elseif ($loguser['title']) {
		//	$reason	= "Ban reason: ".xssfilters($loguser['title'])."<br>If you think have been banned in error, please contact {$config['admin-name']}.";
		} else {
			$reason	= $sql->resultq("SELECT `reason` FROM ipbans WHERE $checkips");
			$reason	= ($reason ? "Reason: ".xssfilters($reason) : "<i>(No reason given)</i>");
		}

		$expiration = (
			$baninfo['expire']
			? " until ".printdate($baninfo['expire']).".<br>That's ".timeunits2($baninfo['expire'] - time())." from now"
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
		Other helpful stuff
	*/



//  Fake downtime page, long deleted
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
