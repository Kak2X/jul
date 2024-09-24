<?php
/*
	if ($_POST['action'] == "Register" && $_POST['homepage']) {
		header("Location: http://acmlm.no-ip.org/board/register.php");
		die();
	}
*/		
	if (isset($_POST['homepage']) && $_POST['homepage']) {
		// If someone submits the form with the fake homepage field filled,
		// just do nothing and send them off elsewhere to spam
		die(header("Location: http://127.0.0.1"));
	}
	
	$meta['noindex'] = true;
	
	require "lib/common.php";
	load_layout();
	
	// Registration throttling
	// It's only possible to login here
	$regmode = $sql->resultq("SELECT regmode FROM misc");
	if ($regmode == 3 && $config['login-fail-mode'] == LOGFAIL_TEMPBLOCK) {
		$count = $sql->resultq("SELECT COUNT(*) FROM failedregs WHERE ip = '{$_SERVER['REMOTE_ADDR']}' AND `time` > '". (time() - $config['login-fail-timeframe'] * 60) ."'");
		if ($count >= $config['login-ban-threshold']) {
			errorpage("Too many registration attempts in a short time! Try again later.", 'index.php', 'the board', 0);
		}
	}
	
	$_POST['name']	  = filter_string($_POST['name']);
	$_POST['pass']	  = filter_string($_POST['pass']);
	$_POST['pass2']	  = filter_string($_POST['pass2']);
	$_POST['email']	  = filter_string($_POST['email']);
	$_POST['regcode'] = filter_string($_POST['regcode']);
		
	
	
	/*
		regmode:
		0 - Normal
		1 - Disabled
		2 - Pending users
		3 - Regcode
	*/
	
	if (!$isadmin && $regmode == 1)
		errorpage("Registration is disabled. Please contact an admin if you have any questions.");
	

	$error		= false;
	$regerrors 	= [
		'main' => "",
		'name' => "",
		'pass' => "",
		'pass2' => "",
		'code' => "",
	];
	
	if (__($_POST['letsgo'])) {
		
		check_token($_POST['auth'], TOKEN_REGISTER);
		
		/* 
			Round of validations starts here 
		*/

		/*
		if ($_POST['name'] == "Blaster") {
			$sql -> query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Idiot'");
			report_send(IRC_STAFF, xk(7) ."Auto-IP banned Blaster with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." on registration.");
			die("<td class='tdbg1 center'>Thank you, $_POST['name'], for registering your account.<br>".redirect('index.php','the board',0).$footer);
		}
		*/
		
		// TODO: Change how this is done
		if (!$config['no-curl']) {
			$ch = curl_init();
			curl_setopt ($ch,CURLOPT_URL, "http://". $_SERVER['REMOTE_ADDR']);
			curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 3); // <---- HERE
			curl_setopt ($ch, CURLOPT_TIMEOUT, 5); // <---- HERE
			$file_contents = curl_exec($ch);
			curl_close($ch);

			if (
				stristr($file_contents, "proxy")
				|| stristr($file_contents, "forbidden")
				|| stristr($file_contents, "it works")
				|| stristr($file_contents, "anonymous")
				|| stristr($file_contents, "filter")
				|| stristr($file_contents, "panel")
				) {

				/*
				$adjectives	= array(
					"shitlord",
					"shitheel",
					"shitbag",
					"douche",
					"douchebag",
					"douchenozzle",
					"fuckwit",
					"FUCKER",
					"script-kiddie",
					"dumbfuck extraordinare",
					);
				
				shuffle($adjectives);

				$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Reregistering fuckwit'");
				report_send(IRC_STAFF, xk(7) ."Auto-IP banned proxy-abusing $adjectives[0] with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." on registration. (Tried to register with username $_POST['name'])");
				errorpage("Thank you, $_POST['name'], for registering your account.", 'index.php', 'the board', 0);
				*/
				$regerrors['main'] .= "<div>It appears you're trying to register through some proxy service or other anonymizing tool.
				<br>These have often been abused to get around bans, so we don't allow registering using these.
				<br>Try disabling it and registering again, or contact an administrator for help.</div>";
				$error = true;
			}
		}
		
		/*
		// You asked for it
		if (isset($_POST['homepage']) && $_POST['homepage']) {
			$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Automatic spambot protection'");
			report_send(IRC_STAFF, xk(7) ."Auto-IP banned user with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." for filling in the dummy registration field. (Tried to register with username $_POST['name'])");
			errorpage("Thank you, $_POST['name'], for registering your account.", 'index.php', 'the board', 0);
		}*/


		// Restrict username to 32 chars (only now so previous logging logs it in full)
		$_POST['name'] = substr(trim($_POST['name']), 0, 32);
		
		if (!$_POST['name']) {
			$regerrors['name'] .= "<li>No username specified.</li>";
			$error = true;
		}
		if (!$_POST['pass']) {
			$regerrors['pass'] .= "<li>No password specified.</li>";
			$error = true;
		}
		if ($regmode == 3 && !$_POST['regcode']) {
			$regerrors['code'] .= "<li>No registration key specified.</li>";
			$error = true;
		} 
			
		if (!$error) {
			if (strlen($_POST['pass']) < 8 && !$isadmin) {
				$regerrors['pass'] .= "<li>The password is too short.</li>";
				$error = true;
			}
			if ($_POST['pass'] != $_POST['pass2']) {
				$regerrors['pass2'] .= "<li>The passwords don't match. Re-type them correctly.</li>";
				$error = true;
			}
		}
		
		if (!$error) {
			$usermatch = strtolower(str_replace(' ', '', $_POST['name']));
			if ($userid = $sql->resultp("SELECT id FROM users WHERE ? IN (LOWER(REPLACE(name, ' ', '')), LOWER(REPLACE(displayname, ' ', '')))", [$usermatch])) {
				$regerrors['main'] .= "<div>The username '". htmlspecialchars($_POST['name']) ."' is already <a href='profile.php?id={$userid}'>in use</a>.</div>";
				$regerrors['name'] .= "<li>In use</li>";
				$error = true;
			}
			else if (!$isadmin && !$config['allow-rereggie'] && ($nomultis = $sql->fetchq("SELECT $userfields FROM users u WHERE lastip = '{$_SERVER['REMOTE_ADDR']}'"))) {
				$regerrors['main'] .= "<div>You may have an account already as ".getuserlink($nomultis).".<br/>If this is incorrect, please contact an administrator.</div>";
				$error = true;
			}
			// Check the same things, but in the pending users table
			else if ($regmode == 2) {
				if ($sql->resultp("SELECT id FROM pendingusers WHERE ? = LOWER(REPLACE(name, ' ', ''))", [$usermatch])) {
					$regerrors['main'] .= "<div>The username '". htmlspecialchars($_POST['name']) ."' is already in the pending approval queue.</div>";
					$error = true;
				}
				else if (!$isadmin && !$config['allow-rereggie'] && ($nomultis = $sql->resultq("SELECT name FROM pendingusers WHERE ip = '{$_SERVER['REMOTE_ADDR']}'"))) {
					$regerrors['main'] .= "<div>You may have an account already as '".htmlspecialchars($nomultis)."' that is pending approval.<br>If this is incorrect, please contact an administrator.</div>";
					$error = true;
				}
			}
		}
		
		// Only after all of that, check if the registration code is valid
		if ($regmode == 3 && !$error) {
			$realcode 	= $sql->resultq("SELECT regcode FROM misc");
			
			if ($_POST['regcode'] != $realcode) {

				// No infinite retries allowed in a short time span
				$thisfail = [
					'time'      => time(),
					'username' 	=> $_POST['name'],
					'password'  => $_POST['pass'],
					'email'     => $_POST['email'],
					'ip'        => $_SERVER['REMOTE_ADDR'],
					'regcode'   => $_POST['regcode'],
				];
				$sql->queryp("INSERT INTO `failedregs` SET ".mysql::setplaceholders($thisfail), $thisfail);
				
				$fails = $sql->resultq("SELECT COUNT(`id`) FROM `failedregs` WHERE `ip` = '". $_SERVER['REMOTE_ADDR'] ."' AND `time` > '". (time() - $config['login-fail-timeframe'] * 60) ."'");
				report_send(
					IRC_ADMIN, xk(14)."Failed attempt".xk(8)." #{$fails} ".xk(14)."to register using the wrong code ".xk(8)."{$_POST['regcode']}".xk(14)." by IP ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(14).".",
					IRC_ADMIN, "Failed attempt **#{$fails}** to register using the wrong code **{$_POST['regcode']}** by IP **{$_SERVER['REMOTE_ADDR']}**."
				);

				if ($fails >= $config['login-ban-threshold']) {
					if ($config['login-fail-mode'] == LOGFAIL_IPBAN) {
						$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Too many failed registration attempts. Send e-mail to re-request the registration code'");
						report_send(
							IRC_ADMIN, xk(7)."Auto-IP banned ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7)." for this.",
							IRC_ADMIN, "Auto-IP banned **{$_SERVER['REMOTE_ADDR']}** for this."
						);
						report_send(
							IRC_STAFF, xk(7)."Auto-IP banned ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7)." for repeated failed registration attempts.",
							IRC_STAFF, "Auto-IP banned **{$_SERVER['REMOTE_ADDR']}** for repeated failed registration attempts."
						);
					} else if ($config['login-fail-mode'] == LOGFAIL_TEMPBLOCK) {
						report_send(
							IRC_ADMIN, xk(7)."Temp-blocked ".xk(8)."{$_SERVER['REMOTE_ADDR']}".xk(7)." for this.",
							IRC_ADMIN, "Temp-blocked **{$_SERVER['REMOTE_ADDR']}** for this."
						);
					}
					die(header("Location: ?"));
				}
				
						
				$warning = $config['login-fail-mode'] && $fails >= $config['login-warn-threshold'] ? "<br/><b>Warning: Continued failed attempts will result in a ban.</b>" : "";
				$invites = discord_get_invites();
				$regerrors['main'] .= "<div>You have entered a bad registration code. If you've forgotten your code, ".($invites ? "<a href='{$invites[0][1]}'>join Discord</a> (sorry) or " : "")."email me at <tt>{$config['admin-email']}</tt> ".($config['admin-discord'] ? "/ Discord <tt>{$config['admin-discord']}</tt>" : "")."{$warning}</div>";
				$regerrors['code'] .= "<li>Bad code</li>";
				$error = true;
			}
		}
		
		/*
			if ($_POST['pass'] == "123") {
			echo	"<td class='tdbg1 center'>Thank you, $_POST['name'], for registering your account.<img src=cookieban.php width=1 height=1><br>".redirect('index.php','the board',0);
			mysql_query("INSERT INTO `ipbans` (`ip`, `reason`, `date`) VALUES ('". $_SERVER['REMOTE_ADDR'] ."', 'blocked password of 123', '". time() ."')");
			die();
		}
		*/
		if (!$error) {
			

			$newuserid 		= $sql->resultq("SELECT MAX(id) FROM users") + 1;
			// The first user is super admin
			$userlevel 		= $newuserid != 1 ? 0 : 4;
			$makedeluser    = ($config['deleted-user-id'] == $newuserid + 1);
			$currenttime 	= time();
			
			
			if (!$x_hacks['host'] && $regmode == 2) { // || $flagged
				
				$sql->queryp("
					INSERT INTO `pendingusers` SET `name` = :name, `password` = :password, `ip` = :ip, `ua` = :ua, `date` = :date, `email` = :email",
					[
						'name'		=> $_POST['name'],
						'password'	=> getpwhash($_POST['pass'], $newuserid),
						'email'		=> $_POST['email'],
						'ip'		=> $_SERVER['REMOTE_ADDR'],
						'ua'		=> $_SERVER['HTTP_USER_AGENT'],
						'date'		=> $currenttime,
					]);
				$newuserid  = $sql->insert_id();
				report_send(
					IRC_STAFF, "New pending user #".xk(12)."{$newuserid}".xk(11)." {$_POST['name']}".xk()." (IP: ".xk(12)."{$_SERVER['REMOTE_ADDR']}".xk().")",
					IRC_STAFF, "New pending user #{$newuserid} **{$_POST['name']}** (IP: **{$_SERVER['REMOTE_ADDR']}**)"
				);
			//		$sql->query("INSERT INTO `ipbans` SET `ip` = '$ipaddr', `reason` = 'Automagic ban', `banner` = 'Acmlmboard'");

				errorpage("Thank you, ".htmlspecialchars($_POST['name']).", for registering your account.<br/>Please wait for an administrator to approve it.",'index.php','the board', 0);
			} else {
				$sql->beginTransaction();
				
				$data = array(
					'id'                => $newuserid,
					'name'              => $_POST['name'],
					'password'          => getpwhash($_POST['pass'], $newuserid),
					'email'             => $_POST['email'],
					'powerlevel'        => $userlevel,
					'lastip'            => $_SERVER['REMOTE_ADDR'],
					'lastua'			=> $_SERVER['HTTP_USER_AGENT'],
					'lastactivity'      => $currenttime,
					'regdate'           => $currenttime,
					'threadsperpage'    => $config['default-tpp'],
					'postsperpage'      => $config['default-ppp'],
					'scheme'            => $miscdata['defaultscheme'],
				);
				$sql->queryp("INSERT INTO users SET ".mysql::setplaceholders($data), $data);
				log_useragent($newuserid);
				$sql->query("INSERT INTO `users_rpg` (`uid`) VALUES ('{$newuserid}')");
				$sql->query("INSERT INTO forumread (user, forum, readdate) SELECT {$newuserid}, id, {$currenttime} FROM forums");
				
				
				$ircout = array (
					'id'	=> $newuserid,
					'name'	=> $_POST['name'],
					'ip'	=> $_SERVER['REMOTE_ADDR'],
				);
				report_new_user("user", $ircout);
				
				
				
				// If the next user is the deleted user ID, make sure to automatically register it
				if ($makedeluser) {
					$delcss = "
.sidebar{$config['deleted-user-id']},.topbar{$config['deleted-user-id']}_2{
	background: #181818;
	font-family: Verdana, sans-serif;
	color: #bbb;
}
.sidebar{$config['deleted-user-id']}{
	text-align: center; 
	font-size: 14px;
	padding-top: .5em
}
.topbar{$config['deleted-user-id']}_2{
	width: 100%;
	font-size: 12px;
}
.mainbar{$config['deleted-user-id']}{
	background: #181818;
	padding: 0;
}";
					$delsidebar = '<span style="letter-spacing: 0px; color: #555; font-size: 10px">Collection of nobodies</span>';
					
					$sql->query("
						INSERT INTO users (id, name, password, powerlevel, regdate, sidebartype, sidebar, css) 
						VALUES ({$config['deleted-user-id']}, 'Deleted user', 'X', -2, {$currenttime}, 3, '{$delsidebar}', '{$delcss}')
					");
					$sql->query("INSERT INTO `users_rpg` (`uid`, `damage`) VALUES ('{$config['deleted-user-id']}', 99999)");
				}
				
				$sql->commit();
				errorpage("Thank you, ".htmlspecialchars($_POST['name']).", for registering your account.", 'index.php', 'the board', 0);
			}
			
		}
		
	}
	
	if ($regerrors['name'])
		$autofocus['name'] = "autofocus";
	else if ($regerrors['code'] || $regerrors['pass'] || $regerrors['pass2']) 
		$autofocus['pass'] = "autofocus";
	else 
		$autofocus['name'] = "autofocus";

	pageheader();
		
	if ($regerrors['main']) {
		boardmessage($regerrors['main'], "Error registering account", false);
		print "<br/>";
	}
	if ($regmode == 2) {
		boardmessage("User registrations go into a queue for pending approval.<br/>Your account won't be created immediately, as an administrator will have to approve it first.", "Notice", false);
		print "<br/>";
	}
?>
<form method="POST" action="register.php">
	<table class='table'>
		<tr><td class='tdbgh center' colspan="2">Login information</td></tr>
		
		<tr>
			<td class='tdbg1 center'>
				<b>User name:</b>
				<div class='fonts'>
					&nbsp; The name you want to use on the board.
				</div>
			</td>
			<td class='tdbg2' style='width: 50%'>
				<input type='text' <?= __($autofocus['name']) ?> name='name' size="25" maxlength="25" value="<?= escape_attribute($_POST['name']) ?>">
				<?= _errorformat('name') ?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>
				<b>Password:</b>
				<div class='fonts'>
					&nbsp; Enter any password at least 8 characters long. It can later be changed by editing your profile.<br>
					<br>Warning: Do <b>not</b> use insecure passwords such as '123456', 'qwerty', or 'pokemon'. It'll result in an instant IP ban.
				</div>
			</td>
			<td class='tdbg2'>
				<input type='password' name='pass' <?= __($autofocus['pass']) ?> size="15" maxlength="64">
				<?= _errorformat('pass') ?>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>
				<div class='fonts'>
					&nbsp; Retype the password again.
				</div>
			</td>
			<td class='tdbg2'>
				<input type='password' name='pass2' size="15" maxlength="64">
				<?= _errorformat('pass2') ?>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>
				<b>E-mail address:</b>
				<div class='fonts'>
					&nbsp; Your e-mail address. This will only be used for recovering your account. (optional)
				</div>
			</td>
			<td class='tdbg2'>
				<input type='text' name='email' size="50" maxlength="60" value="<?= escape_attribute($_POST['email']) ?>">
			</td>
		</tr>
		
<?php	if ($regmode == 3) { ?>
		<tr>
			<td class='tdbg1 center'>
				<b>Registration code:</b>
				<div class='fonts'>
					Secret code required to register, contact <?=$config['admin-name']?> for this.
				</div>
			</td>
			<td class='tdbg2'>
				<input type='regcode' name="regcode"  <?= __($autofocus['code']) ?> size="15" maxlength="64">
				<?= _errorformat('code') ?>
			</td>
		</tr>
<?php	} ?>
		
		<tr>
			<td class='tdbgh center'>&nbsp;</td>
			<td class='tdbgh center'>&nbsp;</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2'>
				<button type="submit" name="letsgo" value="wapiko">Register account</button>
				<?=auth_tag(TOKEN_REGISTER)?>
			</td>
		</tr>
	</table>
	<div style='visibility: hidden;'><b>Homepage:</b><small> DO NOT FILL IN THIS FIELD. DOING SO WILL RESULT IN INSTANT IP-BAN.</small> - <input type='text' name=homepage SIZE=25 MAXLENGTH=255></div>

	</form>

<?php
 
	pagefooter();

	function _errorformat($key) {
		global $regerrors;
		if (!$regerrors[$key]) return "";
		return "<ul style='color: red; margin: 0'>{$regerrors[$key]}</ul>";
	}
