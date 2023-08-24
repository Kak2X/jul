<?php
	require "lib/common.php";
	require "lib/uploader_function.php";

	// Uploader - Folder view
	pageheader("Uploader");
	
	$_GET['mode'] = filter_string($_GET['mode']); // User mode or global mode
	$_GET['user'] = filter_int($_GET['user']); // User ID for user mode. If 0, it shows all user folders.

	if ($_GET['user'] || $_GET['mode'] == 'u') {
		$where = ($_GET['user'] ? "c.user = {$_GET['user']}" : "c.user != 0").
				 ($isadmin ? "" : " AND (!c.minpowerread OR c.minpowerread <= {$loguser['powerlevel']} OR c.user = {$loguser['id']})");
	} else {
		$where = "c.user = 0 ".($isadmin ? "" : " AND (!c.minpowerread OR c.minpowerread <= {$loguser['powerlevel']})");
	}
	
	// Get all of the categories
	$cats = $sql->query("
		SELECT c.id, c.title, c.description, c.files, c.downloads, c.lastfiledate, (c.minpowerread > 0) private, $userfields lastfileuser
		FROM uploader_cat c
		LEFT JOIN users u ON c.lastfileuser = u.id
		WHERE {$where}
		ORDER BY c.ord ASC, c.id ASC
	");
	
	$user = $_GET['user'] ? load_user($_GET['user']) : null;
	$links = uploader_breadcrumbs_links(null, $user);	
	$barright = uploader_barright(null, $user);
	$breadcrumbs = dobreadcrumbs($links, $barright); 

	print $breadcrumbs;
?>
	<table class="table">
		<tr>
			<td class="tdbgh center b">Title</td>
			<td class="tdbgh center b" style="width: 80px">File count</td>
			<td class="tdbgh center b" style="width: 80px">Downloads</td>
			<td class="tdbgh center b" style="width: 150px">Last file</td>
		</tr>
<?php
	for ($i = 0; $x = $sql->fetch($cats); ++$i) {
		$c = ($i % 2)+1;
		print "
		<tr>
			<td class='tdbg{$c}".($x['private'] ? " i" : "")."'><a href='".actionlink("uploader-cat.php", "?cat={$x['id']}")."'>".htmlspecialchars($x['title'])."</a><span class='fonts'><br>".htmlspecialchars($x['description'])."</span></td>
			<td class='tdbg{$c} center'>{$x['files']}</td>
			<td class='tdbg{$c} center'>{$x['downloads']}</td>
			<td class='tdbg{$c} center'>
				<div class='lastpost'>
					".($x['lastfiledate'] ?
					printdate($x['lastfiledate'])."<br>by ".getuserlink($x, $x['lastfileuser']) : "")."
				</div>
			</td>
		</tr>";	
	}
?>
	</table>
<?php

	print $breadcrumbs;

	pagefooter();