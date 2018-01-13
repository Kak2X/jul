<?php

require 'utils/Parents/Counter.php';

class FixForums extends Counter {
	
	public function __construct() {
		$this->title       = "Forum Repair System II";
		$this->description = "This page is intended to repair forums with broken 'last reply' times/users.\n".
		                     "This problem could cause a <span style='color: #FF0000' class='nobr fonts b'>[Deleted user]</span> tag to appear rather than the correct user.";
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

		
		// For each forum get the last post's date and the reported last post date
		// Return only forums with inconsistent values (diff != 0)
		$forums = $sql->query("
			SELECT f.id, f.title, f.lastpostdate, t.date realdate, (t.date - f.lastpostdate) diff
			FROM forums f
			LEFT JOIN (SELECT MAX(lastpostdate) as date, forum FROM threads GROUP BY forum) t ON t.forum = f.id
			HAVING diff != 0
			ORDER BY diff DESC
		");
		
		$count	= 0;
		$update = $sql->prepare("UPDATE forums SET lastpostdate = :date, lastpostuser = :user WHERE id = :forum");
		
		while ($data = $sql->fetch($forums)) {
			
			$userd	= $sql->fetchq("SELECT lastpostdate, lastposter FROM threads WHERE forum = '{$data['id']}' ORDER BY lastpostdate DESC LIMIT 1");
			$status	= $sql->execute($update, ['date' => $userd['lastpostdate'], 'user' => $userd['lastposter'], 'forum' => $data['id']]);
			if ($status) $status = "<font color=#80ff80>Updated</font>";
			else         $status = "<font color=#ff0000>Error</font>";
			$count++;

			?>
			<tr>
				<td class='tdbg1 center'><?=$data['id']?></td>
				<td class='tdbg2'><a href="forum.php?id=<?=$data['id']?>"><?=$data['title']?></a></td>
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

$util = new FixForums();