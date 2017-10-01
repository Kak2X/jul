<?php

require "lib/function.php";

admin_check();

if (isset($_POST['act'])){
	check_token($_POST['auth']);
	
	$_GET['id'] = filter_int($_GET['id']);
	
	if ($_GET['id']){
		
		// Sanity check - No invalid users
		$data	= $sql->fetchq("SELECT * FROM pendingusers WHERE id = {$_GET['id']}");
		if (!$data){
			errorpage("This user doesn't exist. (ID #{$_GET['id']})");
			return header("Location: ?");
		}
		else if ($_POST['act'] == 'Accept'){
			/*
				User accepted:
				- "move" the row from pendingusers to users 
				- create the necessary fields/userpic folder for a new user
			*/
			$querycheck = array();
			$sql->beginTransaction();
			
			$newuser 		= $sql->prepare("INSERT INTO users (name, password, lastip, time) VALUES (?,?,?,?)");
			$querycheck[] 	= $sql->execute($newuser, [$data['name'], $data['password'], $data['ip'], $data['time']]);
			$newuserid	= $sql->lastInsertId();
			$sql->query("DELETE FROM pendingusers WHERE id = {$_GET['id']}", false, $querycheck);
			$sql->query("INSERT INTO forumread (user, forum, readdate) SELECT {$newuserid}, id, ".ctime()." FROM forums", false, $querycheck);
			
			$sql->query("INSERT INTO users_rpg (id) VALUES ($newuserid)", false, $querycheck);
				
			if ($sql->checkTransaction($querycheck)) {
				mkdir("userpic/$newuserid");
				xk_ircsend("1|". xk(8) . $loguser['name'] . xk(7) ." APPROVED pending user ". xk(8) . $data['name'] . xk(7) ." with IP ". xk(8) . $data['ip'] . xk() ." | {$config['board-url']}/?u=". $newuserid  . xk(7).".");
				
				$ircout = array (
					'id'	=> $newuserid,
					'name'	=> $data['name'],
					'ip'	=> $data['lastip']
				);
				xk_ircout("user", $ircout['name'], $ircout);	
				
				errorpage("User approved!", 'admin-pendingusers.php', 'the Pending Users page');
			}
			else{
				errorpage("Couldn't accept the user.", 'admin-pendingusers.php', 'the Pending Users page');
			}
			
			
		}
		else if ($_POST['act'] == 'Reject') {
			/*
				Rejected
				Simply delete the pendinguser data
			*/
			$sql->query("DELETE FROM pendingusers WHERE id = {$_GET['id']}");
			xk_ircsend("1|". xk(8) . $loguser['name'] . xk(7) ." REJECTED pending user ". xk(8) . $data['name'] . xk(7) ." with IP ". xk(8) . $data['ip'] . xk(7).".");
			errorpage("User rejected!", 'admin-pendingusers.php', 'the Pending Users page');
		}
		else if ($_POST['act'] == 'IP Ban') {
			/*
				User rejected:
				Add IP Ban and remove pendingusers data
			*/
			$sql->query("DELETE FROM pendingusers WHERE id = {$_GET['id']}");
			
			$ircmsg = "1|". xk(8) . $loguser['name'] . xk(7) ." IP BANNED pending user ". xk(8) . $data['name'] . xk(7) ." with IP ". xk(8) . $data['ip'] . xk(7).".";
			ipban("Rejected", $ircmsg, $data['ip']);
			errorpage("User blocked!", 'admin-pendingusers.php', 'the Pending Users page');
		}
		else {
			errorpage("User blocked!", 'admin-pendingusers.php', 'the Pending Users page');
		}
		
	}
	else{
		errorpage("No user selected.", 'admin-pendingusers.php', 'the Pending Users page');
	}
}

$users 	= $sql->query("SELECT * FROM pendingusers ORDER BY time DESC");
$token  = generate_token();

pageheader("Pending users");
print adminlinkbar();

?>
<table class='table'>

	<tr><td class='tdbgh center' colspan='5'>Pending users</td></tr>
	
	<tr>
		<td class='tdbgh center' style='width: 50px'>#</td>
		<td class='tdbgh center'>Name</td>
		<td class='tdbgh center' style='width: 250px'>Date</td>
		<td class='tdbgh center' style='width: 200px'>IP</td>
		<td class='tdbgh center' style='width: 230px'>Action</td>
	</tr>
<?php		
if ($sql->num_rows($users)) {
	while ($u = $sql->fetch($users)) {
?>	<tr>
		<td class='tdbg1 center'><?= $u['id'] ?></td>
		<td class='tdbg2 center'><?= $u['name'] ?></td>
		<td class='tdbg2 center'><?= printdate($u['time']) ?></td>
		<td class='tdbg1 center'><?= $u['ip'] ?></td>
		<td class='tdbg2 center'>
			<form method='POST' action='?id=<?= $u['id'] ?>'>
				<input type='hidden' name='auth' value='<?=$token?>'>
				<input type='submit' name='act' value='Accept'>
				<input type='submit' name='act' value='Reject'>
				<input type='submit' name='act' value='IP Ban'>
			</form>
		</td>
	</tr>
<?php
	}
} else {
?>	<tr><td class='tdbg1 center' colspan='5'>There are no pending users.</td></tr>
<?php
}
?>
</table>
<?php

pagefooter();

