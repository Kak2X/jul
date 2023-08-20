<?php
/*
	if ($_POST['action'] == "Register" && $_POST['homepage']) {
		header("Location: http://acmlm.no-ip.org/board/register.php");
		die();
	}
*/

	require "lib/common.php";
	
	
	$meta['noindex'] = true;
	
	$regmode = $sql->resultq("SELECT regmode FROM misc");
	
	/*
		regmode:
		0 - Normal
		1 - Disabled
		2 - Pending users
		3 - Regcode
	*/
	
	if (!$isadmin && $regmode == 1)
		errorpage("Registration is disabled. Please contact an admin if you have any questions.");
	

	$action 	= filter_string($_POST['action']);
	$error		= false;
	$regerrors 	= [
		'main' => "",
		'name' => "",
		'pass' => "",
		'pass2' => "",
		'code' => "",
	];
	
	if ($_POST['action'] == 'Register') {
		
		if (isset($_POST['homepage']) && $_POST['homepage']) {
			// If someone submits the form with the fake homepage field filled,
			// just do nothing and send them off elsewhere to spam
			die(header("Location: http://127.0.0.1"));
		}
		 
		check_token($_POST['auth'], TOKEN_REGISTER);
		
		$_POST['name']	= filter_string($_POST['name']);
		$_POST['pass']	= filter_string($_POST['pass']);
		$_POST['pass2']	= filter_string($_POST['pass2']);
		$_POST['email']	= filter_string($_POST['email']);
		

		
		/* 
			Round of validations starts here 
		*/

		/*
		if ($_POST['name'] == "Blaster") {
			$sql -> query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Idiot'");
			@xk_ircsend("1|". xk(7) ."Auto-IP banned Blaster with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." on registration.");
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
				@xk_ircsend("1|". xk(7) ."Auto-IP banned proxy-abusing $adjectives[0] with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." on registration. (Tried to register with username $_POST['name'])");
				errorpage("Thank you, $_POST['name'], for registering your account.", 'index.php', 'the board', 0);
				*/
				$regerrors['main'] .= "<li>It appears you're trying to register through some proxy service or other anonymizing tool.
				<br>These have often been abused to get around bans, so we don't allow registering using these.
				<br>Try disabling it and registering again, or contact an administrator for help.</li>";
				$error = true;
			}
		}
		
		/*
		// You asked for it
		if (isset($_POST['homepage']) && $_POST['homepage']) {
			$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Automatic spambot protection'");
			@xk_ircsend("1|". xk(7) ."Auto-IP banned user with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." for filling in the dummy registration field. (Tried to register with username $_POST['name'])");
			errorpage("Thank you, $_POST['name'], for registering your account.", 'index.php', 'the board', 0);
		}*/

		
		/*
			Registration code check.
		*/
		if ($regmode == 3) {
			$_POST['regcode'] 	= filter_string($_POST['regcode']);
			$realcode 	= $sql->resultq("SELECT regcode FROM misc");
			
			if ($_POST['regcode'] != $realcode) {

				// No infinite retries allowed in a short time span
				$sql->queryp("INSERT INTO `failedregs` SET `time` = :time, `username` = :user, `password` = :pass, `ip` = :ip, `regcode` = :code",
				[
					'time'	=> time(),
					'user' 	=> $_POST['name'],
					'pass' 	=> $_POST['pass'],
					'email' => $_POST['email'],
					'ip'	=> $_SERVER['REMOTE_ADDR'],
					'code'	=> $_POST['regcode'],
				]);
				
				$fails = $sql->resultq("SELECT COUNT(`id`) FROM `failedregs` WHERE `ip` = '". $_SERVER['REMOTE_ADDR'] ."' AND `time` > '". (time() - 1800) ."'");
				@xk_ircsend("102|". xk(14) ."Failed attempt". xk(8) ." #$fails ". xk(14) ."to register using the wrong code ". xk(8) . $_POST['regcode'] . xk(14) ." by IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(14) .".");

				if ($fails >= 5) {
					$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". time() ."', `reason` = 'Send e-mail to re-request the registration code'");
					@xk_ircsend("102|". xk(7) ."Auto-IP banned ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." for this.");
					@xk_ircsend("1|". xk(7) ."Auto-IP banned ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." for repeated failed registration attempts.");
					die(header("Location: ?"));
				}
				
				$regerrors['main'] .= "<li>You have entered a bad registration code.</li>";
				$regerrors['code'] .= "<li>Bad code</li>";
				$error = true;
			}
		}

		// Restrict username to 25 chars (only now so previous logging logs it in full)
		$_POST['name'] = substr(trim($_POST['name']), 0, 25);
		
		if (!$_POST['name']) {
			$regerrors['name'] .= "<li>No username specified.</li>";
			$error = true;
		}
		if (!$_POST['pass']) {
			$regerrors['pass'] .= "<li>No password specified.</li>";
			$error = true;
		} 
			
		if (!$error) {
			if (strlen($_POST['pass']) < 8 && !$isadmin) {
				$regerrors['pass'] .= "<li>The password is too short.</li>";
				$error = true;
			}
			if ($_POST['pass'] != $_POST['pass2']) {
				$regerrors['pass2'] .= "<li>The passwords don't match. Re-type it correctly.</li>";
				$error = true;
			}
		}
		
		if (!$error) {
			if ($userid = $sql->resultp("SELECT id FROM users WHERE LOWER(REPLACE(name, ' ', '')) = ?", [strtolower(str_replace(' ', '', $_POST['name']))])) {
				$regerrors['main'] .= "<li>The username '". htmlspecialchars($_POST['name']) ."' is already <a href='profile.php?id={$userid}'>in use</a></li>";
				$regerrors['name'] .= "<li>In use</li>";
				$error = true;
			}
			if (!$isadmin && !$config['allow-rereggie'] && ($nomultis = $sql->resultq("SELECT $userfields FROM users u WHERE lastip = '{$_SERVER['REMOTE_ADDR']}'"))) {
				$regerrors['main'] .= "<li>You may have an account already as ".getuserlink($nomultis)."<br>If this is incorrect, please contact an administrator.</li>";
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
					INSERT INTO `pendingusers` SET `name` = :name, `password` = :password, `ip` = :ip, `date` = :date, `email` = :email",
					[
						'name'		=> $_POST['name'],
						'password'	=> getpwhash($_POST['pass'], $newuserid),
						'email'		=> $_POST['email'],
						'ip'		=> $_SERVER['REMOTE_ADDR'],
						'date'		=> $currenttime,
					]);

			//		$sql->query("INSERT INTO `ipbans` SET `ip` = '$ipaddr', `reason` = 'Automagic ban', `banner` = 'Acmlmboard'");

				errorpage("Thank you, ".htmlspecialchars($_POST['name']).", for registering your account.",'index.php','the board', 0);
			} else {

				$ircout = array (
					'id'	=> $newuserid,
					'name'	=> stripslashes($_POST['name']),
					'ip'	=> $_SERVER['REMOTE_ADDR']
				);
				
				
				// No longer useful
				//$ircout['pmatch']	= $sql -> resultq("SELECT COUNT(*) FROM `users` WHERE `password` = '". md5($_POST['pass']) ."'");
				$sql->beginTransaction();
				
				$data = array(
					'id'                => $newuserid,
					'name'              => $_POST['name'],
					'password'          => getpwhash($_POST['pass'], $newuserid),
					'email'             => $_POST['email'],
					'powerlevel'        => $userlevel,
					'lastip'            => $_SERVER['REMOTE_ADDR'],
					'lastactivity'      => $currenttime,
					'regdate'           => $currenttime,
					'threadsperpage'    => $config['default-tpp'],
					'postsperpage'      => $config['default-ppp'],
					'scheme'            => $miscdata['defaultscheme'],
				);
				$sql->queryp("INSERT INTO users SET ".mysql::setplaceholders($data), $data);
				
				$sql->query("INSERT INTO `users_rpg` (`uid`) VALUES ('{$newuserid}')");
				$sql->query("INSERT INTO forumread (user, forum, readdate) SELECT {$newuserid}, id, {$currenttime} FROM forums");
				xk_ircout("user", $ircout['name'], $ircout);
				
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
					$sql->query("INSERT INTO `users_rpg` (`uid`) VALUES ('{$config['deleted-user-id']}')");
				}
				
				$sql->commit();
				errorpage("Thank you, ".htmlspecialchars($_POST['name']).", for registering your account.", 'index.php', 'the board', 0);
			}
			
		}
		
	}

	pageheader();
		
	if ($regerrors['main']) { ?> 
	<table class='table'>
		<tr><td class='tdbgh center'>Error registering account</td></tr>
		<tr><td class='tdbg1 center'><ul><?= $regerrors['main'] ?> </ul></td></tr>
	</table>
<?php } ?>
<form method="POST" action="register.php">
	<br>
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
				<input type='text' autofocus name='name' size="25" maxlength="25">
				<?= _errorformat('name') ?>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center'>
				<b>Password:</b>
				<div class='fonts'>
					&nbsp; Enter any password at least 8 characters long. It can later be changed by editing your profile.<br>
					<br>Warning: Do <b>not</b> use unsecure passwords such as '123456', 'qwerty', or 'pokemon'. It'll result in an instant IP ban.
				</div>
			</td>
			<td class='tdbg2'>
				<input type='password' name='pass' size="15" maxlength="64">
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
				<input type='text' name='email' size="50" maxlength="60">
			</td>
		</tr>
		
<?php	if ($regmode == 3) { ?>
		<tr>
			<td class='tdbg1 center'>
				<b>Regcode:</b>
				<div class='fonts'>
					To keep the morons out; contact <?=$config['admin-name']?> for this.
				</div>
			</td>
			<td class='tdbg2'>
				<input type='regcode' name="regcode" size="15" maxlength="64">
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
				<input type='hidden' name=action VALUE="Register">
				<input type='submit' class=submit name=submit VALUE="Register account">
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
		return "<ul style='color: red'>{$regerrors[$key]}</ul>";
	}