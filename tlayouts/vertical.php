<?php
	
function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.picture,u.moodurl,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.imood,u.ban_expire';}

function postcode($post,$set){
    global $loguser,$controls,$tlayout,$textcolor,$numdir,$numfil,$barimg;

	// Sidebar info
	$exp		= calcexp($post['posts'],(ctime()-$post['regdate']) / 86400);
	$lvl		= calclvl($exp);
	$expleft	= calcexpleft($exp);
	
	$level      = "Level {$lvl}";
	$poststext  = "Post ";
	$postnum    = $post['num'] ? $post['num'] : "";
	$posttotal  = $post['posts'];
	$experience = "EXP {$exp} ($expleft for next)";

	// Control format
    if ($controls['quote']) $controls['quote']='['.$controls['quote'].']';
    $controls['edit'] = str_replace(' | ','',$controls['edit']);
    $controls['edit'] = str_replace('><','> | <',$controls['edit']);
    if ($controls['edit']) $controls['edit']='['. $controls['edit'] .']';
	$controls['ip'] = str_replace('| I','I',$controls['ip']);


	// RPG Level bar
	$bar = "<br>".drawprogressbar(96, 8, $exp - calclvlexp($lvl), totallvlexp($lvl), $barimg);
	
    $postdate = printdate($post['date']);
	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= "Thread: {$set['threadlink']}";
	}
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
	
	if ($post['deleted']) {
		$height = 0;
		$sideleft = "{$noobspan}{$set['userlink']}</span>";
		$sideright = "Posted: {$postdate} {$post['edited']}<br>{$threadlink} {$controls['edit']} | {$controls['ip']}";
	} else {
		$height = 50;
		$sideleft = "
		<table class='font' cellpadding=2 cellspacing=0 border=0>
			<tr>
				<td style='width: 80px; height: 80px'><span class='rpg-avatar'>{$set['userpic']}</span></td>
				<td class='nobr vatop'>
					{$noobspan}{$set['userlink']}</span>
					<span class='fonts'>
						<br>{$level}
						<br>{$bar}
						<br>{$poststext}{$postnum} ({$posttotal} total)
						<br>{$experience}
					</span>
				</td>
			</tr>
		</table>";
		$sideright = "
			Posted: {$postdate}
			<br>{$threadlink} {$controls['quote']}
			<br>{$controls['edit']}
			<br>{$controls['ip']}
			<br>{$set['rating']}";
	}
	
    return "
<table class='table tlayout-vertical' id='{$post['id']}'>
	<tr>
		<td class='tdbg{$set['bg']} vatop'>
			{$sideleft}
		</td>
		<td class='tdbg{$set['bg']} vatop right fonts nobr'>
			{$sideright}
		</td>
	</tr>
	<tr>
		<td class='tdbg{$set['bg']} vatop' style='height: {$height}px' colspan=2>
			{$post['headtext']}
			{$post['text']}
			{$set['attach']}
			{$post['signtext']}
		</td>
	</tr>
	<tr><td class='tdbg{$set['bg']}' height=1 colspan=2></td></tr>
</table>";

  }