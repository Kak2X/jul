<?php

function xk_ircout($type, $user, $in) {
	global $config;
	
	// gone
	//return;
	# and back

	$indef = array(
		//'pow'		=> 1,
		'fid'		=> 0,
		'id'		=> 0,
		//'pmatch'	=> 0,
		'ip'		=> 0,
		'forum'		=> 0,
		'thread'	=> 0,
		'pid'		=> 0,
		'dest'		=> 0, // @TODO: Add channel destination field to forums table
	);
	
	$in = array_merge($indef, $in);
	
	// Public forums have dest 0, everything else 1
	/*$dest	= min(1, max(0, $in['pow']));
	
	// Posts in certain forums are reported elsewhere
	if ($in['fid'] == 99) {
		$dest	= 6;
	} elseif ($in['fid'] == 98) {
		$dest	= 7;
	}
*/
	global $x_hacks;
	if ($x_hacks['host'] || !$config['irc-reporting']) return;

	
	
	if ($type == "user") {
		/* not usable
		if ($in['pmatch']) {
			$color	= array(8, 7);
			if		($in['pmatch'] >= 3) $color	= array(7, 4);
			elseif	($in['pmatch'] >= 5) $color	= array(4, 5);
			$extra	= " (". xk($color[1]) ."Password matches: ". xk($color[0]) . $in['pmatch'] . xk() .")";
		}
		*/
		$extra = "";
		xk_ircsend("1|New user: #". xk(12) . $in['id'] . xk(11) ." $user ". xk() ."(IP: ". xk(12) . $in['ip'] . xk() .")$extra: {$config['board-url']}/?u=". $in['id']);
		// Also show to public channel, but without the admin-only fluff
		xk_ircsend("0|New user: #". xk(12) . $in['id'] . xk(11) ." $user ". xk() .")$extra: {$config['board-url']}/?u=". $in['id']);
		
		
	} else {
//			global $sql;
//			$res	= $sql -> resultq("SELECT COUNT(`id`) FROM `posts`");
		xk_ircsend($in['dest']."|New $type by ". xk(11) . $user . xk() ." (". xk(12) . $in['forum'] .": ". xk(11) . $in['thread'] . xk() ."): {$config['board-url']}/?p=". $in['pid']);

	}

}

function xk_ircsend($str) {
	// $str = <chan id>|<message>
/*	
	$str = str_replace(array("%10", "%13"), array("", ""), rawurlencode($str));

	$str = html_entity_decode($str);
	

	$ch = curl_init();
	//curl_setopt($ch, CURLOPT_URL, "http://treeki.rustedlogic.net:5000/reporting.php?t=$str");
	curl_setopt($ch, CURLOPT_URL, "ext/reporting.php?t=$str");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // <---- HERE
	curl_setopt($ch, CURLOPT_TIMEOUT, 5); // <---- HERE
	$file_contents = curl_exec($ch);
	curl_close($ch);
*/
	return true;
}

// IRC Color code setup
function xk($n = -1) {
	if ($n == -1) $k = "";
		else $k = str_pad($n, 2, 0, STR_PAD_LEFT);
	return "\x03". $k;
}
