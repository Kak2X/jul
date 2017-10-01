<?php

	require 'lib/function.php';

	pageheader("Forum Repair System");
	
	admin_check('forum-admin');
	
	print adminlinkbar();

	if (!isset($_POST['run'])) {
		?>
		<form action="admin-forums.php" method="post">  
		<table class='table'>
			<tr><td class='tdbgh center'>Forum Repair System</td></tr>
			<tr><td class='tdbg1 center'>&nbsp;
				<br>This page is intended to repair forums with broken reply counts.
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
			<tr><td class='tdbgh center'>Forum Repair System</td></tr>
			<tr><td class='tdbg1 center'>Now running.
			</td></tr>
		</table>
		<br>
		<table class='table'>
			<tr>
				<td class='tdbgh center'>id#</td>
				<td class='tdbgh center'>Name</td>
				<td class='tdbgh center'>Posts</td>
				<td class='tdbgh center'>Real Posts</td>
				<td class='tdbgh center'>Err</td>
				<td class='tdbgh center'>Threads</td>
				<td class='tdbgh center'>Real Threads</td>
				<td class='tdbgh center'>Err</td>
				<td class='tdbgh center'>Status</td>
			</tr>
		<?php

		$forums = $sql->query("
			SELECT 	f.id, f.title, f.numthreads, f.numposts, 
					(SUM(t.replies) + COUNT(t.id)) realposts, COUNT(t.id) realthreads,
					((SUM(t.replies) + COUNT(t.id)) - CAST(f.numposts AS SIGNED)) offsetposts, 
					((COUNT(t.id)) - CAST(f.numthreads AS SIGNED)) offsetthreads
			FROM forums f
			LEFT JOIN threads t ON t.forum = f.id
			GROUP BY f.id 
			HAVING offsetposts != 0 OR offsetthreads != 0
			ORDER BY id ASC
		");

		$count	= 0;
		$update = $sql->prepare("UPDATE forums SET numposts = :numposts, numthreads = :numthreads WHERE id = :id");
		while ($data = $sql->fetch($forums)) {

			$status	= $sql->execute($update, ['numposts' => $data['realposts'], 'numthreads' => $data['realthreads'], 'id' => $data['id']]);
			if ($status) 	$status = "<font color=#80ff80>Updated</font>";
			else 			$status = "<font color=#ff0000>Error</font>";
			$count++;

			?>
			<tr>
				<td class='tdbg1 center'><?=$data['id']?></td>
				<td class='tdbg2'><a href="forum.php?id=<?=$data['id']?>"><?=$data['title']?></a></td>
				<td class='tdbg1 right'><?=$data['numposts']?></td>
				<td class='tdbg1 right'><?=$data['realposts']?></td>
				<td class='tdbg2 right'><b><?=$data['offsetposts']?></b></td>
				<td class='tdbg1 right'><?=$data['numthreads']?></td>
				<td class='tdbg1 right'><?=$data['realthreads']?></td>
				<td class='tdbg2 right'><b><?=$data['offsetthreads']?></b></td>
				<td class='tdbg1'><?=$status?></td>
			</tr>
			<?php
		}

		if ($count) {
			print "<tr><td class='tdbgc center' colspan=9>$count forum". ($count != 1 ? "s" : "") ." updated.</td></tr>";
		} else {

			?>
			<tr>
				<td class='tdbg1 center' colspan=9>&nbsp;
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