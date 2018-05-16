<?php
	require 'lib/function.php';
	$windowtitle = "$boardname -- Private Messages";
	require 'lib/layout.php';

	if (!$log)
		errorpage("You need to be logged in to read your private messages.", 'log in (then try again)', 'login.php');

	// Viewing someone else?
	$u = $loguserid;
	if ($isadmin && $id) {
		$u = $id;
		$idparam = "id=$id&";
	}

	// Viewing sent messages?
	$to   = 'to';
	$from = 'from';
	if ($view == 'sent') {
		$to   = 'from';
		$from = 'to';
		$viewparam = 'view=sent&';
	}

	if(!$ppp)
		$ppp=50;
	if(!$page)
		$page=1;

	$pmin=($page-1)*$ppp;
	$msgtotal=$sql->resultp("SELECT count(*) FROM pmsgs WHERE user$to=?", array($u));
	$pagelinks='Pages:';
	$p=0;
	for($i=0; $i<$msgtotal; $i+=$ppp) {
		$p++;
		if($p==$page)
			$pagelinks.=" $p";
		else
			$pagelinks.=" <a href=private.php?{$idparam}{$viewparam}page={$p}>{$p}</a>";
	}

	// 1252378129
	$values = array(
		'user' => $u,
		'min'  => $pmin,
		'ppp'  => $ppp,
	);
	$pmsgs   = $sql->queryp("SELECT p.id,user$from uid,date,t.title,msgread,name,sex,powerlevel,aka,birthday
		FROM pmsgs p,pmsgs_text t,users u
		WHERE user$to=:user
		AND p.id=pid
		AND user$from=u.id "
		.($loguser['id'] == 175 ? "AND p.id > 8387 " : "")
		."ORDER BY " .($loguser['id'] == 175 ? "user$from DESC, " : "msgread ASC, ")
		."p.id DESC
		LIMIT :min,:ppp
	", $values);

	$from[0] = strtoupper($from[0]);

	if(!$view)
		$viewlink="<a href=private.php?{$idparam}view=sent>View sent messages</a>";
	else
		$viewlink="<a href=private.php?{$idparam}>View received messages</a>";

	print "$header
		<table width=100%><td>$fonttag<a href=index.php>$boardname</a> - "
			.(($u != $loguserid) ? $sql->resultp("SELECT `name` FROM `users` WHERE `id` = ?", array($u))."'s private messages" : "Private messages")
			." - "
			.((!$view) ? 'Inbox' : 'Outbox').": $msgtotal</td>
		<td align=right>$smallfont$viewlink | <a href=sendprivate.php>Send new message</a></table>
		$tblstart<tr>
		$tccellh width=50>&nbsp</td>
		$tccellh>Subject</td>
		$tccellh width=15%>$from</td>
		$tccellh width=180>Sent on</td></tr>
	";

	while($pmsg = $sql->fetch($pmsgs)) {
		$new       = ($pmsg['msgread']?'&nbsp;':$statusicons['new']);
		$namecolor = getuserlink($pmsg, array('id'=>'uid'));
		print "
			<tr style='height:20px;'>
			$tccell1>$new</td>
			$tccell2l><a href=showprivate.php?id=$pmsg[id]>$pmsg[title]</td>
			$tccell2>$namecolor</td>
			$tccell2>".date($dateformat,$pmsg['date']+$tzoff)."
			</tr>
		";
	}

	print "$tblend$smallfont$pagelinks$footer";
	printtimedif($startingtime);
?>
