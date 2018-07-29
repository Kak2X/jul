<?php
  function userfields(){return 'u.posts,u.sex,u.powerlevel,u.birthday,u.aka,u.namecolor,u.picture,u.moodurl,u.title,u.useranks,u.location,u.lastposttime,u.lastactivity,u.imood,u.ban_expire';}

  function postcode($post,$set){
    global $controls,$tlayout,$textcolor,$numfil,$barimg;
	static $numdir;
	$exp		= calcexp($post['posts'],(ctime()-$post['regdate']) / 86400);
	$lvl		= calclvl($exp);
	//$expleft	= calcexpleft($exp);
	
	
	//$expdone    = $exp - calclvlexp($lvl);
	//print "lvl -> $lvl; expleft -> {$expleft}; lvlexp(done) -> {$expdone}; totallvlexp -> ".totallvlexp($lvl)."; sumofexp ".($expdone+$expleft)."";
	
	
	
	//$numdir     = 'num1/';
	if ($numdir === NULL) $numdir = get_complete_numdir();
	$level		= "<img src='numgfx/{$numdir}level.png' width=36 height=8><img src='numgfx.php?n=$lvl&l=3&f=$numfil' height=8>";
	$bar = "<br>".drawprogressbar(56, 8, $exp - calclvlexp($lvl), totallvlexp($lvl), $barimg);
	
	$noobspan = $post['noob'] ? "<span style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span>";
	
	$postdate		= printdate($post['date']);
	
	$threadlink		= "";
	if (filter_string($set['threadlink'])) {
		$threadlink	= ", in {$set['threadlink']}";
	}
	
	$height = $post['deleted'] ? 0 : 220;
	
	$optionrow = "";
	if ($set['rating']) {
		$optionrow .= "<tr>
			<td class='tdbg{$set['bg']} fonts' colspan=2>{$set['rating']}</td>
		</tr>";
	}
	
    return "
	<table class='table post tlayout-postwide' id='{$post['id']}'>
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
		{$optionrow}
	  </table>
    ";
  }
?>
