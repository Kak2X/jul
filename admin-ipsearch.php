<?php

	require 'lib/function.php';
	
	admincheck();
	
	pageheader("IP Address Search");
	print adminlinkbar();
	
	
	if (isset($_GET['ip'])) {
		$_POST['ip'] = $_GET['ip'];
	} else {
		$_POST['ip'] = filter_string($_POST['ip']);
	}
	
	// Why the fuck did this truncate results instead of using pagination?
	if (!isset($_POST['ppp'])) {
		$_POST['ppp'] = 500;
	} else {
		$_POST['ppp'] = numrange(filter_int($_POST['ppp']), 1, 500);
	}
	$_POST['page'] = filter_int($_POST['page']);

	if(!filter_string($_POST['su'])) $_POST['su']='n';
	if(!filter_string($_POST['sp'])) $_POST['sp']='u';
	if(!filter_string($_POST['sm'])) $_POST['sm']='n';
	if(!filter_string($_POST['d']))  $_POST['d'] ='y';
	$ch1[$_POST['su']]   = ' checked';
	$ch2[$_POST['sp']]   = ' checked';
	$ch3[$_POST['sm']]   = ' checked';
	$ch4[$_POST['d']]    = ' checked';
	$ch5[$_POST['ppp']]  = ' selected';
	$ch6[$_POST['page']] = ' selected';
	
	

?>
<form method="POST" action="?">
<table class='table'>
	<tr><td class='tdbgh center' colspan=2>IP search</td></tr>
	<tr>
		<td class='tdbg1 center b' style="width: 20%">IP to search:</td>
		<td class='tdbg2'>
			<input type="text" name="ip" size="32" maxlength="32" value="<?=htmlspecialchars($_POST['ip'])?>">
			<span class='fonts'>use * as wildcard</span>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Sort users by:</td>
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
		<td class='tdbg1 center b'>Sort posts by:</td>
		<td class='tdbg2'>
			<input type=radio class='radio' name=sp value=u<?=filter_string($ch2['u'])?>> User &nbsp; &nbsp;
			<input type=radio class='radio' name=sp value=d<?=filter_string($ch2['d'])?>> Date &nbsp; &nbsp;
			<input type=radio class='radio' name=sp value=i<?=filter_string($ch2['i'])?>> IP
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Sort private messages by:</td>
		<td class='tdbg2'>
			<input type=radio class='radio' name=sm value=n<?=filter_string($ch3['n'])?>> Sent by &nbsp; &nbsp;
			<input type=radio class='radio' name=sm value=d<?=filter_string($ch3['d'])?>> Date &nbsp; &nbsp;
			<input type=radio class='radio' name=sm value=i<?=filter_string($ch3['i'])?>> IP
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Distinct users and IP's:</td>
		<td class='tdbg2'>
			<input type=radio class='radio' name=d value=y<?=filter_string($ch4['y'])?>> Yes &nbsp; &nbsp;
			<input type=radio class='radio' name=d value=n<?=filter_string($ch4['n'])?>> No
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Display:</td>
		<td class='tdbg2'>
			<select name='ppp'>
				<option value='1'<?=filter_string($ch5['1'])?>>1</option>
				<option value='50'<?=filter_string($ch5['50'])?>>50</option>
				<option value='100'<?=filter_string($ch5['100'])?>>100</option>
				<option value='250'<?=filter_string($ch5['250'])?>>250</option>
				<option value='500'<?=filter_string($ch5['500'])?>>500</option>
				<option value='1000'<?=filter_string($ch5['1000'])?>>1000</option>	
			</select> entries
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center'>&nbsp;</td>
		<td class='tdbg1'><input type='submit' class="submit" value="Submit"></td>
	</tr>
</table>
<?php

	if ($_POST['ip']) {
		$_POST['ip'] = str_replace('*', '%', $_POST['ip']);
		
		switch ($_POST['su']) {
			case 'n': $usort='ORDER BY u.name'; break;
			case 'p': $usort='ORDER BY u.posts DESC'; break;
			case 'r': $usort='ORDER BY u.regdate'; break;
			case 's': $usort='ORDER BY u.lastposttime'; break;
			case 'a': $usort='ORDER BY u.lastactivity'; break;
			case 'i': $usort='ORDER BY u.lastip'; break;
		}
		switch ($_POST['sp']) {
			case 'u': $psort='ORDER BY u.name'; break;
			case 'd': $psort='ORDER BY p.date'; break;
			case 'i': $psort='ORDER BY p.ip'; break;
		}
		switch ($_POST['sm']) {
			case 'n': $msort='ORDER BY u1.name'; break;
			case 'd': $msort='ORDER BY p.date'; break;
			case 'i': $msort='ORDER BY p.ip'; break;
		}
		if ($_POST['d'] === 'y') {
			$pgroup='GROUP BY p.ip,u.id';
			$mgroup='GROUP BY p.ip,u1.id';
		} else {
			$pgroup=$mgroup='';
		}
		$users = $sql->queryp("
			SELECT u.*, i.ip ipbanned
			FROM users u 
			LEFT JOIN ipbans i ON u.lastip = i.ip
			WHERE u.lastip LIKE ? $usort
			LIMIT ".($_POST['ppp'] * $_POST['page']).", {$_POST['ppp']}
		", [$_POST['ip']]);
		
		$posts = $sql->queryp("
			SELECT p.*, $userfields uid, t.title
			FROM posts p
			LEFT JOIN users   u ON p.user   = u.id
			LEFT JOIN threads t ON p.thread = t.id 
			WHERE p.ip LIKE ?
			$pgroup $psort
			LIMIT ".($_POST['ppp'] * $_POST['page']).", {$_POST['ppp']}
		", [$_POST['ip']]);
		
		$pmsgs = $sql->queryp("
			SELECT 	p.id, p.userfrom, p.userto, p.title, p.ip, p.date,
					u1.name, u1.sex, u1.`powerlevel`, u1.aka, u1.birthday, u1.namecolor,
					u2.name, u2.sex, u2.`powerlevel`, u2.aka, u2.birthday, u2.namecolor
			FROM pmsgs p
			LEFT JOIN users u1 ON p.userfrom = u1.id
			LEFT JOIN users u2 ON p.userto   = u2.id
			WHERE p.ip LIKE ?
			$mgroup $msort
			LIMIT ".($_POST['ppp'] * $_POST['page']).", {$_POST['ppp']}
		", [$_POST['ip']]);

		// Somewhat wonky _POST pagination system, but better than nothing for now
		$pagectrl = "";
		if ($_POST['page'] > 0) {
			$pagectrl .= "<button type='submit' class='submit' name='page' value='".($_POST['page']-1)."'>&lt;-</button>";
		} else {
			$pagectrl .= "<button type='submit' class='submit' disabled>&lt;-</button>";
		}
		$pagectrl = "<br>
		<table class='table'><tr><td class='tdbg1 center'>
		{$pagectrl} &mdash; <button type='submit' class='submit' name='page' value='".($_POST['page']+1)."'>-&gt;</button>
		</td></tr></table>";
		
?>
<?= $pagectrl ?>
<br>
<table class='table'>
	<tr>
		<td class='tdbgh center' colspan=8><b>Users: <?=$sql->num_rows($users)?></b><tr>
		<td class='tdbgc center'>id</td>
		<td class='tdbgc center'>Name</td>
		<td class='tdbgc center'>Registered on</td>
		<td class='tdbgc center'>Last post</td>
		<td class='tdbgc center'>Last activity</td>
		<td class='tdbgc center'>Posts</td>
		<td class='tdbgc center' colspan=2>Last IP</td>
	</tr>
<?php for ($c = 0; $user = $sql->fetch($users); ++$c) { ?>
	<tr>
		<td class='tdbg2 center'><?=$user['id']?></td>
		<td class='tdbg1 center'><?=getuserlink($user)?></td>
		<td class='tdbg1 center'><?=printdate($user['regdate'])?></td>
		<td class='tdbg1 center'><?=printdate($user['lastposttime'])?></td>
		<td class='tdbg1 center'><?=printdate($user['lastactivity'])?></td>
		<td class='tdbg1 center'><?=$user['posts']?></td>
		<td class='tdbg2 center'><?=$user['lastip']?></td>
		<td class='tdbg2 center'><?=
			($user['ipbanned'] ? 
			"<a href='admin-ipbans.php?searchip={$user['lastip']}'>[IP BANNED]</a>" : 
			"<a href='admin-ipbans.php?newip={$user['lastip']}#addban'>IP Ban</a>")?>
		</td>
	</tr>
<?php } ?>
</table>
<br>


<table class='table'>
	<tr>
		<td class='tdbgh center' colspan=5><b>Posts: <?=$sql->num_rows($posts)?></b><tr>
		<td class='tdbgc center'>id</td>
		<td class='tdbgc center'>Posted by</td>
		<td class='tdbgc center'>Thread</td>
		<td class='tdbgc center'>Date</td>
		<td class='tdbgc center'>IP</td>
	</tr>
<?php for($c = 0; $post = $sql->fetch($posts); ++$c) { ?>
	<tr>
		<td class='tdbg2 center'><?=$post['id']?></td>
		<td class='tdbg1 center'><?=getuserlink($post, $post['user'])?></td>
		<td class='tdbg1 center'><a href="thread.php?id=<?=$post['thread']?>"><?=htmlspecialchars($post['title'])?></a></td>
		<td class='tdbg1 center nobr'><?=printdate($post['date'])?></td>
		<td class='tdbg2 center'><?=$post['ip']?></td>
	</tr>
<?php }	?>
</table>
<br>


<table class='table'>
	<tr>
		<td class='tdbgh center b' colspan=6>Private messages: <?=$sql->num_rows($pmsgs)?><tr>
		<td class='tdbgc center'>id</td>
		<td class='tdbgc center'>Sent by</td>
		<td class='tdbgc center'>Sent to</td>
		<td class='tdbgc center'>Title</td>
		<td class='tdbgc center'>Date</td>
		<td class='tdbgc center'>IP</td>
	</tr>
<?php
		for($c = 0; $pmsg = $sql->fetch($pmsgs, PDO::FETCH_NAMED); ++$c) {
			if ($loguser['id'] == 1 || ($pmsg['userfrom'] != 1 && $pmsg['userto'] != 1)) {
?>
	<tr>
		<td class='tdbg2 center'><?=$pmsg['id']?></td>
		<td class='tdbg1 center'><?=getuserlink(array_column_by_key($pmsg, 0), $pmsg['userfrom'])?></td>
		<td class='tdbg1 center'><?=getuserlink(array_column_by_key($pmsg, 1), $pmsg['userto'])?></td>
		<td class='tdbg1 center'><a href="showprivate.php?id=<?=$pmsg['id']?>"><?=htmlspecialchars($pmsg['title'])?></a></td>
		<td class='tdbg1 center nobr'><?=printdate($pmsg['date'])?></td>
		<td class='tdbg2 center'><?=$pmsg['ip']?></td>
	</tr>
<?php	
			}
		}

?>
</table>
</form>
<?php
	}

	pagefooter();