<?php
	if (substr(php_sapi_name(), 0, 3) != 'cli') {
		die("Command-line only.");
	}
	chdir("..");
	if (!file_exists("lib/config.php"))
		die("The board must be installed first.");
	
	require "lib/function.php";
	require "install/setup_schema.php";
	require "lib/config.php";
	
	set_error_handler('error_reporter');
	register_shutdown_function(function() {
		global $errors;
		$log = "Errors: ".print_r($errors, true)."\r\n=========================\r\nQueries: ".print_r(mysql::$debug_list, true);
		file_put_contents("temp/abxdin.log", $log);
	});
	$prefix = "abxd_";
	
	$sqlsrc = new mysql;
	$sqlsrc->connect("localhost", "root", "", "yukina_abxd") or die("Failed to connect to input server.");
	$sqlsrc->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
	
	$sqldest = new mysql;
	$sqldest->connect($sqlhost, $sqluser, $sqlpass, $dbname) or die("Failed to connect to output server.");
	
	print "Connection OK.\r\n";
	map_smilies();
	map_1to1("blockedlayouts", "blockedlayouts", ['user','blockee'=>'blocked']);
	map_1to1("categories", "categories", ['id','name','corder']);
	map_1to1("forummods", "forummods", ['forum','user']);
	map_1to1("forums", "forums", ['id','title','description','catid','minpower','minpowerthread','minpowerreply','numthreads','numposts','lastpostdate','lastpostuser','lastpostid','hidden','forder']);
	map_1to1("ipbans", "ipbans", ['ip','reason','date']);
	map_1to1("misc", "misc", ['views','hotcount','maxusers','maxusersdate','maxuserstext','maxpostsday','maxpostsdaydate','maxpostshour','maxpostshourdate']);
	map_settings();
	map_1to1("moodavatars", "users_avatars", ['id','uid'=>'user','mid' => 'file','name'=>'title']);
	map_1to1("poll", "poll", ['id','question','briefing','closed','doublevote']);
	map_1to1("pollvotes", "pollvotes", ['user','choiceid'=>'choice','poll']);
	map_1to1("poll_choices", "poll_choices", ['id','poll','choice','color']);
	
	$layouts_user = map_postlayouts();
	map_pms();
	map_posts();
	
	map_1to1("threads", "threads", ['id','forum','user','views','title','description','COALESCE(icon,"")' => 'icon','replies','lastpostdate','lastposter','closed','sticky','poll']);
	map_threadsread();
	map_1to1("usercomments", "users_comments", ['id','uid'=>'userto','cid'=>'userfrom','text','date'],",`read`=1");
	map_users();

	// Last round
	$sqldest->query("UPDATE threads SET icon = REPLACE(icon, 'img/icons/', 'images/icons_custom/') WHERE icon IS NOT NULL");
	$sqldest->query("UPDATE pm_threads SET icon = REPLACE(icon, 'img/icons/', 'images/icons_custom/') WHERE icon IS NOT NULL");

	
// 1 to 1 mapping
function map_1to1($src_table, $dest_table, $field_map, $heh = "") {
	global $sqldest, $sqlsrc, $prefix;
	
	print "Mapping $src_table to $dest_table\r\n";
	
	// field = ?, field2 = ?, ...
	$src_fields = "";
	$dest_fields = "";
	foreach ($field_map as $fsrc => $fdest) {
		// If the field is numeric (no real key), use the same field name across source/dest
		if (is_int($fsrc))
			$fsrc = $fdest;
		$src_fields .= ($src_fields ? "," : "").(strpos($fsrc, '(') !== false ? $fsrc : "`$fsrc`");
		$dest_fields .= ($dest_fields ? "," : "")."`$fdest` = ?";
	}
	
	// the 1-1 mapping with manually selecting the fields means we can feed $x directly to execute()
	$sqldest->query("TRUNCATE `$dest_table`");
	$q = $sqldest->prepare("INSERT INTO `$dest_table` SET $dest_fields$heh");
	foreach ($sqlsrc->query("SELECT $src_fields FROM `$prefix$src_table`") as $x) {
		$sqldest->execute($q, array_values($x));
	}
}
	
function map_postlayouts() {
	global $sqlsrc, $sqldest, $prefix;
	print "Mapping Post Layouts\r\n";
	$res = [];
	$sqldest->query("TRUNCATE postlayouts");
	$q = $sqldest->prepare("INSERT INTO postlayouts SET text = ?");
	foreach ($sqlsrc->query("SELECT id, postheader, signature FROM {$prefix}users") as $x) {
		if ($x['postheader']) {
			$sqldest->execute($q, [$x['postheader']]);
			$idhead = $sqldest->insert_id();
		} else $idhead = 0;
		if ($x['signature']) {
			$sqldest->execute($q, [$x['signature']]);
			$idsign = $sqldest->insert_id();
		} else $idsign = 0;
		$res[$x['id']] = [$idhead, $idsign];
	}
	return $res;
}
	
function map_pms() {
	global $sqlsrc, $sqldest, $layouts_user, $prefix;
	print "Mapping PMs\r\n";
	
	// query adapted from Arisotura's AB2.064 pmthreadgen.php
	// thank you for for doing it in 2016 so I didn't have to :D	
	$pms = $sqlsrc->query("
		SELECT *,
		TRIM(IF(SUBSTR(title,1,3)='Re*', SUBSTR(title,LOCATE(':',title)+1), REPLACE(title,'Re: ',''))) strippedtitle,  
		CAST(IF(SUBSTR(title,1,3)='Re*', SUBSTR(title,4,LOCATE(':',title)-4), IF(SUBSTR(title,1,12)='Re: Re: Re:', 3, IF(SUBSTR(title,1,8)='Re: Re: ', 2, IF(SUBSTR(title,1,4)='Re: ', 1, 0)))) AS UNSIGNED) replylevel
		FROM {$prefix}pmsgs p
		INNER JOIN {$prefix}pmsgs_text pt ON pt.pid=p.id
		ORDER BY drafting DESC, strippedtitle ASC, date ASC
	");
	
	// Generate posts + additional data for generating the thread
	$last_key = null;
	$tid = 0; // thread id - 1
	
	$msg_read = [];
	$threadinfo = [];
	$pm_drafts = null;
	foreach ($pms as $pm) {
		
		// Drafts are all thrown at the end, and all go in their own private containment thread
		if ($pm['drafting']) {
			if ($pm_drafts === null) {
				$tid = count($threadinfo);
				$threadinfo[$tid] = ['key' => ['### DRAFTS ###'], 'userto' => $pm['userfrom'], 'posts' => []];
				$pm_drafts = true;
			}
			
		// Group by title and userfrom/userto combo
		// If it matches the last key, reuse it.
		} else if (!same_key($last_key, $pm)) {
			
			// Otherwise detect if it matches an existing thread key.
			// We do this in case the same thread text is reused for multiple PMs between different users all mixed in.
			// (unlikely, but better safe than sorry)
			$found = false;
			for ($tid = 0; $tid < count($threadinfo); ++$tid) {
				if (same_key($threadinfo[$tid]['key'], $pm)) {
					$last_key = $threadinfo[$tid]['key'];
					$found = true;
					break;
				}
			}
			
			// If not, create a new one
			if (!$found) {
				if (isset($threadinfo[$tid])) die("LOGIC ERROR");
				//++$tid;
				$last_key = make_key($pm); // includes the title
				$threadinfo[$tid] = ['key' => $last_key, 'userto' => $pm['userto'], 'posts' => []];
			}
		}
		
		// Convert [reply=""] tag to [quote=]
		$pm['text'] = preg_replace("'\[reply=\"(.*?)\"]'si", '[quote=\\1]', $pm['text']);
		$pm['text'] = str_replace('[/reply]', '[/quote]', $pm['text']);
		
		// Add the reply...
		$threadinfo[$tid]['posts'][] = [
			'id' => $pm['id'],
			'thread' => $tid + 1,
			'user' => $pm['userfrom'],
			'date' => $pm['date'],
			'ip' => $pm['ip'],
			'deleted' => min($pm['deleted'], 1),
			'deletedby' => $pm['deleted'] ? $pm['userfrom'] : 0,
			'headid' => filter_int($layouts_user[$pm['userfrom']][0]),
			'signid' => filter_int($layouts_user[$pm['userfrom']][1]),
			'text' => $pm['text'],
		];
		// This goes elsewhere, it's not part of the query
		$msg_read[$pm['id']] = $pm['msgread'];
	}
	
	$sqldest->query("TRUNCATE pm_threads");
	$sqldest->query("TRUNCATE pm_posts");
	$sqldest->query("TRUNCATE pm_access");
	$sqldest->query("TRUNCATE pm_threadsread");
	
	$q_pmthread = $sqldest->prepare("INSERT INTO pm_threads (id,user,title,firstpostdate,lastposter,lastpostdate,replies) VALUES (?,?,?,?,?,?,?)");
	$q_pmpost = $sqldest->prepare("INSERT INTO pm_posts (id,thread,user,date,ip,deleted,deletedby,headid,signid,text) VALUES (?,?,?,?,?,?,?,?,?,?)");
	$q_pmacl = $sqldest->prepare("INSERT INTO pm_access (thread,user) VALUES (?,?)");
	$q_pmread = $sqldest->prepare("INSERT INTO pm_threadsread (uid, tid, time, `read`) VALUES (?,?,?,?)");

	// Build the threads
	foreach ($threadinfo as $t) {
		// Links to first/last post
		$fp = $t['posts'][0];
		$lp = $t['posts'][count($t['posts'])-1];
		
		$user1 = $fp['user'];
		$user2 = $t['userto'];
		
		$lastread1 = $fp['date']; // User 1 - Last read post
		$lastpost1 = null; // User 1 - Last post date
		$lastread2 = null;
		$lastpost2 = null;
		
		$sqldest->execute($q_pmthread, [$fp['thread'], $fp['user'], $t['key'][0], $fp['date'], $lp['user'], $lp['date'], count($t['posts'])-1]);
		
		foreach ($t['posts'] as $p) {
			//--
			// also determine lastread while we're here.
			// because of how the original PM system worked, the 'read' in a post made by the sender marked if a post has been read by the target.
			// that's the reasoning behind the "opposite" thing
			if ($p['user'] == $user2) {
				$lastpost2 = $p['date']; // straight
				if ($msg_read[$p['id']])
					$lastread1 = $p['date']; // opposite
			} else {
				$lastpost1 = $p['date'];
				if ($msg_read[$p['id']])
					$lastread2 = $p['date'];
			}
			//--
			$sqldest->execute($q_pmpost, array_values($p)); //[$p['id'], $post['thread'], $p['user'], $p['date'], $p['ip'], $p['deleted'], $p['headid'], $p['signid'], $p['text']]);
		}
		$sqldest->execute($q_pmacl, [$fp['thread'], $fp['user']]);
		if ($fp['user'] != $t['userto'])
			$sqldest->execute($q_pmacl, [$fp['thread'], $t['userto']]);
		
		$sqldest->execute($q_pmread, [$user1, $fp['thread'], $lastread1+1, (int)($lastread1 >= $lastpost1)]);
		if ($lastread2)
			$sqldest->execute($q_pmread, [$user2, $fp['thread'], $lastread2+1, (int)($lastread2 >= $lastpost2)]);
	}
	

}
function same_key($key, $pm) {
	return $key != null && $pm['strippedtitle'] == $key[0] && (($pm['userfrom'] == $key[1] && $pm['userto'] == $key[2]) || ($pm['userto'] == $key[1] && $pm['userfrom'] == $key[2]));
}
function make_key($pm) {
	return [$pm['strippedtitle'], $pm['userfrom'], $pm['userto']];
}
	
function map_posts() {
	global $sqlsrc, $sqldest, $layouts_user, $prefix;
	print "Mapping Posts\r\n";
	
	// better than blackhole89's version since currentrevision is a tracked field here for simpler queries, but still, no
	// I want my posts_old
	$badposts = $sqlsrc->query("
		SELECT	p.`id`, p.`thread`, p.`user`, p.`date`, p.`ip`, p.`num`, p.`deleted`, p.`deletedby`, p.`reason`, p.`options`, p.`mood`, p.currentrevision currev,
				pt.text, pt.revision rev, pt.user revuser, pt.date revdate
		FROM {$prefix}posts p
		INNER JOIN {$prefix}posts_text pt ON pt.pid = p.id
		ORDER BY p.id ASC, pt.revision ASC
	");
	$sqldest->query("TRUNCATE posts");
	$sqldest->query("TRUNCATE posts_old");
		
	$q_posts = $sqldest->prepare("
		INSERT INTO posts SET id=?,thread=?,user=?,date=?,ip=?,num=?,moodid=?,
		text=?,
		nosmilies=?,headid=?,signid=?,
		editedby=?,editdate=?,deleted=?,deletedby=?,deletereason=?,revision=?");
	$q_posts_old = $sqldest->prepare("
		INSERT INTO posts_old SET pid=?,
		text=?,
		headid=?,signid=?,
		revuser=?,revdate=?,revision=?");	

	foreach ($badposts as $x) {
		$nolayout = $x['options'] & 1;
		$nosmilies = $x['options'] & 2;
		if (!$nolayout) {
			$headid = filter_int($layouts_user[$x['user']][0]); 
			$signid = filter_int($layouts_user[$x['user']][1]);
		} else {
			$headid = $signid = 0;
		}
			
		if ($x['currev'] == $x['rev']) {
			if ($x['currev']) { // > 0
				$revuser = $x['revuser'];
				$revdate = $x['revdate'];
			} else {
				$revuser = $revdate = null;
			}
			
			$sqldest->execute($q_posts, [
				$x['id'], $x['thread'], $x['user'], $x['date'],$x['ip'],$x['num'],$x['mood'],
				$x['text'],
				$nosmilies, $headid, $signid,
				$revuser, $revdate, $x['deleted'],$x['deletedby'],$x['reason'],$x['rev']
			]);
		} else {
			$sqldest->execute($q_posts_old, [
				$x['id'],
				$x['text'],
				$headid, $signid,
				$x['revuser'], $x['revdate'], $x['rev']
			]);
		}
	}
}

function map_threadsread() {
	global $sqlsrc, $sqldest, $prefix;
	print "Mapping Threadsread\r\n";
	
	$sqldest->query("TRUNCATE threadsread");
	$data = $sqlsrc->query("
		SELECT r.id, r.thread, r.date, IF(r.date >= t.lastpostdate, 1, 0) `read`
		FROM {$prefix}threadsread r
		LEFT JOIN {$prefix}threads t ON r.thread = t.id
	");
	$q = $sqldest->prepare("INSERT INTO threadsread SET uid=?,tid=?,time=?,`read`=?");	
	foreach ($data as $x)
		$sqldest->execute($q, array_values($x));
}

function map_users() {
	global $sqlsrc, $sqldest, $prefix;
	print "Mapping Users\r\n";
	$sqldest->query("TRUNCATE users");
	$sqldest->query("TRUNCATE users_rpg");
	$data = $sqlsrc->query("SELECT * FROM {$prefix}users");
	$q_users = $sqldest->prepare("INSERT INTO users SET 
	id=?,posts=?,regdate=?,name=?,displayname=?,password=?,
	postheader=?,signature=?,bio=?,
	powerlevel=?,powerlevel_prev=?,sex=?,namecolor=?,namecolor_bak=?,
	title=?,realname=?,location=?,birthday=?,email=?,privateemail=?,homepageurl=?,homepagename=?,
	lastposttime=?,lastactivity=?,lastip=?,lastua=?,lasturl=?,lastforum=?,
	postsperpage=?,threadsperpage=?,timezone=?,viewsig=?,signsep=?,
	profile_locked=?,editing_locked=?,uploads_locked=?,uploader_locked=?,avatar_locked=?,
	dateformat=?,dateshort=?,ban_expire=?,ajax=1");	
	$q_usersrpg = $sqldest->prepare("INSERT INTO `users_rpg` (`uid`) VALUES (?)");
	foreach ($data as $x) {
		$pass = rand_str(mt_rand(9, 15));
		$forbidden = explode(" ", $x['forbiddens']);
		if (!$forbidden) $forbidden = []; // just in case of explode returning false
		
		print "[USER] Temporary password for user #{$x['id']} ({$x['name']}) is \"{$pass}\"\r\n";
		$sqldest->execute($q_users, [
			$x['id'],$x['posts'],$x['regdate'],$x['name'],$x['displayname'],getpwhash($pass, $x['id']),
			$x['postheader'],$x['signature'],$x['bio'],
			$x['powerlevel'],$x['tempbanpl'],$x['sex'],$x['color'],$x['color'],
			$x['title'],$x['realname'],$x['location'],$x['birthday'],$x['email'],$x['showemail'] ? 0 : 2,$x['homepageurl'],$x['homepagename'],
			$x['lastposttime'],$x['lastactivity'],$x['lastip'],$x['lastknownbrowser'],$x['lasturl'],$x['lastforum'],
			$x['postsperpage'],$x['threadsperpage'],floor($x['timezone']/3600),$x['blocklayouts'],$x['signsep'] ? 3 : 1,
			in_array("editProfile", $forbidden),in_array("editPost", $forbidden),in_array("useUploader", $forbidden),in_array("useUploader", $forbidden),in_array("editProfile", $forbidden),
			$x['dateformat']." ".$x['timeformat'],$x['dateformat'],$x['tempbantime']
		]);
		$sqldest->execute($q_usersrpg, [$x['id']]);
		
	}
}

function rand_str($len) {
	static $set = "QWERTYUIOPASDFGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm1234567890";
	$r   = "";
	for ($i = 0, $m = strlen($set) - 1; $i < $len; ++$i) {
		$c = mt_rand(0, $m);
		$r .= $set[$c];
	}
	return $r;
}

function map_smilies() {
	global $sqlsrc, $prefix;
	print "Mapping smilies to smilies.dat\r\n";
	
	$res = $sqlsrc->getarray("
		SELECT code, CONCAT('images/smilies/', image) 
		FROM `{$prefix}smilies`
	");
	if (!file_exists("smilies.bak"))
		copy("smilies.dat", "smilies.bak");
	writesmilies($res);
}

// from admin-editresources
function writesmilies($res) {
	$err = "";
	$h = fopen('smilies.dat', 'w');
	foreach ($res as $row) {
		if ($row && !fputcsv($h, $row, ',')) {
			$err .= "<br>{$row[0]}";
		}
	}
	fclose($h);
	return $err;
}

function map_settings() {
	global $sqlsrc, $sqldest, $prefix, $config;
	print "Mapping settings\r\n";
	
	$settings = $sqlsrc->getresultsbykey("SELECT CONCAT(plugin,'.',name), value FROM {$prefix}settings");
	 
	// misc
	$data = [
		'attntitle' => str_replace(["<br>","<br/>"], "", $settings['main.PoRATitle']),
		'attntext' => str_replace(["<br>","<br/>"], "", $settings['main.PoRAText']),
		'regmode' => $settings['main.registrationWord'] ? 3 : 0,
		'regcode' => $settings['main.registrationWord'],
		'irc_enable' => __($settings['ircreport.host']) && __($settings['ircreport.port']),
	];
	$sqldest->queryp("UPDATE misc SET ".mysql::setplaceholders($data), $data);
	
	// irc reporting
	if ($data['irc_enable']) {
		$data = [
			'server' => $settings['ircpage.server'],
			'port' => $settings['ircreport.port'],
			'opnick' => $sqlsrc->resultq("SELECT name FROM {$prefix}users ORDER BY id LIMIT 1"),
		];
		$sqldest->queryp("UPDATE irc_settings SET ".mysql::setplaceholders($data), $data);
		$sqldest->queryp("UPDATE irc_channels SET name=? WHERE id=".IRC_MAIN, [$settings['ircpage.channel']]);
		
		$config['irc-server-title'] = $settings['ircpage.server'];
		$config['irc-servers'] = [$settings['ircpage.server']];
		$config['irc-channels'] = [$settings['ircpage.channel']];
	}

	// faq.dat	
	if ($settings['faq.faq']) {
		file_put_contents("faq.dat", $settings['faq.faq']);
	}
	
	// misc config
	$config['board-name'] = $settings['main.boardname'];
	$config['trash-forum'] = $settings['main.trashForum'];
	$config['announcement-forum'] = 0;
	$config['deleted-user-id'] = $sqldest->resultq("SELECT COUNT(*)+2 FROM users"); // Leave space for the next registration
	file_put_contents("lib/config.php", setup_generate_config());
}