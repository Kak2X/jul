<?php

require "lib/common.php";

admincheck();

$_GET['id']      = filter_int($_GET['id']); // Channel ID
$_GET['action']  = filter_string($_GET['action']); // URL action id, affects what $_GET['id'] refers to (ie: irc channel vs discord webhook)
$_POST['action'] = filter_string($_POST['action']); // Submit action id

// We can afford it, there aren't many channels and it's an admin page anyway
$irc_chans = $sql->fetchq("
	SELECT i.id, i.*, COUNT(f.id) fusage 
	FROM irc_channels i
	LEFT JOIN forums f ON f.ircchan = i.id
	GROUP BY i.id
", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
$disc_chans = $sql->fetchq("
	SELECT d.id, d.*, COUNT(f.id) fusage 
	FROM discord_webhooks d
	LEFT JOIN forums f ON f.discordwebhook = d.id
	GROUP BY d.id
", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);

if ($_POST['action'] == "restartbot") {
	check_token($_POST['auth']);
	irc_raw_send(BOTCMD_RESTART, "Received restart command...");	
	errorpage("Restart command sent!", '?', 'reporting settings page', 0);	
}
else if ($_GET['action'] == "ircdel" || $_GET['action'] == "discdel") {
	// Shared deletion code since the tables are similar enough
	if ($_GET['action'] == "ircdel") {
		$tblname   = "irc_channels";	// Table to delete stuff from
		$forumcol  = "ircchan"; 		// column in the "forums" table that points to $tblname
		$whatsthis = "IRC channel";		// visual label of what we're deleting
		$anchor    = "irc"; 			// link anchor when closing the dialog, to scroll to the appropriate form
		$chans     = $irc_chans;
	} else {
		$tblname   = "discord_webhooks";
		$forumcol  = "discordwebhook";
		$whatsthis = "Discord webhook";
		$anchor    = "discord";
		$chans     = $disc_chans;
	}
	
	if (!isset($chans[$_GET['id']]))
		errorpage("This channel doesn't exist.");
	$chan = $chans[$_GET['id']];
	if ($chan['nodelete']) {
		errorpage("This is a default channel, you can disable it but not delete it.");
	}
	
	if (confirmed($msgkey = 'dodel')) {
		// these validations in practice don't matter, since reporting gets skipped anyway if the channel ID is invalid.
		// they are here mostly for db consistency reasons.
		$_POST['mergeid'] = filter_int($_POST['mergeid']);
		if ($_GET['id'] == $_POST['mergeid'])
			errorpage("You can't select the same channel!");
		if ($_POST['mergeid'] != 0 && !isset($chans[$_POST['mergeid']]))
			errorpage("The selected channel doesn't exist!");

		$sql->beginTransaction();
		$sql->query("UPDATE forums SET {$forumcol} = {$_POST['mergeid']} WHERE {$forumcol} = {$_GET['id']}");
		$sql->query("DELETE FROM {$tblname} WHERE id = {$_GET['id']}");
		$sql->commit();
	
		errorpage("The {$whatsthis} reference has been deleted!", "?#{$anchor}", "reporting settings page");
	}
	
	$title   = "Delete {$whatsthis}";
	$message = "Are you sure you want to <b>delete</b> the reference to the {$whatsthis} '<b>".htmlspecialchars($chan['name'])."</b>'?";
	
	// Give chance to repoint instead of disabling everything
	if ($chan['fusage'] > 0) {
		$forum_usage = $sql->fetchq("SELECT id, title FROM forums WHERE {$forumcol} = {$_GET['id']}", PDO::FETCH_ASSOC, mysql::FETCH_ALL);
		$chanlist = $sql->fetchq("SELECT id, name FROM {$tblname} WHERE id != {$_GET['id']}", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
		$message .= "<br/>{$chan['fusage']} forum".($chan['fusage'] == 1 ? " is" : "s are")." using it for reporting:<ul>";
		foreach ($forum_usage as $x) {
			$message .= "<li><a href='forum.php?id={$x['id']}'>".htmlspecialchars($x['title'])."</a></li>";
		}
		$message .= "</ul>If you want, you can choose to repoint them to the channel below:<br/>".
					int_select("mergeid", $chanlist, 0, "*** None, disable reporting ***");
	}
	$form_link = "?action={$_GET['action']}&id={$_GET['id']}";
	$buttons   = array(
		[BTN_SUBMIT, "DELETE"],
		[BTN_URL   , "Cancel", "?#{$anchor}"]
	);
	confirm_message($msgkey, $message, $title, $form_link, $buttons);
		
}
else if ($_GET['action'] == "ircedit") {
	
	if ($_POST['action'] == "saveirc") {
		check_token($_POST['auth']);
		
		if ($_GET['id'] != -1 && !isset($irc_chans[$_GET['id']]))
			errorpage("This channel doesn't exist.");
		
		$_POST['name'] = filter_string($_POST['name']);
		if (!$_POST['name'] || !($_POST['name'] = trim($_POST['name'])))
			errorpage("The channel name is required.");
		if (strpos($_POST['name'], "#") !== 0)
			errorpage("The channel must start with #.");
		
		$set = [
			'name' => $_POST['name'],
			'description' => filter_string($_POST['description']),
			'chankey' => filter_string($_POST['chankey']),
			'minpower' => filter_int($_POST['minpower']),
			'enabled' => filter_int($_POST['enabled']),
		];
		
		if ($_GET['id'] != -1)
			$sql->queryp("UPDATE irc_channels SET ".mysql::setplaceholders($set)." WHERE id = {$_GET['id']}", $set);	
		else
			$sql->queryp("INSERT INTO irc_channels SET ".mysql::setplaceholders($set), $set);	
		
		die(header("Location: ?#irc"));	
	}
	
	pageheader("Post Reporting - IRC Channel");
	print adminlinkbar("admin-reporting.php");
	
	if ($_GET['id'] == -1) {
		$title = "Adding new IRC channel";
		$chan = [
			'name' => "",
			'description' => "",
			'enabled' => 1,
			'minpower' => PWL_MIN,
			'chankey' => ""
		];
	} else {
		if (!isset($irc_chans[$_GET['id']]))
			errorpage("This channel doesn't exist!");
		$chan = $irc_chans[$_GET['id']];
		$title = "Editing the IRC channel '<b>".htmlspecialchars($chan['name'])."</b>'";
	}
	//  style="width: 250px"
	?>	
	<form method="POST" action="?action=<?=$_GET['action']?>&id=<?=$_GET['id']?>">
		<table class="table">
			<tr><td class="tdbgh center" colspan="2"><?= $title ?></td></tr>
			<tr>
				<td class="tdbg1 center b">
					Channel Name
					<div class="fonts">Must begin with #</div>
				</td>
				<td class="tdbg2"><?= input_html("name", $chan['name'], ['input' => 'text', 'width' => '300px']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">
					Channel Key
					<div class="fonts">Specify if the To allow the IRC bot to access the protected channel.</div>
				</td>
				<td class="tdbg2"><?= input_html("chankey", $chan['chankey'], ['input' => 'text', 'width' => '300px']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">
					Channel Description
					<div class="fonts">Describe the channel contents, if you want to.</div>
				</td>
				<td class="tdbg2"><?= input_html("description", $chan['description'], ['input' => 'text', 'width' => '550px']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">
					Power needed to view
					<div class="fonts">Hides the channel from the <a href="irc.php" target="_blank">IRC Chat page</a> unless you have at least this power.</div>
				</td>
				<td class="tdbg2"><?= power_select("minpower", $chan['minpower']) ?></span></td>
			</tr>
			
			<tr>
				<td class="tdbg1 center b">
					Enable channel
					<div class="fonts">If disabled, no posts will be reported to this channel.</div>
				</td>
				<td class="tdbg2"><?= input_html("enabled", $chan['enabled'], ['input' => 'radio', 'special' => 'yesno']) ?></td>
			</tr>
			
			<tr>
				<td class="tdbg1"></td>
				<td class="tdbg2">
					<button type="submit" name="action" value="saveirc">Save changes</button> <a href="?" class="button">Cancel</a>
					<?= auth_tag() ?>
				</td>
			</tr>
		</table>
	</form>
	<?php
} 
else if ($_GET['action'] == "discedit") {
	
	if ($_POST['action'] == "savedisc") {
		check_token($_POST['auth']);
		
		if ($_GET['id'] != -1 && !isset($disc_chans[$_GET['id']]))
			errorpage("This channel doesn't exist.");
		
		$_POST['webhook'] = filter_string($_POST['webhook']);
		if (!$_POST['webhook'] || !($_POST['webhook'] = trim($_POST['webhook'])))
			errorpage("The Webhook ID is required.");
		
		$_POST['name'] = filter_string($_POST['name']);
		if (!$_POST['name'] || !($_POST['name'] = trim($_POST['name'])))
			errorpage("The channel name is required.");

		$set = [
			'name' => $_POST['name'],
			'description' => filter_string($_POST['description']),
			'webhook' => $_POST['webhook'],
			'minpower' => filter_int($_POST['minpower']),
			'enabled' => filter_int($_POST['enabled']),
		];
		
		if ($_GET['id'] != -1)
			$sql->queryp("UPDATE discord_webhooks SET ".mysql::setplaceholders($set)." WHERE id = {$_GET['id']}", $set);	
		else
			$sql->queryp("INSERT INTO discord_webhooks SET ".mysql::setplaceholders($set), $set);	
		
		die(header("Location: ?#discord"));	
	}
	
	if ($_GET['id'] == -1) {
		$title = "Adding new Discord webhook";
		$chan = [
			'webhook' => "",
			'name' => "",
			'description' => "",
			'enabled' => 1,
			'minpower' => PWL_MIN,
		];
	} else {
		if (!isset($disc_chans[$_GET['id']]))
			errorpage("This channel doesn't exist!");
		$chan = $disc_chans[$_GET['id']];
		$title = "Editing the Discord webhook for '<b>".htmlspecialchars($chan['name'])."</b>'";
	}
	
	pageheader("Post Reporting - Discord Webhook");
	print adminlinkbar("admin-reporting.php");
	
	?>	
	<form method="POST" action="?action=<?=$_GET['action']?>&id=<?=$_GET['id']?>">
		<table class="table">
			<tr><td class="tdbgh center" colspan="2"><?= $title ?></td></tr>
			<tr>
				<td class="tdbg1 center b">
					Webhook ID
					<div class="fonts">What matters to discord.</div>
				</td>
				<td class="tdbg2"><b>https://discord.com/api/webhooks/</b><?= input_html("webhook", $chan['webhook'], ['input' => 'text', 'width' => '300px']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">	
					Title
					<div class="fonts">Channel title, shown on the <a href="irc.php" target="_blank">Discord page</a></div>
				</td>
				<td class="tdbg2"><?= input_html("name", $chan['name'], ['input' => 'text', 'width' => '300px']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">
					Channel Description
					<div class="fonts">Describe the channel contents, if you want to.</div>
				</td>
				<td class="tdbg2"><?= input_html("description", $chan['description'], ['input' => 'text', 'width' => '550px']) ?></td>
			</tr>
			<tr>
				<td class="tdbg1 center b">
					Power needed to view
					<div class="fonts">Hides the channel from the <a href="irc.php" target="_blank">Discord page</a> unless you have at least this power.</div>
				</td>
				<td class="tdbg2"><?= power_select("minpower", $chan['minpower']) ?></span></td>
			</tr>
			
			<tr>
				<td class="tdbg1 center b">
					Enable channel
					<div class="fonts">If disabled, no posts will be reported to this channel.</div>
				</td>
				<td class="tdbg2"><?= input_html("enabled", $chan['enabled'], ['input' => 'radio', 'special' => 'yesno']) ?></td>
			</tr>
			
			<tr>
				<td class="tdbg1"></td>
				<td class="tdbg2">
					<button type="submit" name="action" value="savedisc">Save changes</button> <a href="?" class="button">Cancel</a>
					<?= auth_tag() ?>
				</td>
			</tr>
		</table>
	</form>
	<?php	
}
else {
	if ($_POST['action'] == "saveglob") {
		check_token($_POST['auth']);
		
		$sql->beginTransaction();
		
		// Update board-specific settings
		$set = [
			'irc_enable' => filter_int($_POST['irc_enable']),
			'discord_enable' => filter_int($_POST['discord_enable']),
		];
		$sql->queryp("UPDATE misc SET ".mysql::setplaceholders($set), $set);
		
		//--
		// Update irc bot-specific settings.
		$oldport = $sql->resultq("SELECT recvport FROM irc_settings");
		$set = [
			'server' => filter_string($_POST['server']),
			'port' => filter_int($_POST['port']),
			'nick' => filter_string($_POST['nick']),
			'pass' => filter_string($_POST['pass']),
			'opnick' => strn_replace(" ", "", filter_string($_POST['opnick'])),
			'recvport' => filter_int($_POST['recvport']),
		];
		$sql->queryp("UPDATE irc_settings SET ".mysql::setplaceholders($set), $set);
		$sql->commit();
		
		// If the port changed, we are forced to do this
		if ($changed = ($oldport != $set['recvport']))
			irc_raw_send(BOTCMD_RESTART, "Recv port changed, autorestarting...", $oldport);			
		
		errorpage("Settings saved".($changed ? " and IRC bot restarted" : "")."!", '?', 'reporting settings page', 0);		
	} 

	pageheader("Post Reporting");
	print adminlinkbar("admin-reporting.php");
	
	$settings = $sql->fetchq("SELECT * FROM irc_settings");
?>	
<form method="POST" action="?">
	<table class="table">
		<tr><td class="tdbgh center" colspan="2">Bot commands</td></tr>
		<tr>
			<td class="tdbg1 center"><button type="submit" name="action" value="restartbot">Restart bot</button></td>
			<td class="tdbg2">Use this if you have made any changes to IRC channel settings outside of the global "<b>Enable IRC Reporting</b>" option.<br/>Note that editing the "<b>Listener Port</b>" field will automatically perform this action.</td>
		</tr>
	</table>
	
	<br/>
	<table class="table" id="glob">
		<tr><td class="tdbgh center" colspan="2">Global reporting options</td></tr>
		<tr>
			<td class="tdbg1 center b">Enable IRC Reporting</td>
			<td class="tdbg2"><?= input_html("irc_enable", $miscdata['irc_enable'], ['input' => 'radio', 'special' => 'yesno']) ?></td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Enable Discord Reporting</td>
			<td class="tdbg2"><?= input_html("discord_enable", $miscdata['discord_enable'], ['input' => 'radio', 'special' => 'yesno']) ?></td>
		</tr>
		<tr><td class="tdbgh center" colspan="2">IRC Bot connection options</td></tr>
		<tr>
			<td class="tdbg1 center b">Server Hostname:</td>
			<td class="tdbg2"><?= input_html("server", $settings['server'], ['input' => 'text', 'width' => '300px']) ?></td>
		</tr>
		<tr>
			<td class="tdbg1 center b">Server Port:</td>
			<td class="tdbg2"><?= input_html("port", $settings['port'], ['input' => 'text', 'width' => '100px']) ?></td>
		</tr>
		<tr>
			<td class="tdbg1 center b">
				Nickname:
				<div class="fonts">The nicknames for the bot account, comma-separated.</div>
			</td>
			<td class="tdbg2"><?= input_html("nick", $settings['nick'], ['input' => 'text', 'width' => '500px']) ?></td>
		</tr>
		<tr>
			<td class="tdbg1 center b">
				Password:
				<div class="fonts">Optional - The NickServ account password.</div>
			</td>
			<td class="tdbg2"><?= input_html("pass", $settings['pass'], ['input' => 'password', 'width' => '500px']) ?></td>
		</tr>
		<tr>
			<td class="tdbg1 center b">
				Operator Nicknames:
				<div class="fonts">The nicknames for the operators, comma-separated, which should be registered on NickServ for extra security.</div>
			</td>
			<td class="tdbg2"><?= input_html("opnick", $settings['opnick'], ['input' => 'text', 'width' => '500px']) ?></td>
		</tr>
		<tr>
			<td class="tdbg1 center b">
				Listener Port:
				<div class="fonts">The IRC bot will listen to this local port for incoming board messages.</div>
			</td>
			<td class="tdbg2"><?= input_html("recvport", $settings['recvport'], ['input' => 'text', 'width' => '500px']) ?></td>
		</tr>
		<tr>
			<td class="tdbg1"></td>
			<td class="tdbg2">
				<button type="submit" name="action" value="saveglob">Save changes</button>
				<?= auth_tag() ?>
			</td>
		</tr>
	</table>
</form>
<?php	
}

?>
<br/>

<table class="table" id="irc">
	<tr><td class="tdbgh center" colspan="7">IRC Channels</td></tr>
	<tr><td class="tdbgc center" colspan="7"><a href="?action=ircedit&id=-1">Add new channel</a></td></tr>
	<tr>
		<td class="tdbgh center b" style="width: 95px"></td>
		<td class="tdbgh center b">Name</td>
		<td class="tdbgh center b">Key</td>
		<td class="tdbgh center b">Description</td>
		<td class="tdbgh center b" style="width: 150px">Used in # forums</td>
		<td class="tdbgh center b" style="width: 85px">Enabled?</td>
		<td class="tdbgh center b" style="width: 85px">Default?</td>
	</tr>
<?php foreach ($irc_chans as $chan) { ?>
		<tr>
			<td class="tdbg2 center fonts">
				<a href="?action=ircedit&id=<?=$chan['id']?>">Edit</a>
				<?= ($chan['nodelete'] ? "" : " - <a href=\"?action=ircdel&id={$chan['id']}\">Delete</a>") ?>
			</td>
			<td class="tdbg1 b"><?= htmlspecialchars($chan['name']) ?></td>
			<td class="tdbg1 b"><?= htmlspecialchars($chan['chankey']) ?></td>
			<td class="tdbg2 fonts"><?= htmlspecialchars($chan['description']) ?></td>
			<td class="tdbg1 right"><?= $chan['fusage'] ?></td>
			<td class="tdbg1 center b"><?= _mkyn($chan['enabled']) ?></td>
			<td class="tdbg1 center b"><?= _mkyn($chan['nodelete']) ?></td>
		</tr>
<?php } ?>
	<tr><td class="tdbgc center" colspan="7"><a href="?action=ircedit&id=-1">Add new channel</a></td></tr>
</table>

<br/>

<table class="table" id="discord">
	<tr><td class="tdbgh center" colspan="6">Discord Webhooks</td></tr>
	<tr><td class="tdbgc center" colspan="6"><a href="?action=discedit&id=-1">Add new webhook</a></td></tr>
	<tr>
		<td class="tdbgh center b" style="width: 95px"></td>
		<td class="tdbgh center b">Title</td>
		<td class="tdbgh center b">Description</td>
		<td class="tdbgh center b" style="width: 150px">Used in # forums</td>
		<td class="tdbgh center b" style="width: 85px">Enabled?</td>
		<td class="tdbgh center b" style="width: 85px">Default?</td>
	</tr>
<?php foreach ($disc_chans as $chan) { ?>
		<tr>
			<td class="tdbg2 center fonts">
				<a href="?action=discedit&id=<?=$chan['id']?>">Edit</a>
				<?= ($chan['nodelete'] ? "" : " - <a href=\"?action=discdel&id={$chan['id']}\">Delete</a>") ?>
			</td>
			<td class="tdbg1 b"><?= htmlspecialchars($chan['name']) ?></td>
			<td class="tdbg2 fonts"><?= htmlspecialchars($chan['description']) ?></td>
			<td class="tdbg1 right"><?= $chan['fusage'] ?></td>
			<td class="tdbg1 center b"><?= _mkyn($chan['enabled']) ?></td>
			<td class="tdbg1 center b"><?= _mkyn($chan['nodelete']) ?></td>
		</tr>
<?php } ?>
	<tr><td class="tdbgc center" colspan="6"><a href="?action=discedit&id=-1">Add new webhook</a></td></tr>
</table>

<?php

pagefooter();

function _mkyn($yes) {
	return "<span class='b' style='color:#".($yes ? "0f0'>YES": "f00'>NO")."</span>";
}