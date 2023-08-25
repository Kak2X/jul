<?php
	require "lib/common.php";
	require "lib/uploader_function.php";
	
	
	$_GET['cat']   = filter_int($_GET['cat']);
	
	if (!$_GET['cat']) 
		errorpage("No folder specified.");
	
	load_uploader_category($_GET['cat'], "c.id, c.title, c.user, c.minpowerread, c.minpowerupload, c.minpowermanage");

	// Uploader - folder view
	pageheader("Uploader - \"{$cat['title']}\"");	
	
	// Filtering
	$_GET['sort'] = filter_string($_GET['sort']);
	$_GET['ord']  = filter_int($_GET['ord']);
	$_GET['page'] = filter_int($_GET['page']);
	$_GET['search'] = filter_string($_GET['search']);
	$_GET['searchopt'] = filter_int($_GET['searchopt']);
		
		
	// File name search
	if ($_GET['search']) {
		switch ($_GET['searchopt']) {
			case 1:
				$wild = ['','%']; // Starts with
				break;
			case 2:
				$wild = ['%','']; // Ends with
				break;
			case 3:
				$wild = ['','']; // Exactly
				break;
			default:
				$wild = ['%','%'];
				break;
		}
	}

		
	// Sort field
	switch ($_GET['sort']) {
		case 'd': $sort = 'f.date'; break;
		case 'u': $sort = 'u1.name'; break;
		default:  $sort = 'f.filename';
				  $_GET['sort'] = 'f';
	}
	// Sort order
	$ord = ($_GET['ord'] ? "DESC" : "ASC");
	
	// Pagination
	$ppp = get_ipp($_GET['ppp'], 100);
	$min = $_GET['page'] * $ppp;
	
	// And individual files
	$condition = "f.cat = {$_GET['cat']}"
				 .($isadmin || $loguser['id'] == $cat['user'] ? "" : " AND (!f.private OR f.user = {$loguser['id']})")
				 .($_GET['search'] ? " AND f.filename LIKE ?" : "");
				 
	$qfiles = "
		SELECT f.id, f.hash, f.filename, f.description, f.user, f.private, f.date, f.lasteditdate, f.size, f.downloads,
			".set_userfields('u1').", ".set_userfields('u2')."
		FROM uploader_files f
		LEFT JOIN users u1 ON f.user         = u1.id
		LEFT JOIN users u2 ON f.lastedituser = u2.id
		WHERE {$condition}
		ORDER BY {$sort} {$ord}
		LIMIT $min,$ppp
	";
	$qtotal = "SELECT COUNT(*) FROM uploader_files f WHERE {$condition}";
	
	if ($_GET['search']) { // shrug
		$qargs = [$wild[0].str_replace('*', '%', mysql::filter_like_wildcards($_GET['search'])).$wild[1]];
		$files = $sql->queryp($qfiles, $qargs);
		$total = $sql->resultp($qtotal, $qargs);
	} else {
		$files = $sql->query($qfiles);
		$total = $sql->resultq($qtotal);
	}
	
	
	$links = uploader_breadcrumbs_links($cat);
	$barright = uploader_barright($cat);
	if (can_manage_category($cat) && !$loguser['uploader_locked']) {
		$barright .= " - <a href=\"".actionlink("uploader-catman.php", "?cat={$_GET['cat']}&user={$cat['user']}&action=edit")."\">Edit this folder</a>";
	}
	$breadcrumbs = dobreadcrumbs($links, $barright); 
	
	$pagelinks = pagelist(actionlink(null,"?cat={$_GET['cat']}"), $total, $ppp);

	
	$uploadfile = "";
	if ($loguser['id'] && !$loguser['uploader_locked'] && can_upload_in_category($cat)) {
		$uploadfile = "
		<tr>
			<td class='tdbgc center b' colspan='8'>
				<a href='".actionlink("uploader-up.php", "?cat={$_GET['cat']}")."'>Upload a new file</a>
			</td>
		</tr>";
	}
	
?>
	<?= $breadcrumbs ?>
	<form method="GET" action="<?=actionlink()?>">
	<table class="table">
		<tr><td class="tdbgh center b" colspan="2">Filters</td></tr>
		<tr>
			<td class="tdbg1 center b">
				Search file name:
				<div class="fonts">You can use * as a wildcard.</div>
			</td>
			<td class="tdbg2">
				<input type="text" name="search" style="width: 100%; max-width: 500px" value="<?= htmlspecialchars($_GET['search']) ?>">
				<label><input type="radio" name="searchopt" value="0"<?= ($_GET['searchopt'] == 0 ? " checked" : "") ?>>&nbsp;Default</label>
				<label><input type="radio" name="searchopt" value="1"<?= ($_GET['searchopt'] == 1 ? " checked" : "") ?>>&nbsp;Starts with</label>
				<label><input type="radio" name="searchopt" value="2"<?= ($_GET['searchopt'] == 2 ? " checked" : "") ?>>&nbsp;Ends with</label>
				<label><input type="radio" name="searchopt" value="3"<?= ($_GET['searchopt'] == 3 ? " checked" : "") ?>>&nbsp;Exactly</label>				
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Sort by</td>
			<td class="tdbg2">
				<label><input type="radio" name="sort" value="f"<?= ($_GET['sort'] == 'f' ? " checked" : "") ?>>&nbsp;File name</label>
				<label><input type="radio" name="sort" value="u"<?= ($_GET['sort'] == 'u' ? " checked" : "") ?>>&nbsp;User name</label>
				<label><input type="radio" name="sort" value="d"<?= ($_GET['sort'] == 'd' ? " checked" : "") ?>>&nbsp;Upload date</label>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Order:</td>
			<td class="tdbg2">
				<label><input type="radio" name="ord" value="0"<?= (!$_GET['ord'] ? " checked" : "") ?>>&nbsp;Ascending</label>
				<label><input type="radio" name="ord" value="1"<?= ( $_GET['ord'] ? " checked" : "") ?>>&nbsp;Descending</label>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b"></td>
			<td class="tdbg2">
				<button type="submit">Submit</button>
				<input type="hidden" name="cat" value="<?= $_GET['cat'] ?>">
			</td>
		</tr>
	</table>
	</form>
	<br>

	<?= $pagelinks ?>
	<table class="table">
		<?= $uploadfile ?>
		<tr>
			<td class="tdbgh center b"></td>
			<td class="tdbgh center b">File name</td>
			<td class="tdbgh center b">Description</td>
			<td class="tdbgh center b">Owner</td>
			<td class="tdbgh center b">Upload date</td>
			<td class="tdbgh center b">Last edited</td>
			<td class="tdbgh center b">Size</td>
			<td class="tdbgh center b">Downloads</td>
		</tr>
<?php
	$canmanage = can_manage_category($cat);
	for ($i = 0; $x = $sql->fetch($files); ++$i) {
		$c = ($i % 2)+1;
		
		print "
		<tr>
			<td class='tdbg{$c} center fonts nobr'>
				".(
				!$loguser['uploader_locked'] && ($canmanage || can_edit_file($x))
				? (($isadmin || $xconf['allow-file-edit']) ? "<a href='".actionlink("uploader-editfile.php?action=edit&f={$x['hash']}")."'>Edit</a> - " : "")."<a href='".actionlink("uploader-editfile.php?action=delete&f={$x['hash']}")."'>Delete</a>"
				: "")."
			</td>
			<td class='tdbg{$c}".($x['private'] ? " i" : "")."'><a href='".actionlink("uploader-get.php?f={$x['hash']}")."'>".htmlspecialchars($x['filename'])."</a></td>
			<td class='tdbg{$c} fonts'>".xssfilters($x['description'])."</td>
			<td class='tdbg{$c} center'>".getuserlink(get_userfields($x, 'u1'))."</td>
			<td class='tdbg{$c} center fonts'>".printdate($x['date'])."</td>
			<td class='tdbg{$c} center fonts'>
				".($x['lasteditdate'] ? "
					".printdate($x['lasteditdate'])." by ".getuserlink(get_userfields($x, 'u2'))."
				" : "&mdash;")."
			</td>
			<td class='tdbg{$c} center nobr'>".sizeunits($x['size'])."</td>
			<td class='tdbg{$c} center'>{$x['downloads']}</td>
		</tr>";	
	}	
?>
		<?= $uploadfile ?>
	</table>
	<?= $pagelinks ?>
	<?= $breadcrumbs ?>
<?php

	pagefooter();