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
	global $sql, $loguser, $userfields;
	
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
	
	if (isset($u['aka']) && $u['aka']) {
		$akafield		= htmlspecialchars($u['aka']);
		$alsoKnownAs	= ($u['aka'] && $u['aka'] != $u['name']) ? " title=\"Also known as: {$akafield}\"" : '';
	} else {
		$alsoKnownAs 	= "";
	}
	
	$u['name'] 		= htmlspecialchars($u['name'], ENT_QUOTES);
	// Don't calculate birthday effect again
	if (isset($u['namecolor']) && $u['namecolor']) {
		if ($u['namecolor'] != 'rnbow' && is_birthday($u['birthday'])) {
			$u['namecolor'] = 'rnbow';
		}
	} else {
		$u['namecolor'] = "";
	}
	
	$namecolor  = getnamecolor($u['sex'], $u['group'], $u['namecolor']);
	$minipic    = ($useicon && isset($u['minipic']) && $u['minipic']) ? "<img width=16 height=16 src=\"".htmlspecialchars($u['minipic'], ENT_QUOTES)."\" align='absmiddle'> " : "";
	
	return "$minipic<a style='color:#{$namecolor}' class='{$urlclass} nobr' href='profile.php?id={$u['id']}'{$alsoKnownAs}>{$u['name']}</a>";
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
	global $sql, $loguser, $userfields;
	if (!$userid) return "";
	
	$race = '';

	$postradar = $sql->query("
		SELECT u.posts, $userfields
		FROM postradar p
		INNER JOIN users u ON p.comp = u.id
		WHERE p.user = $userid
		ORDER BY posts DESC
	");
	
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
			return "<span class='nobr'>{$t}</span>";
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

function fonlineusers($id){
	global $loguser, $sql, $userfields;

	if($loguser['id'])
		$sql->query("UPDATE users  SET lastforum = $id WHERE id = {$loguser['id']}");
	else
		$sql->query("UPDATE guests SET lastforum = $id WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");

	$forumname      = $sql->resultq("SELECT title FROM forums WHERE id = $id");
	$onlinetime     = ctime()-300;
	$onusers        = $sql->query("
		SELECT $userfields, hideactivity, (lastactivity <= $onlinetime) nologpost
		FROM users u
		WHERE lastactivity > $onlinetime 
		  AND lastforum = $id 
		  AND (".has_perm('show-hidden-user-activity')." OR !hideactivity)
		ORDER BY name
	");
	
	
	$onlineusers	= "";

	for($numon = 0; $onuser = $sql->fetch($onusers); ++$numon){
		
		if ($numon) $onlineusers .= ', ';

		/* if ((!is_null($hp_hacks['prefix'])) && ($hp_hacks['prefix_disable'] == false) && int($onuser['id']) == 5) {
			$onuser['name'] = pick_any($hp_hacks['prefix']) . " " . $onuser['name'];
		} */
		$onuser['minipic']	 = htmlspecialchars($onuser['minipic'], ENT_QUOTES);
		$namelink			 = getuserlink($onuser);
		
		if($onuser['nologpost']) // Was the user posting without using cookies?
			$namelink="($namelink)";
			
		if($onuser['hideactivity'])
			$namelink="[$namelink]";		
			
		if ($onuser['minipic'])
			$namelink = "<img width=16 height=16 src=\"".htmlspecialchars($onuser['minipic'])."\" align='absmiddle'> $namelink";
			
		$onlineusers .= $namelink;
	}
	
	$p = ($numon ? ':' : '.');
	$s = ($numon != 1 ? 's' : '');
	
	$guests = "";
	if (!has_perm('view-bpt-info')) {
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
		$guests = $numguests ? " | <span class='nobr'>$numguests guest".($numguests>1?"s":"").($guestcat ? " (".implode(",", $guestcat).")</span>" : "") : "";
	}
	
	return "$numon user$s currently in $forumname$p $onlineusers $guests";
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
