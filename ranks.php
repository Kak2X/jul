<?php
	require "lib/common.php";
	
	$_GET['set'] 		= filter_int($_GET['set']);
	$_GET['showall'] 	= filter_int($_GET['showall']);

	if (!$_GET['set']) $_GET['set'] = -1;
	
	//$set 		= (($_GET['set']) ? (int) $_GET['set'] : -1);
	//$showall 	= (($_GET['showall']) ? 1 : 0);

	$ranksetlist = "";
	$rsets = $sql->query('SELECT * FROM ranksets WHERE id > 0 ORDER BY id');
	while ($rset = $sql->fetch($rsets)) {
		// Select first rankset in none was specified
		if ($_GET['set'] < 0) $_GET['set'] = $rset['id'];
		$selected = ($rset['id'] == $_GET['set'] ? 'selected' : '' );
		$ranksetlist .= "<option value={$rset['id']} {$selected}>".htmlspecialchars($rset['name']);
	}
	$ch[$_GET['showall']] = 'checked';
	
	pageheader();

?>
<FORM ACTION=ranks.php NAME=REPLIER>
<table class='table'>
	<tr><td class='tdbgh center' colspan=2>&nbsp;</td></tr>
	<tr>
		<td class='tdbg1 center'><b>Rank set</b></td>
		<td class='tdbg2'>
			<select name=set><?=$ranksetlist?></select>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center'><b>Users to show</b></td>
		<td class='tdbg2'>
			<input type=radio class='radio' name=showall value=0 <?=filter_string($ch[0])?>> Selected rank set only
			&nbsp; &nbsp;
			<input type=radio class='radio' name=showall value=1 <?=filter_string($ch[1])?>> All users
		</td>
	</tr>
	<tr><td class='tdbgh center' colspan=2>&nbsp;</td></tr>
	<tr>
		<td class='tdbg1 center'>&nbsp;</td>
		<td class='tdbg2'><input type=submit class=submit value=View></td>
	</tr>
</table>
</FORM>
<br>
<table class='table'>
	<tr>
		<td class='tdbgh center' width=150>Rank</td>
		<td class='tdbgh center' width=60>Posts</td>
		<td class='tdbgh center' width=60>Ranking</td>
		<td class='tdbgh center' colspan=2>Users on that rank</td>
	</tr>
<?php

	// Print
	$useranks = ($_GET['showall'] ? '' : "AND useranks={$_GET['set']}");
	$btime = time()-86400*30; // 30 days without browsing = Marked as inactive

	$ranks = $sql->query("SELECT * FROM ranks WHERE rset = {$_GET['set']} ORDER BY num");
	$totalranks = $sql->num_rows($ranks);

	if ($totalranks > 0) {
		$rank  = $sql->fetch($ranks);

		// 300 queries [11sec] ---> 20 queries [1sec]
		$users = $sql->query("SELECT $userfields, posts, lastactivity, lastposttime FROM users u WHERE posts >= {$rank['num']} $useranks ORDER BY posts ASC");
		$user  = $sql->fetch($users);
		$total = $sql->num_rows($users);
	}
	
	for ($i = 0; $i < $totalranks; ++$i) {
		// Fetch next rank, to determine the upper bound of the current $rank
		$rankn = $sql->fetch($ranks);
		// If we reached the end of the ranks, use an extremely high post limit
		if (!$rankn) $rankn = ['num' => 8388607];

		// Continue fetching users from the ordered list until we reach the upper bound
		$userarray = array();
		$inactive  = 0;

		for ($u = 0; $user && $user['posts'] < $rankn['num']; ++$u) {
			if (max($user['lastactivity'], $user['lastposttime']) > $btime) {
				$userarray[$user['name']] = getuserlink($user);
			} else {
				++$inactive;
			}
			$user = $sql->fetch($users);
		}
		
		// Sort them alphabetically
		ksort($userarray);
		$userlisting = implode(", ", $userarray);

		if ($inactive) 		$userlisting .= ($userlisting?', ':'')."$inactive inactive";
		if (!$userlisting) 	$userlisting = '&nbsp;';

		if ($userlisting != '&nbsp;' || $rank['num'] <= $loguser['posts'] || $ismod) {
?>	<tr>
		<td class='tdbg2 fonts' width=200><?=xssfilters($rank['text'])?></td>
		<td class='tdbg1 center' width=60><?=$rank['num']?></td>
		<td class='tdbg1 center' width=60><?=$total?></td>
		<td class='tdbg1 center' width=30><?=$u?></td>
		<td class='tdbg2 fonts center' width=*><?=$userlisting?></td>
	</tr>
<?php
		} else {
?>	<tr>
		<td class='tdbg1 fonts' width=200>? ? ?</td>
		<td class='tdbg1 center' width=60>???</td>
		<td class='tdbg1 center' width=60>?</td>
		<td class='tdbg1 center' width=30>?</td>
		<td class='tdbg1 fonts center' width=*>?</td>
	</tr>
<?php
		}
		$rank = $rankn;
		$total -= $u;
	}
?>
</table>
<?php
	
	pagefooter();