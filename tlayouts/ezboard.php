<?php
function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.ban_expire';}

function postcode($post,$set) {
	global $tzoff, $smallfont, $controls; //ip,$quote,$edit;

	// Shorten the control labels
	//$controls['quote'] = str_replace( 'Quote', 'Reply', $controls['quote']);
	//$controls['del']   = str_replace('Delete',   'Del', $controls['edit']);
	//$controls['ip']    = str_replace(  'IP: ',      '', $controls['ip']);
	
	$threadlink = "";
	if (filter_string($set['threadlink'])) {
		$threadlink = ", in {$set['threadlink']}";
	}
	
	$postnum = ($post['num'] ? " {$post['num']}/":'').$post['posts'];
	

	// remove paranthesis and uppercase the first letter
	if ($set['edited']) $set['edited'] = ucfirst(str_replace(array("(",")"), "", trim($set['edited'])))."<br>";
	
	$csskey = getcsskey($post);
	
	//--
	$data = new tlayout_ext_input();
	$data->csskey           = $csskey;
	//--
	$opt = get_tlayout_opts('ezboard', $set, $post, $data);
	//--
	if ($set['warntext']) 		$opt->option_rows_top .= $set['warntext'];
	if ($set['highlighttext'])	$opt->option_rows_top .= $set['highlighttext'];
	//--
	
	return "{$set['highlightline']}
<table class='table post tlayout-ezboard contbar{$post['uid']}{$csskey}' id='{$post['id']}'>
	<tr>
		<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$csskey} vatop' style='width: 200px'>
			{$set['userspan']}{$set['userlink']}</span>{$opt->top_left}
			<span class='fonts'>
				<br>
				<b>{$set['userrank']}</b><br>
				Posts: {$postnum}<br>
				{$set['new']}({$set['date']}){$threadlink}<br>
				{$set['edited']}
				".implode(" | ", $controls)."
				{$set['userpic']}
			</span>
		</td>
		<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$csskey} vatop' id='post{$post['id']}'>
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