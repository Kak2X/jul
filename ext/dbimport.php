<?php
	const _MAX_SIZE_MAIN = 10485760;
	chdir("..");
	require "lib/config.php";
	
	if (!$config['allow-debug-dump']) {
		die("Disabled.");
	}
	
	if (isset($_POST['submit'])) {
		$filepath = isset($_FILES['mainfile']['tmp_name']) ? $_FILES['mainfile']['tmp_name'] : "";
		if (!$filepath) {
			die("No file specified.");
		} else {
			set_time_limit(0);
			system("/xampp/mysql/bin/mysql -u {$sqluser} ".($sqlpass ? "-p{$sqlpass} " : "")."{$dbname} < \"{$filepath}\"");
			print "Dump imported.<br/>";
		}
	}
?>
<form method="POST" action="?" enctype="multipart/form-data">
	<input type="hidden" name="MAX_FILE_SIZE" value="<?= _MAX_SIZE_MAIN ?>">
	<input type="file" name="mainfile" accept=".sql">
	<small>Max size: <?= _MAX_SIZE_MAIN ?></small>
	<input type="submit" name="submit" value="Import."/>
</form>