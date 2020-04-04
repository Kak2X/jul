<?php

	require "lib/function.php";
	require "lib/uploader_function.php";
	
	pageheader("Personal folders");
	
	$_GET['page'] = filter_int($_GET['page']);
	$_GET['sort'] = filter_string($_GET['sort']);
	$_GET['ord']  = filter_int($_GET['ord']);
	
	
	$ppp = get_ipp($_GET['ppp'], 50);
    $min = $_GET['page'] * $ppp;
	
	
	switch ($_GET['sort']) {
		case 't': $sort = "COUNT(*)"; break;
		case 'i': $sort = "u.id"; break;
		default:  $sort = "u.name";
	}
	$ord = $_GET['ord'] ? "DESC" : "ASC";
	
	// List all users with custom categories with matching powerlevels you can read (and the category owner can always read their own)
	$conditions = "c.user != 0 AND (!c.minpowerread OR c.minpowerread <= {$loguser['powerlevel']}".($loguser['id'] ? " OR c.user = {$loguser['id']}" : "").")";
	$users = $sql->query("
		SELECT $userfields, COUNT(*) total
		FROM users u
		INNER JOIN uploader_cat c ON u.id = c.user
		WHERE $conditions
		GROUP BY u.id
		ORDER BY {$sort} {$ord}
		LIMIT $min,$ppp
	");
	// this could result in "phantom pages" but who cares and it shouldn't happen anyway
	$total = (int) $sql->resultq("SELECT COUNT(DISTINCT c.user) FROM uploader_cat c WHERE $conditions GROUP BY c.user");
	
	$pagelinks = pagelist(actionlink("uploader-catbyuser.php?"), $total, $ppp);
	
	
	// Breadcrumbs
	$links = uploader_breadcrumbs_links(NULL, NULL, UBL_USERCAT);
	$breadcrumbs = dobreadcrumbs($links, "<a href='".actionlink("uploader.php?mode=u")."'>Show all personal folders</a>"); 
?>
	<?= $breadcrumbs ?>
	<table class="table fonts">
		<tr>
			<td class="tdbgh center" colspan="2"><?= $total ?> user<?= $total == 1 ? "" : "s" ?> with personal folders found</td>
		</tr>
		<tr>
			<td class="tdbg1 center">Sort by</td>
			<td class="tdbg2 center">
				<a href="<?=actionlink(null,"?sort=n&ord={$_GET['ord']}")?>">User name</a> | 
				<a href="<?=actionlink(null,"?sort=i&ord={$_GET['ord']}")?>">User ID</a> | 
				<a href="<?=actionlink(null,"?sort=t&ord={$_GET['ord']}")?>">Folder count</a>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center">Order</td>
			<td class="tdbg2 center">
				<a href="<?=actionlink(null,"?sort={$_GET['sort']}&ord=0")?>">Ascending</a> | 
				<a href="<?=actionlink(null,"?sort={$_GET['sort']}&ord=1")?>">Descending</a>
			</td>
		</tr>
	</table>
	<br>
	
	<?= $pagelinks ?>
	<table class="table">
		<tr>
			<td class="tdbgh center b" style="width: 50px"></td>
			<td class="tdbgh center b">User</td>
			<td class="tdbgh center b" style="width: 70px">Total</td>
		</tr>
<?php
	for ($i = 0; $x = $sql->fetch($users); $i = 1 - $i) {
		$c = $i+1;
		print "
			<tr>
				<td class='tdbg{$c} center'><a href='".actionlink("uploader.php?mode=u&user={$x['id']}")."'>View</td>
				<td class='tdbg{$c}'>".getuserlink($x)."</td>
				<td class='tdbg{$c} center'>{$x['total']}</td>
			</tr>";
	} 
?>
	</table>
	<?= $pagelinks . $breadcrumbs ?>
<?php
	pagefooter();