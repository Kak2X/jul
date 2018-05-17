<?php
function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.ban_expire';}

function postcode($post,$set) {
	global $tzoff, $smallfont, $controls; //ip,$quote,$edit;

	// Shorten the control labels
	$controls['quote'] = str_replace(  'Quote', 'Reply', $controls['quote']);
	$controls['edit']  = str_replace('>Delete',  '>Del', $controls['edit']);
	$controls['ip']    = str_replace( '| IP: ',      '', $controls['ip']);
	
	$threadlink = "";
	if (filter_string($set['threadlink'])) {
		$threadlink = ", in {$set['threadlink']}";
	}
	
	$postnum = ($post['num'] ? " {$post['num']}/":'').$post['posts'];
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
	

	// remove paranthesis and uppercase the first letter
	if ($post['edited']) $post['edited'] = ucfirst(str_replace(array("(",")"), "", trim($post['edited'])))."<br>";
	
	$csskey = getcsskey($post);
	
	$optionrow = "";
	if ($set['rating']) {
		$optionrow .= "<tr>
			<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey}_opt fonts'></td>
			<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey}_opt fonts'>{$set['rating']}</td>
		</tr>"; // &nbsp;<b>Post ratings:</b>
	}
	
	return "
<table class='table contbar{$post['uid']}{$csskey}' id='{$post['id']}'>
	<tr>
		<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey} vatop' style='width: 200px'>
			{$noobspan}{$set['userlink']}</span>
			<span class='fonts'>
				<br>
				<b>{$set['userrank']}</b><br>
				Posts: {$postnum}<br>
				({$set['date']}){$threadlink}<br>
				{$post['edited']}
				{$controls['ip']}<br>
				{$controls['quote']}{$controls['edit']}<br>
				{$set['userpic']}
			</span>
		</td>
		<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey} vatop' id='post{$post['id']}'>
			{$post['headtext']}
			{$post['text']}
			{$set['attach']}
			{$post['signtext']}
		</td>
	</tr>
	{$optionrow}
</table>";
}