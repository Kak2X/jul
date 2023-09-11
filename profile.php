<?php
	const _POST_LIMIT    = 5000;
	const _PROJECT_LIMIT = true;
	
	require "lib/common.php";
	
	$_GET['id']     = filter_int($_GET['id']);
	$_GET['page']   = filter_int($_GET['page']);
	//$_GET['action'] = filter_string($_GET['action']);
	
	$user = $sql->fetchq("SELECT * FROM users WHERE id = {$_GET['id']}");
	if (!$user) {
		errorpage("The specified user doesn't exist.");
	}
	
	$windowtitle = "Profile for ".htmlspecialchars($user['name']);

//	if ($_GET['id'] == 1 && !$x_hacks['host']) {
//		pageheader();
//		print "<br><center><img src='http://earthboundcentral.com/wp-content/uploads/2009/01/m3deletede.png'></center><br>";
//		pagefooter();
//	}

	if ($loguser['id']) {
		$layoutblocked = $sql->resultq("SELECT 1 FROM blockedlayouts WHERE user = {$loguser['id']} AND blocked = {$_GET['id']}");
	}
	
	pageheader($windowtitle);
	
	$numdays = (time() - $user['regdate']) / 86400;
	$userlink = getuserlink($user, 0, '', true);	// With minipic

	// Also known as
	if ($user['aka'] && $user['aka'] != $user['name']) {
		$aka = htmlspecialchars($user['aka']);
	} else {
		$aka = NULL;
	}
	
	// Banned until
	if ($user['powerlevel'] == -1 && $user['ban_expire']) {
		$bantime = printdate($user['ban_expire'])." (".timeunits2($user['ban_expire']-time())." remaining)";
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
		$topposts = _POST_LIMIT * (_PROJECT_LIMIT ? 1 : ceil($user['posts'] / _POST_LIMIT));
		// regtime * remaining posts / posts
		$projdate = time() + (time() - $user['regdate']) * ($topposts - $user['posts']) / ($user['posts']);
		if (
			$projdate > time() && 
			$projdate < 2000000000 && 
			(!_PROJECT_LIMIT || $user['posts'] < _POST_LIMIT)
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
	
	// Warnings (Posts + PMs)
	$warncount = $sql->fetchq("
		SELECT 
		(SELECT COUNT(*) FROM posts WHERE warned > 0 AND user = {$_GET['id']}) post, 
		(SELECT COUNT(*) FROM pm_posts WHERE warned > 0 AND user = {$_GET['id']}) pm
	");
	$warnings = "";
	if ($warncount['post'])
		$warnings .= "<a href='thread.php?user={$_GET['id']}&warn=1'>{$warncount['post']}</a> post".($warncount['post'] == 1 ? "" : "s");
	if ($isadmin || $loguser['id'] == $_GET['id']) {
		if ($warncount['pm'])
			$warnings .= ($warnings ? ", " : "" )."{$warncount['pm']}</a> PM".($warncount['pm'] == 1 ? "" : "s");
		if ($warnings)
			$warnings = "<a href='postsbyuser.php?id={$_GET['id']}&pm=1&warn=1'>".array_sum($warncount)."</a> ({$warnings})";
	}
	if (!$warnings)
		$warnings = "None";
	
	// Highlights (Posts-only, it only matters in public)
	$hilicount = $sql->getresultsbykey("SELECT highlighted, COUNT(*) FROM posts WHERE highlighted > 0 AND user = {$_GET['id']} GROUP BY highlighted");
	$highlights = "";
	if (filter_int($hilicount[PHILI_LOCAL]))
		$highlights .= "{$hilicount[PHILI_LOCAL]} threadmarked</a>";
	if (filter_int($hilicount[PHILI_SUPER]))
		$highlights .= ($highlights ? ", " : "" )."<a href='thread.php?user={$_GET['id']}&hi=2'>{$hilicount[PHILI_SUPER]}</a> featured";
	if ($highlights)
		$highlights = "<a href='thread.php?user={$_GET['id']}&hi=1'>".array_sum($hilicount)."</a> ({$highlights})";
	else
		$highlights = "None";
	
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
			if ($x['posts'] < 0 || $x['regdate'] > time()) {
				$level = 1;
			} else {
				$level = calclvl(calcexp($x['posts'], (time() - $x['regdate']) / 86400));
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
			SELECT p.id, t.title ttitle, f.id fid, f.title ftitle, f.minpower, f.login
			FROM posts p
			INNER JOIN threads t ON p.thread = t.id
			INNER JOIN forums  f ON t.forum  = f.id
			WHERE p.user = {$_GET['id']} AND p.date = {$user['lastposttime']} 
		");
		if (!can_view_forum($post)) {
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
	$lastip = "";
	if ($isadmin && $user['lastip']) {
		$lastip = htmlspecialchars($user['lastip']);
		$lastip = " <br>with IP: <a href='admin-ipsearch.php?ip={$lastip}' style='font-style:italic;'>{$lastip}</a>";
	}
	
	// Last user agent
	$lastua = null;
	if ($isadmin) {
		$lastua = ($user['lastua'] ? htmlspecialchars($user['lastua']) : "<i>None</i>")." <a class='nobr' href='admin-useragents.php?id={$_GET['id']}'>(View History)</a>";
	}

	// Email address
	$email = "<a href=\"mailto:".urlencode($user['email'])."\">".htmlspecialchars($user['email'])."</a>";
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
	$tzdate   = date($loguser['dateformat'], time() + $tzoffset * 3600);
	
	// Birthday
	if ($user['birthday']) {
		$birthday = date("l, F j, Y", $user['birthday']);
		$age = "(".floor((time()-$user['birthday'])/86400/365.2425)." years old)";
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
			'Highlights received' => $highlights,
			'Warnings received'   => $warnings,			
			'Registered on' => printdate($user['regdate'])." (".floor($numdays)." days ago)",
			'Last post'     => "{$lastpostdate}{$lastpostlink}",
			'Last activity' => printdate($user['lastactivity']).$lastip,	
			'Last user agent' => $lastua,
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
			'Color scheme'    => htmlspecialchars($sql->resultq("SELECT name FROM schemes WHERE id = {$user['scheme']}")),
		],
		
		'Personal information' => [
			'Real name' => htmlspecialchars($user['realname']),
			'Location'  => str_ireplace("&lt;br&gt;", "<br>", htmlspecialchars($user['location'])),
			'Birthday'  => "{$birthday} {$age}",
			'User bio'  => dofilters(domarkup(replace_tags($user['bio'], get_tags($user)))),
		],
	];
	
	/*
		Custom profile fields
	*/
	if ($user['extrafields'] && ($custom = json_decode($user['extrafields'], true))) {
		foreach ($custom as $ctitle => $cvar) {
			$profile['Extra'][xssfilters($ctitle)] = nl2br(xssfilters($cvar));
		}
	}
	
	/*
		Equipped items
	*/
	$shops 	= $sql->getresultsbykey("SELECT id, name FROM itemcateg");
	$equip  = getuseritems($_GET['id'], true);
	$shoplist = "";
	foreach ($shops as $shopid => $shopname) {
		$shoplist .= "
			<tr>
				<td class='tdbg1 fonts center'>".htmlspecialchars($shopname)."</td>
				<td class='tdbg2 fonts center' style='width: 100%'>".htmlspecialchars(filter_string($equip[$shopid]['name']))."&nbsp;</td>
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
			"Show posts"                 => ["thread.php?user={$_GET['id']}"],
			"View threads by this user"  => ["forum.php?user={$_GET['id']}"],
			"View comments by this user" => ["usercomment.php?id={$_GET['id']}"],
			"View layout code"           => ["postlayouts.php?id={$_GET['id']}"],
			"View forum bans to this user" => ["forumbansbyuser.php?id={$_GET['id']}"],
		],
		1 => [
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
		
		$options[0]["Send private message"] = ["newpmthread.php?userid={$_GET['id']}"];
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
	hook_use_ref('profile-options', $options);
	
	
	/*
		Profile comments
	*/
	$ppp = get_ppp();
	$comments = $sql->query("
		SELECT c.id cid, c.userfrom, c.date, c.text, c.`read`, $userfields
		FROM users_comments c
		LEFT JOIN users u ON c.userfrom = u.id
		WHERE c.userto = {$_GET['id']}
		ORDER BY c.id DESC
		LIMIT ".($_GET['page'] * $ppp).", $ppp
	");
	$total      = $sql->resultq("SELECT COUNT(id) FROM users_comments WHERE userto = {$_GET['id']}");
	$pagelinks  = pagelist("usercomment.php?id={$_GET['id']}&ppp={$ppp}&to", $total, $ppp);
	if ($pagelinks)
		$pagelinks = "<tr><td class='tdbg2' colspan=4>{$pagelinks}</td></tr>";
	
	// Comment list
	
	$comm_txt = "";
	$unmark   = array();
	$i = 0;
	
	if (!$sql->num_rows($comments)) {
		$comm_txt = "<tr><td class='tdbg1 center' colspan=4><i>There are no profile comments for this user.</i></td></tr>";
	} else while ($x = $sql->fetch($comments)){
		$dellink = $isadmin ? "<a href='usercomment.php?act=del&id={$x['cid']}&auth={$token}'>Remove</a>" : $x['cid'];
		//--
		if (!$x['read'] && $_GET['id'] == $loguser['id']) {
			$newmark = $statusicons['new'];
			$unmark[] = $x['cid'];
		} else {
			$newmark = "";
		}
		//--
		$cell = ($i++ % 2) + 1;
		$comm_txt .= "
			<tr>
				<td class='tdbg{$cell} center' style='width: 1px'>{$newmark}</td>
				<td class='tdbg{$cell} center nobr' style='width: 60px'>{$dellink}</td>
				<td class='tdbg{$cell} center nobr' style='width: 150px'>".printdate($x['date'])."</td>
				<td class='tdbg{$cell}'>".getuserlink($x).": ".dofilters(domarkup($x['text']))."</td>
			</tr>";
	}
	
	if ($unmark) {
		$sql->query("UPDATE users_comments SET `read` = 1 WHERE id IN (".implode(',', $unmark).")");
	}
	
	// New comment link
	if (!$user['comments']) {
		$comm_new = "This user has profile comments disabled.";
	} else if (!$loguser['id']) {
		$comm_new = "You must be logged in to add a comment for this user.";
	} else if ($banned) {
		$comm_new = "Banned users aren't allowed to add profile comments.";
	} else {
		$comm_new = "
		<form method='POST' action='usercomment.php?act=add&id={$_GET['id']}'>
			".getuserlink($loguser).":&nbsp;<input type='text' name='text' style='width: 800px; height: 16px; vertical-align: middle'> <input type='submit' class='fonts' name='add' value='Add comment'>
			".auth_tag()."
		</form>
		";
	}
	
?>
<div>Profile for <?=$userlink?></div>
<table cellpadding=0 cellspacing=0 border=0>
	<tr>
		<td width=100% valign=top>
		<?= _profile_table($profile, 'General information') ?>
		<br>
		<?= _profile_table($profile, 'Contact information') ?>
		<br>
		<?= _profile_table($profile, 'User settings') ?>
		<br>
		<?= _profile_table($profile, 'Personal information') ?>	
		<br>
		<?= isset($profile['Extra']) ? _profile_table($profile, 'Extra') : "" ?>	
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
		<br>
		<?= hook_print('profile-table-mini', "", $user) ?>
	</td>
</table>
<br>
<?= preview_post($user, $data, PREVIEW_PROFILE, "Sample post") ?>
<br>
<table class='table fonts' id='comments'>
	<tr><td class='tdbgh center' colspan=4>Profile Comments</td></tr>
	<?= $comm_txt ?>
	<?= $pagelinks ?>
	<tr><td class='tdbg2' colspan=4><?= $comm_new ?></td></tr>
</table>
<br>
<table class='table'>
	<tr><td class='tdbgh fonts center'>Options</td></tr>
	<?= _profile_controls($options, 0) ?>
	<?= _profile_controls($options, 1) ?>
	<?= _profile_controls($options, 2) ?>
</table>
<?php

	pagefooter();
  
  
function _profile_table($arr, $head) {
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

function _profile_controls($arr, $key, $colspan=1) {
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