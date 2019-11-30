<?php

function setupheader($title) {
?><!doctype html>
<html>
	<head>
		<title><?= $title ?> -- Acmlmboard Installer</title>
		<link rel="stylesheet" href="../schemes/base.css" type="text/css">
		<link rel="stylesheet" href="../schemes/spec-install.css" type="text/css">
		<link rel="stylesheet" href="base.css" type="text/css">
	</head>
	<body>
	<form id="main-form" method="POST" action="?">
	<table class="table main-window">
		<tr><td class="tdbgh center b" id="page-title" colspan="2"><?= $title ?></td></tr>
		<tr>
			<td class="tdbg1">
				<div id="page-contents">
<?php
}

function setupfooter($buttons = 0) {
?>
				</div>
<?php
	if (is_string($buttons)) {
		print $btnoverride;
	} else {
		global $step;
		
		$btnl = "";
		if ($buttons & BTN_NEXT)
			$btnl .= "<button type='submit' name='step' value='".($step + 1)."' style='left: 0px'>Next</button>";
		else
			$btnl .= "<button type='button' disabled style='left: 0px'>Next</button>";
		
		if ($buttons & BTN_PREV)
			$btnl .= "<button type='submit' name='step' value='".($step - 1)."' style='right: 0px'>Back</button>";
		else
			$btnl .= "<button type='button' disabled style='right: 0px'>Back</button>";
	
		print "<div id='button-area' class='btn-area'>{$btnl}</div>";
	}
?>					
			</td>
		</tr>
		<tr>
			<td class="tdbgh center b">
				Acmlmboard Installer 2.0 (30-11-2019)
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
<!--	<script type="text/javascript" src="installer.js"></script> -->
	</body>
</html>
<?php
	die;
}
