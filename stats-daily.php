<?php
	require "lib/common.php";
	
	pageheader("Daily stats");
	
	
?>
	<table class="table">
		<tr><td class="tdbgh center">Information</td></tr>
		<tr>
			<td class="tdbgc center">
				<br>
				Daily stats for the board. This page might take a while to fully display.
				<br><br>
			</td>
		</tr>
	</table>
	
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

	$users      = 0;
	$posts      = 0;
	$threads    = 0;
	$views      = 0;
	$stats = $sql->query("SELECT * FROM dailystats ORDER BY id ASC"); // NOTE: Originally dailystats did not have an ID. Added due to InnoDB shenanigains.
	while ($day = $sql->fetch($stats)) {
		print "
		<tr>
			<td class='tdbg1 fonts center'>{$day['date']}</td>
			<td class='tdbg2 fonts center'>{$day['users']}</td>
			<td class='tdbg2 fonts center'>{$day['posts']}</td>
			<td class='tdbg2 fonts center'>{$day['threads']}</td>
			<td class='tdbg2 fonts center'>{$day['views']}</td>
			<td class='tdbg2 fonts center'>".($day['users']-$users)."</td>
			<td class='tdbg2 fonts center'>".($day['posts']-$posts)."</td>
			<td class='tdbg2 fonts center'>".($day['threads']-$threads)."</td>
			<td class='tdbg2 fonts center'>".($day['views']-$views)."</td>
		</tr>";
		$users      = $day['users'];
		$posts      = $day['posts'];
		$threads    = $day['threads'];
		$views      = $day['views'];
	}
?>

	</table>
<?php

	pagefooter();