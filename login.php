<?php
	// Bots don't need to be on this page
	$meta['noindex'] = true;
	
	require "lib/common.php";
	
	login_throttle();
	
	$username = filter_string($_POST['username']);
	if ($username) $username = trim($username);
	$password = filter_string($_POST['userpass']);
	$verifyid = $config['force-lastip-match'] ? 4 : filter_int($_POST['verify']);
	
	// For the alternate way to log out, without requiring JS
	$_GET['action'] = filter_string($_GET['action']);
	if ($_GET['action'] == "logout") {
		if (!confirmed($msgkey = 'logout', TOKEN_LOGIN)) {
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
	
	
	$action		= filter_string($_POST['action']);
	$txt 		= "";
	$form_msg	= "";
	
	if ($action) {
		check_token($_POST['auth'], TOKEN_LOGIN);
		if ($action == 'logout') {
			remove_board_cookie('loguserid');
			remove_board_cookie('logverify');

			// May as well unset this as well
			remove_board_cookie('logpassword');
			
			errorpage("You are now logged out.", "index.php", "the board", 0); 
		}
		else if ($action == 'login') {
			//if (/*$username == "Blaster" || */$username === "tictOrnaria") {
			//	$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Abusive / malicious behavior'");
			//	report_send(IRC_STAFF, xk(7) ."Auto banned tictOrnaria (malicious bot) with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) .".");
			//  die;
			//}
			$userid = checkuser($username, $password);
			
			switch ($userid) {
				case -1:
					$form_msg = "You didn't input a username.";
					break;
				case -2:
					$form_msg = "No user with that username exists.<br/><br/>If you aren't sure if you have an account, check the <a href='memberlist.php'>memberlist</a> or <a href='register.php'>register a new account</a>.";
					$username = "";
					break;
				case -3:
				case -4:
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

					if ($fails >= $config['login-ban-threshold']) {
						
						if ($config['login-ipban']) {
							$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Too many failed login attempts. Send e-mail for password recovery'");
							report_send(
								IRC_ADMIN, xk(7)."Auto-IP banned ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7)." for this.",
								IRC_ADMIN, "Auto-IP banned **{$_SERVER['REMOTE_ADDR']}** for this."
							);
							report_send(
								IRC_STAFF, xk(7)."Auto-IP banned ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7)." for repeated failed logins.",
								IRC_STAFF, "Auto-IP banned **{$_SERVER['REMOTE_ADDR']}** for repeated failed logins."
							);
						} else {
							report_send(
								IRC_ADMIN, xk(7)."Temp-blocked ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7)." for this.",
								IRC_ADMIN, "Temp-blocked **{$_SERVER['REMOTE_ADDR']}** for this."
							);
						}
						die(header("Location: ?"));			
					} else {
						$invites = discord_get_invites();
						$form_msg = "The password you entered doesn't match.<br/><br/>
						If you've forgotten your password, ".($invites ? "<a href='{$invites[0][1]}'>join Discord</a> (sorry) or " : "")."email me at <tt>{$config['admin-email']}</tt> ".($config['admin-discord'] ? "/ Discord <tt>{$config['admin-discord']}</tt>" : "");
						
						if ($fails >= $config['login-warn-threshold'])
							$form_msg .= "<br/><br/><b>Warning: Continued failed attempts will result in a ban.</b>";
					}
					break;
				default:
					// Login successful: Create a new password hash, which has the effect of invalidating previous tokens
					$pwhash = getpwhash($password, $userid);
					$sql->query("UPDATE users SET password = '{$pwhash}' WHERE id = '{$userid}'");
					
					$verify = create_verification_hash($verifyid, $pwhash);

					set_board_cookie('loguserid', $userid);
					set_board_cookie('logverify', $verify);
					
					load_layout();
					
					errorpage("You are now logged in as ".getuserlink(null, $userid).".", "index.php", "the board", 0);
					break;
			}
		} else { // Just what do you think you're doing
			errorpage("Just what do you think you're doing, anyway?");
			/*
			$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Generic internet exploit searcher'");
			report_send(
				IRC_STAFF, xk(7)."Auto-banned asshole trying to be clever with the login form (action: ".xk(8)."{$action}".xk(7).") with IP ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7).".",
				IRC_STAFF, "Auto-banned asshole trying to be clever with the login form (action: **{$action}**) with IP **{$_SERVER['REMOTE_ADDR']}**."
			);
			errorpage("Couldn't login.  Either you didn't enter an existing username, or you haven't entered the right password for the username.");
			*/
		}
	
	}
	
	// Main Form	
	pageheader();
	
	if ($form_msg) {
		boardmessage("Couldn't login. {$form_msg}", "Message", false);
		print "<br/>";
	}
	
	?>
	<form method="POST" action="?">
	<table class="table">
		<tr>
			<td class="tdbgh center" width="150">&nbsp;</td>
			<td class="tdbgh center" width="40%">&nbsp</td>
			<td class="tdbgh center" width="150">&nbsp;</td>
			<td class="tdbgh center" width="40%">&nbsp;</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">User name:</td>
			<td class="tdbg2">
				<input type="text" name="username" maxlength="25" style="width:280px" tabindex="1" value="<?= htmlspecialchars($username) ?>" <?= (!$username ? " autofocus='1'" : "") ?>>
			</td>
			<td class="tdbg1 center b" rowspan="2">IP Verification:</td>
			<td class="tdbg2" rowspan="2">
				<?php
				if ($config['force-lastip-match']) {
					print "<i>Enforced</i>";
				} else {
					$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
					$verify_list = array_fill(0, 4, '');
					$verify_list[0] = "Don't use";
					for ($i = 4; $i > 0; --$i) {
						$verify_list[$i]	= "/".($i*8)." (".implode('.', $ipaddr).")";
						$ipaddr[$i-1]		= 'xxx';
					}
					
					print input_html("verify", $verifyid, ['input' => 'select', 'options' => $verify_list, 'tabindex' => 4])."
					<div class='fonts'>
						You can require your IP address to match your current IP, to an extent, to remain logged in.
					</div>";
				}
				?>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Password:</td> 
			<td class="tdbg2">
				<input type="password" name="userpass" maxlength="64" style="width:180px" tabindex="2"<?= ($username ? " autofocus='1'" : "") ?>>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center">&nbsp;</td>
			<td class="tdbg2" colspan="3">
				<button type="submit" name="action" value="login" tabindex="3">Login</button>
				<?= auth_tag(TOKEN_LOGIN) ?>
			</td>
		</tr>
	</table>
	</form>
	<?php
	
	pagefooter();