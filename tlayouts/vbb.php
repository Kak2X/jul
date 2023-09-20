<?php
  
function userfields() {return 'posts,sex,powerlevel,picture,useranks,location,homepageurl,homepagename,u.ban_expire,\'\' sidebar';}

function postcode($post, $set){
    global $controls, $loguser;
	
    $homepage = filter_string($post['homepageurl']) ? " [<a href='{$post['homepageurl']}'>www</a>]" : "";
    $postdate = printdate($post['date']);
	if ($set['edited']) $postdate .= "<br>";
	
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
	
	//--
	$data = new tlayout_ext_input();
	$data->csskey           = $csskey;
	$data->rowspan          = 2;
	//--
	$opt = get_tlayout_opts('vbb', $set, $post, $data);
	//--
	if ($set['warntext']) 		$opt->option_rows_top .= $set['warntext'];
	if ($set['highlighttext'])	$opt->option_rows_top .= $set['highlighttext'];
	//--
	
    return "{$set['highlightline']}
	<table class='table post tlayout-vbb contbar{$post['uid']}{$csskey}' id='{$post['id']}'>
		<tr>
			<td class='tdbg{$set['bg']} vatop sidebar{$post['uid']}{$csskey}' style='width: 200px'>
				{$set['userspan']}{$set['userlink']}</span>
				{$sidebar}
			</td>
			<td class='tdbg{$set['bg']} vatop mainbar{$post['uid']}{$csskey}' id='post{$post['id']}'>
				{$opt->option_rows_top}
				{$post['headtext']}
				{$post['text']}
				{$set['attach']}
				{$post['signtext']}
			</td>
		</tr>
		<tr>
			<td class='tdbg{$set['bg']} fonts sidebar{$post['uid']}{$csskey}_opt'>{$set['new']}{$postdate}</td>
			<td class='tdbg{$set['bg']} vatop mainbar{$post['uid']}{$csskey}_opt'>
				<table class='w fonts'><tr>
					<td>[<a href='profile.php?id={$u}'>Profile</a>] [<a href='newpmthread?userid={$u}'>Send PM</a>]{$homepage} [<a href='thread.php?mode=user&user={$u}'>Search</a>]{$threadlink}</td>
					<td class='nobr right'>{$set['edited']} ".($controls ? "[".implode("] [", $controls)."]" : "")."</td>
				</tr></table>
			</td>
		</tr>
		{$opt->option_rows_bottom}
	</table>
    ";
  }