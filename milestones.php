<?php
	require 'lib/function.php';
	
	pageheader("{$config['board-name']} -- Milestones");
	
	$posts   = max(10000, filter_int($_GET['p']));
	$threads = max(1000,  filter_int($_GET['t']));
	$ismod   = has_perm('all-forum-access');

	$tmp1 = $tmp2 = 0;
	// u.name as uname, u.sex as usex, u.powerlevel as upowerlevel,
	$milestones = $sql->query("
		SELECT 	p.*, t.title threadname, $userfields x, 
				f.id as fid, f.title as ftitle,
				pf.group{$loguser['group']} forumperm, pu.permset userperm
		FROM posts p 
		
		LEFT JOIN users            u ON p.user       = u.id 
		LEFT JOIN threads          t ON p.thread     = t.id 
		LEFT JOIN forums           f ON t.forum      = f.id 
		LEFT JOIN perm_forums     pf ON f.id         = pf.id
		LEFT JOIN perm_forumusers pu ON f.id         = pu.forum AND pu.user = {$loguser['id']}
		
		WHERE (p.id % $posts = 0 OR p.id = 1) AND ($ismod OR !ISNULL(f.id))
		ORDER BY p.id ASC");
		
	$poststable = 
		"<tr><td class='tdbgh center' colspan=6 style='font-weight:bold'>Post Milestones</td></tr>
		<tr>
			<td class='tdbgh center' width=30>&nbsp;</td>
			<td class='tdbgh center' width=280>Forum</td>
			<td class='tdbgh center' width=*>In Thread</td>
			<td class='tdbgh center' width=200>User</td>
			<td class='tdbgh center' width=250>Time</td>
		</tr>";
		
	$last = 0;
	while ($ms = $sql->fetch($milestones)) {
		$tmp2 = $ms['id'];
		// Gaps in post IDs?
		while (($tmp2 -= $posts) > $tmp1) {
				$poststable .= "<tr>
					<td class='tdbg1 center'>$tmp2</td>
					<td class='tdbg2 center'><i>(unknown)</i></td>
					<td class='tdbg2 center'><i>(post deleted)</i></td>
					<td class='tdbg1 center'>????</td>
					<td class='tdbg1 center'>????<br><span class='fonts'>(????)</span></td>
				</td>";
		}
		$tmp1 = $ms['id'];

		if (!has_forum_perm('read', ['forumperm' => $ms['forumperm'], 'userperm' => $ms['userperm']])) {
			$forumlink = "<i>(restricted forum)</i>";
			$threadlink = "<i>(restricted)</i>";
			$userlink = "????";
		} else {
			$forumlink = "<a href='forum.php?id={$ms['fid']}'>".htmlspecialchars($ms['ftitle'])."</a>";
			$threadlink = "<a href='thread.php?pid={$ms['id']}#{$ms['id']}'>".htmlspecialchars($ms['threadname'])."</a>";
			$userlink = getuserlink($ms, $ms['user']); //"<a href='profile.php?id={$ms[user]}'><span style='color: #". getnamecolor($ms['usex'], $ms['upowerlevel']) ."'>{$ms['uname']}</span></a>";
		}

		if ($last) {
		  $timetaken = timeunits($ms['date']-$last);
		} else {
		  $timetaken = "(first post)";
			$last = $ms['date'];
			$timestamp = printdate($ms['date'])."<div class='fonts'>$timetaken</div>";

			$poststable .= "<tr>
				<td class='tdbg1 center'>{$ms['id']}</td>
				<td class='tdbg2 center'>{$forumlink}</td>
				<td class='tdbg2 center'>{$threadlink}</td>
				<td class='tdbg1 center'>{$userlink}</td>
				<td class='tdbg1 center'>{$timestamp}</td>
			</td>";
		}
	}

	$tmp1 = $tmp2 = 0;
// u1.name AS name1,u1.sex AS sex1,u1.powerlevel AS power1,u2.name AS name2,u2.sex AS sex2,u2.powerlevel AS power2,
// AND u2.id=t.lastposter 
	$milestones = $sql->query("
		SELECT 	t.*, f.title as forumtitle,
				u1.name, u1.sex, u1.`group`, u1.aka, u1.birthday, u1.namecolor,
				u2.name, u2.sex, u2.`group`, u2.aka, u2.birthday, u2.namecolor,
				pf.group{$loguser['group']} forumperm, pu.permset userperm
		FROM threads t
		
		LEFT JOIN forums           f ON f.id         = t.forum
		LEFT JOIN users           u1 ON t.user       = u1.id
		LEFT JOIN users           u2 ON t.lastposter = u2.id
		LEFT JOIN perm_forums     pf ON f.id         = pf.id
		LEFT JOIN perm_forumusers pu ON f.id         = pu.forum AND pu.user = {$loguser['id']}
		WHERE (t.id % $threads = 0 OR t.id = 1) AND ($ismod OR !ISNULL(f.id))
		ORDER BY t.id ASC");
	$threadstable = 
		"<tr><td class='tdbgh center' colspan=7 style=\"font-weight:bold;\">Thread Milestones</td></tr>
		<tr>
			<td class='tdbgh center' width=30></td>
			<td class='tdbgh center' colspan=2> Thread</td>
			<td class='tdbgh center' width=20%>Started by</td>
			<td class='tdbgh center' width=60> Replies</td>
			<td class='tdbgh center' width=60> Views</td>
			<td class='tdbgh center' width=180> Last post</td>
		</tr>";
	while ($ms = $sql->fetch($milestones)) {
		$tmp2 = $ms['id'];
		while (($tmp2 -= $threads) > $tmp1) {
				$threadstable .= "<tr>
					<td class='tdbg1 center'>$tmp2</td>
					<td class='tdbg1 center' width=40px>&nbsp;</td>
					<td class='tdbg2'><i>(thread deleted)</i></td>
					<td class='tdbg2 center'>????</td>
					<td class='tdbg1 center'>????</td>
					<td class='tdbg1 center'>????</td>
					<td class='tdbg1 center'>????<span class='fonts'><br>by ????</td>
				</td>";
		}
		$tmp1 = $ms['id'];

		if (!has_forum_perm('read', ['forumperm' => $ms['forumperm'], 'userperm' => $ms['userperm']])) {
			$threadlink = "<i>(restricted)</i>";
			$userlink = "????";
			$tpic = "&nbsp;";
			$replies = "????";
			$views = "????";
			$lastpost = "????<span class='fonts'><br>by ????";
		}
		else {
			$threadlink = "<a href='thread.php?id={$ms['id']}'>{$ms['title']}</a>";
			$threadlink .= '<br><span class="fonts" style="position: relative; top: -1px;">&nbsp;&nbsp;&nbsp;'
						. "In <a href='forum.php?id={$ms['forum']}'>{$ms['forumtitle']}</a>"
						. '</span>';
			
			$threadauthor 	= getuserlink(array_column_by_key($ms, 0), $ms['user']);
			$lastposter 	= getuserlink(array_column_by_key($ms, 1), $ms['lastposter']);
			
			$lastpost = printdate($ms['lastpostdate'])."
			<span class='fonts'><br>by $lastposter
			<a href='thread.php?id={$ms['id']}&end=1'>{$statusicons['getlast']}</a>";

			$replies 	= $ms['replies'];
			$views 		= $ms['views'];
			$tpic 		= ($ms['icon']) ? "<img src=\"".htmlspecialchars($ms['icon'])."\">" : "&nbsp;";
		}
		$threadstable .= "<tr>
			<td class='tdbg1 center'>{$ms['id']}</td>
			<td class='tdbg1 center' width=40px style='max-width:40px;max-height:30px;overflow:hidden;'>$tpic</td>
			<td class='tdbg2'>$threadlink</td>
			<td class='tdbg2 center'>$threadauthor</td>
			<td class='tdbg1 center'>$replies</td>
			<td class='tdbg1 center'>$views</td>
			<td class='tdbg1 center'>$lastpost</td>
		</td>";
	}



	print "
	<table class='table'>
		$poststable
	</table>
	<br>
	<table class='table'>
		$threadstable
	</table>";
	
	pagefooter();

?>