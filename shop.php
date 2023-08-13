<?php

	/*
	if ($_GET['action'] == "buy" && $_GET['id'] == 286) {
		return header("Location: shoph.php");
	}
	*/
	require "lib/common.php";
	
	$id		= filter_int($_GET['id']);
	$cat 	= filter_int($_GET['cat']);
	$action = filter_string($_GET['action']);
	//$lol	= filter_int($_GET['lol']);
	
	if (!$loguser['id']) {
		errorpage("You must be logged in to access the Item Shop.",'index.php','return to the main page',0);
	}
	//if ($isadmin && $lol) {
	//	$loguser['id'] = $lol;
	//}
	
	if ($action == "buy" && $id == 286) {
		// Fuck unmantained alternate pages
		//return header("Location: shoph.php");
		return header("Location: shop.php?h");
	}
	
	if (isset($_GET['h'])) {
		$hidden = 1;
		$h_q = "?h&";
	} else {
		$hidden = 0;
		$h_q = "?";
	}

	pageheader();

	$user 	= $sql->fetchq("SELECT u.posts, u.regdate, r.* FROM users u INNER JOIN users_rpg r ON u.id = r.uid WHERE id = {$loguser['id']}");
	
	$st = getstats($user);
	$GP = $st['GP'];
	
	if ($action == "buy") {
		check_token($_GET['auth'], TOKEN_SHOP);
		
		$item = $sql->fetchq("SELECT * FROM items WHERE id = $id AND hidden = $hidden");
		if (!$item || $item['coins'] > $GP || $item['gcoins'] > $user['gcoins'])
			errorpage("You don't have enough coins to buy this item!");
		
		$pitem = $sql->fetchq("SELECT coins FROM items WHERE id=".$user['eq'.$item['cat']]);
		$whatever = $item['coins'] - $pitem['coins'] * 0.6;
		//print "Debug output: Cost: ". $item['coins'] ." - Current item's sell value: ". ($pitem['coins'] * 0.6) ." - Amount to subtract: ". $whatever .". /debug";
		$sql->query("UPDATE users_rpg SET `eq{$item['cat']}`= $id, `spent` = spent + $whatever, `gcoins` = `gcoins` - {$item['gcoins']} WHERE uid = {$loguser['id']}");
		errorpage("The ".htmlspecialchars($item['name'])." has been bought and equipped.","shop.php$h_q",'return to the shop',0);		
		
	}
	else if ($action == "sell") {
		check_token($_GET['auth'], TOKEN_SHOP);
		
		// "The has been unequipped and sold"
		if (!$user['eq'.$cat])
			errorpage("Just what do you think you're doing?");
		
		$item = $sql->fetchq("SELECT name, coins FROM items WHERE id=".$user['eq'.$cat]);
		$sql->query("UPDATE users_rpg SET eq$cat = 0, spent = spent-{$item['coins']}*0.6 WHERE uid={$loguser['id']}");
		errorpage("The ".htmlspecialchars($item['name'])." has been unequipped and sold.","shop.php$h_q",'return to the shop',0);
	}
	else if (!$action) {

		//$shops 		= $sql->query('SELECT * FROM itemcateg ORDER BY corder');
		$shops		= $sql->fetchq("SELECT * FROM itemcateg ORDER BY corder", PDO::FETCH_ASSOC, mysql::FETCH_ALL);
		$eq 		= $sql->fetchq("SELECT * FROM users_rpg WHERE uid = {$loguser['id']}");
		$q 			= "";
		foreach($shops as $i) $q .= " OR id=".$eq["eq".$i['id']];
		$eqitems 	= $sql->query("SELECT * FROM items WHERE 0$q");
		while ($item = $sql->fetch($eqitems)) $items[$item['id']] = $item;
		$shoplist = "";
		foreach ($shops as $shop)
			$shoplist .= "
			<tr>
				<td class='tdbg1 center'><a href='shop.php{$h_q}action=items&cat={$shop['id']}#status'>".htmlspecialchars($shop['name'])."</a></td>
				<td class='tdbg2 fonts center'>".htmlspecialchars($shop['description'])."
				<td class='tdbg1 fonts center'>".htmlspecialchars(filter_string($items[$eq['eq'.$shop['id']]]['name']))."
			</tr>
			";
		?>
		
		
		<table style="width: 100%">
			<tr>
				<td valign=top style="width: 120px"><img src="status.php?u=<?=$loguser['id']?>"></td>
				<td valign=top>
					<?=($hidden ? "
					<table class='table'>
						<tr><td class='tdbgh center'><b>???</b></td></tr>
						<tr><td class='tdbg1 center'>This place hasn't been touched in ages...</td></tr>
					</table><br>" : "")?>
					<table class='table'>
						<tr><td class='tdbgh center' colspan=3><?=($hidden ? "Hidden Shop" : "Shop list")?></td></tr>
						<tr>
							<td class='tdbgc center'>Shop</td>
							<td class='tdbgc center'>Description</td>
							<td class='tdbgc center'>Item equipped</td>
						</tr>
						<?=$shoplist?>
					</table>
				</td>
			</tr>
		</table>
		<?php		
	}
	else if ($action == "items") {
		$eq 	= $sql->resultq("SELECT eq$cat FROM users_rpg WHERE uid = {$loguser['id']}");
		$eqitem = $sql->fetchq("SELECT * FROM items WHERE id = $eq");
		
		$token  = generate_token(TOKEN_SHOP);
?>
	<script>
		function preview(user,item,cat,name){
			document.getElementById('prev').src='status.php?u='+user+'&it='+item+'&ct='+cat+'&'+Math.random();
			document.getElementById('pr').innerHTML='Equipped with<br>'+name+'<br>---------->';
		}
	</script>
	<style>
		.disabled {color:#888888}
		.higher   {color:#abaffe}
		.equal    {color:#ffea60}
		.lower    {color:#ca8765}
	</style>
	<table class='table'><tr><td class='tdbg1 center'><a href="shop.php<?=$h_q?>">Return to shop list</a></td></tr></table>
	<table>
		<tr>
			<td style='width: 256px'>
				<img src="status.php?u=<?=$loguser['id']?>">
			</td>
			<td class='center fonts' style='width: 150px'>
				<div id=pr><!-- preview text --></div>
			</td>
			<td>
				<img src="images/_.gif" id=prev>
			</td>
		</tr>
	</table>
	<br>
<?php
		// Table fields
		$atrlist='';
		for($i=0; $i<9; ++$i) $atrlist.="<td class='tdbgh center' width=50>{$stat[$i]}</td>";
		$items = $sql->query("SELECT * FROM items WHERE cat = $cat AND hidden = $hidden ORDER BY type, coins");
		?>
	<table class='table'>
		<tr>
			<td class='tdbgh center' style="width: 110px" colspan=2>Commands</td>
			<td class='tdbgc fontt center' style="width: 1px" rowspan=10000>&nbsp;</td>
			<td class='tdbgh center' colspan=1>Item</td>
			<?=$atrlist?>
			<td class='tdbgh center' style="width: 6%"><img src="images/coin.gif"></td>
			<td class='tdbgh center' style="width: 5%"><img src="images/coin2.gif"></td>
		</tr>
		<?php
		// Item list
		while ($item = $sql->fetch($items)) {
			// Buy/Sell/Preview text
			$preview = "<a href=#status onclick='preview({$loguser['id']},{$item['id']},$cat,\"". htmlentities($item['name'], ENT_QUOTES) ."\")'>Preview</a>";
			if ($item['id'] == $eq && $item['id']) {
				$comm = "80 colspan=2><a href='shop.php{$h_q}action=sell&cat=$cat&auth=$token'>Sell</a>";
			} else if ($item['id'] && $item['coins'] <= $GP && $item['gcoins'] <= $user['gcoins']){
				$comm = "30><a href='shop.php{$h_q}action=buy&id={$item['id']}&auth=$token'>Buy</a></td><td class='tdbg1 center' width=50>$preview";
			} else if (!$eq && !$item['id']){
				$comm = "80 colspan=2>-";
			} else {
				$comm  ="80 colspan=2>$preview";
			}
			// Affordable?
			if ($item['id'] == $eqitem['id'])
				$color = 'class=equal';
			else if ($item['coins'] > $GP || $item['gcoins'] > $user['gcoins'])
				$color = 'class=disabled';
			else
				$color = '';
			
			// Item attributes
			$atrlist='';
			for ($i = 0; $i < 9; ++$i) {
				$st = $item["s{$stat[$i]}"];
				if (substr($item['stype'], $i, 1) == 'm') { // * .2 float
				  $st = vsprintf('x%1.2f',$st/100);
				  if ($st==100) $st = '&nbsp;';
				} else {
				  if ($st>0) $st = "+$st";
				  if (!$st)  $st = '&nbsp;';
				}
				
				$itst = $item["s{$stat[$i]}"];
				$eqst = $eqitem["s{$stat[$i]}"];
				// Convenience for same-operator EXP Statuses
				if(!$color && substr($item['stype'],$i,1) == substr($eqitem['stype'],$i,1)){
				  if($itst> $eqst) $st="<span class=higher>$st</span>";
				  if($itst==$eqst) $st="<span class=equal>$st</span>";
				  if($itst< $eqst) $st="<span class=lower>$st</span>";
				}
				$atrlist .= "<td class='tdbg1 center'>$st</td>";
			}

			$item['name'] = htmlspecialchars($item['name']);
			if ($item['desc']) {
				$item['name']	.= " <span class='fonts' style='color: #88f'>- ". htmlspecialchars($item['desc']) ."</span>";
			}

			?>
		<tr <?=$color?>>
			<td class='tdbg1 center' width=<?=$comm?></td>
			<td class='tdbg2'><?=$item['name']?></td>
			<?=$atrlist?>
			<td class='tdbg2 right'><?=($item['coins']  < 8388607 ? $item['coins']  : "tons")?></td>
			<td class='tdbg2 right'><?=($item['gcoins'] < 8388607 ? $item['gcoins'] : "tons")?></td>
		<?php
		}
	?>
	</table>
	<?php
	}
	else {
		errorpage("0 PTS. AWARDED FOR THIS STUNT");
	}

	pagefooter();
?>