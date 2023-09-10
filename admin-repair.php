<?php

$meta['cache'] = true;
	
require "lib/common.php";
admincheck();

$_POST['run'] = filter_int($_POST['run']);

if ($_POST['run'])
	check_token($_POST['auth']);

pageheader("Thread Repair System");
print adminlinkbar();

switch ($_POST['run']) {
	case 0:	
?>
	<style>
		.opt { padding: 8px 0; text-align: left; margin: auto; width: 600px }
		.opt > input, .opt > div { display: inline-block; vertical-align: middle }
	</style>
	<form method="POST" action="?">  
		<table class='table'>
			<tr><td class='tdbgh center'>Thread Repair System</td></tr>
			<tr><td class='tdbg1 center'>
				This page is intended to perform "repair" tasks, which may or may not take a while to finish.<br/>
				Please don't flood it with requests.</br>
				<br/>
				Select the task to perform:<br/>
				
				<?= 
				_opt(1, 
				"Fix counters",
				"Repairs threads and forums with broken reply counts.
				<br>This problem causes \"phantom pages\" (e.g., too few or too many pages displayed).")
				._opt(2,
				"Fix thread last reply data",
				"Repairs threads with broken 'last reply' times/users.
				<br>This problem causes bumped threads that shouldn't be, especially with badly deleted posts.")
				?>
				<input type="submit" name="submit" value="Start"/><?= auth_tag() ?>
			</td></tr>
		</table>
	</form>
<?php
		break;
		
	case 1:
		
		print "
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
		";
		
		$threads = $sql->query("
			SELECT p.thread, t.title threadname,
			       COUNT(p.id) 'real', ((COUNT(p.id) - 1) - CAST(t.replies AS SIGNED)) offset, t.replies
			FROM posts p
			LEFT JOIN threads t ON p.thread = t.id
			GROUP BY p.thread 
			HAVING offset != 0 OR offset IS NULL
			ORDER BY ISNULL(threadname) ASC, p.thread DESC
		");

		$count	= "";
		$update = $sql->prepare("UPDATE threads SET replies = ? WHERE id = ?");
		while ($data = $sql->fetch($threads)) {
			$status	= "";

			if ($data['replies'] === NULL) { 
				$status				= "<span style='color: #ff8080'>Invalid thread</span>";
				$data['threadname'] = "<em>(Deleted thread)</em>";
				$data['replies'] = $data['offset'] = "&mdash;";
			} else {
				$status	= _status($sql->execute($update, [$data['real']-1, $data['thread']]));
				$count++;
				$data['replies']++;
				$data['threadname'] = htmlspecialchars($data['threadname']);
			}

			print "
			<tr>
				<td class='tdbg1 center'><a href='thread.php?id={$data['thread']}'>{$data['thread']}</a></td>
				<td class='tdbg2'><a href='thread.php?id={$data['thread']}'>{$data['threadname']}</a></td>
				<td class='tdbg1 right'>{$data['replies']}</td>
				<td class='tdbg1 right'>{$data['real']}</td>
				<td class='tdbg2 right b'>{$data['offset']}</td>
				<td class='tdbg1'>{$status}</td>
			</tr>";
		}
		if ($count) {
			print "<tr><td class='tdbgc center' colspan=6>$count thread". ($count != 1 ? "s" : "") ." updated.</td></tr>";
		} else {
			print "<tr><td class='tdbg1 center' colspan=6>&nbsp;<br>No problems found.<br>&nbsp;</td></tr>";
		}
		
		print "</table>
		<br>
		<table class='table'>
			<tr><td class='tdbgh center'>Forum Repair System</td></tr>
			<tr><td class='tdbg1 center'>Now running.
			</td></tr>
		</table>
		<br>
		<table class='table'>
			<tr>
				<td class='tdbgh center' rowspan='2'>id#</td>
				<td class='tdbgh center' rowspan='2'>Forum Name</td>
				<td class='tdbgh center' colspan='3'>Posts</td>
				<td class='tdbgh center' colspan='3'>Threads</td>
				<td class='tdbgh center' rowspan='2'>Status</td>
			</tr>
			<tr>
				<td class='tdbgh center'>Reports</td>
				<td class='tdbgh center'>Real</td>
				<td class='tdbgh center'>Err</td>
				<td class='tdbgh center'>Reports</td>
				<td class='tdbgh center'>Real</td>
				<td class='tdbgh center'>Err</td>
			</tr>";
			
			
		$forums = $sql->query("
			SELECT f.id, f.title forumname,
			       COUNT(p.id) preal, (COUNT(p.id) - CAST(f.numposts AS SIGNED)) poffset, f.numposts,
			       COUNT(DISTINCT(t.id)) treal, (COUNT(DISTINCT(t.id)) - CAST(f.numthreads AS SIGNED)) toffset, f.numthreads
			FROM forums f
			LEFT JOIN threads t ON f.id = t.forum
			LEFT JOIN posts   p ON t.id = p.thread
			GROUP BY f.id
			HAVING poffset != 0 OR toffset != 0
			ORDER BY f.id ASC
		");
			
		$count	= "";
		$update = $sql->prepare("UPDATE forums SET numposts = ?, numthreads = ? WHERE id = ?");
		while ($data = $sql->fetch($forums)) {
			
			$status	= _status($sql->execute($update, [$data['preal'], $data['treal'], $data['id']]));
			$count++;
			
			print "
			<tr>
				<td class='tdbg1 center'><a href='forum.php?id={$data['id']}'>{$data['id']}</a></td>
				<td class='tdbg2'><a href='forum.php?id={$data['id']}'>".htmlspecialchars($data['forumname'])."</a></td>
				<td class='tdbg1 right'>{$data['numposts']}</td>
				<td class='tdbg1 right'>{$data['preal']}</td>
				<td class='tdbg2 right b'>{$data['poffset']}</td>
				<td class='tdbg1 right'>{$data['numthreads']}</td>
				<td class='tdbg1 right'>{$data['treal']}</td>
				<td class='tdbg2 right b'>{$data['toffset']}</td>
				<td class='tdbg1'>{$status}</td>
			</tr>";
		}
		if ($count) {
			print "<tr><td class='tdbgc center' colspan=9>$count forum". ($count != 1 ? "s" : "") ." updated.</td></tr>";
		} else {
			print "<tr><td class='tdbg1 center' colspan=9>&nbsp;<br>No problems found.<br>&nbsp;</td></tr>";
		}
		
		print "</table>";
			
		break;
	case 2:
		print "
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
		";
 	
		$threads = $sql->query("
			SELECT t.id, t.title, t.lastpostdate, p.date as realdate 
			FROM threads t
			
			LEFT JOIN (SELECT MAX(date) as date, thread FROM posts GROUP BY thread) p ON p.thread = t.id
			WHERE t.lastpostdate != p.date
			ORDER BY t.id DESC
		");

		$count	= "";
		$update = $sql->prepare("UPDATE threads SET lastposter = ?, lastpostdate = ? WHERE id = ?");
		while ($data = $sql->fetch($threads)) {

			$status	= "";

			if (!$data['lastpostdate'] && $data['realdate'] === NULL) {
				$status	= "<font color=#ff8888>Broken thread</font>";
			} else {
				$userd	= $sql->fetchq("SELECT date, user FROM posts WHERE thread = '{$data['id']}' ORDER BY date DESC LIMIT 1");
				$status	= _status($sql->execute($update, [$userd['user'], $userd['date'], $data['id']])); 
				$count++;
			}
			
			print "
<tr>
	<td class='tdbg1 center'>{$data['id']}</td>
	<td class='tdbg2'><a href='thread.php?id={$data['id']}'>".htmlspecialchars($data['title'])."</a></td>
	<td class='tdbg1 center'>".($data['lastpostdate'] ? printdate($data['lastpostdate']) : "-")."</td>
	<td class='tdbg1 center'>".($data['realdate'] ? printdate($data['realdate']) : "-")."</td>
	<td class='tdbg1 center'>".timeunits2($data['lastpostdate'] - $data['realdate'])."</td>
	<td class='tdbg2'>{$status}</td>
</tr>";
		}

		if ($count) {
			print "<tr><td class='tdbgc center' colspan=6>$count thread". ($count != 1 ? "s" : "") ." updated.</td></tr>";
		} else {
			print "<tr><td class='tdbgc center' colspan=6>Nothing to repair.</td></tr>";
		}
		print "</table>";
		break;
	default:
		errorpage("Invalid selection.");
}

if ($_POST['run'])
	print '<div class="tdbg1 center" style="margin-top: 16px">Click <a href="?">here</a> to return to the previous page.</div>';

pagefooter();
	
function _opt($id, $title, $desc) {
	print '
<div class="opt">
	<input type="radio" name="run" id="lx'.$id.'" value="'.$id.'">
	<div class="lbl">
		<label for="lx'.$id.'">'.$title.'</label>
		<div class="fonts">'.$desc.'</div>
	</div>
</div>';
}
function _status($status) {
	return $status 
		? "<span style='color: #80ff80'>Updated</span>"
		: "<span style='color: #ff0000'>Error</span>";
}