<?php

function settags($text, $tags) {
	if (!$tags) {
		return $text;
	} else {
		$text	= dotags($text, array(), $tags);
	}

	return $text;
}

function dotags($msg, $user, &$tags = array()) {
	global $sql, $loguser;
	if (is_string($tags)) {
		$tags	= json_decode($tags, true);
	}

	if (empty($tags) && empty($user)) {
		// settags sent us here and we have nothing to go off of.
		// Shrug our shoulders, and move on.
		return $msg;
	}

	if (empty($tags)) {
		$tags	= array(
			'/me '			=> "*<b>". $user['username'] ."</b> ",
			'&date&'		=> date($loguser['dateformat'], ctime() + $loguser['tzoff']),
			'&numdays&'		=> floor($user['days']),

			'&numposts&'	=> $user['posts'],
			'&rank&'		=> getrank($user['useranks'], '', $user['posts'], 1),
			'&postrank&'	=> $sql->resultq("SELECT count(*) FROM `users` WHERE posts > {$user['posts']}") + 1,
			'&5000&'		=>  5000 - $user['posts'],
			'&10000&'		=> 10000 - $user['posts'],
			'&20000&'		=> 20000 - $user['posts'],
			'&30000&'		=> 30000 - $user['posts'],

			'&exp&'			=> $user['exp'],
			'&expgain&'		=> calcexpgainpost($user['posts'], $user['days']),
			'&expgaintime&'	=> calcexpgaintime($user['posts'], $user['days']),

			'&expdone&'		=> $user['expdone'],
			'&expdone1k&'	=> floor($user['expdone'] /  1000),
			'&expdone10k&'	=> floor($user['expdone'] / 10000),

			'&expnext&'		=> $user['expnext'],
			'&expnext1k&'	=> floor($user['expnext'] /  1000),
			'&expnext10k&'	=> floor($user['expnext'] / 10000),

			'&exppct&'		=> sprintf('%01.1f', ($user['lvllen'] ? (1 - $user['expnext'] / $user['lvllen']) : 0) * 100),
			'&exppct2&'		=> sprintf('%01.1f', ($user['lvllen'] ? (    $user['expnext'] / $user['lvllen']) : 0) * 100),

			'&level&'		=> $user['level'],
			'&lvlexp&'		=> calclvlexp($user['level'] + 1),
			'&lvllen&'		=> $user['lvllen'],
		);
	}

	$msg	= strtr($msg, $tags);
	return $msg;
}

// doreplace()
function prepare_tags($msg, $posts, $days, $userid, &$tags = null) {
	global $tagval, $sql;

	$user	= $sql->fetchq("SELECT name, useranks FROM `users` WHERE `id` = $userid", PDO::FETCH_ASSOC, mysql::USE_CACHE);

	$userdata		= array(
		'id'		=> $userid,
		'username'	=> $user['name'],
		'posts'		=> $posts,
		'days'		=> $days,
		'useranks'	=> $user['useranks'],
		'exp'		=> calcexp($posts,$days)
	);

	$userdata['level']		= calclvl($userdata['exp']);
	$userdata['expdone']	= $userdata['exp'] - calclvlexp($userdata['level']);
	$userdata['expnext']	= calcexpleft($userdata['exp']);
	$userdata['lvllen']		= totallvlexp($userdata['level']);


	if (!$tags) {
		$tags	= array();
	}
	$msg	= dotags($msg, $userdata, $tags);

	return $msg;
}

function escape_codeblock($text) {
	/* Old code formatting
	$list  = array("[code]", "[/code]", "<", "\\\"" , "\\\\" , "\\'", "[", ":", ")", "_");
	$list2 = array("", "", "&lt;", "\"", "\\", "\'", "&#91;", "&#58;", "&#41;", "&#95;");

	// @TODO why not just use htmlspecialchars() or htmlentities()
	return "[quote]<code>". str_replace($list, $list2, $text[0]) ."</code>[/quote]";
	*/
	// Slightly less insane code block parser.
	$text[0] = substr($text[0] , 6, -6);
	
	// Hack around the problem of different nested quotes.
	// TODO: Replace with an appropriate regex
	$len 	= strlen($text[0]);
	$intext = false;
	$ret	= "";
	for ($i = 0; $i < $len; ++$i) {
		
		if ($text[0][$i] == '"' || $text[0][$i] == "'") {
			if ($i && $text[0][$i-1] != '\\') {
				if (!$intext) {
					$intext = $text[0][$i];
				} else if ($intext != $text[0][$i]) {
					$ret .= htmlentities($text[0][$i], ENT_QUOTES); // Escape badly opened quotes inside other quotes so that the regex below won't break
					continue;
				} else {
					$intext = false;
				}
			}
		}
		$ret .= $text[0][$i];
	}
	
	// Horrible hacks on top of horrible hacks to prevent regex overlapping
	// Other symbols
	$ret = preg_replace_callback("'(\(|\)|\[|\]|\{|\}|\=|\<|\>|\:)'", function($x){return "<span style=color:#007700>".htmlspecialchars($x[1])."</span>";}, $ret);
	// Operators
	$ret = preg_replace_callback("'(\+|\-|\||\!)'", function($x){return "<span style=color:#C0C0FF>".htmlspecialchars($x[0])."</span>";}, $ret);
	
	// Strings
	$ret = preg_replace("'([^\\\])(\')(.*?)([^\\\]\')'si", "$1<span style='color: #ff6666 !important'>$2$3$4</span>", $ret);
	$ret = preg_replace("'([^\\\])(\")(.*?)([^\\\]\")'si", "$1<span style='color: #ff6666 !important'>$2$3$4</span>", $ret);
	
	//	Comment lines
	$ret = preg_replace("'\/\*(.*?)\*\/'si", "<span style='color: #FF8000 !important'>/*$1*/</span>",$ret); /* */
	$ret = preg_replace("'\/\/(.*?)\r?\n'i", "<span style='color: #FF8000 !important'>//$1</span>\r\n",$ret); //
	
	return "[quote]<code style='background: #000 !important; color: #fff'>$ret</code>[/quote]";
}
// do_replace2()
function format_post($msg, $options='0|0', $nosbr = false){
	global $hacks;
	// options will contain smiliesoff|htmloff
	$options = explode("|", $options);
	$smiliesoff = $options[0];
	$htmloff = $options[1];


	$msg=preg_replace_callback("'\[code\](.*?)\[/code\]'si", 'escape_codeblock',$msg);


	if ($htmloff) {
		$msg = str_replace("<", "&lt;", $msg);
		$msg = str_replace(">", "&gt;", $msg);
	}

	if (!$smiliesoff) {
		global $smilies;
		if (!$smilies) $smilies = readsmilies();
		for($s = 0; $smilies[$s][0]; ++$s){
			$smilie = $smilies[$s];
			$msg = str_replace($smilie[0], "<img src='$smilie[1]' align=absmiddle>", $msg);
		}
	}

	// Simple check for skipping BBCode replacements
	if (strpos($msg, "[") !== false){
		$msg=str_replace('[red]',	'<font color=FFC0C0>',$msg);
		$msg=str_replace('[green]',	'<font color=C0FFC0>',$msg);
		$msg=str_replace('[blue]',	'<font color=C0C0FF>',$msg);
		$msg=str_replace('[orange]','<font color=FFC080>',$msg);
		$msg=str_replace('[yellow]','<font color=FFEE20>',$msg);
		$msg=str_replace('[pink]',	'<font color=FFC0FF>',$msg);
		$msg=str_replace('[white]',	'<font color=white>',$msg);
		$msg=str_replace('[black]',	'<font color=0>'	,$msg);
		$msg=str_replace('[/color]','</font>',$msg);
		$msg=preg_replace("'\[quote=(.*?)\]'si", '<blockquote><font class=fonts><i>Originally posted by \\1</i></font><hr>', $msg);
		$msg=str_replace('[quote]','<blockquote><hr>',$msg);
		$msg=str_replace('[/quote]','<hr></blockquote>',$msg);
		$msg=preg_replace("'\[sp=(.*?)\](.*?)\[/sp\]'si", '<span style="border-bottom: 1px dotted #f00;" title="did you mean: \\1">\\2</span>', $msg);
		$msg=preg_replace("'\[abbr=(.*?)\](.*?)\[/abbr\]'si", '<span style="border-bottom: 1px dotted;" title="\\1">\\2</span>', $msg);
		$msg=str_replace('[spoiler]','<div class="fonts pstspl2"><b>Spoiler:</b><div class="pstspl1">',$msg);
		$msg=str_replace('[/spoiler]','</div></div>',$msg);
		$msg=preg_replace("'\[(b|i|u|s)\]'si",'<\\1>',$msg);
		$msg=preg_replace("'\[/(b|i|u|s)\]'si",'</\\1>',$msg);
		$msg=preg_replace("'\[img\](.*?)\[/img\]'si", '<img src=\\1>', $msg);
		$msg=preg_replace("'\[url\](.*?)\[/url\]'si", '<a href=\\1>\\1</a>', $msg);
		$msg=preg_replace("'\[url=(.*?)\](.*?)\[/url\]'si", '<a href=\\1>\\2</a>', $msg);
		$msg=preg_replace("'\[youtube\]([a-zA-Z0-9_-]{11})\[/youtube\]'si", '<iframe src="https://www.youtube.com/embed/\1" width="560" height="315" frameborder="0" allowfullscreen="allowfullscreen"></iframe>', $msg);
		
		// Userlinks
		$msg = preg_replace_callback("'\[user=(\d+)\]'si", 'userlink_from_id', $msg);
	}
	
	if (strpos($msg, "@") !== false){
		// Userlinks
		$msg = preg_replace_callback("'\\@\"(.*?)\"'si", 'userlink_from_name', $msg);
	}
	
	do {
		$msg	= preg_replace("/<(\/?)t(able|h|r|d)(.*?)>(\s+?)<(\/?)t(able|h|r|d)(.*?)>/si",
				"<\\1t\\2\\3><\\5t\\6\\7>", $msg, -1, $replaced);
	} while ($replaced >= 1);
	
	if ($hacks['comments']) {
		$p = str_replace("<!--", '<span style="color:#80ff80">&lt;!--', $p);
		$p = str_replace("-->", '--&gt;</span>', $p);
	}

	if (!$nosbr) sbr(0,$msg);

	return $msg;
}

function formatting_trope($input) {
	$in		= "/[A-Z][^A-Z]/";
	$out	= " \\0";
	$output	= preg_replace($in, $out, $input);

	return trim($output);
}

function squot($t, &$src){
	switch($t){
		case 0: $src=htmlspecialchars($src); break;
		case 1: $src=urlencode($src); break;
		case 2: $src=str_replace('&quot;','"',$src); break;
		case 3: $src=urldecode('%22','"',$src); break;
	}
}
function sbr($t, &$src){
	switch($t) {
		case 0: $src=str_replace("\n",'<br>',$src); break;
		case 1: $src=str_replace('<br>',"\n",$src); break;
	}
}


function nuke_js($before, $after) {

	global $sql, $loguser;
	$sql->queryp("
		INSERT INTO `jstrap` SET
			`loguser`  =  {$loguser['id']},
			`ip`       = :ipaddr,
			`text`     = :source,
			`url`      = :url,
			`time`     = ".ctime().",
			`filtered` = :filtered",
		[
		 ':ipaddr'   => $_SERVER['REMOTE_ADDR'], 
		 ':url'      => $_SERVER['REQUEST_URI'],
		 ':source'   => $before,
		 ':filtered' => $after
		]
	);

}

function xssfilters($p, $strict = false){
	
	// The filters here should NOT be moved to the database
	$temp = $p;
	
	$p=str_ireplace("FSCommand","BS<z>Command", $p);
	$p=str_ireplace("execcommand","hex<z>het", $p);
	// This shouldn't hit code blocks due to the way they are formatted
	$p=preg_replace("'on\w+( *?)=( *?)(\'|\")'si", "jscrap=$3", $p);
	$p=preg_replace("'<(/?)(script|meta|embed|object|svg|form|textarea|xml|title|input|xmp|plaintext|base|!doctype|html|head|body)'i", "&lt;$1$2", $p);
	$p=preg_replace("'<iframe(?! src=\"https://www.youtube.com/embed/)'si",'<<z>iframe',$p);
	
	/*
	$p=preg_replace("'onload'si",'onl<z>oad',$p);
	$p=preg_replace("'onerror'si",'oner<z>ror',$p);
	$p=preg_replace("'onunload'si",'onun<z>load',$p);
	$p=preg_replace("'onchange'si",'onch<z>ange',$p);
	$p=preg_replace("'onsubmit'si",'onsu<z>bmit',$p);
	$p=preg_replace("'onreset'si",'onr<z>eset',$p);
	$p=preg_replace("'onselect'si",'ons<z>elect',$p);
	$p=preg_replace("'onblur'si",'onb<z>lur',$p);
	$p=preg_replace("'onfocus'si",'onfo<z>cus',$p);
	$p=preg_replace("'onclick'si",'oncl<z>ick',$p);
	$p=preg_replace("'ondblclick'si",'ondbl<z>click',$p);
	$p=preg_replace("'onmousedown'si",'onm<z>ousedown',$p);
	$p=preg_replace("'onmousemove'si",'onmou<z>semove',$p);
	$p=preg_replace("'onmouseout'si",'onmou<z>seout',$p);
	$p=preg_replace("'onmouseover'si",'onmo<z>useover',$p);
	$p=preg_replace("'onmouseup'si",'onmou<z>seup',$p);
	*/
	if ($temp != $p) {
		nuke_js($temp, $p);
		if ($strict) return NULL;
	}
	
	
	$p=preg_replace("'document.cookie'si",'document.co<z>okie',$p);
	$p=preg_replace("'eval'si",'eva<z>l',$p);
	$p=preg_replace("'javascript:'si",'javasc<z>ript:',$p);	
	//$p=preg_replace("'document.'si",'docufail.',$p);
	//$p=preg_replace("'<script'si",'<<z>script',$p);
	//$p=preg_replace("'</script'si",'<<z>/script',$p);
	//$p=preg_replace("'<meta'si",'<<z>meta',$p);
	

	return $p;
	
}


function dofilters($p, $f = 0){
	static $filters = NULL;
	
	$p = xssfilters($p);
	
	if (!isset($filters)) {
		global $sql;
		if ($f == -1) 	$where = " OR 1";
		else if ($f)	$where = " OR forum = $f";
		else			$where = "";
		$filters = $sql->fetchq("
			SELECT method, source, replacement
			FROM filters
			WHERE enabled = 1 AND (forum = 0{$where})
			ORDER BY id ASC
		", PDO::FETCH_ASSOC, mysql::FETCH_ALL);
	}
	foreach($filters as $x) {
		switch ($x['method']) {
			case 0:
				$p = str_replace($x['source'], $x['replacement'], $p);
				break;
			case 1:
				$p = str_ireplace($x['source'], $x['replacement'], $p);
				break;
			case 2:
				$p = preg_replace("'{$x['source']}'si", $x['replacement'], $p); // Force 'si modifiers to prevent the 'e modifier from being used
				break;
		}
	}

	return $p;
}

function cleanurl($url) {
	$pos1 = $pos = strrpos($url, '/');
	$pos2 = $pos = strrpos($url, '\\');
	if ($pos1 === FALSE && $pos2 === FALSE)
		return $url;

	$spos = max($pos1, $pos2);
	return substr($url, $spos+1);
}

function unescape($in) {
	$out	= urldecode($in);
	while ($out != $in) {
		$in		= $out;
		$out	= urldecode($in);
	}
	return $out;
}
