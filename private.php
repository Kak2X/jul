<?php
	
	// No-JS Compatibility
	if (isset($_POST['setdir'])) {
		if (!isset($_POST['idparam'])) 		$_POST['idparam'] 		= "";
		if (!isset($_POST['folderjump'])) 	$_POST['folderjump'] 	= PMFOLDER_ALL;
		header("Location: private.php?{$_POST['idparam']}&dir={$_POST['folderjump']}");
		die;
	}
	
	
	require 'lib/function.php';
	
	$meta['noindex'] = true;
	if (!$loguser['id']) {
		errorpage("You need to be logged in to read your private messages.", 'login.php', 'log in (then try again)');
	}
	$_GET['id']      = filter_int($_GET['id']);
	$_GET['dir']     = isset($_GET['dir']) ? filter_int($_GET['dir']) : PMFOLDER_ALL;
	$_GET['action']  = filter_string($_GET['action']);
	
	// Viewing someone else?
	if ($isadmin && $_GET['id']) {
		$valid      = $sql->resultq("SELECT 1 FROM users WHERE id = {$_GET['id']}");
		if (!$valid) {
			errorpage("This user doesn't exist!");
		}
		
		$u 			= $_GET['id'];
		$idparam 	= "id={$_GET['id']}";
		$navlink    = "&dir={$_GET['dir']}&user={$_GET['id']}";
	} else {
		$u 			= $loguser['id'];
		$idparam 	= '';
		$navlink    = "&dir={$_GET['dir']}";
	}
	
	if ($u == $loguser['id'] && $_GET['action']) {
		check_token($_GET['auth'], TOKEN_MGET);
		
		switch ($_GET['action']) {
			case 'markfolderread':
				// Don't do this for "groups" or nonexisting folders
				$valid = $sql->resultq("SELECT COUNT(*) FROM pm_folders WHERE folder = {$_GET['dir']} AND user = {$loguser['id']}");
				if (!default_pm_folder($_GET['dir'], DEFAULTPM_DEFAULT) && !$valid) {
					errorpage("This folder isn't valid.");
				}
				$sql->query("
					DELETE FROM pm_threadsread 
					WHERE uid = {$loguser['id']} AND tid IN (
						SELECT `thread` 
						FROM `pm_access` 
						WHERE `user` = '{$loguser['id']}' AND `folder` = '{$_GET['dir']}'
					)");
				$sql->query("REPLACE INTO pm_foldersread (user, folder, readdate) VALUES ({$loguser['id']}, {$_GET['dir']}, ".ctime().')');
				break;
			case 'markallfoldersread':
				$sql->query("DELETE FROM pm_threadsread WHERE uid = {$loguser['id']}");
				$sql->query("
					REPLACE INTO pm_foldersread (user, folder, readdate) 
						SELECT {$loguser['id']}, folder, ".ctime()."
						FROM pm_access 
						WHERE user = {$loguser['id']}
					");
				break;
		}
		return header("Location: private.php?dir={$_GET['dir']}");
	}
	
	$folderread = $sql->getresultsbykey("SELECT folder, readdate FROM pm_foldersread WHERE user = {$loguser['id']}");
	pageheader("Private Messages");
	
	
	$ppp = (isset($_GET['ppp']) ? ((int) $_GET['ppp']) : (($loguser['id']) ? $loguser['postsperpage'] : $config['default-ppp']));
	$ppp = max(min($ppp, 500), 1);

	$tpp = (isset($_GET['tpp']) ? ((int) $_GET['tpp']) : (($loguser['id']) ? $loguser['threadsperpage'] : $config['default-tpp']));
	$tpp = max(min($tpp, 500), 1);

	$page = filter_int($_GET['page']);
    $min  = $page * $tpp;
	
	
	/*
		Get the thread list
	*/
	switch ($_GET['dir']) {
		case PMFOLDER_ALL: // Everything
			$qwhere = "";
			$nofolder = true;
			break;
		case PMFOLDER_TO: // All pmthreads in which you've been added
			$qwhere = " AND t.user != {$u}";
			$nofolder = true;
			break;
		case PMFOLDER_BY: // All pmthreads from you
			$qwhere = " AND t.user = {$u}";
			break;
		default: // Folders
			if (!default_pm_folder($_GET['dir'], DEFAULTPM_DEFAULT)) {
				$valid = $sql->resultq("SELECT 1 FROM pm_folders WHERE user = {$u} AND folder = {$_GET['dir']}");
				if (!$valid) {
					errorpage("Cannot access the folder. It either doesn't exist or it isn't for you.",'private.php','your private message box',0);
				}
			}
			$qwhere = " AND a.folder = {$_GET['dir']}";
	}
	// Get the userfields for partecipants in the listed threads
	$partecipants = $sql->fetchq("
		SELECT a.thread, $userfields
		FROM pm_access a
		LEFT JOIN users u ON u.id = a.user
		INNER JOIN (
			SELECT t.id 
			FROM pm_access a 
			LEFT JOIN pm_threads      t ON a.thread     = t.id
			WHERE a.user = {$u}{$qwhere}
			ORDER BY t.lastpostdate DESC
			LIMIT $min,$tpp
		) r ON a.thread = r.id
	", PDO::FETCH_GROUP, mysql::FETCH_ALL);
	
	// Get threads
	$threads = $sql->query("
		SELECT t.*, a.folder,
		       ".set_userfields('u1')." uid, 
		       ".set_userfields('u2')." uid, 
		       r.read tread, r.time treadtime 
		
		FROM pm_access a 
		LEFT JOIN pm_threads      t ON a.thread     = t.id
		LEFT JOIN users          u1 ON t.user       = u1.id
		LEFT JOIN users          u2 ON t.lastposter = u2.id
		LEFT JOIN pm_threadsread  r ON t.id = r.tid AND r.uid = {$u}
		
		WHERE a.user = {$u}{$qwhere}
		ORDER BY t.lastpostdate DESC
				
		LIMIT $min,$tpp			
	");
	$pmcount   = $sql->num_rows($threads);
	
	/*
		Forum page list at the top & bottom
	*/
	$pagelinks = "";
	if ($pmcount > $tpp) {
		$args = "";
		if (isset($_GET['ppp'])) $args .= "&ppp={$ppp}";
		if (isset($_GET['tpp'])) $args .= "&tpp={$tpp}";
		
		$pagelinks = 
			"<table style='width: 100%'>
				<tr>
					<td class='fonts'>
						".pagelist("?{$idparam}{$args}", $pmcount, $tpp, true)."
					</td>
				</tr>
			</table>";
    }
	
	
	// For the thread pagelinks
	$_GET['page'] = 0; // horrible hack
	$maxfromstart = (($loguser['pagestyle']) ?  9 :  4);
	$maxfromend   = (($loguser['pagestyle']) ? 20 : 10);	
    $threadlist = "";
	if (!$pmcount) {
		$threadlist .= 
			"<tr>
				<td class='tdbg1 center' style='font-style:italic' colspan=7>
					There are no conversations in this folder.
				</td>
			</tr>";
	} else for ($i = 1; $thread = $sql->fetch($threads, PDO::FETCH_NAMED); ++$i) {

		// Thread status
		$threadstatus = "";
		if ($thread['lastpostdate'] > filter_int($folderread[$thread['folder']]) && !$thread['tread']) {
			$threadstatus .= "new";
			$newpost = ($thread['treadtime'] ? $thread['treadtime'] : filter_int($folderread[$thread['folder']]));
		} else {
			$newpost      = NULL;
		}
		if ($thread['closed']) $threadstatus .= "off";
		
		// Thread title column
		$threadtitle = "<a href='showprivate.php?id={$thread['id']}{$navlink}'>{$thread['title']}</a>";
		$posticon    = $thread['icon'] ? "<img src=\"".htmlspecialchars($thread['icon'])."\">" : "&nbsp;";
		$belowtitle  = array(); // An extra line below the title in certain circumstances
		// Extra pages
		$threadlinks = pagelist("showprivate.php?id={$thread['id']}", $thread['replies'] + 1, $ppp, $maxfromstart, $maxfromend);
		if($thread['replies'] >= $ppp) {
			if ($loguser['pagestyle']) {
				$belowtitle[] = $threadlinks;
			} else {
				$threadtitle .= " <span class='pagelinks fonts'>({$threadlinks})</span>";
			}
		}
		if ($threaddesc = trim($thread['description'])) {
			$threadtitle .= "<br><span class='fonts'>".htmlspecialchars($threaddesc)."</span>";
		}
		if (!empty($belowtitle)) {
			$threadtitle .= '<br><span class="fonts" style="position: relative; top: -1px;">&nbsp;&nbsp;&nbsp;' . implode(' - ', $belowtitle) . '</span>';
		}
		
		
		$users   = "";
		foreach ($partecipants[$thread['id']] as $user) {
			$users .= ($users ? ", " : "").getuserlink($user);
		}
		
		$threadauthor 	= getuserlink(array_column_by_key($thread, 0), $thread['user']);
		$lastposter 	= getuserlink(array_column_by_key($thread, 1), $thread['lastposter']);
		

		$threadlist .= 
			"<tr>
				<td class='tdbg1 center'>".($threadstatus ? $statusicons[$threadstatus] : "&nbsp;")."</td>
				<td class='tdbg2 center' style='width: 40px'>
					<div style='max-width:60px;max-height:30px;overflow:hidden;'>
						$posticon
					</div>
				</td>
				<td class='tdbg2'>
					". ($newpost ? "<a href='showprivate.php?id={$thread['id']}{$navlink}&lpt=$newpost'>{$statusicons['getnew']}</a> " : "") ."
					{$threadtitle}
				</td>
				<td class='tdbg2 center'>
					{$threadauthor}
					<div class='fonts'>on ".printdate($thread['firstpostdate'])."</div>
				</td>
				<td class='tdbg2 center fonts'>{$users}</td>
				<td class='tdbg1 center'>{$thread['replies']}</td>
				<td class='tdbg2 center'>
					<div class='lastpost'>
						".printdate($thread['lastpostdate'])."<br>
						by {$lastposter}
						<a href='showprivate.php?id={$thread['id']}{$navlink}&end=1'>{$statusicons['getlast']}</a>
					</div>
				</td>
			</tr>";
	}
	
	
	/*
		Folder selection & other controls
	*/
	if ($u != $loguser['id']) {
		$users_p = htmlspecialchars($sql->resultq("SELECT `name` FROM `users` WHERE `id` = $u"))."'s p";
	} else {
		$users_p = "P";
	}
	
	$infotable =
		"<table style='width: 100%'>
			<tr>
				<td class='font'>
					<a href='index.php'>{$config['board-name']}</a> - {$users_p}rivate messages - 
					<form method='POST' action='?' style='display: inline'>
						".pm_folder_select('folderjump', $u, $_GET['dir'], PMSELECT_ALL | PMSELECT_JS | PMSELECT_SHOWCNT)."
					</form>
				</td>
				<td class='fonts right'>
					<a href='sendprivate.php?dir={$_GET['dir']}'>New conversation</a> - 
					<a href='privatefolders.php'>Edit folders</a>
				</td>
			</tr>
		</table>";
	
	print "
	{$infotable}
	{$pagelinks}
	<table class='table'>
		<tr>
			<td class='tdbgh center' style='width: 30px'></td>
			<td class='tdbgh center' colspan=2>Title</td>
			<td class='tdbgh center' style='width: 170px'>Started by</td>
			<td class='tdbgh center' style='width: 14%'>Partecipants</td>
			<td class='tdbgh center' style='width: 60px'>Replies</td>
			<td class='tdbgh center' style='width: 150px'>Last reply</td>
		</tr>
		{$threadlist}
	</table>
	{$pagelinks}
	";
	
	pagefooter();
	