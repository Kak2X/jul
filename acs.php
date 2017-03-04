<?php
	require 'lib/function.php';
	
	$username 	= htmlspecialchars(filter_string($_GET['username']));
	$u			= filter_int($_GET['u']);
	$view		= filter_int($_GET['view']);
	//$vd = date('m-d-y', ctime());
	
	$timestamp = fieldstotimestamp('other', '_GET');
	if (!$timestamp) $timestamp = ctime() - 86400;
	
	$m	= date("m", $timestamp);
	$d	= date("d", $timestamp);
	$y	= date("y", $timestamp);
	
	$v = filter_int($_GET['v']);
	if (!$v) {
		$dd		= mktime(0,0,0,$m,$d-1,$y);// + (3*3600);
		$dd2	= mktime(0,0,0,$m,$d,$y);// + (3*3600);
		//$dd		= mktime(0,0,0,substr($vd,0,2),substr($vd,3,2),substr($vd,6,2));// + (3*3600);
		//$dd2	= mktime(0,0,0,substr($vd,0,2),substr($vd,3,2)+1,substr($vd,6,2));// + (3*3600);
	} else {
		$dd		= mktime(0,0,0,$m,$d,$y);// + (3*3600);
		$dd2	= mktime(0,0,0,$m,$d+1,$y);// + (3*3600);
	}

	$users = $sql->query("
		SELECT $userfields, COUNT(*) cnt 
		FROM users AS u
		INNER JOIN posts p ON u.id = p.user
		WHERE p.date >= $dd AND p.date < $dd2 AND u.group NOT IN(".GROUP_BANNED.",".GROUP_PERMABANNED.")
		GROUP BY u.id 
		ORDER BY cnt DESC
	");

	if (!$u) {				// Yourself
		$n = $loguser['name'];
	}
	else if ($u == 2)		// Other user
		$n = $username;
	else					// None
		$n = '';

	if(!$view || $view <= 0 || $view > 2) $view=0;

	$ch1[$v]	= 'checked';
	$ch2[$u]	= 'checked';
	$ch3[$view]	= 'checked';

	$tposts		= $sql->resultq("SELECT COUNT(*) FROM posts WHERE date > $dd AND date < $dd2");
	$rcount		= ($tposts >= 400 ? 10 : 5);
	$spoints	= ($tposts >= 400 ? 11 : 8);
	
	pageheader();
	?>
	<form action=acs.php>
	<table class='table'>
		<tr><td class='tdbgh center' colspan=2>Currently viewing <?=date('m-d-y',$dd)?></td></tr>
		
		<tr>
			<td class='tdbg1 center'>
				<b>Day:</b><br>
				<span class='fonts'> Select the day to view rankings from. (mm-dd-yy format)</span>
			</td>
			<td class='tdbg2'>
				<input type=radio class='radio' name=v value=0 <?=filter_string($ch1[0])?>> Today &nbsp;&nbsp;
				<input type=radio class='radio' name=v value=1 <?=filter_string($ch1[1])?>> Other: <?=datetofields($timestamp, 'other', true, false, true)?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>
				<b>User:</b><br>
				<span class='fonts'> This user will be highlighted.</span>
			</td>
			<td class='tdbg2'>
				<input type=radio class='radio' name=u value=1 <?=filter_string($ch2[1])?>> None &nbsp;&nbsp;
				<input type=radio class='radio' name=u value=0 <?=filter_string($ch2[0])?>> You &nbsp;&nbsp;
				<input type=radio class='radio' name=u value=2 <?=filter_string($ch2[2])?>> Other: <input type='text' name=username VALUE="<?=$username?>" SIZE=25 MAXLENGTH=25>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'><b>View format:</b></td>
			<td class='tdbg2'>
				<input type=radio class='radio' name=view value=0 <?=filter_string($ch3[0])?>> Full rankings &nbsp;&nbsp;
				<input type=radio class='radio' name=view value=1 <?=filter_string($ch3[1])?>> Rankers &nbsp;&nbsp;
				<input type=radio class='radio' name=view value=2 <?=filter_string($ch3[2])?>> JCS form
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2'><input type=submit value=Submit></td>
		</tr>
	</table>
	</form>
	<!-- ACS results -->
	<table class='table'>
		<tr>
	<?php
	$max = 1;
	if ($view < 2) {
		// Full rankings
		?>
			<td class='tdbgh center' width=30>#</td>
			<td class='tdbgh center' width=60%>Name</td>
			<td class='tdbgh center' width=50>Posts</td>
			<td class='tdbgh center' width=*>Total: <?=$tposts?></td>
		</tr>
		<?php
		$rp = $rr = NULL;
		for ($i = 1; $user = $sql->fetch($users); ++$i) {
			
			if ($user['cnt'] > $max)
				$max = $user['cnt'];
			
			if ($rp != $user['cnt']) $r = $i;
			$rp = $user['cnt'];

			// Don't rank with 1 post
			if ($user['cnt'] <= 1 && $rcount >= $r)
				$rcount = $r-1;
			if($rr <= $rcount && $r > $rcount && !$view) 
				print "<tr><td class='tdbgc center' colspan=4><img src='images/_.gif' height='4' width='1'></td></tr>";
			
			$rr 	= $r;
			$b 		= $slashb = '';
			
			$col 	= '1';	// Ranked
			if($r > $rcount) $col = '2'; // Not ranked
			if(!strcasecmp($user['name'], $n)){
				$col 	= 'c'; // You (or whoever's selected)
				$b 		= '<b>';
				$slashb = '</b>';
			}
			
			if(!$view  || ($view == 1 && ($r<=$rcount || !strcasecmp($user['name'], $n)))) {
				if (isset($_GET['dur'])) $user['name'] = "DU". str_repeat("R", mt_rand(1,25));
				print "
				<tr>
					<td class='tdbg{$col} center'>$b$r$slashb</td>
					<td class='tdbg{$col}'>".getuserlink($user)."</td>
					<td class='tdbg{$col} center'>$b{$user['cnt']}$slashb</td>
					<td class='tdbg{$col}'><img src='images/{$numdir}bar-on.gif' width=".($user['cnt']*100/$max)."% height=8></td>
				</tr>";
			}
		}
	} else {
		// Ranked yesterday:
//		$usersy=mysql_query("SELECT users.id,users.name,users.sex,users.powerlevel,COUNT(posts.id) AS cnt FROM users,posts WHERE posts.user=users.id AND posts.date>".($dd-86400)." AND posts.date<$dd GROUP BY users.id ORDER BY cnt DESC");
//		$i=0;
//		while($user=mysql_fetch_array($usersy) and $r <= $rcount ) {
//			$i++;
//			if($rp!=$user['cnt']) $r=$i;
//			$rp=$user['cnt'];
//			if($r<=5) $ranky[$user['id']]=$r;
//		}

		// JCS Form
		$rp = 0;
		$r  = 0;
		$i  = 0;
		$tie = $tend = NULL;
		$dailyposts = $dailypoints = $ndailyposts = $ndailypoints = "";
		for ($i = 1; $user = $sql->fetch($users) and $r <= $rcount; ++$i){
			// Don't rank with 1 post
			if ($user['cnt'] <= 1 && $rcount >= $r) {
				$rcount = $r-1;
			}
			if ($rp != $user['cnt']) {
				$r = $i;
				if ($tend) $tie = '';
				if ($tie) $tend = 1;
			} else {
				$tie  = 'T';
				$tend = 0;
			}
			$posts[$user['id']] = $user['cnt'];
			// Ranked yesterday:
//			$ry=$ranky[$user['id']];
//			if(!$ry) $ry='NR';

			$rp = $user['cnt'];
			//$myfakename = (($user['aka'] && $user['aka'] != $user['name']) ? "$user[aka] ($user[name])" : $user['name']);
			//$myrealname = (($user['aka']) ? $user['aka'] : $user['name']);
			$myfakename = $myrealname = $user['name'];
			
			
			$dailyposts		.= $tie . $ndailyposts;
			$dailypoints	.= $tie . $ndailypoints;
			$ndailyposts	= "$r) ". $myfakename ." - ". $user['cnt'] ."<br>";
			$ndailypoints	= "$r) ". $myrealname ." - ". ($spoints - $r) ."<br>";

//			$ndailyposts	= "$tie$r) ". $user['name'] ." - ". $user['cnt'] ." - ". ($spoints - $r) ."<br>";
//			$ndailyposts	= "$tie$r) ". $user['name'] ." - ". $user['cnt'] ." - ". ($spoints - $r) ."<br>";

		}
		// "Fix" to last line being cut off
		if ($i > 1) {
			$dailyposts		.= $tie . $ndailyposts;
			$dailypoints	.= $tie . $ndailypoints;
			$ndailyposts	= "$r) ". $myfakename ." - ". $user['cnt'] ."<br>";
			$ndailypoints	= "$r) ". $myrealname ." - ". ($spoints - $r) ."<br>";
		}
		if($r <= $rcount) {
			if ($tend) $tie='';
	//			$dailyposts.=$tie.$ndailyposts;
	//			$dailypoints.=$tie.$ndailypoints;
		}

		// More ranked yesterday stuff
//		$lose=$user[cnt];
//		@mysql_data_seek($usersy,0);
//		$i=0;
//		$rp=0;
//		$r=0;
//		while($user=mysql_fetch_array($usersy) and $r<=$rcount){
//			$i++;
//			if($rp!=$user[cnt]) $r=$i;
//			$rp=$user[cnt];
//			if($posts[$user[id]]<=$lose && $r<=$rcount) $offcharts.=($offcharts?', ':'OFF THE CHARTS: ')."$user[name] ($r)";
//		}

		print 
			"<td class='tdbg1'>".
			 strtoupper(date('F j',$dd)) ."<br>".
			"---------<br><br>".
			"TOTAL NUMBER OF POSTS: $tposts<br><br>".
			"$dailyposts<br><br>".
			"DAILY POINTS<br>".
			"--------------------<br>".
			"$dailypoints";
	}
	
?>
	</table>
<?php

	pagefooter();
