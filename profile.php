<?php
	const POST_LIMIT    = 5000;
	const PROJECT_LIMIT = true;
	
	require 'lib/function.php';
	
	$_GET['id']		= filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);
	
	$user = $sql->fetchq("SELECT * FROM users WHERE id = {$_GET['id']}");
	if (!$user) {
		errorpage("The specified user doesn't exist.");
	}
	
	$windowtitle = "Profile for {$user['name']}";

//	if ($_GET['id'] == 1 && !$x_hacks['host']) {
//		pageheader();
//		print "<br><center><img src='http://earthboundcentral.com/wp-content/uploads/2009/01/m3deletede.png'></center><br>";
//		pagefooter();
//	}

	if ($loguser['id']) {
		$layoutblocked = $sql->resultq("SELECT 1 FROM blockedlayouts WHERE user = {$loguser['id']} AND blocked = {$_GET['id']}");
	}
	
	pageheader($windowtitle);
	
	$numdays = (ctime() - $user['regdate']) / 86400;
	$userlink = getuserlink($user, 0, '', true);	// With minipic

	// Also known as
	if ($user['aka'] && $user['aka'] != $user['name']) {
		$aka = htmlspecialchars($user['aka']);
	} else {
		$aka = NULL;
	}
	
	// Banned until
	if ($user['powerlevel'] == -1 && $user['ban_expire']) {
		$bantime = printdate($user['ban_expire'])." (".timeunits2($user['ban_expire']-ctime())." remaining)";
	} else {
		$bantime = NULL;
	}
	
	// Total posts
	if (isset($_GET['lol'])) {
		$user['posts'] = mt_rand(1562, 15702);
		$maxposts = $user['posts'] + mt_rand(200, $user['posts']);
	}
	$postavg 	= sprintf("%01.2f", $user['posts'] / $numdays);
	//// Projected date
	$projtext   = "";
	if ($user['posts']) {
		$topposts = POST_LIMIT * (PROJECT_LIMIT ? 1 : ceil($user['posts'] / POST_LIMIT));
		// regtime * remaining posts / posts
		$projdate = ctime() + (ctime() - $user['regdate']) * ($topposts - $user['posts']) / ($user['posts']);
		if (
			$projdate > ctime() && 
			$projdate < 2000000000 && 
			(!PROJECT_LIMIT || $user['posts'] < POST_LIMIT)
		) {
			$projtext = " -- Projected date for {$topposts} posts: ".printdate($projdate);
		}
	}
	//// Postbar
	if (!isset($_GET['lol'])) {
		$maxposts 		= $sql->resultq("SELECT MAX(posts) FROM users");
		if (!$maxposts) $maxposts = 1;
	}
	$bar = drawprogressbar(116, 8, $user['posts'], $maxposts, $barimg);
	
	// Total threads
	$threadsposted 	= $sql->resultq("SELECT COUNT(*) FROM threads WHERE user = {$_GET['id']}");
	
	// EXP
	$exp 		= calcexp($user['posts'], $numdays);
	$lvl 		= calclvl($exp);
	$expleft 	= calcexpleft($exp);
	$expstatus 	= "Level: {$lvl}<br>EXP: {$exp} (for next level: {$expleft})";
	if ($user['posts'] > 0) {
		$expstatus .= "<br>Gain: ".calcexpgainpost($user['posts'], $numdays)." EXP per post, ".calcexpgaintime($user['posts'],$numdays)." seconds to gain 1 EXP when idle";
	}
	
	// User rating
	$ratingstatus = NULL;
	if ($config['enable-ratings']) {
		// Ratings work based on the RPG level
		// higher your level is, more points are given
		
		$ratescore = 0;
		$ratetotal = 0;
		$ratings = $sql->query("
			SELECT r.rating, u.posts, u.regdate
			FROM userratings r
			LEFT JOIN users u ON r.userfrom = u.id
			WHERE r.userrated = {$_GET['id']}
		");
		$numvotes   = $sql->num_rows($ratings);
		while ($x = $sql->fetch($ratings)) {
			if ($x['posts'] < 0 || $x['regdate'] > ctime()) {
				$level = 1;
			} else {
				$level = calclvl(calcexp($x['posts'], (ctime() - $x['regdate']) / 86400));
			}
			$ratescore += $x['rating'] * $level;
			$ratetotal += $level;
		}
		if ($ratetotal) {
			$ratetotal *= 10;
			$ratingstatus = (floor($ratescore * 1000 / $ratetotal) / 100)." ({$ratescore}/{$ratetotal}, {$numvotes} votes)";
		} else {
			$ratingstatus = "None";
		}
	}
	
	// Last post
	if ($user['posts']) {
		$lastpostdate = printdate($user['lastposttime']);
		$postsfound = $sql->resultq("SELECT COUNT(*) FROM posts WHERE user = {$_GET['id']}");
		$post = $sql->fetchq("
			SELECT p.id, t.title ttitle, f.id fid, f.title ftitle, f.minpower
			FROM posts p
			INNER JOIN threads t ON p.thread = t.id
			INNER JOIN forums  f ON t.forum  = f.id
			WHERE p.user = {$_GET['id']} AND p.date = {$user['lastposttime']}
		");
		if (!$post || ($post['minpower'] && $post['minpower'] > $loguser['powerlevel'])) {
			$lastpostlink = ", in a restricted forum";
		} else {
			$threadtitle  = htmlspecialchars($post['ttitle']);
			$forumtitle   = htmlspecialchars($post['ftitle']);
			$lastpostlink = ", in <a href='thread.php?pid={$post['id']}#{$post['id']}'>{$threadtitle}</a> (<a href='forum.php?id={$post['fid']}'>{$forumtitle}</a>)";
		}
	} else {
		$lastpostdate = "None";
		$lastpostlink = "";
		$postsfound   = 0;
	}
	
	// Last activity (IP info)
	if ($isadmin && $user['lastip']) {
		$lastip = " <br>with IP: <a href='admin-ipsearch.php?ip={$user['lastip']}' style='font-style:italic;'>{$user['lastip']}</a>";
	}

	// Email address
	$email = urlencode(htmlspecialchars($user['email']));
	$email = "<a href=\"mailto:{$email}\">{$email}</a>";
	switch ($user['privateemail']) {
		case 0: break; // Public
		case 1:
			if (!$loguser['id']) $email = "Email witheld from guests. Log in to see it.";
			break;
		case 2:
			if (!$isadmin || $loguser['id'] != $_GET['id']) $email = "<i>Private</i>";
			break;
	}
	
	// Homepage
	$homepagename = ($user['homepagename'] ? htmlspecialchars($user['homepagename'])."</a> - " : "").htmlspecialchars($user['homepageurl']);	
	
	// ICQ
	if (!$user['icq']) {
		$user['icq'] = $icqicon = "";
	} else {
		$icqicon = "<a href=\"http://wwp.icq.com/{$user['icq']}#pager\"><img src=\"http://wwp.icq.com/scripts/online.dll?icq={$user['icq']}&img=5\" border=0></a>";
	}
	
	// AIM screen name
	if ($user['aim']) {
		$aimnum = str_replace(" ", "+", $user['aim']);
		$aimlink = "<a href=\"aim:goim?screenname=".htmlspecialchars($aimnum)."\">".htmlspecialchars($user['aim'])."</a>";
	} else {
		$aimlink = "";
	}
	
	//Timezone offset
	$tzoffset = $user['timezone'];
	$tzoffrel = $tzoffset - $loguser['timezone'];
	$tzdate   = date($loguser['dateformat'], ctime() + $tzoffset * 3600);
	
	// Birthday
	if ($user['birthday']) {
		$birthday = date("l, F j, Y", $user['birthday']);
		$age = "(".floor((ctime()-$user['birthday'])/86400/365.2425)." years old)";
	} else {
		$birthday = $age = "";
	}
	
	$profile = [
		'General information' => [
			#'Username'      => htmlspecialchars($user['name']),
			'Also known as'	=> $aka,
			'Banned until'  => $bantime,
			'Total posts'   => "{$user['posts']} ({$postsfound} found, {$postavg} per day) {$projtext}<br>{$bar}",
			'Total threads' => $threadsposted,
			'EXP'           => $expstatus,
			'User rating'   => $ratingstatus,
			'Registered on' => printdate($user['regdate'])." (".floor($numdays)." days ago)",
			'Last post'     => "{$lastpostdate}{$lastpostlink}",
			'Last activity' => printdate($user['lastactivity']).$lastip,	
		],
		
		'Contact information' => [
			'Email address'   => $email,
			'Homepage'        => "<a href=\"".htmlspecialchars($user['homepageurl'])."\">{$homepagename}</a>",
			'ICQ number'      => "{$user['icq']}{$icqicon}",
			'AIM screen name' => $aimlink,
		],
		
		'User settings' => [
			'Timezone offset' => "{$tzoffset} hours from the server, {$tzoffrel} hours from you (current time: {$tzdate})",
			'Items per page'  => "{$user['threadsperpage']} threads, {$user['postsperpage']} posts",
			'Color scheme'    => $sql->resultq("SELECT name FROM schemes WHERE id = {$user['scheme']}"),
		],
		
		'Personal information' => [
			'Real name' => htmlspecialchars($user['realname']),
			'Location'  => str_ireplace("&lt;br&gt;", "<br>", htmlspecialchars($user['location'])),
			'Birthday'  => "{$birthday} {$age}",
			'User bio'  => dofilters(doreplace2(doreplace($user['bio'], $user['posts'], $numdays, $_GET['id']))),
		],
	];
	
	/*
		Equipped items
	*/
	$shops 	= $sql->getresultsbykey("SELECT id, name FROM itemcateg");
	$equip  = getuseritems($_GET['id'], true);
	$shoplist = "";
	foreach ($shops as $shopid => $shopname) {
		$shoplist .= "
			<tr>
				<td class='tdbg1 fonts center'>{$shopname}</td>
				<td class='tdbg2 fonts center' style='width: 100%'>".filter_string($equip[$shopid]['name'])."&nbsp;</td>
			</tr>
		";
	}
	
	/*
		Post preview
	*/
	$data = array(
		// Text
		'message' => "Sample text. [quote=fhqwhgads]A sample quote, with a <a href=about:blank>link</a>, for testing your layout.[/quote]This is how your post will appear.",
		// Post metadata
		'forum'   => 0,
		// (mod) Options
		'nosmilies' => 0,
		'nohtml'    => 0,
		'nolayout'  => 0,
		'moodid'    => 0,
		'noob'      => 0,
		// Attachments
		'attach_key' => NULL,
		#'attach_sel' => "",
	);
	
	
/*
	Profile options
*/
	// Base
	$options = [
		0 => [
			"Show posts"                => ["thread.php?user={$_GET['id']}"],
			"View threads by this user" => ["forum.php?user={$_GET['id']}"],
		],
		1 => [
			"View forum bans to this user" => ["forumbansbyuser.php?id={$_GET['id']}"],
			"List posts by this user"      => ["forum.php?user={$_GET['id']}"],
			"List posts by this user"      => ["postsbyuser.php?id={$_GET['id']}"],
			"Posts by time of day"         => ["postsbytime.php?id={$_GET['id']}"],
			"Posts by thread"              => ["postsbythread.php?id={$_GET['id']}"],
			"Posts by forum"               => ["postsbyforum.php?id={$_GET['id']}"],
		],
	];
	if ($loguser['id']) {
		$token = generate_token(TOKEN_MGET);
		$un_b = ($layoutblocked ? "Unb" : "B");
		
		$options[0]["Send private message"] = ["sendprivate.php?userid={$_GET['id']}"];
		$options[0]["{$un_b}lock layout"]   = ["blocklayout.php?id={$_GET['id']}&action=block&auth={$token}"];
		if ($config['enable-ratings'] && $loguser['id'] != $_GET['id']) {
			$options[0]["Rate user"] = ["rateuser.php?id={$_GET['id']}"];
		}
		if ($isadmin) {
			$italic = "style='font-style: italic'";
			$options[2] = [
				"View private messages" => ["private.php?id={$_GET['id']}", $italic],
				"View favorites"        => ["forum.php?fav=1&user={$_GET['id']}", $italic],
				"View votes"            => ["rateuser.php?action=viewvotes&id={$_GET['id']}", $italic],
				"Edit user"             => ["editprofile.php?id={$_GET['id']}", $italic],
				"View blocked layouts"  => ["blocklayout.php?action=view&id={$_GET['id']}", $italic],
				"Nuke layout"           => ["blocklayout.php?action=nuke&id={$_GET['id']}", $italic],
			];
			
			if (!$config['enable-ratings']) {
				$options[2]['View votes'] = NULL;
			}
		}
	}
	if (
		( $config['allow-avatar-storage'] && $sql->resultq("SELECT COUNT(*) FROM users_avatars WHERE user = {$_GET['id']}")) ||
		(!$config['allow-avatar-storage'] && $user['moodurl'])
	) {
		$options[0]["Preview mood avatar"] = ["avatar.php?id={$_GET['id']}", "class='popout' target='_blank'"];	
	}
	
?>
<div>Profile for <?=$userlink?></div>
<table cellpadding=0 cellspacing=0 border=0>
	<tr>
		<td width=100% valign=top>
		<?= profile_table($profile, 'General information') ?>
		<br>
		<?= profile_table($profile, 'Contact information') ?>
		<br>
		<?= profile_table($profile, 'User settings') ?>
	</td>
	<td>&nbsp;&nbsp;&nbsp;</td>
	<td valign=top>
		<table class='table'>
			<tr><td class='tdbgh center'>RPG status</td></tr>
			<tr><td class='tdbg1'><img src='status.php?u=<?=$_GET['id']?>'></td></tr>
		</table>
		<br>
		<table class='table'>
			<tr><td class='tdbgh center' colspan=2>Equipped Items</td></tr>
			<?=$shoplist?>
		</table>
	</td>
</table>
<br>
<?= profile_table($profile, 'Personal information') ?>
<br>
<?= preview_post($user, $data, PREVIEW_PROFILE, "Sample post") ?>
<table class='table'>
	<tr><td class='tdbgh fonts center'>Options</td></tr>
	<?= profile_controls($options, 0) ?>
	<?= profile_controls($options, 1) ?>
	<?= profile_controls($options, 2) ?>
</table>
<?php

	pagefooter();
  
  
function profile_table($arr, $head) {
	$out = "";
	foreach ($arr[$head] as $field => $val) {
		if ($val !== NULL) {
			$out .= "<tr><td class='tdbg1 b' style='width: 150px'>{$field}</td><td class='tdbg2'>{$val}</td></tr>\r\n";
		}
	}
	return "
	<table class='table'>
		<tr><td class='tdbgh center' colspan=2>{$head}</td></tr>
		{$out}
	</table>";
}

function profile_controls($arr, $key, $colspan=1) {
	if (!isset($arr[$key])) { // Restricted set
		return "";
	}
	$out = "";
	foreach ($arr[$key] as $field => $val) {
		if ($val !== NULL) {
			$attr = isset($val[1]) ? " {$val[1]}" : "";
			$out .= ($out ? " | " : "")."<a href=\"{$val[0]}\"{$attr}>{$field}</a>\r\n";
		}
	}
	return "<tr><td class='tdbg2 fonts center' colspan={$colspan}>{$out}</td></tr>";
}