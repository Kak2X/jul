<?php

function userfields(){
	return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.picture,u.moodurl,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.imood,u.ban_expire';
}

function postcode($post,$set){
	global $config, $controls, $tlayout, $textcolor, $numfil, $hacks, $x_hacks, $loguser, $barimg;
	static $numdir;
	
	$exp		= calcexp($post['posts'],(ctime()-$post['regdate']) / 86400);
	$lvl		= calclvl($exp);
	$expleft	= calcexpleft($exp);
	
	if ($tlayout == 1) {
		// Without numgfx (standard)
		$level		= "Level: $lvl";
		$poststext	= "Posts: ";
		$postnum	= $post['num'] ? "{$post['num']}/" : "";
		$posttotal	= $post['posts'];
		$experience	= "EXP: $exp<br>For next: $expleft";
		$barwidth   = 96;
	} else {
		// With numgfx (old)
		if ($numdir === NULL) $numdir = get_complete_numdir();
		//$numdir     = 'num1/';
		$level		= "<img src='numgfx/{$numdir}level.png' width=36 height=8><img src='numgfx.php?n=$lvl&l=3&f=$numfil' height=8>";
		$experience	= "<img src='numgfx/{$numdir}exp.png' width=20 height=8><img src='numgfx.php?n=$exp&l=5&f=$numfil' height=8><br><img src='numgfx/{$numdir}fornext.png' width=44 height=8><img src='numgfx.php?n=$expleft&l=2&f=$numfil' height=8>";
		$poststext	= "<img src='images/_.gif' height=2><br><img src='numgfx/{$numdir}posts.png' width=28 height=8>";
		$postnum	= $post['num'] ? "<img src='numgfx.php?n={$post['num']}/&l=5&f=$numfil' height=8>" : "";
		$posttotal	= "<img src='numgfx.php?n={$post['posts']}&f=$numfil'".($post['num']?'':'&l=4')." height=8>";
		$barwidth   = 56;
	}

	// RPG Level bar
	$bar = "<br>".drawprogressbar($barwidth, 8, $exp - calclvlexp($lvl), totallvlexp($lvl), $barimg);
	
	$syndrome = syndrome($post['act']);
	
	// Other stats
	if ($post['lastposttime']) {
		$sincelastpost	= 'Since last post: '.timeunits(ctime()-$post['lastposttime']);
	} else {
		$sincelastpost = "";
	}
	
	$lastactivity	= 'Last activity: '.timeunits(ctime()-$post['lastactivity']);
	$since			= 'Since: '.printdate($post['regdate'], true);
	$postdate		= printdate($post['date']);
	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}

	$post['edited']	= filter_string($post['edited']);
	//if ($post['edited']) {
		// Old post edited marker
		// $post['text'] .= "<hr><font class='fonts'>{$post['edited']}";
	//}
	
	// Deleted user has its own layout
	// RIP to all the others since we're not Jul
	
	if ($post['uid'] == $config['deleted-user-id']) {
		$fcol1			= "#bbbbbb";
		$fcol2			= "#555555";
		$fcol3			= "#181818";

		return 
		"<table class='table' id='{$post['id']}'>
			<tr>
				<td class='tdbg{$set['bg']}' valign=top rowspan=2 style='text-align: center; background: $fcol3; font-size: 14px; color: $fcol1; font-family: Verdana, sans-serif; padding-top: .5em'>
					{$set['userlink']}
					<br><span style='letter-spacing: 0px; color: $fcol2; font-size: 10px;'>Collection of nobodies</span>
					<br><img src='images/_.gif' width=200 height=200>
				</td>
				<td class='tdbg{$set['bg']}' valign=top height=1 style='width: 100%; background: $fcol3; font-size: 12px; color: $fcol1; font-family: Verdana, sans-serif'>
					<table cellspacing=0 cellpadding=2 width=100% class=fonts>
						<tr>
							<td>
								Posted on $postdate$threadlink{$post['edited']}
							</td>
							<td style='width: 255px' class='nobr'>
								{$controls['quote']}{$controls['edit']}{$controls['ip']}
							</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td class='tdbg{$set['bg']}' valign=top style='background: $fcol3; padding: 0;'>
					{$post['headtext']}
					{$post['text']}
					{$set['attach']}
					{$post['signtext']}
				</td>
			</tr>
		</table>";

		
	} else { // else if (!(in_array($post['uid'], $sidebars) && !$x_hacks['host']) || $loguser['viewsig'] == 0)
	

		$set['location'] = str_ireplace("&lt;br&gt;", "<br>", $set['location']);
		
		// Default layout
		if (!$post['headid']) {
			$csskey = "_x".$post['id'];
		} else {
			$csskey = "_".$post['headid'];
		}
		
		// EXTENDED LAYOUT OPTS
		$icqicon = $imood = "";
		if ($tlayout == 6) {
			//if ($post['icq']) $icqicon="<a href='http://wwp.icq.com/{$post['icq']}#pager'><img src='http://wwp.icq.com/scripts/online.dll?icq={$post['icq']}&img=5' border=0 width=13 height=13 align=absbottom></a>";
			if ($post['imood']) {
				$imood = "<img src='http://www.imood.com/query.cgi?email={$post['imood']}&type=1&fg={$textcolor}&trans=1' style='height: 15px' align=absbottom>";
			}
			
			$statustime = ctime() - 300;
			if ($post['lastactivity'] < $statustime) {
				$status = htmlspecialchars($post['name'])." is <span class='b' style='color: #FF0000'>Offline</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
			} elseif($post['lastactivity'] > $statustime) {
				$status = htmlspecialchars($post['name'])." is <span class='b' style='color: #00FF00'>Online</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
			}
			
			static $tokenstr;
			if (!isset($tokenstr)) $tokenstr = "&auth=".generate_token(TOKEN_MGET);
			
			$un_b = $post['blockedlayout'] ? "Unb" : "B";
			$optionrow = "
			<tr>
				<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}_opt fonts'><b>Status</b>: {$status}</td>
				<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey}_opt fonts' style='width: 80%'>&nbsp;<b>Options</b>:
					<a href='sendprivate.php?userid={$post['uid']}'>Send PM</a> - 
					<a href='blocklayout.php?action=block&id={$post['uid']}{$tokenstr}'>{$un_b}lock layout</a> - 
					<a href='forum.php?user={$post['uid']}'>Threads by user</a> - 
					<a href='thread.php?user={$post['uid']}'>Posts by user</a>
				</td>
			</tr>";
		} else {
			$optionrow = "";
		}
		
		$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
		$height   = $post['deleted'] ? 0 : 220;
		
		if ($post['deleted']) {
			$height  = 0;
			$sidebar = "&nbsp;";
		} else {
			$height = 220;
			$sidebar = "
				{$set['userrank']}
				$syndrome<br>
				$level$bar<br>
				{$set['userpic']}<br>
				". (filter_bool($hacks['noposts']) ? "" : "$poststext$postnum$posttotal<br>") ."
				$experience<br>
				<br>
				$since<br>
				{$set['location']}<br>
				<br>
				$sincelastpost<br>
				$lastactivity<br>
				$icqicon$imood<br>
			";
		}
		
		return 
		"<div style='position:relative'>
			<table class='table contbar{$post['uid']}{$csskey}' id='{$post['id']}'>
				<tr>
					<td class='tdbg{$set['bg']} topbar{$post['uid']}{$csskey}_1' valign=top style='border-bottom: none'>
						{$noobspan}{$set['userlink']}</span>
					</td>
					<td class='tdbg{$set['bg']} topbar{$post['uid']}{$csskey}_2' valign=top height=1 width=100%>
						<table cellspacing=0 cellpadding=2 class='w fonts'>
							<tr>
								<td>
									Posted on $postdate$threadlink{$post['edited']}
								</td>
								<td class='nobr' style='width: 255px'>
									{$controls['quote']}{$controls['edit']}{$controls['ip']}
								</td>
							</tr>
						</table>
					</td>
				</tr>
				<tr>
					<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey} fonts' valign=top>
						{$sidebar}
						<img src='images/_.gif' width=200 height=1>
					</td>
					<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey}' valign=top height={$height} id='post{$post['id']}'>
						{$post['headtext']}
						{$post['text']}
						{$set['attach']}
						{$post['signtext']}
					</td>
				</tr>
				{$optionrow}
			</table>
		</div>";
	}
	
}



function kittynekomeowmeow($p) {
	global $loguser;
	$kitty	= array("meow", "mrew", "mew", "mrow", "mrrrr", "mrowl", "rrrr", "mrrrrow", "mreeeew",);
	$punc	= array(",", ".", "!", "?");
	$p		= preg_replace('/\s\s+/', ' ', $p);

	$c		= substr_count($p, " ");
	for ($i = 0; $i < $c; $i++) {
		$mi	= array_rand($kitty);
		$m	.= ($m ? " " : "") . $kitty[$mi];
		$l	= false;
		if (mt_rand(0,7) == 7) {
			$pi	= array_rand($punc);
			$m	.= $punc[$pi];
			$l	= true;
		}
	}

	if ($l != true) {
		$pi	= array_rand($punc);
		$m	.= $punc[$pi];
	}

	// if ($loguser['id'] == 1)
	return $m ." :3";
}

