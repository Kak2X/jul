<?php
function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.picture,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.ban_expire,\'\' sidebar';}

function postcode($post,$set){
	global  $controls, $tlayout, $textcolor, $numdir, $numfil, $hacks, $x_hacks, $loguser;

	
	$exp     = calcexp($post['posts'],(time()-$post['regdate'])/86400);
	$lvl     = calclvl($exp);
	$expleft = calcexpleft($exp);

	// Not used?
	//$reinf=syndrome($post['act']);

	$sincelastpost 	= "";
	$lastactivity 	= "";
	$since = 'Since: '.printdate($post['regdate'], true);

	$postdate  =  printdate($post['date']);

	$threadlink = "";
	if(filter_string($set['threadlink'])) 
		$threadlink = ", in {$set['threadlink']}";

	/* if($post['edited']){
		$set['edited'].="<hr><font class="fonts">$post['edited']";
	}*/

	$height   = $post['deleted'] ? 0 : 220;	
	
	//--
	$data = new tlayout_ext_input();
	$data->rowspan          = 2;
	//--
	$opt = get_tlayout_opts('hydra', $set, $post, $data);
	//--
	if ($set['warntext']) 		$opt->option_rows_top .= $set['warntext'];
	if ($set['highlighttext'])	$opt->option_rows_top .= $set['highlighttext'];
	//--
	
	return "{$set['highlightline']}
	<table class='table post tlayout-hydra' id='{$post['id']}'>
		<tr>
			<td class='tdbg{$set['bg']} vatop' rowspan='{$opt->rowspan}' style='width: 20% !important;'>
				{$set['userspan']}{$set['userlink']}</span>
				<span class='fonts'>
					<br>
					<center>{$set['userpic']}</center><br>
					{$post['title']}<br>
					<br>
				</span>
			</td>
			<td class='tdbg{$set['bg']} vatop' style='height: 1px'>
				<table class='fonts' style='clear: both; width: 100%;'>
					<tr>
						<td>
							{$set['new']}Posted on $postdate$threadlink{$post['edited']}
						</td>
						<td style='float: right;'>
							".implode(" | ", $controls)."
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td class='tbl tdbg{$set['bg']} vatop' style='overflow: visible; width: 70%;' height={$height} id='post{$post['id']}'>
				{$opt->option_rows_top}
				{$post['headtext']}
				{$post['text']}
				{$set['attach']}
				{$post['signtext']}
			</td>
		</tr>
		{$opt->option_rows_bottom}
	</table>
	<br>";
/*
	if (!$set['picture']) $set['picture']	= "images/_.gif";

	if ($_GET['z']) {
		print_r($st['eq']);
	}
	*/
}
