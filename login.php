<?php
	require 'lib/function.php';

	// Bots don't need to be on this page
	$meta['noindex'] = true;

	$_POST['username'] = filter_string($_POST['username'], true);
	$_POST['userpass'] = filter_string($_POST['userpass']);
	$_POST['verify']   = filter_int($_POST['verify']);
	
	$_POST['action']   = filter_string($_POST['action']);
	

	
	if ($_POST['action']) {
		check_token($_POST['auth'], TOKEN_LOGIN);
	} else if ($miscdata['private'] == 2) {
		header("Location: login-h.php");
		die;
	}
	
	if ($_POST['action'] == 'login') {
		switch (login($_POST['username'], $_POST['userpass'], $_POST['verify'])) {
			case 1:
				$msg = "You are now logged in as ".trim($_POST['username']).".";
				break;
			case -1:
				$msg = "Couldn't login.  You didn't input a username.";
				break;
			case -2:
				$msg = "Couldn't login.  Either you didn't enter an existing username, or you haven't entered the right password for the username.";
				break;
		}
		
		$txt = "<tr><td class='tdbg1 center'>$msg<br>".redirect('index.php','the board',0)."</td></tr>";
		
	}
	elseif ($_POST['action'] == 'logout') {
		logout();
		$txt = "<tr><td class='tdbg1 center'> You are now logged out.<br>".redirect('index.php','the board',0)."</td></tr>";
	}
	elseif (!$_POST['action']) {
		$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
		for ($i = 4; $i > 0; --$i) {
			$verifyoptext[$i] = "(".implode('.', $ipaddr).")";
			$ipaddr[$i-1]       = 'xxx';
		}
		$txt = "
		<body onload='window.document.REPLIER.username.focus()'>
		
		<FORM ACTION=login.php NAME=REPLIER METHOD=POST>
			<tr>
				<td class='tdbgh center' width=150>&nbsp;</td>
				<td class='tdbgh center' width=40%>&nbsp</td>
				<td class='tdbgh center' width=150>&nbsp;</td>
				<td class='tdbgh center' width=40%>&nbsp;</td>
			</tr>
			<tr>
				<td class='tdbg1 center'><b>User name:</b></td>
				<td class='tdbg2'>
					<input type='text' name=username MAXLENGTH=25 style='width:280px;'>
				</td>
				
				<td class='tdbg1 center' rowspan=2><b>IP Verification:</b></td>
				<td class='tdbg2' rowspan=2>
					<select name=verify>
						<option selected value=0>Don't use</option>
						<option value=1> /8 $verifyoptext[1]</option>
						<option value=2>/16 $verifyoptext[2]</option>
						<option value=3>/24 $verifyoptext[3]</option>
						<option value=4>/32 $verifyoptext[4]</option>
					</select>
					<br>
					<small>
						You can require your IP address to match your current IP, to an extent, to remain logged in.
					</small>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'><b>Password:</b></td> 
				<td class='tdbg2'>
					<input type='password' name=userpass MAXLENGTH=64 style='width:180px;'>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center'>&nbsp;</td>
				<td class='tdbg2' colspan=3>
					<input type='hidden' name=action VALUE=login>
					<input type='submit' class=submit name=submit VALUE=Login>
					<input type='hidden' name='auth' value='".generate_token(TOKEN_LOGIN)."'>
				</td>
			</tr>
		</FORM>";
	}
	else { // Just what do you think you're doing
		$ban_reason = "Generic internet exploit searcher";
		$irc_msg = xk(7) ."Auto-banned asshole trying to be clever with the login form (action: " . xk(8) . $action . xk(7) . ") with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) .".";
		ipban($_SERVER['REMOTE_ADDR'], $ban_reason, $irc_msg);
		errorpage("Couldn't login.  Either you didn't enter an existing username, or you haven't entered the right password for the username.");
	}	

	pageheader();
	print "<table class='table'>{$txt}</table>";
	pagefooter();