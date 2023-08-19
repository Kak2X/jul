<?php
	require "lib/common.php";

	$_GET['id'] = filter_int($_GET['id']);
	
	$qstrings = array();

	if ($_GET['id']) {
		$qstrings[] = "user = {$_GET['id']}";
		$from = 'from '.$sql->resultq("SELECT name FROM users WHERE id = {$_GET['id']}");
	} else {
		$from = "from the board";
	}

	$_GET['posttime'] = isset($_GET['posttime']) ? (int) $_GET['posttime'] : 86400;
	
	if (!$_GET['id'] && (!$_GET['posttime'] || $_GET['posttime'] > 2592000)) // All posts
		$_GET['posttime'] = 2592000;

	if ($_GET['posttime']) {
		$qstrings[] = "date > ".(time()-$_GET['posttime']);
		$during = ' during the last '.timeunits2($_GET['posttime']);
	} else {
		$during = "";
	}
	
	if (empty($qstrings)) $qwhere = '1';
	else $qwhere = implode(' AND ', $qstrings);	

	$posts = $sql->query("
		SELECT COUNT(*) AS cnt, FROM_UNIXTIME(date,'%k') AS hour 
		FROM posts 
		WHERE {$qwhere} 
		GROUP BY hour
	");

	
	
	pageheader("Posts by time of day");

	$qid = ($_GET['id'] ? "id={$_GET['id']}&" : "");
?>
	<span class="fonts">
		Timeframe:
		<a href='postsbytime.php?id=<?=$qid?>&posttime=86400'>Last day</a> |
		<a href='postsbytime.php?id=<?=$qid?>&posttime=604800'>Last week</a> |
		<a href='postsbytime.php?id=<?=$qid?>&posttime=2592000'>Last 30 days</a> |
		<a href='postsbytime.php?id=<?=$qid?>&posttime=31536000'>Last year</a> |
		<a href='postsbytime.php?id=<?=$qid?>&posttime=0'>All-time</a>
		<br>
	</span>
	<span class="font"> Posts <?=$from?> by time of day<?=$during?>:</span>
	<table class='table'>
		<tr>
			<td class='tdbgh center' width=100>Time</td>
			<td class='tdbgh center' width=50>Posts</td>
			<td class='tdbgh center'>&nbsp;</td>
		</tr>
<?php

	$postshour = array_fill(0, 24, 0);
	$max = 0;
	while($h = $sql->fetch($posts))
		if (($postshour[$h['hour']] = $h['cnt']) > $max)
			$max = $h['cnt'];

	for($i = 0; $i < 24 ;++$i) {
		$time = sprintf('%1$02d:00 - %1$02d:59', $i);
		$bar  = drawminibar($max, 8, $postshour[$i], "images/bar/{$numdir}bar-on.png");

		?>
		<tr>
			<td class='tdbg2 fonts center'><?=$time?></td>
			<td class='tdbg2 fonts center'><?=$postshour[$i]?></td>
			<td class='tdbg1 fonts' width=*><?=$bar?></td>
		</tr>
		<?php
	}
?>	</table>
<?php

	pagefooter();