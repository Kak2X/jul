<?php
  function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.picture,u.moodurl,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.imood,u.ban_expire';}

  function postcode($post,$set){
    global $controls,$tlayout,$textcolor,$numdir,$numfil;
	
	$exp		= calcexp($post['posts'],(ctime()-$post['regdate']) / 86400);
	$lvl		= calclvl($exp);
	$expleft	= calcexpleft($exp);
	
	$numdir     = 'num1/';
	$level		= "<img src='images/{$numdir}level.gif' width=36 height=8><img src='numgfx.php?n=$lvl&l=3&f=$numfil' height=8>";
	//$experience	= "<img src='images/{$numdir}exp.gif' width=20 height=8><img src='numgfx.php?n=$exp&l=5&f=$numfil' height=8><br><img src='images/{$numdir}fornext.gif' width=44 height=8><img src='numgfx.php?n=$expleft&l=2&f=$numfil' height=8>";
	//$poststext	= "<img src='images/_.gif' height=2><br><img src='images/{$numdir}posts.gif' width=28 height=8>";
	//$postnum	= $post['num'] ? "<img src='numgfx.php?n={$post['num']}/&l=5&f=$numfil' height=8>" : "";
	//$posttotal	= "<img src='numgfx.php?n={$post['posts']}&f=$numfil'".($post['num']?'':'&l=4')." height=8>";

	$barimg = array(
		'left'  => "images/{$numdir}barleft.gif",
		'right' => "images/{$numdir}barright.gif",
		'on'    => "images/{$numdir}bar-on.gif",
		'off'   => "images/{$numdir}bar-off.gif",
	);
	$bar = "<br>".drawprogressbar(56, 8, 100-round(@($expleft/totallvlexp($lvl))*100), $barimg);
	
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
	
	$postdate		= printdate($post['date']);
	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}
	
	$height = $post['deleted'] ? 0 : 220;
	
    return "
	<table class='table' id='{$post['id']}'>
		<tr>
			<td class='tdbg{$set['bg']} w' height=1 colspan=2>
				<table cellspacing=0 cellpadding=2 class='w fonts'>
					<tr>
						<td>{$noobspan}{$set['userlink']}</span><span class='fonts'><br> {$level}{$bar}</span></td>
						<td class='nobr' style='width: 255px'>{$controls['quote']}{$controls['edit']}{$controls['ip']}</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td class='tdbg{$set['bg']} vatop' style='height: {$height}px' colspan=2  id='post{$post['id']}'>
				{$post['headtext']}
				{$post['text']}
				{$set['attach']}
				{$post['signtext']}
			</td>
		</tr>
		<tr>
			<td class='tdbg{$set['bg']} fonts w' colspan=2>
				Posted on {$postdate}{$threadlink}{$post['edited']}
			</td>
		</tr>
	  </table>
    ";
  }
?>
