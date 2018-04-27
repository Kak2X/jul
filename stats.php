<?php
	require 'lib/function.php';

	
	$misc   = $sql->fetchq('SELECT * FROM misc');
	$tstats = $sql->query('SHOW TABLE STATUS');
	while ($t = $sql->fetch($tstats)) $tbl[$t['Name']]=$t;

/*
	$sch_info = "";
	$schemes = $sql->query('
		SELECT COUNT(u.id) as schemecount, u.scheme, schemes.name
		FROM users AS u
		LEFT JOIN schemes ON (u.scheme = schemes.id)
		WHERE (schemes.ord >= 0)
		GROUP BY u.scheme
		ORDER BY schemecount DESC
	');

	while ($row = $sql->fetch($schemes)) {
		$sch_info .= "<tr><td class='tdbg1 center'>$row[name]</td><td class='tdbg1 center'>$row[schemecount]</tr>";
	} */
	
	pageheader();
	
?>
	<table class='table'>
	<tr><td class='tdbgh center'>Interesting statistics</td></tr>
	<tr><td class='tdbg1'>
		<img src='ext/ppdgauge.php' alt='Posts in last 24 hours' title='Posts in last 24 hours' style='display: block; float: right;'>
		<ul>
			<li><a href='activeusers.php'>Recently active posters</a></li>
			<li><a href='acs.php'>Daily poster rankings</a></li>
			<li><a href='milestones.php'>Post milestones</a></li>
			<li><a href='sigsize.php'>Biggest posters</a></li>
			<li><a href='sigsize.php'>Largest post layouts</a></li>
			<li><a href='sigsize.php?bio=1'>Largest bios</a></li>
			<li><a href='activity.php?u=<?= ($loguser['id'] ? $loguser['id'] : 1) ?>'>Graph of your posting history</a> (change the ID in the URL to see others)</li>
			<li><a href='activity2.php'>Graph of the top 10 posters</a></li>
			<li><a href='activity3.php'>Graph of total post count and posts per day</a></li>
			<li><a href='activity3u.php'>Graph of active users per day</a></li>
			<li><a href='avatar.php'>Mood avatars</a></li>
		</ul>
	</td>
	</tr>
	</table>
	<br>
	<table class='table'>
		<tr><td class='tdbgh center'>Records</td><td class='tdbgh center'>&nbsp;</td></tr>
		<tr>
			<td class='tdbg1 fonts center'><b>Most posts within 24 hours:</td>
			<td class='tdbg2 fonts'><?=$misc['maxpostsday']?>, on <?=date($loguser['dateformat'],$misc['maxpostsdaydate'])?></td>
		</tr>
		<tr>
			<td class='tdbg1 fonts center'><b>Most posts within 1 hour:</td>
			<td class='tdbg2 fonts'><?=$misc['maxpostshour']?>, on <?=date($loguser['dateformat'],$misc['maxpostshourdate'])?></td>
		</tr>
		<tr>
			<td class='tdbg1 fonts center'><b>Most users online:</td>
			<td class='tdbg2 fonts'><?=$misc['maxusers']?>, on <?=date($loguser['dateformat'],$misc['maxusersdate'])?><?=$misc['maxuserstext']?></td>
		</tr>
	</table>
	<br>
	<?php
/*
	// This is kind of in Edit Profile already.
	"<table class='table'><tr><td class='tdbgh center' colspan='2'>Scheme Usage Breakdown</td></tr>
	<tr><td class='tdbgh center'>Scheme Name</td><td class='tdbgh center'>Users</td></tr>
	$sch_info
	</table><br>".
*/
?>	<table class='table'>
		<tr>
			<td class='tdbgh center'>Table name</td>
			<td class='tdbgh center'>Rows</td>
			<td class='tdbgh center'>Avg. data/row</td>
			<td class='tdbgh center'>Data size</td>
			<td class='tdbgh center'>Index size</td>
			<td class='tdbgh center'>Overhead</td>
			<td class='tdbgh center'>Total size</td>
		</tr>
	<?php
echo //tblinfo('posts_text').
	tblinfo('posts')
	.tblinfo('posts_old')
	.tblinfo('pm_posts')
	//.tblinfo('pmsgs_text')
	.tblinfo('postlayouts')
	.tblinfo('threads')
	.tblinfo('users')
	.tblinfo('forumread')
	.tblinfo('threadsread')
	.tblinfo('pm_threads')
	.tblinfo('pm_threadsread')
	.tblinfo('pm_foldersread')
	.tblinfo('news')
	.tblinfo('news_comments')
	.tblinfo('postradar')
	.tblinfo('ipbans')
	.tblinfo('defines')
	.tblinfo('dailystats')
	.tblinfo('rendertimes');
?>	</table>
  <br>
  <table class='table'>
	<tr><td class='tdbgh fonts center' colspan=9>Daily stats</td></tr>
	<tr>
		<td class='tdbgc fonts center'>Date</td>
		<td class='tdbgc fonts center'>Total users</td>
		<td class='tdbgc fonts center'>Total posts</td>
		<td class='tdbgc fonts center'>Total threads</td>
		<td class='tdbgc fonts center'>Total views</td>
		<td class='tdbgc fonts center'>New users</td>
		<td class='tdbgc fonts center'>New posts</td>
		<td class='tdbgc fonts center'>New threads</td>
		<td class='tdbgc fonts center'>New views</td>
	</tr>
<?php
	$users 		= 0;
	$posts 		= 0;
	$threads	= 0;
	$views		= 0;
	$stats = $sql->query("SELECT * FROM dailystats ORDER BY id ASC");	// NOTE: Originally dailystats did not have an ID. Added due to InnoDB shenanigains.
	while($day = $sql->fetch($stats)){
		?>
	<tr>
		<td class='tdbg1 fonts center'><?=$day['date']?></td>
		<td class='tdbg2 fonts center'><?=$day['users']?></td>
		<td class='tdbg2 fonts center'><?=$day['posts']?></td>
		<td class='tdbg2 fonts center'><?=$day['threads']?></td>
		<td class='tdbg2 fonts center'><?=$day['views']?></td>
		<td class='tdbg2 fonts center'><?=($day['users']-$users)?></td>
		<td class='tdbg2 fonts center'><?=($day['posts']-$posts)?></td>
		<td class='tdbg2 fonts center'><?=($day['threads']-$threads)?></td>
		<td class='tdbg2 fonts center'><?=($day['views']-$views)?></td>
	</tr>
	<?php
		$users 		= $day['users'];
		$posts 		= $day['posts'];
		$threads 	= $day['threads'];
		$views 		= $day['views'];
	}
?></table><?php

	pagefooter();
	
	function sp($sz) {
//    $b="$sz B";
//    if($sz>1023) $b=sprintf('%01.2f',$sz/1024).' kB';
//    if($sz>10239) $b=sprintf('%01.1f',$sz/1024).' kB';
//    if($sz>102399) $b=sprintf('%01.0f',$sz/1024).' kB';
//    if($sz>1048575) $b=sprintf('%01.2f',$sz/1048576).' MB';
//    if($sz>10485759) $b=sprintf('%01.1f',$sz/1048576).' MB';
//    if($sz>104857599) $b=sprintf('%01.0f',$sz/1048576).' MB';
		$b=number_format($sz,0,'.',',');
		return $b;
	}

	function tblinfo($n) {
		global $tbl;
		$t=$tbl[$n];
		return "
		<tr align=right>
		<td class='tdbg2 center'>$t[Name]</td>
		<td class='tdbg2'>".sp($t['Rows']) ."</td>
		<td class='tdbg2'>".sp($t['Avg_row_length'])."</td>
		<td class='tdbg2'>".sp($t['Data_length'])."</td>
		<td class='tdbg2'>".sp($t['Index_length'])."</td>
		<td class='tdbg2'>".sp($t['Data_free'])."</td>
		<td class='tdbg2'>".sp($t['Data_length']+$t['Index_length'])."</td></tr>";
	}

?>
