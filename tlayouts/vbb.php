<?php
  
function userfields() {return 'posts,sex,powerlevel,picture,useranks,location,homepageurl,homepagename,u.ban_expire';}

function postcode($post, $set){
    global $controls, $loguser;
	
	// Convert controls to VBB-like format
    if ($controls['quote']) $controls['quote'] = "[{$controls['quote']}]";
    $controls['edit'] = str_replace(' | ','',$controls['edit']);
    $controls['edit'] = str_replace('a>','a>]',$controls['edit']);
    $controls['edit'] = str_replace('<a','[<a',$controls['edit']);
    $controls['ip']   = str_replace('| ','&nbsp; &nbsp;',$controls['ip']);
	
    $homepage = filter_string($post['homepageurl']) ? " [<a href='{$post['homepageurl']}'>www</a>]" : "";
    $postdate = printdate($post['date']);
	if ($post['edited']) $postdate .= "<br>";
	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}
    $u = $post['uid'];
	
	if ($post['deleted']) {
		$sidebar = "";
	} else {
		$location = filter_string($post['location']) ? "Location: {$post['location']}<br>" : "";
		$since = 'Registered: '.date('M Y', $post['regdate'] + $loguser['tzoff']);
		$sidebar = "
		<span class='fonts'><br>
			{$set['userrank']}<br>
			{$set['userpic']}<br>
			<br>
			{$since}<br>
			{$location}
			Posts: {$post['posts']}
		</span>";
	}
	
	$csskey = getcsskey($post);
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
	
	$optionrow = "";
	if ($set['rating']) {
		$optionrow .= "<tr>
			<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}_opt fonts'></td>
			<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey}_opt fonts'>{$set['rating']}</td>
		</tr>"; // &nbsp;<b>Post ratings:</b>
	}
	
    return "
	<table class='table tlayout-vbb contbar{$post['uid']}{$csskey}' id='{$post['id']}'>
		<tr>
			<td class='tdbg{$set['bg']} vatop sidebar{$post['uid']}{$csskey}' style='width: 200px'>
				{$noobspan}{$set['userlink']}</span>
				{$sidebar}
			</td>
			<td class='tdbg{$set['bg']} vatop mainbar{$post['uid']}{$csskey}' id='post{$post['id']}'>
				{$post['headtext']}
				{$post['text']}
				{$set['attach']}
				{$post['signtext']}
			</td>
		</tr>
		<tr>
			<td class='tdbg{$set['bg']} fonts sidebar{$post['uid']}{$csskey}_opt'>{$postdate}</td>
			<td class='tdbg{$set['bg']} vatop mainbar{$post['uid']}{$csskey}_opt'>
				<table class='w fonts'><tr>
					<td>[<a href='profile.php?id=$u'>Profile</a>] [<a href='sendprivate?userid=$u'>Send PM</a>]{$homepage} [<a href='thread.php?user=$u'>Search</a>]{$threadlink}</td>
					<td class='nobr right'>{$post['edited']} {$controls['quote']} {$controls['edit']} {$controls['ip']}</td>
				</tr></table>
			</td>
		</tr>
		{$optionrow}
	</table>
    ";
  }