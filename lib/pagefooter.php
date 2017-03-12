<?php

function pagefooter() {
	global $x_hacks, $sql, $sqldebuggers, $loguser, $config, $scriptname, $startingtime;
	
	if (!$config['affiliate-links']) {
		$affiliatelinks = "";
	} else {
		$affiliatelinks = "<form><select onchange='window.open(this.options[this.selectedIndex].value)'>{$config['affiliate-links']}</select></form>";
	}
	
	$doomnum = ($x_hacks['mmdeath'] >= 0) ? "<div style='position: absolute; top: -100px; left: -100px;'>Hidden preloader for doom numbers:
	<img src='numgfx/death/0.png'> <img src='numgfx/death/1.png'> <img src='numgfx/death/2.png'> <img src='numgfx/death/3.png'> <img src='numgfx/death/4.png'> <img src='numgfx/death/5.png'> <img src='numgfx/death/6.png'> <img src='numgfx/death/7.png'> <img src='numgfx/death/8.png'> <img src='numgfx/death/9.png'></div>" : "";

	
	// Acmlmboard - <a href='https://github.com/Xkeeper0/jul'>". (file_exists('version.txt') ? file_get_contents("version.txt") : shell_exec("git log --format='commit %h [%ad]' --date='short' -n 1")) ."</a>
	// <br>". 	($loguser['id'] && $scriptname != 'index.php' ? adbox() ."<br>" : "") ."
	/*
<!-- Piwik -->
<script type=\"text/javascript\">
var pkBaseURL = ((\"https:\" == document.location.protocol) ? \"https://stats.tcrf.net/\" : \"http://stats.tcrf.net/\");
document.write(unescape(\"%3Cscript src='\" + pkBaseURL + \"piwik.js' type='text/javascript'%3E%3C/script%3E\"));
</script><script type=\"text/javascript\">
try {
var piwikTracker = Piwik.getTracker(pkBaseURL + \"piwik.php\", 4);
piwikTracker.trackPageView();
piwikTracker.enableLinkTracking();
} catch( err ) {}
</script><noscript><p><img src=\"http://stats.tcrf.net/piwik.php?idsite=4\" style=\"border:0\" alt=\"\" /></p></noscript>
<!-- End Piwik Tag -->
<!--<script type=\"text/javascript\" src=\"http://ajax.aspnetcdn.com/ajax/jQuery/jquery-1.6.min.js\"></script>
<script type=\"text/javascript\" src=\"js/useful.js\"></script> -->
	*/
	
	
	
	?>
	
	<br>
	<br>
	<center>
		<!--
		<img src='adnonsense.php?m=d' title='generous donations to the first national bank of bad jokes and other dumb crap people post' style='margin-left: 44px;'><br>
		<img src='adnonsense.php' title='hotpod fund' style='margin: 0 22px;'><br>
		<img src='adnonsense.php?m=v' title='VPS slushie fund' style='margin-right: 44px;'>
		-->
		<br>
	
		<span class='fonts'>
		<br>
		<br>
		<a href='<?=$config['footer-url']?>'><?=$config['footer-title']?></a>
		<br>
		<?=$affiliatelinks?>
		<br>
		
		<table cellpadding=0 border=0 cellspacing=2>
			<tr>
				<td>
					<img src='images/poweredbyacmlm.gif'>
				</td>
				<td>
					<span class='fonts'>
						Acmlmboard - <?=BOARD_VERSION?><br>
						&copy;2000-<?=date("Y")?> Acmlm, Xkeeper, Inuyasha, Kak et al. 
					</span>
				</td>
			</tr>
		</table>
		<?=$doomnum?>
		
	<?php

	/*
		( used to be in printtimedif() )
	*/
	$exectime = microtime(true) - $startingtime;

	$qseconds = sprintf("%01.6f", mysql::$time);
	$sseconds = sprintf("%01.6f", $exectime - mysql::$time);
	$tseconds = sprintf("%01.6f", $exectime);

	$queries = mysql::$queries;
	$cache   = mysql::$cachehits;

	// Old text
	//print "<br>{<font class="fonts">} Page rendered in {$tseconds} seconds.</font><br>";

	print "<br>
		<span class='fonts'>{$queries} database queries". (($cache > 0) ? ", {$cache} query cache hits" : "") .".</span>
		<table class='fonts' style='border-spacing: 0px'>
			<tr><td align=right>Query execution time:&nbsp;</td><td>{$qseconds} seconds</td></tr>
			<tr><td align=right>Script execution time:&nbsp;</td><td>{$sseconds} seconds</td></tr>
			<tr><td align=right>Total render time:&nbsp;</td><td>{$tseconds} seconds</td></tr>
		</table>";
		
	$debugPerm = has_perm('view-debugger');
	// Print errors locally
	print error_printer(true, ($debugPerm || $config['always-show-debug']), $GLOBALS['errors']);

	// Print mysql queries
	if ((mysql::$debug_on && (in_array($_SERVER['REMOTE_ADDR'], $sqldebuggers) || $loguser['id'] == 1 || $debugPerm)) || $config['always-show-debug']) {
		if (!isset($_GET['debugsql']) && !$config['always-show-debug']) // $_SERVER['REQUEST_METHOD'] != 'POST'
			print "<br><a href='".$_SERVER['REQUEST_URI'].(($_SERVER['QUERY_STRING']) ? "&" : "?")."debugsql=1'>Useless mySQL query debugging shit</a>";
		else
			print mysql::debugprinter();
	}
	

	if (!$x_hacks['host']) {
		$pages	= array(
			"index.php",
			"thread.php",
			"forum.php",
		);
		if (in_array($scriptname, $pages)) {
			$sql->queryp("INSERT INTO rendertimes SET page = ?, time = ?, rendertime  = ?", ["/$scriptname", ctime(), $exectime]);
			$sql->query("DELETE FROM rendertimes WHERE time < '". (ctime() - 86400 * 14) ."'");
		}
	}	

	die;
}
