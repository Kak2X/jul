<?php

	require "lib/function.php";
	admincheck();

	// Prepare the extension name
	$_GET['extfile'] = filter_string($_GET['extfile']);
	if (!$_GET['extfile']) { 
		// An extension name is expected. Redirect out if missing.
		die(header("Location: admin-extensions.php"));
	}
	if (!ext_valid($_GET['extfile']))
		errorpage("The specified extension does not exist or is invalid.");

	$info = ext_read_metadata($_GET['extfile']);
	if (filter_bool($info['noconfig'])) {
		errorpage("This feature has no configurable settings.", "admin-extensions.php", "the extensions manager");
	}
	
	if (isset($_POST['submit']) && isset($_POST['config'])) {
		check_token($_POST['auth']);
		ext_write_config($_GET['extfile']);
		errorpage("Settings saved!", "admin-extensions.php", "the extensions manager");
	}
	
	
	$xname = htmlspecialchars($info['name']);
	
	pageheader("{$xname}: Settings");
	print adminlinkbar("admin-extensions.php");

	$links = array(
		["Optional features", "admin-extensions.php"],
		[$info['name'], null]
	);
	$barlinks = dobreadcrumbs($links); 
?>
<form method="POST" action="?extfile=<?= $_GET['extfile'] ?>">
	<?= $barlinks ?>

	<?= ext_config_layout($_GET['extfile']) ?>
<table class="table">
	<tr>
		<td class="tdbg2 center">
			<input type="submit" name="submit" value="Save settings"> &mdash; <a href="admin-extensions.php" class="button">Cancel</a>
			<?= auth_tag() ?>
		</td>
	</tr>
</table>
</form>
<?php

	pagefooter();