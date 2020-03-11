<?php

	require "lib/function.php";
	
	admincheck();
	
	pageheader("");
	
	print adminlinkbar();

	if (!isset($_POST['run'])) {
		?>
		<form action="?" method="post">  
		<table class='table'>
			<tr><td class='tdbgh center'>Uploader File Count Fix</td></tr>
			<tr><td class='tdbg1 center'>&nbsp;
				<br>This page is intended to repair folders with incorrect file counts.
				<br>This will also reveal files in broken folders, which you can then move as you would move any other file.
				<br>&nbsp;
				<br><input type='submit' name="run" value="Start"><?= auth_tag() ?>
				<br>&nbsp;
			</td></tr>
		</table>
		</form>
		<?php
	} else {
		
		check_token($_POST['auth']);
?>
		<table class="table">
			<tr>
				<td class="tdbgh center b">#</td>
				<td class="tdbgh center b">Folder</td>
				<td class="tdbgh center b">File count</td>
				<td class="tdbgh center b">Fixed count</td>
				<td class="tdbgh center b">Err.</td>
				<td class="tdbgh center b">Status</td>
			</tr>
<?php		
		
		// main block fix
		$cats = $sql->query("
			SELECT f.cat id, c.title, c.files count_cur, COUNT(f.id) count_real 
			FROM uploader_files f
			LEFT JOIN uploader_cat c ON c.id = f.cat
			GROUP BY f.cat
			HAVING count_real != count_cur
			ORDER BY ISNULL(c.id) ASC, c.id ASC
		");
		
		$update = $sql->prepare("UPDATE uploader_cat SET files = ? WHERE id = ?");
		$count = _fix_file_count($cats, $update);
		
		// leftovers not caught by the above query (categories with cur > 0 and real == 0)
		$blanks = $sql->query("
			SELECT c.id, c.title, c.files count_cur, COUNT(f.id) count_real 
			FROM uploader_cat c
			LEFT JOIN uploader_files f ON c.id = f.cat
			GROUP BY f.cat
			HAVING count_cur > 0 AND count_real = 0
			ORDER BY ISNULL(c.id) ASC, c.id ASC
		");
		// for convenience we also null out the last file info
		$update = $sql->prepare("UPDATE uploader_cat SET files = ?, lastfiledate = 0, lastfileuser = 0 WHERE id = ?");
		$count += _fix_file_count($blanks, $update);
		
		
		if ($count) {
			print "<tr><td class='tdbgc center' colspan=6>$count folder". ($count != 1 ? "s" : "") ." updated.</td></tr>";
		} else {
			?>
			<tr>
				<td class="tdbg1 center" colspan="6">&nbsp;
					<br>No problems found.
					<br>&nbsp;
				</td>
			</tr>
			<?php
		}
		?>
		</table><?php
	}
	
	pagefooter();
	
	function _fix_file_count($cats, $update) {
		global $sql;
		$count = 0;
		for ($c = true; $x = $sql->fetch($cats); $c = !$c) {
			$cell = $c ? 1 : 2;
			
			if (!$x['id']) {
				$x['count_real'] = $x['offset'] = "&mdash;";
				$x['title'] = "<i>(broken category)</i>";
				$status = "<span style='color:#ff0000'>&mdash;</span>";
			} else {
				$x['offset'] = $x['count_real'] - $x['count_cur'];
				$status = $sql->execute($update, [$x['count_real'], $x['id']]);
				if ($status) 	$status = "<span style='color:#80ff80'>Updated</span>";
				else 			$status = "<span style='color:#ff0000'>Error</span>";
				++$count;
			}
			print "<tr>
				<td class='tdbg{$cell} center'>{$x['id']}</td>
				<td class='tdbg{$cell}'><a href='uploader.php?cat={$x['id']}'>".htmlspecialchars($x['title'])."</a></td>
				<td class='tdbg{$cell} center'>{$x['count_cur']}</td>
				<td class='tdbg{$cell} center'>{$x['count_real']}</td>
				<td class='tdbg{$cell} center'>{$x['offset']}</td>
				<td class='tdbg{$cell} center'>{$status}</td>
			</tr>";	
		}
		
		return $count;
	}