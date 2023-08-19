<?php
	chdir("../");
	require "lib/common.php";

	$_GET['u'] = filter_int($_GET['u']);
	if(!$_GET['u']) die("Missing ?u=<id> parameter");
	$user = $sql->fetchq("SELECT name,posts,regdate,users_rpg.* FROM users,users_rpg WHERE id='{$_GET['u']}' AND uid=id") or die("User doesn't exist");
	$p = $user['posts'];
	$d = (time()-$user['regdate'])/86400;
	
	$u  = filter_int($_GET['u']);	// User ID
	$it = filter_int($_GET['it']);	// Extra item (for item previews)
	$ne = filter_int($_GET['ne']);	// No item display
	$nc = filter_int($_GET['nc']);	// No RPG Class display
	$ct = filter_int($_GET['ct']);	// No coins display
	
	if(!$it)
		$it=0;
	if(!$ne) {
		$num 	= $sql->fetchq("SELECT id FROM itemcateg", PDO::FETCH_COLUMN, mysql::FETCH_ALL);
		$q 		= "";
		foreach($num as $i) $q .= " OR id = ".filter_int($user['eq'.$i]);
		$items = $sql->getarraybykey("SELECT * FROM items WHERE id=$it$q", 'id');
	}
	if(!$nc)
		$class = $sql->fetchq("SELECT * FROM `rpg_classes` WHERE `id` = '{$user['class']}'");

	if($ct) {
		$GPdif = floor($items[$user['eq'.$ct]][coins]*0.6)-$items[$it][coins];
		$user['eq'.$ct] = $it;
	}	

	$st=getstats($user,$items,$class);
	$st['GP']+=$GPdif;
	if($st['lvl']>0) $pct=1-calcexpleft($st['exp'])/totallvlexp($st['lvl']);



	$st['tonext']	= calcexpleft($st['exp']);
	$st['GP2']		= $user['gcoins'];
	$st['id']		= $_GET['u'];
	$st['name']		= $user['name'];
	$st['class']	= $user['class'];
	$st['classname']= $class['name'];
	

	if (isset($_REQUEST['s'])) {
		if ($_REQUEST['s'] == "json") {
			header("Content-type: application/json;");
			print json_encode($st);
			
		} else {
			header("Content-type: text/plain;");
			print serialize($st);
		}
	} else {
		header("Content-type: text/plain;");
		foreach ($st as $k => $v) {
			print "$k=$v\n";
		}
	}	