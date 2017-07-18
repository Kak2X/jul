<?php

	require 'lib/function.php';

	//if (!in_array($loguser['id'], array(175, 1)) && $loguser['powerlevel'] < 1) {
	if (!has_perm('view-shitbugs')) {
		errorpage("&nbsp;<br>No.<br>&nbsp;");
	}


	//$expower = in_array($loguser['id'], array(175, 1, 2100));
	$expower = ($loguser['id'] == 1 || has_perm('logs-banner'));
	
	if ($expower && isset($_GET['banip'])) {
		check_token($_GET['auth'], 20, $_GET['banip']);
		$sql->query("INSERT INTO `ipbans` SET `ip` = '". $_GET['banip'] ."', `reason`='Abusive/unwelcome activity', `date` = '". ctime() ."', `banner` = '{$loguser['id']}'");// or print mysql_error();
		xk_ircsend("1|". xk(8) . $loguser['name'] . xk(7) ." added IP ban for ". xk(8) . $_GET['banip'] . xk(7) .".");
		return header("Location: ?");
	}
	
	pageheader("Admin Cruft");

	$clearbutton = '&nbsp;';
	if ($expower) {
		if (isset($_POST['clear'])) {
			check_token($_POST['auth'], 40);
			$query = $sql->query("TRUNCATE `minilog`");
		}
		$clearbutton = "<br><form style='margin: 0px; padding: 0px;' action='?' method='post'><input type='submit' class=submit name='clear' value='Clear log'><input type='hidden' name='auth' value='".generate_token(40)."'></form><br>";
	}

	$banflagnames[    1]	= "union<br>select";
	$banflagnames[16384]	= "<s>acunetix</s><br>[WIP]";
	$banflagnames[ 2048]	= "get<br>+";
	$banflagnames[    4]	= "get<br>--";
	$banflagnames[    8]	= "get<br>()";
	$banflagnames[    2]	= "get<br>comment";
	$banflagnames[   16]	= "get<br>exec";
	$banflagnames[   32]	= "get<br>password";
	$banflagnames[ 4096]	= "get<br>script";
	$banflagnames[ 8192]	= "get<br>cookie";
	$banflagnames[   64]	= "cookie<br>comment";
	$banflagnames[  128]	= "cookie<br>exec";
	$banflagnames[  256]	= "cookieban<br>user";
	$banflagnames[  512]	= "cookieban<br>nonuser";
	$banflagnames[ 1024]	= "non-int<br>userid";

	$cells	= count($banflagnames) + 4;
		
?>
<table class='table'>
	<tr><td class='tdbgh center'>Shitbug detection system</td></tr>
	<tr><td class='tdbg1 center'>&nbsp;
		<br>This page lists denied requests, showing what the reason was.
		<br><?=$clearbutton?>
	</td></tr>
</table>
<br>
<table class='table'>
<?php
			
	$colheaders	= "<tr><td class='tdbgh center' width='180'>Time</td><td class='tdbgh center' width='50'>Count</td><td class='tdbgh center'>IP</td><td class='tdbgh center' width='50'>&nbsp</td>";

	foreach ($banflagnames as $flag => $name)
		$colheaders	.= "<td class='tdbgh center' width='60'>$name</td>";

	$colheaders	.= "</tr>";
	print $colheaders;

	$query	= $sql -> query("SELECT *, (SELECT COUNT(`ip`) FROM `ipbans` WHERE `ip` = `minilog`.`ip`) AS `banned` FROM `minilog` ORDER BY `time` DESC");
	
	$rowcnt		= 0;
	$lastflag	= 0;
	$combocount	= 0;
	$lastip		= "";
	$tempout = "";
	
	while ($data = $sql -> fetch($query)) {
		if (($lastip != $data['ip'] || $lastflag != $data['banflags']) && $lastflag != 0) {
			$rowcnt++;
			print str_replace("%%%COMBO%%%", ($combocount > 1 ? " &times;$combocount" : ""), $tempout);
			
			if (!($rowcnt % 50))
				print $colheaders;
			elseif ($lastip != $data['ip'])
				print "<tr><td class='tdbgh center' colspan='$cells'><img src='images/_.gif' height=5 width=5></td></tr>";

			$tempout	= "";
			$combocount	= 0;
		}
		
		$lastip		= $data['ip'];
		$lastflag	= $data['banflags'];
		$combocount++;
		
		if ($combocount == 1) {
			$tempout	= "<tr><td class='tdbg1 center'>". date("m-d-y H:i:s", $data['time']) ."</td><td class='tdbg1 center'>%%%COMBO%%%</td><td class='tdbg1 center'><a href='admin-ipsearch.php?ip=". $data['ip'] ."'>". $data['ip'] ."</a></td>";

			if ($data['banned'])
				$tempout .= "<td class='tdbg1 fonts center'><span style='color: #f88; font-weight: bold;'>Banned</span></td>";
			elseif ($expower)
				$tempout .= "<td class='tdbg1 fonts center'><a href='?banip={$data['ip']}&auth=".generate_token(20, $data['ip'])."'>Ban</a></td>";
			else
				$tempout .= "<td class='tdbg1 fonts center'>&nbsp;</td>";

			foreach ($banflagnames as $flag => $name) {
				if ($data['banflags'] & $flag)
					$tempout	.= "<td class='tdbgc center' width='60'>Hit</td>";
				else
					$tempout	.= "<td class='tdbg2 center' width='60'>&nbsp;</td>";
			}
			$tempout .= "</tr>";
		}
	}
	
	print str_replace("%%%COMBO%%%", ($combocount > 1 ? " &times;$combocount" : ""), $tempout);

	print "</table>";
	
	pagefooter();
?>