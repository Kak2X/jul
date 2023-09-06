<?php
	require "lib/common.php";
	
	// Bots don't need to be on this page
	$meta['noindex'] = true;

	$username = filter_string($_POST['username'], true);
	$password = filter_string($_POST['userpass']);
	
	if (!$config['force-lastip-match']) {
		$verifyid = filter_int($_POST['verify']);
	} else {
		$verifyid = 4;
	}
	
	$_GET['action'] = filter_string($_GET['action']);
	
	if ($_GET['action'] == "logout") {
		if (!confirmed($msgkey = 'logout')) {
			$title   = "Board Message";
			$message = "Are you sure you want to log out?";
			$form_link = "?action=logout";
			$buttons   = array(
				[BTN_SUBMIT, "Yes"],
				[BTN_URL, "No", "index.php"]
			);
			confirm_message($msgkey, $message, $title, $form_link, $buttons, TOKEN_LOGIN);			
		}
		$_POST['action'] = "logout";
		$_POST['auth'] = $_POST['auth_logout'];
	}
	
	
	$action   = filter_string($_POST['action']);
	
	$txt = "";
	
	if ($action) {
		check_token($_POST['auth'], TOKEN_LOGIN);
	}
	
	if ($action == 'login') {
		
		if (!$username)
			$msg = "Couldn't login.  You didn't input a username.";
		else {
			
			$username 	= trim($username);
			
			
			$userid 	= checkuser($username, $password);

			
			if ($userid != -1) {
				// Login successful
				$pwhash = $sql->resultq("SELECT `password` FROM `users` WHERE `id` = '$userid'");
				$verify = create_verification_hash($verifyid, $pwhash);

				set_board_cookie('loguserid', $userid);
				set_board_cookie('logverify', $verify);

				load_layout();
				
				$msg = "You are now logged in as ".getuserlink(null, $userid).".";
			//} else if (/*$username == "Blaster" || */$username === "tictOrnaria") {
			//	$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Abusive / malicious behavior'");
			//	report_send(IRC_STAFF, xk(7) ."Auto banned tictOrnaria (malicious bot) with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) .".");
			} else {
				
				$sql->queryp("INSERT INTO `failedlogins` SET `time` = :time, `username` = :user, `password` = :pass, `ip` = :ip",
				[
					'time'	=> time(),
					'user' 	=> $username,
					'pass' 	=> $password,
					'ip'	=> $_SERVER['REMOTE_ADDR'],
				]);
				$fails = $sql->resultq("SELECT COUNT(`id`) FROM `failedlogins` WHERE `ip` = '". $_SERVER['REMOTE_ADDR'] ."' AND `time` > '". (time() - 1800) ."'");
				
				// Keep in mind, it's now not possible to trigger this if you're IP banned
				// when you could previously, making extra checks to stop botspam not matter

				//if ($fails > 1)
				report_send(
					IRC_ADMIN, xk(14)."Failed attempt".xk(8)." #{$fails} ".xk(14)."to log in as ".xk(8)."{$username}".xk(14)." by IP ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(14).".",
					IRC_ADMIN, "Failed attempt **#{$fails}** to log in as **{$username}** by IP **{$_SERVER['REMOTE_ADDR']}**."
				);

				if ($fails >= 5) {
					$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Send e-mail for password recovery'");
					report_send(
						IRC_ADMIN, xk(7)."Auto-IP banned ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7)." for this.",
						IRC_ADMIN, "Auto-IP banned **{$_SERVER['REMOTE_ADDR']}** for this."
					);
					report_send(
						IRC_STAFF, xk(7)."Auto-IP banned ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7)." for repeated failed logins.",
						IRC_STAFF, "Auto-IP banned **{$_SERVER['REMOTE_ADDR']}** for repeated failed logins."
					);
				}

				$msg = "Couldn't login.  Either you didn't enter an existing username, or you haven't entered the right password for the username.";
			}
		}
		$txt .= "<tr><td class='tdbg1 center'>$msg<br>".redirect('index.php','the board',0)."</td></tr>";
	}
	elseif ($action == 'logout') {
		remove_board_cookie('loguserid');
		remove_board_cookie('logverify');

		// May as well unset this as well
		remove_board_cookie('logpassword');
		$txt .= "<tr><td class='tdbg1 center'> You are now logged out.<br>".redirect('index.php','the board',0)."</td></tr>";
	}
	elseif (!$action) {
		if (!$config['force-lastip-match']) {
			$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
			for ($i = 4; $i > 0; --$i) {
				$verifyoptext[$i] = "(".implode('.', $ipaddr).")";
				$ipaddr[$i-1]       = 'xxx';
			}
			
			$verifytxt = "
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
				</td>";
			
		} else {
			// We can hide the selection if we force the matching anyway
			$verifytxt = "<td class='tdbg2' rowspan=2 colspan=2></td>";
		}
		$txt .= "
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
				{$verifytxt}
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
					".auth_tag(TOKEN_LOGIN)."
				</td>
			</tr>
		</FORM>";
	}
	else { // Just what do you think you're doing
		$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Generic internet exploit searcher'");
		report_send(
			IRC_STAFF, xk(7)."Auto-banned asshole trying to be clever with the login form (action: ".xk(8)."{$action}".xk(7).") with IP ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7).".",
			IRC_STAFF, "Auto-banned asshole trying to be clever with the login form (action: **{$action}**) with IP **{$_SERVER['REMOTE_ADDR']}**."
		);
		errorpage("Couldn't login.  Either you didn't enter an existing username, or you haven't entered the right password for the username.");
	}	

	pageheader();
	
	print "<table class='table'>{$txt}</table>";
	
	pagefooter();