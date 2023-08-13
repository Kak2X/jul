<?php
	require "lib/common.php";

	$windowtitle = "Online users";

/*
	if (empty($_COOKIE) && $_SERVER['HTTP_REFERER'] == "http://jul.rustedlogic.net/") {
		// Some lame botnet that keeps refreshing this page every second or so.
		xk_ircsend("102|". date("Y-m-d h:i:s") ." - ".xk(7)."IP address ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." is being weird. ". xk(5) ."(UA: ". $_SERVER['HTTP_USER_AGENT'] .")");
		header("Location: http://". $_SERVER['REMOTE_ADDR'] ."/");
		die("Fuck off, forever.");
	}
	if (empty($_COOKIE)) {
		// Some lame botnet that keeps refreshing this page every second or so.
		xk_ircsend("102|". date("Y-m-d h:i:s") ." - ".xk(7)."IP address ". xk(8) . $_SERVER['REMOTE_ADDR'] . xk(7) ." is being slightly less weird, but still weird. ". xk(5) ."(UA: ". $_SERVER['HTTP_USER_AGENT'] .")");
		header("Location: http://". $_SERVER['REMOTE_ADDR'] ."/");
		die("Don't be weird.");
	}
*/

	$time = filter_int($_GET['time']);
	if (!$time) $time = 300;

	// FOR THE LOVE OF GOD XKEEPER JUST GIVE ME ~NUKE ACCESS
	$banorama	= ($_SERVER['REMOTE_ADDR'] == $x_hacks['adminip'] || $loguser['id'] == 1 /* || $loguser['id'] == 5 || $loguser['id'] == 2100*/);

	if ($banorama && filter_string($_GET['banip'])) {
		check_token($_GET['auth'], TOKEN_BANNER, $_GET['banip']);
		$ircmsg = xk(8) . $loguser['name'] . xk(7) ." added IP ban for ". xk(8) . $_GET['banip'] . xk(7) .".";
		ipban($_GET['banip'], "online.php ban", $ircmsg);
//		if ($_GET['uid']) mysql_query("UPDATE `users` SET `powerlevel` = -1, `title` = 'Banned; account hijacked. Contact admin via PM to change it.' WHERE `id` = '". $_GET['uid'] ."'") or print mysql_error();
		return header("Location: online.php"); // ?m=1
	}


	pageheader($windowtitle);
	
	// (this disabled the IP sorting, for whatever it's worth)
	//$sort	= filter_bool($_GET['sort']); 
	$sort = filter_string($_GET['sort']);
	if ($sort == 'IP' && $isadmin) $ipsort = true;	// Just check now and don't bother for the rest
	$lnk 	= ($sort ? "?sort=1&" : '?');
	?>
	<div class='fonts'>
		Show online users during the last:
		<a href="online.php<?=$lnk?>time=60">minute</a> |
		<a href="online.php<?=$lnk?>time=300">5 minutes</a> |
		<a href="online.php<?=$lnk?>time=900">15 minutes</a> |
		<a href="online.php<?=$lnk?>time=3600">hour</a> |
		<a href="online.php<?=$lnk?>time=86400">day</a>
<?php if ($isadmin) { ?>
		<br>Admin cruft: <a href="online.php?<?=(isset($ipsort) ? '':'sort=IP&')?>time=<?=$time?>">Sort by <?=(isset($ipsort) ? 'date' : 'IP')?></a>		
<?php } 
	
	// Logged in users
	$posters = $sql->query("
		SELECT $userfields, u.posts, lastactivity, lastip, lastposttime, lasturl, hideactivity
		FROM users u
		WHERE lastactivity > ".(ctime()-$time)." AND ($ismod OR !hideactivity)
		ORDER BY ".(isset($ipsort) ? 'lastip' : 'lastactivity DESC')
	);


	?><br>
	<span class='font'> Online users during the last <?=timeunits2($time)?>:</span>
	<table class='table'>
		<td class='tdbgh center' width=20>&nbsp;</td>
		<td class='tdbgh center' width=200>Username</td>
		<td class='tdbgh center' width=120> Last activity</td>
		<td class='tdbgh center' width=180> Last post</td>
		<td class='tdbgh center' width=*>URL</td>
	<?=($isadmin ? "<td class='tdbgh center' width=120>IP address</td>" : "")?>
		<td class='tdbgh center' width=60> Posts</td>
	</tr>
	<?php

	for ($i = 1; $user=$sql->fetch($posters); ++$i) {
		$userlink = getuserlink($user);
		if ($user['hideactivity']) $userlink = "<b>[</b> $userlink <b>]</b>";
		if (!$user['posts']) $user['lastposttime'] = getblankdate();
		else                 $user['lastposttime'] = printdate($user['lastposttime']);

		$user['lastip'] = htmlspecialchars($user['lastip'], ENT_QUOTES);
		//$user['lasturl']=str_replace('<','&lt;',$user['lasturl']);
		//$user['lasturl']=str_replace('>','&gt;',$user['lasturl']);
		//$user['lasturl']=str_replace('%20',' ',$user['lasturl']);
		$user['lasturl']=str_replace('shop?h&','shop?',$user['lasturl']);
		$user['lasturl']=preg_replace('/[\?\&]debugsql|(=[0-9]+)/i','',$user['lasturl']); // let's not give idiots any ideas
		$user['lasturl']=preg_replace('/[\?\&]auth(=[0-9a-z]+)/i','',$user['lasturl']); // don't reveal the token
		$user['lasturl']=escape_attribute($user['lasturl']);		
		if (substr($user['lasturl'], -11) =='(IP banned)' || substr($user['lasturl'], -11) =='(Tor proxy)' || substr($user['lasturl'], -5) == '(Bot)') {
			$ptr = strrpos($user['lasturl'], '(', -4);
			$realurl = substr($user['lasturl'], 0, $ptr-1);
		} else {
			$realurl = $user['lasturl'];
		}
		
		?>
		<tr style="height:24px">
			<td class='tdbg1 center'><?=$i?></td>
			<td class='tdbg2'><?=$userlink?></td>
			<td class='tdbg1 center'><?=date('h:i:s A',$user['lastactivity']+$loguser['tzoff'])?></td>
			<td class='tdbg1 center'><?=$user['lastposttime']?></td>
			<td class='tdbg2'><a rel="nofollow" href="<?=_urlformat($realurl)?>"><?=$user['lasturl']?></td>
		<?php

		if ($banorama)
			$ipban	= "<span class='fonts'><br>[<a href='?banip={$user['lastip']}&uid={$user['id']}&auth=".generate_token(TOKEN_BANNER, $user['lastip'])."'>Ban</a> - <a href='http://google.com/search?q={$user['lastip']}'>G</a>]</span>";
		else $ipban = "";
		
		if($isadmin)
			print "<td class='tdbg1 center'><a href='admin-ipsearch.php?ip={$user['lastip']}'>{$user['lastip']}</a> $ipban</td>";
//		<td class='tdbg1 right'>". $user['ipmatches'] ." <img src='". ($user['ipmatches'] > 0 ? "images/dot2.gif" : "images/dot5.gif") ."' align='absmiddle'></td>";

		?>
			<td class='tdbg2 center'><?=$user['posts']?></td>
		<?php
	}
		?>
		</tr>
	</table>
		<?php
	//WHERE date>'.(ctime()-$time).'
	$guests = $sql->query('
		SELECT *, (SELECT COUNT(`ip`) FROM `ipbans` WHERE `ip` = `guests`.`ip`) AS banned
		FROM guests
		ORDER BY '.(isset($ipsort) ? 'ip' : 'date').' DESC
	');

	?>
	<span class='font'><br>Guests online in the past 5 min.:</span>
	<table class='table'>
		<tr>
			<td class='tdbgh center' width=20>&nbsp;</td>
			<td class='tdbgh center' width=300>&nbsp;</td>
			<td class='tdbgh center' width=120>Last activity</td>
			<td class='tdbgh center' width=*>URL</td>
		<?=($isadmin ? "<td class='tdbgh center' width=120> IP address</td>" : "")?>
		</tr>
	<?php

	for($i=1;$guest=$sql->fetch($guests);++$i){
		$guest['ip'] = htmlspecialchars($user['ip'], ENT_QUOTES);
		//$guest['lasturl']=str_replace('<','&lt;',$guest['lasturl']);
		//$guest['lasturl']=str_replace('>','&gt;',$guest['lasturl']);
		$guest['lasturl']=str_replace('shop?h&','shop?',$guest['lasturl']);
		$guest['lasturl']=preg_replace('/[\?\&]debugsql|(=[0-9]+)/i','',$guest['lasturl']); // let's not give idiots any ideas
		$guest['lasturl']=preg_replace('/[\?\&]auth(=[0-9a-z]+)/i','',$guest['lasturl']); // just in case
		$guest['lasturl']=htmlspecialchars($guest['lasturl'], ENT_QUOTES);
/*		if ($guest['useragent'] == "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.5; en-US; rv:1.9.0.19) Gecko/2010031218 Firefox/3.0.19" && $banorama) {
//		if (stripos($guest['useragent'], "robot") !== false && $banorama)
			$marker	= " style='color: #f88;'";
		else
			$marker	= "";
		
*/

		$marker = '';
		
		if (substr($guest['lasturl'], -11) =='(IP banned)' || substr($guest['lasturl'], -11) =='(Tor proxy)' || substr($guest['lasturl'], -5) == '(Bot)') {
			$ptr = strrpos($guest['lasturl'], '(', -4);
			$realurl = substr($guest['lasturl'], 0, $ptr-1);
		} else {
			$realurl = $guest['lasturl'];
		}
/*
		$lasturltd	= "<td class='tdbg2'$marker><a rel=\"nofollow\" href=\"". _urlformat($guest['lasturl']) ."\">$guest[lasturl]";
		if (substr($guest['lasturl'], -11) =='(IP banned)')
			$lasturltd	= "<td class='tdbg2'$marker><a rel=\"nofollow\" href=\"". substr($guest['lasturl'], 0, -12) ."\">". substr($guest['lasturl'], 0, -12) ."</a> (IP banned)";
		elseif (substr($guest['lasturl'], -11) =='(Tor proxy)')
			$lasturltd	= "<td class='tdbg2'$marker><a rel=\"nofollow\" href=\"". substr($guest['lasturl'], 0, -12) ."\">". substr($guest['lasturl'], 0, -12) ."</a> (Tor proxy)";
		elseif (substr($guest['lasturl'], -5) =='(Bot)')
			$lasturltd	= "<td class='tdbg2'$marker><a rel=\"nofollow\" href=\"". substr($guest['lasturl'], 0, -6) ."\">". substr($guest['lasturl'], 0, -6) ."</a> (Bot)";
*/

		?>
		<tr style="height:40px">
			<td class='tdbg1 center'<?=$marker?>><?=$i?></td>
			<td class='tdbg2 fonts center'<?=$marker?>><?=htmlspecialchars($guest['useragent'])?></td>
			<td class='tdbg1 center'<?=$marker?>><?=date('h:i:s A',$guest['date']+$loguser['tzoff'])?></td>
			<td class='tdbg2'<?=$marker?>><a rel="nofollow" href="<?=_urlformat($realurl)?>"><?=$guest['lasturl']?></td>
		<?php


		if ($banorama && !$guest['banned'])
			$ipban	= "<a href='?banip={$guest['ip']}&auth=" . generate_token(TOKEN_BANNER, $guest['ip']) ."'>Ban</a> - ";
		elseif ($guest['banned'])
		 	$ipban	= "<span style='color: #f88; font-weight: bold;'>Banned</span> - ";
		else
			$ipban	= "";

		if($isadmin)
			print "</td><td class='tdbg1 center'$marker>
			<a href=admin-ipsearch.php?ip={$guest['ip']}>{$guest['ip']}</a><span class='fonts'>
			<br>[$ipban<a href='http://google.com/search?q={$guest['ip']}'>G</a>-<a href='http://en.wikipedia.org/wiki/User:{$guest['ip']}'>W</a>-<a href='http://{$guest['ip']}/'>H</a>]</a></span>";
  

	}
		?>
		</tr>
	</table>
	</div>
	<?php
	
	pagefooter();

	function _urlformat($url) {
		return preg_replace("/^\/thread\.php\?pid=([0-9]+)$/", "/thread.php?pid=\\1#\\1", $url);
	}