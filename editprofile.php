<?php

	require "lib/common.php";
	load_layout();
	
	$id = filter_int($_GET['id']);
	$noeffect = "";
	
	if ($id) {
		admincheck();
		$edituser 	= true;
		$titleopt	= 1;
		$id_q		= "?id=$id";
		$userdata	= $sql->fetchq("SELECT u.*,r.gcoins,r.damage,r.class FROM users u LEFT JOIN users_rpg r ON u.id = r.uid WHERE u.id = $id");
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
		else if ($loguser['titleoption']==1) $titleopt=($issuper || $loguser['posts']>=500 || ($loguser['posts']>=250 && (time()-$loguser['regdate'])>=100*86400));
		else if ($loguser['titleoption']==2) $titleopt=1;
		else 								 $titleopt=0;
		
		$id 		= $loguser['id'];
		$id_q		= "";

		// Usually you can get away with reusing $loguser data for this
		// Not on mobile mode though, as certain options are hardcoded to $loguser
		if (!$x_hacks['smallbrowse']) {
			$userdata 	= $loguser;
		} else {
			//die("what are you doing. just stop");
			$userdata = $sql->fetchq("SELECT * FROM users WHERE id = $id");
			$noeffect = "<br><b>This option will currently have no effect as you're using a mobile browser.</b>";
		}
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


		// With date formats, the preset has priority
		$eddateformat = filter_string($_POST['datepreset']);
		if (!$eddateformat) $eddateformat = filter_string($_POST['dateformat']);
		$eddateshort = filter_string($_POST['dateshortpreset']);
		if (!$eddateshort) $eddateshort = filter_string($_POST['dateshort']);	
		
		// Also reset the date settings in case they match with the default
		if ($eddateformat == $config['default-dateformat']) $eddateformat = '';
		if ($eddateshort  == $config['default-dateshort'])  $eddateshort  = '';
		
		
		// \n -> <br> conversion
		$_POST['postheader'] = filter_string($_POST['postheader']);
		$_POST['signature'] 	= filter_string($_POST['signature']);
		$bio 		= filter_string($_POST['bio']);
		sbr(0,$_POST['postheader']);
		sbr(0,$_POST['signature']);
		sbr(0,$bio);
		
		// Make sure the thread layout does exist to prevent "funny" shit
		$tlayout = filter_int($_POST['layout']);
		$valid = $sql->resultq("SELECT id FROM tlayouts WHERE id = $tlayout");
		if (!$valid) $tlayout = 1;	// Regular (no numgfx)

		// Changing the password?
		$password 	= filter_string($_POST['pass1']);
		$passchk 	= filter_string($_POST['pass2']);
		if ($password && ($edituser || $password == $passchk)) {	// Make sure we enter the correct password
			$passwordenc = getpwhash($password, $id);
			if ($loguser['id'] == $id) {
				$verifyid = intval(substr($_COOKIE['logverify'], 0, 1));
				$verify = create_verification_hash($verifyid, $passwordenc);
				set_board_cookie('logverify', $verify);
			}
		} else { // Sneaky!  But no.
			$passwordenc = $userdata['password'];
		}
		
		if ($issuper) {
			
			// The form fields for the namecolor are different than what's saved on the DB, so that we only need to fetch one outside of editprofile.
			// The selected color (hex color or a special string) goes to 'namecolor'.
			// To keep track of the selected *hex* color even when it's not used, a copy of that goes to 'namecolor_bak'.
			if (!($namecolor_bak = parse_color_input(filter_string($_POST['namecolor'])))) // Color input type
				$namecolor_bak = $userdata['namecolor_bak']; // Failsafe in case an hex color was never selected previously
					
			switch (filter_int($_POST['colorspec'])) { // Selection box
				case 0: $namecolor = ""; break;
				case 1: $namecolor = $namecolor_bak; break;
				case 2: $namecolor = "random"; break;
				case 3: $namecolor = "time";   break;
				case 4: $namecolor = "rnbow";  break;
				default: $namecolor = ""; break;
			}
			// Custom sidebar HTML
			$_POST['sidebar'] = filter_string($_POST['sidebar']);
			$_POST['sidebartype'] = sidebartype_db($_POST['sidebartype'], $_POST['sidebarcell']);
		} else {
			$namecolor = $userdata['namecolor'];
			$namecolor_bak = $userdata['namecolor_bak'];
			$_POST['sidebar'] = $userdata['sidebar'];
			$_POST['sidebartype'] = $userdata['sidebartype'];
		}
		
		$scheme = filter_int($_POST['scheme']);
		if (!can_select_scheme($scheme))
			errorpage("'Inspect element' doesn't cut it here. Thanks for trying though.");
		
		$fontsize = filter_int($_POST['fontsize']);
		if (!$fontsize) 
			$fontsize = null;
		
		$sql->beginTransaction();
		
		// Editprofile fields
		$mainval = array(
			// Login info
			'password'			=> $passwordenc,	
			// Appareance
			'title'				=> $titleopt ? filter_string($_POST['title']) : $userdata['title'],
			'namecolor'			=> $namecolor,
			'namecolor_bak'		=> $namecolor_bak,
			'useranks' 			=> isset($_POST['useranks']) ? filter_int($_POST['useranks']) : $userdata['useranks'],
			'css' 				=> filter_string($_POST['css']), // NOT nl2br'd
			'postheader' 		=> $_POST['postheader'],
			'signature' 		=> $_POST['signature'],
			'sidebar'			=> $_POST['sidebar'],
			'sidebartype'		=> $_POST['sidebartype'],
			// Personal information
			'sex' 				=> numrange(filter_int($_POST['sex']), 0, 2),
			'aka' 				=> filter_string($_POST['aka']),
			'realname' 			=> filter_string($_POST['realname']),
			'location' 			=> filter_string($_POST['location']),
			'birthday'			=> fieldstotimestamp('birth', '_POST'),
			'bio' 				=> $bio,
			// Online services
			'email' 			=> filter_string($_POST['email']),
			'privateemail' 		=> filter_int($_POST['privateemail']),
			'icq' 				=> filter_int($_POST['icq']),
			'aim' 				=> filter_string($_POST['aim']),
			'imood' 			=> filter_string($_POST['imood']),
			'homepageurl' 		=> filter_string($_POST['homepageurl']),
			'homepagename'	 	=> filter_string($_POST['homepagename']),
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
			'scheme' 			=> $scheme,
			'fontsize'			=> $fontsize,
			'hideactivity' 		=> filter_int($_POST['hideactivity']),
			'splitcat' 			=> filter_int($_POST['splitcat']),
			'schemesort' 		=> filter_int($_POST['schemesort']),
			'comments' 			=> filter_int($_POST['comments']),
			'ajax' 				=> filter_int($_POST['ajax']),
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
			$weblink = trim(filter_string($_POST['picture_weblink']));
			if (filter_int($_POST['del_picture'])) {
				delete_avatar($id, 0);
			} else if (filter_int($_FILES['picture']['size'])) {
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
				report_send(IRC_STAFF, xk(7)."Someone (*cough* {$loguser['id']} '{$loguser['name']}' *cough*) is trying to be funny...");
			}
		
			 //$sql->query("INSERT logs SET useraction ='Edit User ".$user[nick]."(".$user[id]."'");
			 
			 // Do the double name check here
			$users = $sql->query('SELECT name FROM users');
			
			$username = substr(filter_string($_POST['name']), 0, 32);
			$samename = $sql->resultp("SELECT id FROM users WHERE id != {$id} AND ? IN (LOWER(REPLACE(name, ' ', '')), LOWER(REPLACE(displayname, ' ', '')))", [strtolower(str_replace(' ', '', $username))]);
			
			
			$displayname = substr(filter_string($_POST['displayname']), 0, 32);
			$samedisplay = $displayname && $sql->resultp("SELECT id FROM users WHERE id != {$id} AND ? IN (LOWER(REPLACE(name, ' ', '')), LOWER(REPLACE(displayname, ' ', '')))", [strtolower(str_replace(' ', '', $displayname))]);
			
			// Extra edituser fields
			$adminval = array(
				
				'name'				=> ($samename || !$username) ? $userdata['name'] : $username,
				'displayname'       => $samedisplay ? $userdata['displayname'] : $displayname,
				// No "Imma become a root admin" bullshit
				'powerlevel' 		=> $sysadmin ? filter_int($_POST['powerlevel']) : min(3, filter_int($_POST['powerlevel'])),
				'regdate'			=> fieldstotimestamp('reg', '_POST'),
				'posts'				=> filter_int($_POST['posts']),
				'profile_locked'	=> filter_int($_POST['profile_locked']),
				'editing_locked'	=> filter_int($_POST['editing_locked']),
				'avatar_locked'     => filter_int($_POST['avatar_locked']),
				'uploads_locked'	=> filter_int($_POST['uploads_locked']),
				'uploader_locked'	=> filter_int($_POST['uploader_locked']),
				'rating_locked'		=> filter_int($_POST['rating_locked']),
				'titleoption'		=> filter_int($_POST['titleoption']),
				'ban_expire'		=> ($_POST['powerlevel'] == -1 && filter_int($_POST['ban_hours']) > 0) ? (time() + filter_int($_POST['ban_hours']) * 3600) : 0,
			);
	
			$adminset = mysql::setplaceholders($adminval).",";
			
			// RPG editing
			$rpgval = [
				'gcoins' => filter_int($_POST['gcoins']),
				'damage' => filter_int($_POST['damage']),
			];
			$class = filter_int($_POST['class']);
			if (!$class || $sql->resultq("SELECT COUNT(*) FROM rpg_classes WHERE id = {$class}"))
				$rpgval['class'] = $class;
			$sql->queryp("UPDATE users_rpg SET ".mysql::setplaceholders($rpgval)." WHERE uid = $id", $rpgval);
		} else {
			$adminval = array();
			$adminset = "";
		}
		
		$sql->queryp("
			UPDATE users SET {$adminset}{$userset}
			WHERE id = {$id}", array_merge($adminval, $mainval));
		
		$sql->commit();
		if (!$edituser)	{
			errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for editing your profile.","profile.php?id=$id",'view your profile',0);
		} else { 
			errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for editing this user.","profile.php?id=$id","view {$userdata['name']}'s profile",0);
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

		_table_format("Login information", array(
			"User name" 	=> [4, "name", "", 32, 32], // static
			"Display name" 	=> [4, "displayname", "If you want to change this, ask an admin.", 32, 32], // static
			"Password"		=> [4, "password", "You can change your password by entering a new one here."], // password field
		));
		
		if ($edituser) {
			// Set type from static to input, as an admin should be able to do that.
			$fields["Login information"]["User name"][0] = 0;
			$fields["Login information"]["Display name"][0] = 0;
			
			// ... and also gets the extra "Administrative bells and whistles"
			_table_format("Administrative bells and whistles", array(
				"Power level" 				=> [4, "powerlevel", ""], // Custom listbox with negative values.
				"Ban duration"			    => [4, "ban_hours", ""],
				"Number of posts"			=> [0, "posts", "", 6, 10],
				"Registration date"			=> [4, "regdate", ""],
				"Lock Profile"				=> [2, "profile_locked", "", "Unlocked|Locked"],
				"Restrict Editing"			=> [2, "editing_locked", "", "Unlocked|Locked|Locked (but hidden)"],
				"Restrict Avatar Uploads"	=> [2, "avatar_locked", "", "Unlocked|Locked"],
				"Restrict Attachments"      => [2, "uploads_locked", "", "Unlocked|Locked"],
				"Restrict Uploader"         => [2, "uploader_locked", "", "Unlocked|Locked"],
				"Restrict Post Rating"      => [2, "rating_locked", "", "Unlocked|Locked"],
				"Custom Title Privileges" 	=> [2, "titleoption", "", "Revoked|Determine by rank/posts|Enabled"],
			));
		}
		
		if ($titleopt) {
			_table_format("Appareance", array(
				"Custom title" => [0, "title", "This title will be shown below your rank.", 60, 255],
			));
		}
		if ($issuper) {
			_table_format("Appareance", array(
				"Name color" 	=> [4, "namecolor", "Your username will be shown using this color."],
			));
		}
		_table_format("Appareance", array(
			"User rank"         => [4, "useranks", "You can hide your rank, or choose from different sets."],
		));
		if ($config['allow-avatar-storage']) {
			_table_format("Appareance", array(
				"Avatar"	 => [4, "picture", "The image showing up below your username in posts. Select an image to upload."],
				"Minipic"	 => [4, "minipic", "This picture will appear next to your username. Select an image to upload."],
			));
		} else {
			_table_format("Appareance", array(
				"Avatar"            => [0, "picture", "The full URL of the image showing up below your username in posts. Leave it blank if you don't want to use a avatar. Anything over {$config['max-avatar-size-x']}&times;{$config['max-avatar-size-y']} pixels will be removed.", 60, 100],
				"Mood avatar"       => [0, "moodurl", "The URL of a mood avatar set. '\$' in the URL will be replaced with the mood, e.g. <b>http://your.page/here/\$.png</b>!", 60, 100],
				"Minipic"           => [0, "minipic", "The full URL of a small picture showing up next to your username on some pages. Leave it blank if you don't want to use a picture. The picture is resized to {$config['max-minipic-size-x']}&times;{$config['max-minipic-size-y']}.", 60, 100],
			));
		}
		_table_format("Appareance", array(
			"Post layout"       => [1, "css", "CSS added here will be added on its own tag.", 16],
			"Post header"       => [1, "postheader", "HTML added here will come before your post."],
			"Footer/Signature" 	=> [1, "signature", "HTML and text added here will be added to the end of your post."],
		));		
		if ($issuper) {
			_table_format("Appareance", array(
				"Sidebar"       => [1, "sidebar", "HTML added here will be used for the post sidebar in the regular or extended layout. Leave blank to use the default sidebar."],
				"Sidebar type"  => [4, "sidebartype", "You can select a few different sidebar modes."],
			));
		}
		
		_table_format("Personal information", array(
			"Sex" 		    => [2, "sex", "Male or female. (or N/A if you don't want to tell it).", "Male|Female|N/A"],
			"Also known as" => [0, "aka", "If you go by an alternate alias (or are constantly subjected to name changes), enter it here.  It will be displayed in your profile if it doesn't match your current username.", 25, 25],
			"Real name"     => [0, "realname", "Your real name (you can leave this blank).", 40],
			"Location" 	    => [0, "location", "Where you live (city, country, etc.).", 40],
			"Birthday"	    => [4, "birthday", "Your date of birth."],
			"Bio"		    => [1, "bio", " Some information about yourself, showing up in your profile. Accepts HTML."],
		));

		_table_format("Online services", array(
			"Email address" 	=> [0, "email", "This is only shown in your profile; you don't have to enter it if you don't want to.", 60, 60],
			"Email privacy" 	=> [2, "privateemail", "You can select a few privacy options for the email field.", "Public|Hide to guests|Private"],
			"AIM screen name" 	=> [0, "aim", "Your AIM screen name, if you have one.", 30, 30],
			"ICQ number" 		=> [0, "icq", "Your ICQ number, if you have one.", 10, 10],
			"imood" 			=> [0, "imood", "If you have a imood account, you can enter the account name (email) for it here.", 60, 100],
			"Homepage URL" 		=> [0, "homepageurl", "Your homepage URL (must start with the \"http://\") if you have one.", 60, 80],
			"Homepage Name" 	=> [0, "homepagename", "Your homepage name, if you have a homepage.", 60, 100],
		));
		
		_table_format("Options", array(
			"Custom date format" 			=> [4, "dateformat", "Change how dates are displayed. Uses <a href='http://php.net/manual/en/function.date.php'>date()</a> formatting. Leave blank to use the default.", 16, 32],
			"Custom short date format" 		=> [4, "dateshort", "Change how abbreviated dates are displayed. Uses the same formatting. Leave blank to reset.", 8, 16],
			"Timezone offset"	 			=> [0, "timezone", "How many hours you're offset from the time on the board (".date($loguser['dateformat'],time()).").", 5, 5],
			"Posts per page"				=> [0, "postsperpage", "The maximum number of posts you want to be shown in a page in threads.", 3, 3],
			"Threads per page"	 			=> [0, "threadsperpage", "The maximum number of threads you want to be shown in a page in forums.", 3, 3],
			"Use post toolbar" 				=> [2, "posttool", "You can disable it here, which can make thread pages smaller and load faster.", "Disabled|Enabled"],
			"Post layouts"	                => [2, "viewsig", "You can disable them here, which can make thread pages smaller and load faster.{$noeffect}", "Disabled|Enabled|Auto-updating"],
			"Forum List layout"				=> [2, "splitcat", "'Split' uses two columns instead of one.", "Normal|Split ({$splitcount})"],
			"Forum page list style"			=> [2, "pagestyle", "Inline (Title - Pages ...) or Separate Line (shows more pages)", "Inline|Separate line"],
			"Poll vote system"				=> [2, "pollstyle", "Normal (based on users) or Influence (based on levels)", "Normal|Influence"],
			"Thread layout"					=> [4, "layout", "You can choose from a few thread layouts here.{$noeffect}"],
			"Signature separator"			=> [4, "signsep", "You can choose from a few signature separators here."],
			"Color scheme / layout"	 		=> [4, "scheme", "You can select from a few color schemes here."],
			"Scheme sorting mode"	 		=> [2, "schemesort", "Determines how scheme lists are sorted.", "Normal|Alphabetical"],
			"Font size"	 					=> [4, "fontsize", "Change the default font size of the forum."],
			"Hide activity"			 		=> [2, "hideactivity", "You can choose to hide your online status.", "Show|Hide"],
			"Profile comments"			 	=> [2, "comments", "You can disable them here.", "Disable|Enable"],
			"Enable AJAX"			 		=> [2, "ajax", "Enables experimental AJAX features.", "No|Yes"],
		));
		if ($edituser){
			_table_format("Special RPG Extra Bonus Category", array(
				"Green coins"		=> [0, "gcoins", "", 10, 10],
				"Damage Received"	=> [0, "damage", "", 10, 10],
				"Class"				=> [4, "class", ""],
			));
		}
		
		_table_format("Miscellaneous", array(
			"Extra profile fields"		 	=> [4, "extrafields", ""],
		));
		
		/*
			Custom values (used when first value in array is set to 4)
		*/
		
		// Static text for the username (shown when editing your own profile)
		$name = $userdata['name'];
		$displayname = getuserlink($userdata);
		
		
		// Password field + confirmation (unless you're editing another user)
		$password = "<input type='password' name='pass1'>";
		if (!$edituser)	$password .= " Retype: <input type='password' name='pass2'>";
		
		
		$birthday = datetofields($userdata['birthday'], 'birth');
		
		
		if ($issuper) {
			// Sidebar options.
			$sidebartype = sidebartype_html($id, $userdata['sidebartype']);
			
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
			<input type=radio name=colorspec value=0 ".filter_string($sel_color[0]).">None 
			<input type=radio name=colorspec value=1 ".filter_string($sel_color[1]).">Defined: <input type='color' name=namecolor VALUE=\"{$userdata['namecolor']}\" SIZE=7 MAXLENGTH=7> 
			<input type=radio name=colorspec value=2 ".filter_string($sel_color[2]).">Random 
			<input type=radio name=colorspec value=3 ".filter_string($sel_color[3]).">Time-dependent 
			<input type=radio name=colorspec value=4 ".filter_string($sel_color[4]).">Rainbow";
		}
		
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
		if (!$weblink) $weblink = "";
		
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
			$powerlevel = power_select('powerlevel', $userdata['powerlevel'], PWL_MIN, $loguser['powerlevel']);
			
			// Registration time
			$regdate = datetofields($userdata['regdate'], 'reg', DTF_DATE | DTF_TIME);
			
			// Hours left before the user is unbanned
			$ban_hours = ban_select('ban_hours', $userdata['ban_expire'])." (has effect only for Banned users)";
			
			// RPG Class
			$class  = rpgclass_select('class', $userdata['class']);
		}
		
		$schflags = (!$edituser && !$isadmin) ? SL_SHOWUSAGE : SL_SHOWUSAGE | SL_SHOWSPECIAL;
		$scheme = doschemelist($userdata['scheme'], 'scheme', $schflags);
		$fontsize = "<input type='text' name='fontsize' size=5 maxlength=5 value=\"".__($userdata['fontsize'])."\">%";
		
		$dateformat = input_html('dateformat', $userdata['dateformat'], ['input' => 'text', 'special' => 'dateformat', 'width' => '150px'], 'datepreset', $userdata['dateformat']);
		$dateshort = input_html('dateshort', $userdata['dateshort'], ['input' => 'text', 'special' => 'dateshort', 'width' => '150px'], 'dateshortpreset', $userdata['dateshort']);
		
		
		// listbox with <name> <used>
		$layout   = _queryselectbox('layout',   'SELECT tl.id as id, tl.name, COUNT(u.layout) as used FROM tlayouts tl LEFT JOIN users u ON (u.layout = tl.id) GROUP BY tl.id ORDER BY tl.ord');
		$useranks = _queryselectbox('useranks', 'SELECT rs.id as id, rs.name, COUNT(u.useranks) as used FROM ranksets rs LEFT JOIN users u ON (u.useranks = rs.id) GROUP BY rs.id ORDER BY rs.id');
		
		
		$used = $sql->getresultsbykey('SELECT signsep, count(*) as cnt FROM users GROUP BY signsep');
		$signsep = "";
		foreach (SIGNSEP_DESC as $i => $sepn) {
				$sel = ($i==$userdata['signsep'] ? ' selected' : '');
				$signsep .= "<option value='{$i}'{$sel}>{$sepn} (".filter_int($used[$i]).")";
		}
		$signsep="<select name='signsep'>$signsep</select>";
		
		// Misc opts
		$extrafields = "<a href='editprofilex.php?id={$id}' target='_blank'>Extended profile editor</a>";
		
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
					$input = "<input type='text' name='$data[1]' size={$data[3]} maxlength={$data[4]} value=\"".(isset($userdata[$data[1]]) ? htmlspecialchars($userdata[$data[1]]) : "")."\">";
				}
				else if ($data[0] == 1) { // large
					if (!isset($data[3])) $data[3] = 8; // Rows
					$input = "<textarea name='$data[1]' rows='{$data[3]}'>".(isset($userdata[$data[1]]) ? htmlspecialchars($userdata[$data[1]]) : "")."</textarea>";
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
	
	pageheader();
	
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
		<input type='submit' name=submit VALUE="Edit <?=($edituser ? "user" : "profile")?>">
		</td>
	</table>
	</form>
	<?php
	
	pagefooter();

	function _table_format($name, $array){
		global $fields;
		
		if (isset($fields[$name])){ // Already exists: merge arrays
			$fields[$name] = array_merge($fields[$name], $array);
		} else { // It doesn't: Create a new one.
			$fields[$name] = $array;
		}
	}
	
	// When it comes to copy / pasted code...
	function _queryselectbox($val, $query) {
		global $sql, $userdata;
		$txt = "";
		$q = $sql->query($query);
		while ($x = $sql->fetch($q, PDO::FETCH_ASSOC)) {
			$sel = ($x['id'] == $userdata[$val] ? ' selected' : '');
			$txt .=" <option value={$x['id']}{$sel}>".htmlspecialchars($x['name'])." ({$x['used']})</option>\n\r";			
		}
		return "<select name='$val'>$txt</select>";
	}
	
	