<?php
	if (isset($_POST['settings'])) {
		require "admin-settings.php";
		die;
	}

	require "lib/function.php";
	admincheck();
	
	if (isset($_POST['action'])) {
		// Reject missing or bad extension file
		$_POST['extfile'] = filter_string($_POST['extfile']);
		if (!ext_valid($_POST['extfile'])) {
			errorpage("Could not complete the requested action for the extenson \"{$_POST['extfile']}\".<br><br>This is not a valid extension.");
		}

		if (confirmed($msgkey = 'xtman')) {
			$redirtext = "the extension management";
			switch ($_POST['action']) {
				case "disable":
					$res = ext_disable($_POST['extfile']);
					errorpage("Extension '{$_POST['extfile']}' disabled.".($res > 1 ? "<br/>Other ".($res-1)." extensions were disabled due to dependencies." : ""), "admin-extensions.php", $redirtext);

				case "enable":
					if (($res = ext_enable($_POST['extfile'])) !== -1)
						$message = "The extension '{$_POST['extfile']}' has been enabled.";
					else
						$message = "Failed to enable the extension '{$_POST['extfile']}'.<br>Chances are, the required dependencies are missing.";
					errorpage($message, "admin-extensions.php", $redirtext);

				/*case "uninstall":
					if (NO_UNINSTALL)
						errorpage("Uninstall extensions is disabled in this version.");
					$res = $_POST['extfile']::Uninstall();
					errorpage("Extension '{$_POST['extfile']}' removed.".($res > 1 ? "<br/>Other ".($res-1)." extensions were disabled due to dependencies." : ""), "admin-extensions.php", $redirtext);
				*/
			}
		}
		
		// the wonders of using the label as key
		$title   = "Warning";
		$message = "Are you sure you want to {$_POST['action']} the extension '{$_POST['extfile']}'?".
		"<input type='hidden' name='action' value=\"".htmlspecialchars($_POST['action'])."\">".
		"<input type='hidden' name='extfile' value=\"".htmlspecialchars($_POST['extfile'])."\">";
		$form_link = $scriptname;
		$buttons   = array(
			[BTN_SUBMIT, "Yes"],
			[BTN_URL, "No", $scriptname]
		);
		confirm_message($msgkey, $message, $title, $form_link, $buttons);
	}


	pageheader("Extensions");
	print adminlinkbar();
	
	$links = array(
		["Optional features", null],
	);
	$barlinks = dobreadcrumbs($links); 

	// Get all extensions installed, including disabled ones
	$extensions = ext_list();

?>
	<style>
		.installCtrl {
			float: right;
			text-align: right;
			padding-right: 10px; /* 10px 10px 0px; */
		}
		.settingsCtrl {
			padding-top: 10px;
		}
	</style>	
	
	<?= $barlinks ?>
<?php
	foreach ($extensions as $ext) {
?>
	<form method="POST" action="admin-extensions.php">
	<input type="hidden" name="extfile" value="<?= $ext['file'] ?>">

	<table class="table">
		<thead>
			<tr>
				<td class="tdbgh b" colspan="2">
					<?= $ext['name'].($ext['enabled'] ? "" : "<i>(disabled)</i>") ?> v<?=$ext['version']?>
				</td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td class="tdbg1 vatop" style="width: 1px">
					<?= ($ext['image'] ? "<img src=\"extensions/{$ext['file']}.abx/{$ext['image']}\">" : "") ?>
				</td>
				<td class="tdbg2">
					<?php /*
					<span class="installCtrl">
						<input type="submit" name="action" value="Uninstall" disabled style='background: #333; color: #aaa'>
						<div class="settingsCtrl">
							<input type="submit" name="settings" value="Settings">
						</div>
					</span>
					<span class="installCtrl">
					<?= 
						$ext['enabled']
						? '<button type="submit" name="action" value="disable">Enable</button>'
						: '<button type="submit" name="action" value="enable">Disable</button>'
					?>
					</span>*/?>	
					
					<span class="installCtrl">
						<?= 
						$ext['enabled']
						? '<button type="submit" name="action" value="disable">Disable</button>'
						: '<button type="submit" name="action" value="enable">Enable</button>'
						?>
<?php if (!filter_bool($ext['noconfig'])) { ?>
						<div class="settingsCtrl">
							<input type="submit" name="settings" value="Settings">
						</div>
<?php } ?>
					</span>
					
					<?= $ext['description'] ?><br>
					<br>
					<span class="fonts">
						By: <?= $ext['author'] . ($ext['enabled'] ? "<br/>Enabled on: {$ext['enableDate']}" : "") ?><br/>
						Version: <?=$ext['version']?> (<?=$ext['date']?>)
					</span>
				</td>
			</tr>
		</tbody>
	</table>
	</form>
<?php
	}


	pagefooter();