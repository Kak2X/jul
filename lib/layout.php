<?php
	
/*
	if (!$x_hacks['host']) {
		if ($loguserid == 1) $config['board-title']	= "";

		$autobancount = $sql->fetchq("SELECT COUNT(*) AS cnt, MAX(`date`) as time FROM `ipbans` WHERE `reason` LIKE 'Autoban'", MYSQL_ASSOC);
		$totalbancount = $sql->fetchq("SELECT COUNT(*) AS cnt, MAX(`date`) as time FROM `ipbans`", MYSQL_ASSOC);

		$config['board-title']	.= "<br><font class=font color=#ff0000><b>If you got banned, PM an admin for a password change</b></font><br><font class=fonts>". $autobancount['cnt'] ." automatic IP bans have been issued, last ". timeunits2(time() - $autobancount['time']) ." ago"
			."<br>". $totalbancount['cnt'] ." IP bans have been issued in total, last ". timeunits2(time() - $totalbancount['time']) ." ago";
	
		$config['board-title']= "<span style='font-size: 40pt; font-variant: small-caps; color: #f33;'>The Hivemind Collective</span><br><span style='font-size: 6pt; font-variant: small-caps; color: #c00'>(because a group of friends sharing a similar opinion is totally hivemind, dood!)</span>";
	}
*/

#	if (!$x_hacks['host'] && true) {
#		$config['board-title']	.= "</a><br><a href='/thread.php?id=10372'><span style='font-size: 14px;'>Now with more celebrations!</span></a>";
#	}

/*
	if (!$x_hacks['host'])
		$config['board-title']	.= "</a><br><a href='/thread.php?id=9218'><span style='color: #f00; font-weight: bold;'>Security notice for certain users, please read and see if you are affected</span></a>";

	if ($loguser['id'] >= 1 && false) {
		$numdir2	= $numdir;
		$numdir		= "num3/";

		$votetu		= max(0, 1000000 - floor((mktime(15, 0, 0, 7, 22, 2009) - microtime(true)) * (1000000 / 86400)));

		$votetally	= max(0, $votetu / (1000000));

		$votepct2	= floor($votetu * 1);			// no decimal point, so x100 for added precision
		$votepctm	= 5;									// width of the bar
		$votepct	= floor($votetally * 100 * $votepctm);
//		$config['board-title']	.= "</a><br><a href='/thread.php?id=5710'><span style='color: #f22; font-size: 14px;'>". generatenumbergfx($votetu ."/1000000", 2) ." <img src='numgfx/num3/barleft.png'><img src='numgfx/num3/bar-on.png' height='8' width='". ($votepct) ."'><img src='numgfx/num3/bar-off.png' height='8' width='". (100 * $votepctm - $votepct) ."'><img src='numgfx/num3/barright.png'></span></a>";
		$numdir		= $numdir2;
		$cycler		= str_replace("color=", "#", getnamecolor(0, 0));
		$config['board-title']	.= "</a><br><a href='/thread.php?id=5866'><span style='color: $cycler; font-size: 14px;'>Mosts Results posted. Go view.</span></a>";
	} */
	
//$config['board-title'] = "<a href='./'><img src=\"images/christmas-banner-blackroseII.png\" title=\"Not even Christmas in July, no. It's May.\"></a>";

// PONIES!!!
// if($forumid==30) $config['board-title'] = "<a href='./'><img src=\"images/poniecentral.gif\" title=\"YAAAAAAAAAAY\"></a>";
// end PONIES!!!

function load_layout($forcescheme = NULL, $forcetitle = NULL) {
	global 	$sql, $config, $x_hacks, $loguser, $miscdata, $pwlnames, // globals - external
			$nmcol, $statusicons, $numdir, $numfil, $barimg, $numcols, // globals - created
			$favicon, $schemepre, $css, $body_extra, $schemerow, // for pageheader only
			$newpollpic, $newreplypic, $newthreadpic, $closedpic, $nopollpic, $poweredbypic // thread pics
			;
			
	/*
		Default color scheme
	*/
			
	$nmcol = [
		0 => ['-2' => '6a6a6a', '-1' => '888888', '97ACEF', 'D8E8FE', 'AFFABE', 'FFEA95'],
		1 => ['-2' => '767676', '-1' => '888888', 'F185C9', 'FFB3F3', 'C762F2', 'C53A9E'],
		2 => ['-2' => '767676', '-1' => '888888', '7C60B0', 'EEB9BA', '47B53C', 'F0C413']
	];
	
	$linkcolor	='FFD040';
	$linkcolor2 ='F0A020';
	$linkcolor3 ='FFEA00';
	$linkcolor4 ='FFFFFF';
	$textcolor	='E0E0E0';
	$tableheadtext = "";

	$font	= 'Verdana, Geneva, sans-serif';
	$font2	= 'Verdana, Geneva, sans-serif';
	$font3	= 'Tahoma, Verdana, Geneva, sans-serif';

	$newpollpic		= '<img class="pixel" src="schemes/default/status/newpoll.png" alt="New poll" align="absmiddle">';
	$newreplypic	= '<img class="pixel" src="schemes/default/status/newreply.png" alt="New reply" align="absmiddle">';
	$newthreadpic	= '<img class="pixel" src="schemes/default/status/newthread.png" alt="New thread" align="absmiddle">';
	$closedpic		= '<img class="pixel" src="schemes/default/status/threadclosed.png" alt="Thread closed" align="absmiddle">';
	$nopollpic      = '<img class="pixel" src="schemes/default/status/nopolls.png" alt="No more fucking polls" align="absmiddle">';
	$poweredbypic   = '<img class="pixel" src="images/poweredbyacmlm.gif">';
	$numdir			= 'jul/';

	$statusicons = [
		'new'			=> '<img class="pixel" src="schemes/default/status/new.gif">',
		'newhot'		=> '<img class="pixel" src="schemes/default/status/hotnew.gif">',
		'newoff'		=> '<img class="pixel" src="schemes/default/status/off.gif">',
		'newhotoff'		=> '<img class="pixel" src="schemes/default/status/hotoff.gif">',
		'hot'			=> '<img class="pixel" src="schemes/default/status/hot.gif">',
		'hotoff'		=> '<img class="pixel" src="schemes/default/status/hotoff.gif">',
		'off'			=> '<img class="pixel" src="schemes/default/status/off.gif">',

		'getnew'		=> '<img class="pixel" src="schemes/default/status/getnew.png" title="Go to new posts" align="absmiddle">',
		'getlast'		=> '<img class="pixel" src="schemes/default/status/getlast.png" title="Go to last post" style="position:relative;top:1px">',

		'sticky'		=> 'Sticky:',
		'poll'			=> 'Poll:',
		'stickypoll'	=> 'Sticky poll:',
		'ann'			=> 'Announcement:',
		'annsticky'		=> 'Announcement - Sticky:',
		'annpoll'		=> 'Announcement - Poll:',
		'annsticky' 	=> 'Announcement - Sticky:',
		'annpoll'		=> 'Announcement - Poll:',
		'annstickypoll'	=> 'Announcement - Sticky poll:',
	];
	//$schemetime  = -1; // mktime(9, 0, 0) - time();
	$numfil      = 'numnes';
	$numcols     = 60;
	$nullscheme  = 0;
	$schemetype  = 0;
	$formcss     = 0;
	$usebtn      = 0;
	$schemepre   = isset($_GET['scheme']);
	$isChristmas = (date('n') == 12);
	$css_extra = $body_extra = "";
	
	// Favicon, now here so schemes can override it
	$favicon = "images/favicon/favicon";
	if (!$x_hacks['host']) {
		$favicon .= rand(1, 8);
		if ($isChristmas) $favicon .= "x";	// Have a Santa hat
	}
	$favicon .= ".ico";
	
	/*
		Load scheme file
	*/
	
	// First determine the scheme ID to use
	
	// Previewing a scheme?
	if ($schemepre) {
		$scheme = (int)$_GET['scheme'];
		if (!can_select_scheme($scheme))
			dialog("The scheme you're trying to preview doesn't exist, or you're not allowed to use it.", "Scheme Preview");
	}
	// If a scheme is being forced board-wise, make it override forum-specific schemes
	else if ($miscdata['scheme'] !== NULL)
		$scheme = $miscdata['scheme'];
	// Forum-specific scheme, passed to the function
	else if ($forcescheme !== null)
		$scheme = $forcescheme;
	// Force Xmas scheme (cue whining, as always)
	else if ($isChristmas && !$x_hacks['host'] && $config['enable-christmas']) {
		$scheme = 3;
		$x_hacks['rainbownames'] = true;
	}
	// Standard scheme
	else
		$scheme = $loguser['scheme'];

	$schemerow	= $sql->fetchq("SELECT name, file FROM schemes WHERE id = '{$scheme}'");
	if ($schemerow && substr($schemerow['file'], -4) === ".php" && valid_filename(substr($schemerow['file'], 0, -4)) && file_exists("schemes/{$schemerow['file']}")) {
		$filename	= $schemerow['file'];
	} else {
		$filename	= "night.php";
	}
	require "schemes/$filename";
	
	// Some of the original jul schemes do not define the "permabanned" color
	if (!isset($nmcol[0][-2])) {
		$nmcol[0][-2] = $nmcol[0][-1]; 
		$nmcol[1][-2] = $nmcol[1][-1]; 
		$nmcol[2][-2] = $nmcol[2][-1]; 
	}
	// Hide Normal+ to non-admins
	if ($loguser['powerlevel'] < $config['view-super-minpower']) {
		$nmcol[0][1]	= $nmcol[0][0];
		$nmcol[1][1]	= $nmcol[1][0];
		$nmcol[2][1]	= $nmcol[2][0];
	}
	
	// Default bar image definition, after numdir got potentially updated by the scheme
	$barimg = array(
		0 => "images/bar/{$numdir}barleft.png",
		1 => "images/bar/{$numdir}bar-on.png",
		2 => "images/bar/{$numdir}bar-off.png",
		3 => "images/bar/{$numdir}barright.png",
	);
	
	/*
		Build the CSS using the scheme variables
	*/
	$css = "<link rel='stylesheet' href='schemes/base.css' type='text/css'>";
	if ($nullscheme) {
		//  Special "null" scheme.
		$css .= "<style type='text/css'>";
	} else if ($schemetype == 1) {
		// External CSS
		$css .= hook_print('header-css')."
		<link rel='stylesheet' type='text/css' href='schemes/$schemefile.css'>
		<style type='text/css'>";
		$usebtn = 1;
	} else {
		// Standard
		
		// Convert image URL to proper CSS url
		if (isset($bgimage) && $bgimage)
			$bgimage = " url('$bgimage')";
		else 
			$bgimage = "";
		
		$css .= hook_print('header-css')."
		<style type='text/css'>
			a,.buttonlink                   { color: #$linkcolor; }
			a:visited,.buttonlink:visited   { color: #$linkcolor2; }
			a:active,.buttonlink:active     { color: #$linkcolor3; }
			a:hover,.buttonlink:hover 	    { color: #$linkcolor4; }
			body {
				color: #$textcolor;
				font-family: $font;
				background: #$bgcolor$bgimage;
			}
			div.lastpost { font-family: $font2 !important; }
			div.lastpost:first-line { font-family: $font !important; }
			.font 	{font-family: $font}
			.fonth	{color:#$tableheadtext; font-family: $font}
			.fonts	{font-family: $font2}
			.fontt	{font-family: $font3}
			.tdbg1	{background:#$tablebg1}
			.tdbg2	{background:#$tablebg2}
			.tdbgc	{background:#$categorybg}
			.tdbgh	{background:#$tableheadbg; /* color:#$tableheadtext */}
			.table	{border: #$tableborder 1px solid;
					 font-family: $font}
			.tdbg1,.tdbg2,.tdbgc,.tdbgh	{border: #$tableborder 1px solid}
			.attachment-box,.attachment-box-addnew {
				border: #$tableborder 1px solid;
				background: #$tablebg2;
			}
			.attachment-box:hover,.attachment-box-addnew:hover {
				background: #$categorybg !important;
			}
		";
	}
	
	if (
		   isset($scr1)
		&& isset($scr2)
		&& isset($scr3)
		&& isset($scr4)
		&& isset($scr5)
		&& isset($scr6)
		&& isset($scr7)
	) {
		$css	.= "
		/* IE/Webkit/Chrome/etc. custom scrollbars. Remember these? */
		body {
			scrollbar-face-color:		#$scr3;
			scrollbar-track-color:		#$scr7;
			scrollbar-arrow-color:		#$scr6;
			scrollbar-highlight-color:	#$scr2;
			scrollbar-3dlight-color:	#$scr1;
			scrollbar-shadow-color:		#$scr4;
			scrollbar-darkshadow-color:	#$scr5;
		}
		::-webkit-scrollbar, ::-webkit-scrollbar-button {
			width:	1.25em;
			height:	1.25em;
		}
		::-webkit-scrollbar-track	{
			background-color: #$scr7;
		}
		::-webkit-scrollbar-track-piece	{}
		::-webkit-scrollbar-thumb, ::-webkit-scrollbar-button	{
			background-color:		#$scr3;
			background-size:		contain;
			background-repeat:		no-repeat;
			background-position:	center;
			border:					2px solid;
			color:					#$scr6;
			border-color: 			#$scr1 #$scr4 #$scr5 #$scr2;
		}
		::-webkit-scrollbar-thumb:active, ::-webkit-scrollbar-button:active	{
			background-color:	#$scr4;
			border-color: 		#$scr5 #$scr2 #$scr1 #$scr5;
		}
		::-webkit-scrollbar-button:vertical:decrement {
			background-image: url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' fill='%23$scr6'><polygon points='12 75, 50 25, 88 75'/></svg>\");
		}
		::-webkit-scrollbar-button:vertical:increment {
			background-image: url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' fill='%23$scr6'><polygon points='12 25, 50 75, 88 25'/></svg>\");
		}
		::-webkit-scrollbar-button:horizontal:decrement {
			background-image: url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' fill='%23$scr6'><polygon points='75 12, 25 50, 75 88'/></svg>\");
		}
		::-webkit-scrollbar-button:horizontal:increment {
			background-image: url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='100' height='100' fill='%23$scr6'><polygon points='25 12, 75 50, 25 88'/></svg>\");
		}
		::-webkit-scrollbar-corner	{
			background:	#$scr7;
		}
		";
	}
	
	// Is custom CSS defined for form elements?
	if ($formcss) {
		$numcols = 80;
		
		if (!isset($formtextcolor)) {
			$formtextcolor = $textcolor;
		}
		if (!isset($formcolor)) {
			$formcolor = "000";
		}
		if (!isset($inputborder)) {
			$inputborder   = $tableborder;
		}
		
		$css.="
		textarea,input,select,button,.button{
		  border:	#$inputborder solid 1px;
		  background:#$formcolor;
		  color:	#$formtextcolor;
		  font-family:	$font}
		textarea:focus {
		  border:	#$inputborder solid 1px;
		  background:#$formcolor;
		  color:	#$formtextcolor;
		  font-family:	$font}
		input[type=radio]{
		  border:	none;
		  background:none;
		  color:	#$formtextcolor;
		  font-family:	$font}
		input[type=submit],input[type=button],button,.button{
		  border:	#$inputborder solid 2px;
		  font-family:	$font}
		.button{color: #$formtextcolor !important;}
		";
	} else if (!$usebtn) {
		$css.="
		a.button, a.button:active, a.button:hover {
			font-weight: bold !important;
			cursor: pointer;
		}
		";
	}

	// April 1st page flip
	/*
	$css .= "
		body {
			transform:			scale(-1, 1);
			-o-transform:		scale(-1, 1);
			-moz-transform:		scale(-1, 1);
			-webkit-transform:	scale(-1, 1);
		}
		.tbl {
			transform:			scale(-1, 1);
			-o-transform:		scale(-1, 1);
			-moz-transform:		scale(-1, 1);
			-webkit-transform:	scale(-1, 1);
		}
	";
	*/
	
	// 10/18/08 - hydrapheetz: added a small hack for "extra" css goodies.
	if ($css_extra) {
		$css .= $css_extra . "\n";
	}
	
	if ($loguser['fontsize']) {
		$css .= "body { font-size: {$loguser['fontsize']}% }\n"; 
	}
	$css .= "</style>";

	// $css	.= "<!--[if IE]><style type='text/css'>#f_ikachan, #f_doomcounter, #f_mustbeblind { display: none; }</style><![endif]-->	";
	
	// When a post milestone is reached, everybody gets rainbow colors for a day
	if (!$x_hacks['rainbownames']) {
		$x_hacks['rainbownames'] = ($sql->resultq("SELECT `date` FROM `posts` WHERE (`id` % 100000) = 0 ORDER BY `id` DESC LIMIT 1") > time()-86400);
	}
	
	// "Mobile" layout
	$smallbrowsers	= array("Nintendo DS", "Android", "PSP", "Windows CE", "BlackBerry", "iPhone", "Mobile");
	if ((str_replace($smallbrowsers, "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT']) || filter_int($_GET['mobile'])) {
		$loguser['layout']		= 2;
		$loguser['viewsig']		= 0;
		$config['board-title']	= "<span style='font-size: 2em'>{$config['board-name']}</span>";
		$x_hacks['smallbrowse']	= true;
	}
	
	$GLOBALS['forcetitle'] = $forcetitle;
}

function pageheader($windowtitle = '', $mini = false, $centered = false) {
	global 	$sql, $loguser, $config, $x_hacks, $miscdata, $runtime, $scriptname, $meta, $userfields, $numcols, $barimg, $isbot, $schemerow,
			$isadmin, $issuper, $sysadmin, $isChristmas, $nmcol, $favicon, $url, $bpt_flags, $body_extra, $schemepre, $css, $forcetitle;
			
	// Load this if it wasn't explicitly launched
	if (!isset($nmcol)) {
		load_layout();
	}
	
	/*
		Track activity only for pages that render the main header
	*/
	if ($loguser['id']) {
		if (!filter_bool($meta['notrack'])) {
			$influencelv = calclvl(calcexp($loguser['posts'], (time() - $loguser['regdate']) / 86400));
			$sql->queryp("
				UPDATE users
				SET lastactivity = :lastactivity, lastip = :lastip, lasturl = :lasturl ,lastforum = :lastforum, influence = :influence
				WHERE id = {$loguser['id']}",
				[
					'lastactivity' 	=> time(),
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
				'date'			=> time(),
				'useragent'		=> $_SERVER['HTTP_USER_AGENT'],
				'lasturl'		=> $url,
				'lastforum'		=> 0,
				'flags'			=> $bpt_flags,
			]);
	}
	$sql->query("DELETE FROM guests WHERE ip = '{$_SERVER['REMOTE_ADDR']}' OR date < ".(time() - 300));
	

	/*
		View milestones
	*/

	$views = $miscdata['views'] + 1;

	if (!filter_bool($meta['notrack'])) {
		
		if (!$isbot && !$runtime['ajax-request']) {

			// Don't increment the view counter for bots
			$sql->query("UPDATE misc SET views = views + 1");

			// Log hits close to a milestone
			if($views%10000000>9999000 || $views%10000000<1000) {
				$sql->query("INSERT INTO hits VALUES ($views ,{$loguser['id']}, '{$_SERVER['REMOTE_ADDR']}', ".time().")");
			}

			// Print out a message to IRC whenever a 10-million-view milestone is hit
			if (
				 $views % 10000000 >  9999994 ||
				($views % 10000000 >= 9991000 && $views % 1000 == 0) ||
				($views % 10000000 >= 9999900 && $views % 10 == 0) ||
				($views > 5 && $views % 10000000 < 5)
			) {
				// View <num> by <username/ip> (<num> to go)
				report_send(
					IRC_MAIN, "View ".xk(11).str_pad(number_format($views), 10, " ", STR_PAD_LEFT).xk()." by ".($loguser['id'] ? xk(11).str_pad($loguser['name'], 25, " ") : xk(12).str_pad($_SERVER['REMOTE_ADDR'], 25, " ")).xk().($views % 1000000 > 500000 ? " (". xk(12).str_pad(number_format(1000000 - ($views % 1000000)), 5, " ", STR_PAD_LEFT).xk(2) ." to go".xk().")" : ""),
					IRC_MAIN, "View **".number_format($views)."** by ".($loguser['id'] ? "**{$loguser['name']}**" : "**{$_SERVER['REMOTE_ADDR']}**").($views % 1000000 > 500000 ? " (**".number_format(1000000 - ($views % 1000000))." to go)" : ""),
				);
			}
			
		}
		// Dailystats update in one query
		$sql->query("INSERT INTO dailystats (date, users, threads, posts, views) " .
					 "VALUES ('".date('m-d-y',time())."', (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM threads), (SELECT COUNT(*) FROM posts), $views) ".
					 "ON DUPLICATE KEY UPDATE users=VALUES(users), threads=VALUES(threads), posts=VALUES(posts), views=$views");
	}
	
	// Only here to skip executing it when the header doesn't get rendered.
	if (!filter_bool($meta['notrack'])) {
		// Delete expired bans
		$sql->query("
			UPDATE `users` SET
				`ban_expire` = 0,
				`powerlevel` = powerlevel_prev
			WHERE `ban_expire` != 0 AND
				  `powerlevel` = '-1' AND
				  `ban_expire` < ".time()
		);		
	}
		
	// UTF-8 time?
	header("Content-type: text/html; charset=utf-8'");

	// cache bad (well, most of the time)
	//if (!isset($meta['cache'])) {
	//	header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
	//	header('Pragma: no-cache');
	//}		
	
	/*
		META tags & Favicon
	*/
	$metatag = '';

	if (isset($meta['noindex']))
		$metatag .= "<meta name=\"robots\" content=\"noindex,follow\" />";

	if (isset($meta['description']))
		$metatag .= "<meta name=\"description\" content=\"{$meta['description']}\" />";

	if (isset($meta['canonical']))
		$metatag .= "<link rel='canonical' href='{$meta['canonical']}' />";
	
	/*
		Board title
	*/
	$windowtitle = $config['board-name'] . ($windowtitle ? " -- " . htmlspecialchars($windowtitle) : "");
	
	/*
		Header links at the top of every page
	*/
	$headlinks = '';
	if ($loguser['id']) {
		
		if ($isadmin)
			$headlinks .= '<a href="admin.php" style="font-style:italic;">Admin</a> - ';

		if ($issuper)
			$headlinks .= '<a href="shoped.php" style="font-style:italic;">Shop Editor</a> - ';
		
		if ($isadmin)
			$headlinks .= '<a href="register.php" style="font-style:italic;">Register</a> - ';
			
		
		// Now with logout workaround when JS is disabled
		$logout = '
		<form action="login.php" method="post" name="logout" style="display: inline"><input type="hidden" name="action" value="logout">'.auth_tag(TOKEN_LOGIN).'</form>
		<a href="login.php?action=logout" onclick="event.preventDefault(); document.logout.submit()">Logout</a>';
		
		$headlinks.= $logout.'
		- <a href="editprofile.php">Edit profile</a>
		'.(!$loguser['profile_locked'] ? " - <a href='postlayouts.php'>Edit layout</a>" : "").'
		'.($config['allow-avatar-storage'] ? " - <a href='editavatars.php'>Edit avatars</a>" : "").'
		- <a href="postradar.php">Post radar</a>
		- <a href="shop.php">Item shop</a>
		- <a href="forum.php?fav=1">Favorites</a>
		- <a href="blocklayout.php">Blocked layouts</a>'.hook_print('header-links');
		
		// Page-specific addendums
		switch ($scriptname) {
			case 'index.php':
			case 'latestposts.php':
				$headlinks .= " - <a href='index.php?action=markallforumsread'>Mark all forums read</a>";
				break;
			
			case 'forum.php':
			case 'thread.php':
				// Since we're supposed to have $forum when we browse these pages...
				global $forum;
				if (isset($forum['id']))
					$headlinks .= " - <a href='index.php?action=markforumread&forumid={$forum['id']}'>Mark forum read</a>";
				break;
				
			case 'private.php':
				global $u;
				if ($loguser['id'] == $u) {
					$tokenstr = "&auth=".generate_token(TOKEN_MGET);
					if (!default_pm_folder($_GET['dir'], DEFAULTPM_GROUPS)) {
						$headlinks .= " - <a href='?action=markfolderread&dir={$_GET['dir']}{$tokenstr}'>Mark folder as read</a>";
					}
					$headlinks .= " - <a href='?action=markallfoldersread{$tokenstr}'>Mark all folders as read</a>";
				}
				break;
		}
		
	} else {
		$headlinks.='
		  <a href="register.php">Register</a>
		- <a href="login.php">Login</a>';
	}
	
	if (!$loguser['id'] && $miscdata['private']) {
		$headlinks2 = '<a href="faq.php">Rules/FAQ</a>';
	} else {
		$headlinks2 = "
		<a href='index.php'>Main</a>
		- <a href='memberlist.php'>Memberlist</a>
		- <a href='activeusers.php'>Active users</a>
		- <a href='calendar.php'>Calendar</a>
		<!-- - <a href='http://tcrf.net'>Wiki</a> -->
		".($config['irc-servers'] && $config['irc-channels'] ? " - <a href='irc.php'>IRC Chat</a>" : "")."
		- <a href='online.php'>Online users</a>
		".hook_print('header-links-2')."
		<br>
		<a href='ranks.php'>Ranks</a>
		- <a href='faq.php'>Rules/FAQ</a>
		- <a href='acs.php'>JCS</a>
		- <a href='stats.php'>Stats</a>
		- <a href='latestposts.php'>Latest Posts</a>
		- <a href='hex.php' title='Color Chart' class='popout' target='_blank'>Color Chart</a>
		- <a href='smilies.php' title='Smilies' class='popout' target='_blank'>Smilies</a>
		";
	}
	
	
	
	/*
		Unread PMs box
	*/
	$privatebox = "";
	// Note that we ignore this in private.php (obviously) and the index page (it handles PMs itself)
	// This box only shows up when a new PM is found, so it's optimized for that
	if ($loguser['id'] && !in_array($scriptname, array("private.php","index.php")) ) {
		$lastthread = $sql->query("
			SELECT t.id
			FROM pm_threads t
			INNER JOIN pm_access       a ON t.id         = a.thread
			LEFT  JOIN pm_foldersread fr ON a.folder     = fr.folder AND a.user = fr.user
			LEFT  JOIN pm_threadsread tr ON t.id         = tr.tid    AND tr.uid = {$loguser['id']}
			WHERE a.user = {$loguser['id']} 
			  AND (!tr.read OR tr.read IS NULL)			  
			  AND (fr.readdate IS NULL OR t.lastpostdate > fr.readdate)
			ORDER BY t.lastpostdate DESC
		");
		$unreadcount = $sql->num_rows($lastthread);
		
		if ($unreadcount) {
			$tid = $sql->result($lastthread);
			$lastpost = $sql->fetchq("
				SELECT p.id pid, p.date, $userfields
				FROM pm_posts p
				LEFT JOIN users u ON p.user = u.id
				WHERE p.thread = {$tid}
				ORDER BY p.date DESC
				LIMIT 1
			");
			$privatebox = "
				<tr>
					<td colspan=3 class='tbl tdbg2 center fonts'>
						{$statusicons['new']} <a href='private.php'>You have {$unreadcount} new private message".($unreadcount != 1 ? 's' : '')."</a> -- <a href='showprivate.php?pid={$lastpost['pid']}#{$lastpost['pid']}'>Last unread message</a> from ".getuserlink($lastpost)." on ".printdate($lastpost['date'])."
					</td>
				</tr>";			
			
		}
	}
	
	// Pretty similar to the above but for profile comments
	// Of course this is simpler
	if ($loguser['id'] && $loguser['comments']) {
		$unreadcount = $sql->resultq("SELECT COUNT(*) FROM users_comments WHERE userto = {$loguser['id']} AND `read` = 0");
		if ($unreadcount) {
			$privatebox .= "
			<tr>
				<td colspan=3 class='tdbg2 center fonts'>
					{$statusicons['new']} <a href='usercomment.php?id={$loguser['id']}&to=1'>You have {$unreadcount} new profile comment".($unreadcount != 1 ? 's' : '')."</a>
				</td>
			</tr>";
		}
	}
	
	// Overriding the default title?
	// Moved here to allow overriding themes defining custom headers (and fixing the bug which renders the custom header non-clickable)
	if ($miscdata['specialtitle'])
		$config['board-title'] = $miscdata['specialtitle'];	// Global
	else if ($forcetitle) 
		$config['board-title'] = $forcetitle; // Forum specific
	else 
		$config['board-title'] = "<a href='./'>{$config['board-title']}</a>"; // Leave unchanged
	
	// Normal+ can view the submessage
	if ($issuper) {
		$config['board-title'] .= $config['title-submessage'];
	}
	
	$config['board-title'] = xssfilters($config['board-title']);
	
	/*
		Extra title rows (for admin info)
	*/
	if ($schemepre) {
		$config['board-title']	.= "</a><br><span class='font'>Previewing scheme \"<b>". htmlspecialchars($schemerow['name']) ."</b>\"</span>";
	}
	
	// Admin-only info
	// in_array($loguserid,array(1,5,2100))
	if ($sysadmin) {
		if (file_exists("{$config['backup-folder']}/".date("Ymd").".zip") && date('Gi') < 100){ // Give this warning message for an hour
			$config['board-title']	.=  "<br><a href='admin-backup.php'><span class='font b' style='color: #f00'>Please download the nightly backup.</span></a>";			
		}
		
		$xminilog	= $sql->fetchq("SELECT COUNT(*) as count, MAX(`date`) as date FROM `pendingusers`");
		if ($xminilog['count']) {
			$xminilogip	= $sql->fetchq("SELECT `name`, `ip` FROM `pendingusers` ORDER BY `date` DESC LIMIT 1");
			$config['board-title']	.= "<br><a href='admin-pendingusers.php'><span class='font' style='color: #ff0'><b>{$xminilog['count']}</b> pending user(s), last <b>'{$xminilogip['name']}'</b> at <b>". printdate($xminilog['date']) ."</b> by <b>{$xminilogip['ip']}</b></span></a>";
		}
	}
	
	// Additional options
	$config['board-title'] .= hook_print('header-title-rows');
	
	// Build post radar
	$race = $loguser['id'] ? postradar($loguser['id']) : "";
	

		
	/*
		JS Utility (and other crap in common.js that's important to be loaded immediately)
		
		<noscript>   -> Only shown with JS disabled
		.js          -> Only shown with JS enabled
		.nojs-jshide -> Shown with JS disabled, hidden off-screen with JS enabled
	*/
	$jscripts = "<style>
	.js, .nojs-jshide {display: none}
</style>
<noscript><style>.nojs-jshide {display: unset}</style></noscript>
<script type='text/javascript' src='js/common.js'></script>";
	
	//No gunbound rankset here (yet), stop futily trying to update it
	//updategb();

	/*
		Page overlays
	*/
	$overlay = '';
	if (filter_bool($_GET['w'])) {
		$overlay	= "<img src=images/wave/squid.png style=\"position: fixed; left: ". mt_rand(0,100) ."%; top: ". mt_rand(0,100) ."%;\" title=\"Ikachaaaan!\">";
		$overlay	.= "<img src=images/wave/cheepcheep.png style=\"position: fixed; left: ". mt_rand(0,100) ."%; top: ". mt_rand(0,100) ."%;\" title=\"cheep tricks\">";
		$overlay 	.= "<img src=images/wave/chest.png style=\"position: fixed; right: 20px; bottom: 0px;\" title=\"1\">";

		for ($i = rand(0,5); $i < 20; ++$i) {
			$overlay .= "<img src=images/wave/seaweed.png style=\"position: fixed; left: ". mt_rand(0,100) ."%; bottom: -". mt_rand(24,72) ."px;\" title=\"weed\">";
		}
	}

	$dispviews = $miscdata['views'];
	//if (($views % 1000000 >= 999000) && ($views % 1000000 < 999990))
	//	$dispviews = substr((string)$views, 0, -3) . "???";

	// Notice window / points of required attention (global version)
	$attn = "";
	if ($miscdata['attntitle'])
		$attn .= "<tr><td class='tdbgh center b'>".dofilters(domarkup($miscdata['attntitle']))."</td></tr>";
	if ($miscdata['attntext'])
		$attn .= "<tr><td class='tdbg2 left'>".dofilters(domarkup($miscdata['attntext']))."</td></tr>";
	if ($attn)
		$attn = "<table class='table attn-glob fonts'>{$attn}</table>";
	
?><!doctype html>
<html>
	<head>
		<meta http-equiv='Content-type' content='text/html; charset=utf-8'>
		<meta name='viewport' content='width=device-width, initial-scale=1'>
		<?=$metatag?>
		<title><?=$windowtitle?></title>
		<?= 
		/* Because this breaks links which do not specify a page (ie: ?id=1), this is disabled for pages loaded directly without the help of pageloader */
		(isset($meta['base']) ? "<base href=\"{$meta['base']}\">" : "")
		 ?>
		<link rel='shortcut ico' href='<?=$favicon?>' type='image/x-icon'>
		<?=$css?>
		<?=$jscripts?>
	</head>
	<body <?= ($centered ? "class='flexhvc h'" : "") ?>>
		<?= (isset($body_extra) ? $body_extra : "") ?>
	<?php

	if (!$mini) {
	?>
		<?=$overlay?>
		<center>
			<table class='table'>
				<tr>
					<td class='tbl tdbg1 center' colspan='3'>
						
						<table class="td-header w">
							<tr>
								<td><?=$config['board-title']?></td>
								<td class="right"><?= $attn ?></td>
							</tr>
						</table>
						<div class='fonts'>
							<?=$headlinks?>	
						</div>							
<?php		
		if (!$x_hacks['smallbrowse']) {
				// Desktop header
?>						
					</td>
				</tr>
				<tr>
					<td style='width: 120px' class='tdbg2 center fonts nobr'>
						Views: <?=$dispviews?>
					</td>
					<td class='tbl tdbg2 center fonts'>
						<?=$headlinks2?>
					</td>
					<td style='width: 120px' class='tdbg2 center fonts nobr'>
						<?=printdate()?>
					</td>
				</tr>			
<?php
			
		} else {
				// Mobile header
?>
						<div class='fonts'>
							<?=$dispviews?> views, <?=printdate()?><br>
							(mobile view enabled)
						</div>
					</td>
				</tr>
				<tr>
					<td class='tdbg2 center fonts w' colspan=3>
						<?=$headlinks2?>
					</td>
				</tr>
<?php
		}
			// Common
?>
				<tr>	
					<td colspan=3 class='tdbg1 center fonts'>
						<?=$race?>
						<?=$privatebox?>
				</table>
		</center>
		<br>
<?php	
	}
	
	define('HEADER_PRINTED', true);
}



function pagefooter($showfooter = true) {
	global $x_hacks, $sql, $sqldebuggers, $loguser, $config, $scriptname, $startingtime, $_adminsidebar, $poweredbypic, $js_extra;
	
	if ($_adminsidebar !== null)
		print $_adminsidebar->DisplayBottom();
	
	if (!$config['affiliate-links']) {
		$affiliatelinks = "";
	} else {
		$affiliatelinks = "<form><select onchange='window.open(this.options[this.selectedIndex].value)'>{$config['affiliate-links']}</select></form>";
	}
	
	$ikachan_text = '';
	if ($config['show-ikachan']) { // Ikachan! :D!
		//$ikachan = 'images/ikachan/vikingikachan.png';
		//$ikachan = 'images/sankachan.png';
		//$ikachan = 'images/ikamad.png';
		$ikachan = 'images/squid.png';

		$ikaquote = 'Capturing turf before it was cool';
		//$ikaquote = 'Someone stole my hat!';
		//$ikaquote = 'If you don\'t like Christmas music, well... it\'s time to break out the earplugs.';
		//$ikaquote = 'This viking helmet is stuck on my head!';
		//$ikaquote = 'Searching for hats to wear!  If you find any, please let me know...';
		//$ikaquote = 'What idiot thought celebrating a holiday five months late was a good idea?';
		//$ikaquote = 'Back to being a fixture now, please stop bitching.';
		//$ikaquote = 'I just want to let you know that you are getting coal this year. You deserve it.';
		
		// Enables people to disable the floating squid if they want, or re-enable it later
		// Saved browser-side rather than being yet another silly config option
		if (isset($_COOKIE['ikachan']) && $_COOKIE['ikachan'] === "hidden") {
			$ikachan_text = "<a href='javascript:void();' onclick=\"document.cookie='ikachan=shown;max-age=0;path=/';alert('Floating Ikachan re-enabled for future pageloads...'); this.remove();\"><img id='f_ikachan' src='$ikachan' style='vertical-align: middle;' title=\"$ikaquote (click to re-enable random floating position)\"></a>";
		} else {
			$ikachan_text = "<a href='javascript:void();' onclick=\"javascript:document.cookie='ikachan=hidden;max-age=31536000;path=/';alert('Floating Ikachan disabled for future pageloads...'); this.remove();\"><img id='f_ikachan' src='$ikachan' style=\"z-index: 999999; position: fixed; left: ". mt_rand(0,100) ."%; top: ". mt_rand(0,100) ."%;\" title=\"$ikaquote (click to hide in future page loads)\"></a>";
		}
	}
	
	$doomnum = ($x_hacks['mmdeath'] >= 0) ? "<div style='position: absolute; top: -100px; left: -100px;'>Hidden preloader for doom numbers:
	<img src='numgfx/death/0.png'> <img src='numgfx/death/1.png'> <img src='numgfx/death/2.png'> <img src='numgfx/death/3.png'> <img src='numgfx/death/4.png'> <img src='numgfx/death/5.png'> <img src='numgfx/death/6.png'> <img src='numgfx/death/7.png'> <img src='numgfx/death/8.png'> <img src='numgfx/death/9.png'></div>" : "";

	
	// Acmlmboard - <a href='https://github.com/Xkeeper0/jul'>". (file_exists('version.txt') ? file_get_contents("version.txt") : shell_exec("git log --format='commit %h [%ad]' --date='short' -n 1")) ."</a>
	// <br>". 	($loguser['id'] && $scriptname != 'index.php' ? adbox() ."<br>" : "") ."
	/*
<!-- Piwik -->
<script type=\"text/javascript\">
var pkBaseURL = ((\"https:\" == document.location.protocol) ? \"https://stats.tcrf.net/\" : \"http://stats.tcrf.net/\");
document.write(unescape(\"%3Cscript src='\" + pkBaseURL + \"piwik.js' type='text/javascript'%3E%3C/script%3E\"));
</script><script type=\"text/javascript\">
try {
var piwikTracker = Piwik.getTracker(pkBaseURL + \"piwik.php\", 4);
piwikTracker.trackPageView();
piwikTracker.enableLinkTracking();
} catch( err ) {}
</script><noscript><p><img src=\"http://stats.tcrf.net/piwik.php?idsite=4\" style=\"border:0\" alt=\"\" /></p></noscript>
<!-- End Piwik Tag -->
<!--<script type=\"text/javascript\" src=\"http://ajax.aspnetcdn.com/ajax/jQuery/jquery-1.6.min.js\"></script>
<script type=\"text/javascript\" src=\"js/useful.js\"></script> -->
	*/
	if (isset($js_extra))
		print $js_extra;
	//print "<script type='text/javascript' src='js/jquery.min.js'></script>";
	print "<br><br><center>";
	
	if ($showfooter) {
		?>
		<!--
		<img src='adnonsense.php?m=d' title='generous donations to the first national bank of bad jokes and other dumb crap people post' style='margin-left: 44px;'><br>
		<img src='adnonsense.php' title='hotpod fund' style='margin: 0 22px;'><br>
		<img src='adnonsense.php?m=v' title='VPS slushie fund' style='margin-right: 44px;'>
		-->
		<br>
	
		<span class='fonts'>
			<br>
			<br>
			<a href='<?=$config['footer-url']?>'><?=$config['footer-title']?></a>
			<br>
		</span>
		<?=$affiliatelinks?>
		<span class='fonts'>
			<br>
			
			<table cellpadding=0 border=0 cellspacing=2>
				<tr>
					<td><?= $poweredbypic ?></td>
					<td>
						Acmlmboard - <a href="https://github.com/Kak2X/jul"><?= file_get_contents('version.txt') ?></a><br>
						&copy;2000-<?=date("Y")?> Acmlm, Xkeeper, Inuyasha, et al.<?= $ikachan_text ?>
					</td>
				</tr>
			</table>	
		<?php
	} else {
		print $ikachan_text;
	}
	
	print $doomnum;

	/*
		( used to be in printtimedif() )
	*/
	$exectime = microtime(true) - $startingtime;

	$qseconds = sprintf("%01.6f", mysql::$time);
	$sseconds = sprintf("%01.6f", $exectime - mysql::$time);
	$tseconds = sprintf("%01.6f", $exectime);
	
	$curmem   = sizeunits(memory_get_usage());
	$maxmem   = sizeunits(memory_get_peak_usage()); 
	
	$queries = mysql::$queries;
	$cache   = mysql::$cachehits;

	if (isset($_GET['oldfooter'])) {
		print "<br><font>Page rendered in {$tseconds} seconds; used {$curmem} (max {$maxmem})</font><br>";
	} else {
		print "<br>
			<span>{$queries} database queries". (($cache > 0) ? ", {$cache} query cache hits" : "") .".</span>
			<table style='border-spacing: 0px'>
				<tr><td align=right>Query execution time:&nbsp;</td><td>{$qseconds} seconds</td></tr>
				<tr><td align=right>Script execution time:&nbsp;</td><td>{$sseconds} seconds</td></tr>
				<tr><td align=right>Total render time:&nbsp;</td><td>{$tseconds} seconds</td></tr>
				<tr><td align=right>Memory used:&nbsp;</td><td>{$curmem} (max {$maxmem})</td></tr>
			</table>";
	}
		
	print "</span>";
	
	// Print errors locally
	print error_printer(true, ($loguser['powerlevel'] == 4 || $config['always-show-debug']), $GLOBALS['errors']);

	// Print mysql queries
	if (in_array($_SERVER['REMOTE_ADDR'], $sqldebuggers) || $config['always-show-debug']) {
		print "<br><a href='".actionlink(null, "?{$_SERVER['QUERY_STRING']}".($_SERVER['QUERY_STRING'] ? "&" : "")."debugsql=1")."'>".(mysql::$debug_on ? "Disable" : "Enable")." useless mySQL query debugging shit</a><br>";
		if (mysql::$debug_on) {
		
?>
	<br>
	<table class='table'>
		<tr>
			<td class='tdbgh center b' colspan=5>
				SQL Debug
			</td>
		</tr>
		<tr>
			<td class='tdbgh center' style='width: 20px'>&nbsp;</td>
			<td class='tdbgh center' style='width: 20px'>ID</td>
			<td class='tdbgh center' style='width: 300px'>Function</td>
			<td class='tdbgh center'>Query</td>
			<td class='tdbgh center' style='width: 90px'>Time</td>
		</tr>
<?php
			

			
			
			$oldid    = NULL;
			$num      = 1;
			$transact = $transchg = false;
			foreach(mysql::$debug_list as $i => $d) {
				
				// Add a separator between connection ID changes
				if ($oldid != $d[0]) {
?>
		<tr><td class='tdbgc center' style='height: 4px' colspan=5></td></tr>
<?php
				}
				$oldid = $d[0];
				
				// Does the row *NOT* count towards the query count?
				if ($d[5]) {
					$c = "-";
				} else {
					$c = $num;
					++$num;
				}
				
				// Format the message text
				if ($d[6] & mysql::MSG_TRANSCHG) {
					// Transaction change
					$transchg = true;
					$transact = !$transact;
				} else if ($d[6] & mysql::MSG_QUERY) {
					// The error marker has a higher precedence (for obv. reasons)
					
					if ($d[7] !== NULL) {
						$color = "FF0000";
					} else if ($d[6] & mysql::MSG_CACHED) {
						$color = "00dd00";
					} else if ($d[6] & mysql::MSG_PREPARED) {
						$color = "ffff44";
					} else if ($d[6] & mysql::MSG_EXECUTE) {
						$color = "ffcc44";
					} else {
						$color = "";
					}
					
					// Set the color for non-standard queries
					if ($color !== NULL) {
						$d[3] = "<span style='color:#{$color}".
							   ( $d[7] !== NULL 
							   ? ";border-bottom:1px dotted {$color}' title=\"{$d[7]}\"" 
							   : "'" ).
							   ">{$d[3]}</span>";
						$d[4] = "<span style='color:#{$color}'>{$d[4]}</span>";
					}
					
					$color = NULL;
				} else { // Informative messages
					$d[3] = "<i>{$d[3]}</i>";
				}
				
				// Highlight queries in a transaction
				if ($transchg) {
					$cell = 'c fonts';
					$transchg = false;
				} else if ($transact) {
					$cell = 'h';
				} else {
					$cell = (($i & 1)+1); // Cycling tccell1/2
				}
				
?>
		<tr>
			<td class='tdbg<?=$cell?> center'><?= $c ?></td>
			<td class='tdbg<?=$cell?> center'><?= $d[0] ?></td>
			<td class='tdbg<?=$cell?> center'>
				<?=$d[1]?><span class='fonts'><br>
				<?=$d[2]?></span>
			</td>
			<td class='tdbg<?=$cell?>'><?= $d[3] ?></td>
			<td class='tdbg<?=$cell?> center'><?= $d[4] ?></td>
		</tr>
<?php
			}
?>
	</table>
<?php
		}
	}
	
	// Logging of rendering times. Used back when DreamHost was being trash.
	// Not that it ever stopped, but it hasn't really been an issue
	if (!$x_hacks['host'] && $config['log-rendertimes']) {
		$pages	= array(
			"index.php",
			"thread.php",
			"forum.php",
		);
		if (in_array($scriptname, $pages)) {
			$sql->queryp("INSERT INTO rendertimes SET page = ?, time = ?, rendertime  = ?", ["/$scriptname", time(), $exectime]);
			$sql->query("DELETE FROM rendertimes WHERE time < '". (time() - 86400 * 14) ."'");
		}
	}	

	die;
}

	
	function dialog($message, $title = 'Board Message', $pagetitle = NULL) {
		require "lib/dialog.php";
		die;
	}

	function fatal_error($type, $message, $file, $line) {
?><style type='text/css'>
	body, #w {
		padding: 0px !important;
		margin: 0px !important;
		color: #fff !important;
		position: fixed !important;
	}
	#w {
		background: #000 !important; 
		left: 0px !important;
		top: 0px !important;
		width: 100%;
		height: 100%;
		overflow: auto;
	}
</style>
<pre id='w'>Fatal <?=$type?>

<span style='color: #0f0'><?=$file?></span>#<span style='color: #fe6'><?=$line?></span>

<span style='color: #fc0'><?=$message?></span>
</pre>
<?php
		die;
	}