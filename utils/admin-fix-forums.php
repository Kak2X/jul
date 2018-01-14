<?php

require 'utils/Parents/Counter.php';

class FixForums extends Counter {
	
	public function __construct() {
		$this->title       = "Forum Repair System";
		$this->description = "This page is intended to repair forums with broken posts and thread counts.\n".
		                     "This process assumes the thread reply counts are correct. If not sure, fix them before proceeding.";
		$this->locks       = ['forums', 'threads'];
	}
	
	public function launch() {
		global $sql;
		
		?>
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

		
		// For each forum get the assumed and "real" thread and posts counts
		// Calculating the offset in the query is simply not worth it
		$forums = $sql->query("
			SELECT  f.id, f.title, f.numthreads, f.numposts, 
			        (SUM(t.replies) + COUNT(t.id)) realposts, 
			        (COUNT(t.id)) realthreads
			FROM forums f
			LEFT JOIN threads t ON t.forum = f.id
			GROUP BY f.id 
			ORDER BY id ASC
		");
		
		$count	= 0;
		$update = $sql->prepare("UPDATE forums SET numposts = :numposts, numthreads = :numthreads WHERE id = :id");
		while ($data = $sql->fetch($forums)) {
			
			// If either of the sets don't match, fix the threads
			if (($data['realposts'] - $data['numposts']) || ($data['realthreads'] - $data['numthreads'])) {
			
				$status	= $sql->execute($update, ['numposts' => $data['realposts'], 'numthreads' => $data['realthreads'], 'id' => $data['id']]);
				if ($status) $status = "<font color=#80ff80>Updated</font>";
				else         $status = "<font color=#ff0000>Error</font>";
				$count++;

				?>
				<tr>
					<td class='tdbg1 center'><?=$data['id']?></td>
					<td class='tdbg2'><a href="forum.php?id=<?=$data['id']?>"><?=$data['title']?></a></td>
					<td class='tdbg1 right'><?=$data['numposts']?></td>
					<td class='tdbg1 right'><?=$data['realposts']?></td>
					<td class='tdbg2 right'><b><?=$data['realposts'] - $data['numposts']?></b></td>
					<td class='tdbg1 right'><?=$data['numthreads']?></td>
					<td class='tdbg1 right'><?=$data['realthreads']?></td>
					<td class='tdbg2 right'><b><?=$data['realthreads'] - $data['numthreads']?></b></td>
					<td class='tdbg1'><?=$status?></td>
				</tr>
				<?php
			}
		}
		?>
		</table>
		<?php		
		
		return $count;
	}
}

$util = new FixForums();