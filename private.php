<?php

	require 'lib/function.php';
	
	$meta['noindex'] = true;
	
	$id 	= filter_int($_GET['id']);
	$page	= filter_int($_GET['page']);
	$view	= filter_string($_GET['view']);
	$folder = filter_int($_GET['dir']);
	
	$windowtitle = "{$config['board-name']} -- Private Messages";

	if (!$loguser['id'])
		errorpage("You need to be logged in to read your private messages.", 'login.php', 'log in (then try again)');

	// Viewing someone else?
	if ($isadmin && $id) {
		$u 			= $id;
		$idparam 	= "id=$id&";
	} else {
		$u 			= $loguser['id'];
		$idparam 	= '';
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
	$ppp	= max(min($ppp, 500), 1);
	
	// Page number links
	if (!$page) $page = 1;

	$pmin 		= ($page - 1) * $ppp;
	$msgtotal 	= $sql->resultq("SELECT COUNT(*) FROM pmsgs WHERE user$to = $u");
	$pagelinks 	= 'Pages:';
	
	for($i = 0, $p = 1; $i < $msgtotal; $i += $ppp, ++$p) {
		if($p == $page)
			$pagelinks.=" $p";
		else
			$pagelinks.=" <a href='private.php?{$idparam}{$viewparam}page={$p}'>{$p}</a>";
	}
	

	// 1252378129
	//		.($loguser['id'] == 175 ? "AND p.id > 8387 " : "")
	//	."ORDER BY " .($loguser['id'] == 175 ? "user$from DESC, " : "msgread ASC, ")
	$pmsgs   = $sql->query("
		SELECT p.id pid, p.date, p.title, p.msgread, $userfields
		FROM pmsgs p
		LEFT JOIN users u ON user{$from} = u.id
		WHERE p.user{$to} = $u
		ORDER BY p.msgread ASC, p.id DESC
		LIMIT $pmin, $ppp
	");

	$from[0] = strtoupper($from[0]);

	if(!$view)
		$viewlink = "<a href='private.php?{$idparam}view=sent'>View sent messages</a>";
	else
		$viewlink = "<a href='private.php?{$idparam}'>View received messages</a>";

	pageheader($windowtitle);
	
	
	if ($u != $loguser['id'])
		$users_p = $sql->resultq("SELECT `name` FROM `users` WHERE `id` = $u")."s p";
	else
		$users_p = "P";
	
	$xbox = (!$view) ? 'Inbox' : 'Outbox';
	
	?>
	<table class='font' style='width: 100%'>
		<tr>
			<td>
				<a href='index.php'><?=$config['board-name']?></a> - <?=$users_p?>rivate messages - <?=$xbox?>: <?=$msgtotal?>
			</td>
			<td class='right fonts'>
				<?=$viewlink?> | <a href="sendprivate.php">Send new message</a>
			</td>
		</tr>
	</table>
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
?>
