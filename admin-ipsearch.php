<?php
	require 'lib/function.php';
	pageheader("IP Address Search");
	admincheck();
	print adminlinkbar();

	$_POST['ip'] = filter_string($_POST['ip']);
	if(!filter_string($_POST['su'])) $_POST['su']='n';
	if(!filter_string($_POST['sp'])) $_POST['sp']='u';
	if(!filter_string($_POST['sm'])) $_POST['sm']='n';
	if(!filter_string($_POST['y']))  $_POST['d'] ='y';
	$ch1[$_POST['su']]= ' checked';
	$ch2[$_POST['sp']]= ' checked';
	$ch3[$_POST['sm']]= ' checked';
	$ch4[$_POST['d']] = ' checked';

?>
<form action='?' method=post>
<table class='table'>
	<tr><td class='tdbgh center' colspan=2>IP search</td></tr>
	<tr>
		<td class='tdbg1 center' style="width: 20%"><b>IP to search:</b></td>
		<td class='tdbg2'><input type='text' name=ip size=15 maxlength=15 value="<?=htmlspecialchars($_POST['ip'])?>"></td>
	</tr>
	<tr>
		<td class='tdbg1 center'><b>Sort users by:</b></td>
		<td class='tdbg2'>
			<input type=radio class='radio' name=su value=n<?=filter_string($ch1['n'])?>> Name &nbsp; &nbsp;
			<input type=radio class='radio' name=su value=p<?=filter_string($ch1['p'])?>> Posts &nbsp; &nbsp;
			<input type=radio class='radio' name=su value=r<?=filter_string($ch1['r'])?>> Registration &nbsp; &nbsp;
			<input type=radio class='radio' name=su value=s<?=filter_string($ch1['s'])?>> Last post &nbsp; &nbsp;
			<input type=radio class='radio' name=su value=a<?=filter_string($ch1['a'])?>> Last activity &nbsp; &nbsp;
			<input type=radio class='radio' name=su value=i<?=filter_string($ch1['i'])?>> Last IP
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center'><b>Sort posts by:</b></td>
		<td class='tdbg2'>
			<input type=radio class='radio' name=sp value=u<?=filter_string($ch2['u'])?>> User &nbsp; &nbsp;
			<input type=radio class='radio' name=sp value=d<?=filter_string($ch2['d'])?>> Date &nbsp; &nbsp;
			<input type=radio class='radio' name=sp value=i<?=filter_string($ch2['i'])?>> IP
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center'><b>Sort private messages by:</b></td>
		<td class='tdbg2'>
			<input type=radio class='radio' name=sm value=n<?=filter_string($ch3['n'])?>> Sent by &nbsp; &nbsp;
			<input type=radio class='radio' name=sm value=d<?=filter_string($ch3['d'])?>> Date &nbsp; &nbsp;
			<input type=radio class='radio' name=sm value=i<?=filter_string($ch3['i'])?>> IP
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center'><b>Distinct users and IP's:</b></td>
		<td class='tdbg2'>
			<input type=radio class='radio' name=d value=y<?=filter_string($ch4['y'])?>> Yes &nbsp; &nbsp;
			<input type=radio class='radio' name=d value=n<?=filter_string($ch4['n'])?>> No
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center'>&nbsp;</td>
		<td class='tdbg1'><input type='submit' class=submit name=s value=Submit></td>
	</tr>
</table>
</form>
<?php

	if ($_POST['ip']) {
		$_POST['ip'] = str_replace('*', '%', $_POST['ip']);
		
		switch ($_POST['su']) {
			case 'n': $usort='ORDER BY name'; break;
			case 'p': $usort='ORDER BY posts DESC'; break;
			case 'r': $usort='ORDER BY regdate'; break;
			case 's': $usort='ORDER BY lastposttime'; break;
			case 'a': $usort='ORDER BY lastactivity'; break;
			case 'i': $usort='ORDER BY lastip'; break;
		}
		switch ($_POST['sp']) {
			case 'u': $psort='ORDER BY u.name'; break;
			case 'd': $psort='ORDER BY p.date'; break;
			case 'i': $psort='ORDER BY p.ip'; break;
		}
		switch ($_POST['sm']) {
			case 'n': $msort='ORDER BY u.name'; break; // name1
			case 'd': $msort='ORDER BY p.date'; break;
			case 'i': $msort='ORDER BY p.ip'; break;
		}
		if ($_POST['d'] === 'y') {
			$pgroup='GROUP BY p.ip,u.id';
			$mgroup='GROUP BY p.ip,u.id'; // u1
		} else {
			$pgroup=$mgroup='';
		}
		$users = $sql->queryp("SELECT * FROM users WHERE lastip LIKE ? $usort", [$_POST['ip']]);
		$posts = $sql->queryp("
			SELECT p.*, $userfields uid, t.title
			FROM posts p
			LEFT JOIN users   u ON p.user   = u.id
			LEFT JOIN threads t ON p.thread = t.id 
			WHERE p.ip LIKE ?
			$pgroup $psort
		", [$_POST['ip']]);
		// u1.name, u AS name1,u2.name AS name2,u1.sex AS sex1,u2.sex AS sex2,u1.powerlevel pow1,u2.powerlevel pow2 
		// AND p.userto=u2.id
		// bah
		$pmsgs = $sql->queryp("
			SELECT p.id, p.userfrom, p.userto, p.title, p.ip, p.date, $userfields uid
			FROM pmsgs p
			LEFT JOIN users u ON p.userfrom = u.id
			WHERE p.ip LIKE ?
			$mgroup $msort
		", [$_POST['ip']]);


		
		
?>
<br>
<table class='table'>
	<tr>
		<td class='tdbgh center' colspan=7><b>Users: <?=$sql->num_rows($users)?></b><tr>
		<td class='tdbgc center'>id</td>
		<td class='tdbgc center'>Name</td>
		<td class='tdbgc center'>Registered on</td>
		<td class='tdbgc center'>Last post</td>
		<td class='tdbgc center'>Last activity</td>
		<td class='tdbgc center'>Posts</td>
		<td class='tdbgc center'>Last IP</td>
	</tr>
<?php
		for($c=0; $c<500 && $user=$sql->fetch($users); ++$c) {
			//  if ($users['id'] != 428)
?>
	<tr>
		<td class='tdbg2 center'><?=$user['id']?></td>
		<td class='tdbg1 center'><?=getuserlink($user)?></td>
		<td class='tdbg1 center'><?=printdate($user['regdate'])?></td>
		<td class='tdbg1 center'><?=printdate($user['lastposttime'])?></td>
		<td class='tdbg1 center'><?=printdate($user['lastactivity'])?></td>
		<td class='tdbg1 center'><?=$user['posts']?></td>
		<td class='tdbg2 center'><?=$user['lastip']?></td>
	</tr>
<?php
		}
		if($post = $sql->fetch($users))
			print "<tr><td class='tdbg2 center' colspan=7>Too many results!</td></tr>";
?>
</table>
<br>
<?php



?>
<table class='table'>
	<tr>
		<td class='tdbgh center' colspan=5><b>Posts: <?=$sql->num_rows($posts)?></b><tr>
		<td class='tdbgc center'>id</td>
		<td class='tdbgc center'>Posted by</td>
		<td class='tdbgc center'>Thread</td>
		<td class='tdbgc center'>Date</td>
		<td class='tdbgc center'>IP</td>
	</tr>
<?php
		for($c=0; $c<500 && $post=$sql->fetch($posts); ++$c) {
		//if ($post['user'] != 428)
?>
	<tr>
		<td class='tdbg2 center'><?=$post['id']?></td>
		<td class='tdbg1 center'><?=getuserlink($post, $post['user'])?></td>
		<td class='tdbg1 center'><a href="thread.php?id=<?=$post['thread']?>"><?=htmlspecialchars($post['title'])?></a></td>
		<td class='tdbg1 center nobr'><?=printdate($post['date'])?></td>
		<td class='tdbg2 center'><?=$post['ip']?></td>
	</tr>
<?php
		}
		if($post=$sql->fetch($posts))
			print "<tr><td class='tdbg2 center' colspan=5>Too many results!</td></tr>";

?>
</table>
<br>
<?php



?>
<table class='table'>
	<tr>
		<td class='tdbgh center' colspan=6><b>Private messages: <?=$sql->num_rows($pmsgs)?></b><tr>
		<td class='tdbgc center'>id</td>
		<td class='tdbgc center'>Sent by</td>
		<td class='tdbgc center'>Sent to</td>
		<td class='tdbgc center'>Title</td>
		<td class='tdbgc center'>Date</td>
		<td class='tdbgc center'>IP</td>
	</tr>
<?php
		for($c=0; $c<500 && $pmsg=$sql->fetch($pmsgs); ++$c) {
			//if ($pmsg['userfrom'] != 428 && $pmsg['userto'] != 428)
?>
	<tr>
		<td class='tdbg2 center'><?=$pmsg['id']?></td>
		<td class='tdbg1 center'><?=getuserlink($pmsg, $pmsg['userfrom'])?></td>
		<td class='tdbg1 center'><?=getuserlink(NULL, $pmsg['userto'])?></td>
		<td class='tdbg1 center'><a href="showprivate.php?id=<?=$pmsg['id']?>"><?=htmlspecialchars($pmsg['title'])?></a></td>
		<td class='tdbg1 center nobr'><?=printdate($pmsg['date'])?></td>
		<td class='tdbg2 center'><?=$pmsg['ip']?></td>
	</tr>
<?php	
		}
		if($pmsg=$sql->fetch($pmsgs))
			print "<tr><td class='tdbg2 center' colspan=6>Too many results!</td></tr>";

?>
</table>
<?php
	}

	pagefooter();