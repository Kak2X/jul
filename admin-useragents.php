<?php

	require "lib/common.php";
	admincheck();
	load_layout();

	$_GET['id']     = filter_int($_GET['id']);
	$_GET['page'] 	= filter_int($_GET['page']);
	
	if ($_GET['id']) {
		$baseurl = "?id={$_GET['id']}";
		$qwhere  = "WHERE l.user = {$_GET['id']}";
		$userfor = " for ".getuserlink(null, $_GET['id']);
	} else {
		$baseurl = "?";
		$qwhere  = "";
		$userfor = "";
	}
	
	if (isset($_GET['ppp'])) {
		$ppp = numrange($_GET['ppp'], 1, 100);
		$baseurl .= "&ppp={$ppp}";
	} else {
		$ppp = 25;
	}
	
	
	
	$min = $ppp * $_GET['page'];
	$items = $sql->query("
		SELECT l.*, {$userfields} uid 
		FROM log_useragent l
		LEFT JOIN users u ON l.user = u.id
		{$qwhere}
		ORDER BY l.lastchange DESC
		LIMIT {$min}, {$ppp}
	");
	
	$total = $sql->resultq("SELECT COUNT(*)	FROM log_useragent l {$qwhere}");
	$pagelinks = "<span class='fonts'>".pagelist($baseurl, $total, $ppp)."</span>";

	$txt = "";
	foreach ($items as $x) {
		$ip = htmlspecialchars($x['ip']);
		$txt .= "
		<tr>
			<td class='tdbg1 center'>{$x['id']}</td>
			<td class='tdbg2 center'>".($x['uid'] ? getuserlink($x, $x['uid']) : "<i>Guest</i>")."</td>
			<td class='tdbg2 center'>".printdate($x['creationdate'])."</td>
			<td class='tdbg2 center'>".printdate($x['lastchange'])."</td>
			<td class='tdbg1 center'><a href=\"admin-ipsearch.php?ip={$ip}\">{$ip}</a></td>
			<td class='tdbg1 fonts'>".escape_html($x['useragent'])."</td>
		</tr>";
	}

	pageheader("User Agent History");
	print adminlinkbar();
	
	
	if (!$config['log-useragents'])
		print boardmessage("User agent logging is disabled.", "Notice");

?>
<form method='GET' class='font right'>Select user: <?= user_select('id', $_GET['id'], null, "*** All users ***") ?> <button type='submit'>Search</button></form>
<?= $pagelinks ?>
<table class='table'>
	<tr><td class='tdbgh center b' colspan='6'>User agent history<?= $userfor ?></td></tr>
	<tr>
		<td class='tdbgc center b' style='width: 50px'>#</td>
		<td class='tdbgc center b'>User</td>
		<td class='tdbgc center b' style='width: 200px'>First activity</td>
		<td class='tdbgc center b' style='width: 200px'>Last login</td>
		<td class='tdbgc center b' style='width: 200px'>Last IP</td>
		<td class='tdbgc center b'>User Agent</td>
	</tr>
	<?= $txt ?>
</table>
<?= $pagelinks ?>
<?php

	pagefooter();

