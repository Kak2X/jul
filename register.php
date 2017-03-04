<?php
/*
	if ($_POST['action'] == "Register" && $_POST['homepage']) {
		header("Location: http://acmlm.no-ip.org/board/register.php");
		die();
	}
*/

	require 'lib/function.php';
	
	
	$meta['noindex'] = true;
	
	const T_REGISTER = 40;

	//$ipstart=substr($userip,0,6);
	//print $header;

	$regmode = $sql->resultq("SELECT regmode FROM misc");
	
	/*
		regmode:
		0 - Normal
		1 - Disabled
		2 - Pending users
		3 - regcode
	*/
	
	$isadmin = has_perm('reregister');
	
	if (!$isadmin && $regmode == 1)
		errorpage("Registration is disabled. Please contact an admin if you have any questions.");
	

	$action 	= filter_string($_POST['action']);
	
	if($_POST['action']=='Register') {
		 
		check_token($_POST['auth'], T_REGISTER);
		
		$name = filter_string($_POST['name'], true);
		$pass = filter_string($_POST['pass']);
		

		/*
		if ($name == "Blaster") {
			$sql -> query("INSERT INTO `ipbans` SET `ip` = '". $_SERVER['REMOTE_ADDR'] ."', `date` = '". ctime() ."', `reason` = 'Idiot'");
			@xk_ircsend("1|". xk(7) ."Auto-IP banned Blaster with IP ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." on registration.");
			die("<td class='tdbg1 center'>Thank you, $username, for registering your account.<br>".redirect('index.php','the board',0).$footer);
		}
		*/

		/* do curl here */
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
		
		//print "<table class='table'>";

		if (!$samename && $pass && $pass != "123" && $username && !$shortpass && !$nomultis && !$badcode) {
			
			// The first user is super admin
			switch ($sql->num_rows($users)) {
				case 0: 
					$userlevel = GROUP_SYSADMIN;
					break;
				case ($config['deleted-user-id'] - 1):
					$userlevel = GROUP_PERMABANNED;
					break;
				default:
					$userlevel = GROUP_NORMAL;
			}
			$newuserid 		= $sql->resultq("SELECT MAX(id) FROM users") + 1;
			$currenttime 	= ctime();	
			
			if (!$x_hacks['host'] && $regmode == 2) {
				
				$sql->queryp("
					INSERT INTO `pendingusers` SET `name` = :name, `password` = :password, `ip` = :ip, `time` = :time",
					[
						'name'		=> $name,
						'password'	=> getpwhash($pass, $newuserid),
						'ip'		=> $_SERVER['REMOTE_ADDR'],
						'time'		=> $currenttime,
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

				$sql->queryp("INSERT INTO `users` SET `name` = :name, `password` = :password, `group` = :group, `lastip` = :ip, `lastactivity` = :lastactivity, `regdate` = :regdate, postsperpage = :postsperpage, threadsperpage = :threadsperpage",
					[
						'name'				=> $name,
						'password'			=> getpwhash($pass, $newuserid),
						'group'				=> $userlevel,
						'ip'				=> $_SERVER['REMOTE_ADDR'],
						'lastactivity'		=> $currenttime,
						'regdate'			=> $currenttime,
						'threadsperpage'	=> $config['default-ppp'],
						'postsperpage'		=> $config['default-tpp']
					]);
				
				
				xk_ircout("user", $ircout['name'], $ircout);

				$sql->query("INSERT INTO `users_rpg` (`uid`) VALUES ('{$newuserid}')");
				
				// Automatic registration of deleted user
				if ($newuserid == $config['deleted-user-id']-1) {
					$sql->query("INSERT INTO `users` SET 
						`name`           = 'Deleted User',
						`password`       = '',
						`group`          = '".GROUP_PERMABANNED."',
						`lastip`         = '0.0.0.0',
						`lastactivity`   = '$currenttime',
						`regdate`        = '$currenttime',
						`postsperpage`   = '{$config['default-ppp']}',
						`threadsperpage` = '{$config['default-tpp']}'");
				}
				
				errorpage("Thank you, $username, for registering your account.", 'index.php', 'the board', 0);
			}
			
		} else {

		/*
			if ($password == "123") {
				$sql->query("INSERT INTO `ipbans` (`ip`, `reason`, `date`) VALUES ('". $_SERVER['REMOTE_ADDR'] ."', 'blocked password of 123', '". ctime() ."')");
				errorpage("Thank you, $username, for registering your account.",'index.php','the board',0);
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
			}  elseif ($shortpass) {
				$reason = "That password is too short.";
			} else {
				$reason = "Unknown reason.";
			}
			
			errorpage("Couldn't register the account. $reason", "index.php", "the board", 0);
		}
		
		
	} else {
		
		pageheader();
		
		$maxid = $sql->resultq("SELECT MAX(id) FROM users");
		// Instruct the user if we're about to create a special account.
		switch ($maxid) {
			case 0: 
				$readme = 
					"You are registering the first account on this board.<br>".
					"This account will be automatically given all the privileges.<br>".
					"<br>".
					"After registering and logging in, you may want to check out the <i><b>Admin</b></i> page (accessible from the header links) for further configuration options.";
				break;
			case ($config['deleted-user-id'] - 1):
				$readme = 
					"You are registering the 'Deleted User' account.<br>".
					"This account will automatically be set to the '".$grouplist[GROUP_PERMABANNED]['name']."' group.<br>".
					"<br>".
					"When users are deleted, all of their posts and threads will change ownership to this user.";
				break;
			default:
				$readme = "";
		}
		
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
<?= quick_help($readme) ?>
<form ACTION='register.php' NAME=REPLIER METHOD=POST>
	<table class='table' onload='window.document.REPLIER.name.focus()'>
		<tr><td class='tdbgh center' colspan=2>Login information</td></tr>
		
		<tr>
			<td class='tdbg1 center'>
				<b>User name:</b>
				<div class='fonts'>
					&nbsp; The name you want to use on the board.
				</div>
			</td>
			<td class='tdbg2' style='width: 50%'>
				<input type='text' name=name SIZE=25 MAXLENGTH=25>
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
				<input type='password' name=pass SIZE=15 MAXLENGTH=64>
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
				<input type='hidden' name=auth value="<?=generate_token(T_REGISTER)?>">
			</td>
		</tr>
	</table>
	<div style='visibility: hidden;'><b>Homepage:</b><small> DO NOT FILL IN THIS FIELD. DOING SO WILL RESULT IN INSTANT IP-BAN.</small> - <input type='text' name=homepage SIZE=25 MAXLENGTH=255></div>
	</form>
		<?php
	}
 
	pagefooter();
 ?>
