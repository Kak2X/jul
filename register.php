<?php
/*
	if ($_POST['action'] == "Register" && $_POST['homepage']) {
		header("Location: http://acmlm.no-ip.org/board/register.php");
		die();
	}
*/

	require 'lib/function.php';
	
	
	$meta['noindex'] = true;
	
	$regmode = $sql->resultq("SELECT regmode FROM misc");
	
	/*
		regmode:
		0 - Normal
		1 - Disabled
		2 - Pending users
		3 - regcode
	*/
	
	if (!$isadmin && $regmode == 1)
		errorpage("Registration is disabled. Please contact an admin if you have any questions.");
	

	$action 	= filter_string($_POST['action']);
	
	if($_POST['action']=='Register') {
		 
		check_token($_POST['auth'], TOKEN_REGISTER);
		
		$name = filter_string($_POST['name']);
		$pass  = filter_string($_POST['pass']);
		$pass2 = filter_string($_POST['pass2']);
		

		/*
		if ($name == "Blaster") {
			$sql -> query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."', `reason` = 'Idiot'");
			@xk_ircsend("1|". xk(7) ."Auto-IP banned Blaster with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." on registration.");
			die("<td class='tdbg1 center'>Thank you, $username, for registering your account.<br>".redirect('index.php','the board',0).$footer);
		}
		*/

		/* do curl here */
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

				$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."', `reason` = 'Reregistering fuckwit'");
				@xk_ircsend("1|". xk(7) ."Auto-IP banned proxy-abusing $adjectives[0] with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." on registration. (Tried to register with username $name)");
				errorpage("Thank you, $name, for registering your account.", 'index.php', 'the board', 0);
			}
		}
		
		// You asked for it
		if (isset($_POST['homepage']) && $_POST['homepage']) {
			$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."', `reason` = 'Automatic spambot protection'");
			@xk_ircsend("1|". xk(7) ."Auto-IP banned user with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." for filling in the dummy registration field. (Tried to register with username $name)");
			errorpage("Thank you, $name, for registering your account.", 'index.php', 'the board', 0);
		}

		
		$badcode = false;
		
		if ($regmode == 3) {
			$checkcode 	= filter_string($_POST['regcode'], true);
			$realcode 	= $sql->resultq("SELECT regcode FROM misc");
			
			if ($checkcode != $realcode) {

				// No infinite retries allowed in a short time span
				$sql->queryp("INSERT INTO `failedregs` SET `time` = :time, `username` = :user, `password` = :pass, `ip` = :ip, `regcode` = :code",
				[
					'time'	=> ctime(),
					'user' 	=> $name,
					'pass' 	=> $pass,
					'ip'	=> $_SERVER['REMOTE_ADDR'],
					'code'	=> $checkcode,
				]);
				
				//$name 		= stripslashes($name);
				//$checkcode 	= stripslashes($checkcode);
				
				$fails = $sql->resultq("SELECT COUNT(`id`) FROM `failedregs` WHERE `ip` = '". $_SERVER['REMOTE_ADDR'] ."' AND `time` > '". (ctime() - 1800) ."'");
				
				@xk_ircsend("102|". xk(14) ."Failed attempt". xk(8) ." #$fails ". xk(14) ."to register using the wrong code ". xk(8) . $checkcode . xk(14) ." by IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(14) .".");

				if ($fails >= 5) {
					$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."', `reason` = 'Send e-mail to re-request the registration code'");
					@xk_ircsend("102|". xk(7) ."Auto-IP banned ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." for this.");
					@xk_ircsend("1|". xk(7) ."Auto-IP banned ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." for repeated failed registration attempts.");
				}
				$badcode = true;
			}
		}

		
		// Check for duplicate names
		$users = $sql->query('SELECT name FROM users');
		
		$username  = substr(xssfilters(trim($name)),0,25);
		$username2 = str_replace(' ','',$username);
		$username2 = preg_replace("'&nbsp;?'si",'',$username2);
		//$username2 = stripslashes($username2);
		
		
		$samename = NULL;
		
		while ($user = $sql->fetch($users)) {
			$user['name'] = str_replace(' ','',$user['name']);
			if (strcasecmp($user['name'], $username2) == 0) $samename = $user['name'];
		}
		
		
		if ($isadmin || $config['allow-rereggie']) 
			$nomultis = false;
		else 
			$nomultis = $sql->resultq("SELECT id FROM `users` WHERE `lastip` = '{$_SERVER['REMOTE_ADDR']}'");
		
		
		$shortpass = (strlen($pass) < 8 && !$isadmin); 
		$retyped   = ($pass == $pass2);
		
		// Making sure
		if (!$samename && $retyped && $pass && $pass != "123" && $username && !$shortpass && !$nomultis && !$badcode) {
			
			// The first user is super admin
			$userlevel 		= $sql->num_rows($users) ? 0 : 4;
			$newuserid 		= $sql->resultq("SELECT MAX(id) FROM users") + 1;
			$makedeluser    = ($config['deleted-user-id'] == $newuserid + 1);
			$currenttime 	= ctime();
			
			
			if (!$x_hacks['host'] && $regmode == 2) { // || $flagged
				
				$sql->queryp("
					INSERT INTO `pendingusers` SET `name` = :name, `password` = :password, `ip` = :ip, `date` = :date",
					[
						'name'		=> $name,
						'password'	=> getpwhash($pass, $newuserid),
						'ip'		=> $_SERVER['REMOTE_ADDR'],
						'date'		=> $currenttime,
					]);

			//		$sql->query("INSERT INTO `ipbans` SET `ip` = '$ipaddr', `reason` = 'Automagic ban', `banner` = 'Acmlmboard'");

				errorpage("Thank you, $name, for registering your account.",'index.php','the board', 0);
			} else {

				$ircout = array (
					'id'	=> $newuserid,
					'name'	=> stripslashes($name),
					'ip'	=> $_SERVER['REMOTE_ADDR']
				);
				
				
				// No longer useful
				//$ircout['pmatch']	= $sql -> resultq("SELECT COUNT(*) FROM `users` WHERE `password` = '". md5($pass) ."'");
				$sql->beginTransaction();
				
				$data = array(
					'id'                => $newuserid,
					'name'              => $name,
					'password'          => getpwhash($pass, $newuserid),
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
				mkdir("userpic/$newuserid");
				if ($makedeluser) {
					mkdir("userpic/{$config['deleted-user-id']}");
				}
				errorpage("Thank you, $username, for registering your account.", 'index.php', 'the board', 0);
			}
			
		} else {

		/*	if ($password == "123") {
			echo	"<td class='tdbg1 center'>Thank you, $username, for registering your account.<img src=cookieban.php width=1 height=1><br>".redirect('index.php','the board',0);
			mysql_query("INSERT INTO `ipbans` (`ip`, `reason`, `date`) VALUES ('". $_SERVER['REMOTE_ADDR'] ."', 'blocked password of 123', '". ctime() ."')");
			die();
		}
		*/

			if ($badcode) {
				$reason = "You have entered a bad registration code.";
			} elseif ($samename) {
				$reason = "That username is already in use.";
			} elseif ($nomultis) {
				$reason = "You have already registered! (<a href='profile.php?id=$nomultis'>here</a>)";
			} elseif (!$username || !$pass) {
				$reason = "You haven't entered a username or password.";
			} elseif ( (stripos($username, '3112')) === true || (stripos($username, '3776')) === true || (stripos($username, '460')) ) {
				$reason = "You have entered a banned username";
			} elseif ($shortpass) {
				$reason = "That password is too short.";
			} elseif (!$retyped) {
				$reason = "That passwords do not match. Re-type it correctly.";
			} else {
				$reason = "Unknown reason.";
			}
			
			errorpage("Couldn't register the account. $reason", "index.php", "the board", 0);
		}
		
		
	} else {
		
		pageheader();
		
		if ($regmode == 3) {
			$entercode = "
		<tr>
			<td class='tdbg1 center'>
				<b>Regcode:</b>
				<div class='fonts'>
					To keep the morons out; contact {$config['admin-name']} for this.
				</div>
			</td>
			<td class='tdbg2'>
				<input type='regcode' name=regcode SIZE=15 MAXLENGTH=64>
			</td>
		</tr>";
		} else {
			$entercode = "";
		}
?>
<form method="POST" action="register.php">
	<br>
	<table class='table'>
		<tr><td class='tdbgh center' colspan=2>Login information</td></tr>
		
		<tr>
			<td class='tdbg1 center'>
				<b>User name:</b>
				<div class='fonts'>
					&nbsp; The name you want to use on the board.
				</div>
			</td>
			<td class='tdbg2' style='width: 50%'>
				<input type='text' autofocus name='name' SIZE=25 MAXLENGTH=25>
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
				<input type='password' name='pass' SIZE=15 MAXLENGTH=64>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>
				<div class='fonts'>
					&nbsp; Retype the password again.
				</div>
			</td>
			<td class='tdbg2'>
				<input type='password' name='pass2' SIZE=15 MAXLENGTH=64>
			</td>
		</tr>
		
		<?=$entercode?>
		
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
	}
 
	pagefooter();
 ?>
