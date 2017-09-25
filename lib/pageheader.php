<?php

function pageheader($windowtitle = '', $forcescheme = NULL, $forcetitle = NULL, $mini = false) {
	global 	$sql, $loguser, $config, $x_hacks, $miscdata, $scriptname, $meta, $userfields, $forum, $numcols, $isChristmas;
			
	// Load images and color settings right away
	require 'lib/colors.php';
	
	/*
		META tags & Favicon
	*/
	$metatag = '';

	if (filter_bool($meta['noindex'])) {
		$metatag 	.= "<meta name=\"robots\" content=\"noindex,follow\" />";
	}
	if (filter_bool($meta['description'])) {
		$metatag 	.= "<meta name=\"description\" content=\"{$meta['description']}\" />";
	}
	if (filter_bool($meta['canonical'])) {
		$metatag	.= "<link rel='canonical' href='{$meta['canonical']}'>";
	}
	
	$favicon = "favicon";
	if (!$x_hacks['host']) {
		$favicon .= rand(1, 8);
		if ($isChristmas) $favicon .= "x";	// Have a Santa hat
	}

	/*
		Board title (and sub titles)
	*/
	if (!$windowtitle) $windowtitle = $config['board-name'];
	
	// Overriding the default title?
	if ($miscdata['specialtitle'])
		$config['board-title'] = $miscdata['specialtitle'];	// Global
	else if ($forcetitle) 
		$config['board-title'] = $forcetitle; // Forum specific
	else 
		$config['board-title'] = "<a href='./'>{$config['board-title']}</a>"; // Leave unchanged
	
	if (has_perm('view-submessage')) {
		$config['board-title'] .= $config['title-submessage'] ? "<br><b>".$config['title-submessage']."</b>" : "";
	}
	
	// Admin-only info
	if (has_perm('sysadmin-actions')) {
		if (file_exists("{$config['backup-folder']}/".date("Ymd").".zip") && date('Gi') < 100){ // Give this warning message for an hour
			$config['board-title']	.=  "<br><a href='admin-backup.php'><span class='font b' style='color: #f00'>Please download the nightly backup.</span></a>";			
		}
	}
	if (has_perm('logs-banner')) {
		$xminilog	= $sql->fetchq("SELECT COUNT(*) as count, MAX(`time`) as time FROM `minilog`");
		if ($xminilog['count']) {
			$xminilogip	= $sql->fetchq("SELECT `ip`, `banflags` FROM `minilog` ORDER BY `time` DESC LIMIT 1");
			$config['board-title']	.= "<br><a href='shitbugs.php'><span class='font' style='color: #f00'><b>". $xminilog['count'] ."</b> suspicious request(s) logged, last at <b>". printdate($xminilog['time']) ."</b> by <b>". $xminilogip['ip'] ." (". $xminilogip['banflags'] .")</b></span></a>";
		}
		
		$xminilog	= $sql->fetchq("SELECT COUNT(*) as count, MAX(`time`) as time FROM `pendingusers`");
		if ($xminilog['count']) {
			$xminilogip	= $sql->fetchq("SELECT `name`, `ip` FROM `pendingusers` ORDER BY `time` DESC LIMIT 1");
			$config['board-title']	.= "<br><a href='admin-pendingusers.php'><span class='font' style='color: #ff0'><b>". $xminilog['count'] ."</b> pending user(s), last <b>'". $xminilogip['name'] ."'</b> at <b>". printdate($xminilog['time']) ."</b> by <b>". $xminilogip['ip'] ."</b></span></a>";
		}
	}
	
	
	/*
		Header links at the top of every page
	*/
	$headlinks = '';
	if ($loguser['id']) {
		
		if (has_perm('admin-actions'))
			$headlinks .= '<a href="admin.php" style="font-style:italic;">Admin</a> - ';

		if (has_perm('use-shoped')) {
			$headlinks .= '<a href="shoped.php" style="font-style:italic;">Shop Editor</a> - ';
		}
		
		$headlinks .= '<noscript><style>#logoutlink{display: none;}</style></noscript>
		<span id="logoutlink"><a href="javascript:document.logout.submit()">Logout</a></span>
		<input type="hidden" name="action" value="logout">
		<input type="hidden" name="auth" value="'.generate_token(TOKEN_LOGIN).'">
		<noscript><input type="submit" name="njout" class="tdbg1 buttonlink fonts" value="Logout"></noscript>
		- <a href="editprofile.php">Edit profile</a>
		'.($config['allow-custom-forums'] ? '- <a href="editcustomforums.php">My forums</a>' : '').'
		- <a href="postradar.php">Post radar</a>
		- <a href="shop.php">Item shop</a>
		- <a href="forum.php?fav=1">Favorites</a>';
		
		// Page-specific addendums
		switch ($scriptname) {
			case 'index.php':
			case 'latestposts.php':
				$headlinks .= " - <a href='index.php?action=markallforumsread'>Mark all forums read</a>";
				break;
			
			case 'forum.php':
			case 'thread.php':
				// Since we're supposed to have $forum when we browse these pages...
				if (isset($forum['id']))
					$headlinks .= " - <a href='index.php?action=markforumread&forumid={$forum['id']}'>Mark forum read</a>";
				break;
		}
		
	} else {
		$headlinks.='
		  <a href="register.php">Register</a>
		- <a href="login.php">Login</a>';
	}
	
	$headlinks2 = "
	<a href='index.php'>Main</a>
	".($config['allow-custom-forums'] ? "- <a href='customforums.php'>Browse forums</a>" : "")."
	- <a href='memberlist.php'>Memberlist</a>
	- <a href='activeusers.php'>Active users</a>
	- <a href='calendar.php'>Calendar</a>
	<!-- - <a href='http://tcrf.net'>Wiki</a> -->
	- <a href='irc.php'>IRC Chat</a>
	- <a href='online.php'>Online users</a><br>
	<a href='ranks.php'>Ranks</a>
	- <a href='faq.php'>Rules/FAQ</a>
	- <a href='acs.php'>JCS</a>
	- <a href='stats.php'>Stats</a>
	- <a href='latestposts.php'>Latest Posts</a>
	- <a href='hex.php' title='Color Chart' class='popout' target='_blank'>Color Chart</a>
	- <a href='smilies.php' title='Smilies' class='popout' target='_blank'>Smilies</a>
	";		
	
	
	/*
		Unread PMs box
	*/
	$new		= '&nbsp;';
	$privatebox = "";
	// Note that we ignore this in private.php (obviously) and the index page (it handles PMs itself)
	// This box only shows up when a new PM is found, so it's optimized for that
	if ($loguser['id'] && !in_array($scriptname, array("private.php","index.php")) ) {

		
		$unreadpm = $sql->fetchq("
			SELECT COUNT(p.id) cnt, p.date, $userfields
			FROM pmsgs p
			INNER JOIN users u ON p.userfrom = u.id
			WHERE p.userto = {$loguser['id']} AND p.msgread = 0
			ORDER BY p.id DESC
		");	
		
		if ($unreadpm['cnt']) {
			$privatebox = "
				<tr>
					<td colspan=3 class='tdbg2 center fonts'>
						{$statusicons['new']} <a href=private.php>You have {$unreadpm['cnt']} new private message".($unreadpm['cnt'] == 1 ? 's' : '')."</a> -- Last unread message from ".getuserlink($unreadpm)." on ".date($loguser['dateformat'], $unreadpm['date'] + $loguser['tzoff'])."
					</td>
				</tr>";
		}

	}
	
	
	/*
		CSS
	*/
		
	// Default values
	$numcols 	= 60;
	$nullscheme = 0;
	$schemetype = 0;
	$formcss 	= 0;
	
	// If a scheme is being forced board-wise, make it override forum-specific schemes
	// (Special schemes and $specialscheme now pass through $forcescheme)
	if ($miscdata['scheme'] !== NULL)
		$forcescheme = $miscdata['scheme'];
	
	
	$schemepre	= false;

	// Just skip all of this if we've forced a scheme
	if ($forcescheme === NULL) {
	
		//	Previewing a scheme?
		if (isset($_GET['scheme'])) {
			$loguser['scheme'] = (int) $_GET['scheme'];
			$schemepre	= true;
		} 

		// Force Xmas scheme (cue whining, as always)
		if ($isChristmas && !$x_hacks['host']) {
			$scheme = 3;
			$x_hacks['rainbownames'] = true;
		}
		
	} else {
		$loguser['scheme'] = $forcescheme;
	}

	$schemerow	= $sql->fetchq("SELECT name, file FROM schemes WHERE id = {$loguser['scheme']}");
	
	$filename	= "";
	if ($schemerow) {
		$filename	= $schemerow['file'];
	} else {
		$filename	= "night.php";
		$schemepre	= false;
	}

	#	if (!$x_hacks['host'] && true) {
	#		$filename	= "ymar.php";
	#	}
	
	
	require "schemes/$filename";
	
	// Hide Normal+ to non-admins
	if (!has_perm('show-super-users')) {
		$grouplist[GROUP_SUPER]['namecolor0'] = $grouplist[GROUP_NORMAL]['namecolor0'];
		$grouplist[GROUP_SUPER]['namecolor1'] = $grouplist[GROUP_NORMAL]['namecolor1'];
		$grouplist[GROUP_SUPER]['namecolor2'] = $grouplist[GROUP_NORMAL]['namecolor2'];
	}

	if ($schemepre) {
		$config['board-title']	.= "</a><br><span class='font'>Previewing scheme \"<b>". $schemerow['name'] ."</b>\"</span>";
	}

	//$config['board-title'] = "<a href='./'><img src=\"images/christmas-banner-blackroseII.png\" title=\"Not even Christmas in July, no. It's May.\"></a>";

	// PONIES!!!
	// if($forumid==30) $config['board-title'] = "<a href='./'><img src=\"images/poniecentral.gif\" title=\"YAAAAAAAAAAY\"></a>";
	// end PONIES!!!
	
	
	// Build post radar
	$race = $loguser['id'] ? postradar($loguser['id']) : "";
	

	if (isset($bgimage) && $bgimage != "")
		$bgimage = " url('$bgimage')";
	else 
		$bgimage = '';
	
	if ($nullscheme) {
		// special "null" scheme.
		$css = "";
	} else if ($schemetype == 1) {
		// External CSS
		$css = "<link rel='stylesheet' href='css/basics.css' type='text/css'><link rel='stylesheet' type='text/css' href='css/$schemefile.css'>";
		// backwards compat
		//global $bgcolor, $linkcolor;
		//$bgcolor = "000";
		//$linkcolor = "FFF";
	} else {
		// Standard
		$css="
			<link rel='stylesheet' href='css/base.css' type='text/css'>
			<style type='text/css'>
			a,.buttonlink 					{color: #$linkcolor; }
			a:visited,.buttonlink:visited 	{ color: #$linkcolor2; }
			a:active,.buttonlink:active 	{ color: #$linkcolor3; }
			a:hover,.buttonlink:hover 	{ color: #$linkcolor4; }
			body {
				scrollbar-face-color:		#$scr3;
				scrollbar-track-color:		#$scr7;
				scrollbar-arrow-color:		#$scr6;
				scrollbar-highlight-color:	#$scr2;
				scrollbar-3dlight-color:	#$scr1;
				scrollbar-shadow-color:	#$scr4;
				scrollbar-darkshadow-color:	#$scr5;
				color: #$textcolor;
				font:13px $font;
				background: #$bgcolor$bgimage;
			}
			div.lastpost { font: 10px $font2 !important; white-space: nowrap; }
			div.lastpost:first-line { font: 13px $font !important; }
			.font 	{font:13px $font}
			.fonth	{font:13px $font;color:$tableheadtext}	/* this is only used once (!) */
			.fonts	{font:10px $font2}
			.fontt	{font:10px $font3}
			.tdbg1	{background:#$tablebg1}
			.tdbg2	{background:#$tablebg2}
			.tdbgc	{background:#$categorybg}
			.tdbgh	{background:#$tableheadbg; color:$tableheadtext}
			.table	{empty-cells:	show; width: 100%;
					 border-top:	#$tableborder 1px solid;
					 border-left:	#$tableborder 1px solid;
					 border-spacing: 0px;
					 font:13px 		 $font;}
			.tdbg1,.tdbg2,.tdbgc,.tdbgh	{
					 border-right:	#$tableborder 1px solid;
					 border-bottom:	#$tableborder 1px solid}
		";
	}
	
	// Is custom CSS defined for form elements?
	if ($formcss) {
		$numcols = 80;
		
		if (!isset($formtextcolor)) {
			$formtextcolor = $textcolor; // Only one scheme uses this (!)
		}
		if (!isset($inputborder)) {
			$inputborder   = $tableborder;
		}
		$css.="
		textarea,input,select{
		  border:	#$inputborder solid 1px;
		  background:#000000;
		  color:	#$formtextcolor;
		  font:	10pt $font;}
		textarea:focus {
		  border:	#$inputborder solid 1px;
		  background:#000000;
		  color:	#$formtextcolor;
		  font:	10pt $font;}
		input[type=radio]{
		  border:	none;
		  background:none;
		  color:	#$formtextcolor;
		  font:	10pt $font;}
		input[type=submit]{
		  border:	#$inputborder solid 2px;
		  font:	10pt $font;}
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
	if (!$nullscheme && !$schemetype) {
		if (isset($css_extra)) {
			$css .= $css_extra . "\n";
		}
		$css.='</style>';
	}

	// $css	.= "<!--[if IE]><style type='text/css'>#f_ikachan, #f_doomcounter, #f_mustbeblind { display: none; }</style><![endif]-->	";
	
	//No gunbound rankset here (yet), stop futily trying to update it
	//updategb();
	
//$jscripts = '';

	/*
		Page overlays
	*/
	$overlay = '';
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

		$overlay = "<img id='f_ikachan' src='$ikachan' style='z-index: 999999; position: fixed; left: ". mt_rand(0,100) ."%; top: ". mt_rand(0,100) ."%;' title=\"$ikaquote\">";
	}
	

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


	
?>
<!doctype html>
<html>
	<head>
		<meta http-equiv='Content-type' content='text/html; charset=utf-8'>
		<meta name='viewport' content='width=device-width, initial-scale=1'>
		<?=$metatag?>
			<title><?=$windowtitle?></title>
			<link rel='shortcut ico' href='<?=$favicon?>.ico' type='image/x-icon'>
			<?=$css?>
	</head>
	<body>
	<?=infobar::showAll()?>
	<?php

	if (!$mini) {
	?>
		<?=$overlay?>
		<center>
			<table class='table'>
				<tr>
					<td class='tdbg1 center' colspan=3><?=$config['board-title']?>
						<span class='fonts'>
							<br>
							<form action="login.php" method="post" name="logout" style="display: inline"><?=$headlinks?></form>			
<?php		
		if (!$x_hacks['smallbrowse']) {
				// Desktop header
?>						</span>
					</td>
				</tr>
				<tr>
					<td style='width: 120px' class='tdbg2 center fonts nobr'>
						Views: <?=$dispviews?>
					</td>
					<td class='tdbg2 center fonts'>
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
							<br>
							<?=$dispviews?> views, <?=printdate()?>
						</span>
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
	// Forum online users
	if (isset($forum['id']) && in_array($scriptname, array('forum.php', 'thread.php')))
		echo "<table class='table'><td class='tdbg1 fonts center'>".fonlineusers($forum['id'])."</table>";
	
	define('HEADER_PRINTED', true);
}

/*
	if (!$x_hacks['host']) {
		if ($loguserid == 1) $config['board-title']	= "";

		$autobancount = $sql->fetchq("SELECT COUNT(*) AS cnt, MAX(`date`) as time FROM `ipbans` WHERE `reason` LIKE 'Autoban'", MYSQL_ASSOC);
		$totalbancount = $sql->fetchq("SELECT COUNT(*) AS cnt, MAX(`date`) as time FROM `ipbans`", MYSQL_ASSOC);

		$config['board-title']	.= "<br><font class=font color=#ff0000><b>If you got banned, PM an admin for a password change</b></font><br><font class=fonts>". $autobancount['cnt'] ." automatic IP bans have been issued, last ". timeunits2(ctime() - $autobancount['time']) ." ago"
			."<br>". $totalbancount['cnt'] ." IP bans have been issued in total, last ". timeunits2(ctime() - $totalbancount['time']) ." ago";
	
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