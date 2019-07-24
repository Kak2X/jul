<?php
	require "lib/function.php";
	require "lib/uploader_function.php";
	
	pageheader("Uploader");
	
	$_GET['cat']  = filter_int($_GET['cat']);
	
	if (!$_GET['cat']) {
		switch ($_GET['mode']) {
			case 'u':
				$where = ($_GET['user'] ? "c.user = {$_GET['user']}" : "c.user != 0").
				         ($isadmin ? "" : " AND (!c.minpowerread OR c.minpowerread <= {$loguser['powerlevel']} OR c.user = {$loguser['id']})");
				break;
			default:
				$where = "c.user = 0 ".($isadmin ? "" : " AND (!c.minpowerread OR c.minpowerread <= {$loguser['powerlevel']})");
				break;
		}
		
		// Get all of the global categories
		$cats = $sql->query("
			SELECT c.id, c.title, c.description, c.files, c.downloads, c.lastfiledate, (c.minpowerread > 0) private, $userfields lastfileuser
			FROM uploader_cat c
			LEFT JOIN users u ON c.lastfileuser = u.id
			WHERE {$where}
			ORDER BY c.ord ASC, c.id ASC
		");
		
		$user = uploader_load_user($_GET['user']);
		$links = uploader_breadcrumbs_links(NULL, $user);	
		$barright = _barright();
		$breadcrumbs = dobreadcrumbs($links, $barright); 
	
?>
		<?= $breadcrumbs ?>
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
				<td class='tdbg{$c}".($x['private'] ? " i" : "")."'><a href='{$baseparams}&cat={$x['id']}'>".htmlspecialchars($x['title'])."</a><span class='fonts'><br>".htmlspecialchars($x['description'])."</span></td>
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
		<?= $breadcrumbs ?>
<?php
	}
	else {
		load_uploader_category($_GET['cat'], "id, title, user, minpowerread, minpowerupload, minpowermanage");
		uploader_fix_baseparams($cat);

		
		
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
		
		$user = uploader_load_user($_GET['user']);
		$links = uploader_breadcrumbs_links($cat, $user);
		$barright = _barright();
		if (can_manage_category($cat) && !$loguser['uploader_locked']) {
			$barright .= " - <a href=\"uploader-catman.php{$baseparams}&cat={$_GET['cat']}&action=edit\">Edit this folder</a>";
		}
		$breadcrumbs = dobreadcrumbs($links, $barright); 
		
		$pagelinks = pagelist("{$baseparams}&cat={$_GET['cat']}", $total, $ppp);
	
		
		$uploadfile = "";
		if ($isadmin || (!$loguser['uploader_locked'] && ($loguser['id'] == $cat['user'] || $loguser['powerlevel'] >= $cat['minpowerupload']))) {
			$uploadfile = "
			<tr>
				<td class='tdbgc center b' colspan='8'>
					<a href='uploader-up.php{$baseparams}&cat={$_GET['cat']}'>Upload a new file</a>
				</td>
			</tr>";
		}
		
?>
		<?= $breadcrumbs ?>
		<form method="GET" action="?">
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
					<?= uploader_base_params(true) ?>
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
		$canmanage = can_manage_category_files($cat);
		for ($i = 0; $x = $sql->fetch($files); ++$i) {
			$c = ($i % 2)+1;
			
			print "
			<tr>
				<td class='tdbg{$c} center fonts nobr'>
					".(
					!$loguser['uploader_locked'] && ($canmanage || (!$banned && $x['user'] == $loguser['id']))
					? (($isadmin || $config['uploader-allow-file-edit']) ? "<a href='uploader-editfile.php?action=edit&f={$x['hash']}'>Edit</a> - " : "")."<a href='uploader-editfile.php?action=delete&f={$x['hash']}'>Delete</a>"
					: "")."
				</td>
				<td class='tdbg{$c}".($x['private'] ? " i" : "")."'><a href='uploader-get.php?f={$x['hash']}'>".htmlspecialchars($x['filename'])."</a></td>
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
	}
	
	pagefooter();
	
	function _barright() {
		global $loguser, $isadmin, $baseparams, $user;
		$barright = "<a href='uploader-catbyuser.php'>Folders by user</a>";
		if ($loguser['id'] && !$loguser['uploader_locked']) {
			if ($isadmin)
				$barright .= " - <a href='uploader-catman.php'>Manage shared folders</a>";
			if (($isadmin && $_GET['user']) || $_GET['user'] == $loguser['id'])
				$barright .= " - <a href='uploader-catman.php{$baseparams}'>Manage ".htmlspecialchars($user['name'])."'s folders</a>";
		}
		return $barright;
	}