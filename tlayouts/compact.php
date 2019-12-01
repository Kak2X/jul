<?php

function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.ban_expire';}

function postcode($post,$set){
	global $controls, $config;

	$postnum = ($post['num'] ? " {$post['num']}/":'').$post['posts'];

	$threadlink = "";
	if (filter_string($set['threadlink']))
		$threadlink = ", in {$set['threadlink']}";
	
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
	$height   = $post['deleted'] ? 0 : 60;
	
	// We don't show the .topbar declaration since there's no CSS allowed anyway
	return 
	"<table class='table' id='{$post['id']}'>
		<tr>
			<td class='tdbg{$set['bg']} vatop'>
				<div class='mobile-avatar'>{$set['userpic']}</div>
				{$noobspan}{$set['userlink']}</span><br>
				<span class='fonts'> Posts: {$postnum}</span>
			</td>
			<td class='tdbg{$set['bg']} vatop' style='width: 50%'>
				<div class='fonts right'>{$set['new']} Posted on {$set['date']}$threadlink</div>
				<div class='right'>{$controls['quote']}{$controls['edit']}</div>
				<span style='float: right'>&nbsp;{$controls['ip']}</span>{$set['rating']}
			</td>
		</tr>
		<tr>
			<td class='tdbg{$set['bg']} vatop' style='height: {$height}px' colspan=2 id='post{$post['id']}'>
				{$post['headtext']}
				{$post['text']}
				{$set['attach']}
				{$post['signtext']}
			</td>
		</tr>
	</table>";
}
?>