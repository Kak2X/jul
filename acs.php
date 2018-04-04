<?php
	require 'lib/function.php';
	
	$_GET['username'] = filter_string($_GET['username'], true);
	$_GET['u']        = filter_int($_GET['u']); // Selected user
	$_GET['view']     = filter_int($_GET['view']); // Acs view mode
	$_GET['cday']     = filter_int($_GET['cday']); // Custom day
	
	/*
		If no timestamp is received or the custom day option isn't selected,
		reset the date to today.
	*/
	if (!$_GET['cday'] || !$timestamp = fieldstotimestamp('o', '_GET')) {
		$timestamp = ctime();
	}
	
	$m  = date("m", $timestamp);
	$d  = date("d", $timestamp);
	$y  = date("y", $timestamp);
	
	$daystart = mktime(0,0,0,$m,$d,$y) + $config['server-time-offset'];
	$dayend   = $daystart + 86400;

	// We can already filter out those who made only 1 post here
	$users = $sql->query("
		SELECT $userfields, COUNT(*) cnt 
		FROM users AS u
		INNER JOIN posts p ON u.id = p.user
		WHERE p.date >= $daystart 
		  AND p.date < $dayend 
		  AND u.powerlevel >= 0
		GROUP BY u.id 
		HAVING COUNT(*) > 1 OR u.id = {$_GET['u']}
		ORDER BY cnt DESC
	");

	if (!$_GET['u']) {              // Yourself
		$n = $loguser['name'];
	} else if ($_GET['u'] == 2) {   // Other user
		$n = $_GET['username'];
	} else {                        // None
		$n = '';
	}
	
	$_GET['view'] = numrange($_GET['view'], 0, 2);

	$ch1[$_GET['cday']]	= 'checked';
	$ch2[$_GET['u']]	= 'checked';
	$ch3[$_GET['view']]	= 'checked';

	/*
		In case we had many posts in the day (>400), 
		raise the limit for ranking in
	*/
	$tposts	    = $sql->resultq("SELECT COUNT(*) FROM posts WHERE date > $daystart AND date < $dayend");
	$rcount     = ($tposts >= 400 ? 10 : 5); // Max sets of positions
	$spoints    = ($tposts >= 400 ? 11 : 8);
	
	
	
	pageheader();
	
	?>
	<form action='acs.php'>
	<table class='table'>
		<tr><td class='tdbgh center' colspan=2>Currently viewing <?= date('m-d-y', $daystart) ?></td></tr>
		
		<tr>
			<td class='tdbg1 center'>
				<div class='b'>Day:</div>
				<span class='fonts'> Select the day to view rankings from. (mm-dd-yy format)</span>
			</td>
			<td class='tdbg2'>
				<input type="radio" name="cday" value=0 <?=filter_string($ch1[0])?>> Today &nbsp;&nbsp;
				<input type="radio" name="cday" value=1 <?=filter_string($ch1[1])?>> Other: <?=datetofields($timestamp, 'o', DTF_DATE | DTF_NOLABEL)?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>
				<div class='b'>User:</div>
				<span class='fonts'> This user will be highlighted.</span>
			</td>
			<td class='tdbg2'>
				<input type="radio" name="u" value=1 <?=filter_string($ch2[1])?>> None &nbsp;&nbsp;
				<input type="radio" name="u" value=0 <?=filter_string($ch2[0])?>> You &nbsp;&nbsp;
				<input type="radio" name="u" value=2 <?=filter_string($ch2[2])?>> Other: <input type='text' name=username VALUE="<?=$_GET['username']?>" SIZE=25 MAXLENGTH=25>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>View format:</td>
			<td class='tdbg2'>
				<input type="radio" name="view" value=0 <?=filter_string($ch3[0])?>> Full rankings &nbsp;&nbsp;
				<input type="radio" name="view" value=1 <?=filter_string($ch3[1])?>> Rankers &nbsp;&nbsp;
				<input type="radio" name="view" value=2 <?=filter_string($ch3[2])?>> JCS form
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2'><input type="submit" value="Submit"></td>
		</tr>
	</table>
	</form>
	<!-- ACS results -->
	<table class='table'>
		<tr>
	<?php
	$max       = 1; // Placeholder value
	$ranked    = true;	
	if ($_GET['view'] < 2) {
		// Full rankings
		?>
			<td class='tdbgh center' style="width: 30px">#</td>
			<td class='tdbgh center' style="width: 60%">Name</td>
			<td class='tdbgh center' style="width: 50px">Posts</td>
			<td class='tdbgh center'>Total: <?=$tposts?></td>
		</tr>
		<?php
		$rprev     = NULL;
		$print_sep = false;
		for ($i = 1; $user = $sql->fetch($users); ++$i) {
			
			// Set the real max post value (will only happen on the first loop)
			if ($user['cnt'] > $max) {
				$max = $user['cnt'];
			}
			
			// Ties stop the counter
			// So, we restore them to the original value once the tie is over
			if ($rprev != $user['cnt']) {
				$r = $i;
			}
			$rprev     = $user['cnt'];
			
			if ($user['cnt'] <= 1 && $ranked) {
				$ranked = false;
			} else if ($ranked) { // Once it's not ranked, it's over
				$ranked = $r <= $rcount;
			}

			if (!$print_sep && !$ranked && !$_GET['view']) {
?>			<tr><td class="tdbgc center" style="height: 4px" colspan=4></td></tr><?php		
				$print_sep = true;
			}
			
			if (!strcasecmp($user['name'], $n)) {
				$col = 'c b'; // You (or whoever's selected)
			} else if ($ranked) {
				$col = '1';
			} else {
				$col = '2';
			}
			
			//if (!$_GET['view'] || ($_GET['view'] == 1 && ($ranked || !strcasecmp($user['name'], $n)))) {
			if (!$_GET['view'] || $ranked || $col == 'c b') {
				
				if (isset($_GET['dur'])) {
					$user['name'] = "DU". str_repeat("R", mt_rand(1,25));
				}
			?>
				<tr>
					<td class="tdbg<?=$col?> center"><?=$r?></td>
					<td class="tdbg<?=$col?>"><?=getuserlink($user)?></td>
					<td class="tdbg<?=$col?> center"><?=$user['cnt']?></td>
					<td class="tdbg<?=$col?>"><?= drawminibar($max, 8, $user['cnt'], "images/bar/{$numdir}bar-on.png") ?></td>
				</tr>
			<?php		
			}
		}
	} else {
		
		// Ranked yesterday:
//		$usersy=$sql->query("SELECT users.id,users.name,users.sex,users.powerlevel,COUNT(posts.id) AS cnt FROM users,posts WHERE posts.user=users.id AND posts.date>".($dd-86400)." AND posts.date<$dd GROUP BY users.id ORDER BY cnt DESC");
//		$i=0;
//		while($user=$sql->fetchq($usersy) and $r <= $rcount ) {
//			$i++;
//			if($rprev!=$user['cnt']) $r=$i;
//			$rprev=$user['cnt'];
//			if($r<=5) $ranky[$user['id']]=$r;
//		}

		// JCS Form		
		$rprev  = 0;
		$r      = 0;
		$ranked = true;
		$tie = $tend = NULL;
		$dailyposts = $dailypoints = $ndailyposts = $ndailypoints = "";
		for ($i = 1; $user = $sql->fetch($users); ++$i){
			
			// Don't rank with 1 post or if we're over the limit
			if ($user['cnt'] <= 1 && $rcount >= $r) {
				$ranked = false;
				break;
			}
			
			// Set a tie if the previous counter is the same as the current 
			if ($rprev != $user['cnt']) {
				$r = $i;
				if ($tend) $tie  = '';
				if ($tie)  $tend = 1;
			} else {
				$tie  = 'T';
				$tend = 0;
			}
			//$posts[$user['id']] = $user['cnt'];
			// Ranked yesterday:
//			$ry=$ranky[$user['id']];
//			if(!$ry) $ry='NR';

			$rprev = $user['cnt'];
			
			
			$myfakename = (($user['aka'] && $user['aka'] != $user['name']) ? "{$user['aka']} ({$user['name']})" : $user['name']);
			$myrealname = (($user['aka']) ? $user['aka'] : $user['name']);
			//$myfakename = $myrealname = $user['name'];
			
			// To handle ties we need to know if this counter is the
			// same as the next fetched row
			// So we set the previous counter here...
			$dailyposts     .= $tie . $ndailyposts;
			$dailypoints    .= $tie . $ndailypoints;
			// ...and THEN we update it to the current
			$ndailyposts     = "$r) ". $myfakename ." - ". $user['cnt'] ."<br>";
			$ndailypoints    = "$r) ". $myrealname ." - ". ($spoints - $r) ."<br>";

//			$ndailyposts	= "$tie$r) ". $user['name'] ." - ". $user['cnt'] ." - ". ($spoints - $r) ."<br>";
//			$ndailyposts	= "$tie$r) ". $user['name'] ." - ". $user['cnt'] ." - ". ($spoints - $r) ."<br>";

		}
		// Last line of the ranked.
		if($i > 1) {
			if ($tend) {
				$tie = '';
			}
			$dailyposts    .= $tie . $ndailyposts;
			$dailypoints   .= $tie . $ndailypoints;
		}

		// More ranked yesterday stuff
//		$lose=$user[cnt];
//		@mysql_data_seek($usersy,0);
//		$i=0;
//		$rprev=0;
//		$r=0;
//		while($user=mysql_fetch_array($usersy) and $r<=$rcount){
//			$i++;
//			if($rprev!=$user[cnt]) $r=$i;
//			$rprev=$user[cnt];
//			if($posts[$user[id]]<=$lose && $r<=$rcount) $offcharts.=($offcharts?', ':'OFF THE CHARTS: ')."$user[name] ($r)";
//		}

?>
		<td class='tdbg1'>
			<?= strtoupper(date('F j',$daystart)) ?><br>
			---------<br><br>
			TOTAL NUMBER OF POSTS: <?= $tposts ?><br><br>
			<?= $dailyposts ?><br><br>
			DAILY POINTS<br>
			--------------------<br>
			<?= $dailypoints ?>
		</td>
	</tr>
<?php
	}
?>
	</table>
<?php

	pagefooter();
