<?php
	const LT_NONALPHA = '%';
	const _POWL_ALL = 42;
	const _LINK_SEPARATOR = ' | ';
	
	require 'lib/function.php';
	
	function sortbyexp($a,$b) {
		$cmpa = (($a['exp'] === 'NaN') ? -1 : (int) $a['exp']);
		$cmpb = (($b['exp'] === 'NaN') ? -1 : (int) $b['exp']);
		if (!$_GET['ord']) { // DESC
			if ($cmpa == $cmpb) return $a['id']-$b['id'];
			return $cmpb - $cmpa;
		} else {  // ASC
			if ($cmpa == $cmpb) return $b['id']-$a['id'];
			return $cmpa - $cmpb;
		}	
	}
	function sortbyrating($a, $b){
		if (!$_GET['ord']) { // DESC
			return ($b['rating'] - $a['rating']);
		} else {
			return ($a['rating'] - $b['rating']);
		}
	}
	
	// Variable filtering and query strings
	$_GET['sex'] 	= filter_string($_GET['sex']);
	$_GET['sort'] 	= filter_string($_GET['sort']);
	$_GET['ord'] 	= filter_int($_GET['ord']);
	
	$_GET['pow'] 	= isset($_GET['pow']) && $_GET['pow'] != _POWL_ALL ? (int)$_GET['pow'] : _POWL_ALL;
	$_GET['ppp'] 	= filter_int($_GET['ppp']);
	$_GET['rpg'] 	= filter_int($_GET['rpg']);
	$_GET['page'] 	= filter_int($_GET['page']);
	$_GET['lt']     = filter_string($_GET['lt']);
	
	if($_GET['sex']) $qsex = "&sex={$_GET['sex']}"; else $qsex = "";
	$qpow = "&pow={$_GET['pow']}";
	if($_GET['ppp']) $qppp = "&ppp={$_GET['ppp']}"; else $qppp = "";
	if($_GET['ord']) $qord = "&ord=1"; else $qord = "";
	if($_GET['rpg']) $qrpg = "&rpg=1"; else $qrpg = "";
	if ($_GET['lt']) {
		$_GET['lt'] = ucfirst(substr($_GET['lt'], 0, 1));
		$qlt = "&lt={$_GET['lt']}";
	} else {
		$qlt = "";
	}
	$q = $qppp.$qrpg;

	if(!$_GET['ppp']) $_GET['ppp']=50;
	else $_GET['ppp'] = numrange($_GET['ppp'], 1, 500);
	//if(!$_GET['page']) $_GET['page']=0;

	
	// WHERE clause of query
	$qwhere = array();	
	switch ($_GET['sex']) {
		case 'm': $qwhere[] = '(sex=0)'; break;
		case 'f': $qwhere[] = '(sex=1)'; break;
		case 'n': $qwhere[] = '(sex=2)'; break;
		case 'x': 
			if ($isadmin) 
				$qwhere[] = 'NOT (sex BETWEEN 0 AND 2)';
			else
				$_GET['sex'] = '';
			break;
	}
	if ($_GET['pow'] != _POWL_ALL) {
		if (($_GET['pow'] == 1 || $_GET['pow'] == 0) && $loguser['powerlevel'] < $config['view-super-minpower'])
			$sqlpower = "IN (0, 1)";
		elseif ($_GET['pow'] == 3 || $_GET['pow'] == 4) // merge admin + sysadmin (they appear the same)
			$sqlpower = "IN (3, 4)";
		elseif ($_GET['pow'] == -1 || $_GET['pow'] == -2) // merge banned + permabanned
			$sqlpower = "IN (-1, -2)";
		else
			$sqlpower = "= '{$_GET['pow']}'";

		$qwhere[] = "powerlevel $sqlpower";
	}
	if ($_GET['lt']) {
		if ($_GET['lt'] == LT_NONALPHA) { // Non alphabetic
			$qwhere[] = "u.name NOT REGEXP '^[a-z]'";
		} else {
			// Alphabetic chars only
			$ltnum = ord($_GET['lt']);
			if ($ltnum < 65 || $ltnum > 90)
				$_GET['lt'] = 'A';
			$qwhere[] = "u.name LIKE '{$_GET['lt']}%'";
		}
	}
	
	$where = (empty($qwhere) ? '' : "WHERE ".implode(' AND ', $qwhere));	
	
	switch ($_GET['sort']) {
		case 'name': 	$sorting = "ORDER BY u.name"; break;
		case 'reg':  	$sorting = "ORDER BY u.regdate"; break;
		case 'rating':
		case 'exp':  	$sorting = ""; break;
		case 'age':  	$sorting = "AND u.birthday ORDER BY u.birthday"; break; 
		case '':
		case 'posts':	$sorting = "ORDER BY u.posts"; break;
		case 'act':		$sorting = "ORDER BY u.lastactivity"; break;
		default: errorpage("No.");
	}
	

	// Ordering / limit and special modes
	$order = $fields = $join = $limit = "";
	$min = $_GET['ppp'] * $_GET['page'];
	
	if ($_GET['sort'] != 'exp' && $_GET['sort'] != 'rating') {
		$order = ($_GET['ord'] ? "ASC" : "DESC");
		$limit = " LIMIT {$min}, {$_GET['ppp']}";
	}
	if ($_GET['rpg']) {
		$fields = ", r.*";
		$join   = "LEFT JOIN users_rpg r ON u.id = r.uid";
	}	
	
	// Don't fetch every single user at once when possible.
	// Unfortunately it has to be done when the sort option is not a real field (ie: ratings or exp)
	$users1 = $sql->query("
		SELECT $userfields, u.lastactivity, u.regdate, u.posts{$fields}
		FROM users u
		$join
		$where 
		$sorting $order
		$limit
	");
	$numusers = $sql->resultq("SELECT COUNT(*) FROM users u	$where");

	$users = array();
	$userids = array();
	for ($i = 0; $user = $sql->fetch($users1); ++$i) {
		$user['days'] = (ctime()-$user['regdate'])/86400;
		$user['exp']  = calcexp($user['posts'],$user['days']);
		$user['lvl']  = calclvl($user['exp']);
		$user['rating'] = null;
		$users[] = $user;
		if (!$limit)
			$userids[$user['id']] = $i;
	}
	
	if ($_GET['sort'] == 'exp') {
		usort($users,'sortbyexp');
	} else if ($_GET['sort'] == 'rating') {
	
		// Alternative: one query per user (no thanks)
		$allratings = $sql->fetchq("
			SELECT ur.userrated, ur.rating, ur.userfrom, u1.posts, u1.regdate
			FROM userratings ur
			INNER JOIN (
				SELECT u.id
				FROM users u
				$where 
				$sorting $order
				$limit
			) u2 ON ur.userrated = u2.id
			INNER JOIN users u1 ON ur.userfrom = u1.id
		", PDO::FETCH_GROUP, mysql::FETCH_ALL);
		
		$tempdb = array();
		foreach ($allratings as $userid => $userratings) {
		// Uncomment for hilarity
		//foreach ($users as $user) {
			//$userid = $user['id'];
			//$userratings=$sql->query("SELECT userfrom,userrated,rating FROM userratings WHERE userrated=$userid");
			//--
			
			$ratescore = $ratetotal = 0;
			foreach ($userratings as $x) {
				// Cache previous calculations when possible
				if (!isset($tempdb[$x['userfrom']]['lvl'])) {
					$unlist[] = $x['userfrom'];
					$tdays = (ctime() - $x['regdate']) / 86400;
					$texp  = calcexp($x['posts'], $tdays);
					$tempdb[$x['userfrom']]['lvl']  = calclvl($texp);
				}
				// The actual rating calculations, which depend on level
				$level = max(1, $tempdb[$x['userfrom']]['lvl']);
				$ratescore += $x['rating'] * $level;
				$ratetotal += 10 * $level;
			}
			
			$aid = $userids[$userid];
			$users[$aid]['numvotes'] = count($userratings);
			$users[$aid]['ratescore'] = $ratescore;
			$users[$aid]['ratetotal'] = $ratetotal;
			$users[$aid]['rating'] = $ratescore * 100000 / $ratetotal / 10000;
		}
		unset($tempdb, $aid, $userids);
		usort($users, 'sortbyrating');
  }
	
	$pagelinks = 
	"<span class='fonts'>".
		pagelist("memberlist.php?sort={$_GET['sort']}$qsex$qpow$qrpg$qppp$qlt", $numusers, $_GET['ppp'], true).
	"</span>";
	

	// Menu declarations
	
	// Sort by
	$sortelem = array(
		'posts'  => "Total posts",
		'exp'    => "EXP",
		'name'   => "User name",
		'reg'    => "Registration date",
		'act'    => "Last activity",
		'age'    => "Age",
		'rating' => "Rating",
	);
	
	//--
	// Sex	
	$sexelem = array(
		'm' => "Male",
		'f' => "Female",
		'n' => "N/A",
		''  => "All",
	);
	if ($isadmin)
		$sexelem['x'] = "Broken";
	
	//--
	// Powerlevel
	
	$powlelem = array(
		-1 => $pwlnames[-1],
		0  => $pwlnames[0],
		1  => $pwlnames[1],
		2  => $pwlnames[2],
		3  => $pwlnames[3],
		_POWL_ALL => "All",
	);
	
	if ($loguser['powerlevel'] < $config['view-super-minpower']) {
		unset($powlelem[1]);
	}			
	
	//--
	// First letter
	
	// Generate each individual letter from A to Z
	for ($i = 0; $i < 26; ++$i) {
		$c = chr(65 + $i);
		$alphaelem[$c] = $c;
	}
	$alphaelem[LT_NONALPHA] = '#';
	$alphaelem[""] = 'All';
	
	//--
	// Sort order
	
	$orderelem = array(
		0 => 'Descending',
		1 => 'Ascending',
	);
	//--
	
	$s = ($numusers != 1 ? "s" : "");
	pageheader();
	
print "
<table class='table'>
	<tr><td class='tdbgh center' colspan=2>$numusers user$s found.</td></tr>
	<tr>
		<td class='tdbg1 fonts center'>	Sort by:</td>
		<td class='tdbg2 fonts center'>
			".linkset_list("memberlist.php?$q$qpow$qsex$qord$qlt&sort=", $sortelem, $_GET['sort'], _LINK_SEPARATOR)."
		</td>
	</tr>
	<tr>
		<td class='tdbg1 fonts center'>	Sex:</td>
		<td class='tdbg2 fonts center'>
			".linkset_list("memberlist.php?sort={$_GET['sort']}$q$qpow$qord$qlt&sex=", $sexelem, $_GET['sex'], _LINK_SEPARATOR)."
		</td>
	</tr>
	<tr>
		<td class='tdbg1 fonts center'>	Powerlevel:</td>
		<td class='tdbg2 fonts center'>
			".linkset_list("memberlist.php?sort={$_GET['sort']}$q$qsex$qord$qlt&pow=", $powlelem, $_GET['pow'], _LINK_SEPARATOR)."
		</td>
	</tr>
	<tr>
		<td class='tdbg1 fonts center'>	First letter:</td>
		<td class='tdbg2 fonts center'>
			".linkset_list("memberlist.php?sort={$_GET['sort']}$qsex$qpow$qrpg$qppp&lt=", $alphaelem, $_GET['lt'], _LINK_SEPARATOR)."
		</td>
	</tr>	
	<tr>
		<td class='tdbg1 fonts center'>	Sort order:</td>
		<td class='tdbg2 fonts center'>
			".linkset_list("memberlist.php?sort={$_GET['sort']}$q$qsex$qpow$qlt&ord=", $orderelem, $_GET['ord'], _LINK_SEPARATOR)."
		</td>
	</tr>
</table>
<br>
<table class='table'>
		<tr>
			<td class='tdbgh center' width=30>#</td>
			<td class='tdbgh center' style='width: {$config['max-minipic-size-x']}px'>&nbsp;</td>
			<td class='tdbgh center'>Username</td>
	";

	if(!$_GET['rpg']) {
		print "
			<td class='tdbgh center' width=200>Registered on</td>
			<td class='tdbgh center' width=200>Last activity</td>
			<td class='tdbgh center' width=60>Posts</td>
			<td class='tdbgh center' width=35>Level</td>
			<td class='tdbgh center' width=100>EXP</td>
		";
		if ($_GET['sort'] == 'rating')
			print "
			<td class='tdbgh center' colspan=3 width=90>Rating</td>";
		print "</tr>";
	} else {
		$items   = $sql->getarraybykey("SELECT * FROM items", 'id');
        $classes = $sql->getarraybykey("SELECT * FROM rpg_classes", 'id');

		print "<td class='tdbgh center' width=35>Level</td>";
		print "<td class='tdbgh center' width=90>Class</td>";
		for($i=0;$i<9;++$i) print "<td class='tdbgh center' width=65>".$stat[$i].'</td>';
		print "<td class='tdbgh center' width=80><img src='images/coin.gif'></td>";
		print "<td class='tdbgh center' width=60><img src='images/coin2.gif'></td>";
		print "</tr>";
	}

	$ulist = "";
	// Results already truncated by the query, so the correct indexes start at 0
	if ($limit)
		$min = 0;
	
	for ($u = 0; $u < $_GET['ppp']; ++$u) {
		$i = $min + $u;
		if (!isset($users[$i])) break;
		$user = $users[$i];
		
		$ulist .= "<tr style='height:24px'>";
		
		$userpicture = get_minipic($user['id'], $user['minipic']);
		$userlink = getuserlink($user);
		$ulist .= "
			<td class='tdbg2 center'>".($i+1).".</td>
			<td class='tdbg1 center'>{$userpicture}</td>
			<td class='tdbg2'>{$userlink}</td>
		";

		if (!$_GET['rpg']) {
			$ulist .= "
				<td class='tdbg2 center'><span title='". timeunits2(ctime() - $user['regdate'], true) ." ago'>".printdate($user['regdate'])."</span></td>
				<td class='tdbg2 center'><span title='". timeunits2(ctime() - $user['lastactivity'], true) ." ago'>".printdate($user['lastactivity'])."</span></td>
				<td class='tdbg1 center'>{$user['posts']}</td>
				<td class='tdbg1 center'>{$user['lvl']}</td>
				<td class='tdbg1 center'>{$user['exp']}</td>
			";
			if ($_GET['sort'] == 'rating') {
				if ($user['rating'] === null) {
					$ulist .= "
						<td class='tdbg1 center'>None</td>
						<td class='tdbg2 center'>&mdash;</td>
						<td class='tdbg2 center'>0 votes</td>";
				} else {
					$ulist .= "
						<td class='tdbg1 center'><center><b>".(sprintf('%01.2f', $user['rating']))."</b></td>
						<td class='tdbg2 center'>{$user['ratescore']} / {$user['ratetotal']}</td>
						<td class='tdbg2 center'>{$user['numvotes']} vote".($user['numvotes'] != 1 ? "s" : "")."</td>";
				}
			}
		}
		else {
			if (!isset($classes[$user['class']]))
				$class = array('name' => 'None');
			else
				$class = $classes[$user['class']];
			$stats = getstats($user,$items,$class);

			$ulist.="<td class='tdbg1 center'>$user[lvl]</td>";
			$ulist.="<td class='tdbg1 center'>$class[name]</td>";
			for($k=0;$k<9;++$k) $ulist.="<td class='tdbg1 fonts center'>".$stats[$stat[$k]].'</td>';
			$ulist.="<td class='tdbg1 fonts center'>$stats[GP]</td>";
			$ulist.="<td class='tdbg1 fonts center'>$user[gcoins]</td>";
		}
		$ulist.="</tr>";
	}

	print "$ulist</table>$pagelinks";
	pagefooter();
	