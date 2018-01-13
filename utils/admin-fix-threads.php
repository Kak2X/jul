<?php

require 'utils/Parents/Counter.php';

class FixThreads extends Counter {
	
	public function __construct() {
		$this->title       = "Thread Repair System";
		$this->description = "This page is intended to repair threads with broken reply counts. Please don't flood it with requests.\n".
		                     "This problem causes \"phantom pages\" (e.g., too few or too many pages displayed).";
	}
	
	public function launch() {
		global $sql;
		
		?>
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
		
		// For each thread get the number of real posts, reported replies, and 
		// the difference between reported replies and real replies (real posts - 1).
		// Return only threads with an inconsistent values (offset != 0)
		$threads = $sql->query("
			SELECT p.thread, (COUNT(p.id)) 'real', ((COUNT(p.id) - 1) - CAST(t.replies AS SIGNED)) offset, t.replies, t.title threadname
			FROM posts p
			LEFT JOIN threads t ON p.thread = t.id
			GROUP BY p.thread HAVING offset != 0
			ORDER BY offset DESC
		");

		$count	= 0;
		$update = $sql->prepare("UPDATE threads SET replies = :replies WHERE id = :id");
		
		// Cycle through broken threads
		while ($data = $sql->fetch($threads)) {

			$status	= $sql->execute($update, ['replies' => $data['real']-1, 'id' => $data['thread']]);
			if ($status) $status = "<font color=#80ff80>Updated</font>";
			else         $status = "<font color=#ff0000>Error</font>";
			$count++;

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
		}
		?>
		</table>
		<?php		
		
		return $count;
	}
}

$util = new FixThreads();