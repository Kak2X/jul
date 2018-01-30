<?php
	
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


function pageheader($windowtitle = '', $forcescheme = NULL, $forcetitle = NULL, $mini = false) {
	global 	$sql, $loguser, $config, $x_hacks, $miscdata, $scriptname, $meta, $userfields, $forum, $numcols,
			$isadmin, $issuper, $sysadmin, $isChristmas;
			
	// Load images right away
	require 'lib/colors.php';
	
	/*
		META tags & Favicon
	*/
	$metatag = '';

	if (isset($meta['noindex']))
		$metatag .= "<meta name=\"robots\" content=\"noindex,follow\" />";

	if (isset($meta['description']))
		$metatag .= "<meta name=\"description\" content=\"{$meta['description']}\" />";

	if (isset($meta['canonical'])) {
		$metatag .= "<link rel='canonical' href='{$meta['canonical']}' />";
	}
	
	$favicon = "favicon";
	if (!$x_hacks['host']) {
		$favicon .= rand(1, 8);
		if ($isChristmas) $favicon .= "x";	// Have a Santa hat
	}

	/*
		Board title (and sub titles)
	*/
	if(!$windowtitle) $windowtitle = $config['board-name'];
	
	// Overriding the default title?
	if ($miscdata['specialtitle'])
		$config['board-title'] = $miscdata['specialtitle'];	// Global
	else if ($forcetitle) 
		$config['board-title'] = $forcetitle; // Forum specific
	else 
		$config['board-title'] = "<a href='./'>{$config['board-title']}</a>"; // Leave unchanged
	
	// Admin-only info
	// in_array($loguserid,array(1,5,2100))
	if ($sysadmin) {
		$xminilog	= $sql->fetchq("SELECT COUNT(*) as count, MAX(`time`) as time FROM `minilog`");
		if ($xminilog['count']) {
			$xminilogip	= $sql->fetchq("SELECT `ip`, `banflags` FROM `minilog` ORDER BY `time` DESC LIMIT 1");
			$config['board-title']	.= "<br><a href='shitbugs.php'><span class=font style='color: #f00'><b>". $xminilog['count'] ."</b> suspicious request(s) logged, last at <b>". printdate($xminilog['time']) ."</b> by <b>". $xminilogip['ip'] ." (". $xminilogip['banflags'] .")</b></span></a>";
		}
		
		$xminilog	= $sql->fetchq("SELECT COUNT(*) as count, MAX(`time`) as time FROM `pendingusers`");
		if ($xminilog['count']) {
			$xminilogip	= $sql->fetchq("SELECT `username`, `ip` FROM `pendingusers` ORDER BY `time` DESC LIMIT 1");
			$config['board-title']	.= "<br><span class='font' style='color: #ff0'><b>". $xminilog['count'] ."</b> pending user(s), last <b>'". $xminilogip['username'] ."'</b> at <b>". printdate($xminilog['time']) ."</b> by <b>". $xminilogip['ip'] ."</b></span>";
		}
	}
	
	
	/*
		Header links at the top of every page
	*/
	$headlinks = '';
	if($loguser['id']) {
		
		if($isadmin)
			$headlinks .= '<a href="admin.php" style="font-style:italic;">Admin</a> - ';

		if($issuper) {
			$headlinks .= '<a href="shoped.php" style="font-style:italic;">Shop Editor</a> - ';
		}
		
		$headlinks.='
		<a href="javascript:document.logout.submit()">Logout</a>
		- <a href="editprofile.php">Edit profile</a>
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
	
	if (!$loguser['id'] && $miscdata['private']) {
		$headlinks2 = '<a href="faq.php">Rules/FAQ</a>';
	} else {
		$headlinks2 = "
		<a href='index.php'>Main</a>
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
	}
	
	
	
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
					<td colspan=3 class='tbl tdbg2 center fonts'>
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
	if (!$forcescheme) {
	
		//	Previewing a scheme?
		if (isset($_GET['scheme'])) {
			$loguser['scheme'] = (int) $_GET['scheme'];
			$schemepre	= true;
		} 

		// Force Xmas scheme (cue whining, as always)
		if (false && $isChristmas && !$x_hacks['host']) {
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
		$css = "<link rel='stylesheet' href='css/base.css' type='text/css'><link rel='stylesheet' type='text/css' href='css/$schemefile.css'>";
		// backwards compat
		//global $bgcolor, $linkcolor;
		//$bgcolor = "000";
		//$linkcolor = "FFF";
	} else {
		// Standard
		$css = "<link rel='stylesheet' href='css/base.css' type='text/css'>
		<style type='text/css'>
			a,.buttonlink                   { color: #$linkcolor; }
			a:visited,.buttonlink:visited   { color: #$linkcolor2; }
			a:active,.buttonlink:active     { color: #$linkcolor3; }
			a:hover,.buttonlink:hover 	    { color: #$linkcolor4; }
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
			.fonth	{font:13px $font;color:$tableheadtext}
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
	
	//$numcols=(filter_int($numcols) ? $numcols : 60);

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
		.radio{
		  border:	none;
		  background:none;
		  color:	#$formtextcolor;
		  font:	10pt $font;}
		.submit{
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


	
?><html>
	<head>
		<meta http-equiv='Content-type' content='text/html; charset=utf-8'>
		<meta name='viewport' content='width=device-width, initial-scale=1'>
		<?=$metatag?>
		<title><?=$windowtitle?></title>
		<link rel='shortcut ico' href='images/favicon/<?=$favicon?>.ico' type='image/x-icon'>
		<?=$css?>
	</head>
	<body>
	<?php

	if (!$mini) {
	?>
		<?=$overlay?>
		<center>
			<table class='table'>
				<form action='login.php' method='post' name='logout'>
					<input type='hidden' name='action' value='logout'>
					<input type='hidden' name='auth' value='<?=generate_token(30)?>'>
				</form>
				<tr>
					<td class='tbl tdbg1 center' colspan=3><?=$config['board-title']?>
						<span class='fonts'>
							<br>
							<?=$headlinks?>				
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



function pagefooter() {
	global $x_hacks, $sql, $sqldebuggers, $loguser, $config, $scriptname, $startingtime;
	
	if (!$config['affiliate-links']) {
		$affiliatelinks = "";
	} else {
		$affiliatelinks = "<form><select onchange='window.open(this.options[this.selectedIndex].value)'>{$config['affiliate-links']}</select></form>";
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
	
	
	
	?>
	
	<br>
	<br>
	<center>
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
		<?=$affiliatelinks?>
		<br>
		
		<table cellpadding=0 border=0 cellspacing=2>
			<tr>
				<td>
					<img src='images/poweredbyacmlm.gif'>
				</td>
				<td>
					<span class='fonts'>
						Acmlmboard - <?=BOARD_VERSION?><br>
						&copy;2000-<?=date("Y")?> Acmlm, Xkeeper, Inuyasha, et al. 
					</span>
				</td>
			</tr>
		</table>
		<?=$doomnum?>
		
	<?php

	/*
		( used to be in printtimedif() )
	*/
	$exectime = microtime(true) - $startingtime;

	$qseconds = sprintf("%01.6f", mysql::$time);
	$sseconds = sprintf("%01.6f", $exectime - mysql::$time);
	$tseconds = sprintf("%01.6f", $exectime);

	$queries = mysql::$queries;
	$cache   = mysql::$cachehits;

	// Old text
	//print "<br>{<font class="fonts">} Page rendered in {$tseconds} seconds.</font><br>";

	print "<br>
		<span class='fonts'>{$queries} database queries". (($cache > 0) ? ", {$cache} query cache hits" : "") .".</span>
		<table class='fonts' style='border-spacing: 0px'>
			<tr><td align=right>Query execution time:&nbsp;</td><td>{$qseconds} seconds</td></tr>
			<tr><td align=right>Script execution time:&nbsp;</td><td>{$sseconds} seconds</td></tr>
			<tr><td align=right>Total render time:&nbsp;</td><td>{$tseconds} seconds</td></tr>
		</table>";
		
	// Print errors locally
	print error_printer(true, ($loguser['powerlevel'] == 4 || $config['always-show-debug']), $GLOBALS['errors']);

	// Print mysql queries
	if (mysql::$debug_on && in_array($_SERVER['REMOTE_ADDR'], $sqldebuggers) || $loguser['id'] == 1 || $config['always-show-debug']) {
		if (!isset($_GET['debugsql']) && !$config['always-show-debug']) {
			// Link to enable debugging
			print "<br><a href='".$_SERVER['REQUEST_URI'].(($_SERVER['QUERY_STRING']) ? "&" : "?")."debugsql=1'>Useless mySQL query debugging shit</a>";
		} else {
		
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
	

	if (!$x_hacks['host']) {
		$pages	= array(
			"index.php",
			"thread.php",
			"forum.php",
		);
		if (in_array($scriptname, $pages)) {
			$sql->queryp("INSERT INTO rendertimes SET page = ?, time = ?, rendertime  = ?", ["/$scriptname", ctime(), $exectime]);
			$sql->query("DELETE FROM rendertimes WHERE time < '". (ctime() - 86400 * 14) ."'");
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