<?php

	require "lib/function.php";
	
	admincheck();
	
	// Allow linking from other pages:
	// ...to searches here
	if (isset($_GET['ip'])){
		$_POST['searchip'] = $_GET['ip'];
	}
	// ...from IP Ban links
	$_GET['newip'] = filter_string($_GET['newip']);
	$_GET['page']  = filter_int($_GET['page']);
	
	if (isset($_POST['ipban'])){
		check_token($_POST['auth']);

		// Here we go
		$_POST['newip'] = filter_string($_POST['newip']);
		if (!$_POST['newip']) {
			errorpage("You forgot to enter an IP!");
		} else if ($_POST['newip'] == $_SERVER['REMOTE_ADDR']) {
			errorpage("Bad idea.");
		}
		
		$_POST['reason']    = filter_string($_POST['reason']);
		$_POST['ircreason'] = filter_string($_POST['ircreason']);
		$_POST['expire']    = filter_int($_POST['expire']);
		
		if (trim($_POST['ircreason'])) {
			$ircreason = " for this reason: " . xk(8) . $_POST['ircreason'] . xk(7);
		} else if (trim($_POST['reason'])) {
			$ircreason = " for this reason: " . xk(8) . $_POST['reason'] . xk(7);
		} else {
			$ircreason = "";
		}
		
		$ircmessage = "1|". xk(8) . $loguser['name'] . xk(7) ." added IP ban for ". xk(8) . $_POST['newip'] . xk(7) . $ircreason . ".";
		ipban($_POST['newip'], $_POST['reason'], $ircmessage, IRC_STAFF, $_POST['expire'], $loguser['id']);
		#setmessage("Added IP ban for {$_POST['newip']}.");
		return header("Location: ?");	
	}
	else if (isset($_POST['dodel']) && isset($_POST['delban'])){
		check_token($_POST['auth']);
		
		// Iterate over the sent IPs and add them to the query
		if (!empty($_POST['delban'])){
			$del = $sql->prepare("DELETE FROM ipbans WHERE ip = ?");
			$i = 0;
			foreach ($_POST['delban'] as $ban) {
				$sql->execute($del, [$ban]);
				++$i;
			}
			#setmessage("Removed IP ban for $i IP(s).");
		} else {
			#setmessage("No IP bans selected.");
		}
		return header("Location: ?");	
	}
	
	

	if (isset($_POST['setreason']) && $_POST['setreason']) {
		$reason = filter_string($_POST['setreason']);
	} else {
		$reason = filter_string($_POST['searchreason']);
	}
	
	$ppp	= isset($_GET['ppp']) ? ((int) $_GET['ppp']) : 100;
	$ppp	= max(min($ppp, 500), 1);
	
	// Query values
	$outres = array();
	$reasonsearch = $searchip = "1";
	if ($reason) {
		$outres['reason'] = $reason;
		$reasonsearch = "i.reason = :reason";
	}
	if (isset($_POST['searchip'])) {
		$outres['searchip'] = str_replace('*', '%', $_POST['searchip']);
		$searchip = "i.ip LIKE :searchip";
	}
	
	$total = $sql->resultq("SELECT COUNT(*) FROM ipbans");
	$bans  = $sql->queryp("
		SELECT i.ip, i.date, i.reason, i.perm, i.banner, i.expire, $userfields
		FROM ipbans i
		LEFT JOIN users u ON i.banner = u.id
		WHERE {$reasonsearch} AND {$searchip}
		ORDER BY i.date DESC
		LIMIT ".($_GET['page'] * $ppp).",$ppp
	", $outres);
	
	$pagectrl	= "<span class='fonts'>".pagelist("?reason=$reason", $total, $ppp)."</span>";
	
	$txt = "";
	while ($x = $sql->fetch($bans)) {
		$txt .= "
			<tr>
				<td class='tdbg2 center'><input type='checkbox' name='delban[]' value=\"{$x['ip']}\"></td>
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
				<span class='fonts'>use * as wildcard</span>
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
		<tr><td class='tdbg2' colspan='2'><input type='submit' class='submit' name='dosearch' value='Search'></td></tr>
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
		<tr><td class='tdbg2' colspan='6'><input type='submit' class='submit' name='dodel' value='Delete selected'></td></tr>
	</table>
	<?= $pagectrl ?>
	
	<br><br>
	
	<table class='table' id='addban'>
		<tr><td class='tdbgh center b' colspan='2'>Add IP ban</td></tr>
		
		<tr>
			<td class='tdbg1 center b' style='width: 120px'>IP Address</td>
			<td class='tdbg2'><input type='text' name='newip' value="<?=htmlspecialchars($_GET['newip'])?>"></td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Ban reason</td>
			<td class='tdbg2'><input type='text' name='reason' style='width: 500px'></td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>
				Message to send on IRC
				<div class='fonts'>If not specified, the <i>Ban reason</i> will be used.</div>
			</td>
			<td class='tdbg2'><input type='text' name='ircreason' style='width: 500px'></td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Duration</td>
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
		<tr><td class='tdbg2' colspan='2'><input type='submit' class='submit' name='ipban' value='IP Ban'></td></tr>
	</table>
	
	</form>
	<?php
	
	pagefooter();