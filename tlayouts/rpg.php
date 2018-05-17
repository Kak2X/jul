<?php
  function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.ban_expire';}

  function postcode($post,$set){
    global $controls,$tableborder,$tablebg2,$tableheadtext,$numdir,$barimg;
			
	$numdays    = (ctime() - $post['regdate']) / 86400;
	$exp		= calcexp($post['posts'], $numdays);
	$mp         = calcexpgainpost($post['posts'], $numdays);
	$lvl		= calclvl($exp);
	$expleft	= calcexpleft($exp);
	
	$bar = "<br>".drawprogressbar(100, 8, $exp - calclvlexp($lvl), totallvlexp($lvl), $barimg);
	$postdate = printdate($post['date']);
	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}

	$csskey = getcsskey($post);
	$optionrow = "";
	if ($set['rating']) {
		$optionrow .= "<tr>
			<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}_opt fonts'></td>
			<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey}_opt fonts'>{$set['rating']}</td>
		</tr>"; // &nbsp;<b>Post ratings:</b>
	}
	
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
	
	if ($post['deleted']) {
		$height = 0;
		$rpgbox = "";
	} else {
		$height = 220;
		$rpgbox = "
		<span class='fonts'><br>{$set['userrank']}</span>
		<table border bordercolor=$tableborder cellspacing=0 cellpadding=0 style='background: {$tablebg2}' id='rpg{$post['uid']}{$csskey}'>
			<tr>
				<td style='width: 100px; height: 100px' valign=center align=center id='rpgtop{$post['uid']}{$csskey}_1'><span class='rpg-avatar'>{$set['userpic']}</span></td>
				<td style='width: 60px; height: 60px' class='vatop' id='rpgtop{$post['uid']}{$csskey}_2'>
					<table class='w fontt' cellpadding=0 cellspacing=0>
						<tr>
							<td class='b' style='color: {$tableheadtext}'>LV<br><br>HP<br>MP</td>
							<td class='b right'>{$lvl}<br><br>{$post['posts']}<br>{$mp}</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan=2 id='rpglow{$post['uid']}{$csskey}_1'>
					<table class='w fontt' cellpadding=0 cellspacing=0>
						<tr>
							<td class='b' style='color: {$tableheadtext}'>EXP points<br>For next LV</td>
							<td class='b right'>{$exp}<br>{$expleft}</td>
						</tr>
						<tr><td colspan=2>{$bar}</td></tr>
					</table>
				</td>
			</tr>
		</table>";
	}
	

		
	
return "
<table class='table contbar{$post['uid']}{$csskey}' id='{$post['id']}'>
	<tr>
		<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey} vatop' rowspan=2 style='width: 200px'>
			{$noobspan}{$set['userlink']}</span>
			{$rpgbox}
		</td>
		<td class='tdbg{$set['bg']} topbar{$post['uid']}{$csskey}_2'>
			<table class='w fonts' cellspacing=0 cellpadding=2>
				<tr>
					<td>Posted on {$postdate}{$threadlink}{$post['edited']}</td>
					<td class='nobr' style='width: 255px'>{$controls['quote']}{$controls['edit']}{$controls['ip']}</td>
				</tr>
			</table>
		<tr>
		<td class='tdbg2 vatop mainbar{$post['uid']}{$csskey}' style='height: {$height}px' id='post{$post['id']}'>
			{$post['headtext']}
			{$post['text']}
			{$set['attach']}
			{$post['signtext']}
		</td>
	</tr>
	{$optionrow}
</table>
";
}