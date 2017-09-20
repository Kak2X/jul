<?php
	require 'lib/function.php';
	
	if ($miscdata['private'] != 2) {
		header("Location: login.php");
		die;
	}
	
	// Bots don't need to be on this page
	$meta['noindex'] = true;

	$_POST['username'] = filter_string($_POST['username'], true);
	$_POST['userpass'] = filter_string($_POST['userpass']);
	$_POST['verify']   = filter_int($_POST['verify']);
	
	$_POST['action']   = filter_string($_POST['action']);
	
	// Keeping it in the style of boardc's hidden login page
	if (isset($_POST['submit'])) {
		check_token($_POST['auth'], TOKEN_LOGIN);
		switch (login($_POST['username'], $_POST['userpass'], $_POST['verify'])) {
			case 1:
				die("OK!<br>".redirect('index.php','the board',0));
			case -1:
				die("No username.");
			case -2:
				die("Bad credentials.");
		}		
	} else {
		$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
		for ($i = 4; $i > 0; --$i) {
			$verifyoptext[$i] = "(".implode('.', $ipaddr).")";
			$ipaddr[$i-1]       = 'xxx';
		}
?><!doctype html><style>body{background: #000; color: #fff}</style><pre>
<form method='POST' action='?'>
<table>
	<tr>
		<td>User name:</td>
		<td><input type='text' name='username' MAXLENGTH=25 style='width:280px;'></td>
	</tr>
	<tr>
		<td>Password:</td>
		<td><input type='password' name='userpass' MAXLENGTH=64 style='width:180px;'></td>
	</tr>
	<tr>
		<td>IP Verification:</td>
		<td><select name=verify>
				<option selected value=0>Don't use</option>
				<option value=1> /8 <?=$verifyoptext[1]?></option>
				<option value=2>/16 <?=$verifyoptext[2]?></option>
				<option value=3>/24 <?=$verifyoptext[3]?></option>
				<option value=4>/32 <?=$verifyoptext[4]?></option>
			</select></td>
	</tr>
	<tr>
		<td>&nbsp;</td>
		<td><input type='submit' class=submit name=submit VALUE='Login'><input type='hidden' name='auth' value='<?=generate_token(TOKEN_LOGIN)?>'></td>
	</tr>
</table>

</form>
<?php
	}