<?php
	/*
		Backup management page.
		Works in conjunction with the backup script at 'ext/backup.php'
	*/
	
	require "lib/function.php";
	
	$windowtitle = "{$config['board-name']} -- Board Backups";
	
	if (!$sysadmin) {
		errorpage("Sorry, but no.");
	}
	
	const MANUAL_BACKUP = true; // Signal to the backup script to ignore the sapi restriction
	
	if (!file_exists($config['backup-folder'])) {
		errorpage("Cannot use the backup function.<br><br>The 'backup-folder' setting in the configuration file points to a nonexisting folder.");
	}

	if (isset($_POST['go'])){
		check_token($_POST['auth']);
		
		$act = filter_int($_POST['actid']);
		switch ($act) {
			case 0: // Download
				$download = filter_int($_POST['download']);
				if (!file_exists("{$config['backup-folder']}/{$download}.zip")){
					errorpage("Could not find {$download}.zip in the backups directory.");
				} else {
					// Clear out any previous state
					if (ob_get_level()) ob_end_clean();
					
					// Set the correct headers to make this file downloadable
					header("Pragma: public");
					header("Expires: 0");
					header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
					header("Cache-Control: public");
					header("Content-Description: File Transfer");
					header("Content-type: application/octet-stream");
					header("Content-Disposition: attachment; filename=\"".preg_replace("'[^a-z0-9]'si", "_", $config['board-name'])."_{$download}.zip\"");
					header("Content-Transfer-Encoding: binary");
					header("Content-Length: ".filesize("{$config['backup-folder']}/$download.zip"));
					readfile("{$config['backup-folder']}/$download.zip");
					die;
				}
			case 1: // Manually do backup
				// This should ideally execute the php shell, but oh well
				pageheader("{$windowtitle} - Manual backup");
				print adminlinkbar();
?>				<table class="table">
					<tr><td class="tdbgh center b">Backup log</td></tr>
					<tr>
						<td class="tdbgh" style='background: #000; color: #fff'>
<?php			chdir("ext");
				include "backup.php"; // will chdir("..");
?>						</td>
					</tr>
				</table>
<?php			break;
			case 2: // Delete old backups
				$allbackups = glob("{$config['backup-folder']}/*.zip");
				$ctime 		= time();
				foreach($allbackups as $f) {
					$x 			 = substr($f, 11, 8); //YYYYMMDD.ZIP
					$curdate 	 = mktime(0,0,0, substr($x,4,2), substr($x,6,2), substr($x,0,4));
					if ($ctime - $curdate > $config['backup-threshold'] * 86400) unlink($f);
				}				
				return header ("Location: admin-backup.php");
		}
		
	}
	else {
		pageheader($windowtitle);
		print adminlinkbar();
		
		$list = "<select name='download'><optgroup label='Current backups'>";
		// Find .zip files
		// these are sorted alphabetically, so we reverse the list to start from the newest entries
		$allbackups = array_reverse(glob("{$config['backup-folder']}/*.zip"));
		$optgroup 	= false;
		$ctime		= time();
		$oldbackups = 0;
		$offset		= strlen("{$config['backup-folder']}/");
		foreach($allbackups as $f) {
			$x 			 = substr($f, $offset, 8); //YYYYMMDD.ZIP
			$curdate 	 = mktime(0,0,0, substr($x,4,2), substr($x,6,2), substr($x,0,4));
			if (!$optgroup && $ctime - $curdate > $config['backup-threshold'] * 86400) {
				$optgroup = true;
				$list 	 .= "</optgroup><optgroup label='Old backups'>";
			}
			if ($optgroup) $oldbackups++;
			$list 		.= "<option value='$x'>".printdate($curdate, true)." (".number_format(filesize("{$config['backup-folder']}/$x.zip") / 1024, 1)." KB)</option>"; // To check if it exists, do a file_exists on <name>.zip
			
			$file[] = $x;
		}
		unset($allbackups);
		$list .= "</optgroup></select>";
		
		if (isset($file)) {
			$numfiles = count($file);
			$lastdate = mktime(0,0,0, substr($file[0],4,2), substr($file[0],6,2), substr($file[0],0,4));			
		} else {
			$numfiles = $lastdate = 0;
		}

			
			?>
		<br>
		<form method='POST' action='?'>
		<center>
		<table class='table' style='width: 600px'>
		
			<tr><td class='tdbgh center b'>Board backups</td></tr>
			
			<tr>
				<td class='tdbg1'><center>
					<br>There are <?= $numfiles ?> backup archive(s) saved in total<?= $lastdate ? ", last on ".printdate($lastdate, PRINT_DATE) : "" ?>
					<br><?= $oldbackups ? "$oldbackups of these backups are considered old (older than {$config['backup-threshold']} days)" : "" ?>
					<br>&nbsp;
					<table class="font">
						<tr><td class="center" colspan=2>What do you want to do?</td></tr>
<?php				if ($numfiles) { ?>
						<tr>
							<td><input type='radio' name='actid' value=0 checked></td>
							<td>Download this backup: <?= $list ?></td>
						</tr>
<?php				} ?>
						<tr>
							<td><input type='radio' name='actid' value=1></td>
							<td>Manually execute a backup (will overwrite today's backup)</td>
						</tr>
						<tr>
							<td><input type='radio' name='actid' value=2></td>
							<td>Delete old backups (older than <?= $config['backup-threshold'] ?> days)</td>
						</tr>
					</table>
					
					<br><input type='submit' class='submit' value='Execute action' name='go'>
					<br><?= auth_tag() ?>&nbsp;
				</center></td>
			</tr>
			
		</table>
		</center>
		</form>	
		<?php
	}
	
	pagefooter();