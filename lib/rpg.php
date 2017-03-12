<?php
$stat = array('HP','MP','Atk','Def','Int','MDf','Dex','Lck','Spd');
function basestat($p,$d,$stat){
	$p+=0;
	$e=calcexp($p,$d);
	$l=calclvl($e);
	if($l=='NAN') return 1;
	switch($stat){
		case 0: return (pow($p,0.26) * pow($d,0.08) * pow($l,1.41) * 0.95) + 20; //HP
		case 1: return (pow($p,0.22) * pow($d,0.12) * pow($l,1.41) * 0.32) + 10; //MP
		case 2: return (pow($p,0.18) * pow($d,0.04) * pow($l,1.37) * 0.29) +  2; //Str
		case 3: return (pow($p,0.16) * pow($d,0.07) * pow($l,1.37) * 0.28) +  2; //Atk
		case 4: return (pow($p,0.15) * pow($d,0.09) * pow($l,1.37) * 0.29) +  2; //Def
		case 5: return (pow($p,0.14) * pow($d,0.10) * pow($l,1.37) * 0.29) +  1; //Shl
		case 6: return (pow($p,0.17) * pow($d,0.05) * pow($l,1.37) * 0.29) +  2; //Lck
		case 7: return (pow($p,0.19) * pow($d,0.03) * pow($l,1.37) * 0.29) +  1; //Int
		case 8: return (pow($p,0.21) * pow($d,0.02) * pow($l,1.37) * 0.25) +  1; //Spd
	}
}
function getstats($u, $items=array(), $class = array()){
	global $stat;
	$p = $u['posts'];
	$d = (ctime()-$u['regdate'])/86400;
	for($i=0; $i<9; ++$i) {
		$m[$i] = 1;
	}
	$a = array_fill(0, 9, 0);
	for($i = 1; $i < 7; ++$i) {
		$item = filter_int($items[$u['eq'.$i]]);
		for($k = 0; $k < 9; ++$k){
			$is = $item["s{$stat[$k]}"];
			if (substr($item['stype'],$k,1)=='m') $m[$k] *= $is / 100;
			else $a[$k] += $is;
		}
	}
	for($i = 0; $i < 9; ++$i){
		$stats[$stat[$i]] = max(1, floor(basestat($p, $d, $i) * $m[$i]) + $a[$i]);
	}
	// after calculating stats with items
	for($k = 0; $k < 9; ++$k) {
		if (isset($class[$stat[$k]])) {
			//$stats[$stat[$k]]	= ceil($stats[$stat[$k]] * ($class[$stat[$k]] != 0 ? $class[$stat[$k]] : -1));		// 0 can be 0, anything else will result in 1 because of max(1)
			$stats[$stat[$k]] = ceil($stats[$stat[$k]] * $class[$stat[$k]]);
		}
	}

	$stats['GP'] 	= coins($p,$d)-$u['spent'];
	$stats['exp'] 	= calcexp($p,$d);
	$stats['lvl'] 	= calclvl($stats['exp']);
	return $stats;
}
function coins($p,$d){
	$p+=0;
	if($p<0 || $d<0) return 0;
	return floor(pow($p,1.3) * pow($d,0.4) + $p*10);
}

function calcexpgainpost($posts,$days)	{return @floor(1.5*@pow($posts*$days,0.5));}
function calcexpgaintime($posts,$days)	{return sprintf('%01.3f',172800*@(@pow(@($days/$posts),0.5)/$posts));}

function calcexpleft($exp)			{return calclvlexp(calclvl($exp)+1)-$exp;}
function totallvlexp($lvl)			{return calclvlexp($lvl+1)-calclvlexp($lvl);}

function calclvlexp($lvl){
  if($lvl==1) return 0;
  else return floor(pow(abs($lvl),3.5))*($lvl>0?1:-1);
}
function calcexp($posts,$days){
  if(@($posts/$days)>0) return floor($posts*pow($posts*$days,0.5));
  elseif($posts==0) return 0;
  else return 'NaN';
}
function calclvl($exp){
  if($exp>=0){
    $lvl=floor(@pow($exp,2/7));
    if(calclvlexp($lvl+1)==$exp) $lvl++;
    if(!$lvl) $lvl=1;
  }else $lvl=-floor(pow(-$exp,2/7));
  if(is_string($exp) && $exp=='NaN') $lvl='NaN';
  return $lvl;
}
function getuseritems($user, $name = false, $extra = 0){
	global $sql;
	
	$num 	= $sql->fetchq("SELECT id FROM itemcateg", PDO::FETCH_COLUMN, false, true);
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
	", PDO::FETCH_GROUP | PDO::FETCH_UNIQUE, false, true);
	
	return $itemdb;
}

