<?php
	require 'lib/function.php';
	
	$_GET['t'] = filter_int($_GET['t']);
	$_GET['p'] = filter_int($_GET['p']);
	$maxtime  = ($_GET['t'] ? max(min($_GET['t'], 86400), 60) : false);
	$maxposts = ($_GET['p'] ? max(min($_GET['p'], 100),   1)  : false);
	if ($maxtime === false && $maxposts === false) $maxposts = 50; // Default
	
	$_GET['raw']	= filter_bool($_GET['raw']);
	$_GET['lastid'] = filter_int($_GET['lastid']);
	
	$data = $sql->query("
		SELECT 	p.id, p.user, p.date, t.title, t.forum fid, f.title ftitle, $userfields x
				".($loguser['id'] ? ", r.read AS tread, r.time as treadtime " : "")."
		FROM `posts` p
		
		LEFT JOIN `threads` t ON p.thread = t.id
		LEFT JOIN `forums`  f ON t.forum  = f.id
		LEFT JOIN `users`   u ON p.user   = u.id
		".($loguser['id'] ? "LEFT JOIN threadsread r ON t.id = r.tid AND r.uid = {$loguser['id']} " : "")."
		
		WHERE 	f.minpower <= {$loguser['powerlevel']}
				AND p.date >= ".($maxtime ? (ctime()-$maxtime) : (ctime()-86400*7)) // time limit here
				.($_GET['lastid'] ? "AND p.id > {$_GET['lastid']} ":"")."
				AND ($ismod OR !ISNULL(f.id))
		ORDER BY `id` DESC
		".($maxposts ? "LIMIT 0, $maxposts" : '')); // posts limit here		
	
	$output	= "";
	
	if (!$_GET['raw']) {
		// Normal page
		pageheader("{$config['board-name']} - A revolution in posting technology&trade;");		
		
		if ($loguser['id']) {
			$forumread = $sql->getresultsbykey("SELECT forum, readdate FROM forumread WHERE user = {$loguser['id']}");
		}
		while ($in = $sql->fetch($data)) {
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
		require "lib/colors.php";
		
		$output = array(
				'tzoff'		=> $tzoff,
				'localtime' => ctime(),
				'posts'		=> array(), //$sql->fetchAll($data, PDO::FETCH_ASSOC)
			);
		
		while ($in = $sql->fetch($data)) {
			
			$in['namecolor'] = getnamecolor($in['sex'], $in['powerlevel'], $in['namecolor']);
			$output['posts'][] = $in;
		}
		
		//header("Content-type: text/plain", true);
		header("Content-Type: application/json", true);
		header("Ajax-request: ".IS_AJAX_REQUEST, true);
		echo json_encode($output);
	}