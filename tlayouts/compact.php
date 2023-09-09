<?php

function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.ban_expire,\'\' sidebar';}

function postcode($post,$set){
	global $controls, $config;

	$postnum = ($post['num'] ? " {$post['num']}/":'').$post['posts'];

	$threadlink = "";
	if (filter_string($set['threadlink']))
		$threadlink = ", in {$set['threadlink']}";
	
	$height   = $post['deleted'] ? 0 : 60;
	
	$data = new tlayout_ext_input();
	$opt = get_tlayout_opts('compact', $set, $post, $data);
	//--
	if ($set['warntext']) 		$opt->option_rows_top .= $set['warntext'];
	if ($set['highlighttext'])	$opt->option_rows_top .= $set['highlighttext'];
	//--
	
	// We don't show the .topbar declaration since there's no CSS allowed anyway
	return "{$set['highlightline']}
	<table class='table' id='{$post['id']}'>
		<tr>
			<td class='tdbg{$set['bg']} vatop'>
				<div class='mobile-avatar'>{$set['userpic']}</div>
				{$set['userspan']}{$set['userlink']}</span><br>
				<span class='fonts'> Posts: {$postnum}</span>{$opt->top_left}
			</td>
			<td class='tdbg{$set['bg']} vatop' style='width: 50%'>
				<div class='fonts right'>{$set['new']} Posted on {$set['date']}$threadlink</div>
				<div class='right'>{$controls['quote']}{$controls['edit']}</div>
				<span style='float: right'>&nbsp;{$controls['ip']}</span>{$opt->top_right}
			</td>
		</tr>
		<tr>
			<td class='tdbg{$set['bg']} vatop' style='height: {$height}px' colspan=2 id='post{$post['id']}'>
				{$opt->option_rows_top}
				{$post['headtext']}
				{$post['text']}
				{$set['attach']}
				{$post['signtext']}
			</td>
		</tr>
		{$opt->option_rows_bottom}
	</table>";
}
?>