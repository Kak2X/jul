<?php
	require 'lib/function.php';

	// Variable filtering and query strings
	$_GET['sex'] 	= filter_string($_GET['sex']);
	$_GET['sort'] 	= filter_string($_GET['sort']);
	$_GET['ord'] 	= filter_int($_GET['ord']);
	
	$_GET['pow'] 	= filter_int($_GET['pow']);
	$_GET['ppp'] 	= filter_int($_GET['ppp']);
	$_GET['rpg'] 	= filter_int($_GET['rpg']);
	$_GET['page'] 	= filter_int($_GET['page']);
	
	if($_GET['sex']) $qsex = "&sex={$_GET['sex']}"; else $qsex = "";
	if($_GET['pow']) $qpow = "&pow={$_GET['pow']}"; else $qpow = "";
	if($_GET['ppp']) $qppp = "&ppp={$_GET['ppp']}"; else $qppp = "";
	if($_GET['ord']) $qord = "&ord=1"; else $qord = "";
	if($_GET['rpg']) $qrpg = "&rpg=1"; else $qrpg = "";
	$q = $qppp.$qrpg;

	if(!$_GET['ppp']) $_GET['ppp']=50;
	if(!$_GET['page']) $_GET['page']=0;

	
	// WHERE clause of query
	$qwhere = array();
	switch ($_GET['sex']) {
		case 'm': $qwhere[] = '(sex=0)'; break;
		case 'f': $qwhere[] = '(sex=1)'; break;
		case 'n': $qwhere[] = '(sex=2)'; break;
	}
	if ($_GET['pow']) {
		if (($_GET['pow'] == GROUP_NORMAL || $_GET['pow'] == GROUP_SUPER) && !has_perm('show-super-users'))
			$sqlpower = "IN (".GROUP_NORMAL.", ".GROUP_SUPER.")";
		elseif ($_GET['pow'] == GROUP_ADMIN || $_GET['pow'] == GROUP_SYSADMIN) // merge admin + sysadmin (they appear the same)
			$sqlpower = "IN (".GROUP_ADMIN.", ".GROUP_SYSADMIN.")";
		elseif ($_GET['pow'] == GROUP_BANNED || $_GET['pow'] == GROUP_PERMABANNED) // merge banned + permabanned
			$sqlpower = "IN (".GROUP_BANNED.", ".GROUP_PERMABANNED.")";
		else
			$sqlpower = "= '{$_GET['pow']}'";

		$qwhere[] = "`group` $sqlpower";
	}
	$where = 'WHERE '.((empty($qwhere)) ? '1' : implode(' AND ', $qwhere));
	
	switch ($_GET['sort']) {
		case 'name': 	$sorting = "ORDER BY u.name"; break;
		case 'reg':  	$sorting = "ORDER BY u.regdate"; break; // DESC
		case 'exp':  	$sorting = ""; break;
		case 'age':  	$sorting = "AND u.birthday ORDER BY u.birthday"; break; // ASC
		case '':
		case 'posts':	$sorting = "ORDER BY u.posts"; break; // DESC
		default: errorpage("No.");
	}
	if ($_GET['sort'] != 'exp')
		$order = $_GET['ord'] ? "ASC" : "DESC";
	else
		$order = "";
	
	$users1 = $sql->query("
		SELECT $userfields, u.regdate, u.posts, r.* 
		FROM users u
		LEFT JOIN users_rpg r ON u.id = r.uid 
		$where 
		$sorting $order
	");
	
	/*
	if (!in_array($sort, array('name','reg','exp','age','posts')))
		$sort = 'posts';
	$query='SELECT id,posts,regdate,name,minipic,sex,powerlevel,aka,r.* FROM users LEFT JOIN users_rpg r ON id=uid ';
	if($sort=='name')  $users1=$sql->query("$query$where ORDER BY name", MYSQL_ASSOC);
	if($sort=='reg')   $users1=$sql->query("$query$where ORDER BY regdate DESC", MYSQL_ASSOC);
	if($sort=='exp')   $users1=$sql->query("$query$where", MYSQL_ASSOC);
	if($sort=='age')   $users1=$sql->query("$query$where AND birthday ORDER BY birthday", MYSQL_ASSOC);
	if($sort=='posts') $users1=$sql->query("$query$where ORDER BY posts DESC", MYSQL_ASSOC);
*/
	$numusers = $sql->num_rows($users1);

	for($i = 0; $user = $sql->fetch($users1); ++$i){
		$user['days'] = (ctime()-$user['regdate'])/86400;
		$user['exp']  = calcexp($user['posts'],$user['days']);
		$user['lvl']  = calclvl($user['exp']);
		$users[] = $user;
	}

	if ($_GET['sort'] == 'exp') {
		usort($users,'sortbyexp'.($_GET['ord'] ? 'asc' : 'desc'));
	}
	
	$pagelinks = "<span class='fonts'>Pages:";
	for($i = 0, $total = $numusers / $_GET['ppp']; $i < $total; ++$i) {
		$pagelinks .= ($i == $_GET['page'] ? ' '.($i+1): " <a href='memberlist.php?sort={$_GET['sort']}$qsex$qpow$qrpg$qppp&page=$i'>".($i+1).'</a>');
	}
	$s = $numusers != 1 ? "s" : "";
	
	pageheader();
	
	// We don't need $grouplist anymore now, so it can be edited directly
	if (!has_perm('show-super-users')) {
		unset($grouplist[GROUP_SUPER]);
	}
	unset($grouplist[GROUP_SYSADMIN], $grouplist[GROUP_PERMABANNED], $grouplist[GROUP_GUEST]);
	$groupout = "";
	foreach ($grouplist as $key => $val) {
		$groupout .= "<a href='memberlist.php?sort={$_GET['sort']}$q$qsex$qord&pow=$key'>{$val['name']}</a> | ";
	}
	
print "
<table class='table'>
	<tr><td class='tdbgh center' colspan=2>$numusers user$s found.</td></tr>
	<tr>
		<td class='tdbg1 fonts center'>	Sort by:</td>
		<td class='tdbg2 fonts center'>
			<a href='memberlist.php?sort=posts$q$qpow$qsex$qord'>Total posts</a> |
			<a href='memberlist.php?sort=exp$q$qpow$qsex$qord'>EXP</a> |
			<a href='memberlist.php?sort=name$q$qpow$qsex$qord'>User name</a> |
			<a href='memberlist.php?sort=reg$q$qpow$qsex$qord'>Registration date</a> |
			<a href='memberlist.php?sort=age$q$qpow$qsex$qord'>Age</a>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 fonts center'>	Sex:</td>
		<td class='tdbg2 fonts center'>
			<a href='memberlist.php?sort={$_GET['sort']}$q$qpow$qord&sex=m'>Male</a> |
			<a href='memberlist.php?sort={$_GET['sort']}$q$qpow$qord&sex=f'>Female</a> |
			<a href='memberlist.php?sort={$_GET['sort']}$q$qpow$qord&sex=n'>N/A</a> |
			<a href='memberlist.php?sort={$_GET['sort']}$q$qpow$qord'>All</a><tr>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 fonts center'>	Group:</td>
		<td class='tdbg2 fonts center'>
			$groupout
			<a href='memberlist.php?sort={$_GET['sort']}$q$qsex$qord'>All</a>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 fonts center'>	Sort order:</td>
		<td class='tdbg2 fonts center'>
			<a href='memberlist.php?sort={$_GET['sort']}$q$qsex$qpow'>Descending</a> |
			<a href='memberlist.php?sort={$_GET['sort']}$q$qsex$qpow&ord=1'>Ascending</a>
		</td>
	</tr>
</table>
<br>
<table class='table'>
		<tr>
			<td class='tdbgh center' width=30>#</td>
			<td class='tdbgh center' width=16><img src=images/_.gif width=16 height=8></td>
			<td class='tdbgh center'>Username</td>
	";

	if(!$_GET['rpg']) {
		print "
			<td class='tdbgh center' width=200>Registered on</td>
			<td class='tdbgh center' width=60>Posts</td>
			<td class='tdbgh center' width=35>Level</td>
			<td class='tdbgh center' width=100>EXP</td></tr>
		";
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

	$s = $_GET['ppp']*$_GET['page'];
	$ulist = "";
	for ($u = 0; $u < $_GET['ppp']; ++$u) {
		$i = $s + $u;
		if (!isset($users[$i])) break;
		$ulist .= "<tr style='height:24px'>";
		$user = $users[$i];
		
		$userpicture = $user['minipic'] ? "<img width=16 height=16 src=\"".htmlspecialchars($user['minipic'])."\">" : '&nbsp;';
		$userlink = getuserlink($user);
		$ulist.="
			<td class='tdbg2 center'>".($i+1).".</td>
			<td class='tdbg1'>{$userpicture}</td>
			<td class='tdbg2'>{$userlink}</td>
		";

		if(!$_GET['rpg']){
			$ulist.="
				<td class='tdbg2 center'>".printdate($user['regdate'])."</td>
				<td class='tdbg1 center'>{$user['posts']}</td>
				<td class='tdbg1 center'>{$user['lvl']}</td>
				<td class='tdbg1 center'>{$user['exp']}</td>
			";
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
	
	function sortbyexpdesc($a,$b) {
		$cmpa = (($a['exp'] === 'NaN') ? -1 : (int) $a['exp']);
		$cmpb = (($b['exp'] === 'NaN') ? -1 : (int) $b['exp']);
		if ($cmpa == $cmpb) return $a['id']-$b['id'];
		return $cmpb - $cmpa;
	}
	// horror dot png
	function sortbyexpasc($a,$b) {
		$cmpa = (($a['exp'] === 'NaN') ? -1 : (int) $a['exp']);
		$cmpb = (($b['exp'] === 'NaN') ? -1 : (int) $b['exp']);
		if ($cmpa == $cmpb) return $b['id']-$a['id'];
		return $cmpa - $cmpb;
	}