<?php
	
	require "lib/function.php";
	admincheck();
	
	$_GET['id']     = filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);
	
	if ($_GET['action'] == 'resync') {
		if (confirmed($msgkey = 'resync')) {
			resync_post_ratings();
			errorpage("The rating counts have been syncronized.<br>Click <a href='?'>here</a> to continue.");
		}
		
		$title     = "Post Rating Syncronization";
		$message   = "
			This will resyncronize the post rating totals for all users.<br>
			This can be a potentially slow action; please don't flood this page with requests.";
		$form_link = "?action=resync";
		$buttons   = array(
			[BTN_SUBMIT, "Resyncronize ratings"],
			[BTN_URL   , "Cancel", "?"]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}
	
	
	if (isset($_POST['submit']) || isset($_POST['submit2'])) {
		if ($_GET['id'] > 0 && filter_bool($_POST['delete'])) {
			if (confirmed($msgkey = 'rat-del')) {
				$sql->beginTransaction();
				//--
				$sql->query("DELETE FROM ratings WHERE id = {$_GET['id']}");
				$sql->query("DELETE FROM posts_ratings WHERE rating = {$_GET['id']}");
				$sql->query("DELETE FROM pm_ratings WHERE rating = {$_GET['id']}");
				//--
				$sql->commit();
				$id = 0; // Don't display edit window
				die(header("Location: ?"));
			}
			
			// Warn before doing potentially bad stuff
			$title     = "Warning";
			$message   = "
				Are you sure you want to <b>permanently DELETE</b> this rating?<br>
				If you only want to soft delete a rating, please disable it instead.<br><br>
				After deleting the rating, you may want to resyncronize the rating counts.";
			$form_link = "?id={$_GET['id']}";
			$buttons   = array(
				[BTN_SUBMIT, "Delete rating"],
				[BTN_URL   , "Cancel", "?id={$_GET['id']}"]
			);
			confirm_message($msgkey, $message, $title, $form_link, $buttons);

		} else {
			check_token($_POST['auth']);
			$values = array(
				'title'       => filter_string($_POST['title']),
				'description' => filter_string($_POST['description']),
				'image'       => filter_string($_POST['image']),
				'points'      => filter_int($_POST['points']),
				'enabled'     => filter_int($_POST['enabled']),
				'minpower'    => filter_int($_POST['minpower']),
			);
			$phs = mysql::setplaceholders($values);
			
			if ($_GET['id'] > 0) {
				$sql->queryp("UPDATE ratings SET {$phs} WHERE id = {$_GET['id']}", $values);
				$id = $_GET['id'];
			} else {
				$sql->queryp("INSERT INTO ratings SET {$phs}", $values);
				$id = $sql->insert_id();
			}
			
		}
		$editlink = isset($_POST['submit']) ? "id={$id}" : ""; // Save and continue?
		return header("Location: ?{$editlink}");
	}
	
	pageheader("Ratings editor");
	print adminlinkbar();
	
	$ratings = get_ratings(true);
	
	if ($_GET['id']) {
		if ($_GET['id'] <= -1 || !isset($ratings[$_GET['id']])) {
			$x = array(
				'title'       => 'New rating',
				'description' => 'Sample description',
				'image'       => 'images/ratings/default/denied.gif',
				'points'      => 1,
				'enabled'     => 1,
				'minpower'    => 0,
			);
			$editAction = "New rating";
			$delrating = "";
		} else {
			$x = $ratings[$_GET['id']];
			$editAction = "Editing rating '".htmlspecialchars($x['title'])."'";
			$delrating = "<input type='checkbox' name='delete' value=1> Delete rating";
		}		
?>
		<form method="POST" action="?id=<?= $_GET['id'] ?>">
		<table class="table" style="width: 800px; margin: auto">
			<tr><td class="tdbgh center b" colspan=7><?= $editAction ?></td></tr>
			
			<tr>
				<td class="tdbg1 center b">Title:</td>
				<td class="tdbg1"><input type="text" name="title" style="width: 300px" maxwidth=30 value="<?= htmlspecialchars($x['title']) ?>"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Description:</td>
				<td class="tdbg1"><input type="text" name="description" style="width: 550px" maxwidth=100 value="<?= htmlspecialchars($x['description']) ?>"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Image link:</td>
				<td class="tdbg1"><input type="text" name="image" style="width: 550px" maxwidth=100 value="<?= htmlspecialchars($x['image']) ?>"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Points awarded:</td>
				<td class="tdbg1"><input type="text" name="points" style="width: 50px" maxwidth=4 value="<?= htmlspecialchars($x['points']) ?>"></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Power level required:</td>
				<td class="tdbg1"><?= power_select('minpower', $x['minpower']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Options</td>
				<td class="tdbg1">
					<label><input type="checkbox" name="enabled" value=1<?= ($x['enabled'] ? " checked" : "") ?>> Enabled</label>
				</td>
			</tr>
			<tr>
				<td class="tdbg1 center b"></td>
				<td class="tdbg1">
					<input type="submit" name="submit" value="Save and continue"> <input type="submit" name="submit2" value="Save and close">
					<?= auth_tag() ?>
					<label style="float: right; padding-right: 5px"><?= $delrating ?></label>
				</td>
			</tr>
		</table>
		</form>
<?php } ?>
	
	<div class="font right">
		Actions: <a href='?id=-1'>Add a new rating</a> - <a href='?action=resync'>Resync ratings</a>
	</div>
	<table class='table'>
		<tr><td class='tdbgh center b' colspan=8>Ratings list</td></tr>
		<tr>
			<td class='tdbgc center' style='width: 50px'></td>
			<td class='tdbgc center' style='width: 50px'>Set</td>
			<td class='tdbgc center' style='width: 60px'>Preview</td>
			<td class='tdbgc center'>Title</td>
			<td class='tdbgc center'>Image</td>
			<td class='tdbgc center'>Description</td>
			<td class='tdbgc center' style='width: 200px'>Power level required</td>
			<td class='tdbgc center' style='width: 50px'>Pts.</td>
		</tr>
	<?php
	$i = 0;
	foreach ($ratings as $id => $data) {
		$cell = ($i++%2)+1;
		print "
		<tr>
			<td class='tdbg{$cell} fonts center'><a href='?id={$id}'>Edit</a></td>
			<td class='tdbg{$cell} center b'>".rating_colors(($data['enabled'] ? "ON" : "OFF"), ($data['enabled'] ? 1 : -1))."</td>
			<td class='tdbg{$cell} center'>".rating_image($data)."</td>
			<td class='tdbg{$cell}'>".htmlspecialchars($data['title'])."</td>
			<td class='tdbg{$cell}'>".htmlspecialchars($data['image'])."</td>
			<td class='tdbg{$cell}'>".htmlspecialchars($data['description'])."</td>
			<td class='tdbg{$cell} center'>{$pwlnames[$data['minpower']]}</td>
			<td class='tdbg{$cell} center'>".rating_colors($data['points'],$data['points'])."</td>
		</tr>";
	}
?>
	</table>
<?php
	
	pagefooter();