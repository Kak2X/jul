<?php

//echo 0b111111111111111111111111111111;

const PT_PERMLIST = array(
	
	'Generic Permissions' => NULL,
	'sysadmin-actions'			=> "General Sysadmin actions", // SYSADMIN [Generic Sysadmin Perm]
	'admin-actions'				=> "General Admin actions", // ADMIN [Generic Admin Perm]
	'forum-admin'				=> "Forum administration", // ADMIN [Generic Admin Perm]
	'all-forum-access'			=> "General Global Mod actions", // MOD [Generic Mod Perm]	

	
	'Administrative Features' => NULL,
	'view-others-pms'			=> "View other user's PMs", // ADMIN
	'bypass-lockdown' 			=> "Can bypass board lockdown", // SYSADMIN
	'view-debugger' 			=> "Can view Error and SQL debuggers", // SYSADMIN
	'reregister'				=> "Can re-register ignoring restrictions", // ADMIN
	'logs-banner'				=> "Can ban users from the suspicious requests and online page", // SYSADMIN / Other whitelisted
	
	'Restricted pages' => NULL,
	'view-shitbugs'				=> "Can view suspicious requests log", // SUPER
	'use-shoped'				=> "Shop Editor access", // SUPER
	'use-shoped-hidden'			=> "Can edit hidden shop items",  // SYSADMIN / Whitelisted	
	
	'Restricted features' => NULL,
	'show-super-users'			=> "Can see 'Normal+' group", // ADMIN
	'show-hidden-user-activity'	=> "Show hidden users in online bar", // MOD
	'show-all-ranks'			=> "Show all ranks", // MOD
	'view-submessage'			=> "Can view the staff-only message", // SUPER
	'view-bpt-info'				=> "View Bot/Tor/Proxy info in online bar", // ADMIN (+ online.php ip sort)
	'display-hidden-forums' 	=> "Can view hidden forums in the index page", // ADMIN
	
	'Restricted profile options' => NULL,
	'has-always-title'			=> "Bypass custom title requirements", // SUPER
	'change-namecolor'			=> "Can change his own namecolor", // SUPER
	'select-secret-themes'		=> "Show secret themes in theme list", // ADMIN / Other whitelisted

	'Normal User Actions' => NULL,
	'edit-own-posts'			=> "Can edit his own posts", // NORMAL
	'edit-own-profile'			=> "Can edit his own profile", // NORMAL
	'view-online-page'			=> "Can view online users page", // Dedicated to the autistic shithead known as AlbertoCML	
	'send-pms'					=> "Can send PMs", // NORMAL
	'edit-own-events'			=> "Can edit his own events", // NORMAL
	'has-title'					=> "Custom title status", // NORMAL
	'create-custom-forums'		=> "Create custom forums",
	'bypass-custom-forum-limits'=> "Bypass custom forum limits/requirements",
	
	
	'News Engine' => NULL,
	'post-news' 				=> "Can post news", // ADMIN
	'news-admin' 				=> "Can moderate the news section", // ADMIN
	

);

foreach (PT_PERMLIST as $title => $desc) {
	if ($desc !== NULL) {
		echo "UPDATE perm_definitions SET permcat = (SELECT id FROM perm_types_definitions WHERE description = '".str_replace('\'', '\'\'', $cattxt)."') WHERE title = '{$title}';<br>";
	} else {
		$cattxt = $title;
	}
}

/*
	$dat = array(
		'bypass-lockdown',
		'display-hidden-forums',
		'view-debugger',
		'view-submessage',
		'view-bpt-info',
		'show-hidden-user-activity',
		'sysadmin-actions',
		'admin-actions',
		'logs-banner',
		'use-shoped',
		'forum-admin',
		'edit-own-posts',
		'all-forum-access',
		'edit-own-profile',
		'has-title',
		'has-always-title',
		'change-namecolor',
		'view-online-page',
		'select-secret-themes',
		'edit-own-events',
		'show-super-users',
		'view-others-pms',
		'show-all-ranks',
		'reregister',
		'send-pms',
		'view-shitbugs',
		'use-shoped-hidden',
		'create-custom-forums',
		'post-news',
		'news-admin',
		'bypass-custom-forums-limits'
	);
	
	
	$p = 0; $b = 0b11;
	echo "<pre>INSERT INTO perm_definitions (title, permset, permbit) VALUES\n";
	for ($i = 0, $c = count($dat); $i < $c; ++$i) {
		echo "('{$dat[$i]}', $p, $b),\n";
		
		if ($b == 0b110000000000000000000000000000) {
			$b = 0b11;
			$p++;
		} else {
			$b = $b << 2;
		}
	}
	*/