<?php

require "lib/common.php";
require "lib/uploader_function.php";

$_GET['f']      = filter_string($_GET['f']);
$_GET['action'] = filter_string($_GET['action']);

$error = NULL;
if (!$loguser['id'])
	$error = "You need to be logged in to use this feature.";
else if ($loguser['uploader_locked'] || $loguser['powerlevel'] < 0)
	$error = "You aren't allowed to upload files on the uploader.";
else if (!$_GET['f'])
	$error = "No file was specified.";

if ($error)
	errorpage($error, actionlink("uploader.php"), "the file uploader");

load_uploader_file($_GET['f']);

if (!can_manage_category($cat) && !can_edit_file($file))
	errorpage("You aren't allowed to edit this file.", actionlink("uploader.php"), "the file uploader");

$baseparams = "?action={$_GET['action']}&f={$_GET['f']}";
	

	
	
if ($_GET['action'] == 'edit') {
	if (!$xconf['allow-file-edit'])
		admincheck();

	if (isset($_POST['submit'])) {
		check_token($_POST['auth']);
		
		$_POST['cat'] = filter_int($_POST['cat']);
		$opts = array(
			'filename' => $file['filename'],
			'desc'     => filter_string($_POST['desc']),
			'private'  => filter_int($_POST['private']),
			'cat'      => $_POST['cat'],
			'override' => false,
		);
		if ($isadmin) {
			$opts['mime'] = filter_string($_POST['mime']);
			$opts['is_image'] = filter_int($_POST['is_image']);
			$opts['filename'] = filter_string($_POST['filename']);
			$opts['override'] = filter_bool($_POST['override']);
		}
		
		// Category validation 
		if ($_POST['cat'] != $cat['id']) {
			load_uploader_category($_POST['cat']);
			if (!can_upload_in_category($cat))
				errorpage("You aren't allowed to upload files in this folder.");
			// The warning is not applicable as you would lose $_FILES in the process
			// and that's bad
			/*
			// give out warning if you aren't explicitly disabling the private option
			if (!$cat['user'] && $file['private'] && $opts['private']) {
				$message = "You are about to move a private file in a shared category.<br>".
						   "Because private files are not allowed in shared categories,<br> continuing will <b>make the file public</b>.<br><br>".
						   "Are you sure you want to continue?".save_vars($_POST);
				$form_link = "{$scriptname}{$baseparams}";
				$buttons   = array(
					0 => ["Yes"],
					1 => ["No", $baseparams]
				);
				confirmpage($message, $form_link, $buttons);
			}
			*/
		} else {
			if (!$cat['user'] && $opts['private'])
				$opts['private'] = 0;
		}
		
		if (!reupload_file($_FILES['up'], $file, $loguser, $opts))
			errorpage("An error occurred while reuploading the file.");
		
		die(header("Location: uploader-cat.php?cat={$_POST['cat']}"));
	}
	
	$links = uploader_breadcrumbs_links($cat, null, [["Editing file \"{$file['filename']}\"", null]]);
	$breadcrumbs = dobreadcrumbs($links); 
	pageheader("Uploader");
	
	print $breadcrumbs;
?>
<form method="POST" action="<?=actionlink(null, $baseparams)?>" enctype="multipart/form-data">
<table class="table">
	<tr><td class="tdbgh center b" colspan="2">Editing file '<?= htmlspecialchars($file['filename']) ?>'</td></tr>
	<tr>
		<td class="tdbg1 center b">Reupload file:</td>
		<td class="tdbg2">
			<input type="hidden" name="MAX_FILE_SIZE" value="<?= $xconf['max-file-size'] ?>">
			<input name="up" type="file"> <span class="fonts">
				Select a new file if you want to replace the old one.  Max size: <?= sizeunits($xconf['max-file-size']) ?>.
<?php if ($isadmin) { ?>
				<br/>Doing this will override the "Mime type" and "Image file" options (recommended).
				<br/>If you want to change those options while reuploading the file, mark this checkbox ->
				<input type="checkbox" name="override" value="1">
<?php } ?>
			</span>
		</td>
	</tr>
	<tr>
		<td class="tdbg1 center b">Description:</td>
		<td class="tdbg2"><input type="text" name="desc" style="width: 400px" value="<?= htmlspecialchars($file['description']) ?>"></td>
	</tr>
	<tr>
		<td class="tdbg1 center b">Folder:</td>
		<td class="tdbg2"><?= uploader_cat_select('cat', $file['cat'], UCS_DEFAULT | UCS_UPLOADPERM) ?></td>
	</tr>
<?php if ($isadmin) { ?>
	<tr>
		<td class="tdbg1 center b i">File name:</td>
		<td class="tdbg2"><input type="text" name="filename" style="width: 400px" value="<?= htmlspecialchars($file['filename']) ?>"></td>
	</tr>
	<tr>
		<td class="tdbg1 center b i">Mime type:</td>
		<td class="tdbg2"><?= mime_select('mime', $file['mime']) ?></td>
	</tr>
<?php } ?>
	<tr id="opts">
		<td class="tdbg1 center b">Options:</td>
		<td class="tdbg2">
			<label class="nobr"><input type="checkbox" id="private" name="private" value="1"<?= $file['private'] ? " checked" : "" ?>> Private Upload</label> 
			<span class="fonts" id="optshlp">(not allowed if the file is in a shared folder)</span>
<?php if ($isadmin) {?>
			<label class="nobr i"><input type="checkbox" name="is_image" value="1"<?= $file['is_image'] ? " checked" : "" ?>> Image file</label>
<?php } ?>
		</td>
	</tr>
	<tr>
		<td class="tdbg1 center b"><?= auth_tag() ?></td>
		<td class="tdbg2"><input type="submit" name="submit" value="Save changes"></td>
	</tr>
</table>
</form>

<?php /* 
	Because private files are disallowed in shared categories, the option disables itself when a shared category is selected.
	If JS is disabled, we instead show a message stating that the private file option won't work on shared categories.
*/ ?>
<script type="text/javascript">
	var priv = document.getElementById("private");
	var optshlp = document.getElementById("optshlp");
	var cat = document.getElementsByName("cat")[0];
	cat.addEventListener('change', function() {
		_togglePrivate((this.options[this.selectedIndex].dataset.allowprivate > 0));
	});
	function _togglePrivate(enabled) {
		private.disabled = !enabled;
		optshlp.style.display = (enabled ? "none" : "inline");
	}
	_togglePrivate(<?=($cat['user'] ? "true" : "false")?>);
</script>
<?php
	print $breadcrumbs;
}
else if ($_GET['action'] == 'delete') {
	$filename = htmlspecialchars(trim($file['filename']));
	
	if (confirmed($msgkey = 'del-file')) {
		delete_upload($file);	
		errorpage("The file '{$filename}' has been deleted!", actionlink("uploader-cat.php?cat={$file['cat']}"), "the uploader");
	}
	
	$title     = "File deletion";
	$message   = "Are you sure you want to <b>delete</b> the file '<tt>{$filename}</tt>'?";
	$form_link = actionlink(null, $baseparams);
	$buttons   = array(
		[BTN_SUBMIT, "Delete file"],
		[BTN_URL   , "Cancel", actionlink("uploader-cat.php?cat={$file['cat']}")]
	);
	
	confirm_message($msgkey, $message, $title, $form_link, $buttons);
}
	pagefooter();