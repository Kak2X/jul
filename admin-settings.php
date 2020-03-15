<?php

	require "lib/function.php";
	admincheck();

	// Prepare the extension name
	$_POST['extfile'] = filter_string($_POST['extfile']);
	if (!$_POST['extfile']) {
		// An extension name is expected. Redirect out if missing.
		die(header("Location: admin-extensions.php"));
	}
	if (!ext_valid($_POST['extfile']))
		errorpage("The specified extension does not exist or is invalid.");

	if (isset($_POST['submit']) && isset($_POST['config'])) {
		ext_write_config($_POST['extfile']);
		errorpage("Settings saved!", "admin-extensions.php", "the extensions manager");
	}
	
	
	pageheader("Settings");
	print adminlinkbar("admin-extensions.php");


?>
<form method="POST" action="admin-settings.php">
<input type="hidden" name="extfile" value="<?= $_POST['extfile'] ?>">
<table class="table">
	<tr>
		<td class="tdbgh center b" colspan="2">
			Settings for '<?= $_POST['extfile'] ?>'
		</td>
	</tr>
	<?php
		// Because the settings page json is loaded separately and it's not done anywhere else
		// it's possible to afford doing slow shit for a prettier json format (I hope)
	
		print ext_config_layout($_POST['extfile']);
	/*
		// TODO: find a way to merge this with the way it's done in the installer
		foreach ( as $catData) {
			print "<tr><td class='tdbgc center b' colspan='2'>{$catData['title']}</td></tr>";
			foreach ($catData['options'] as $key => $option) {

				$settingHtml = "";
				$fieldDesc = (isset($option['inlineDesc']) ? " <span class='fonts'>{$option['inlineDesc']}</span>" : "");
				$style = isset($option['style']) ? " style=\"{$option['style']}\"" : "";

				switch ($option['type']) {
					case 'text':
						$settingHtml .= "<input type='text' name='conf[{$key}]' value=\"".htmlspecialchars($conf[$key])."\"{$style}>";
						break;
					case 'textbox':
						$rows  = isset($option['rows'])  ? $option['rows']  : 10;
						$cols  = isset($option['cols'])  ? $option['cols']  : 80;
						if (!isset($option['style'])) $style = " style='resize: vertical'";
						$settingHtml .= "<textarea name='conf[{$key}]' rows='{$rows}' cols='{$cols}'{$style}>".htmlspecialchars($conf[$key])."</textarea>";
						break;
					case 'int':
						$settingHtml .= "<input type='text' name='conf[{$key}]' value=\"".((int)$conf[$key])."\" class='right'{$style}>";
						break;
					case 'permission':
						$settingHtml .= power_select("conf[{$key}]", $conf[$key]);
						break;
					default:
						break;
				}

				print "
				<tr>
					<td class='tdbg1 center b nobr' style='width: 250px'>
						{$option['title']}:
						".($option['description'] ? "<div class='fonts'>{$option['description']}</div>" : "")."
					</td>
					<td class='tdbg2'>
						{$settingHtml}{$fieldDesc}
					</td>
				</tr>";
			}
		}*/
	?>
	<tr>
		<td class="tdbg1"></td>
		<td class="tdbg2"><input type="submit" name="submit" value="Save settings"></td>
	</tr>
</table>
</form>
<?php

	pagefooter();