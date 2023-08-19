<?php
$stat = array('HP','MP','Atk','Def','Int','MDf','Dex','Lck','Spd');
function basestat($posts, $days, $stat) {
	static $basedefs = NULL;
	$exp   = calcexp($posts, $days);
	$level = calclvl($exp);
	
	if($level === 'NaN'){
		return 1;
	}
	
	if ($basedefs === NULL) {
		$basedefs = array(
	//   stat => [posts pow, days pow, lvl pow, multiplier, base val] 
			0 => [     0.26,     0.08,    1.11,       0.95,       20], //HP
			1 => [     0.22,     0.12,    1.11,       0.32,       10], //MP
			2 => [     0.18,     0.04,    1.09,       0.29,        2], //Str
			3 => [     0.16,     0.07,    1.09,       0.28,        2], //Atk
			4 => [     0.15,     0.09,    1.09,       0.29,        2], //Def
			5 => [     0.14,     0.10,    1.09,       0.29,        1], //Shl
			6 => [     0.17,     0.05,    1.09,       0.29,        2], //Lck
			7 => [     0.19,     0.03,    1.09,       0.29,        1], //Int
			8 => [     0.21,     0.02,    1.09,       0.25,        1], //Spd
		);
	}
	
	return
		  pow($posts, $basedefs[$stat][0]) // posts pow
		* pow( $days, $basedefs[$stat][1]) // days pow
		* pow($level, $basedefs[$stat][2]) // level pow
		* $basedefs[$stat][3]              // base multiplier
		+ $basedefs[$stat][4];             // base stat value
}
function getstats($user, $items = array(), $class = array()){
	global $stat;
	
	$posts = $user['posts'];
	$days  = (time() - $user['regdate']) / 86400;
	$m     = array_fill(0, 9, 1); // initialize multipliers to *1
	$a     = array_fill(0, 9, 0); // initialize additions to +0
	
	foreach ($items as $item) {
		for ($k = 0; $k < 9; ++$k) {
			$statval = $item["s{$stat[$k]}"];
			// stype is a string containing a sequence of 'm' and 'a'
			if ($item['stype'][$k] === 'm') 
				$m[$k] *= $statval / 100; // fixed point
			else 
				$a[$k] += $statval;
		}
	}
	
	// Calculate all the stats
	for ($i = 0; $i < 9; ++$i) {
		$stats[$stat[$i]] = max(1, floor(basestat($posts, $days, $i) * $m[$i]) + $a[$i]);
	}
	
	// ...and their stat boost from classes
	for($k = 0; $k < 9; ++$k) {
		if (isset($class[$stat[$k]])) {
			//$stats[$stat[$k]]	= ceil($stats[$stat[$k]] * ($class[$stat[$k]] != 0 ? $class[$stat[$k]] : -1));		// 0 can be 0, anything else will result in 1 because of max(1)
			$stats[$stat[$k]] = ceil($stats[$stat[$k]] * $class[$stat[$k]]);
		}
	}

	$stats['GP']     = coins($posts, $days) - $user['spent'];
	$stats['exp']    = calcexp($posts, $days);
	$stats['lvl']    = calclvl($stats['exp']);
	return $stats;
}
function coins($posts, $days) {
	$posts += 0;
	if ($posts < 0 || $days < 0) return 0;
	else return floor(pow($posts, 1.3) * pow($days, 0.4) + $posts * 10);
}
function calcexpgainpost($posts, $days)	{
	return floor(1.5 * sqrt($posts * $days));
}

function calcexpgaintime($posts, $days)	{
	if ($posts == 0) return '0.000'; // no division by 0
	// <2 days> * (sqrt(days/posts) / posts)
	else return sprintf('%01.3f', 172800 * (sqrt($days / $posts) / $posts));
}

function calcexpleft($exp) {return calclvlexp(calclvl($exp) + 1) - $exp;}
function totallvlexp($lvl) {return calclvlexp($lvl + 1) - calclvlexp($lvl);}

function calclvlexp($lvl) {
	if ($lvl == 1) return 0;
	else return floor(pow(abs($lvl), 3.5)) * ($lvl > 0 ? 1 : -1);
}
function calcexp($posts, $days) {
	if (!$posts || !$days) return 0;
	else if ($posts > 0)   return floor($posts * sqrt($posts * $days));
	else                   return 'NaN'; // Negative posts (likely a banned user)
}
function calclvl($exp){
	if ($exp === 'NaN') {
		$lvl = $exp;
	} else if ($exp >= 0) {
		$lvl = floor(pow($exp, 2 / 7));
		// If we have enough exp for the next level, increase it
		if (calclvlexp($lvl + 1) == $exp) ++$lvl;
		else if (!$lvl)                   $lvl = 1;
	} else {
		// just in case, handle negative experience
		// even though normally it should be impossible to have it
		$lvl = -floor(pow(-$exp, 2 / 7));
	}
	return $lvl;
}
function getuseritems($user, $name = false, $extra = 0){
	global $sql;
	
	$num 	= $sql->fetchq("SELECT id FROM itemcateg", PDO::FETCH_COLUMN, mysql::FETCH_ALL);
	$q 		= "";
	foreach($num as $i){
		$q .= "r.eq$i = i.id OR ";
	}	
	
	// For our convenience we group this
	$itemdb = $sql->fetchq("
		SELECT i.cat, i.sHP, i.sMP, i.sAtk, i.sDef, i.sInt, i.sMDf, i.sDex, i.sLck, i.sSpd, i.effect".($name ? ", i.id, i.name" : "")."
		FROM items i
		INNER JOIN users_rpg r ON ($q $extra)
		WHERE r.uid = $user
	", PDO::FETCH_GROUP | PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
	
	return $itemdb;
}

