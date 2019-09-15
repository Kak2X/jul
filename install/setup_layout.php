<?php

function setupheader($title) {
	
	
?><!doctype html>
<html>
	<head>
		<title><?= $title ?></title>
		<link rel="stylesheet" href="../schemes/base.css" type="text/css">
		<link rel="stylesheet" href="../schemes/spec-install.css" type="text/css">
		<link rel="stylesheet" href="base.css" type="text/css">
	</head>
	<body>
	<form method="POST" action="?">
	<table class="table main-window">
		<tr><td class="tdbgh center b" colspan="2"><?= $title ?></td></tr>
		<tr>
			<td class="tdbg1">

<?php
}

function setupfooter($buttons = 0) {
	
	if (is_string($buttons)) {
		print $btnoverride;
	} else {
		$btnl = "";
		if ($buttons & BTN_NEXT)
			$btnl .= "<button type='submit' name='step' value='".($_POST['step'] + 1)."' style='left: 0px'>Next</button>";
		else
			$btnl .= "<button type='button' disabled style='left: 0px'>Next</button>";
		
		if ($buttons & BTN_PREV)
			$btnl .= "<button type='submit' name='step' value='".($_POST['step'] - 1)."' style='right: 0px'>Back</button>";
		else
			$btnl .= "<button type='button' disabled style='right: 0px'>Back</button>";
	
		print "<div class='btn-area'>{$btnl}</div>";
	}
?>					
			</td>
		</tr>
		<tr>
			<td class="tdbgh center b">
				Acmlmboard Installer 2.0 (14-09-2019)
			</td>
		</tr>
<?php if (SETUP_DEBUG) { ?>
		<tr>
			<td class="tdbg2">
				Debug: <a href="?unban">Remove IP bans</a> - <a href="?logout">Log out</a>
			</td>
		</tr>
<?php } ?>
	</table>
	</form>
	</body>
</html>
<?php
	die;
}
