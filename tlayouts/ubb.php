<?php

  function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.ban_expire';}

  function postcode($post,$set){
    global $controls, $loguser;

	$set['location'] = str_ireplace("&lt;br&gt;", "<br>", $set['location']);
    $since='<br>Registered: '.date('M Y',$post['regdate'] + $loguser['tzoff']);
    $postdate = printdate($post['date']);
	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}
	
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
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
    return "
	<table class='table post tlayout-ubb' id='{$post['id']}'>
		<tr>
			<td class='tdbg{$set['bg']} vatop' style='width: 200px; border-bottom: none'>
				{$noobspan}{$set['userlink']}</span>
				{$sidebar}
			</td>
			<td class='tdbg{$set['bg']} vatop' style='border-bottom: none' id='post{$post['id']}'>
				<table class='w fonts' cellspacing=0 cellpadding=2>
						<tr>
							<td>Posted on {$postdate}{$threadlink}{$post['edited']}</td>
							<td class='nobr' style='width: 255px'>{$controls['quote']}{$controls['edit']}{$controls['ip']}</td>
						</tr>
				</table>
				<hr>
				{$post['headtext']}
				{$post['text']}
				{$set['attach']}
				{$post['signtext']}
			</td>
		</tr>
		<tr>
			<td class='tdbg{$set['bg']}'></td>
			<td class='tdbg{$set['bg']}'><hr>{$set['rating']}</td>
		</tr>
	 </table>
    ";
  }
