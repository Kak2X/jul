<?php

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
	return $sql->fetchq("SELECT forum, readdate FROM forumread WHERE user = $userid", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
}


function getrank($rankset, $title, $posts, $group, $bandate = NULL){
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

	if ($title) {
		$rank .= '<br>'.$title;
	} else if (!in_array($group, [GROUP_NORMAL, GROUP_SUPER])) {
		global $grouplist;
		$rank .= '<br>'.$grouplist[$group]['name']; //filter_string($powerranks[$powl]);
	} 
	// *LIVE* ban expiration date
	if ($bandate && $group == GROUP_BANNED) {
		$rank .= (isset($grouplist) ? "" : "<br>Banned")." until ".printdate($bandate, PRINT_DATE)."<br>Expires in ".timeunits2($bandate-ctime());
	}
	return $rank;
}

/* there's no gunbound rank (yet)
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

function getuserlink($u = NULL, $id = 0, $urlclass = '', $useicon = false) {
	global $sql, $loguser, $grouplist, $userfields;
	
	if (!$u) {
		if ($id == $loguser['id']) {
			$u = $loguser;
		} else {
			$u = $sql->fetchq("SELECT $userfields FROM users u WHERE id = $id", PDO::FETCH_ASSOC, mysql::USE_CACHE);
		}
	}
	
	if (!$id && $u['id']) {
		$id = $u['id'];
	}
	// When the username is null it typically means the user has been deleted.
	// Print this so we don't just end up with a blank link.
	if ($u['name'] == NULL) {
		return "<span style='color: #FF0000' class='b nobr'>[Deleted user]</span>";
	}
	
	// don't htmlspecialchar it yet (for the aka check)
	$name = $u['displayname'] ? $u['displayname'] : $u['name'];
	
	if ($u['displayname'] && !$u['aka']) {
		// If no aka is specified but we have a display name, by default it will use the login name
		$alsoKnownAs = " title=\"Also known as: ".htmlspecialchars($u['name'])."\"";
	} else if ($u['aka'] && $u['aka'] != $name) {
		// If it's specified and it's different from the displayed name, print it normally
		$alsoKnownAs	= " title=\"Also known as: ".htmlspecialchars($u['aka'])."\"";
	} else {
		$alsoKnownAs 	= "";
	}
	
	$name = htmlspecialchars($name, ENT_QUOTES);
	
	
	if ($u['namecolor']) {
		if ($u['namecolor'] != 'rnbow' && is_birthday($u['birthday'])) { // // Don't calculate birthday effect again
			$namecolor = 'rnbow';
		} else {
			$namecolor = $u['namecolor'];
		}
	} else {
		$namecolor = "";
	}
	
	$namecolor  = getnamecolor($u['sex'], get_usergroup($u), $namecolor);
	$minipic    = ($useicon && has_minipic($u['id'])) ? get_minipic($u['id'], true)." " : "";
	
	return "$minipic<a style='color:#{$namecolor}' class='{$urlclass} nobr' href='profile.php?id={$u['id']}'{$alsoKnownAs}>{$name}</a>";
}

// Handle subgroups
// Note this does mean if the subgroup with higher priority is hidden, it won't be shown
function get_usergroup($u) {
	global $grouplist;
	return (
		$u['main_subgroup'] && 
		(
			!$grouplist[$u['main_subgroup']]['hidden'] || 
			has_perm('show-super-users')
		)
	) ? $u['main_subgroup'] : $u['group'];
}

// hopefully this will result in some consistency when asking just the minipic
// now using max-???? properties so it won't stretch when it's less than the minimum
function get_minipic($user, $skipcheck = false) {
	if ($skipcheck || has_minipic($user)) {
		global $config;
		return "<img style='max-width: {$config['max-minipic-size-x']}px;max-height: {$config['max-minipic-size-y']}px' src='".avatarpath($user, 'm')."' align='absmiddle'>";
	} else {
		return "";
	}
}

function has_minipic($user) { return is_file(avatarpath($user, 'm')); }
function del_minipic($user) {
	if (has_minipic($user)) {
		return unlink(avatarpath($user, 'm'));
	} else {
		return false;
	}
}

function getnamecolor($sex, $group, $namecolor = ''){
	global $grouplist, $x_hacks;

	// Force rainbow effect on everybody
	if ($x_hacks['rainbownames']) $namecolor = 'rnbow';
	
	if ($namecolor) {
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
	else {
		$output = $grouplist[$group]['namecolor'.$sex];
	}

	return $output;
}

// Intended to be used with preg_replace_callback
function userlink_from_id($id){return getuserlink(NULL, $id[1], '', true);}
function userlink_from_name($set){
	global $userfields;
	// Might as well fetch all fields here
	$data = $sql->fetchp("SELECT $userfields FROM users u WHERE u.name = ?", [$set[1]], PDO::FETCH_ASSOC, mysql::USE_CACHE);
	return makeuserlink($data, $data['id'], '', true);
}

function postradar($userid){
	global $sql, $loguser, $userfields, $hacks;
	
	// Save ourselves a query if we're viewing our own post radar
	// since we already fetch all user fields for $loguser
	if (!$userid) {
		return ""; // Very likely we're not logged in. Get out.
	} else if ($userid == $loguser['id']) {
		$userdata = $loguser;
	} else {
		$userdata = $sql->fetchq("SELECT posts, radar_mode FROM users WHERE id = $userid");
	}
	
	$race = '';

	if (!$userdata['radar_mode']) {
		// Standard post radar
		$postradar = $sql->query("
			SELECT u.posts, $userfields
			FROM postradar p
			INNER JOIN users u ON p.comp = u.id
			WHERE p.user = $userid
			ORDER BY posts DESC
		");
	} else {
		// Automatic post radar
		// might as well give high priority to the user for simplicity's sake
		$rank = $sql->resultq("
			SELECT COUNT(*)
			FROM users u1
			LEFT JOIN users u2 ON u2.posts > u1.posts
			WHERE u1.id = $userid
		");
		$postradar = $sql->query("
			SELECT u.posts, $userfields
			FROM users u
			ORDER BY u.posts DESC
			LIMIT ".($rank > 1 ? $rank - 2 : 0).", 5
		");
	}
	
	$rows = $sql->num_rows($postradar);
	
	if ($rows) {
		$race = 'You are ';
		
		$myposts = $userdata['posts'];

		for($i = 0; $user = $sql->fetch($postradar); ++$i) {
			if ($i)                     $race .= ', ';
			if ($i && $i == $rows - 1)  $race .= 'and ';
			
			// get the string for comparision, which looks off when noposts is set
			$diff = $myposts - $user['posts'];
			if ($diff < 0)
				$comp_txt = (!$hacks['noposts'] ? -$diff : "") ." behind";
			else if ($diff > 0)
				$comp_txt = (!$hacks['noposts'] ?  $diff : "") ." ahead of";
			else
				$comp_txt = ' tied with';
			
			$race .= 
			"<span class='nobr'>".
				"{$comp_txt} ". getuserlink($user) . (!$hacks['noposts'] ? " ({$user['posts']})" : "").
			"</span>";
		}
	}
	return $race;
}
// TODO: Convert to be global function as in boardc
// $id becomes $forum['id']
function onlineusers($forum = NULL, $thread = NULL){
	global $loguser, $sql, $userfields;
	
	// Start off by determining what extra checks have to be made
	if ($thread) {
		$check_and = "AND lastforum = {$forum['id']} AND lastthread = {$thread['id']}";
		$update    = "lastforum = {$forum['id']}, lastthread = {$thread['id']}"; // For online users update
		$location  = "reading '<i>" . htmlspecialchars($thread['title']) . "<i>'"; // "users currently in <thread>"
	} else if ($forum) {
		$check_and = "AND lastforum = {$forum['id']}";
		$update    = "lastforum = {$forum['id']}, lastthread = 0";
		$location  = "in '<i>" . htmlspecialchars($forum['title']) . "<i>'";  // "users currently in <forum>"
	} else {
		$check_and = "";
		$update    = "lastforum = 0, lastthread = 0";
		$location  = "online"; // "users currently online"
	}

	// Update lastforum/lastthread information
	if ($loguser['id'])
		$sql->query("UPDATE users  SET {$update} WHERE id = {$loguser['id']}");
	else
		$sql->query("UPDATE guests SET {$update} WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");

	$onlinetime     = ctime() - 300; // 5 minutes
	
	/*
		Online users
	*/
	$onusers        = $sql->query("
		SELECT {$userfields}, hideactivity, (lastactivity <= {$onlinetime}) nologpost
		FROM users u
		WHERE lastactivity > {$onlinetime} 
		  {$check_and} 
		  AND (".has_perm('show-hidden-user-activity')." OR !hideactivity)
		ORDER BY u.name
	");
	
	$onlineusers	= "";
	for($numon = 0; $onuser = $sql->fetch($onusers); ++$numon){
		
		if ($numon) $onlineusers .= ', ';
		
		// hardcoded name randomizer?
		/* if ((!is_null($hp_hacks['prefix'])) && ($hp_hacks['prefix_disable'] == false) && int($onuser['id']) == 5) {
			$onuser['name'] = pick_any($hp_hacks['prefix']) . " " . $onuser['name'];
		} */
		$namelink     = getuserlink($onuser);
		$minipic      = has_minipic($onuser['id']) ? get_minipic($onuser['id'], true)." " : "";
		
		if($onuser['nologpost']) // Was the user posting without using cookies?
			$namelink="($namelink)";
			
		if($onuser['hideactivity']) // is the user trying to be a sneaky asshole?
			$namelink="[$namelink]";		
		
		$onlineusers .= $minipic.$namelink;
	}
	

	$users = "{$numon} user".($numon != 1 ? 's' : '').
	         " currently {$location}".($numon ? ': ' : '.').
			 "{$onlineusers}";
	
	/*
		Online guests
	*/
	$guests    = "";
	$bpt_info  = "";
	if (!has_perm('view-bpt-info')) {
		// Standard guest counter view
		$numguests = $sql->resultq("SELECT COUNT(*) FROM guests	WHERE date > {$onlinetime} {$check_and}");
	} else {
		// Detailed view of BPT (Bot/Proxy/Tor) flags
		$onguests = $sql->query("SELECT flags FROM guests WHERE date > {$onlinetime} {$check_and}");
		// Fill in the proper flag counters with the proper priority
		// - tor & ipbanned at the top since they are blocked (includes malicious bots, as they are ipbanned on sight)
		// - bot & proxy not globally blocked have less priority
		$ginfo = array_fill(0, 4, 0);
		for ($numguests = 0; $onguest = $sql->fetch($onguests); ++$numguests) {
			if      ($onguest['flags'] & BPT_TOR)       $ginfo[2]++;
			else if ($onguest['flags'] & BPT_IPBANNED)  $ginfo[0]++;
			else if ($onguest['flags'] & BPT_BOT)       $ginfo[3]++;
			else if ($onguest['flags'] & BPT_PROXY)     $ginfo[1]++;
		}
		if ($numguests) {
			// Print out those BPT flags...
			$specinfo = array(
				'IP banned', 
				'Prox'.($ginfo[1] == 1 ? 'ies' : 'y'), 
				'Tor banned', 
				'bot'.($ginfo[1] == 1 ? '' : 's')
			);
			$guestcat = "";
			for ($i = 0; $i < 4; ++$i) {
				if ($ginfo[$i]) { // <cnt> IP banned, <cnt> Proxies, ...
					if ($guestcat) $guestcat .= ', ';
					$guestcat .= $ginfo[$i] . " " . $specinfo[$i]; 
				}
			}
			
			if ($guestcat) {
				$bpt_info = "({$guestcat})";
			}
			
		}
	}
	
	// The guest part is optional, according to tradition.
	if ($numguests) {
		$guests = "| {$numguests} guest" . ($numguests == 1 ? '' : 's') . "{$bpt_info}";
	}
	
	return "{$users} {$guests}";
}

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

function loadtlayout(){
	global $loguser, $tlayout, $sql;
	$tlayout    = $loguser['layout'] ? $loguser['layout'] : 1;
	$layoutfile = $sql->resultq("SELECT file FROM tlayouts WHERE id = $tlayout");
	require "tlayouts/$layoutfile.php";
}

// moodlist(return -> true)
function getavatars($user, $all = false) {
	global $sql;
	return $sql->fetchq("
		SELECT file, title, hidden
		FROM user_avatars
		WHERE user = {$user}".($all ? "" : " AND file != 0")."
		ORDER by file ASC
	", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
}

function avatarpath($user, $file_id) {return "userpic/{$user}/{$file_id}";}
function dummy_avatar($title, $hidden) {return ['title' => $title, 'hidden' => $hidden];}


// Banner 0 = automatic ban
function ipban($ip, $reason, $ircreason = NULL, $destchannel = IRC_STAFF, $expire = 0, $banner = 0) {
	global $sql;
	$sql->query("INSERT INTO `ipbans` SET `ip` = '{$ip}', `reason`='{$reason}', `date` = '". ctime() ."', `banner` = '{$banner}', `expire` = '{$expire}'");
	if ($ircreason !== NULL) {
		xk_ircsend("{$destchannel}|{$ircreason}");
	}
}

function userban($id, $reason = "", $ircreason = NULL, $expire = false, $permanent = false){
	global $sql;

	$expire_query	= ($expire && !$permanent) ? ",`ban_expire` = '".(ctime()+3600*$expire)."'" : "";
	$new_powl		= $permanent ? GROUP_PERMABANNED : GROUP_BANNED;
			
	$res = $sql->queryp("UPDATE users SET `group_prev` = `group`, `group` = ?, title = ? $expire_query WHERE id = ?", array($new_powl, $reason, $id));
	if ($ircreason !== NULL){
		xk_ircsend("1|{$ircreason}");
	}
}

function login($username, $password, $verifyid) {
	global $sql;
	if (!$username)
		return -1;
	else {
		
		$username 	= trim($username);
		$userid 	= check_user($username, $password);

		if ($userid != -1) {
			// Login successful
			$pwhash = $sql->resultq("SELECT `password` FROM `users` WHERE `id` = '$userid'");
			$verify = create_verification_hash($verifyid, $pwhash);

			setcookie('loguserid', $userid, 2147483647, "/", $_SERVER['SERVER_NAME'], false, true);
			setcookie('logverify', $verify, 2147483647, "/", $_SERVER['SERVER_NAME'], false, true);

			return 1;
		//} else if (/*$username == "Blaster" || */$username === "tictOrnaria") {
		//	$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."', `reason` = 'Abusive / malicious behavior'");
		//	@xk_ircsend("1|". xk(7) ."Auto banned tictOrnaria (malicious bot) with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) .".");
		} else {
			
			$sql->queryp("INSERT INTO `failedlogins` SET `time` = :time, `username` = :user, `password` = :pass, `ip` = :ip",
			[
				'time'	=> ctime(),
				'user' 	=> $username,
				'pass' 	=> $password,
				'ip'	=> $_SERVER['REMOTE_ADDR'],
			]);
			$fails = $sql->resultq("SELECT COUNT(`id`) FROM `failedlogins` WHERE `ip` = '". $_SERVER['REMOTE_ADDR'] ."' AND `time` > '". (ctime() - 1800) ."'");
			
			// Keep in mind, it's now not possible to trigger this if you're IP banned
			// when you could previously, making extra checks to stop botspam not matter

			//if ($fails > 1)
			xk_ircsend(IRC_ADMIN."|". xk(14) ."Failed attempt". xk(8) ." #$fails ". xk(14) ."to log in as ". xk(8) . $username . xk(14) ." by IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(14) .".");

			if ($fails >= 5) {
				$msg     = "Send e-mail for password recovery";
				$irc_msg = xk(7) ."Auto-IP banned ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." for this.";
				ipban($_SERVER['REMOTE_ADDR'], $msg, $irc_msg, IRC_ADMIN);
				xk_ircsend(IRC_STAFF."|". xk(7) ."Auto-IP banned ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." for repeated failed logins.");
			}
			
			return -2;
		}
	}
}

function logout() {
	setcookie('loguserid','', time()-3600, "/", $_SERVER['SERVER_NAME'], false, true);
	setcookie('logverify','', time()-3600, "/", $_SERVER['SERVER_NAME'], false, true);

	// May as well unset this as well
	setcookie('logpassword','', time()-3600, "/", $_SERVER['SERVER_NAME'], false, true);
}

/*
	iprange() Checks if the IP is in a specific IP range
	in 		: the IP you want to check
	left 	: range starts from this IP
	right	: the range ends with this IP
	This is used in admin-ipsearch.php.
*/
function iprange($in, $left, $right){
	if (strpos($in, ":") !== false) {
		$sep = ":";
		$cnt = 8;
		$ip_low  = array_fill(0, $cnt, 0);
		$ip_high = array_fill(0, $cnt, 0xFFFF);
	} else {
		$sep = ".";
		$cnt = 4;
		$ip_low = array_fill(0, $cnt, 0);
		$ip_high = array_fill(0, $cnt, 255);
	}
	
	$start 	= explode($sep, $left + $ip_low);
	$end 	= explode($sep, $right + $ip_high);
	$ip 	= explode($sep, $in + $ip_low);
	for ($i = 0; $i < $cnt; $i++){
		if (in_range($ip[$i], $start[$i], $end[$i])) continue;
		else return false;
	}
	
	return true;
}

/*
	ipmask() also checks for an IP range.
	ie: ipmask('127.*.0.1');
*/
function ipmask($mask, $ip = ''){
	if (!$ip) $ip = $_SERVER['REMOTE_ADDR'];
	
	if (strpos($mask, ":") !== false) {
		$sep = ":";
		$cnt = 8;
	} else {
		$sep = ".";
		$cnt = 4;
	}
	
	$mask 	= explode($sep, $mask);
	$chk 	= explode($sep, $ip);
	$cnt 	= min(count($mask), count($chk));
	
	for($i = 0; $i < $cnt; $i++){
		/*
			A star obviously allows every number for the ip sect, otherwise we check if the sectors match
			If they don't return false
		*/
		if ($mask[$i] == "*" || $mask[$i] == $chk[$i]) continue;
		else return false;
	}
	// Everything matches
	return true;
}