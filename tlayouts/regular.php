<?php

function userfields(){
	return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.picture,u.moodurl,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.imood,u.ban_expire';
}

function postcode($post,$set){
	global $config, $controls, $tlayout, $textcolor, $numfil, $hacks, $x_hacks, $loguser, $barimg;
	static $numdir;
	
	$exp		= calcexp($post['posts'],(time()-$post['regdate']) / 86400);
	$lvl		= calclvl($exp);
	$expleft	= calcexpleft($exp);
	$postdate	= printdate($post['date']);
	// Post syndrome text
	$syndrome 	= syndrome($post['act']);
	
	// Thread link support in the top bar (for modes like "threads by user")
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}
	
	// Default layout
	$csskey = getcsskey($post);
	
	// Sidebar options
	$sidebaronecell = $post['sidebartype'] & 1;
	$sidebartype    = $post['sidebartype'] >> 1;
	if ($sidebartype == 2 && !file_exists("sidebars/{$post['uid']}.php"))
		$sidebartype = 0;
	
	//--
	$data = new tlayout_ext_input();
	$data->csskey           = $csskey;
	$data->sidebar_one_cell = $sidebaronecell;
	// Keep count of the cell size (for single column mode and option_row_top extras)
	$data->rowspan          = $sidebaronecell ? 2 : 1;
	//--
	
	$opt = get_tlayout_opts('regular', $set, $post, $data);
	//--
	if ($set['warntext']) {
		$opt->option_rows_top .= $set['warntext'];
	}

	if ($set['highlighttext']) {
		$opt->option_rows_top .= $set['highlighttext'];
	}
	//--
//	if (true) {
		// Extra row specific to the "Regular Extended" layout
		$icqicon = $imood = "";
		if (($tlayout == 6 || $tlayout == 12) && $sidebartype != 1) {
			//++$rowspan;
			
			//if ($post['icq']) $icqicon="<a href='http://wwp.icq.com/{$post['icq']}#pager'><img src='http://wwp.icq.com/scripts/online.dll?icq={$post['icq']}&img=5' border=0 width=13 height=13 align=absbottom></a>";
			if ($post['imood']) {
				$imood = "<img src='http://www.imood.com/query.cgi?email={$post['imood']}&type=1&fg={$textcolor}&trans=1' style='height: 15px' align=absbottom>";
			}
			
			$statustime = time() - 300;
			if ($post['lastactivity'] < $statustime) {
				$status = htmlspecialchars($post['name'])." is <span class='b' style='color: #FF0000'>Offline</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
			} elseif($post['lastactivity'] > $statustime) {
				$status = htmlspecialchars($post['name'])." is <span class='b' style='color: #00FF00'>Online</span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
			}
			
			// Cache the auth key
			static $tokenstr;
			if (!isset($tokenstr)) $tokenstr = "&auth=".generate_token(TOKEN_MGET);
			
			$un_b = $post['blockedlayout'] ? "Unb" : "B";
			$opt->option_rows_bottom .= "
			<tr>
				<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}_opt fonts'><b>Status</b>: {$status}</td>
				<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey}_opt fonts' style='width: 80%'>&nbsp;<b>Options</b>:
					<a href='newpmthread.php?userid={$post['uid']}'>Send PM</a> - 
					<a href='blocklayout.php?action=block&id={$post['uid']}{$tokenstr}'>{$un_b}lock layout</a> - 
					<a href='forum.php?user={$post['uid']}'>Threads by user</a> - 
					<a href='thread.php?mode=user&user={$post['uid']}'>Posts by user</a>
				</td>
			</tr>";
		}
		
		$height   = $post['deleted'] ? 0 : 220;
		
		if ($post['deleted']) {
			// If a post is deleted, blank out the sidebar regardless of options.
			$sidebar = "&nbsp;";
		} else if ($sidebartype != 2 && (!$post['sidebartext'] || !$loguser['viewsig'])) {
			// Default sidebarm with all of the default vars that come with it
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
			
			// Other stats
			if ($post['lastposttime']) {
				$sincelastpost= 'Since last post: '.timeunits(time()-$post['lastposttime']);
			} else {
				$sincelastpost = "";
			}
			$lastactivity	= 'Last activity: '.timeunits(time()-$post['lastactivity']);
			$since			= 'Since: '.printdate($post['regdate'], true);
			
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
			// Custom sidebar using the 'sidebartext' field (an even bigger mistake)
			$sidebar = $post['sidebartext'];
			
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
					//'&comppostprep&'  => $postnum.$posttotal,
					
					// Images and whatever
					'&avatar&'        => $set['userpic'],
					'&rank&'          => $set['userrank'],
					'&syndrome&'      => $syndrome,
					
					// RPG
					'&level&'         => pretty_nan($lvl),
					'&exp&'           => pretty_nan($exp),
					'&levelexp&'      => pretty_nan(calclvlexp($lvl)),
					'&totallevelexp&' => pretty_nan(totallvlexp($lvl)),

					// Dates
					'&lastactivity&'  => timeunits(time()-$post['lastactivity']),
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
					$expbar_apply = function ($m) use ($replace, $barimg) {
						$width = isset($m[2]) ? $m[2] : 96;
						return drawprogressbar($width, 8, $replace['&exp&'] - $replace['&levelexp&'], $replace['&totallevelexp&'], $barimg);
					};
				$sidebar = preg_replace_callback("'&(expbar)(?:_(\d*))?&'", $expbar_apply, $sidebar);
				
			}
		}
		
		
		if ($sidebaronecell) {
			// Single cell sidebar
			$topbar1 = "
			<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}' rowspan={$opt->rowspan} valign=top>
				{$set['userspan']}{$set['userlink']}</span>{$opt->top_left}
				<br>{$sidebar}
				<img src='images/_.gif' width=200 height=1>
			</td>";
			$sidebar = "";
		} else {
			// Normal
			$topbar1 = "
			<td class='tdbg{$set['bg']} topbar{$post['uid']}{$csskey}_1' rowspan={$opt->rowspan} valign=top style='border-bottom: none'>
				{$set['userspan']}{$set['userlink']}</span>{$opt->top_left}
			</td>";
			$sidebar = "
			<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}' style='border-top: none' valign=top>
				{$sidebar}
				<img src='images/_.gif' width=200 height=1>
			</td>";
		}
		
		// Position relative div moved to the CSS defn for the .post
		// Incidentally, this fixes an issue with DCII as browsers would close the body container defining the padding.
		return 
		"{$set['highlightline']}
			<table class='table post tlayout-regular contbar{$post['uid']}{$csskey}' id='{$post['id']}'>
				<tr>
					{$topbar1}
					<td class='tdbg{$set['bg']} topbar{$post['uid']}{$csskey}_2' valign=top height=1>
						<table cellspacing=0 cellpadding=2 class='w fonts'>
							<tr>
								<td>
									{$set['new']}{$set['highlightctrl']}Posted on $postdate$threadlink{$set['edited']}
								</td>
								<td class='right'>
									".implode(" | ", $controls)."
								</td>
								{$opt->top_right}
							</tr>
						</table>
					</td>
				</tr>
				
				<tr>
					{$sidebar}
					<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey} w' valign=top height={$height} id='post{$post['id']}'>
						{$opt->option_rows_top}
						{$post['headtext']}
						{$post['text']}
						{$set['attach']}
						{$post['signtext']}
					</td>
				</tr>
				{$opt->option_rows_bottom}
			</table>
		";
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

