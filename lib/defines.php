<?php

	const PERM_FIELDS_NUM = 1;	// Number of permission fields
	
	// Permission names are passed lowercase and with - instead of _ (ie: has_perm('bypass-lockdown') checks PERM_BYPASS_LOCKDOWN)
	// The correct bitmask to search for is specified in the constant definition, so we do not need to worry about passing the bitmask field id manually.
	function has_perm($permName) {
		global $loguser;
		$permArray = constant("PERM_" . str_replace("-", "_", strtoupper($permName)));
		return $loguser['permflags']['set'.$permArray[0]] & $permArray[1];
	}
	
	function check_perm($permName, $group, $cache = NULL) {
		if ($cache === NULL) {
			$cache = load_perm(0, $group);
		}
		$permArray = constant("PERM_" . str_replace("-", "_", strtoupper($permName)));
		return $cache['set'.$permArray[0]] & $permArray[1];
	}
	
	// Forum permissions are 6 bits long (Read,Post,Edit,Delete,Thread,Mod)
	// $permName can be any of those four words
	// $sourceArr has to contain the key 'forumperm' which defines the bitset to check, and optionally a 'userperm' key as patch data.
	function has_forum_perm($permName, $sourceArr, $noallcheck = false) {
		if ($noallcheck || !has_perm('all-forum-access')) {
			$permBit = constant("PERM_FORUM_" . strtoupper($permName));
			$check = isset($sourceArr['userperm']) ? $sourceArr['userperm'] : $sourceArr['forumperm'];
			return $check & $permBit;
		} else {
			return PERM_ALL_FORUM_ACCESS;
		}
	}
	
	function get_forum_perm($forum, $user, $group){
		global $sql;
		return $sql->fetchq("
			SELECT pf.group{$group} forumperm, pu.permset userperm
			FROM forums f
			LEFT JOIN perm_forums     pf ON f.id    = pf.id
			LEFT JOIN perm_forumusers pu ON f.id    = pu.forum AND pu.user = {$user}
			WHERE f.id = {$forum}
		", PDO::FETCH_ASSOC, true);
	}
	
	// Generate the bitmask field names for a query
	function perm_fields($talias = "", $falias = "", $fakevalue = NULL) {
		$pre 	= $talias ? "{$talias}." : ""; // Table alias
		if ($fakevalue !== NULL) {
			$pre = "$fakevalue $pre";	// Goat value to set all perm fields to
		}
		
		$out = "";
		// Thank you iteration performance (for whatever it's worth)
		if (!$falias) {
			for ($i = 0; $i < PERM_FIELDS_NUM; ++$i) {
				$out .= ($i ? " ," : "")."{$pre}set".($i+1);
			}
		} else {
			for ($i = 0; $i < PERM_FIELDS_NUM; ++$i) {
				$out .= ($i ? " ," : "")."{$pre}set".($i+1)." {$falias}".($i+1);
			}
		}
		return $out;
	}
	
	/*
		Permission system:
		
		The usergroups are defined in perm_groups. Any group ID has bitmasks for global forum options.
		These can be overridden by the bitmasks in perm_user if they aren't NULL.
	*/
	function load_perm($user, $group) {
		global $sql;
		
		$setfields = perm_fields();
		
		$power = $sql->fetchq("SELECT {$setfields} FROM perm_groups WHERE id = {$group}");
		// Save a query if we're not logged in, since we wouldn't have proper perm_user bitmasks anyway.
		if ($user) {
			$userpower 	= $sql->fetchq("SELECT {$setfields} FROM perm_users WHERE id = {$user}");
			
			if (is_array($userpower)) {
				// Replace non-null bitmasks with this "patch data"
				foreach ($userpower as $set => $bitmask) {
					if (isset($bitmask)) {
						$power[$set] = $bitmask;
					}
				}
			}
		}
		return $power;
	}
	
	function ismod($fdata) {return ($fdata && has_forum_perm('mod', $fdata));}
	
	/*
		Permission definitions
		<permission name> = [<bitmask set>, <flag>]
	*/
	const PERM_BYPASS_LOCKDOWN 				= [1, 0b1]; // SYSADMIN
	const PERM_DISPLAY_HIDDEN_FORUMS 		= [1, 0b10]; // ADMIN
	const PERM_VIEW_DEBUGGER				= [1, 0b100]; // SYSADMIN
	const PERM_VIEW_SUBMESSAGE				= [1, 0b1000]; // SUPER
	const PERM_VIEW_BPT_INFO				= [1, 0b10000]; // ADMIN (+ online.php ip sort)
	const PERM_SHOW_HIDDEN_USER_ACTIVITY	= [1, 0b100000]; // MOD
	const PERM_SYSADMIN_ACTIONS				= [1, 0b1000000]; // SYSADMIN [Generic Sysadmin Perm]
	const PERM_ADMIN_ACTIONS				= [1, 0b10000000]; // ADMIN [Generic Admin Perm]
	const PERM_LOGS_BANNER					= [1, 0b100000000]; // SYSADMIN / Other whitelisted
	const PERM_USE_SHOPED					= [1, 0b1000000000]; // SUPER
	const PERM_FORUM_ADMIN					= [1, 0b10000000000]; // ADMIN
	const PERM_EDIT_OWN_POSTS				= [1, 0b100000000000]; // NORMAL
	const PERM_ALL_FORUM_ACCESS				= [1, 0b1000000000000]; // MOD [Generic Mod Perm]
	const PERM_EDIT_OWN_PROFILE				= [1, 0b10000000000000]; // NORMAL
	const PERM_HAS_TITLE					= [1, 0b100000000000000]; // NORMAL
	const PERM_HAS_ALWAYS_TITLE				= [1, 0b1000000000000000]; // SUPER
	const PERM_CHANGE_NAMECOLOR				= [1, 0b10000000000000000]; // SUPER
	const PERM_VIEW_ONLINE_PAGE				= [1, 0b100000000000000000]; // GUEST
	const PERM_SELECT_SECRET_THEMES			= [1, 0b1000000000000000000]; // ADMIN / Other whitelisted
	const PERM_EDIT_OWN_EVENTS				= [1, 0b10000000000000000000]; // NORMAL
	const PERM_SHOW_SUPER_USERS				= [1, 0b100000000000000000000]; // ADMIN
	const PERM_VIEW_OTHERS_PMS				= [1, 0b1000000000000000000000]; // ADMIN
	const PERM_SHOW_ALL_RANKS				= [1, 0b10000000000000000000000]; // MOD
	const PERM_REREGISTER					= [1, 0b100000000000000000000000]; // ADMIN
	const PERM_SEND_PMS						= [1, 0b1000000000000000000000000]; // NORMAL
	const PERM_VIEW_SHITBUGS				= [1, 0b10000000000000000000000000]; // SUPER
	const PERM_USE_SHOPED_HIDDEN			= [1, 0b100000000000000000000000000]; // SYSADMIN / Whitelisted
//  const PERM__________________			= [1, 0b00000000000000000000000000000000];
	
	
	/*
		Forum permissions
	*/
	const PERM_FORUM_READ 		= 0b1;
	const PERM_FORUM_POST 		= 0b10;
	const PERM_FORUM_THREAD 	= 0b100;
	const PERM_FORUM_EDIT		= 0b1000;
	const PERM_FORUM_DELETE		= 0b10000;
	const PERM_FORUM_MOD 		= 0b100000;
	const PERM_FORUM_NOTMOD 	= 0b011111;
	
	
	/*
		Default unremovable groups
		They HAVE to match the entries in the perm_groups table
	*/
	const GROUP_NORMAL		= 1;
	const GROUP_SUPER		= 2;
	const GROUP_MOD			= 3;
	const GROUP_ADMIN		= 4;
	const GROUP_SYSADMIN 	= 5;
	const GROUP_GUEST		= 6;	// Separator
	const GROUP_BANNED		= 7;
	const GROUP_PERMABANNED = 8;
	
	/*
		Only use for color schemes
	*/
	const MALE 		= 'namecolor0';
	const FEMALE 	= 'namecolor1';
	const N_A 		= 'namecolor2';
	
	
	/*
		Bot / Tor / Proxy flags
		Used to set the flags for online guests table
	*/
	const BPT_IPBANNED 	= 1;
	const BPT_PROXY 	= 2;
	const BPT_TOR 		= 4;
	const BPT_BOT 		= 8;
	
	
	//const PDO_CACHE		= 0b1;
	//const PDO_FETCHALL	= 0b10;
	
	