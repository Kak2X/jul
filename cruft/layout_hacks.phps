<?php


	if (!$x_hacks['host']) {
		if ($loguserid == 1) $config['board-title']	= "";

		$autobancount = $sql->fetchq("SELECT COUNT(*) AS cnt, MAX(`date`) as time FROM `ipbans` WHERE `reason` LIKE 'Autoban'", MYSQL_ASSOC);
		$totalbancount = $sql->fetchq("SELECT COUNT(*) AS cnt, MAX(`date`) as time FROM `ipbans`", MYSQL_ASSOC);

		$config['board-title']	.= "<br><font class=font color=#ff0000><b>If you got banned, PM an admin for a password change</b></font><br><font class=fonts>". $autobancount['cnt'] ." automatic IP bans have been issued, last ". timeunits2(time() - $autobancount['time']) ." ago"
			."<br>". $totalbancount['cnt'] ." IP bans have been issued in total, last ". timeunits2(time() - $totalbancount['time']) ." ago";
	
		$config['board-title']= "<span style='font-size: 40pt; font-variant: small-caps; color: #f33;'>The Hivemind Collective</span><br><span style='font-size: 6pt; font-variant: small-caps; color: #c00'>(because a group of friends sharing a similar opinion is totally hivemind, dood!)</span>";
	}


#	if (!$x_hacks['host'] && true) {
#		$config['board-title']	.= "</a><br><a href='/thread.php?id=10372'><span style='font-size: 14px;'>Now with more celebrations!</span></a>";
#	}


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
	}
	
//$config['board-title'] = "<a href='./'><img src=\"images/christmas-banner-blackroseII.png\" title=\"Not even Christmas in July, no. It's May.\"></a>";

// PONIES!!!
// if($forumid==30) $config['board-title'] = "<a href='./'><img src=\"images/poniecentral.gif\" title=\"YAAAAAAAAAAY\"></a>";
// end PONIES!!!