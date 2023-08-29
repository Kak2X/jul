<?php

	require "lib/common.php";
	admincheck();
	
	$_GET['id']     = filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);
	$_POST['page']  = filter_int($_POST['page']);
	$_GET['r']      = filter_int($_GET['r']);
	
	
	if ($_GET['action'] == 'edit') {
		
		if (isset($_POST['submit']) || isset($_POST['submit2'])) {
			if ($_GET['id'] != -1 && !$sql->resultq("SELECT COUNT(*) FROM attachments WHERE id = {$_GET['id']}"))
				errorpage("This attachment does not exist.");
			
			$_POST['assign'] = filter_int($_POST['assign']);
			// Determine if the ID is assigned to a post or PM
			if (filter_int($_POST['type'])) {
				$redir = "showprivate.php";
				$post  = 0;
				$pm    = $_POST['assign'];
				$valid = (!$pm || $sql->resultq("SELECT COUNT(*) FROM pm_posts WHERE id = $pm")); 
			} else {
				$redir = "thread.php";
				$pm    = 0;
				$post  = $_POST['assign'];
				$valid = (!$post || $sql->resultq("SELECT COUNT(*) FROM posts WHERE id = $post")); 
			}
			
			if (!$valid) 
				errorpage("Invalid post ID specified.");
			
			$user = filter_int($_POST['user']);
			if (!valid_user($user))
				errorpage("You didn't select an user.");
			
			// Upload / reupload
			$file = filter_array($_FILES['upload']);
			if (upload_error($file, true))
				$file = NULL;
			
			// If no file name is specified, use the one from the uploaded file.
			if (!isset($_POST['filename'])) {
				if (!$file) {  // unless no new file is being uploaded
					errorpage("No file name specified.");
				}
				$_POST['filename'] = str_replace(array("\r", "\n"), '', $file['name']);
			}
			
			if ($_GET['id'] == -1) {
				if (!$file)
					errorpage("No file selected.");
				
				// Go through normal post procedure.
				$key   = "aac{$loguser['id']}";
				$post  = $_POST['assign'];
				$flags = ($pm ? ATTACH_PM : 0);
				
				upload_attachment($file, $key, $user, $post, $flags);
				$ids = confirm_attachments($key, $user, $post, $flags);
				
				$_GET['id'] = array_pop($ids); // redirect to upload edit
			
				$in = array(
					'filename' => filter_string($_POST['filename']),
					'post'     => $post,
					'pm'       => $pm,
					'user'     => $user,
				);
			} else {
				if ($file) {
					// We already know the ID of the file, so do an instant replace
					$path = attachment_name($_GET['id']);
					// Move it right away
					move_uploaded_file($file['tmp_name'], $path);
					
					$_POST['mime'] = mime_content_type($path);
					$_POST['size'] = $file['size'];
						
					list($width, $height) = getimagesize($path);
					$_POST['is_image'] = ($width && $height);
				}
				$in = array(
					'filename' => filter_string($_POST['filename']),
					'post'     => $post,
					'pm'       => $pm,
					'mime'     => filter_string($_POST['mime']),
					'views'    => filter_int($_POST['views']),
					'size'     => filter_int($_POST['size']),
					'is_image' => filter_int($_POST['is_image']),
					'user'     => $user,
				);
			}
			
			$sql->queryp("UPDATE attachments SET ".mysql::setplaceholders($in)." WHERE id = {$_GET['id']}", $in);
			if (isset($_POST['submit2'])) {
				if ($_GET['r']) {
					return header("Location: {$redir}?pid={$_POST['assign']}#{$_POST['assign']}");
				} else {
					return header("Location: ?");
				}
			} else {
				return header("Location: ?id={$_GET['id']}&r={$_GET['r']}&action=edit");
			}
		}
		
		$attach = $sql->fetchq("SELECT * FROM attachments WHERE id = {$_GET['id']}");
		if (!$attach) {
			$title = "Creating a new attachment";
			$attach = array(
				'filename' => '',
				'post'     => 0,
				'pm'       => 0,
				'mime'     => '', // 'text/plain',
				'views'    => 0,
				'size'     => 0,
				'is_image' => 0,
				'user'     => 0,
			);
			$_GET['id'] = -1;
		} else {
			$title = "Editing an attachment";
		}
		
		pageheader($title);
		print adminlinkbar();
?>
	<form method="POST" action="?action=edit&id=<?= $_GET['id'] ?>&r=<?= $_GET['r'] ?>" enctype="multipart/form-data">
	<table class="table">
		<tr><td class="tdbgh center b" colspan="2"><?= $title ?></td></tr>
		<tr>
			<td class="tdbg1 center b">File name:</td>
			<td class="tdbg2">
				<input type="text" name="filename" style="width: 500px" value="<?= htmlspecialchars($attach['filename']) ?>">
				<span class="fonts"> if (re)uploading the file, you can leave this blank to use the new file's name</span>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Assigned to:</td>
			<td class="tdbg2">
				<label><input type="radio" name="type" value=0<?= $attach['post'] != 0 ? " checked" : ""?>> Post</label>
				<label><input type="radio" name="type" value=1<?= $attach['pm'] != 0 ? " checked" : ""?>> PM</label>
				<input type="text" name="assign" style="width: 100px" value="<?= max($attach['post'], $attach['pm']) ?>">
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Uploaded by:</td>
			<td class="tdbg2"><?= user_select('user', $attach['user']) ?></td>
		</tr>
<?php if ($_GET['id'] != -1) { ?>
		<tr>
			<td class="tdbg1 center b">MIME type:</td>
			<td class="tdbg2"><?= mime_select('mime', $attach['mime']) ?></td>
		</tr>
		<tr>
			<td class="tdbg1 center b">File size (bytes):</td>
			<td class="tdbg2"><input type="text" name="size" style="width: 150px" value="<?= $attach['size'] ?>"></td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Downloads:</td>
			<td class="tdbg2"><input type="text" name="views" style="width: 150px" value="<?= $attach['views'] ?>"></td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Options:</td>
			<td class="tdbg2">
				<label><input type="checkbox" name="is_image" value=1<?= $attach['is_image'] ? " checked" : ""?>> Display thumbnail</label>
			</td>
		</tr>
<?php } ?>
		<tr>
			<td class="tdbg1 center b">(Re)upload:</td>
			<td class="tdbg2"><input type="file" name="upload"></td>
		</tr>
		<tr>
			<td class="tdbg1 center b"></td>
			<td class="tdbg2"><input type="submit" name="submit" value="Save and continue"> - <input type="submit" name="submit2" value="Save and close"></td>
		</tr>
	</table>
	</form>
<?php
		
	} else if ($_GET['action'] == 'delete') {
		
		$data = $sql->fetchq("SELECT filename, pm, post FROM attachments WHERE id = {$_GET['id']}");
		if (!$data) {
			errorpage("This attachment does not exist. It may have already been deleted.");
		}
		
		// Redirect selection 
		// Are we coming from the attachment editor or a thread / PM?
		if ($_GET['r']) {
			$redirurl  = ($data['pm'] ? "showprivate.php?pid={$data['pm']}#{$data['pm']}" : "thread.php?pid={$data['post']}#{$data['post']}");
			$redirpage = ($data['pm'] ? "the private message" : "the post");
		} else {
			$redirurl  = "?";
			$redirpage = "the attachments page";
		}
		
		if (confirmed($msgkey = 'del-atc')) {
			remove_attachments(array($_GET['id']));
			errorpage("The attachment has been deleted!", $redirurl, $redirpage);
		}
		
		$title     = "Delete Attachment";
		$message   = "Are you sure you want to permanently <b>DELETE</b> the attachment \"".htmlspecialchars($data['filename'])."\"?";
		$form_link = "?action=delete&id={$_GET['id']}&r={$_GET['r']}";
		$buttons   = array(
			[BTN_SUBMIT, "Yes"],
			[BTN_URL   , "No", $redirurl]
		);
		
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	} else {
		pageheader("Attachments");
		print adminlinkbar();
		
		$_POST['type']     = filter_int($_POST['type']);
		$_POST['filename'] = filter_string($_POST['filename']);
		$_POST['mime']     = filter_string($_POST['mime']);
		$_POST['thumb']    = filter_int($_POST['thumb']);
		$_POST['user']     = filter_int($_POST['user']);
		
		$ppp = 50;
		$min = $_POST['page'] * $ppp;
		
		$where = array();
		$values = array();
		switch ($_POST['type']) {
			case 1: $where[] = "a.post != 0"; break;
			case 2: $where[] = "a.pm != 0"; break;
			case 3: $where[] = "(a.pm = 0 AND a.post = 0)"; break;
		}
		if ($_POST['filename']) {
			$where[] = "a.filename LIKE ?";
			$values[] = str_replace('*', '%', $_POST['filename']);
		}
		if ($_POST['mime']) {
			$where[] = "a.mime LIKE ?";
			$values[] = str_replace('*', '%', $_POST['mime']);
		}
		switch ($_POST['thumb']) {
			case 1: $where[] = "a.is_image != 0"; break;
			case 2: $where[] = "a.is_image = 0"; break;
		}
		if ($_POST['user']) {
			$where[] = "a.user = ?";
			$values[] = $_POST['user'];
		}
		
		if ($where)
			$qwhere = "WHERE ".implode(' AND ', $where);
		else
			$qwhere = "";
		
		$total = $sql->resultp("SELECT COUNT(*) FROM attachments a {$qwhere}", $values);
		if ($min > $total) {
			$_POST['page'] = floor($total / $ppp);
			$min = ($ppp * $_POST['page']);
		}
		$pagelinks = page_select($total, $ppp);
		
		$attachments = $sql->queryp("
			SELECT a.*, $userfields uid
			FROM attachments a
			LEFT JOIN users u ON a.user = u.id
			
			{$qwhere}
			ORDER BY a.id ASC
			LIMIT {$min},{$ppp}
		", $values);
		
		$txt = "";
		$i = 0;
		while ($x = $sql->fetch($attachments)) {
			$cell = (++$i)%2+1;
			if ($x['post']) {
				$type = "<a href='thread.php?pid={$x['post']}#{$x['post']}'>Post</a>";
			} else if ($x['pm']) {
				$type = "<a href='showprivate.php?pid={$x['pm']}#{$x['pm']}'>PM</a>";
			} else {
				$type     = "-";
			}
			$txt .= "<tr>
				<td class='tdbg{$cell} center fonts nobr'><a href='?id={$x['id']}&action=edit'>Edit</a> - <a href='?id={$x['id']}&action=delete'>Delete</a></td>
				<td class='tdbg{$cell} center'>{$x['id']}</td>
				<td class='tdbg{$cell} center'><a href='download.php?id={$x['id']}' target='_blank'>".htmlspecialchars($x['filename'])."</a></td>
				<td class='tdbg{$cell} center'>".htmlspecialchars($x['mime'])."</td>
				<td class='tdbg{$cell} center'>".sizeunits($x['size'])."</td>
				<td class='tdbg{$cell} center'>".getuserlink($x, $x['uid'])."</td>
				<td class='tdbg{$cell} center'>{$x['views']}</td>
				<td class='tdbg{$cell} center'>".($x['is_image'] ? "Yes" : "No")."</td>
				<td class='tdbg{$cell} center'>{$type}</td>
			</tr>";
		}
		$sel_type[$_POST['type']]   = ' checked';
		$sel_thumb[$_POST['thumb']] = ' checked';
		
?>
		<form method="POST" action="?">
		<table class="table">
			<tr><td class="tdbgh center b" colspan=2>Attachments</td></tr>
			<tr>
				<td class="tdbg1 center b">View from:</td>
				<td class="tdbg2">
					<label><input type="radio" name="type" value="0" <?= filter_string($sel_type[0]) ?>> All</label>
					<label><input type="radio" name="type" value="1" <?= filter_string($sel_type[1]) ?>> Posts</label>
					<label><input type="radio" name="type" value="2" <?= filter_string($sel_type[2]) ?>> PMs</label>
					<label><input type="radio" name="type" value="3" <?= filter_string($sel_type[3]) ?>> Unassigned</label>
				</td>
			</tr>
			<tr>
				<td class="tdbg1 center b">File name:</td>
				<td class="tdbg2">
					<input type="text" name="filename" value="<?= htmlspecialchars($_POST['filename']) ?>" style="width: 450px">
					<span class="fonts">use * as wildcard</span>
				</td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Uploaded by:</td>
				<td class="tdbg2">
					<?= user_select('user', $_POST['user'], null, "*** Any user ***") ?>
				</td>
			</tr>
			<tr>
				<td class="tdbg1 center b">MIME type:</td>
				<td class="tdbg2"><?= mime_select('mime', $_POST['mime']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Thumbnail option:</td>
				<td class="tdbg2">
					<label><input type="radio" name="thumb" value="0" <?= filter_string($sel_thumb[0]) ?>> All</label>
					<label><input type="radio" name="thumb" value="1" <?= filter_string($sel_thumb[1]) ?>> With thumbnail</label>
					<label><input type="radio" name="thumb" value="2" <?= filter_string($sel_thumb[2]) ?>> Without thumbnail</label>
				</td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Page:</td>
				<td class="tdbg2"><?= $pagelinks ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b"></td>
				<td class="tdbg2">
					<input type="submit" name="submit" value="Submit">
				</td>
			</tr>
		</table>
		</form>
		<table class="table">
			<tr><td class='tdbgc center'><a href='?action=edit&id=-1'>&lt; Upload a new attachment &gt;</a></td></tr>
		</table>
		<br>
		<table class="table">
			<tr>
				<td class='tdbgh center'></td>
				<td class='tdbgh center'>#</td>
				<td class='tdbgh center'>Filename</td>
				<td class='tdbgh center'>MIME type</td>
				<td class='tdbgh center'>Size</td>
				<td class='tdbgh center'>Uploaded by</td>
				<td class='tdbgh center'>Downloads</td>
				<td class='tdbgh center'>Thumbnail</td>
				<td class='tdbgh center'>Type</td>
			</tr>
			<?= $txt ?>
		</table>
<?php
		
		
	}
	
	pagefooter();