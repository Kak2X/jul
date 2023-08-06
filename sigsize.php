<?php

	require 'lib/function.php';

	$_GET['bio'] = filter_bool($_GET['bio']);

	pageheader(($_GET['bio'] ? "Bio" : "Layout") ." size comparison");
	
print "
<div class='font'>
	Show: <a href='?'>layout sizes</a> - <a href='?bio=1'>bio sizes</a>
</div>
<table class='table'>
	<tr>
		<td class='tdbgh center'>&nbsp;</td>
		<td class='tdbgh center' colspan=2>User</td>
".  ($_GET['bio'] ? "<td class='tdbgh center'>Bio</td>" : 
		"<td class='tdbgh center'>Header</td>
		<td class='tdbgh center'>Signature</td>
		<td class='tdbgh center'>Total</td>");
	
	$users = $sql->query("
		SELECT $userfields, ".
		($_GET['bio'] ? 
			"LENGTH(bio) AS tsize, bio as postheader" : 
			"LENGTH(postheader) AS hsize,LENGTH(signature) AS ssize,LENGTH(postheader)+LENGTH(signature) AS tsize, postheader")."
		FROM users u ORDER BY tsize DESC");

	$last = $max = NULL;
	for ($i = 1; $u = $sql->fetch($users); ++$i) {
		if (!$u['tsize']) break;
		if (!$last || $last['tsize'] != $u['tsize']) $r = $i;
		$last	= $u;
		$max	= max($u['tsize'], $max);

		// apparently the layout maker was a Very Bad Thing™
		if (strpos($u['postheader'], "<style>.loclass") !== false) $lm	= true;
		else $lm	= false;

	print "
	<tr>
		<td class='tdbg2 center'>". ($lm ? "<img src='images/smilies/denied.gif' title='Say no to the layout maker!' align=absmiddle> $r <img src='images/smilies/denied.gif' title='Say no to the layout maker!' align=absmiddle>" : "$r") ."</td>
		<td class='tdbg2 center' style='width: {$config['max-minipic-size-x']}px'>". get_minipic($u['id'], $u['minipic']) ."</td>
		<td class='tdbg1 center'>".getuserlink($u)."</td>
		". (!$_GET['bio'] ? "<td class='tdbg2 center' width=100>". number_format($u['hsize']) ."</td>
							 <td class='tdbg2 center' width=100>". number_format($u['ssize']) ."</td>" : "") ."
		<td class='tdbg1 center' width=100><b>". number_format($u['tsize']) ."</b><br><img src=images/minibar.png width=\"". number_format($u['tsize'] / $max * 200) ."\" align=left height=3></td>
	</tr>";
	}
	print "</table>";
	
	pagefooter();
?>