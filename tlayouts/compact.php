<?php

function userfields(){return 'u.posts,u.sex,u.`group`,u.displayname,u.main_subgroup,u.ban_expire,u.birthday,u.aka,u.namecolor,u.picture';}

function postcode($post, $set, $controls){

	$postnum = ($post['num'] ? " {$post['num']}/":'').$post['posts'];

	$threadlink = "";
	if (filter_string($set['threadlink']))
		$threadlink = ", in {$set['threadlink']}";
	
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
		
	// We don't show the .topbar declaration since there's no CSS allowed anyway
	return 
	"<table class='table'>
		<tr>
			<td class='tbl tdbg{$set['bg']} vatop'>
				".($post['deleted'] ? 
				"{$noobspan}{$set['userlink']}</span>" : // Hide post & avatar when deleted, but still show the mark of shame
				"<div class='mobile-avatar'>{$set['userpic']}</div>
				{$noobspan}{$set['userlink']}</span><br>
				<span class='fonts'> Posts: $postnum</span>".)."
			</td>
			<td class='tbl tdbg{$set['bg']} vatop right' style='width: 50%'>
				<span class='fonts'> Posted on {$set['date']}$threadlink</span>
				<br>{$controls['quote']}{$controls['edit']}
				<br>{$controls['ip']}
			</td>
		</tr>
		<tr>
			<td class='tbl tdbg{$set['bg']} vatop' style='height: 60px' colspan=2 id='post{$post['id']}'>
				".($post['deleted'] ? $post['text'] : "
				{$post['headtext']}
				{$post['text']}
				{$post['signtext']}
				{$set['edited']}"
			.)."
			</td>
		</tr>
	</table><br>";
}