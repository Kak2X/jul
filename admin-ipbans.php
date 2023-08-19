<?php

	require "lib/common.php";
	
	admincheck();
	
	$_GET['action'] = filter_string($_GET['action']);
	$_GET['ip']     = filter_string($_GET['ip']);
	$_GET['page']   = filter_int($_GET['page']);
	
	if (isset($_POST['ipban']) || isset($_POST['ipban2'])){
		check_token($_POST['auth']);

		// Here we go
		$_POST['ip'] = filter_string($_POST['ip']);
		if (!$_POST['ip']) {
			errorpage("You forgot to enter an IP!");
		} else if (strpos($_POST['ip'], "*") !== false) {
			errorpage("Do not use wildcards to specify IP ranges."); // this isn't the system from AB2
		} else if (stripos($_SERVER['REMOTE_ADDR'], $_POST['ip']) === 0) {
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
		
		$sql->beginTransaction();
		
		// instead of checking for the add / edit action (which come from the same place anyway)
		// we check if the ip passed over exists, which is only set on the edit action
		if (!$_GET['ip'] || !ipban_exists($_GET['ip'])) {
			if (ipban_exists($_POST['ip']) && !confirmed($msgkey = 'delprev')) {
				$form_link = "?ip={$_POST['ip']}";
				$title     = "Info";
				$message = "This IP mask was already banned previously. If you continue, the ban info will be updated.<br/>Do you want to continue?";
				$buttons = array(
					[BTN_SUBMIT, "Yes"],
					[BTN_URL   , "No", "?action=edit&ip={$_POST['ip']}"]
				);
				confirm_message($msgkey, $message, $title, $form_link, $buttons);
			}
			$ircmessage = xk(8) . $loguser['name'] . xk(7) ." added IP ban for ". xk(8) . $_POST['ip'] . xk(7) . $ircreason . ".";
			ipban($_POST['ip'], $_POST['reason'], $ircmessage, IRC_STAFF, $_POST['expire'], $loguser['id']);
		} else {
			// doesn't really matter if $_GET['ip'] is invalid. in that case nothing will be updated
			$ircmessage = xk(8) . $loguser['name'] . xk(7) ." modified IP ban for ". xk(8) . $_POST['ip'] . xk(7) . $ircreason . ".";
			ipban_edit($_GET['ip'], $_POST['ip'], $_POST['reason'], $ircmessage, IRC_STAFF, $_POST['expire'], $loguser['id']);
		}

		$sql->commit();
		#setmessage("Added IP ban for {$_POST['ip']}.");
		if (isset($_POST['ipban'])) {
			die(header("Location: ?action=edit&ip={$_POST['ip']}"));
		} else {
			die(header("Location: ?"));	
		}
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
	
	
	// Allow linking from other pages:
	if (isset($_GET['searchip'])){
		// ...to searches here
		$_POST['searchip'] = $_GET['searchip'];
	} else {
		$_POST['searchip'] = filter_string($_POST['searchip']);
	}
	
	if (isset($_POST['setreason']) && $_POST['setreason']) {
		$reason = filter_string($_POST['setreason']);
	} else {
		$reason = filter_string($_POST['searchreason']);
	}
	$_POST['showexpired'] = filter_int($_POST['showexpired']);
	$_POST['hideautomatic'] = filter_int($_POST['hideautomatic']);
	
	$ppp	= get_ppp();
	
	// Query values
	$outval = $outres = array();
	if ($reason) {
		$outval['reason'] = $reason;
		$outres[] = "i.reason = :reason";
	}
	if ($_POST['searchip']) {
		$outval['searchip'] = str_replace('*', '%', $_POST['searchip']);
		$outres[] = "i.ip LIKE :searchip";
	}
	if (!$_POST['showexpired']) {
		$outres[] = "(i.expire = 0 OR i.expire > ".time().")";
	}
	if ($_POST['hideautomatic']) {
		$outres[] = "i.banner != 0";
	}
	
	$total = $sql->resultq("SELECT COUNT(*) FROM ipbans");
	$bans  = $sql->queryp("
		SELECT i.ip, i.date, i.reason, i.perm, i.banner, i.expire, $userfields
		FROM ipbans i
		LEFT JOIN users u ON i.banner = u.id
		".($outres ? "WHERE ".implode(" AND ", $outres) : "")."
		ORDER BY i.date DESC
		LIMIT ".($_GET['page'] * $ppp).",$ppp
	", $outval);
	
	$pagectrl	= "<span class='fonts'>".pagelist("?reason=$reason", $total, $ppp)."</span>";
	
	pageheader("IP Bans");
	print adminlinkbar();
	
	$token = auth_tag();
	
	$txt = "";
	while ($x = $sql->fetch($bans)) {
		$ip = htmlspecialchars($x['ip']);
		
		$txt .= "
			<tr>
				<td class='tdbg2 center'><input type='checkbox' name='delban[]' value=\"{$ip}\"></td>
				<td class='tdbg2 center fonts'><a href=\"?action=edit&ip={$ip}\">Edit</a></td>
				<td class='tdbg1 center'>{$ip}</td>
				<td class='tdbg2 center'>".printdate($x['date'])."</td>
				<td class='tdbg2 center'>".print_ban_time($x)."</td>
				<td class='tdbg1'>".htmlspecialchars($x['reason'])."</td>
				<td class='tdbg2 center'>".($x['banner'] ? getuserlink($x) : "<i>Automatic</i>")."</td>
			</tr>
		";
	}
	
	?>
	
	<div class="fonts right">Actions: <a href="?action=add">Add an IP ban</a></div>
<?php
	if ($_GET['action']) {
	
		if ($_GET['action'] == 'add' || !($ipinfo = $sql->fetchp("SELECT ip, reason, expire FROM ipbans WHERE ip = ?", [$_GET['ip']]))) {
			$title = "Add IP ban";
			$ipinfo = array(
				'ip'  => $_GET['ip'],
				'reason' => "",
				'expire' => 0,
			);
		} else {
			$title = "Editing IP ban for '".htmlspecialchars($_GET['ip'])."'";
		}
		
?>
		<form method='POST' action="?ip=<?= htmlspecialchars($_GET['ip']) ?>">
		<?= $token ?>
		<table class='table'>
			<tr><td class='tdbgh center b' colspan='2'><?= $title ?></td></tr>
			
			<tr>
				<td class='tdbg1 center b' style='width: 250px'>IP Address</td>
				<td class='tdbg2'>
					<input type='text' name='ip' value="<?=htmlspecialchars($ipinfo['ip'])?>">
					<span class='fonts'>To specify an IP range use incomplete masks, not wildcards. ie: use '192.168.' instead of '192.168.*.*'</span>
				</td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Ban reason</td>
				<td class='tdbg2'><input type='text' name='reason' style='width: 100%; max-width: 500px' value="<?= htmlspecialchars($ipinfo['reason']) ?>"></td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>
					Message to send on IRC
					<div class='fonts'>If not specified, the <i>Ban reason</i> will be used.</div>
				</td>
				<td class='tdbg2'><input type='text' name='ircreason' style='width: 100%; max-width: 500px'></td>
			</tr>
			<tr>
				<td class='tdbg1 center b'>Duration</td>
				<td class='tdbg2'>
					<?= ban_select('expire', $ipinfo['expire']) ?>
				</td>
			</tr>
			<tr><td class='tdbg2' colspan='2'>
				<input type="submit" name="ipban" value="Save and continue">&nbsp;<input type="submit" name="ipban2" value="Save and close">
			</td></tr>
		</table>
		</form>
<?php
	}
?>
	<form method='POST' action='?'>
	<table class='table'>
		<tr>
			<td class='tdbgh' style='width: 120px'>&nbsp;</td>
			<td class='tdbgh'>&nbsp;</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Search IP:</td>
			<td class='tdbg2'>
				<input type='text' name='searchip' value="<?= htmlspecialchars(filter_string($_POST['searchip'])) ?>">
				<span class='fonts'>use * as wildcard</span>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Reason:</td>
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
		<tr>
			<td class='tdbg1 center b'>Options:</td>
			<td class='tdbg2'>
				<label><input type="checkbox" name="showexpired" value="1"<?= $_POST['showexpired'] ? " checked" : ""?>> Show expired</label>
				<label><input type="checkbox" name="hideautomatic" value="1"<?= $_POST['hideautomatic'] ? " checked" : ""?>> Hide autobans</label>
			</td>
		</tr>
		<tr><td class='tdbg2' colspan='2'><input type='submit' class='submit' name='dosearch' value='Search'></td></tr>
	</table>
	<br>
	<?= $pagectrl ?>
	<?= $token ?>
	<table class='table'>
		<tr>
			<td class='tdbgh center'>#</td>
			<td class='tdbgh center'></td>
			<td class='tdbgh center'>IP Address</td>
			<td class='tdbgh center' style='width: 200px'>Ban date</td>
			<td class='tdbgh center' style='width: 350px'>Expiration date</td>
			<td class='tdbgh center'>Reason</td>
			<td class='tdbgh center'>Banned by</td>
		</tr>
		<?= $txt ?>
		<tr><td class='tdbg2' colspan='7'><input type='submit' class='submit' name='dodel' value='Delete selected'></td></tr>
	</table>
	</form>
	<?= $pagectrl ?>
	<br><br>
	
<?php

	pagefooter();