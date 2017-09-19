<?php

#	die();
	require 'lib/function.php';
	
	$_GET['id'] 	= filter_int($_GET['id']);
	$_GET['cat'] 	= filter_int($_GET['cat']);
	$_GET['item'] 	= filter_int($_GET['item']);
	$_GET['type'] 	= filter_int($_GET['type']);
	
	
	$stats		= array(
		0	=> "sHP", 
		1	=> "sMP", 
		2	=> "sAtk", 
		3	=> "sDef", 
		4	=> "sInt", 
		5	=> "sMDf", 
		6	=> "sDex", 
		7	=> "sLck",
		8	=> "sSpd", 
		);
	$effects	= array(
		"None",
		"1: Forces female gender",
		"2: Forces male gender",
		"3: Forces catgirl status [WIP]",
		"4: Other [WIP]",
		"5: Shows hidden comments"
	);
	
	if (!has_perm('use-shoped')) {
		pageheader("nope.avi");
		errorpage("No.");
	}

	$hiddeneditok	= has_perm('use-shoped-hidden'); //in_array($loguser['id'], array(1, 18));


	if (isset($_POST['edit'])) {
		check_token($_POST['auth']);
		
		if (filter_int($_POST['coins']) < 0 || filter_int($_POST['gcoins']) < 0) {
			// $sql -> query("UPDATE `users` SET `powerlevel` = -1, `title` = 'Next time, read the goddamn warning before doing something stupid'");
			die("You don't pay warnings much heed, do you?");
		}
		
		$vals = array(
			'name'		=> filter_string($_POST['name'], true),
			'desc'		=> filter_string($_POST['desc'], true),
			'cat'		=> filter_int($_POST['cat']),
			'type'		=> filter_int($_POST['type']),
			'effect'	=> filter_int($_POST['effect']),
			'coins'		=> filter_int($_POST['coins']),
			'gcoins'	=> filter_int($_POST['gcoins'])
		);
		$q = "name=:name,`desc`=:desc,cat=:cat,type=:type,effect=:effect,coins=:coins,gcoins=:gcoins,";
		
		if ($hiddeneditok) {
			$vals['hidden'] = filter_int($_POST['hidden']);
			$q .= "hidden=:hidden,";
		}
	
		$stypes = "";
		foreach($stats as $stat) {
			$itemstat 	= filter_float($_POST[$stat]);
			$operator 	= filter_string($_POST["m$stat"]);	// m -> * [.2 float], (nothing) -> + [int]
			if ($operator == "m") $itemstat *= 100;
			$vals[":$stat"] = $itemstat;
			$q 		.= "$stat=:$stat,";
			$stypes	.= $operator;
		}
		$vals['stype'] = $stypes;
		$q	.= "stype=:stype";
		
		$queryok = array();
		if ($_GET['id'] <= -1) {
			$sql->queryp("INSERT INTO items SET $q, user = {$loguser['id']}", $vals, $queryok);
			if (!$queryok[0]) errorpage("Couldn't add the item.");
			$id	= $sql->insert_id();
		} else {
			$sql->queryp("UPDATE items SET $q WHERE id = {$_GET['id']}", $vals, $queryok);
			if (!$queryok[0]) errorpage("Couldn't add the item.");
			$id	= $_GET['id'];
		}

		header("Location: ?cat=".filter_int($_POST['cat'])."&id=". $id . ($_GET['type'] ? "&type=".filter_int($_POST['type']) : ""));
		die($q);
	}


	pageheader("Shop Editor");

	?>
	<table class='table'>
		<tr><td class='tdbgh center'><b>WARNING</b></td></tr>
		<tr>
			<td class='tdbg1 center'>
				MAKE AN ITEM WITH A NEGATIVE COST AND YOU <span style="border-bottom: 1px dotted #f00;font-style:italic;" title="did you mean: won't really (but don't try it anyway, it won't work)">WILL</span> GET BANNED
			</td>
		</tr>
	</table>
	<br>
	<?php
/*
	$categories	= array(
		1	=> "Weapons",
		2	=> "Armor",
		3	=> "Shields",
		4	=> "Helmets",
		5	=> "Boots",
		6	=> "Accessories",
		7	=> "Usable",
		99	=> "Special",
	);*/
	$categories = $sql->fetchq("SELECT id, name FROM itemcateg ORDER BY id ASC", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
	$categories[99] = "Special";
		

	$cat	= $_GET['cat'] ? $_GET['cat'] : 1;
	
	echo linkbar($categories, $cat);

//	$stats	= array("sHP", "sMP", "sAtk", "sDef", "sInt", "sMDf", "sDex", "sLck", "sSpd");

	$types	= $sql -> query("SELECT `id`, `name` FROM `itemtypes` WHERE `id` IN (SELECT DISTINCT(`type`) FROM `items` WHERE `cat` = '$cat') ORDER BY `ord` ASC");
	$typerow[0]		= "";
	while ($type	= $sql -> fetch($types)) {
		$typerow[$type['id']] = "<tr><td class='tdbgc center' colspan=\"16\"><a href=\"?cat=". $_GET['cat'] . ($_GET['item'] ? "&item=". $_GET['item'] : "") . ($_GET['type'] == $type['id'] ? "" : "&type=". $type['id']) ."\">". $type['name'] ."</a></td></tr>";
	}
	$typerow[255] = "<tr><td class='tdbgc center' colspan=\"16\"><b>Unknown</b></td></tr>";
	
	if ($_GET['id']) {
	
		$typesq	= $sql -> query("SELECT `id`, `name` FROM `itemtypes` ORDER BY `ord` ASC");
		$alltypes[255]	= "Unknown";
		while($typex = $sql -> fetch($typesq)) {
			$alltypes[$typex['id']]	= $typex['name'];
		}

		
		$item	= $sql->fetchq("SELECT * FROM `items` WHERE `id` = '". $_GET['id'] ."'" . ($hiddeneditok ? "" : " AND `hidden` = '0'"));
		if (!$item) {
			$item = array('cat' => $cat, 'name' => "", 'desc' => "", 'hidden' => "", 'type' => "", 'effect' => 0, 'coins' => 0, 'gcoins' => 0);
			$_GET['id']		= -1;
			$newitem 		= true;
		}

		foreach ($stats as $n => $stat) {
			if (!isset($newitem) && $item['stype']{$n} == "m") {
				$optionbox		= "<select name=\"m$stat\"><option value=\"m\" selected>x</option><option value=\"a\">+/-</option></select>";
				$val			= number_format($item[$stat] / 100, 2);
			} else {
				$optionbox		= "<select name=\"m$stat\"><option value=\"m\">x</option><option value=\"a\" selected>+/-</option></select>";
				$val			= isset($item[$stat]) ? $item[$stat] : "";
			}
			$stbox[$stat]	= "
				<td class='tdbgh center' width=\"11%\">". substr($stat, 1) ."</td>
				<td class='tdbg1' width=\"22%\"><input type=\"text\" name=\"$stat\" maxlength=\"8\" size=\"5\" value=\"$val\" class=\"right\"> $optionbox</td>";
		}
?>
		<form method="post" action="?cat=1&id=<?=$_GET['id']?><?=($_GET['type'] ? "&type=". $_GET['type'] : "")?>">
		<table class='table'>
			<tr>
				<td class='tdbgh center' colspan=6>Editing <b><?=($_GET['id'] >= 1 ? htmlspecialchars($item['name']) : "New item")?></b></td>
			</tr>
			<tr>
				<td class='tdbgh center'>Name</td>
				<td class='tdbg1' colspan=3><input type="text" name="name" value="<?=htmlspecialchars($item['name'])?>"  style="width: 100%" maxlength="255">
					<?=($hiddeneditok ? "<br><input type=\"checkbox\" id=\"hiddenitem\" name=\"hidden\" value=\"1\"". ($item['hidden'] ? " checked" : "") ."> <label for=\"hiddenitem\">Hidden item</label>" : "")?>
				</td>
				<td class='tdbgh center'>Category</td>
				<td class='tdbg1'><?=linkbar($categories, $item['cat'], 1, "cat")?> / <?=linkbar($alltypes, $item['type'], 1, "type")?></td>
			</tr>
			<tr>
				<td class='tdbgh center'>Desc</td>
				<td class='tdbg1' colspan=3><input type="text" name="desc" value="<?=htmlspecialchars($item['desc'])?>" style="width: 100%"></td>
				<td class='tdbgh center'>Effect</td>
				<td class='tdbg1'><?=linkbar($effects, $item['effect'], 1, "effect")?></td>
			</tr>

			<tr><td class='tdbgc center' colspan=6><img src="images/_.gif" height=6 width=6></td></tr>
			<tr><?=$stbox['sHP'] ?><?=$stbox['sMP'] ?><?=$stbox['sLck']?></tr>
			<tr><?=$stbox['sAtk']?><?=$stbox['sInt']?><?=$stbox['sDex']?></tr>
			<tr><?=$stbox['sDef']?><?=$stbox['sMDf']?><?=$stbox['sSpd']?></tr>
			<tr><td class='tdbgc center' colspan=6><img src="images/_.gif" height=6 width=6></td></tr>

			<tr>
				<td class='tdbgc center' colspan=2><input type="submit" name="edit" value="Save"><input type="hidden" name="auth" value="<?=generate_token()?>"</td>
				<td class='tdbgh center'> Coins </td>
				<td class='tdbg1'><input type="text" name="coins" maxlength="8" size="10" value="<?=$item['coins']?>" class="right"> <img src="images/coin.gif" align="absmiddle"></td>
				<td class='tdbgh center'> G.Coins </td>
				<td class='tdbg1'><input type="text" name="gcoins" maxlength="8" size="10" value="<?=$item['gcoins']?>" class="right"> <img src="images/coin2.gif" align="absmiddle"></td>
			</tr>
		
		</table></form><br>
<?php
	}

	$items	= $sql->query("
		SELECT `items`.*, `users`.`id` as uid, `users`.`sex` as usex, `users`.`group` as ugroup, `users`.`namecolor` as unc, `users`.`name` as uname
		FROM `items`
		LEFT JOIN `users` ON `users`.`id` = `items`.`user`
		WHERE `cat` = '$cat'". ($_GET['type'] ? "
		AND `type` = '". $_GET['type'] ."' " : "") .
		($hiddeneditok ? "" : " AND `hidden` = '0'") ."
		ORDER BY `type` ASC, `coins` ASC, `gcoins` ASC
	");
	
	?>
	<table class='table'>
		<tr><td class='tdbgc center' colspan="16">&lt; <a href="?cat=$cat&id=-1">New Item</a> &gt;</td></tr>
		<tr>
			<td class='tdbgh center'>&nbsp;</td>
			<td class='tdbgh center' colspan='2'>Name</td>
			<td class='tdbgh center'>HP</td>
			<td class='tdbgh center'>MP</td>
			<td class='tdbgh center'>Atk</td>
			<td class='tdbgh center'>Def</td>
			<td class='tdbgh center'>Int</td>
			<td class='tdbgh center'>MDf</td>
			<td class='tdbgh center'>Dex</td>
			<td class='tdbgh center'>Lck</td>
			<td class='tdbgh center'>Spd</td>
			<td class='tdbgh center'>Efx</td>
			<td class='tdbgh center'>Coins</td>
			<td class='tdbgh center'>G.Coins</td>
			<td class='tdbgh center'>Pv</td>
		</tr>
	<?php

	while ($item = $sql->fetch($items)) {
		$stype	= str_split($item['stype']);
		
		if ($_GET['id'] == $item['id']) {
			$tc1	= "h";
			$tc2	= "h";
		} else {
			$tc2	= "2";
			$tc1	= "1";
		}

		if ($item['hidden']) {
			$item['name']	= "<img src='images/dot4.gif' align='absmiddle'> ". $item['name'];
		}

/*
		if ($item['uname']) {
			$item['name']	= "<a href=\"profile.php?id=". $item['uid'] ."\" class=\"fonts\"><font ". getnamecolor($item['usex'], $item['upow']) .">". $item['uname'] ."'s</font></a> ". $item['name'];
		}
*/
		if ($item['uname']) {
			$item['uname']	= "<nobr><a href=\"profile.php?id=". $item['uid'] ."\" class=\"fonts\"><span style='color: #". getnamecolor($item['usex'], $item['ugroup'], $item['unc']) ."'>". $item['uname'] ."</span></a></nobr>";
		} else {
			$item['uname']	= "";
		}

		if ($item['desc']) {
			$item['name']	.= " <span class=\"fonts\" style=\"color: #88f;\">- ". $item['desc'] ."</span>";
		}

		$typerow[$item['type']] .= "<tr>
				<td class='tdbg1 fonts center'><a href=\"?cat=$cat&id=". $item['id'] . ($_GET['type'] ? "&type=". $_GET['type'] : "") ."\">Edit</a></td>
				<td class='tdbg{$tc2} center'>". $item['uname'] ."</td><td class='tdbg{$tc2}'>". $item['name'] ."</td>";

		$val	= 0;
		foreach($stats as $n => $stat) {
			$num	= ($stype[$n] == "m" ? vsprintf('%1.2fx',$item[$stat]/100) : $item[$stat]);
			if ($item[$stat] > 0 && $stype[$n] != "m") $num = "+". $num;
			if ($item[$stat] == 0 && $stype[$n] != "m") $num = "";

			if ($item[$stat] > 0 && $stype[$n] == "a") {
				$num = "<font color=\"#88ff88\">$num</font>";
				$val += floor(pow(($item[$stat] * 1.80), 1.739));

			} elseif ($item[$stat] > 100 && $stype[$n] == "m") {
				$num = "<font color=\"#ccffcc\">$num</font>";
				$val += floor(pow(($item[$stat] - 100) * 100, 1.3));

			} elseif ($item[$stat] < 0 && $stype[$n] == "a") {
				$num = "<font color=\"#ffbbbb\">$num</font>";
				$val -= floor(pow(abs(($item[$stat]) * 2), 1.25));

			} elseif ($item[$stat] < 100 && $stype[$n] == "m") {
				$num = "<font color=\"#ff8888\">$num</font>";
				$val -= floor(pow(abs($item[$stat] - 100) * 2.5, 1.3));
			}

			$typerow[$item['type']] .= "<td class='tdbg{$tc1} center'>". $num ."</td>\n";
		}

		$valt	= $val ."t";
		$val	= round(($val * 2), -1 * (strlen($valt) - 3)) / 2;

		$val	= number_format($val);
		

		$typerow[$item['type']] .= "
				<td class='tdbg{$tc2} center'>". ($item['effect'] ? $item['effect'] : "&nbsp;") ."</td>
				<td class='tdbg{$tc2} right'>". number_format($item['coins']) ."</td>
				<td class='tdbg{$tc2} right'>". number_format($item['gcoins']) ."</td>
				<td class='tdbg{$tc2} right fonts nobr'>". $val ." Pv</td>
			</tr>";
	}

	if ($typerow[0]) {
		$typerow[0]	= "<tr><td class='tdbgc center' colspan=\"16\"><b>???</b></td></tr>". $typerow[0];
	}

	print implode("", $typerow);
	?>
	<tr>
		<td class='tdbgh center'>&nbsp;</td>
		<td class='tdbgh center' colspan='2'>Name</td>
		<td class='tdbgh center'>HP</td>
		<td class='tdbgh center'>MP</td>
		<td class='tdbgh center'>Atk</td>
		<td class='tdbgh center'>Def</td>
		<td class='tdbgh center'>Int</td>
		<td class='tdbgh center'>MDf</td>
		<td class='tdbgh center'>Dex</td>
		<td class='tdbgh center'>Lck</td>
		<td class='tdbgh center'>Spd</td>
		<td class='tdbgh center'>Efx</td>
		<td class='tdbgh center'>Coins</td>
		<td class='tdbgh center'>G.Coins</td>
		<td class='tdbgh center'>Pv</td>
	</tr>
	<tr><td class='tdbgc center' colspan="16">&lt; <a href="?cat=$cat&id=-1">New Item</a> &gt;</td></tr>
	</table>
	<?php
	
	pagefooter();




	function linkbar($links, $sel = 1, $type = 0, $name = "cat") {

		if ($type == 0) {
			$c	= count($links);
			$w	= floor(1 / $c * 100);

			$r	= "<table class='table'><tr><td class='tdbgh center' colspan=$c><b>Item Categories</b></td></tr><tr>";

			foreach($links as $link => $name) {

				$cell = ($link == $sel) ? "c" : "1";
				$r	.= "<td class='tdbg{$cell} center' width=\"$w%\"><a href=\"?cat=$link\">$name</a></td>";
			}

			return $r ."</table><br>";
		} else {

			$r	= "<select name=\"$name\">";

			foreach($links as $link => $name) {
				$r	.= "<option value=\"$link\"". ($sel == $link ? " selected" : "") .">$name</option>";
			}

			return $r ."</select>";
		}
	}
?>