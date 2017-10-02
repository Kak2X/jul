<?php

function userfields(){
	return 'u.posts,u.sex,u.`group`,u.displayname,u.main_subgroup,u.ban_expire,u.birthday,u.aka,u.namecolor,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.imood';
}


function postcode($post, $set, $controls){
	global $config, $tlayout, $textcolor, $numdir, $numfil, $hacks, $x_hacks, $loguser;
	
	$exp        = calcexp($post['posts'],(ctime()-$post['regdate']) / 86400);
	$lvl        = calclvl($exp);
	$expleft    = calcexpleft($exp);
	
	if ($tlayout == 1) {
		// Without numgfx (standard)
		$level		= "Level: $lvl";
		$poststext	= "Posts: ";
		$postnum	= $post['num'] ? "{$post['num']}/" : "";
		$posttotal	= $post['posts'];
		$experience	= "EXP: $exp<br>For next: $expleft";
		$totalwidth	= 96;
		$barwidth	= $totalwidth-round(@($expleft/totallvlexp($lvl))*$totalwidth);
		
		if ($barwidth < 1) $barwidth=0;
		
		if ($barwidth > 0) $baron="<img src='images/{$numdir}bar-on.gif' width=$barwidth height=8>";
		else $baron = "";
		
		if ($barwidth < $totalwidth) $baroff="<img src='images/{$numdir}bar-off.gif' width=".($totalwidth-$barwidth)." height=8>";
		else $baroff = "";
		$bar="<br><img src='images/{$numdir}barleft.gif' height=8>$baron$baroff<img src='images/{$numdir}barright.gif' height=8>";

	} else {
		// With numgfx (old)
		$numdir = 'num1/';
		$level		= "<img src='images/{$numdir}level.gif' width=36 height=8><img src='numgfx.php?n=$lvl&l=3&f=$numfil' height=8>";
		$experience	= "<img src='images/{$numdir}exp.gif' width=20 height=8><img src='numgfx.php?n=$exp&l=5&f=$numfil' height=8><br><img src='images/{$numdir}fornext.gif' width=44 height=8><img src='numgfx.php?n=$expleft&l=2&f=$numfil' height=8>";
		$poststext	= "<img src='images/_.gif' height=2><br><img src='images/{$numdir}posts.gif' width=28 height=8>";
		$postnum	= $post['num'] ? "<img src='numgfx.php?n={$post['num']}/&l=5&f=$numfil' height=8>" : "";
		$posttotal	= "<img src='numgfx.php?n={$post['posts']}&f=$numfil'".($post['num']?'':'&l=4')." height=8>";
		$totalwidth	= 56;
		$barwidth	= $totalwidth-round(@($expleft/totallvlexp($lvl))*$totalwidth);
		
		if($barwidth<1) $barwidth=0;
		
		if($barwidth>0) $baron="<img src=images/{$numdir}bar-on.gif width=$barwidth height=8>";
		
		if($barwidth<$totalwidth) $baroff="<img src=images/{$numdir}bar-off.gif width=".($totalwidth-$barwidth)." height=8>";
		$bar="<br><img src='images/{$numdir}barleft.gif' width=2 height=8>$baron$baroff<img src='images/{$numdir}barright.gif' width=2 height=8>";
	}

	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}

	$post['edited']	= filter_string($post['edited']);
	$postdate       = printdate($post['date']);
	$noobspan       = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
		
	//if ($post['edited']) {
		// Old post edited marker
		// $post['text'] .= "<hr><font class='fonts'>{$post['edited']}";
	//}
	
	
	// Deleted user has its own layout
	// RIP to all the others since we're not Jul
	if ($post['deleted']) {
		return 
		"<div style='position:relative'>
			<table class='table' id='{$post['id']}'>
				<tr>
					<td class='tbl tdbg{$set['bg']} vatop' style='border-bottom: none; min-width: 200px'>
						{$set['userlink']}</span>
					</td>
					<td class='tbl tdbg{$set['bg']} vatop w' style='height: 1px'>
						<table class='w fonts' style='border-spacing: 0px; padding: 2px'>
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
					<td class='tbl tdbg{$set['bg']} vatop fonts'>
					</td>
					<td class='tbl tdbg{$set['bg']} vatop' id='post{$post['id']}'>
						{$post['text']}
					</td>
				</tr>
			</table>
		</div>";
	} else if ($post['uid'] == $config['deleted-user-id']) {
		$fcol1			= "#bbbbbb"; // Font color
		$fcol2			= "#555555"; // Desc color
		$fcol3			= "#181818"; // BG   color
		
		// CSS for the unified left bar
		$sidebar_css = 
			"vertical-align: top;".
			"text-align: center;".
			"background: $fcol3;".
			"font-size: 14px;".
			"color: $fcol1;".
			"font-family: Verdana, sans-serif;".
			"padding-top: .5em;".
			"min-width: 200px;";
			
		// CSS for the description text "Collection of nobodies"
		$desc_css = 
			"letter-spacing: 0px;".
			"color: $fcol2;".
			"font-size: 10px;";
		
		// CSS for post title bar
		$topbar_css = 
			"vertical-align: top;".
			"width: 100%;".
			"background: $fcol3;".
			"font-size: 12px;".
			"color: $fcol1;".
			"font-family: Verdana, sans-serif;".
			"height: 1px;";
		
		// CSS for post content
		$mainbar_css =
			"vertical-align: top;".
			"background: $fcol3;".
			"height: 220px;";

		return 
		"<table class='table' id='{$post['id']}'>
			<tr>
				<td class='tbl tdbg{$set['bg']}' rowspan=2 style='{$sidebar_css}'>
					{$set['userlink']}
					<br><span style='{$desc_css}'>Collection of nobodies</span>
				</td>
				<td class='tbl tdbg{$set['bg']}' style='{$topbar_css}'>
					<table class='w fonts' style='border-spacing: 0px; padding: 2px'>
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
				<td class='tbl tdbg{$set['bg']}' style='{$mainbar_css}' id='post{$post['id']}'>
					{$post['headtext']}
					{$post['text']}
					{$post['signtext']}
				</td>
			</tr>
		</table>";

		
	} else { // else if (!(in_array($post['uid'], $sidebars) && !$x_hacks['host']) || $loguser['viewsig'] == 0)
		
		// Default layout
			$syndrome = syndrome($post['act']);
	
		// Other stats
		if ($post['lastposttime']) {
			$sincelastpost	= 'Since last post: '.timeunits(ctime()-$post['lastposttime']);
		} else {
			$sincelastpost = "";
		}
		
		$lastactivity	= 'Last activity: '.timeunits(ctime()-$post['lastactivity']);
		$since			= 'Since: '.printdate($post['regdate'], PRINT_DATE);
		
		// Each topbar override silently adds its own id to prevent CSS conflicts
		if (!$post['headid'])
			$csskey = "_x".$post['id'];
		else
			$csskey = "_".$post['headid'];
		
		return 
		"<div style='position:relative'>
			<table class='table contbar{$post['uid']}{$csskey}' id='{$post['id']}'>
				<tr>
					<td class='tbl tdbg{$set['bg']} topbar{$post['uid']}{$csskey}_1 vatop' style='border-bottom: none; min-width: 200px'>
						{$noobspan}{$set['userlink']}</span>
					</td>
					<td class='tbl tdbg{$set['bg']} topbar{$post['uid']}{$csskey}_2 vatop w' style='height: 1px'>
						<table class='w fonts' style='border-spacing: 0px; padding: 2px'>
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
					<td class='tbl tdbg{$set['bg']} sidebar{$post['uid']}{$csskey} vatop fonts'>
						{$set['userrank']}
						$syndrome<br>
						$level$bar<br>
						{$set['userpic']}<br>
						". ($hacks['noposts'] ? "" : "$poststext$postnum$posttotal<br>") ."
						$experience<br>
						<br>
						$since<br>
						{$set['location']}<br>
						<br>
						$sincelastpost<br>
						$lastactivity<br>
						<br>
					</td>
					<td class='tbl tdbg{$set['bg']} mainbar{$post['uid']}{$csskey} vatop' style='height: 220px' id='post{$post['id']}'>
						{$post['headtext']}
						{$post['text']}
						{$post['signtext']}
					</td>
				</tr>
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
