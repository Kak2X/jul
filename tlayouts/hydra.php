<?php
function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.picture,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.ban_expire';}

function postcode($post,$set){
	global  $ip, $quote, $edit, $tlayout, $textcolor, $numdir, $numfil, $hacks, $x_hacks, $loguser;

	
	$exp     = calcexp($post['posts'],(ctime()-$post['regdate'])/86400);
	$lvl     = calclvl($exp);
	$expleft = calcexpleft($exp);

	// Not used?
	//$reinf=syndrome($post['act']);

	$sincelastpost 	= "";
	$lastactivity 	= "";
	$since = 'Since: '.printdate($post['regdate'], true);

	$postdate  =  printdate($post['date']);

	if(filter_string($set['threadlink'])) 
		$threadlink = ", in {$set['threadlink']}";

	/* if($post['edited']){
		$set['edited'].="<hr><font class="fonts">$post['edited']";
	}*/

	//$sidebars	= array(1, 16, 18, 19, 387);
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
			
	return 
	"<table class='table'>
		<tr>
			<td class='tbl tdbg{$set['bg']}' valign=top rowspan=2 style='width: 20% !important;'>
				{$noobspan}{$set['userlink']}</span>
				<span class='fonts'>
					<br>
					<center>{$set['userpic']}</center><br>
					{$post['title']}<br>
					<br>
				</span>
			</td>
			<td class='tbl tdbg{$set['bg']}' valign=top height=1>
				<table class='fonts' style='clear: both; width: 100%;'>
					<tr>
						<td>
							Posted on $postdate$threadline{$post['edited']}
						</td>
						<td style='float: right;'>
							$quote$edit$ip
						</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td class='tbl tdbg{$set['bg']}' valign=top style='overflow: visible; width: 70%;' height=220 id='post{$post['id']}'>
				{$post['headtext']}
				{$post['text']}
				{$post['signtext']}
			</td>
		</tr>
	</table>
	<br>";
/*
	if (!$set['picture']) $set['picture']	= "images/_.gif";

	if ($_GET['z']) {
		print_r($st['eq']);
	}
	*/
}

?>