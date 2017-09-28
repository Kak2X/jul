<?php
	
	require "lib/function.php";
	
	if (!$config['allow-image-uploads']) errorpage("File / image uploads have been disabled.");
	if (!$loguser['id'])                 errorpage("You need to be logged in to edit your avatars.");
	if ($banned)                         errorpage("Banned users aren't allowed to edit avatars.");
	if (!has_perm('upload-avatars'))     errorpage("You aren't allowed to upload avatars.");
	
	
	const DELAYED_CRAP = " <br>(the changes may not take effect immediately)";
	const AVBOX_DEFAULT  = 0b1;
	const AVBOX_UPLOAD   = 0b10; // uses 'new' for upload prefix
	
	// Editing another user?
	$_GET['id'] = filter_int($_GET['id']);
	if (!$_GET['id']) {
		$_GET['id'] = $loguser['id'];
	}
	
	$isadmin = has_perm('admin-actions');
	if (!$isadmin && $_GET['id'] != $loguser['id']) {
		errorpage("You aren't allowed to do this!");
	}
	
	$user = $sql->fetchq("SELECT $userfields FROM users u WHERE id = {$_GET['id']}");
	if (!$user) {
		errorpage("This user doesn't exist.");
	}
	
	
	$new_default = isset($_POST['newdef']);//(filter_int($_FILES['new0']['size']);
	if ($new_default || isset($_POST['newav'])) {//filter_int($_FILES['newfile']['size'])){
		/*
			Add a new avatar
		*/
		check_token($_POST['auth']);
		
		if ($new_default) {
			// Default avatar?
			$file     = $_FILES['new0'];
			$title    = "Default";
			$newid    = 0;
			$hidden   = 0;
		} else {
			$file = $_FILES['newfile'];
			
			// No blank titles
			$title = filter_string($_POST['newtitle'], true);
			if (!$title) errorpage("The avatar title cannot be blank.");
			
			// ID to be used for the image name
			$newid = (int) $sql->resultq("SELECT MAX(file) FROM user_avatars WHERE user = {$_GET['id']}");
			$newid++; // Offset by 1 (incidentally this also leaves out value 0; which is reserved to the default avatar)

			// filter that title
			$title = xssfilters($title);
			
			$hidden = filter_int($_POST['newhidden']);
		}
			
		$res = imageupload(
			$file, // the file
			$config['max-avatar-size-bytes'], // some
			$config['max-avatar-size-x'], // image
			$config['max-avatar-size-y'], // limits
			avatarpath($_GET['id'], $newid), // image path
			[$_GET['id'], $newid, $title, $hidden] // db data (user id, file id, title,...)
		);
		
		if ($res) {
			msg_holder::set_cookie("Avatar '<i>".htmlspecialchars($title)."</i>' uploaded!".DELAYED_CRAP);
			return header("Location: editavatars.php?id={$_GET['id']}");
		} else {
			errorpage("Could not add the avatar.");
		}
	}
	else if (isset($_POST["change0"])){
		/*
			Change the default avatar
		*/
		check_token($_POST['auth']);
		
		$res = imageupload(
			$_FILES['new0'],               // the file
			$config['max-avatar-size-bytes'], // some
			$config['max-avatar-size-x'],     // image
			$config['max-avatar-size-y'],     // limits
			avatarpath($_GET['id'], 0)       // image path
		);
		
		
		if ($res){
			msg_holder::set_cookie("Default avatar updated!".DELAYED_CRAP);
			return header("Location: editavatars.php?id={$_GET['id']}");
		} else {
			errorpage("Could not update the avatar.");
		}
	}

	// Loop through the avatar and check if we want to change them
	$usermood = getavatars($_GET['id'], true);
	foreach ($usermood as $file => $data) {
		
		if (isset($_POST["del{$file}"])){
			/*
				Delete an avatar (including the default one)
				Removes the entry from the database and deletes the image
			*/
			check_token($_POST['auth']);

			$sql->query("DELETE from user_avatars WHERE user = {$_GET['id']} AND file = {$file}");
			unlink(avatarpath($_GET['id'], $file));
			msg_holder::set_cookie("Avatar '<i>{{$data['title']}}</i>' deleted!");
			return header("Location: editavatars.php?id={$_GET['id']}");
		}	
		else if (isset($_POST["change{$file}"]) && $file > 0){
			/*
				Change the non-default avatars ($i['file'] > 0)
			*/
			check_token($_POST['auth']);
			
			$new_title = filter_string($_POST["ren{$file}"], true);
			$new_hidden = filter_int($_POST["hid{$file}"]);
			
			if ($new_title) {
				$new_title = xssfilters($new_title);
				$sql->queryp("UPDATE user_avatars SET title = ?, hidden = ? WHERE user = {$_GET['id']} AND file = {$file}", [$new_title, $new_hidden]);
			} else {
				$new_title = $data['title']; // if blank don't change the name
			}
			
			// Conditional in case you just want to update the avatar name
			if (filter_int($_FILES["new{$file}"]['size'])){
				
				$res = imageupload(
					$_FILES["new{$file}"],            // the file
					$config['max-avatar-size-bytes'], // some
					$config['max-avatar-size-x'],     // image
					$config['max-avatar-size-y'],     // limits
					avatarpath($_GET['id'], $file)    // image path
				);
				
				if ($res){
					msg_holder::set_cookie("Avatar '".htmlspecialchars($new_title)."' updated!".DELAYED_CRAP);
				} else {
					errorpage("Could not upload the changed avatar.");
				}
				
			} else {
				msg_holder::set_cookie("Avatar info for '".htmlspecialchars($new_title)."' updated!".DELAYED_CRAP);
			}
			
			return header("Location: editavatars.php?id={$_GET['id']}");
		}
	}

	$maxsize_txt = "<small>Max size: {$config['max-avatar-size-x']}x{$config['max-avatar-size-y']} | ".($config['max-avatar-size-bytes']/1024)." KB</small>";
	
	// Always show default avatar table, so you can upload one
	if (isset($usermood[0])) {
		$txt = avbox($_GET['id'], 0, dummy_avatar("[Default avatar]", 0), AVBOX_DEFAULT);
	} else {
		$txt = avbox($_GET['id'], 0, dummy_avatar("[Default avatar]", 0), AVBOX_DEFAULT | AVBOX_UPLOAD);
	}
	
	// Remove the default avatar
	unset($usermood[0]);
	
	// we reuse $usermood from earlier
	foreach ($usermood as $file => $data) {
		$txt .= avbox($_GET['id'], $file, $data);
	}
	
	
	pageheader("Edit avatars");
	
	
	?>
	<!-- extra global css for avatar tables -->
	<style type='text/css'>
		.avatarbox{
			background-repeat: no-repeat;
			background-position: center;
			min-width: <?= $config['max-avatar-size-x'] ?>px;
			height: <?= $config['max-avatar-size-y'] ?>px;
		}
		.sect{width: <?= $config['max-avatar-size-y'] + 150 ?>px !important}
		.sizex{width: <?= $config['max-avatar-size-x'] ?>px}
	</style>
	<form method='POST' action='editavatars.php?id=<?= $_GET['id'] ?>' enctype='multipart/form-data'>
	<input type='hidden' name='auth' value='<?=generate_token()?>'>
	
	<table class='table'>
		<tr>
			<td class='tdbgh center b'>
				User avatars for <?= getuserlink(NULL, $_GET['id'], '', true) ?>
			</td>
		</tr>
	</table>
	
	<?= msg_holder::get_message() ?>
	<center>
	<?php echo $txt ?>
	
	
	<table class='table sect'>
	
		<tr><td class='tdbgh center b' colspan=2>New Avatar</td></tr>
		
		<tr>
			<td class='tdbg1 b center' style='min-width: 100px'>Title</td>
			<td class='tdbg2'><input type='text' name='newtitle'></td>
		</tr>
		<tr>
			<td class='tdbg1 b center'>Options</td>
			<td class='tdbg2'>
				<input type='checkbox' name='newhidden' value='1'>
				<label for='newhidden'>Hidden</label>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 b center'>Upload</td>
			<td class='tdbg2'>
				<input type='hidden' name='MAX_FILE_SIZE' value='<?php echo $config['max-avatar-size-bytes'] ?>'>
				<input name='newfile' type='file'>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center' colspan=2>
				<?php echo $maxsize_txt ?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbgc center' colspan=2>
				<input type='submit' name='newav' value='Upload'>
			</td>
		</tr>
		
	</table>
	</center>
	
	</form>	
	<?php
	pagefooter();
	
	function avbox($user, $file, $data, $options = 0) {
		global $config;
		
		$commands = "<input type='submit' name='change{$file}' value='Update'>&nbsp;-".
			            "&nbsp;<input type='submit' name='del{$file}' value='Delete'>";
						
		$upload_title = "Reupload";
						
		if ($options & AVBOX_DEFAULT) {
			
			if ($options & AVBOX_UPLOAD) {
				// Uploading the default image only gives out a single button
				$upload_title = "Upload";
				$commands = "<input type='submit' name='newdef' value='Upload'>";
			}
			
			$hidden_txt = "-";
			$name_title = "Name";
			
			// not overkill for fields not intended to be filled in
			$misc_readonly = " readonly";
			$misc_hidden   = " style='visibility:hidden'";
		} else {
			$hidden_txt = "";
			
			$name_title    = "Rename";
			$misc_readonly = $misc_hidden = "";
		}

		
		$data['title'] = htmlspecialchars($data['title']);
		$sel_hidden = $data['hidden'] ? "checked" : "";
		return "
			<table class='table sect' style='display: inline-block;'>
			
				<!-- <tr><td class='tdbgh center' colspan=2>{$data['title']}</td></tr> -->
				
				<tr>
					<td class='tdbg2 avatarbox' style='background-image: url(".avatarpath($user, $file).");' colspan=2></td>
				</tr>
				
				<tr>
					<td class='tdbg1 b center' style='min-width: 100px'>{$name_title}</td>
					<td class='tdbg2'><input type='text' name='ren{$file}' class='sizex' {$misc_readonly} value=\"{$data['title']}\"></td>
				</tr>
				
				<tr>
					<td class='tdbg1 b center'>Options</td>
					<td class='tdbg2'>
						<input type='checkbox' name='hid{$file}' value='1' {$sel_hidden}{$misc_readonly}{$misc_hidden}>
						<label for='newhidden'{$misc_readonly}{$misc_hidden}>Hidden</label>
					</td>
				</tr>
				
				<tr>
					<td class='tdbg1 b center'>{$upload_title}</td>
					<td class='tdbg2 w'>
						<input type='hidden' name='MAX_FILE_SIZE' value='{$config['max-avatar-size-bytes']}'>
						<input name='new{$file}' type='file'>
					</td>
				</tr>
				
				<tr><td class='tdbgc center' colspan=2>{$commands}</td></tr>
					
			</table>
		";
	}