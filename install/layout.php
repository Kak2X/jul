<?php

const BTN_PREV = 0b1;
const BTN_NEXT = 0b10;

function setupheader($title, $formlink = "?", $firststep = 0) {
	global $buttons;
	
	// Page handler
	$_POST['step']       = filter_int($_POST['step']);
	$_POST['stepcmd']    = filter_string($_POST['stepcmd']);
	$_POST['__chconfig'] = filter_bool($_POST['__chconfig']);
	
	if ($_POST['stepcmd'] == 'Next')
		$_POST['step']++;
	else
		$_POST['step']--;

	$firststep = $_POST['__chconfig'] ? STEP_CONFIG : STEP_INTRO;

		
	// Every page but the first one has a back button
	if ($_POST['step'] <= $firststep)  {
		$_POST['step'] = $firststep;
		$buttons = BTN_NEXT;
	} else {
		$buttons = BTN_NEXT | BTN_PREV;
	}

	?><!doctype html>
	<html>
		<head>
			<title><?= $title ?></title>
			<link rel="stylesheet" href="../schemes/base.css" type="text/css">
			<link rel="stylesheet" href="../schemes/spec-install.css" type="text/css">
		</head>
		<body>
		<form method="POST" action="<?= $formlink ?>">
		<center>
			<table class="container">
				<tr><td class="tdbgh b"><?= $title ?></td></tr>
				<tr>
					<td class="table">
		<?php
		

	print savevars($_POST);
}

function setupfooter($footer, $buttons) {
	// Displayed buttons
	if (is_string($buttons)) {
		print $btnoverride;
	} else {
		$btnl = array();
		if ($buttons & BTN_NEXT) $btnl[] = "<input type='submit' class='submit' name='stepcmd' value='Next'>";
		if ($buttons & BTN_PREV) $btnl[] = "<input type='submit' class='submit' name='stepcmd' value='Back'>";

		print "<br>".implode('&nbsp;-&nbsp;', $btnl);
	}
		
						?>
						<input type="hidden" name="step" value="<?= $_POST['step'] ?>">
					</td>
				</tr>
				<tr>
					<td class="tdbgh">
						<?= $footer ?>
					</td>
				</tr>
			</table>
		</center>
		</form>
		</body>
	</html><?php
	die;
}