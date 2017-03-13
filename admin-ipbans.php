<?php

	require "lib/function.php";
	
	admincheck();
	
	// Quick hack to allow linking other pages to searches here
	if (isset($_GET['ip'])){
		$_POST['searchip'] 	= $_GET['ip'];
	}
	
	if (isset($_POST['ipban'])){
		check_token($_POST['auth']);

		// Here we go
		if (!filter_string($_POST['newip'])) errorpage("You forgot to enter an IP!");
		
		$sql->queryp("INSERT INTO `ipbans` SET `ip`=:ip, `reason`=:reason, `date`=:date, `expire`=:expire, `banner`=:banner", 
			[
				'ip'     => $_POST['newip'],
				'reason' => filter_string($_POST['reason'], true),
				'expire' => filter_int($_POST['expire']) ? (ctime() + ((int) $_POST['expire']) * 3600) : 0,
				'date'   => ctime(),
				'banner' => $loguser['id'],
			]);
		
		$ircreason 	= filter_string($_POST['ircreason']); // Don't strip out control codes for this!
		
		xk_ircsend("1|". xk(8) . $loguser['name'] . xk(7) ." added IP ban for ". xk(8) . $_POST['newip'] . xk(7) . ($ircreason ? " for this reason: " . xk(8) . $ircreason . xk(7) : "") . ".");
		
		#setmessage("Added IP ban for {$_POST['newip']}.");
		return header("Location: ?");	
	}
	else if (isset($_POST['dodel']) && isset($_POST['delban'])){
		check_token($_POST['auth']);
		
		// Iterate over the sent IPs and add them to the query
		if (!empty($_POST['delban'])){
			
			$i = 0;
			$q = "";
			$banout = array();
			foreach ($_POST['delban'] as $ban){
				$q .= ($i ? " OR " : "")."ip = ?";
				$banout[$i] = $ban;
				++$i;
			}
			
			$sql->queryp("DELETE from ipbans WHERE $q", $banout);
			#setmessage("Removed IP ban for $i IP(s).");
		} else {
			#setmessage("No IP bans selected.");
		}
		return header("Location: ?");	
	}
	
	
	$page 		= filter_int($_GET['page']);
	if (isset($_POST['setreason']) && $_POST['setreason']) {
		$reason = filter_string($_POST['setreason']);
	} else {
		$reason = filter_string($_POST['searchreason']);
	}
	$limit = 100;
	
	// Query values
	$outres = array();
	$reasonsearch = $searchip = "1";
	if ($reason) {
		$outres['reason'] = $reason;
		$reasonsearch = "i.reason = :reason";
	}
	if (isset($_POST['searchip'])) {
		$outres['searchip'] = $_POST['searchip'].'%';
		$searchip = "i.ip LIKE :searchip";
	}
	
	$total = $sql->resultq("SELECT COUNT(*) FROM ipbans");
	
	$bans  = $sql->queryp("
		SELECT i.ip, i.date, i.reason, i.perm, i.banner, i.expire, $userfields
		FROM ipbans i
		LEFT JOIN users u ON i.banner = u.id
		WHERE {$reasonsearch} AND {$searchip}
		ORDER BY i.date DESC
		LIMIT ".($page*$limit).",$limit
	", $outres);
	
	$pagectrl	= "<span class='fonts'>Pages: ".dopagelist("admin-ipbans.php?reason=$reason", $total, $limit)."</span>";
	
	$txt = "";
	while ($x = $sql->fetch($bans)) {
		$txt .= "
			<tr>
				<td class='tdbg2 center'><input type='checkbox' name='delban[]' value='{$x['ip']}'></td>
				<td class='tdbg1 center'>{$x['ip']}</td>
				<td class='tdbg2 center'>".printdate($x['date'])."</td>
				<td class='tdbg2 center'>".($x['expire'] ? printdate($x['expire'])." (".timeunits2($x['expire']-ctime()).")" : "Never")."</td>
				<td class='tdbg1'>".($x['reason'] ? htmlspecialchars($x['reason']) : "None")."</td>
				<td class='tdbg2 center'>".($x['banner'] ? getuserlink($x) : "Automatic")."</td>
			</tr>
		";
	}
	
	pageheader("{$config['board-name']} -- IP Bans");
	print adminlinkbar();
	
	?>
	<form method='POST' action='admin-ipbans.php'>
	<input type='hidden' name='auth' value='<?= generate_token() ?>'>

	<table class='table'>
		<tr>
			<td class='tdbgh' style='width: 120px'>&nbsp;</td>
			<td class='tdbgh'>&nbsp;</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>
				Search IP:
			</td>
			<td class='tdbg2'>
				<input type='text' name='searchip' value="<?= htmlspecialchars(filter_string($_POST['searchip'])) ?>">
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>
				Reason:
			</td>
			<td class='tdbg2'>
				<input type='text' name='searchreason' size=72 value="<?= htmlspecialchars($reason) ?>"> or special: 
				<select name="setreason">
					<option value=""></option>
					<option value="Send e-mail for password recovery">Password recovery</option>
					<option value="Send e-mail to re-request the registration code">Regcode recovery</option>
					<option value="online.php ban">Online users ban</option>
					<option value="Abusive/unwelcome activity">Denied request ban</option>
				</select>
			</td>
		</tr>
		<tr><td class='tdbg2' colspan='2'><input type='submit' name='dosearch' value='Search'></td></tr>
	</table>
	
	<br>
	
	<?= $pagectrl ?>
	<table class='table'>
		<tr>
			<td class='tdbgh center'>#</td>
			<td class='tdbgh center'>IP Address</td>
			<td class='tdbgh center' style='width: 200px'>Ban date</td>
			<td class='tdbgh center' style='width: 350px'>Expiration date</td>
			<td class='tdbgh center'>Reason</td>
			<td class='tdbgh center'>Banned by</td>
		</tr>
		<?= $txt ?>
		<tr><td class='tdbg2' colspan='6'><input type='submit' name='dodel' value='Delete selected'></td></tr>
	</table>
	<?= $pagectrl ?>
	
	<br><br>
	
	<table class='table'>
		<tr><td class='tdbgh center b' colspan='2'>Add IP ban</td></tr>
		
		<tr>
			<td class='tdbg1 center' style='width: 120px'><b>IP Address</b></td>
			<td class='tdbg2'><input type='text' name='newip'></td>
		</tr>
		<tr>
			<td class='tdbg1 center'><b>Reason</b></td>
			<td class='tdbg2'><input type='text' name='reason' style='width: 500px'></td>
		</tr>
		<tr>
			<td class='tdbg1 center'><b>IRC Reason</b></td>
			<td class='tdbg2'><input type='text' name='ircreason' style='width: 500px'></td>
		</tr>
		<tr>
			<td class='tdbg1 center'><b>Duration</b></td>
			<td class='tdbg2'>
				<select name='expire'>
					<option value='0'>*Permanent</option>
					<option value='1'>1 hour</option>
					<option value='3'>3 hours</option>
					<option value='6'>6 hours</option>
					<option value='24'>1 day</option>
					<option value='72'>3 days</option>
					<option value='168'>1 week</option>
					<option value='336'>2 weeks</option>
					<option value='744'>1 month</option>
					<option value='1488'>2 months</option>
				</select>
			</td>
		</tr>
		<tr><td class='tdbg2' colspan='2'><input type='submit' name='ipban' value='IP Ban'></td></tr>
	</table>
	
	</form>
	<?php
	
	pagefooter();