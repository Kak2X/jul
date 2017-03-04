<?php

	require 'lib/function.php';

	pageheader("Thread Repair System");
	
	admincheck('forum-admin');
	
	print adminlinkbar("admin-threads.php");

	if (!isset($_POST['run'])) {
		?>
		<form action="admin-threads.php" method="post">  
		<table class='table'>
			<tr><td class='tdbgh center'>Thread Repair System</td></tr>
			<tr><td class='tdbg1 center'>&nbsp;
				<br>This page is intended to repair threads with broken reply counts. Please don't flood it with requests.
				<br>This problem causes "phantom pages" (e.g., too few or too many pages displayed).
				<br>&nbsp;
				<br><input type='submit' class=submit name="run" value="Start"><input type='hidden' name="auth" value="<?=generate_token()?>">
				<br>&nbsp;
			</td></tr>
		</table>
		</form>
		<?php
	} else {
		
		check_token($_POST['auth']);

		?>
		<table class='table'>
			<tr><td class='tdbgh center'>Thread Repair System</td></tr>
			<tr><td class='tdbg1 center'>Now running.
			</td></tr>
		</table>
		<br>
		<table class='table'>
			<tr>
				<td class='tdbgh center'>id#</td>
				<td class='tdbgh center'>Name</td>
				<td class='tdbgh center'>Reports</td>
				<td class='tdbgh center'>Real</td>
				<td class='tdbgh center'>Err</td>
				<td class='tdbgh center'>Status</td>
			</tr>
		<?php

		//dammit, no long queries on one single line
		//$q	= "SELECT `posts`.`thread`, (COUNT(`posts`.`id`)) AS 'real', ((COUNT(`posts`.`id`) - 1) - `threads`.`replies`) AS 'offset', `threads`.`replies`, `threads`.`title` AS `threadname`  FROM `posts` LEFT JOIN `threads` ON `posts`.`thread` = `threads`.`id` GROUP BY `thread` ORDER BY `offset` DESC";
		//$sql	= mysql_query($q) or die(mysql_error());
		
		$threads = $sql->query("
			SELECT p.thread, (COUNT(p.id)) 'real', ((COUNT(p.id) - 1) - CAST(t.replies AS SIGNED)) offset, t.replies, t.title threadname
			FROM posts p
			LEFT JOIN threads t ON p.thread = t.id
			GROUP BY p.thread HAVING offset != 0
			ORDER BY offset DESC
		");

		$count	= "";
		$update = $sql->prepare("UPDATE threads SET replies = :replies WHERE id = :id");
		while ($data = $sql->fetch($threads)) {

			$status	= "";

			//if ($data['offset'] != 0) {

			if ($data['offset'] >= 10000000) { 
				$data['offset']	= ($data['real'] - 1) - $data['replies'];
//				$status			= "<font color=\"#ff8080\">First post missing or otherwise broken</font>";
//				$data['offset']	= "&nbsp;";
			}

			if (!$status) {
				//mysql_query("UPDATE `threads` SET `replies` = '". ($data['real'] - 1) ."' WHERE `id` = '". $data['thread'] ."'") or "<font color=#ff0000>Error</font>: ". mysql_error();
				$status	= $sql->execute($update, ['replies' => $data['real']-1, 'id' => $data['thread']]);
				if ($status) 	$status = "<font color=#80ff80>Updated</font>";
				else 			$status = "<font color=#ff0000>Error</font>";
//				$status	= "Not updated";
				$count++;
			}

			?>
			<tr>
				<td class='tdbg1 center'><?=$data['thread']?></td>
				<td class='tdbg2'><a href="thread.php?id=<?=$data['thread']?>"><?=$data['threadname']?></a></td>
				<td class='tdbg1 right'><?=$data['replies']?></td>
				<td class='tdbg1 right'><?=$data['real']?></td>
				<td class='tdbg2 right'><b><?=$data['offset']?></b></td>
				<td class='tdbg1'><?=$status?></td>
			</tr>
			<?php

			//} else {
			//	break;
			//}
		}

		if ($count) {
			print "<tr><td class='tdbgc center' colspan=6>$count thread". ($count != 1 ? "s" : "") ." updated.</td></tr>";
		} else {

			?>
			<tr>
				<td class='tdbg1 center' colspan=6>&nbsp;
					<br>No problems found.
					<br>&nbsp;
				</td>
			</tr>
			<?php
		}
		?>
		</table><?php
	}

	pagefooter();
?>