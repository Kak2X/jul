<?php
  function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.ban_expire,\'\' sidebar';}

  function postcode($post,$set){
    global $controls,$tableborder,$tablebg2,$tableheadtext,$numdir,$barimg;
			
	$numdays    = (time() - $post['regdate']) / 86400;
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

	//--
	$data = new tlayout_ext_input();
	$data->csskey           = getcsskey($post);
	$data->rowspan          = 2;
	//--
	$opt = get_tlayout_opts('rpg', $set, $post, $data);
	//--
	if ($set['warntext']) 		$opt->option_rows_top .= $set['warntext'];
	if ($set['highlighttext'])	$opt->option_rows_top .= $set['highlighttext'];
	//--
	
	if ($post['deleted']) {
		$height = 0;
		$rpgbox = "";
	} else {
		$height = 220;
		$rpgbox = "
		<span class='fonts'><br>{$set['userrank']}</span>
		<table border='' bordercolor='{$tableborder}' cellspacing='0' cellpadding='0' style='background: #{$tablebg2}' id='rpg{$post['uid']}{$data->csskey}'>
			<tr>
				<td style='width: 100px; height: 100px' valign=center align=center id='rpgtop{$post['uid']}{$data->csskey}_1'><span class='rpg-avatar'>{$set['userpic']}</span></td>
				<td style='width: 60px; height: 60px' class='vatop' id='rpgtop{$post['uid']}{$data->csskey}_2'>
					<table class='w fontt' cellpadding=0 cellspacing=0>
						<tr>
							<td class='b' style='color: {$tableheadtext}'>LV<br><br>HP<br>MP</td>
							<td class='b right'>{$lvl}<br><br>{$post['posts']}<br>{$mp}</td>
						</tr>
					</table>
				</td>
			</tr>
			<tr>
				<td colspan=2 id='rpglow{$post['uid']}{$data->csskey}_1'>
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
	

		
	
return "{$set['highlightline']}
<table class='table post tlayout-rpg contbar{$post['uid']}{$data->csskey}' id='{$post['id']}'>
	<tr>
		<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$data->csskey} vatop' rowspan='{$opt->rowspan}' style='width: 200px'>
			{$set['userspan']}{$set['userlink']}</span>
			{$rpgbox}
		</td>
		<td class='tdbg{$set['bg']} topbar{$post['uid']}{$data->csskey}_2'>
			<table class='w fonts' cellspacing=0 cellpadding=2>
				<tr>
					<td>{$set['new']}Posted on {$postdate}{$threadlink}{$post['edited']}</td>
					<td class='nobr' style='width: 255px'>{$controls['quote']}{$controls['edit']}{$controls['ip']}</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td class='tdbg2 vatop mainbar{$post['uid']}{$data->csskey}' style='height: {$height}px' id='post{$post['id']}'>
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
}