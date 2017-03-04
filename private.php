<?php

	// No-JS Compatibility
	if (isset($_POST['setdir'])) {
		if (!isset($_POST['idparam'])) 		$_POST['idparam'] 		= "";
		if (!isset($_POST['folderjump'])) 	$_POST['folderjump'] 	= -2;
		header("Location: private.php?{$_POST['idparam']}&dir={$_POST['folderjump']}");
		die;
	}
	
	require 'lib/function.php';
	
	//const SELECT_PAD = 30;
	$meta['noindex'] = true;
	
	$id 	= filter_int($_GET['id']);
	$page	= filter_int($_GET['page']);
	$view	= filter_string($_GET['view']);
	$folder = (isset($_GET['dir'])) ? max(-2, filter_int($_GET['dir'])) : -2;
	$sel_folder[$folder] = "selected";
	
	$windowtitle = "{$config['board-name']} -- Private Messages";

	if (!$loguser['id'])
		errorpage("You need to be logged in to read your private messages.", 'login.php', 'log in (then try again)');

	// Viewing someone else?
	if (has_perm('view-others-pms') && $id) {
		$u 			= $id;
		$idparam 	= "id=$id&";
	} else {
		$u 			= $loguser['id'];
		$idparam 	= '';
	}
	
	// Special case folders
	switch ($folder) {
		case -2:	// All inbox
			$infolder = "";
			$view     = "";
			$xbox	  = "Inbox";
			break;
		case -1: // All outbox
			$infolder = "";
			$view     = "sent";
			$xbox     = "Outbox";
			break;
		default: // Own category
			$view     = "";
			$infolder = " AND p.folderto = {$folder}";
			
			if ($folder) {
				$folderinfo = $sql->fetchq("SELECT user, title FROM pmsg_folders WHERE id = {$folder}");
				// Make sure this is one of our folders
				if (!is_array($folderinfo) || $folderinfo['user'] != $u) {
					if (!has_perm('admin-actions')) {
						errorpage("Cannot access the folder. It either doesn't exist or it isn't for you.",'private.php','your private message box',0);
					} else {
						if (!is_array($folderinfo)) {
							errorpage("This folder doesn't exist.");
						} else {
							header("Location: private.php?u={$folderinfo['user']}&dir={$folder}");
							die;
						}
					}
				}
				$xbox     = $folderinfo['title'];
			} else {
				$xbox = "Uncategorized PMs";
			}
	}

	// Viewing sent messages?
	if ($view == 'sent') {
		$to   		= 'from';
		$from 		= 'to';
		$viewparam 	= 'view=sent&';
	} else {
		$to   		= 'to';
		$from 		= 'from';
		$viewparam 	= '';
	}
	
	$ppp	= isset($_GET['ppp']) ? ((int) $_GET['ppp']) : ($loguser['id'] ? $loguser['postsperpage'] : $config['default-ppp']);
	$ppp	= numrange($ppp, 1, 500);
	
	// Page number links
	if (!$page) $page = 1;

	$pmin 		= ($page - 1) * $ppp;
	$msgtotal 	= $sql->resultq("SELECT COUNT(*) FROM pmsgs p WHERE p.user$to = $u{$infolder}");
	$pagelinks 	= 'Pages:';
	
	for($i = 0, $p = 1; $i < $msgtotal; $i += $ppp, ++$p) {
		if($p == $page)
			$pagelinks.=" $p";
		else
			$pagelinks.=" <a href='private.php?{$idparam}{$viewparam}page={$p}'>{$p}</a>";
	}
	
	
	/*
		PM List
	*/
	// 1252378129
	//		.($loguser['id'] == 175 ? "AND p.id > 8387 " : "")
	//	."ORDER BY " .($loguser['id'] == 175 ? "user$from DESC, " : "msgread ASC, ")
	$pmsgs   = $sql->query("
		SELECT p.id pid, p.date, p.title, p.msgread, $userfields
		FROM pmsgs p
		LEFT JOIN users u ON user{$from} = u.id
		WHERE p.user{$to} = $u {$infolder}
		ORDER BY p.msgread ASC, p.id DESC
		LIMIT $pmin, $ppp
	");
	
	$from[0] = strtoupper($from[0]);

	/*
		Folder select box
	*/
	$folders = $sql->fetchq("
		SELECT f.id, f.title, COUNT(p.id) pmnum, SUM(p.msgread) pmread
		FROM pmsg_folders f 
		LEFT JOIN pmsgs p ON f.id = p.folderto AND  p.userto = {$u}
		WHERE f.user = $u
		GROUP BY f.id
		ORDER BY f.ord, f.id ASC
	", PDO::FETCH_UNIQUE, false, true);
	
	// We already have a total of sent messages if we're viewing them
	if ($view != 'sent') {
		$totalsent = $sql->resultq("SELECT COUNT(*) FROM pmsgs WHERE userfrom = $u");
	} else {
		$totalsent = $msgtotal;
	}
	
	//$folders[NULL]['title'] = "Uncategorized PMs";
	
	$totalreceived = 0;
	$allnew = false;
	$folderselect = "";
	foreach ($folders as $fid => $x) {
		if ($x['pmnum'] - $x['pmread'] > 0) {
			$x['title'] = "[NEW] ".$x['title'];
			$allnew = true; // Mark to display [NEW] on all inbox option
		}
		//$fid = (int) $fid;
		$folderselect .= "<option value='$fid' ".filter_string($sel_folder[$fid]).">".htmlspecialchars($x['title'])." ({$x['pmnum']} PMs)</option>\n\r";
		$totalreceived += $x['pmnum'];
	}
	$folderselect = 
		"<select name='folderjump' onChange='parent.location=\"private.php?{$idparam}&dir=\"+this.options[this.selectedIndex].value'>
			<optgroup label='All PMs'>
				<option value='-2' ".filter_string($sel_folder[-2]).">".($allnew ? "[NEW] " : "")."All inbox ($totalreceived PMs)</option>
				<option value='-1' ".filter_string($sel_folder[-1]).">All outbox ($totalsent PMs)</option>
			</optgroup>
			<optgroup label='Custom folders'>
				{$folderselect}
			</optgroup>
		</select>
		<noscript><input type='submit' name='setdir' value='Go'><input type='hidden' name='idparam' value='{$idparam}'></noscript>";
	/*
	if(!$view)
		$viewlink = "<a href='private.php?{$idparam}dir=-1'>View sent messages</a>";
	else
		$viewlink = "<a href='private.php?{$idparam}'>View received messages</a>";
*/
	pageheader($windowtitle);
	
	
	if ($u != $loguser['id'])
		$users_p = $sql->resultq("SELECT `name` FROM `users` WHERE `id` = $u")."'s p";
	else
		$users_p = "P";
	
	?>
	<form method='POST' action='private.php'>
	<table class='font' style='width: 100%'>
		<tr>
			<td>
				<a href='index.php'><?=$config['board-name']?></a> - <?=$users_p?>rivate messages - <?=$xbox?>: <?=$msgtotal?>
			</td>
			<td class='right fonts'>
				<?=$folderselect?> |  <a href='privatefolders.php?<?=$idparam?>'>Edit folders</a> | <a href='private.php?<?=$idparam?>&dir=0'>View uncategorized messages</a> | <a href="sendprivate.php">Send new message</a>
			</td>
		</tr>
	</table>
	</form>
	<table class='table'>
		<tr>
			<td class='tdbgh center' width=50>&nbsp;</td>
			<td class='tdbgh center'>Subject</td>
			<td class='tdbgh center' width=15%><?=$from?></td>
			<td class='tdbgh center' width=180>Sent on</td>
		</tr>
<?php
		while($pmsg = $sql->fetch($pmsgs)) {
			$new       = ($pmsg['msgread'] ? '&nbsp;' : $statusicons['new']);
?>
		<tr style='height:20px'>
			<td class='tdbg1 center'><?=$new?></td>
			<td class='tdbg2'><a href='showprivate.php?id=<?=$pmsg['pid']?>'><?=htmlspecialchars($pmsg['title'])?></a></td>
			<td class='tdbg2 center'><?=getuserlink($pmsg)?></td>
			<td class='tdbg2 center'><?=printdate($pmsg['date'])?></td>
		</tr>
<?php
		}
		?>
	</table>
	<span class="fonts"><?=$pagelinks?></span>
	<?php
	
	pagefooter();