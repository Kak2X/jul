<?php

	// Set this right away to hopefully prevent fuckups
	ini_set("default_charset", "UTF-8");
	
	// UTF-8 time?
	header("Content-type: text/html; charset=utf-8'");

	// cache bad
	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	header('Pragma: no-cache');
	
	

	$startingtime = microtime(true);

	
	// Wait for the midnight backup to finish...
	if ((int)date("Gi") < 5) {
		require "lib/downtime.php";
	}
	
	// Fields necessary to generate userlinks
	$userfields = "u.name, u.aka, u.sex, u.powerlevel, u.birthday, u.namecolor, u.minipic, u.id";
	
	
	$errors = array();
	set_error_handler('error_reporter');
	set_exception_handler('exception_reporter');
		
	require 'lib/config.php';
	require 'lib/mysql.php';
	require 'lib/layout.php';
	require 'lib/rpg.php';

	
	
	$sql	= new mysql;


	$sql->connect($sqlhost, $sqluser, $sqlpass, $dbname) or
		die("<title>Damn</title>
			<body style=\"background: #000 url('images/bombbg.png'); color: #f00;\">
				<font style=\"font-family: Verdana, sans-serif;\">
				<center>
				<img src=\"http://xkeeper.shacknet.nu:5/docs/temp/mysqlbucket.png\" title=\"bought the farm, too\">
				<br><br><font style=\"color: #f88; size: 175%;\"><b>The MySQL server has exploded.</b></font>
				<br>
				<br><font style=\"color: #f55;\">Error: ". $sql->error ."</font>
				<br>
				<br><small>This is not a hack attempt; it is a server problem.</small>
			");
	//$sql->selectdb($dbname) or die("Another stupid MySQL error happened, panic<br><small>". mysql_error() ."</small>");

	// Just fetch now everything from misc that's going to be used on every page
	$miscdata = $sql->fetchq("SELECT disable, views, scheme, specialtitle, private FROM misc");
	
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
		// Bad Design Decisions 2001.
		// :(
		/*
		if (!get_magic_quotes_gpc()) {
			$_GET = addslashes_array($_GET);
			$_POST = addslashes_array($_POST);
			$_COOKIE = addslashes_array($_COOKIE);
		}
		if(!ini_get('register_globals')){
			$supers=array('_ENV', '_SERVER', '_GET', '_POST', '_COOKIE',);
			foreach($supers as $__s) if (is_array($$__s)) extract($$__s, EXTR_SKIP);
			unset($supers);
		}
		*/
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
	
	if ($miscdata['disable']) {
		if ($_SERVER['REMOTE_ADDR'] != $x_hacks['adminip']) {
			if ($miscdata['private'] == 2) {
				do404();
			} else if ($x_hacks['host']) {
				require "lib/downtime-bmf.php";
			} else {
				require "lib/downtime2.php";
			}
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
	
	// Delete expired bans
	$sql->query("
		UPDATE `users` SET 
		    `ban_expire` = 0, 
		    `powerlevel` = powerlevel_prev
		WHERE `ban_expire` != 0 AND 
		      `powerlevel` = '-1' AND
		      `ban_expire` < ".ctime()
	);
	
	$sql->query("DELETE FROM `ipbans` WHERE `expire` != 0 AND `expire` < ".ctime());
	
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
		
		if ($itemdb)
			foreach($itemdb as $item)
				if ($item['effect'] == 5) $hacks['comments'] = true;
		
		if ($loguser['id'] == 1)
			$hacks['comments'] = true;
		//else 
			//$hacks['comments'] = $sql->resultq("SELECT COUNT(*) FROM `users_rpg` WHERE `uid` = '{$loguser['id']}' AND `eq6` IN ('43', '71', '238')");
		
		/*
		if ($loguser['viewsig'] >= 3)
			return header("Location: /?sec=1");
		*/
		if ($loguser['powerlevel'] >= 1)
			$config['board-title'] .= $config['title-submessage'];
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
			'powerlevel' 	=> 0,
			'signsep'		=> 0,
			'dateformat'	=> $config['default-dateformat'],
			'dateshort'		=> $config['default-dateshort'],
			'tzoff'			=> 0,
			'scheme'		=> 0,
			'title'			=> '',
			'hideactivity'	=> 0,
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
	
	// >_>
	$isChristmas = (date('n') == 12);
	
	// Doom timer setup
	//$getdoom = true;
	//require "ext/mmdoom.php";
	
	
	
	$ipbanned = $torbanned = $isbot = 0;
	
	const BPT_IPBANNED 	= 1;
	const BPT_PROXY 	= 2;
	const BPT_TOR 		= 4;
	const BPT_BOT 		= 8;
	
	$bpt_flags = 0;
	
	// These extra variables are in control of the user. Nuke them if they're not valid IPs
	if (!($clientip    = filter_var(filter_string($_SERVER['HTTP_CLIENT_IP']),       FILTER_VALIDATE_IP))) $clientip    = "";
	if (!($forwardedip = filter_var(filter_string($_SERVER['HTTP_X_FORWARDED_FOR']), FILTER_VALIDATE_IP))) $forwardedip = "";	
	
	// Build the query to check if we're IP Banned
					  $checkips  = "INSTR('{$_SERVER['REMOTE_ADDR']}',ip) = 1";
	if ($forwardedip) $checkips .= " OR INSTR('$forwardedip',ip) = 1";
	if ($clientip)    $checkips .= " OR INSTR('$clientip',ip) = 1";

	if($sql->resultq("SELECT COUNT(*) FROM ipbans WHERE $checkips")) $ipbanned = 1;
	if($sql->resultq("SELECT COUNT(*) FROM tor WHERE `ip` = '{$_SERVER['REMOTE_ADDR']}' AND `allowed` = '0'")) $torbanned = 1;

	
	if ($_SERVER['HTTP_REFERER']) {
		$botinfo = $sql->fetchq("SELECT signature, malicious FROM bots WHERE INSTR('".addslashes(strtolower($_SERVER['HTTP_USER_AGENT']))."', signature) > 0");
		if ($botinfo) {
			$isbot = 1;
			if ($botinfo['malicious']) {
				$ipbanned = 1;
				if (!$sql->resultq("SELECT 1 FROM ipbans WHERE $checkips")) {
					$sql->query("INSERT INTO `ipbans` SET `ip` = '{$_SERVER['REMOTE_ADDR']}', `reason`='Malicious bot.', `date` = '". ctime() ."', `banner` = '0'");
				}
			}
		}
	}
	
	//if ($ipbanned || $torbanned)
	//	$windowtitle = $boardname;
	
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
	

	//if (isset($_SERVER['HTTP_REFERER']) && substr($_SERVER['HTTP_REFERER'], 0, strlen($config['board-url'])) != $config['board-url']){
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
		
		echo dialog($message, "Banned");
		
	}
	if ($torbanned) {
		$message = 	"You appear to be using a Tor proxy. Due to abuse, Tor usage is forbidden.".
					"<br>If you have been banned in error, please contact {$config['admin-name']}.".
					"<br>".
					"<br>E-mail: {$config['admin-email']}";
		
		echo dialog($message, "Tor is not allowed");
	}
	
	/*
		View milestones
	*/

	$views = $sql->resultq('SELECT views FROM misc') + 1;
	
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
		$config['board-title']				= "<span style='font-size: 2em'>$boardname</span>";
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

/*
	$getdoom	= true;
	require "ext/mmdoom.php";
*/
	//$x_hacks['rainbownames'] = ($sql->resultq("SELECT MAX(`id`) % 100000 FROM `posts`")) <= 100;
	
	// When a post milestone is reached, everybody gets rainbow colors for a day
	$x_hacks['rainbownames'] = ($sql->resultq("SELECT `date` FROM `posts` WHERE (`id` % 100000) = 0 ORDER BY `id` DESC LIMIT 1") > ctime()-86400);
	
	// Private board option
	$allowedpages = ['register.php', 'login.php', 'faq.php'];
	if (!$loguser['id'] && $miscdata['private'] && !in_array($scriptname, $allowedpages)) {
		errorpage(
			"You need to <a href='login.php'>login</a> to browse this board.<br>".
			"If you don't have an account you can <a href='register.php'>register</a> one.<br><br>".
			"The Rules/FAQ are available <a href='faq.php'>here</a>."
		);// view faq page ehre
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
// New birthday shit
/*
	$today = date('m-d',ctime() - (60 * 60 * 3));
	@$sql->query("UPDATE `users` SET `sex` = `oldsex` WHERE `sex` = 255 AND FROM_UNIXTIME(birthday,'%m-%d')!='$today'");
	@$sql->query("UPDATE `users` SET `oldsex` = `sex`, `sex` = '255' WHERE sex != 255 AND birthday AND FROM_UNIXTIME(birthday,'%m-%d')='$today'");
*/

// Old birthday shit
/*
	mysql_query("UPDATE `users` SET `sex` = '2' WHERE `sex` = 255");
	$busers = @mysql_query("SELECT id, name FROM users WHERE FROM_UNIXTIME(birthday,'%m-%d')='".date('m-d',ctime() - (60 * 60 * 3))."' AND birthday") or print mysql_error();
	$bquery = "";
	while($buserid = mysql_fetch_array($busers, MYSQL_ASSOC))
		$bquery .= ($bquery ? " OR " : "") ."`id` = '". $buserid['id'] ."'";
	if ($bquery)
		mysql_query("UPDATE `users` SET `sex` = '255' WHERE $bquery");
*/

// For our convenience (read: to go directly into a query), at the cost of sacrificing the NULL return value
function filter_int(&$v) 		{ return (int) $v; }
function filter_float(&$v)		{ return (float) $v; }
function filter_bool(&$v) 		{ return (bool) $v; }
function filter_array (&$v)		{ return (array) $v; }
function filter_string(&$v, $codefilter = false) { 
	if ($codefilter) {
		$v = str_replace("\x00", "", $v);
		$v = preg_replace("'[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]'", "", $v);

		//Unicode Control Codes
		$v = str_replace("\xC2\xA0","\x20", $v);
		$v = preg_loop($v, "\xC2+[\x80-\x9F]");
		
		// Entities
		$v = preg_replace("'(&(\n)?#x?([0-9]|[a-f])+[;>]?)+'si", "<small>(garbage entities were deleted)</small>", $v);
	}
	return (string) $v; 
}

/*
function filter_int(&$v) {
	if (!isset($v)) {
		return null;
	} else {
		$v	= (int) $v;
		return $v;
	}
}

function filter_bool(&$v) {
	if (!isset($v)) {
		return null;
	} else {
		$v	= (bool) $v;
		return $v;
	}
}


function filter_string(&$v) {
	if (!isset($v)) {
		return null;
	} else {
		$v	= (string) $v;
		return $v;
	}
}
*/

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

/* bad leftover from the old days
function numsmilies(){
	$fpnt=fopen('smilies.dat','r');
	for($i=0;fgetcsv($fpnt,300,'');$i++);
	$r=fclose($fpnt);
	return $i;
}
*/

function readpostread($userid) {
	global $sql;
	if (!$userid) return array();
	return $sql->getresultsbykey("SELECT forum, readdate FROM forumread WHERE user = $userid");
}

function generatenumbergfx($num, $minlen=0, $double = false) {
	global $numdir;

	$nw			= 8 * ($double ? 2 : 1);
	$num		= (string) $num; // strval
	$len		= strlen($num);
	$gfxcode	= "";

	// Left-Padding
	if($minlen > 1 && $len < $minlen) {
		$gfxcode = "<img src='images/_.gif' width=". ($nw * ($minlen - $len)) ." height=$nw>";
	}

	for($i = 0; $i < $len; ++$i) {
		$code	= $num[$i];
		switch ($code) {
			case "/":
				$code	= "slash";
				break;
		}
		if ($code == " ") {
			$gfxcode.="<img src='images/_.gif' width=$nw height=$nw>";

		} else {
			$gfxcode.="<img src='numgfx/$numdir$code.png' width=$nw height=$nw>";

		}
	}
	return $gfxcode;
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
	// options will contain smiliesoff|htmloff
	$options = explode("|", $options);
	$smiliesoff = $options[0];
	$htmloff = $options[1];


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
	//$msg=str_replace('http://nightkev.110mb.com/justus_layout.css','about:blank',$msg);
	$msg=preg_replace("'\[youtube\]([a-zA-Z0-9_-]{11})\[/youtube\]'si", '<iframe src="https://www.youtube.com/embed/\1" width="560" height="315" frameborder="0" allowfullscreen="allowfullscreen"></iframe>', $msg);


	do {
		$msg	= preg_replace("/<(\/?)t(able|h|r|d)(.*?)>(\s+?)<(\/?)t(able|h|r|d)(.*?)>/si",
				"<\\1t\\2\\3><\\5t\\6\\7>", $msg, -1, $replaced);
	} while ($replaced >= 1);


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


function doforumlist($id, $name = ''){
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
	if ($name) return "<select name='$name'>$forumlinks</select>";
	
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
			}/*
			$dotnum[5] = floor($postsx / $pr[5]);
			$postsx = $postsx - $dotnum[5] * $pr[5];
			$dotnum[4] = floor($postsx / $pr[4]);
			$postsx = $postsx - $dotnum[4] * $pr[4];
			$dotnum[3] = floor($postsx / $pr[3]);
			$postsx = $postsx - $dotnum[3] * $pr[3];
			$dotnum[2] = floor($postsx / $pr[2]);
			$postsx = $postsx - $dotnum[2] * $pr[2];
			$dotnum[1] = floor($postsx / $pr[1]);
*/
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

// Only used to check if an user exists
function checkusername($name){
	global $sql;
	if (!$name) return -1;
	$u = $sql->resultp("SELECT id FROM users WHERE name = ?", [$name]);
	if (!$u) $u = -1;
	return $u;
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

function generate_token($div = 20, $extra = "") {
	global $config, $loguser;
	
	$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
	
	$n 		= count($ipaddr) - 2;
	$orig 	= $ipaddr[$n+1];
	
	for ($i = $n; $i >= 0; --$i) 
		$ipaddr[$i+1] = $ipaddr[$i+1] << ($ipaddr[$i] / $div);
	$ipaddr[0] = $ipaddr[0] << ($orig / $div);
	
	$ipaddr = implode('.', $ipaddr);
		
	return hash('sha256', $loguser['name'] . $ipaddr . $config['salt-string'] . $extra . $loguser['password']);
	
}

function check_token(&$var, $div = 20, $extra = "") {
	$res = (trim($var) == generate_token($div, $extra));
	if (!$res) errorpage("Invalid token.");
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
/*
function getuserlink(&$u, $substitutions = null, $urlclass = '') {
	
	if ($substitutions === true) {
		global $herpderpwelp;
		if (!$herpderpwelp)
			trigger_error('Deprecated: $substitutions passed true (old behavior)', E_USER_NOTICE);
		$herpderpwelp = true;
	}
	
	global $herpderpwelp;
	if (!$herpderpwelp)
		trigger_error('Deprecated: getuserlink function used', E_USER_NOTICE);
	$herpderpwelp = true;

	// dumb hack for $substitutions
	$fn = array(
		'aka'			=> 'aka',
		'id'			=> 'id',
		'name'			=> 'name',
		'sex'			=> 'sex',
		'powerlevel'	=> 'powerlevel',
		'birthday'		=> 'birthday'
	);
	if ($substitutions)
		$fn = array_merge($fn, $substitutions);

	$akafield = htmlspecialchars($u[$fn['aka']], ENT_QUOTES);
	$alsoKnownAs = (($u[$fn['aka']] && $u[$fn['aka']] != $u[$fn['name']])
		? " title='Also known as: {$akafield}'" : '');

	$u[$fn['name']] = htmlspecialchars($u[$fn['name']], ENT_QUOTES);

	global $tzoff;
	$birthday = (date('m-d', $u[$fn['birthday']]) == date('m-d',ctime() + $tzoff));
	$rsex = (($birthday) ? 255 : $u[$fn['sex']]);

	$namecolor = getnamecolor($rsex, $u[$fn['powerlevel']]);

	$class = $urlclass ? " class='{$urlclass}'" : "";
	
	return "<a style='color:#{$namecolor};'{$class} href='profile.php?id=". $u[$fn['id']] ."'{$alsoKnownAs}>". $u[$fn['name']] ."</a>";
}
*/

function getuserlink($u = NULL, $id = 0, $urlclass = '', $useicon = false) {
	global $sql, $loguser, $userfields;
	
	if (!$u) {
		if ($id == $loguser['id']) $u = $loguser;
		else {
			$u = $sql->fetchq("SELECT $userfields FROM users u WHERE id = $id", PDO::FETCH_ASSOC, mysql::USE_CACHE);
			//if (!$u) return "<span style='color: #FF0000'>[Invalid userlink with ID #$id]</span>"; // (development only notice)
		}
	}
	
	if ($id) $u['id'] = $id;
	
	// Values being NULL is a sign of a deleted user
	// Print this so we don't just end up with a blank link.
	if ($u['name'] == NULL) {
		return "<span style='color: #FF0000'><b>[Deleted user]</b></span>";
	}
	
	$akafield		= htmlspecialchars($u['aka']);
	$alsoKnownAs	= ($u['aka'] && $u['aka'] != $u['name']) ? " title=\"Also known as: {$akafield}\"" : '';
	$u['name'] 		= htmlspecialchars($u['name'], ENT_QUOTES);
	// Don't calculate birthday effect again
	if ($u['namecolor'] != 'rnbow' && is_birthday($u['birthday'])) {
		$u['namecolor'] = 'rnbow';
	}
	$namecolor		= getnamecolor($u['sex'], $u['powerlevel'], $u['namecolor']);
	
	$minipic		= ($useicon && isset($u['minipic']) && $u['minipic']) ? "<img width=16 height=16 src=\"".htmlspecialchars($u['minipic'], ENT_QUOTES)."\" align='absmiddle'> " : "";
	
	$class = $urlclass ? " class='{$urlclass}'" : "";
	
	
	return "$minipic<a style='color:#{$namecolor}'{$class} href='profile.php?id={$u['id']}'{$alsoKnownAs}>{$u['name']}</a>";
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

const IRC_MAIN = 0;
const IRC_STAFF = 1;
const IRC_ADMIN = 102;
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

function fonlineusers($id){
	global $loguser, $sql, $userfields, $isadmin, $ismod;

	if($loguser['id'])
		$sql->query("UPDATE users  SET lastforum = $id WHERE id = {$loguser['id']}");
	else
		$sql->query("UPDATE guests SET lastforum = $id WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");

	$forumname		= $sql->resultq("SELECT title FROM forums WHERE id = $id");
	$onlinetime		= ctime()-300;
	$onusers		= $sql->query("
		SELECT $userfields, hideactivity, (lastactivity <= $onlinetime) nologpost
		FROM users u
		WHERE lastactivity > $onlinetime AND lastforum = $id AND ($ismod OR !hideactivity)
		ORDER BY name
	");
	
	
	$onlineusers	= "";

	for($numon = 0; $onuser = $sql->fetch($onusers); ++$numon){
		
		if($numon) $onlineusers .= ', ';

		/* if ((!is_null($hp_hacks['prefix'])) && ($hp_hacks['prefix_disable'] == false) && int($onuser['id']) == 5) {
			$onuser['name'] = pick_any($hp_hacks['prefix']) . " " . $onuser['name'];
		} */
		$onuser['minipic']	 = htmlspecialchars($onuser['minipic'], ENT_QUOTES);
		$namelink			 = getuserlink($onuser);
		$onlineusers		.='<nobr>';
		
		if($onuser['nologpost']) // Was the user posting without using cookies?
			$namelink="($namelink)";
			
		if($onuser['hideactivity'])
			$namelink="[$namelink]";		
			
		if ($onuser['minipic'])
			$namelink = "<img width=16 height=16 src=\"".htmlspecialchars($onuser['minipic'])."\" align='absmiddle'> $namelink";
			
		$onlineusers .= "$namelink</nobr>";
	}
	$p = ($numon ? ':' : '.');
	$s = ($numon != 1 ? 's' : '');
	
	$guests = "";
	if (!$isadmin) {
		$numguests = $sql->resultq("SELECT COUNT(*) FROM guests	WHERE date > $onlinetime AND lastforum = $id");
		if($numguests) $guests = "| $numguests guest" . ($numguests > 1 ? 's' : '');
	} else {
		// Detailed view of tor/proxy/bots
		$onguests = $sql->query("SELECT flags FROM guests WHERE date > $onlinetime");
		$ginfo = array_fill(0, 4, 0);
		for ($numguests = 0; $onguest = $sql->fetch($onguests); ++$numguests) {
			if      ($onguest['flags'] & BPT_TOR) 		$ginfo[2]++;
			else if ($onguest['flags'] & BPT_IPBANNED) 	$ginfo[0]++;
			else if ($onguest['flags'] & BPT_BOT) 		$ginfo[3]++;
			//if ($onguest['flags'] & BPT_PROXY) 		$ginfo[1]++;
		}
		$specinfo = array('IP banned', 'Proxy', 'Tor banned', 'bots');
		$guestcat = array();
		for ($i = 0; $i < 4; ++$i)
			if ($ginfo[$i])
				$guestcat[] = $ginfo[$i] . " " . $specinfo[$i];
		$guests = $numguests ? " | <nobr>$numguests guest".($numguests>1?"s":"").($guestcat ? " (".implode(",", $guestcat).")" : "") : "";
	}
	
	return "$numon user$s currently in $forumname$p $onlineusers $guests";
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
	if($delay < 1) $delay = 1;
	return "You will now be redirected to <a href=$url>$msg</a>...<META HTTP-EQUIV=REFRESH CONTENT=$delay;URL=$url>";
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

/* useless function, leftover that should have never been used in the first place
function loaduser($id,$type){
	global $sql;
	if ($type==1) {$fields='id,name,sex,powerlevel,posts';}
	return @$sql->fetchq("SELECT $fields FROM users WHERE id=$id");
}
*/

function getpostlayoutid($text){
	global $sql;
	
	// Everything breaks on transactions if $text is blank
	if (!$text) return 0;
	
	$id = $sql->resultp("SELECT id FROM postlayouts WHERE text = ? LIMIT 1", [$text]);
	// Is this a new layout?
	if (!$id) {
		$sql->queryp("INSERT INTO postlayouts (text) VALUES (?)", [$text]);
		$id = $sql->insert_id();
	}
	return $id;
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


function moodlist($sel = 0, $return = false) {
	global $loguser;
	$sel		= floor($sel);

	$a	= array("None", "neutral", "angry", "tired/upset", "playful", "doom", "delight", "guru", "hope", "puzzled", "whatever", "hyperactive", "sadness", "bleh", "embarrassed", "amused", "afraid");
	//if ($loguserid == 1) $a[99] = "special";
	if ($return) return $a;

	$c[$sel]	= " checked";
	$ret		= "";

	if ($loguser['id'] && $loguser['moodurl'])
		$ret = '
			<script type="text/javascript">
				function avatarpreview(uid,pic)
				{
					if (pic > 0)
					{
						var moodav="'.htmlspecialchars($loguser['moodurl']).'";
						document.getElementById(\'prev\').src=moodav.replace("$", pic);
					}
					else
					{
						document.getElementById(\'prev\').src="images/_.gif";
					}
				}
			</script>
		';

	$ret .= "
		<b>Mood avatar list:</b><br>
		<table style='border-spacing: 0px'>
			<tr>
				<td style='width: 150px; white-space:nowrap'>";

	foreach($a as $num => $name) {
		$jsclick = (($loguser['id'] && $loguser['moodurl']) ? "onclick='avatarpreview({$loguser['id']},$num)'" : "");
		$ret .= "<input type='radio' name='moodid' value='$num'". filter_string($c[$num]) ." id='mood$num' tabindex='". (9000 + $num) ."' style='height: 12px' $jsclick>
             <label for='mood$num' ". filter_string($c[$sel]) ." style='font-size: 12px'>&nbsp;$num:&nbsp;$name</label><br>\r\n";
	}

	if (!$sel || !$loguser['id'] || !$loguser['moodurl'])
		$startimg = 'images/_.gif';
	else
		$startimg = htmlspecialchars(str_replace('$', $sel, $loguser['moodurl']));

	$ret .= "	</td>
				<td>
					<img src=\"$startimg\" id=prev>
				</td>
			</tr>
		</table>";
	return $ret;
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

function adminlinkbar($sel = 'admin.php') {
	global $isadmin;

	if (!$isadmin) return;

	$links	= array(
		array(
			'admin.php'	=> "Admin Control Panel",
		),
		array(
//			'admin-todo.php'       => "To-do list",
			'announcement.php'     => "Go to Announcements",
			'admin-editforums.php' => "Edit Forum List",
			'admin-editmods.php'   => "Edit Forum Moderators",
			'ipsearch.php'         => "IP Search",
			'admin-threads.php'    => "ThreadFix",
			'admin-threads2.php'   => "ThreadFix 2",
			'del.php'              => "Delete User",
		)
	);

	$r = "<div style='padding:0px;margins:0px;'>
			<table class='table'>
				<tr>
					<td class='tdbgh center'>
						<b>Admin Functions</b>
					</td>
				</tr>
			</table>";

    foreach ($links as $linkrow) {
		$c	= count($linkrow);
		$w	= floor(1 / $c * 100);

		$r .= "<table class='table'><tr>";

		foreach($linkrow as $link => $name) {
			$cell = '1';
			if ($link == $sel) $cell = 'c';
			$r .= "<td class='tdbg{$cell} center' width=\"$w%\"><a href=\"$link\">$name</a></td>";
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
		$f = fopen("../js/$fn",'r');
		$c = fread($f, filesize($fn));
		fclose($f);
		return '<script type="text/javascript">'.$c.'</script>';
	}
}


function xssfilters($p, $validate = false){

	$temp = $p;
	
	$p=str_ireplace("FSCommand","BS<z>Command", $p);
	$p=str_ireplace("execcommand","hex<z>het", $p);
	// This shouldn't hit code blocks due to the way they are formatted
	$p=preg_replace("'on\w+( *?)=( *?)(\'|\")'si", "jscrap=$3", $p);
	$p=preg_replace("'<(/?)(script|meta|embed|object|svg|form|textarea|xml|title|input|xmp|plaintext|base|!doctype|html|head|body)'i", "&lt;$1$2", $p);
	$p=preg_replace("'<iframe(?! src=\"https://www.youtube.com/embed/)'si",'<<z>iframe',$p);
	
	/*
	$p=preg_replace("'onload'si",'onl<z>oad',$p);
	$p=preg_replace("'onerror'si",'oner<z>ror',$p);
	$p=preg_replace("'onunload'si",'onun<z>load',$p);
	$p=preg_replace("'onchange'si",'onch<z>ange',$p);
	$p=preg_replace("'onsubmit'si",'onsu<z>bmit',$p);
	$p=preg_replace("'onreset'si",'onr<z>eset',$p);
	$p=preg_replace("'onselect'si",'ons<z>elect',$p);
	$p=preg_replace("'onblur'si",'onb<z>lur',$p);
	$p=preg_replace("'onfocus'si",'onfo<z>cus',$p);
	$p=preg_replace("'onclick'si",'oncl<z>ick',$p);
	$p=preg_replace("'ondblclick'si",'ondbl<z>click',$p);
	$p=preg_replace("'onmousedown'si",'onm<z>ousedown',$p);
	$p=preg_replace("'onmousemove'si",'onmou<z>semove',$p);
	$p=preg_replace("'onmouseout'si",'onmou<z>seout',$p);
	$p=preg_replace("'onmouseover'si",'onmo<z>useover',$p);
	$p=preg_replace("'onmouseup'si",'onmou<z>seup',$p);
	*/
	if ($temp != $p) {
		nuke_js($temp, $p);
		if ($validate) return NULL;
	}
	
	
	$p=preg_replace("'document.cookie'si",'document.co<z>okie',$p);
	$p=preg_replace("'eval'si",'eva<z>l',$p);
	$p=preg_replace("'javascript:'si",'javasc<z>ript:',$p);	
	//$p=preg_replace("'document.'si",'docufail.',$p);
	//$p=preg_replace("'<script'si",'<<z>script',$p);
	//$p=preg_replace("'</script'si",'<<z>/script',$p);
	//$p=preg_replace("'<meta'si",'<<z>meta',$p);
	

	return $p;
	
}
function dofilters($p){
	global $hacks;
	
	
	$p = xssfilters($p);
	
	/*
	if (filter_bool($_GET['t']) && false) {
		$p=preg_replace("'<script(.*?)</script>'si",'',$p);
		$p=preg_replace("'<script'si",'',$p);
		$p=preg_replace("'\b\s(on[^=]*?=.*)\b'si",'',$p);
		if ($temp != $p) {
			nuke_js($temp, $p);
		}
	} else {
	


		if ($temp != $p) {
			nuke_js($temp, $p);
		}
	}
	*/
	
	//$p=preg_replace("'<object(.*?)</object>'si","",$p);
	//$p=preg_replace("'autoplay'si",'',$p); // kills autoplay, need to think of a solution for embeds.

	// Absolute allowed now alongside position:relative div
	//$p=preg_replace("'position\s*:\s*(absolute|fixed)'si", "display:none", $p);
	$p=preg_replace("'position\s*:\s*fixed'si", "display:none", $p);


	//$p=preg_replace("':awesome:'","<small>[unfunny]</small>", $p);

	$p=preg_replace("':facepalm:'si",'<img src=images/facepalm.jpg>',$p);
	$p=preg_replace("':facepalm2:'si",'<img src=images/facepalm2.jpg>',$p);
	$p=preg_replace("':epicburn:'si",'<img src=images/epicburn.png>',$p);
	$p=preg_replace("':umad:'si",'<img src=images/umad.jpg>',$p);
	$p=preg_replace("':gamepro5:'si",'<img src=images/gamepro5.gif title="FIVE EXPLODING HEADS OUT OF FIVE">',$p);
	$p=preg_replace("':headdesk:'si",'<img src=images/headdesk.jpg title="Steven Colbert to the rescue">',$p);
	$p=preg_replace("':rereggie:'si",'<img src=images/rereggie.png>',$p);
	$p=preg_replace("':tmyk:'si",'<img src=images/themoreyouknow.jpg title="do doo do doooooo~">',$p);
	$p=preg_replace("':jmsu:'si",'<img src=images/jmsu.png>',$p);
	$p=preg_replace("':noted:'si",'<img src=images/noted.png title="NOTED, THANKS!!">',$p);
	$p=preg_replace("':apathy:'si",'<img src=images/stickfigure-notext.png title="who cares">',$p);
	$p=preg_replace("':spinnaz:'si", '<img src="images/smilies/spinnaz.gif">', $p);
	$p=preg_replace("':trolldra:'si", '<img src="images/trolldra.png">', $p);
	$p=preg_replace("':reggie:'si",'<img src=images/reggieshrug.jpg title="REGGIE!">',$p);

//	$p=preg_replace("'drama'si", 'batter blaster', $p);
//	$p=preg_replace("'TheKinoko'si", 'MY NAME MEANS MUSHROOM... IN <i>JAPANESE!</i> HOLY SHIT GUYS THIS IS <i>INCREDIBLE</i>!!!!!!!!!', $p);
//	$p=preg_replace("'hopy'si",'I am a dumb',$p);
	$p=preg_replace("'crashdance'si",'CrashDunce',$p);
	$p=preg_replace("'get blue spheres'si",'HI EVERYBODY I\'M A RETARD PLEASE BAN ME',$p);
	$p=preg_replace("'zeon'si",'shit',$p);
	$p=preg_replace("'faith in humanity'si",'IQ',$p);
//	$p=preg_replace("'motorcycles'si",'<img src="images/cardgames.png" align="absmiddle" title="DERP DERP DERP">',$p);
//	$p=preg_replace("'card games'si",'<img src="images/motorcycles.png" align="absmiddle" title="GET BLUE SPHERES">',$p);
//	$p=preg_replace("'touhou'si", "Baby's First Bullet Hell&trade;", $p);
//	$p=preg_replace("'nintendo'si",'grandma',$p);
//	$p=preg_replace("'card games on motorcycles'si",'bard dames on rotorcycles',$p);

	$p=str_replace("ftp://teconmoon.no-ip.org", 'about:blank', $p);
	if (filter_bool($hacks['comments'])) {
		$p=str_replace("<!--", '<font color=#80ff80>&lt;!--', $p);
		$p=str_replace("-->", '--&gt;</font>', $p);
	}

	$p=str_replace("http://insectduel.proboards82.com","http://jul.rustedlogic.net/idiotredir.php?",$p);
//	$p=str_replace("http://imageshack.us", "imageshit", $p);
	$p=preg_replace("'http://.{0,3}\.?tinypic\.com'si",'tinyshit',$p);
	$p=str_replace('<link href="http://pieguy1372.freeweb7.com/misc/piehills.css" rel="stylesheet">',"<!-- -->",$p);
	$p=str_replace("tabindex=\"0\" ","title=\"the owner of this button is a fucking dumbass\" ",$p);
	$p=str_replace("%WIKISTATSFRAME%","<div id=\"widgetIframe\"><iframe width=\"600\" height=\"260\" src=\"http://stats.rustedlogic.net/index.php?module=Widgetize&action=iframe&moduleToWidgetize=VisitsSummary&actionToWidgetize=getSparklines&idSite=2&period=day&date=today&disableLink=1\" scrolling=\"no\" frameborder=\"0\" marginheight=\"0\" marginwidth=\"0\"></iframe></div>",$p);
	$p=str_replace("%WIKISTATSFRAME2%", '<div id="widgetIframe"><iframe width="100%" height="600" src="http://stats.rustedlogic.net/index.php?module=Widgetize&action=iframe&moduleToWidgetize=Referers&actionToWidgetize=getWebsites&idSite=2&period=day&date=2010-10-12&disableLink=1" scrolling="no" frameborder="0" marginheight="0" marginwidth="0"></iframe></div>', $p);
//	$p=str_replace("http://xkeeper.shacknet.nu:5/", 'http://xchan.shacknet.nu:5/', $p);
//	$p=preg_replace("'<style'si",'&lt;style',$p);
	//$p=str_replace("-.-", "I'M AN ANNOYING UNDERAGE ASSHAT SO I SHOULDN'T BE POSTING BUT I DO IT ANYWAY", $p);
	$p=preg_replace("'(https?://.*?photobucket.com/)'si",'images/photobucket.png#\\1',$p);
	//$p=preg_replace("'%BZZZ%'si",'onclick="bzzz(',$p);
	
	return $p;
}


require 'lib/threadpost.php';
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
	// $str = <chan id>|<message>
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


// additional includes
require_once "lib/datetime.php";


function unescape($in) {

	$out	= urldecode($in);
	while ($out != $in) {
		$in		= $out;
		$out	= urldecode($in);
	}
	return $out;

}

function preg_loop($before, $regex){
	$after = preg_replace("'{$regex}'", "", $before);
	while ($before != $after){
		$before = $after;
		$after = preg_replace("'{$regex}'", "", $before);
	}
	return $after;
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

