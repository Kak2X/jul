<?php

  function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.ban_expire,\'\' sidebar';}

  function postcode($post,$set){
    global $controls, $loguser;
	
    $since		= '<br>Registered: '.date('M Y',$post['regdate'] + $loguser['tzoff']);
    $postdate	= printdate($post['date']);
	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}
	
	//--
	$data = new tlayout_ext_input();
	//--
	$opt = get_tlayout_opts('ubb', $set, $post, $data);
	//--
	if ($set['warntext']) 		$opt->option_rows_top .= $set['warntext'];
	if ($set['highlighttext'])	$opt->option_rows_top .= $set['highlighttext'];
	//--
	
	if ($post['deleted']) {
		$sidebar = "";
	} else {
		$sidebar = "
		<span class='fonts'><br>
			{$set['userrank']}<br>
			{$set['userpic']}<br>
			<br>
			Posts: {$post['posts']}
			{$set['location']}{$since}
		</span>";
	}
    return "{$set['highlightline']}
	<table class='table post tlayout-ubb' id='{$post['id']}'>
		<tr>
			<td class='tdbg{$set['bg']} vatop' style='width: 200px; border-bottom: none'>
				{$set['userspan']}{$set['userlink']}</span>
				{$sidebar}
			</td>
			<td class='tdbg{$set['bg']} vatop' style='border-bottom: none' id='post{$post['id']}'>
				<table class='w fonts' cellspacing=0 cellpadding=2>
						<tr>
							<td>{$set['new']}Posted on {$postdate}{$threadlink}{$post['edited']}</td>
							<td class='nobr' style='width: 255px'>{$controls['quote']}{$controls['edit']}{$controls['ip']}</td>
						</tr>
				</table>
				<hr>
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
