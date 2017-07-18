<?php
	
	require "lib/function.php";
	
	if (!$config['allow-custom-forums']) {
		return header("Location: index.php");
	}	
	
	pageheader();
	
	$_GET['sort'] = filter_int($_GET['sort']);
	$_GET['ord']  = filter_int($_GET['ord']);
	$_GET['user'] = filter_int($_GET['user']);
	$_GET['page'] = filter_int($_GET['page']);
	
	$idparam = '';
	$w = array('a', 'a', 'a', 'a', 'a');
	if ($_GET['ord']) {
		$ord = "ASC";
		$w[1] = 'i';
		$idparam .= "&ord={$_GET['ord']}";
	} else {
		$ord = "DESC";
		$w[0] = 'i';
	}
	switch ($_GET['sort']) {
		case 1:
			$sort = 'f.title';
			$w[3] = 'i'; // Disable the link
			break;
		case 2:
			$sort = 'f.numposts';
			$w[4] = 'i'; // Disable the link
			break;
		default:
			$sort = 'f.lastpostid';
			$w[2] = 'i';
	}
	if ($_GET['sort']) $idparam .= "&sort={$_GET['sort']}";
	
	if ($_GET['user']) {
		$user = $sql->fetchq("SELECT $userfields FROM users u WHERE u.id = {$_GET['user']}");
		if ($user) {
			$quser = "AND f.user = {$_GET['user']}"; // Query addendum
			$place = getuserlink($user)."'s c"; // Added to "where are you" tag
			$nfmsg = " owned by this user"; // Appended to the "no custom forums" message
			$idparam .= "&user={$_GET['user']}";
		} else {
			errorpage("This user doesn't exist.", 'index.php', 'the index');
		}
	} else {
		$quser = "";
		$place = "C";
		$nfmsg = "";
	}
	
	$isadmin = has_perm('forum-admin');
	
	const C_LIMIT = 15;
	
	$forumquery = $sql->query("
		SELECT f.*, $userfields uid, ".user_fields("u1")." uid, pf.group{$loguser['group']} forumperm, pu.permset userperm
		FROM forums f
		LEFT JOIN users            u ON f.lastpostuser = u.id
		LEFT JOIN users           u1 ON f.user         = u1.id
		LEFT JOIN perm_forums     pf ON f.id           = pf.id
		LEFT JOIN perm_forumusers pu ON f.id           = pu.forum AND pu.user = {$loguser['id']}
		WHERE f.custom AND (!f.hidden OR ".has_perm('display-hidden-forums').") $quser
		ORDER BY $sort $ord
		LIMIT ".($_GET['page'] * C_LIMIT).", ".C_LIMIT."
	");
	
	if ($sql->num_rows($forumquery)) {
		$forumids = $sql->fetchq("
			SELECT f.id 
			FROM forums f
			WHERE f.custom AND (!f.hidden OR ".has_perm('display-hidden-forums').")
			ORDER BY f.lastpostid DESC
			LIMIT ".($_GET['page'] * C_LIMIT).", ".C_LIMIT, PDO::FETCH_COLUMN, mysql::FETCH_ALL);

		$modquery = $sql->query("
			SELECT $userfields, f.id forum
			FROM forums f
			INNER JOIN perm_forumusers pu ON f.id    = pu.forum
			INNER JOIN users           u  ON pu.user = u.id
			WHERE f.custom AND (pu.permset & ".PERM_FORUM_MOD.") AND f.id IN (".implode(",", $forumids).")
			ORDER BY f.id, u.name
		");
		$total = $sql->resultq("SELECT COUNT(*) FROM forums f WHERE f.custom = 1 $quser");
		$pagelist = '<span class="fonts">Pages: '.dopagelist("customforums.php?$idparam", $total, C_LIMIT).'</span>';
	} else {
		$pagelist = '';
	}

	
?>
	<table class="w" style="border-spacing: none">
		<tr>
			<td class="font">
				<a href="index.php"><?=$config['board-name']?></a> - <?=$place?>ustom forums
			</td>
			<td class="font right">
				Sort by: 
				<<?=$w[2]?> href="?sort=0&ord=<?=$_GET['ord']?>">Last post</<?=$w[2]?>> - 
				<<?=$w[3]?> href="?sort=1&ord=<?=$_GET['ord']?>">Forum title</<?=$w[3]?>> - 
				<<?=$w[4]?> href="?sort=2&ord=<?=$_GET['ord']?>">Posts</<?=$w[4]?>> | 
				Order: 
				<<?=$w[0]?> href="?sort=<?=$_GET['sort']?>&ord=0">Descending</<?=$w[0]?>> - 
				<<?=$w[1]?> href="?sort=<?=$_GET['sort']?>&ord=1">Ascending</<?=$w[1]?>>
			</td>
		</tr>
	</table>
	<?=$pagelist?>
	<table class="table">
		<tr>
			<td class='tdbgh center'>&nbsp;</td>
			<td class='tdbgh center'>Forum</td>
			<td class='tdbgh center' width=15%>Owner</td>
			<td class='tdbgh center' width=80>Threads</td>
			<td class='tdbgh center' width=80>Posts</td>
			<td class='tdbgh center' width=15%>Last post</td>
		</tr>
<?php
	if (!$sql->num_rows($forumquery)) {
?>		<tr><td class="tdbgc center" colspan=6><i>There are no custom forums<?=$nfmsg?></i></td></tr><?php
	} else {
		$forums		= $sql->fetchAll($forumquery, PDO::FETCH_NAMED);
		$mods		= $sql->fetchAll($modquery);
		
		if ($loguser['id']) {
			$qadd = array();
			foreach ($forums as $forum) {
				if (!isset($postread[$forum['id']])) continue;
				$qadd[] = "(lastpostdate > '{$postread[$forum['id']]}' AND forum = '{$forum['id']}')\r\n";
			}
			
			if ($qadd)
				$qadd = "(".implode(' OR ', $qadd).")";
			else
				$qadd = "1";

			$forumnew = $sql->getresultsbykey("
				SELECT forum, COUNT(*) AS unread
				FROM threads t
				LEFT JOIN threadsread tr ON (tr.tid = t.id AND tr.uid = {$loguser['id']})
				WHERE (ISNULL(`read`) OR `read` != 1) AND $qadd
				GROUP BY forum
			");
		}
		
		foreach ($forums as $forumplace => $forum) {
			
			if (has_forum_perm('read', $forum)) {
			
				/*
					Local mod display
				*/
				$m = 0;
				$modlist = "";
				foreach ($mods as $modplace => $mod) {
					if ($mod['forum'] != $forum['id'])
						continue;

					$modlist .=($m++?', ':'').getuserlink($mod);
					unset($mods[$modplace]);
				}

				if ($modlist)
					$modlist = "<span class='fonts'>(moderated by: $modlist)</span>";

				
				
				
				if($forum['numposts']) {
					$namelink = getuserlink(array_column_by_key($forum, 0), $forum['uid'][0]);
					$forumlastpost = printdate($forum['lastpostdate']);
					$by =  "<span class='fonts'>
								<br>
								by $namelink". ($forum['lastpostid'] ? " <a href='thread.php?pid={$forum['lastpostid']}#{$forum['lastpostid']}'>{$statusicons['getlast']}</a>" : "")
						  ."</span>";
				} else {
					$forumlastpost = getblankdate();
					$by = '';
				}

				$new='&nbsp;';

				if ($forum['numposts']) {
					// If we're logged in, check the result set
					if ($loguser['id'] && isset($forumnew[$forum['id']]) && $forumnew[$forum['id']] > 0) {
						$new = $statusicons['new'] ."<br>". generatenumbergfx((int)$forumnew[$forum['id']]);
					}
					// If not, mark posts made in the last hour as new
					else if (!$loguser['id'] && $forum['lastpostdate'] > ctime() - 3600) {
						$new = $statusicons['new'];
					}
				}
				
				if ($isadmin) {
					$editlink = "<span style='float: right'><a href='admin-editforums.php?id={$forum['id']}'>Edit forum</a> - <a href='editcustomfilters.php?u={$forum['uid'][1]}&id={$forum['id']}'>Edit filters</a> - <a href='forumacl.php?id={$forum['id']}'>Edit access list</a></span>";
				} else if ($forum['uid'][1] == $loguser['id']) {
					$editlink = "<span style='float: right'><a href='editcustomforums.php?id={$forum['id']}'>Edit forum</a> - <a href='editcustomfilters.php?id={$forum['id']}'>Edit filters</a> - <a href='forumacl.php?id={$forum['id']}'>Edit access list</a></span>";
				} else {
					$editlink = "<span style='float: right'><a href='forumacl.php?id={$forum['id']}'>View access list</a></span>";
				}
				
				// Add the text to the rightmost of the last line without creating a new one.
				if ($modlist) {
					$modlist .= $editlink;
				} else {
					$forum['description'] .= $editlink;
				}
?>
				<tr>
					<td class='tdbg1 center' style='width: 4%'><?=$new?></td>
					<td class='tdbg2'>
						<a href='forum.php?id=<?=$forum['id']?>'><?=htmlspecialchars($forum['title'])?></a><br>
						<span class='fonts'>
							<?=$forum['description']?><br>
							<?=$modlist?>
						</span>
					</td>
					<td class='tdbg2 center'><?=getuserlink(array_column_by_key($forum, 1), $forum['uid'][1]);?></td>
					<td class='tdbg1 center'><?=$forum['numthreads']?></td>
					<td class='tdbg1 center'><?=$forum['numposts']?></td>
					<td class='tdbg2 center'>
						<span class='lastpost nobr'>
							<?=$forumlastpost?> <?=$by?>
						</span>
					</td>
				</tr>
<?php

				unset($forums[$forumplace]);
			}
			else {
				unset($forums[$forumplace]);
				continue;
			}	
		}
		
	}
	
?>	</table>
	<?=$pagelist?>
<?php
	
	pagefooter();
	