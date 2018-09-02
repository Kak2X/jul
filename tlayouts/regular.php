<?php

function userfields(){
	return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.picture,u.moodurl,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.imood,u.ban_expire,u.sidebartype,u.sidebar';
}

function postcode($post,$set){
	global $config, $controls, $tlayout, $textcolor, $numfil, $hacks, $x_hacks, $loguser, $barimg;
	static $numdir;
	
	$exp		= calcexp($post['posts'],(ctime()-$post['regdate']) / 86400);
	$lvl		= calclvl($exp);
	$expleft	= calcexpleft($exp);
	
	if ($tlayout == 1 || $tlayout == 6) {
		// Without numgfx (standard)
		$level		= "Level: $lvl";
		$poststext	= "Posts: ";
		$postnum	= $post['num'] ? "{$post['num']}/" : "";
		$posttotal	= $post['posts'];
		$experience	= "EXP: $exp<br>For next: $expleft";
		$barwidth   = 96;
	} else {
		// With numgfx ("old")
		if ($numdir === NULL) $numdir = get_complete_numdir();
		
		// Left "column" span
		// Necessary after removing the padding from the generated numgfx itself (it wouldn't work well with custom sidebars)
		$lcs = "<span style='width: 50px; display: inline-block'>";
		$lcse = "</span>";
		
		//$numdir     = 'num1/';
		$level		= "{$lcs}<img src='numgfx/{$numdir}level.png' width=36 height=8>{$lcse}<img src='numgfx.php?n=$lvl&f=$numfil' height=8>"; // &l=3
		$experience	= "{$lcs}<img src='numgfx/{$numdir}exp.png' width=20 height=8>{$lcse}<img src='numgfx.php?n=$exp&f=$numfil' height=8><br>{$lcs}<img src='numgfx/{$numdir}fornext.png' width=44 height=8>{$lcse}<img src='numgfx.php?n=$expleft&f=$numfil' height=8>"; // &l=5 - &l=2
		$poststext	= "<div style='height: 2px'></div>{$lcs}<img src='numgfx/{$numdir}posts.png' width=28 height=8>{$lcse}";
		$postnum	= $post['num'] ? "<img src='numgfx.php?n={$post['num']}/&f=$numfil' height=8>" : ""; // &l=5
		$posttotal	= "<img src='numgfx.php?n={$post['posts']}&f=$numfil'".($post['num']?'':'&l=4')." height=8>";
		$barwidth   = 56;
		
		unset($lcs, $lcse);
	}
	
	// RPG Level bar
	$bar = "<br>".drawprogressbar($barwidth, 8, $exp - calclvlexp($lvl), totallvlexp($lvl), $barimg);
	
	// Post syndrome text
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
	
	
	// Thread link support in the top bar (for modes like "threads by user")
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}

	$post['edited']	= filter_string($post['edited']);
	//if ($post['edited']) {
		// Old post edited marker
		// $post['text'] .= "<hr><font class='fonts'>{$post['edited']}";
	//}
	
	
	// Default layout
	$csskey = getcsskey($post);
	
	// Sidebar options
	$sidebaronecell = $post['sidebartype'] & 1;
	$sidebartype    = $post['sidebartype'] >> 1;
	if ($sidebartype == 2 && !file_exists("sidebars/{$post['uid']}.php"))
		$sidebartype = 0;
	
	// Keep count of the cell size (for single column mode)
	$rowspan   = 2;
	
	// Rating icons
	$optionrow = "";
	if ($set['rating']) {
		if ($sidebaronecell) {
			$ratingside = "";
			++$rowspan;
		} else {
			$ratingside = "<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}_opt fonts'></td>";
		}
		$optionrow .= "<tr>
			{$ratingside}
			<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey}_opt fonts' style='height: 1px; width: 80%'>{$set['rating']}</td>
		</tr>"; // &nbsp;<b>Post ratings:</b>
	}
	
//	if (true) {
	
		$set['location'] = str_ireplace("&lt;br&gt;", "<br>", $set['location']);
		
		// Extra row specific to the "Regular Extended" layout
		$icqicon = $imood = "";
		if (($tlayout == 6 || $tlayout == 12) && $sidebartype != 1) {
			//++$rowspan;
			
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
			
			// Cache the auth key
			static $tokenstr;
			if (!isset($tokenstr)) $tokenstr = "&auth=".generate_token(TOKEN_MGET);
			
			$un_b = $post['blockedlayout'] ? "Unb" : "B";
			$optionrow .= "
			<tr>
				<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}_opt fonts'><b>Status</b>: {$status}</td>
				<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey}_opt fonts' style='width: 80%'>&nbsp;<b>Options</b>:
					<a href='sendprivate.php?userid={$post['uid']}'>Send PM</a> - 
					<a href='blocklayout.php?action=block&id={$post['uid']}{$tokenstr}'>{$un_b}lock layout</a> - 
					<a href='forum.php?user={$post['uid']}'>Threads by user</a> - 
					<a href='thread.php?user={$post['uid']}'>Posts by user</a>
				</td>
			</tr>";
		}
		
		$noobspan = $post['noob'] ? "<span class='userlink' style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span class='userlink'>";
		$height   = $post['deleted'] ? 0 : 220;
		
		if ($post['deleted']) {
			// If a post is deleted, blank out the sidebar regardless of options.
			$sidebar = "&nbsp;";
		} else if ($sidebartype != 2 && (!$post['sidebar'] || !$loguser['viewsig'])) {
			// Default sidebar
			$sidebar = "<span class='fonts'>
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
			</span>";
		} else if ($sidebartype == 2) {
			// Custom sidebar using PHP code (a mistake)
			include "sidebars/{$post['uid']}.php";
		} else {
			// Custom sidebar using the 'sidebar' field (an even bigger mistake)
			$sidebar = $post['sidebar'];
			
			if (filter_bool($hacks['noposts'])) {
				$post['num'] = $post['posts'] = $postnum = $posttotal = "";
			}
			
			if (strpos($sidebar, '&') !== false) {
				$replace = array(
					// Username
					'&user&'          => $post['name'],
					'&namecolor&'     => $post['namecolor'],
					'&userlink&'      => $set['userlink'],
					
					// Post counters
					'&posts&'         => $post['posts'],
					'&numpost&'       => $post['num'] ? $post['num'] : "",
					'&comppost&'      => ($post['num'] ? "{$post['num']}/" : "").$post['posts'],
					'&comppostprep&'  => $postnum.$posttotal,
					
					// Images and whatever
					'&avatar&'        => $set['userpic'],
					'&rank&'          => $set['userrank'],
					'&syndrome&'      => $syndrome,
					
					// RPG
					'&exp&'           => $exp,
					'&levelexp&'      => calclvlexp($lvl),
					'&totallevelexp&' => totallvlexp($lvl),

					// Dates
					'&lastactivity&'  => timeunits(ctime()-$post['lastactivity']),
					'&since&'         => printdate($post['regdate'], true),
					'&location&'      => $set['location'],
					
				);
				$sidebar = strtr($sidebar, $replace);
				
				// Extra tags start here
				
				// Numgfx to any set
				// Note that the first four only contain numbers and not any of the extra graphics
				// A fix to this would be drawing those, but...
				$allowed_numgfx = "jul|ccs|death|ymar|num(?:[1-9]|dani|dig|ff9)";
				
				static $numgfx_apply, $expbar_apply;
				if ($numgfx_apply === NULL) 
					$numgfx_apply = function ($m) use ($replace) {
						// Replace the numdir with the one we need (and restore it after we're done)
						global $numdir;
						$olddir = $numdir;
						$numdir = $m[2]."/";
						//--
						$size = isset($m[3]) ? $m[3] : 1;
						$out = generatenumbergfx($replace["&{$m[1]}&"], 0, $size);
						//--
						$numdir = $olddir;
						return $out;
					};
				$sidebar = preg_replace_callback("'&(posts)_($allowed_numgfx)(?:_(\d))?&'", $numgfx_apply, $sidebar);
				$sidebar = preg_replace_callback("'&(numpost)_($allowed_numgfx)(?:_(\d))?&'", $numgfx_apply, $sidebar);
				$sidebar = preg_replace_callback("'&(comppost)_($allowed_numgfx)(?:_(\d))?&'", $numgfx_apply, $sidebar);
					
				// EXP Bar generator
				if ($expbar_apply === NULL)
					$expbar_apply = function ($m) use ($replace, $barimg, $barwidth) {
						$width = isset($m[2]) ? $m[2] : $barwidth;
						return drawprogressbar($width, 8, $replace['&exp&'] - $replace['&levelexp&'], $replace['&totallevelexp&'], $barimg);
					};
				$sidebar = preg_replace_callback("'&(expbar)(?:_(\d*))?&'", $expbar_apply, $sidebar);
				
			}
			$sidebar = xssfilters($sidebar);
		}
		
		
		if ($sidebaronecell) {
			// Single cell sidebar
			$topbar1 = "
			<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}' rowspan={$rowspan} valign=top>
				{$noobspan}{$set['userlink']}</span>
				<br>{$sidebar}
				<img src='images/_.gif' width=200 height=1>
			</td>";
			$sidebar = "";
		} else {
			// Normal
			$topbar1 = "
			<td class='tdbg{$set['bg']} topbar{$post['uid']}{$csskey}_1' valign=top style='border-bottom: none'>
				{$noobspan}{$set['userlink']}</span>
			</td>";
			$sidebar = "
			<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}' valign=top>
				{$sidebar}
				<img src='images/_.gif' width=200 height=1>
			</td>";
		}
		
		
		return 
		"<div style='position:relative'>
			<table class='table post tlayout-regular contbar{$post['uid']}{$csskey}' id='{$post['id']}'>
				<tr>
					{$topbar1}
					<td class='tdbg{$set['bg']} topbar{$post['uid']}{$csskey}_2' valign=top height=1>
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
					{$sidebar}
					<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey} w' valign=top height={$height} id='post{$post['id']}'>
						{$post['headtext']}
						{$post['text']}
						{$set['attach']}
						{$post['signtext']}
					</td>
				</tr>
				{$optionrow}
			</table>
		</div>";
//	}
	
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

