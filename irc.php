<?php

	require 'lib/function.php';
	
	pageheader("{$config['board-name']} - IRC Chat");
	
	$_GET['server'] = filter_int($_GET['server']);

	$servers[1]		= "irc.badnik.zone";
	$servers[2]		= "irc.rustedlogic.net";
	$servers[3]		= "irc.tcrf.net";
	if ($_GET['server'] > count($servers) || $_GET['server'] <= -1) $_GET['server'] = 0;


?>	<table class='table'>
		<tr><td class='tdbgh center'><b>IRC Chat - BadnikZONE, #tcrf, #x</b></td></tr>
		<tr>
			<td class='tdbg1 center'>
				Server List:
<?php
	foreach ($servers as $num => $name) {

		if ($num != 1) 	print " | ";
		if ($_GET['server'] == $num) print "<u>";
		print "<a href='irc.php?server=$num'>$name</a>";
		if ($_GET['server'] == $num) print "</u>";
		if ($num == 1) print " (preferred)";

	}
?>			</td>
		</tr>
		<tr>
			<td class='tdbg2 center'>
<?php

	if ($_GET['server']) {

		$badchars = array("~", "&", "@", "?", "!", ".", ",", "=", "+", "%", "*");

		$name = str_replace(" ", "", $loguser['name']);
		$name = str_replace($badchars, "_", $name);
		if (!$name) { 
			$name 		= "J-Guest";
			$guestmsg	= "<br>Welcome, guest. When you connect to the IRC network, please use the command <tt>/nick NICKNAME</tt>.<br>&nbsp;<br>";
		}
		
	?>
	<iframe src="https://kiwiirc.com/client/<?=$servers[$server]?>/?nick=<?=$name?>|?#tcrf,#x" style="border:0;width:100%;height:500px;"></iframe>
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
		<tr><td class='tdbgh center'><b>Quick Help</b></td></tr>
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

	pagefooter();
?>