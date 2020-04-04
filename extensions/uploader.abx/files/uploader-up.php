<?php

	require "lib/function.php";
	require "lib/uploader_function.php";
	
	$_GET['cat'] = filter_int($_GET['cat']);
	
	if (!$loguser['id'])
		errorpage("You need to be logged in to use this feature.");
	if ($loguser['uploader_locked'] || $banned)
		errorpage("You aren't allowed to upload files on the uploader.");
	
	load_uploader_category($_GET['cat']);
	if ($loguser['id'] != $cat['user'] && $loguser['powerlevel'] < $cat['minpowerupload'])
		errorpage("You aren't allowed to upload files in this folder.");
	
	//--
	$user = uploader_load_user($_GET['user']);
	
	if (isset($_POST['submit'])) {
		check_token($_POST['auth']);
		
		if (!isset($_FILES['up']) || !is_array($_FILES['up']))
			errorpage("No <small>file specified</small>.");
		
		$opts = array(
			'filename' => filter_string($_FILES['up']['name']),
			'desc'     => filter_string($_POST['desc']),
			'private'  => filter_int($_POST['private']),
			'cat'      => $_GET['cat'],
		);
		
		// You really aren't
		validate_file_options($cat, $opts);
		
		if (!upload_file($_FILES['up'], $loguser, $opts))
			errorpage("The file could not be uploaded.");
		
		die(header("Location: uploader.php{$baseparams}&cat={$_GET['cat']}"));
	}
	
	pageheader("Uploader");
	
	$perms = get_category_perms($cat);
	$links = uploader_breadcrumbs_links($cat, NULL, UBL_USERCAT);
	$breadcrumbs = dobreadcrumbs($links); 
	
?>
<form method="POST" action="<?=actionlink(null, "{$baseparams}&cat={$_GET['cat']}")?>" enctype="multipart/form-data">
<table class="table">
	<tr><td class="tdbgh center b" colspan="2">Uploading a new file to "<?= htmlspecialchars($cat['title']) ?>"</td></tr>
	<tr>
		<td class="tdbg1 center b">File:</td>
		<td class="tdbg2">
			<input type="hidden" name="MAX_FILE_SIZE" value="<?= $xconf['max-file-size'] ?>">
			<input name="up" type="file"> <span class="fonts">Max size: <?= sizeunits($xconf['max-file-size']) ?></span>
		</td>
	</tr>
	<tr>
		<td class="tdbg1 center b">Description:</td>
		<td class="tdbg2"><input type="text" name="desc" value="" style="width: 400px"></td>
	</tr>
<?php if ($perms['allow-private-files']) { ?>
	<tr>
		<td class="tdbg1 center b">Options:</td>
		<td class="tdbg2">
			<label class="nobr"><input type="checkbox" name="private" value="1"> Private Upload</label>
		</td>
	</tr>
<?php } ?>
	<tr>
		<td class="tdbg1 center b"><?= auth_tag() ?></td>
		<td class="tdbg2"><input type="submit" name="submit" value="Upload file"></td>
	</tr>
</table>
</form>
<?php
	
	pagefooter();