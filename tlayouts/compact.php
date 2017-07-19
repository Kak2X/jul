<?php

function userfields(){return 'u.posts,u.sex,u.`group`,u.ban_expire,u.birthday,u.aka,u.namecolor,u.picture';}

function postcode($post,$set){
	global $ip, $quote, $edit;

	$postnum = ($post['num'] ? " {$post['num']}/":'').$post['posts'];

	$threadlink = "";
	if (filter_string($set['threadlink']))
		$threadlink = ", in {$set['threadlink']}";
	
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
		
	// We don't show the .topbar declaration since there's no CSS allowed anyway
	return 
	"<table class='table'>
		<tr>
			<td class='tbl tdbg{$set['bg']}' valign=top>
				<div class='mobile-avatar'>{$set['userpic']}</div>
				{$noobspan}{$set['userlink']}</span><br>
				<span class='fonts'> Posts: $postnum</span>
			</td>
			<td class='tbl tdbg{$set['bg']}' valign=top width=50% align=right>
				<span class='fonts'> Posted on {$set['date']}$threadlink</span>
				<br>$quote$edit
				<br>$ip
			</td>
		</tr>
		<tr>
			<td class='tbl tdbg{$set['bg']}' valign=top height=60 colspan=2 id='post{$post['id']}'>
				{$post['headtext']}
				{$post['text']}
				{$post['signtext']}
				{$set['edited']}
			</td>
		</tr>
	</table><br>";
}
?>