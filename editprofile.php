<?php

	require 'lib/function.php';
	pageheader();
	
	$id = filter_int($_GET['id']);
	
	if ($id) {
		admincheck();
		$edituser 	= true;
		$titleopt	= 1;
		$id_q		= "?id=$id";
		$userdata	= $sql->fetchq("SELECT u.*,r.gcoins FROM users u LEFT JOIN users_rpg r ON u.id = r.uid WHERE u.id = $id");
		if (!$userdata) {
			errorpage("This user doesn't exist.");
		}
	} else {
		
		if(!$loguser['id'])
			errorpage('You must be logged in to edit your profile.');
		if($banned)
			errorpage("Sorry, but banned users aren't allowed to edit their profile.");
		if($loguser['profile_locked'] == 1)
			errorpage("You are not allowed to edit your profile.");
		
		// Custom title requirements
		if		($loguser['titleoption']==0) $titleopt=0;
		else if ($loguser['titleoption']==1) $titleopt=($loguser['posts']>=500 || ($loguser['posts']>=250 && (ctime()-$loguser['regdate'])>=100*86400));
		else if ($loguser['titleoption']==2) $titleopt=1;
		else 								 $titleopt=0;
		
		$id 		= $loguser['id'];
		$id_q		= "";
		$userdata 	= $loguser;
		$edituser 	= false;
	}
	
	//if($_GET['lol'] || ($loguserid == 1420)) errorpage('<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><object width="100%" height="100%"><param name="movie" value="http://www.youtube.com/v/lSNeL0QYfqo&hl=en_US&fs=1&color1=0x2b405b&color2=0x6b8ab6&autoplay=1"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/lSNeL0QYfqo&hl=en_US&fs=1&color1=0x2b405b&color2=0x6b8ab6&autoplay=1" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="100%" height="100%"></embed></object></div>');
	

	
	
	if (isset($_POST['submit'])) {
		check_token($_POST['auth']);
		
		// Reinforce "Force male / female" gender item effects
		$itemdb = getuseritems($id);
		foreach ($itemdb as $item){
			if 		($item['effect'] == 1) $_POST['sex'] = 1;	// Force female
			else if ($item['effect'] == 2) $_POST['sex'] = 0;	// Force male
		}

		// Reset the date settings in case they match with the default
		$eddateformat 	= filter_string($_POST['dateformat'], true);
		$eddateshort 	= filter_string($_POST['dateshort'], true);
		if ($eddateformat == $config['default-dateformat']) $eddateformat = '';
		if ($eddateshort  == $config['default-dateshort'])  $eddateshort  = '';
		
		
		// \n -> <br> conversion
		$_POST['postheader'] = filter_string($_POST['postheader'], true);
		$_POST['signature'] 	= filter_string($_POST['signature'], true);
		$bio 		= filter_string($_POST['bio'], true);
		sbr(0,$_POST['postheader']);
		sbr(0,$_POST['signature']);
		sbr(0,$bio);
		
		// Make sure the thread layout does exist to prevent "funny" shit
		$tlayout = filter_int($_POST['layout']);
		$valid = $sql->resultq("SELECT id FROM tlayouts WHERE id = $tlayout");
		if (!$valid) $tlayout = 1;	// Regular (no numgfx)
			
		
		/*
			$oldtitle	= "";
			$title		= filter_string($_POST['title'], true);
		while ($oldtitle != $title) {
			$oldtitle = $title;
			$title=preg_replace("'<(b|i|u|s|small|br)>'si", '[\\1]', $title);
			$title=preg_replace("'</(b|i|u|s|small|font)>'si", '[/\\1]', $title);
			$title=preg_replace("'<img ([^>].*?)>'si", '[img \\1]', $title);
			$title=preg_replace("'<font ([^>].*?)>'si", '[font \\1]', $title);
		//   $title=preg_replace("'<[\/\!]*?[^<>]*?>'si", '&lt;\\1&gt;', $title); 
			$title=strip_tags($title);
		//    $title=preg_replace("'<[\/\!]*?[^<>]*?>'si", '&lt;\\1&gt;', $title); 
			$title=preg_replace("'\[font ([^>].*?)\]'si", '<font \\1>', $title);
			$title=preg_replace("'\[img ([^>].*?)\]'si", '<img \\1>', $title);
			$title=preg_replace("'\[(b|i|u|s|small|br)\]'si", '<\\1>', $title);
			$title=preg_replace("'\[/(b|i|u|s|small|font)\]'si", '</\\1>', $title);
			$title=preg_replace("'(face|style|class|size|id)=\"([^ ].*?)\"'si", '', $title);
			$title=preg_replace("'(face|style|class|size|id)=\'([^ ].*?)\''si", '', $title);
			$title=preg_replace("'(face|style|class|size|id)=([^ ].*?)'si", '', $title);
		}*/

		// Changing the password?
		$password 	= filter_string($_POST['pass1']);
		$passchk 	= filter_string($_POST['pass2']);
		if ($password && ($edituser || $password == $passchk)) {	// Make sure we enter the correct password
			$passwordenc = getpwhash($password, $id);
			if ($loguser['id'] == $id) {
				$verifyid = intval(substr($_COOKIE['logverify'], 0, 1));
				$verify = create_verification_hash($verifyid, $hash);
				setcookie('logverify',$verify,2147483647, "/", $_SERVER['SERVER_NAME'], false, true);
			}
		} else { // Sneaky!  But no.
			$passwordenc = $userdata['password'];
		}
		
		if ($issuper) {
			// Always process the backup entry, so it can be updated even when it isn't selected
			$_POST['namecolor'] = filter_string($_POST['namecolor']); // Color input type
			if ($_POST['namecolor']) {
				$namecolor_bak = substr($_POST['namecolor'], 1); // Remove #
				if (!ctype_xdigit($namecolor_bak)) {
					errorpage("What are you trying to accomplish?");
				}
			} else { // This is a failsafe in case a color was never selected previously (causing the ctype check to fail)
				$namecolor_bak = $userdata['namecolor_bak'];
			}
					
			switch (filter_int($_POST['colorspec'])) { // Selection box
				case 0: $namecolor = ""; break;
				case 1: $namecolor = $namecolor_bak; break;
				case 2: $namecolor = "random"; break;
				case 3: $namecolor = "time";   break;
				case 4: $namecolor = "rnbow";  break;
				default: $namecolor = ""; break;
			}
		} else {
			$namecolor = $namecolor_bak = $userdata['namecolor'];
		}
		
		
		$sql->beginTransaction();
		
		// Generally, anything that is allowed to contain HTML goes through xssfilters() here
		// Things that don't will be htmlspecialchars'd when they need to be displayed, so we don't bother 
		
		// Editprofile fields
		$mainval = array(
			// Login info
			'password'			=> $passwordenc,	
			// Appareance
			'title'				=> $titleopt ? xssfilters(filter_string($_POST['title'], true)) : $userdata['title'],
			'namecolor'			=> $namecolor,
			'namecolor_bak'		=> $namecolor_bak,
			'useranks' 			=> isset($_POST['useranks']) ? filter_int($_POST['useranks']) : $userdata['useranks'],
			'css' 				=> xssfilters(filter_string($_POST['css'])), // NOT nl2br'd
			'postheader' 		=> xssfilters($_POST['postheader']),
			'signature' 		=> xssfilters($_POST['signature']),
			// Personal information
			'sex' 				=> numrange(filter_int($_POST['sex']), 0, 2),
			'aka' 				=> filter_string($_POST['aka'], true),
			'realname' 			=> filter_string($_POST['realname'], true),
			'location' 			=> xssfilters(filter_string($_POST['location'], true)),
			'birthday'			=> fieldstotimestamp('birth', '_POST'),
			'bio' 				=> xssfilters($bio),
			// Online services
			'email' 			=> filter_string($_POST['email'], true),
			'privateemail' 		=> filter_int($_POST['privateemail']),
			'icq' 				=> filter_int($_POST['icq']),
			'aim' 				=> filter_string($_POST['aim'], true),
			'imood' 			=> filter_string($_POST['imood'], true),
			'homepageurl' 		=> filter_string($_POST['homepageurl'], true),
			'homepagename'	 	=> filter_string($_POST['homepagename'], true),
			// Options
			'dateformat' 		=> $eddateformat,
			'dateshort' 		=> $eddateshort,
			'timezone' 			=> filter_int($_POST['timezone']),
			'postsperpage' 		=> filter_int($_POST['postsperpage']),
			'threadsperpage'	=> filter_int($_POST['threadsperpage']),
			'posttool'			=> filter_int($_POST['posttool']),
			'viewsig'			=> numrange(filter_int($_POST['viewsig']), 0, 2),
			'pagestyle' 		=> numrange(filter_int($_POST['pagestyle']), 0, 1),
			'pollstyle' 		=> numrange(filter_int($_POST['pollstyle']), 0, 1),
			'layout' 			=> $tlayout,
			'signsep' 			=> numrange(filter_int($_POST['signsep']), 0, 3),
			'scheme' 			=> filter_int($_POST['scheme']),
			'hideactivity' 		=> filter_int($_POST['hideactivity']),
			'splitcat' 			=> filter_int($_POST['splitcat']),
			'schemesort' 		=> filter_int($_POST['schemesort']),
		);
		
		if ($config['allow-avatar-storage']) {

			// Erase minipic
			if (filter_int($_POST['del_minipic'])){
				del_minipic($id); // will check on its own
			}		
			// Upload a new minipic
			else if (filter_int($_FILES['minipic']['size'])){
				upload_avatar(
					$_FILES['minipic'], 
					$config['max-minipic-size-bytes'], 
					$config['max-minipic-size-x'], 
					$config['max-minipic-size-y'], 
					avatar_path($id, 'm') // minipic path
				);
			}
			
			// Same for the avatar
			$weblink = xssfilters(trim(filter_string($_POST['picture_weblink'])));
			if (filter_int($_POST['del_picture'])){
				delete_avatar($id, 0);
			}
			else if (filter_int($_FILES['picture']['size'])){
				upload_avatar(
					$_FILES['picture'], 
					$config['max-avatar-size-bytes'], 
					$config['max-avatar-size-x'], 
					$config['max-avatar-size-y'], 
					avatar_path($id, 0),
					[$id, 0, 'Default', 0, $weblink]
				);
			} else if ($weblink || file_exists(avatar_path($id, 0))) {
				// Make sure we don't accidentaly delete the entire avatar if we just blank the URL
				save_avatar([$id, 0, 'Default', 0, $weblink]);
			} else {		
				// File doesn't exist + blanking the URL = delete avatar
				delete_avatar($id, 0);
			}
			
		} else {
			$mainval['picture'] 	= filter_string($_POST['picture']);
			$mainval['minipic'] 	= filter_string($_POST['minipic']);
			$mainval['moodurl'] 	= filter_string($_POST['moodurl']);			
		}
		
		$userset = mysql::setplaceholders($mainval);
		
		if ($edituser) {
			
			if ($id == 1 && $loguser['id'] != 1) {
				xk_ircsend("1|". xk(7) ."Someone (*cough* {$loguser['id']} *cough*) is trying to be funny...");
			}
		
			 //$sql->query("INSERT logs SET useraction ='Edit User ".$user[nick]."(".$user[id]."'");
			 
			 // Do the double name check here
			$users = $sql->query('SELECT name FROM users');
			
			$username  = substr(xssfilters(filter_string($_POST['name'], true)),0,25);
			$username2 = str_replace(' ','',$username);
			$username2 = preg_replace("'&nbsp;?'si",'',$username2);
			
			$samename = NULL;
			while ($user = $sql->fetch($users)) {
				$user['name'] = str_replace(' ','',$user['name']);
				if (strcasecmp($user['name'], $username2) == 0) $samename = $user['name'];
			}
			
			// Extra edituser fields
			$adminval = array(
				
				'name'				=> ($samename || !$username) ? $userdata['name'] : $username,
				// No "Imma become a root admin" bullshit
				'powerlevel' 		=> $sysadmin ? filter_int($_POST['powerlevel']) : min(3, filter_int($_POST['powerlevel'])),
				'regdate'			=> fieldstotimestamp('reg', '_POST'),
				'posts'				=> filter_int($_POST['posts']),
				'profile_locked'	=> filter_int($_POST['profile_locked']),
				'editing_locked'	=> filter_int($_POST['editing_locked']),
				'avatar_locked'     => filter_int($_POST['avatar_locked']),
				'uploads_locked'	=> filter_int($_POST['uploads_locked']),
				'rating_locked'		=> filter_int($_POST['rating_locked']),
				'titleoption'		=> filter_int($_POST['titleoption']),
				'ban_expire'		=> ($_POST['powerlevel'] == -1 && filter_int($_POST['ban_hours']) > 0) ? (ctime() + filter_int($_POST['ban_hours']) * 3600) : 0,
			);
	
			$adminset = mysql::setplaceholders($adminval).",";
			
			// Green coins support
			$gcoins = filter_int($_POST['gcoins']);
			$sql->query("UPDATE users_rpg SET gcoins = $gcoins WHERE uid = $id");
		} else {
			$adminval = array();
			$adminset = "";
		}
		
		$sql->queryp("
			UPDATE users SET {$adminset}{$userset}
			WHERE id = {$id}", array_merge($adminval, $mainval));
		
		$sql->commit();
		if (!$edituser)	{
			errorpage("Thank you, {$loguser['name']}, for editing your profile.","profile.php?id=$id",'view your profile',0);
		} else { 
			errorpage("Thank you, {$loguser['name']}, for editing this user.","profile.php?id=$id","view {$userdata['name']}'s profile",0);
		}
		
	}
	else {
		
		$splitcount = $sql->resultq("SELECT COUNT(*) FROM `users` WHERE `splitcat` = '1'");
		//squot(0,$userdata['title']);
		//squot(0,$userdata['realname']);
		//squot(0,$userdata['aka']);
		//squot(0,$userdata['location']);
		//    squot(1,$userdata['aim']);
		//    squot(1,$userdata['imood']);
		//squot(0,$userdata['email']);
		//    squot(1,$userdata['homepageurl']);
		//squot(0,$userdata['homepagename']);
		sbr(1,$userdata['postheader']);
		sbr(1,$userdata['signature']);
		sbr(1,$userdata['bio']);
		
		/*
			A ""slightly updated"" version of the table system from boardc
			(You can now set a maxlength for input fields)
		*/

		table_format("Login information", array(
			"User name" 	=> [4, "name", "If you want to change this, ask an admin.", 25, 25], // static
			"Password"		=> [4, "password", "You can change your password by entering a new one here."], // password field
		));
		
		if ($edituser) {
			// Set type from static to input, as an admin should be able to do that.
			$fields["Login information"]["User name"][0] = 0;
			
			// ... and also gets the extra "Administrative bells and whistles"
			table_format("Administrative bells and whistles", array(
				"Power level" 				=> [4, "powerlevel", ""], // Custom listbox with negative values.
				"Ban duration"			    => [4, "ban_hours", ""],
				"Number of posts"			=> [0, "posts", "", 6, 10],
				"Registration date"			=> [4, "regdate", ""],
				"Lock Profile"				=> [2, "profile_locked", "", "Unlocked|Locked"],
				"Restrict Editing"			=> [2, "editing_locked", "", "Unlocked|Locked"],
				"Restrict Avatar Uploads"	=> [2, "avatar_locked", "", "Unlocked|Locked"],
				"Restrict File Uploads"     => [2, "uploads_locked", "", "Unlocked|Locked"],
				"Restrict Post Rating"      => [2, "rating_locked", "", "Unlocked|Locked"],
				"Custom Title Privileges" 	=> [2, "titleoption", "", "Revoked|Determine by rank/posts|Enabled"],
			));
		}
		
		if ($titleopt) {
			table_format("Appareance", array(
				"Custom title" => [0, "title", "This title will be shown below your rank.", 60, 255],
			));
		}
		if ($issuper) {
			table_format("Appareance", array(
				"Name color" 	=> [4, "namecolor", "Your username will be shown using this color."],
			));
		}
		table_format("Appareance", array(
			"User rank"         => [4, "useranks", "You can hide your rank, or choose from different sets."],
		));
		if ($config['allow-avatar-storage']) {
			table_format("Appareance", array(
				"Avatar"	 => [4, "picture", "The image showing up below your username in posts. Select an image to upload."],
				"Minipic"	 => [4, "minipic", "This picture will appear next to your username. Select an image to upload."],
			));
		} else {
			table_format("Appareance", array(
				"Avatar"            => [0, "picture", "The full URL of the image showing up below your username in posts. Leave it blank if you don't want to use a avatar. Anything over {$config['max-avatar-size-x']}&times;{$config['max-avatar-size-y']} pixels will be removed.", 60, 100],
				"Mood avatar"       => [0, "moodurl", "The URL of a mood avatar set. '\$' in the URL will be replaced with the mood, e.g. <b>http://your.page/here/\$.png</b>!", 60, 100],
				"Minipic"           => [0, "minipic", "The full URL of a small picture showing up next to your username on some pages. Leave it blank if you don't want to use a picture. The picture is resized to {$config['max-minipic-size-x']}&times;{$config['max-minipic-size-y']}.", 60, 100],
			));
		}
		table_format("Appareance", array(
			"Post layout"       => [1, "css", "CSS added here will be added on its own tag.", 16],
			"Post header"       => [1, "postheader", "HTML added here will come before your post."],
			"Footer/Signature" 	=> [1, "signature", "HTML and text added here will be added to the end of your post."],
		));		
		
		table_format("Personal information", array(
			"Sex" 		    => [2, "sex", "Male or female. (or N/A if you don't want to tell it).", "Male|Female|N/A"],
			"Also known as" => [0, "aka", "If you go by an alternate alias (or are constantly subjected to name changes), enter it here.  It will be displayed in your profile if it doesn't match your current username.", 25, 25],
			"Real name"     => [0, "realname", "Your real name (you can leave this blank).", 40],
			"Location" 	    => [0, "location", "Where you live (city, country, etc.).", 40],
			"Birthday"	    => [4, "birthday", "Your date of birth."],
			"Bio"		    => [1, "bio", " Some information about yourself, showing up in your profile. Accepts HTML."],
		));

		table_format("Online services", array(
			"Email address" 	=> [0, "email", "This is only shown in your profile; you don't have to enter it if you don't want to.", 60, 60],
			"Email privacy" 	=> [2, "privateemail", "You can select a few privacy options for the email field.", "Public|Hide to guests|Private"],
			"AIM screen name" 	=> [0, "aim", "Your AIM screen name, if you have one.", 30, 30],
			"ICQ number" 		=> [0, "icq", "Your ICQ number, if you have one.", 10, 10],
			"imood" 			=> [0, "imood", "If you have a imood account, you can enter the account name (email) for it here.", 60, 100],
			"Homepage URL" 		=> [0, "homepageurl", "Your homepage URL (must start with the \"http://\") if you have one.", 60, 80],
			"Homepage Name" 	=> [0, "homepagename", "Your homepage name, if you have a homepage.", 60, 100],
		));
		
		table_format("Options", array(
			"Custom date format" 			=> [0, "dateformat", "Change how dates are displayed. Uses <a href='http://php.net/manual/en/function.date.php'>date()</a> formatting. Leave blank to use the default.", 16, 32],
			"Custom short date format" 		=> [0, "dateshort", "Change how abbreviated dates are displayed. Uses the same formatting. Leave blank to reset.", 8, 16],
			"Timezone offset"	 			=> [0, "timezone", "How many hours you're offset from the time on the board (".date($loguser['dateformat'],ctime()).").", 5, 5],
			"Posts per page"				=> [0, "postsperpage", "The maximum number of posts you want to be shown in a page in threads.", 3, 3],
			"Threads per page"	 			=> [0, "threadsperpage", "The maximum number of threads you want to be shown in a page in forums.", 3, 3],
			"Use post toolbar" 				=> [2, "posttool", "You can disable it here, which can make thread pages smaller and load faster.", "Disabled|Enabled"],
			"Post layouts"	                => [2, "viewsig", "You can disable them here, which can make thread pages smaller and load faster.", "Disabled|Enabled|Auto-updating"],
			"Forum List layout"				=> [2, "splitcat", "'Split' uses two columns instead of one.", "Normal|Split ({$splitcount})"],
			"Forum page list style"			=> [2, "pagestyle", "Inline (Title - Pages ...) or Seperate Line (shows more pages)", "Inline|Seperate line"],
			"Poll vote system"				=> [2, "pollstyle", "Normal (based on users) or Influence (based on levels)", "Normal|Influence"],
			"Thread layout"					=> [4, "layout", "You can choose from a few thread layouts here."],
			"Signature separator"			=> [4, "signsep", "You can choose from a few signature separators here."],
			"Color scheme / layout"	 		=> [4, "scheme", "You can select from a few color schemes here."],
			"Scheme sorting mode"	 		=> [2, "schemesort", "Determines how scheme lists are sorted.", "Normal|Alphabetical"],
			"Hide activity"			 		=> [2, "hideactivity", "You can choose to hide your online status.", "Show|Hide"],
		));
		if ($edituser){
			table_format("Options", array(
				"Green coins" 	=> [0, "gcoins", "", 10, 10],
			));
		}		
		
		/*
			Custom values (used when first value in array is set to 4)
		*/
		
		// Static text for the username (shown when editing your own profile)
		$name = $userdata['name'];
		
		// Password field + confirmation (unless you're editing another user)
		$password = "<input type='password' name='pass1'>";
		if (!$edituser)	$password .= " Retype: <input type='password' name='pass2'>";
	
	
		$birthday = datetofields($userdata['birthday'], 'birth');
		
		// The namecolor field is special
		// Usually it contains an hexadecimal number, but it can take extra text values for special effects
		// Because both the coloropt and namecolor are stored in the same field
		// A second "backup" field is used to preserve the user's name color choice from the color picker
		if ($userdata['namecolor'] && ctype_xdigit($userdata['namecolor'])) { // Color defined
			$userdata['namecolor'] = '#'.$userdata['namecolor']; // Input type color compat
			$sel_color[1] = 'checked=1';
		} else {	// Special effect
			switch ($userdata['namecolor']) {
				case 'random': $coloropt = 2; break;
				case 'time':   $coloropt = 3; break;
				case 'rnbow':  $coloropt = 4; break;
				default:       $coloropt = 0; break;	
			}
			$userdata['namecolor'] = '#'.$userdata['namecolor_bak'];
			$sel_color[$coloropt] = 'checked=1';
		}

		$namecolor = " 
		<input type=radio class='radio' name=colorspec value=0 ".filter_string($sel_color[0]).">None 
		<input type=radio class='radio' name=colorspec value=1 ".filter_string($sel_color[1]).">Defined: <input type='color' name=namecolor VALUE=\"{$userdata['namecolor']}\" SIZE=7 MAXLENGTH=7> 
		<input type=radio class='radio' name=colorspec value=2 ".filter_string($sel_color[2]).">Random 
		<input type=radio class='radio' name=colorspec value=3 ".filter_string($sel_color[3]).">Time-dependent 
		<input type=radio class='radio' name=colorspec value=4 ".filter_string($sel_color[4]).">Rainbow";
		
		// Upload a new minipic / Remove the existing one
		$minipic = "
			<input type='hidden' name='MAX_FILE_SIZE' value='{$config['max-minipic-size-bytes']}'>
			<input name='minipic' type='file'>
			<input type='checkbox' id='del_minipic' name='del_minipic' value=1><label for='del_minipic'>Remove minipic</label><br>
			<small>
				Max size: {$config['max-minipic-size-x']}x{$config['max-minipic-size-y']} | ".sizeunits($config['max-minipic-size-bytes'])."
			</small>
		";
		
		// Same for the picture
		$weblink = $sql->resultq("SELECT weblink FROM users_avatars WHERE user = {$id} AND file = 0");
		$picture = "
			<input type='hidden' name='MAX_FILE_SIZE' value='{$config['max-avatar-size-bytes']}'>
			<input name='picture' type='file'>
			<input type='checkbox' id='del_picture' name='del_picture' value=1><label for='del_picture'>Remove avatar</label><br>
			<small>
				Max size: {$config['max-avatar-size-x']}x{$config['max-avatar-size-y']} | ".sizeunits($config['max-avatar-size-bytes'])."
			</small><br/>
			External URL: <input type='text' name='picture_weblink' size=60 maxlength=127 value=\"".htmlspecialchars($weblink)."\">
		";
		
		if ($edituser) {
			// Powerlevel selection
			$powerlevel = power_select('powerlevel', $userdata['powerlevel'], $loguser['powerlevel']);
			
			// Registration time
			$regdate = datetofields($userdata['regdate'], 'reg', DTF_DATE | DTF_TIME);
			
			// Hours left before the user is unbanned
			$ban_hours = ban_hours('ban_hours', $userdata['ban_expire'], ($userdata['powerlevel'] == -1))." (has effect only for Banned users)";
		}

		
		// listbox with <name> <used>
		if (!$edituser && !$isadmin) {
			// herp derp
			$sortmode = $userdata['schemesort'] ? "name" : "ord";
			$scheme   = queryselectbox('scheme',   "SELECT s.id as id, s.name, COUNT(u.scheme) as used FROM schemes s LEFT JOIN users u ON (u.scheme = s.id) WHERE s.ord > 0 AND (!s.special OR s.id = {$userdata['scheme']}) GROUP BY s.id ORDER BY s.{$sortmode}");
		} else {
			$scheme = doschemelist(true, $userdata['scheme'], 'scheme');
		}
		$layout   = queryselectbox('layout',   'SELECT tl.id as id, tl.name, COUNT(u.layout) as used FROM tlayouts tl LEFT JOIN users u ON (u.layout = tl.id) GROUP BY tl.id ORDER BY tl.ord');
		$useranks = queryselectbox('useranks', 'SELECT rs.id as id, rs.name, COUNT(u.useranks) as used FROM ranksets rs LEFT JOIN users u ON (u.useranks = rs.id) GROUP BY rs.id ORDER BY rs.id');
		
		
		$used = $sql->getresultsbykey('SELECT signsep, count(*) as cnt FROM users GROUP BY signsep');
		$signsep = "";
		for($i = 0; isset($sepn[$i]); ++$i){
				$sel = ($i==$userdata['signsep'] ? ' selected' : '');
				$signsep .= "<option value={$i}{$sel}>{$sepn[$i]} (".filter_int($used[$i]).")";
		}
		$signsep="<select name='signsep'>$signsep</select>";
		
		/*
			Table field generator
			Now updated to use the 'new' (commit c028c21269e1d87d0dbce8bf50c7c4b68a2fbfda) layout
		*/
		$t = "";
		foreach($fields as $i => $field){
			$t .= "<tr><td class='tdbgh center' colspan=2>$i</td></tr>";
			foreach($field as $j => $data){
				$desc = $edituser ? "" : "<br><small>$data[2]</small>";
				if (!$data[0]) { // text box
					if (!isset($data[3])) $data[3] = 60;
					if (!isset($data[4])) $data[4] = 100;
					$input = "<input type='text' name='$data[1]' size={$data[3]} maxlength={$data[4]} value=\"".htmlspecialchars($userdata[$data[1]])."\">";
				}
				else if ($data[0] == 1) { // large
					if (!isset($data[3])) $data[3] = 8; // Rows
					$input = "<textarea name='$data[1]' rows={$data[3]} style='width: 100%' wrap='virtual'>".htmlspecialchars($userdata[$data[1]])."</textarea>";
				}
				else if ($data[0] == 2){ // radio
					$ch[$userdata[$data[1]]] = "checked"; //example $sex[$user['sex']]
					$choices = explode("|", $data[3]);
					$input = "";
					foreach($choices as $i => $x)
						$input .= "<input name='$data[1]' type='radio' value=$i ".filter_string($ch[$i]).">&nbsp;$x&nbsp;&nbsp;&nbsp; ";
					unset($ch);
				}
				else if ($data[0] == 3){ // listbox
					$ch[$userdata[$data[1]]] = "selected";
					$choices = explode("|", $data[3]);
					$input = "";
					foreach($choices as $i => $x)
						$input .= "<option value=$i ".filter_string($ch[$i]).">$x</option>";
					$input = "<select name='$data[1]'>$input</select>";
					unset($ch);
				}
				else
					$input = ${$data[1]};
					
				$t .= "<tr><td class='tdbg1 center'><b>$j:</b>$desc</td><td class='tdbg2'>$input</td></tr>";
			}
		}
	}
	
	
	// Hack around autocomplete, fake inputs (don't use these in the file) 
	// Web browsers think they're smarter than the web designer, so they ignore demands to not use autocomplete.
	// This is STUPID AS FUCK when you're working on another user, and not YOURSELF.
	
	$finput = $edituser ? '<input style="display:none" type="text" name="__f__usernm__"><input style="display:none" type="password" name="__f__passwd__">' : "";
	
	?>
	<br>
	<form method="POST" action="editprofile.php<?=$id_q?>" enctype="multipart/form-data" autocomplete=off>
	<table class='table'>
		<tr style='display: none'><td><?=$finput?></td></tr>
		<?=$t?>
		<tr><td class='tdbgh center' colspan=2>&nbsp;</td></tr>
		<tr>
			<td class='tdbg1 center' style='width: 40%'>&nbsp;</td>
			<td class='tdbg2' style='width: 60%'>
		<?= auth_tag() ?>
		<input type='submit' class=submit name=submit VALUE="Edit <?=($edituser ? "user" : "profile")?>">
		</td>
	</table>
	</form>
	<?php
	
	pagefooter();

	function table_format($name, $array){
		global $fields;
		
		if (isset($fields[$name])){ // Already exists: merge arrays
			$fields[$name] = array_merge($fields[$name], $array);
		} else { // It doesn't: Create a new one.
			$fields[$name] = $array;
		}
	}
	
	// When it comes to copy / pasted code...
	function queryselectbox($val, $query) {
		global $sql, $userdata;
		$txt = "";
		$q = $sql->query($query);
		while ($x = $sql->fetch($q, PDO::FETCH_ASSOC)) {
			$sel = ($x['id'] == $userdata[$val] ? ' selected' : '');
			$txt .=" <option value={$x['id']}{$sel}>{$x['name']} ({$x['used']})</option>\n\r";			
		}
		return "<select name='$val'>$txt</select>";
	}
	
	