<?php
	require 'lib/function.php';
	
	$_GET['t'] = filter_int($_GET['t']);
	$_GET['p'] = filter_int($_GET['p']);
	$maxtime  = ($_GET['t'] ? numrange($_GET['t'], 60, 86400) : false);
	$maxposts = ($_GET['p'] ? numrange($_GET['p'],  1,   100) : false);
	if ($maxtime === false && $maxposts === false) $maxposts = 50; // Default
	
	$_GET['raw']	= filter_bool($_GET['raw']);
	$_GET['lastid'] = filter_int($_GET['lastid']); // no notices or SQLi thank you
	
	$data = $sql->query("
		SELECT 	p.id, p.user, p.date, t.title, t.forum fid, f.title ftitle, ".($_GET['raw'] ? "u.name, u.group, u.sex, u.namecolor" : "$userfields x").", 
				pf.group{$loguser['group']} forumperm, pu.permset userperm
				".($loguser['id'] ? ", r.read AS tread, r.time as treadtime " : "")."
		FROM `posts` p
		
		LEFT JOIN `threads`          t ON p.thread = t.id
		LEFT JOIN `forums`           f ON t.forum  = f.id
		LEFT JOIN `users`            u ON p.user   = u.id
		LEFT JOIN `perm_forums`     pf ON f.id     = pf.id
		LEFT JOIN `perm_forumusers` pu ON f.id     = pu.forum AND pu.user = {$loguser['id']}
		".($loguser['id'] ? "LEFT JOIN threadsread r ON t.id = r.tid AND r.uid = {$loguser['id']} " : "")."
		
		WHERE 	p.date >= ".($maxtime ? (ctime()-$maxtime) : (ctime()-86400*7)) // time limit here
				.($_GET['lastid'] ? "AND p.id > {$_GET['lastid']} ":"")."
				AND (".has_perm('forum-admin')." OR !ISNULL(f.id))
		ORDER BY `id` DESC
		".($maxposts ? "LIMIT 0, $maxposts" : '')); // posts limit here		
	
	$_count = $sql->num_rows($data);
	$output	= "";
	
	if (!$_GET['raw']) {
		// Normal page
		pageheader("{$config['board-name']} - A revolution in posting technology&trade;");		
		
		if ($loguser['id']) {
			$forumread = $sql->getresultsbykey("SELECT forum, readdate FROM forumread WHERE user = {$loguser['id']}");
		}
		while ($in = $sql->fetch($data)) {
			
			if (!has_forum_perm('read', $in)) {
				$output	.= "
				<tr>
					<td class='tdbg2 center'>". $in['id'] ."</td>
					<td class='tdbg2 center'><i>Restricted forum</i></td>
					<td class='tdbg1 center'><i>Restricted thread</i></td>
					<td class='tdbg1'>&nbsp;</td>
					<td class='tdbg2'>&nbsp;</td>
				</tr>";			
			} else {
			
				if ($loguser['id'] && $in['date'] > max($forumread[$in['fid']], $in['treadtime'])) {
					$newpost = $statusicons['new']."&nbsp";
				} else {
					$newpost = "";
				}
				$output	.= "
					<tr>
						<td class='tdbg2 center'>". $in['id'] ."</td>
						<td class='tdbg2 center'><a href='forum.php?id=". $in['fid'] ."'>". htmlspecialchars($in['ftitle']) ."</a></td>
						<td class='tdbg1'>$newpost<a href='thread.php?pid=". $in['id'] ."&r=1#". $in['id'] ."'>". htmlspecialchars($in['title']) ."</a></td>
						<td class='tdbg1 center'>".getuserlink($in, $in['user'])."</td>
						<td class='tdbg2 center'>". timeunits(ctime() - $in['date']) ."</td>
					</tr>";
			}
		}
		
/* Doesn't work, as far as I'm aware?
		if ($_GET['fungies']) {
			$jscripts	= '<script type="text/javascript" src="/js/jquery.min.js"></script><script type="text/javascript" src="/js/latestposts.js"></script>';
		} */
?>
	Show:<span class='fonts'>
		<br>Last <a href='?t=1800'>30 minutes</a> - <a href='?t=3600'>1 hour</a> - <a href='?t=18000'>5 hours</a> - <a href='?t=86400'>1 day</a>
		<br>Most recent <a href='?p=20'>20 posts</a> - <a href='?p=50'>50 posts</a> - <a href='?p=100'>100 posts</a>
	</span>
	<table class='table' cellspacing='0' name='latest'>
		<tr><td class='tdbgc center' colspan=6><b>Latest Posts</b></td></tr>
		<tr>
			<td class='tdbgh center' width=30>&nbsp;</td>
			<td class='tdbgh center' width=280>Forum</td>
			<td class='tdbgh center' width=*>Thread</td>
			<td class='tdbgh center' width=200>User</td>
			<td class='tdbgh center' width=130>Time</td>
		</tr>
		<?=$output?>
	</table>
<?php
		//echo $jscripts;
		pagefooter();
		
	} else {
		// JSON Output
		$output = array(
				'tzoff'		=> $tzoff,
				'localtime' => ctime(),
				'posts'		=> $sql->fetchAll($data, PDO::FETCH_ASSOC)
			);
			
		// Remove unnecessary data
		$keys = array_keys($output['posts']);
		foreach ($keys as $key) {
			if (!has_forum_perm('read', $output['posts'][$key])) {
				unset($output['posts'][$key]);
			} else {
				unset($output['posts'][$key]['forumperm'], $output['posts'][$key]['userperm']); 
			}
		}
		
		header("Content-Type: application/json", true);
		header("Ajax-request: ".IS_AJAX_REQUEST, true);
		echo json_encode($output);
	}