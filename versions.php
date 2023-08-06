<?php
	
	require "lib/function.php";
	
	// Change how descriptions are shown
	if (isset($_GET['all'])) {
		set_board_cookie('verAll', (int)($_GET['all'] > 0));		
		$redirStr = "";
		if (isset($_GET['cat']))    $redirStr .= "&cat={$_GET['cat']}";
		if (isset($_GET['id']))     $redirStr .= "&id={$_GET['id']}";
		if (isset($_GET['action'])) $redirStr .= "&action={$_GET['action']}";
		
		return header("Location: ?{$redirStr}");
	}
	
	require "lib/classes/TreeView.php";
	
	$windowtitle = "Version history";
	
	$_GET['action'] = filter_string($_GET['action']);
	$_GET['cat']    = filter_int($_GET['cat']);
	$_GET['id']     = filter_int($_GET['id']);
	
	$_COOKIE['verAll'] = filter_int($_COOKIE['verAll']);
	


	
	if ($_GET['action'] && !$isadmin) {
		errorpage("No.");
	}
	
	// Do not pageheader() when submitting forms
	if (!$_GET['action'] || (!isset($_POST['submit']) && !isset($_POST['submit2']))) { 
		pageheader($windowtitle);
	}
	
	if ($_GET['action'] == 'catdelete') {	
		if (!$sql->resultq("SELECT COUNT(*) FROM archive_cat WHERE id = {$_GET['cat']}"))
			errorpage("This category does not exist.");
		
		if (confirmed($msgkey = 'del-cat')) {
			$_POST['mergeid'] = filter_int($_POST['mergeid']);
			$valid = $sql->resultq("SELECT COUNT(*) FROM archive_cat WHERE id = {$_POST['mergeid']} AND id != {$_GET['cat']}");
			if (!$valid) {
				errorpage("Invalid category selected.");
			}
			
			$sql->beginTransaction();
			// Delete the category and update the item count
			$offset = $sql->resultq("SELECT `count` FROM archive_cat WHERE id = {$_GET['cat']}");
			$sql->query("DELETE FROM archive_cat WHERE id = {$_GET['cat']}");
			$sql->query("UPDATE archive_cat SET `count` = `count` + '{$offset}' WHERE id = {$_POST['mergeid']}");
			$sql->query("UPDATE archive_items SET cat = {$_POST['mergeid']} WHERE cat = {$_GET['cat']}");
			$sql->commit();
			errorpage("Category deleted!", "?", 'the category index');
		}
		
		$categories = $sql->getresultsbykey("SELECT id, title FROM archive_cat WHERE id != {$_GET['cat']}");
		$title   = "Delete Category";
		$message = "
			Are you sure you want to <b>DELETE</b> this category?<br><br>
			The items will be moved into this category: ".field_select('mergeid', $categories, 0, 'Select a category...');
		$form_link = "?action=catdelete&cat={$_GET['cat']}";
		$buttons       = array(
			[BTN_SUBMIT, "Yes"],
			[BTN_URL   , "No", "?"]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	} else if ($_GET['action'] == 'catedit') {
		
		if (isset($_POST['submit']) || isset($_POST['submit2'])) {
			check_token($_POST['auth']);
			if ($_GET['cat'] != -1 && !$sql->resultq("SELECT COUNT(*) FROM archive_cat WHERE id = {$_GET['cat']}"))
				errorpage("This category does not exist.", "?", 'the category index');
			
			$values = array(
				'title'       => filter_string($_POST['title']),
				'description' => filter_string($_POST['description']),
				'minpower'    => filter_int($_POST['minpower']),
				'count'       => filter_int($_POST['count']),
				'ord'         => filter_int($_POST['ord']),
			);
			if (filter_bool($_POST['fixcount'])) {
				$values['count'] = (int)$sql->resultq("SELECT COUNT(*) FROM archive_items WHERE cat = {$_GET['cat']}"); 
			}
			
			if ($_GET['cat'] == -1) {
				$sql->queryp("INSERT INTO archive_cat SET ".mysql::setplaceholders($values), $values);
				$id = $sql->insert_id();
			} else {
				$sql->queryp("UPDATE archive_cat SET ".mysql::setplaceholders($values)." WHERE id = {$_GET['cat']}", $values);
				$id = $_GET['cat'];
			}
			
			if (isset($_POST['submit2'])) {
				return header("Location: ?cat={$id}");
			} else {
				return header("Location: ?cat={$id}&action=catedit");
			}
		}
		
		
		$cat = $sql->fetchq("SELECT * FROM archive_cat WHERE id = {$_GET['cat']}");
		if (!$cat) {
			$_GET['cat'] = -1;
			$cat = array(
				'title'       => '',
				'description' => '',
				'minpower'    => 0,
				'count'       => 0,
				'ord'         => 0,
			);
			$what = "a new category";
		} else {
			$what = "'".htmlspecialchars($cat['title'])."'";
		}
		
?>
		<form method="POST" action="?cat=<?= $_GET['cat'] ?>&action=catedit">
		<table class="table">
			<tr><td class="tdbgh center b" colspan="2">Editing <?= $what ?></td></tr>
			<tr>
				<td class="tdbg1 center b">Title:</td>
				<td class="tdbg2"><input type="text" name="title" style="width: 250px" value="<?= htmlspecialchars($cat['title']) ?>"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Description:</td>
				<td class="tdbg2"><input type="text" name="description" style="width: 600px" value="<?= htmlspecialchars($cat['description']) ?>"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Power level required:</td>
				<td class="tdbg2"><?= power_select('minpower', $cat['minpower']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Item count</td>
				<td class="tdbg2">
					<input type="text" name="count" style="width: 50px" value="<?= $cat['count'] ?>"> - 
					<label><input type="checkbox" name="fixcount" value="1"> Fix automatically</label>
				</td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Order:</td>
				<td class="tdbg2"><input type="text" name="ord" style="width: 50px" value="<?= $cat['ord'] ?>"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b"></td>
				<td class="tdbg2">
					<input type="submit" name="submit" value="Save and continue">&nbsp;<input type="submit" name="submit2" value="Save and close">
					<?= auth_tag() ?>
				</td>
			</tr>
		</table>
		</form>
<?php
		
	} else if ($_GET['action'] == 'delete') {
		if (!$sql->resultq("SELECT COUNT(*) FROM archive_items WHERE id = {$_GET['id']}"))
			errorpage("This item does not exist.");
		
		if (confirmed($msgkey = 'del-item')) {		
			$sql->beginTransaction();
			$sql->query("UPDATE `archive_cat` SET `count` = `count` - 1 WHERE id = (SELECT `cat` FROM `archive_items` WHERE `id` = '{$_GET['id']}')");
			$sql->query("DELETE FROM `archive_items` WHERE `id` = '{$_GET['id']}'");
			$sql->commit();
			errorpage("Item deleted!", "?cat={$_GET['cat']}", 'the category');
		}
		
		$title   = "Warning";
		$message = "Are you sure you want to <b>DELETE</b> this item?";
		$form_link = "?action=delete&cat={$_GET['cat']}&id={$_GET['id']}";
		$buttons       = array(
			[BTN_SUBMIT, "Yes"],
			[BTN_URL   , "No", "?cat={$_GET['cat']}"]
		);
		
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	} else if ($_GET['action'] == 'edit') {
		
		if (isset($_POST['submit']) || isset($_POST['submit2'])) {
			check_token($_POST['auth']);
			
			if ($_GET['id'] != -1 && !($prev = $sql->fetchq("SELECT id, cat FROM archive_items WHERE id = {$_GET['id']}")))
				errorpage("This item does not exist.", "?cat={$_GET['cat']}", 'the category');
			
			$values = array(
				'title'       => filter_string($_POST['title']),
				'description' => filter_string($_POST['description']),
				'features'    => filter_string($_POST['features']),
				'links'       => filter_string($_POST['links']),
				'minpower'    => filter_int($_POST['minpower']),
				'ord'         => filter_int($_POST['ord']),
				'date'        => fieldstotimestamp('date', '_POST'),
				'cat'         => filter_int($_POST['cat']),
			);
			
			$sql->beginTransaction();
			if ($_GET['id'] == -1) {
				$sql->queryp("INSERT INTO archive_items SET ".mysql::setplaceholders($values), $values);
				$id = $sql->insert_id();
				$sql->query("UPDATE `archive_cat` SET `count` = `count` + 1 WHERE id = {$values['cat']}");
			} else {
				if ($prev['cat'] != $values['cat']) {
					$sql->query("UPDATE archive_cat SET `count` = `count` - 1 WHERE id = {$prev['cat']}");
					$sql->query("UPDATE archive_cat SET `count` = `count` + 1 WHERE id = {$values['cat']}");
					$_GET['cat'] = $values['cat'];
				}
				$sql->queryp("UPDATE archive_items SET ".mysql::setplaceholders($values)." WHERE id = {$_GET['id']}", $values);
				$id = $_GET['id'];
			}
			$sql->commit();
			
			if (isset($_POST['submit2'])) {
				return header("Location: ?cat={$_GET['cat']}&id={$id}");
			} else {
				return header("Location: ?cat={$_GET['cat']}&id={$id}&action=edit");
			}
		}
		
		
		$item = $sql->fetchq("SELECT * FROM archive_items WHERE id = {$_GET['id']}");
		if (!$item) {
			$_GET['id'] = -1;
			$item = array(
				'title'       => '',
				'description' => '',
				'features'    => '',
				'links'       => '',
				'date'        => null,
				'minpower'    => 0,
				'cat'         => $_GET['cat'],
				'ord'         => $sql->resultq("SELECT COUNT(ord) FROM archive_items WHERE cat = {$_GET['cat']}") + 1,
			);
			$what = "a new item";
		} else {
			$what = "'".htmlspecialchars($item['title'])."'";
		}
		
		
		$categories = $sql->getresultsbykey("SELECT id, title FROM archive_cat ORDER BY ord ASC, id ASC");
		
?>
		<style>.lh {height: 26px }</style>
		<form method="POST" action="?cat=<?= $_GET['cat'] ?>&id=<?= $_GET['id'] ?>&action=edit">
		<table class="table">
			<tr><td class="tdbgh center b" colspan="4">Editing <?= $what ?></td></tr>
			<tr>
				<td class="tdbg1 center b">Title:</td>
				<td class="tdbg2"><input type="text" name="title" style="width: 250px" value="<?= htmlspecialchars($item['title']) ?>"></td>
				<td class="tdbg1 center b" colspan=2>Options:</td>
			</tr>
			<tr>
				<td class="tdbg1 center b" rowspan=3>Description:</td>
				<td class="tdbg2" rowspan=3>
					<textarea wrap="virtual" name="description" rows="2" cols="80" style="width: 100%; resize:none"><?= htmlspecialchars($item['description']) ?></textarea>
				</td>
				<td class="tdbg1 center b lh">Power level required:</td>
				<td class="tdbg2"><?= power_select('minpower', $item['minpower']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b lh">Category:</td>
				<td class="tdbg2"><?= field_select('cat', $categories, $item['cat'], "Select a category...") ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b"></td>
				<td class="tdbg2"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b" rowspan=3>Features:</td>
				<td class="tdbg2 vatop" id="feattd" rowspan=3>
					<textarea wrap="virtual" id="feattxt" name="features" rows="20" cols="80" style="width: 100%; resize:vertical; white-space: pre; overflow-x: scroll"><?= htmlspecialchars($item['features']) ?></textarea>
				</td>
				<td class="tdbg1 center b lh">Built on:</td>
				<td class="tdbg2"><?= datetofields($item['date'], 'date', DTF_DATE) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b lh">Order:</td>
				<td class="tdbg2"><input type="text" name="ord" style="width: 50px" value="<?= $item['ord'] ?>"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b"></td>
				<td class="tdbg2"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Links:<div class="fonts">One for each line</div></td>
				<td class="tdbg2">
					<textarea wrap="virtual" name="links" rows="2" cols="60" style="width: 100%; resize:vertical"><?= htmlspecialchars($item['links']) ?></textarea>
				</td>
				<td class="tdbg1 center b"></td>
				<td class="tdbg2"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b"></td>
				<td class="tdbg2" colspan="3">
					<input type="submit" name="submit" value="Save and continue">&nbsp;<input type="submit" name="submit2" value="Save and close">
					<?= auth_tag() ?>
				</td>
			</tr>
		</table>
		</form>
<?php
		
		replytoolbar('feat', readsmilies());
	}
	

	
	if ($_GET['cat'] && $_GET['action'] != 'catedit') { //{ > 0 && isset($viewcat)) {
		
		$catlist = $sql->getresultsbykey("
			SELECT id, title 
			FROM archive_cat 
			WHERE !minpower OR minpower <= {$loguser['powerlevel']}
			ORDER BY ord ASC, id ASC
		");
		
		
		if (!isset($catlist[$_GET['cat']])) {
			errorpage("Sorry, but you can't access this category. Either it doesn't exist or you don't have access to it.", 'versions.php', 'the versions page');
		}
		$cattitle = $catlist[$_GET['cat']];
		
		$items = $sql->query("
			SELECT id, title, date, description, features, links
			FROM archive_items
			WHERE cat = {$_GET['cat']} AND (!minpower OR minpower <= {$loguser['powerlevel']})
			ORDER BY ord ASC, id ASC
		");

		
		$txt  = "";
		for ($i = 0; $x = $sql->fetch($items); ++$i) {
			$nest = 0;
			if ($_GET['id'] == $x['id'])
				$cell = 'c';
			else
				$cell = ($i%2)+1;
			
			$editlink = ($isadmin ? "<span class='fonts'><a href='?cat={$_GET['cat']}&id={$x['id']}&action=edit'>Edit</a> - <a href='?cat={$_GET['cat']}&id={$x['id']}&action=delete'>Delete</a></span>" : "");
			
			$txt .= "
			<tr id='i{$x['id']}'>
				<td class='tdbg{$cell} center vatop'>
					<a href='?cat={$_GET['cat']}&id={$x['id']}#i{$x['id']}'>".xssfilters($x['title'])."</a>
					<br>{$editlink}
					".($x['date'] ? "<div class='fonts'>(".printdate($x['date'], true).")</div>" : "")."
				</td>
				<td class='tdbg{$cell} vatop'>
					<div>".nl2br(xssfilters($x['description']))."</div>
					".($_COOKIE['verAll'] || $x['id'] == $_GET['id'] ? "
					<br>
					<div>".nl2br(str_replace("  ", "&nbsp;&nbsp;", xssfilters($x['features'])))
						/*preg_replace_callback(
							"'(^|<br>)( +)?-'si",
							function ($x) use ($nest) {
								// Automatically generate <ul> and <li> based on indentation
								global $nest;
								$cnest = strlen(filter_string($x[2]));
								$x[2]  = "";
								if ($cnest > $nest) { // Opened new
									for (; $cnest > $nest; ++$nest)
										$x[2] .= "<ul>";
								} else if ($cnest < $nest) { // Closed new
									for (; $cnest < $nest; --$nest)
										$x[2] .= "</ul>";
								}
								return $x[1].$x[2]."<li>";
							},
							xssfilters($x['features'])
						)*/."</div>
					" : "")."
				</td>
				<td class='tdbg{$cell} center vatop'>
					".nl2br(xssfilters($x['links']))."
				</td>
			</tr>";
		}
		
		if ($isadmin) {
			$txt = "<tr><td class='tdbgc center b' colspan='3'><a href='?cat={$_GET['cat']}&id=-1&action=edit'>&lt; Add a new item &gt;</a></td></tr>{$txt}";
		}
		
		//if ($txt) {
			
			// The selected link becomes bolded to highlight the choice
			if ($_COOKIE['verAll']) {
				$wa = "b";
				$wo = "a";
			} else {
				$wa = "a";
				$wo = "b";
			}
			
			// Now uses a breadcrumbs bar to save on space
			$links = array(
				[$windowtitle, "?"],
				[$cattitle, "?cat={$_GET['cat']}"],
			);
			$right = "Show description: <{$wa} href='?cat={$_GET['cat']}&id={$_GET['id']}&all=1'>All</{$wa}> - <{$wo} href='?cat={$_GET['cat']}&id={$_GET['id']}&all=0'>Only selected</{$wo}>";
			$barlinks = dobreadcrumbs($links, $right); 
			
			// Sidebar selection code (to save vertical space when a category is selected)
			$urlformat = "?cat=";
			$sidebar = new TreeView("Categories", TreeView::ParseSubmenu($catlist, $urlformat)); 
			
?>
			<style>ul {padding-left: 20px} </style>
			<?= $barlinks . $sidebar->DisplaySidebar($urlformat.$_GET['cat']) ?>
			<table class="table">
				<tr><td class="tdbgh center b" colspan="3"><?= $cattitle ?> | Viewable items: <?= $i ?></td></tr>
				<tr>
					<td class="tdbgh center b nobr"></td>
					<td class="tdbgh center b"></td>
					<td class="tdbgh center b nobr" style="width: 150px"></td>
				</tr>
				
				<?= $txt ?>
			</table>
			<?= $sidebar->DisplayBottom() . $barlinks ?>
<?php
		//}
	} else {
		
		$cats = $sql->query("
			SELECT id, title, description, count 
			FROM archive_cat 
			WHERE !minpower OR minpower <= {$loguser['powerlevel']}
			ORDER BY ord ASC, id ASC
		");
		$i    = 0;
		$txt  = "";
		while ($x = $sql->fetch($cats)) {
			if ($_GET['cat'] == $x['id']) {
				$cell = 'c';
				$viewcat = true;
			} else {
				$cell = (++$i%2)+1;
			}
			$editlink = ($isadmin ? "<div class='fonts'><a href='?cat={$x['id']}&action=catedit'>Edit</a> - <a href='?cat={$x['id']}&action=catdelete'>Delete</a></div>" : "");
			
			$txt .= "
			<tr>
				<td class='tdbg{$cell} center'><a href='?cat={$x['id']}'>View</a>{$editlink}</td>
				<td class='tdbg{$cell}'>
					<b>".htmlspecialchars($x['title'])."</b>
					<div class='fonts'>".xssfilters($x['description'])."</div>
				</td>
				<td class='tdbg{$cell} center'>{$x['count']}</td>
			</tr>";
		}
		
		if ($isadmin) {
			$txt .= "<tr><td class='tdbgc center b' colspan='3'><a href='?cat=-1&action=catedit'>&lt; Add a new category &gt;</a></td></tr>";
		}
		
		$links = array(
			[$windowtitle, null],
		);
		$barlinks = dobreadcrumbs($links); 
		
?>
		<?= $barlinks ?>
		<table class="table">
			<tr><td class="tdbgh center b" colspan="3">Categories</td></tr>
			<tr>
				<td class="tdbgh center" style="width: 150px">#</td>
				<td class="tdbgh center">Category</td>
				<td class="tdbgh center" style="width: 75px">Items</td>
			</tr>
			<?= $txt ?>
		</table>
		<?= $barlinks ?>
<?php
	
	}
	
	pagefooter();
	
function field_select($name, $arr, $sel = 0, $def = "") {
	$txt = ($def ? "<option value='0'>".htmlspecialchars($def)."</option>\n" : "");
	foreach ($arr as $key => $val) {
		$txt .= "<option value='{$key}'".($key == $sel ? " selected" : "").">".htmlspecialchars($val)."</option>\n";
	}
	return "<select name='{$name}'>{$txt}</select>";
}