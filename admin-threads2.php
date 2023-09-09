<?php

	require "lib/common.php";

	pageheader("Thread Repair System II");

	admincheck();
  
	print adminlinkbar("admin-threads2.php");

	if (!isset($_POST['run'])) {
		?>
		<form action="admin-threads2.php" method="post">  
		<table class='table'>
			<tr><td class='tdbgh center'>Thread Repair System II</td></tr>
			<tr><td class='tdbg1 center'>&nbsp;
				<br>This page is intended to repair threads with broken 'last reply' times/users.
				<br>This problem causes bumped threads that shouldn't be, especially with badly deleted posts.
				<br>&nbsp;
				<br><input type='submit' name="run" value="Start"><?= auth_tag() ?>
				<br>&nbsp;
			</td></tr>
		</table>
		</form>
		<?php
	} else {

		?>
		<table class='table'>
			<tr><td class='tdbgh center'>Thread Repair System II</td></tr>
			<tr><td class='tdbg1 center'>Now running.
			</td></tr>
		</table>
		<br>
		<table class='table'>
			<tr>
				<td class='tdbgh center'>id#</td>
				<td class='tdbgh center'>Name</td>
				<td class='tdbgh center'>Reported Date</td>
				<td class='tdbgh center'>Real Date</td>
				<td class='tdbgh center'>Difference</td>
				<td class='tdbgh center'>Status</td>
			</tr>
		<?php
 	
		$threads = $sql->query("
			SELECT t.id, t.title, t.lastpostdate, p.date as realdate 
			FROM threads t
			LEFT JOIN (SELECT MAX(date) as date, thread FROM posts GROUP BY thread) p ON p.thread = t.id
			WHERE t.lastpostdate != p.date
			ORDER BY t.id DESC
		");

		$count	= "";
		$update = $sql->prepare("UPDATE threads SET lastposter = :lastposter, lastpostdate = :lastpostdate WHERE id = :id");
		while ($data = $sql->fetch($threads)) {

			$status	= "";

			if (!$data['lastpostdate'] && $data['realdate'] === NULL) {
				$status	= "<font color=#ff8888>Broken thread</font>";
			} else {
				$userd	= $sql->fetchq("SELECT date, user FROM posts WHERE thread = '{$data['id']}' ORDER BY date DESC LIMIT 1");
				$status	= $sql->execute($update, ['lastposter' => $userd['user'], 'lastpostdate' => $userd['date'], 'id' => $data['id'] ]); 
				if ($status) 	$status = "<font color=#80ff80>Updated</font>";
				else			$status = "<font color=#ff0000>Error</font>";
				$count++;
			}
			
			?>
		<tr>
			<td class='tdbg1 center'><?=$data['id']?></td>
			<td class='tdbg2'><a href="thread.php?id=<?=$data['id']?>"><?= htmlspecialchars($data['title']) ?></a></td>
			<td class='tdbg1 center'><?=($data['lastpostdate'] ? printdate($data['lastpostdate']) : "-")?></td>
			<td class='tdbg1 center'><?=($data['realdate'] ? printdate($data['realdate']) : "-")?></td>
			<td class='tdbg1 center'><?=timeunits2($data['lastpostdate'] - $data['realdate'])?></td>
			<td class='tdbg2'><?=$status?></td>
		</tr>
			<?php
			//}
		}

		if ($count) {
			print "<tr><td class='tdbgc center' colspan=6>$count thread". ($count != 1 ? "s" : "") ." updated.</td></tr>";
		} else {
			print "<tr><td class='tdbgc center' colspan=6>Nothing to repair.</td></tr>";
		}
		?>
	</table>
		<?php
	}
	
	pagefooter();
?>