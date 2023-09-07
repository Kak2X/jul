<?php

require "lib/common.php";

// Discord - Main
$disc_chans     = [];
foreach (explode("\n", $config['discord-invites']) as $row) {
	if (!trim($row))
		continue;
	$chan = explode(";", $row, 2);
	if (count($chan) < 2)
		continue;
	$disc_chans[] = array_map('trim', $chan);
}

define("HAS_IRC", $config['irc-servers'] && $config['irc-channels']);
define("HAS_DISCORD", $disc_chans);

if (!HAS_IRC && !HAS_DISCORD) {
	errorpage("Chat hasn't been configured for this board.");
}


$show_server = isset($_GET['server']);

pageheader("Chat");

if (HAS_DISCORD && !$show_server) {
?>
	<table class="table">
		<tr><td class="tdbgh center b">Discord Chat - <?=$config['discord-guild-title'] ?></td></tr>
		<tr><td class="tdbg1 center">You can choose any of the following Discord invite links:</td></tr>
		<tr>
			<td class="tdbg2">
				<ul style="margin: 10px auto; display: table">
<?php foreach ($disc_chans as $x) { ?>
					<li><a href="<?=$x[1]?>"><?=$x[0]?></a></li>
<?php } ?>
				</ul>
			</td>
		</tr>
	</table>
	<br/>
<?php
}
	
if (HAS_IRC) {
	// Pick an irc server, if unset show server selection
	if (!$show_server) {
		$_GET['server'] = -1;
	} else {
		$_GET['server'] = filter_int($_GET['server']);
		if (!isset($config['irc-servers'][$_GET['server']])) errorpage("Server not found.");
	}
	


?>	<table class='table'>
		<tr><td class='tdbgh center b'>
			IRC Chat - <?=$config['irc-server-title']?>, <?=implode(",", $config['irc-channels'])?>
		</td></tr>
		<tr>
			<td class='tdbg1 center'>
				Server List:
<?php
	foreach ($config['irc-servers'] as $num => $name) {

		if ($num != 0) 	print " | ";
		if ($_GET['server'] == $num) print "<u>";
		print "<a href='?server={$num}'>{$name}</a>";
		if ($_GET['server'] == $num) print "</u>";
		if ($num == 0) print " (preferred)";

	}
?>			</td>
		</tr>
		<tr>
			<td class='tdbg2 center' <?= $show_server ? "style=\"background: #FFF\"" : "" ?>>
<?php

	if ($show_server) {

		$badchars = array("~", "&", "@", "?", "!", ".", ",", "=", "+", "%", "*");

		$name = str_replace(" ", "", $loguser['name']);
		$name = str_replace($badchars, "_", $name);
		if (!$name) { 
			$name 		= "J-Guest";
			$guestmsg	= "<br>Welcome, guest. When you connect to the IRC network, please use the command <tt>/nick NICKNAME</tt>.<br>&nbsp;<br>";
		}
		
	?>
	<iframe src="https://kiwiirc.com/client/<?=$config['irc-servers'][$_GET['server']]?>/?nick=<?=$name?>|?<?=implode(",", $config['irc-channels'])?>" style="border:0;width:100%;height:500px;"></iframe>
	<?php

	} else {
?>				&nbsp;<br>
				Please choose a server to connect to.<br>
				&nbsp;
<?php
	}

?>			</td>
		</tr>
	</table>
	<br>
	<table class='table'>
		<tr><td class='tdbgh center b'>Quick Help</td></tr>
		<tr>
			<td class='tdbg1'>
				Commands:
				<br><tt>/nick [name]</tt> - changes your name
				<br><tt>/me [action]</tt> - does an action (try it)
				<br><tt>/msg [name] [message]</tt> - send a private message to another user
				<br><tt>/join [#channel]</tt> - joins a channel
				<br><tt>/part [#channel]</tt> - leaves a channel
				<br><tt>/quit [message]</tt> - obvious
			</td>
		</tr>
	</table>
<?php
}

pagefooter();
	