<?php

require 'utils/Parents/Counter.php';

class FixThreads2 extends Counter {
	
	public function __construct() {
		$this->title       = "Thread Repair System II";
		$this->description = "This page is intended to repair threads with broken 'last reply' times/users.\n".
		                     "This problem causes bumped threads that shouldn't be, especially with badly deleted posts.";
		$this->locks       = ['threads', 'posts'];
	}
	
	public function launch() {
		global $sql;
		
		?>
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
		
		// For each thread get the last post's date and the reported last post date
		// Return only threads with inconsistent values (diff != 0)
		$threads = $sql->query("
			SELECT t.id, t.title, t.lastpostdate, p.date realdate, (p.date - t.lastpostdate) diff
			FROM threads t
			LEFT JOIN (SELECT MAX(date) as date, thread FROM posts GROUP BY thread) p ON p.thread = t.id
			HAVING diff != 0
			ORDER BY diff DESC
		");

		$count	= 0;
		$update = $sql->prepare("UPDATE threads SET lastposter = :lastposter, lastpostdate = :lastpostdate WHERE id = :id");
		
		// Unlike previously, it's not possible to catch *invalid* threads from posts now
		while ($data = $sql->fetch($threads)) {

			$userd	= $sql->fetchq("SELECT date, user FROM posts WHERE thread = '{$data['id']}' ORDER BY date DESC LIMIT 1");
			$status	= $sql->execute($update, ['lastposter' => $userd['user'], 'lastpostdate' => $userd['date'], 'id' => $data['id'] ]); 
			if ($status) $status = "<font color=#80ff80>Updated</font>";
			else         $status = "<font color=#ff0000>Error</font>";
			$count++;

			?>
		<tr>
			<td class='tdbg1 center'><?=$data['id']?></td>
			<td class='tdbg2'><a href="thread.php?id=<?=$data['id']?>"><?=$data['title']?></a></td>
			<td class='tdbg1 center'><?=($data['lastpostdate'] ? printdate($data['lastpostdate']) : "-")?></td>
			<td class='tdbg1 center'><?=($data['realdate'] ? printdate($data['realdate']) : "-")?></td>
			<td class='tdbg1 center'><?=timeunits2($data['lastpostdate'] - $data['realdate'])?></td>
			<td class='tdbg2'><?=$status?></td>
		</tr>
			<?php
		}
		
		?>
		</table>
		<?php	
		
		return $count;
	}
}

$util = new FixThreads2();