<?php

require "lib/common.php";

admincheck();

if (isset($_POST['act'])){
	check_token($_POST['auth']);
	
	$_GET['id'] = filter_int($_GET['id']);
	if (!$_GET['id']) {
		errorpage("No user selected.", 'admin-pendingusers.php', 'the Pending users page');
	}
	$data	= $sql->fetchq("SELECT * FROM pendingusers WHERE id = {$_GET['id']}");
	if (!$data) {
		errorpage("This user doesn't exist. (ID #{$_GET['id']})", 'admin-pendingusers.php', 'the Pending users page');
	}
		
	if ($_POST['act'] == 'Accept') {
		/*
			User accepted:
			- "move" the row from pendingusers to users 
			- create the necessary fields/userpic folder for a new user
		*/
		$sql->beginTransaction();
		
		$newuser = $sql->prepare("INSERT INTO users (name, password, email, lastip, regdate, postsperpage, threadsperpage, scheme) VALUES (?,?,?,?,?,?,?,?)");
		$sql->execute($newuser, [$data['name'], $data['password'], $data['email'], $data['ip'], $data['date'], $config['default-ppp'], $config['default-tpp'], $miscdata['defaultscheme']]);
		$newuserid	= $sql->insert_id();
		$sql->query("DELETE FROM pendingusers WHERE id = {$_GET['id']}");
		$sql->query("INSERT INTO forumread (user, forum, readdate) SELECT {$newuserid}, id, ".time()." FROM forums");
		$sql->query("INSERT INTO users_rpg (uid) VALUES ({$newuserid})");
		$sql->commit();
		
		report_new_user("approved pending user", [
			'id'	=> $newuserid,
			'name'	=> $data['name'],
			'ip'	=> $data['ip']
		]);
		errorpage("User approved!", 'admin-pendingusers.php', 'the Pending Users page');
	} else if ($_POST['act'] == 'Reject') {
		/*
			Rejected
			Simply delete the pendinguser data
		*/
		$sql->query("DELETE FROM pendingusers WHERE id = {$_GET['id']}");
		report_send(
			IRC_STAFF, xk(8)."{$loguser['name']}".xk(7)." REJECTED pending user ".xk(8)."{$data['name']}".xk(7)." with IP ".xk(8)."{$data['ip']}".xk(7).".",
			IRC_STAFF, "**{$loguser['name']}** REJECTED pending user **{$data['name']}** with IP **{$data['ip']}**."
		);
		errorpage("User rejected!", 'admin-pendingusers.php', 'the Pending Users page');
	} else if ($_POST['act'] == 'IP Ban') {
		/*
			IP Banned:
			Add IP Ban and delete the pendingusers data
		*/
		$sql->query("DELETE FROM pendingusers WHERE id = {$_GET['id']}");
		$ircmsg = xk(8)."{$loguser['name']}".xk(7)." IP BANNED pending user ".xk(8)."{$data['name']}".xk(7)." with IP ".xk(8)."{$data['ip']}".xk(7).".";
		ipban($data['ip'], "Rejected", $ircmsg, IRC_STAFF, 0, $loguser['id']);
		errorpage("User blocked!", 'admin-pendingusers.php', 'the Pending Users page');
	} else {
		errorpage("Invalid action.", 'admin-pendingusers.php', 'the Pending Users page');
	}

}

	$users 	= $sql->query("SELECT * FROM pendingusers ORDER BY date DESC");
	
	if ($sql->num_rows($users)) {
		$txt    = "";
		$token  = auth_tag();
		while ($u = $sql->fetch($users)) {
			$txt .= "
			<tr>
				<td class='tdbg1 center'>{$u['id']}</td>
				<td class='tdbg2 center'>".htmlspecialchars($u['name'])."</td>
				<td class='tdbg2 center'>".printdate($u['date'])."</td>
				<td class='tdbg1 center'>".htmlspecialchars($u['ip'])."</td>
				<td class='tdbg2 center'>
					<form method='POST' action='?id={$u['id']}' style='display: inline'>
						{$token}
						<input type='submit' name='act' value='Accept'>
						<input type='submit' name='act' value='Reject'>
						<input type='submit' name='act' value='IP Ban'>
					</form>
				</td>
			</tr>";
		}
	} else {
		$txt = "<tr><td class='tdbg1 center' colspan='5'>There are no pending users to be judged.</td></tr>";
	}

	pageheader("Pending users");
	print adminlinkbar();

?>
<table class='table'>
	<tr><td class='tdbgh center b' colspan='5'>Pending users</td></tr>
	<tr>
		<td class='tdbgc center b' style='width: 50px'>#</td>
		<td class='tdbgc center b'>Name</td>
		<td class='tdbgc center b' style='width: 250px'>Date</td>
		<td class='tdbgc center b' style='width: 200px'>IP</td>
		<td class='tdbgc center b' style='width: 230px'>Action</td>
	</tr>
	<?= $txt ?>
</table>
<?php

pagefooter();

