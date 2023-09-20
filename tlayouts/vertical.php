<?php
	
function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.picture,u.moodurl,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.imood,u.ban_expire,\'\' sidebar';}

function postcode($post,$set){
    global $loguser,$controls,$tlayout,$textcolor,$numdir,$numfil,$barimg;

	// Sidebar info
	$exp		= calcexp($post['posts'],(time()-$post['regdate']) / 86400);
	$lvl		= calclvl($exp);
	$expleft	= calcexpleft($exp);
	
	$level      = "Level {$lvl}";
	$poststext  = "Post ";
	$postnum    = $post['num'] ? $post['num'] : "";
	$posttotal  = $post['posts'];
	$experience = "EXP {$exp} ($expleft for next)";

	// RPG Level bar
	$bar = "<br>".drawprogressbar(96, 8, $exp - calclvlexp($lvl), totallvlexp($lvl), $barimg);
	
    $postdate = printdate($post['date']);
	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= "Thread: {$set['threadlink']}";
	}
	
	$data = new tlayout_ext_input();
	$opt = get_tlayout_opts('vertical', $set, $post, $data);
	//--
	$warn = "";
	if ($set['warntext']) 		$warn .= $set['warntext'];
	if ($set['highlighttext'])	$warn .= $set['highlighttext'];
	//--
	
	if ($post['deleted']) {
		$height = 0;
		$sideleft = "{$set['userspan']}{$set['userlink']}</span>";
		$sideright = "{$set['new']}Posted: {$postdate} {$set['edited']}<br>{$threadlink} ".($controls ? "[".implode("] [", $controls)."]" : "")."";
	} else {
		$height = 50;
		$sideleft = "
		<table class='font' cellpadding=2 cellspacing=0 border=0>
			<tr>
				<td style='width: 80px; height: 80px'><span class='rpg-avatar'>{$set['userpic']}</span></td>
				<td class='nobr vatop'>
					{$set['userspan']}{$set['userlink']}</span>
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
			{$set['new']}
			Posted: {$postdate}
			<br>{$threadlink}
			".($controls ? "[".implode("] [", $controls)."]" : "")."
			{$opt->option_rows_top}";
	}
	
    return "{$set['highlightline']}
<table class='table post tlayout-vertical' id='{$post['id']}'>
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
			{$warn}
			{$post['headtext']}
			{$post['text']}
			{$set['attach']}
			{$post['signtext']}
		</td>
	</tr>
	<tr><td class='tdbg{$set['bg']}' height=1 colspan=2></td></tr>
	{$opt->option_rows_bottom}
</table>";

  }