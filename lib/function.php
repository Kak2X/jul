<?php
	
	require 'lib/defines.php';

	require 'lib/mysql.php';
	require 'lib/layout.php';
	require 'lib/rpg.php';
	require 'lib/datetime.php';	
	require_once 'lib/routing.php';
	require 'lib/extension.php';
	
	require 'lib/avatars.php';
	require 'lib/attachments.php';
	require 'lib/threadpost.php';
	require 'lib/thread.php';
	require 'lib/pm.php';
	require 'lib/schema.php';
	
	require 'lib/errorhandler.php';
	require 'lib/reporting.php';

// For our convenience (read: to go directly into a query), at the cost of sacrificing the NULL return value
function filter_int(&$v, $d = null) { 
	if ($d === null)
		return (int)$v;
	return $v !== null ? (int)$v : $d;
}
function filter_float(&$v, $d = null) { 
	if ($d === null)
		return (float)$v;
	return $v !== null ? (float)$v : $d;
}
function filter_bool(&$v, $d = null) { 
	if ($d === null)
		return (bool)$v;
	return $v !== null ? (bool)$v : $d;
}
function filter_array(&$v, $d = null) { 
	if ($d === null)
		return (array)$v;
	return $v !== null ? (array)$v : $d;
}
function filter_string(&$v, $d = null) { 
	if ($d === null)
		return (string)$v;
	return $v !== null ? (string)$v : $d;
}
function __(&$v, $d = null)  { 
	if ($d === null)
		return $v;
	return $v !== null ? $v : $d;
}

function readsmilies($path = 'smilies.dat') {
	global $x_hacks;
	if ($x_hacks['host']) {
		$fpnt = fopen('smilies2.dat','r');
	} else {
		$fpnt = fopen($path,'r');
	}
	for ($i = 0; $smil[$i] = fgetcsv($fpnt, 300, ','); ++$i);
	unset($smil[$i]);
	$r = fclose($fpnt);
	return $smil;
}

function readpostread($userid) {
	global $sql;
	if (!$userid) return array();
	return $sql->getresultsbykey("SELECT forum, readdate FROM forumread WHERE user = $userid");
}

function replace_tags($msg, $tags) {
	if ($tags && $msg) {
		$msg	= strtr($msg, $tags);
	}
	return $msg;
}

// to get the tag array out of something
function get_tags($data, $repl = null) {
	global $sql, $loguser;
	
	if (is_string($data)) {
		// this is if we're given directly the 'tagval' field, which already contains everything we need.
		// though it must be first converted to an array.
		$tags = json_decode($data, true);
	} else if (!empty($data)) {
		// when passed an array of the base data to generate tags
		$tagdata['posts']       = isset($repl['posts']) ? $repl['posts'] : $data['posts'];
		$tagdata['days']        = (time() - $data['regdate']) / 86400;
		$tagdata['exp']         = calcexp($tagdata['posts'], $tagdata['days']);
		$tagdata['level']       = calclvl($tagdata['exp']);
		$tagdata['expdone']     = $tagdata['exp'] - calclvlexp($tagdata['level']);
		$tagdata['expnext']     = calcexpleft($tagdata['exp']);
		$tagdata['lvllen']      = totallvlexp($tagdata['level']);
		
		$tags = array(
			'/me '          => "*<b>". $data['name'] ."</b> ",
			'&date&'        => date($loguser['dateformat'], time() + $loguser['tzoff']),
			'&numdays&'     => floor($tagdata['days']),
			'&mood&'        => filter_int($repl['mood']),

			'&numposts&'    => $tagdata['posts'],
			'&rank&'        => getrank($data['useranks'], '', $tagdata['posts'], 0),
			'&postrank&'    => $sql->resultq("SELECT COUNT(*) FROM `users` WHERE posts > {$tagdata['posts']}", 0, 0, mysql::FETCH_ALL) + 1,
			'&5000&'        =>  5000 - $tagdata['posts'],
			'&10000&'       => 10000 - $tagdata['posts'],
			'&20000&'       => 20000 - $tagdata['posts'],
			'&30000&'       => 30000 - $tagdata['posts'],

			'&exp&'         => pretty_nan($tagdata['exp']),
			'&expgain&'     => calcexpgainpost($tagdata['posts'], $tagdata['days']),
			'&expgaintime&' => calcexpgaintime($tagdata['posts'], $tagdata['days']),

			'&expdone&'     => pretty_nan($tagdata['expdone']),
			'&expdone1k&'   => pretty_nan(floor($tagdata['expdone'] /  1000)),
			'&expdone10k&'  => pretty_nan(floor($tagdata['expdone'] / 10000)),

			'&expnext&'     => pretty_nan($tagdata['expnext']),
			'&expnext1k&'   => pretty_nan(floor($tagdata['expnext'] /  1000)),
			'&expnext10k&'  => pretty_nan(floor($tagdata['expnext'] / 10000)),

			'&exppct&'      => sprintf('%01.1f', ($tagdata['lvllen'] ? (1 - $tagdata['expnext'] / $tagdata['lvllen']) : 0) * 100),
			'&exppct2&'     => sprintf('%01.1f', ($tagdata['lvllen'] ? (    $tagdata['expnext'] / $tagdata['lvllen']) : 0) * 100),

			'&level&'       => pretty_nan($tagdata['level']),
			'&lvlexp&'      => pretty_nan(calclvlexp($tagdata['level'] + 1)),
			'&lvllen&'      => pretty_nan($tagdata['lvllen']),
		);
	} else {
		// we were sent here and we have nothing to go off of.
		// shrug our shoulders, and move on.
		$tags = array();
	}
	
	return $tags;
}

function escape_codeblock($text) {
	// Also prevent bbcode from being rendered
	return "<blockquote class='code'><hr>". str_replace("[", "[<z>", htmlspecialchars($text[1])) ."<hr></blockquote>";
}


function domarkup($msg, $stdpost = null, $nosbr = false, $mode = -1) {
	global $hacks, $scriptname;
	
	if (!$msg) return "";
	
	// These options use a consistent name across posts/pms/etc...
	if ($stdpost !== null) {
		$smiliesoff  = $stdpost['nosmilies'];
		$htmloff     = $stdpost['nohtml'];
	} else {
		$smiliesoff = $htmloff = false;
	}
	
	$msg = preg_replace_callback("'\[code\](.*?)\[/code\]'si", 'escape_codeblock', $msg);

	if ($htmloff) {
		$msg = str_replace("<", "&lt;", $msg);
		$msg = str_replace(">", "&gt;", $msg);
	}

	if (!$smiliesoff) {
		global $smilies;
		if (!$smilies) $smilies = readsmilies();
		for ($s = 0; isset($smilies[$s]); ++$s){
			$smilie = $smilies[$s];
			$msg = str_replace($smilie[0], "<img src='{$smilie[1]}' align=absmiddle>", $msg);
		}
	}
	
	// Simple check for skipping BBCode replacements
	if (strpos($msg, "[") !== false){
		$msg = str_replace('[red]',    '<span style="color:#FFC0C0">', $msg);
		$msg = str_replace('[green]',  '<span style="color:#C0FFC0">', $msg);
		$msg = str_replace('[blue]',   '<span style="color:#C0C0FF">', $msg);
		$msg = str_replace('[orange]', '<span style="color:#FFC080">', $msg);
		$msg = str_replace('[yellow]', '<span style="color:#FFEE20">', $msg);
		$msg = str_replace('[pink]',   '<span style="color:#FFC0FF">', $msg);
		$msg = str_replace('[white]',  '<span style="color:white">', $msg);
		$msg = str_replace('[black]',  '<span style="color:black">', $msg);
		$msg = preg_replace("'\[/(color|red|green|blue|orange|yellow|pink|white|black)\]'s",'</span>', $msg);
		//$msg = str_replace('[/color]','</span>', $msg);
		
		//--
		// Post quotes with clickable link and quoted fields
		if ($mode == MODE_POST || $mode == MODE_ANNOUNCEMENT)
			$pidpage = "thread";
		else if ($mode == MODE_PM)
			$pidpage = "showprivate";
		else
			$pidpage = null;
		$msg = preg_replace("'\[quote=\"(.*?)\" id=\"(\d+)\"\]'si", $pidpage
		? '<blockquote><a class="fonts i" href="'.$pidpage.'.php?pid=\\2#\\2">Originally posted by \\1</a><hr>'
		: '<blockquote><span class="fonts i">Originally posted by \\1</span><hr>', $msg);
		//--
		// Generic post quotes
		$msg = preg_replace("'\[quote=(.*?)\]'si", '<blockquote><span class="fonts i">Originally posted by \\1</span><hr>', $msg);
		
		$msg = str_replace('[quote]','<blockquote><hr>', $msg);
		$msg = str_replace('[/quote]','<hr></blockquote>', $msg);
		$msg = preg_replace("'\[sp=(.*?)\](.*?)\[/sp\]'si", '<span style="border-bottom: 1px dotted #f00;" title="did you mean: \\1">\\2</span>', $msg);
		$msg = preg_replace("'\[abbr=(.*?)\](.*?)\[/abbr\]'si", '<span style="border-bottom: 1px dotted;" title="\\1">\\2</span>', $msg);
		// Old spoiler tag
		//$msg = str_replace('[spoiler]','<div class="fonts pstspl2"><b>Spoiler:</b><div class="pstspl1">', $msg);
		//$msg = str_replace('[/spoiler]','</div></div>', $msg);
		// New spoiler tag
		$msg = str_replace('[spoiler]','<label class="spoiler spoiler-b"><div class="spoiler-label"></div><input type="checkbox"><div class="hidden"><div>', $msg);
		$msg = str_replace('[/spoiler]','</div></div></label>', $msg);
		$msg = str_replace('[spoileri]','<label class="spoiler"><span class="spoiler-label"></span><input type="checkbox"><span class="hidden"><span>', $msg);
		$msg = str_replace('[/spoileri]','</span></span></label>', $msg);
	
		// New version of the tag inspired by Xen-Foro's
		// [attach="1" type="<...>" name="<...>" hash="<...>" (props)]
		$msg = preg_replace_callback("'\[attach=\"(\w+)\" type=\"(full|embed|thumb|url)\"( name=\"(.*?)\")?( hash=\"(\w+)\")?(.*?)]'si", function ($text) {
			// 1 -> file id
			// 2 -> bbcode type
			// 4 -> filename or alt text
			// 6 -> temporary file hash
			// 7 -> additional attributes, sent as-is
			
			$url = "download.php?id={$text[1]}";
			if ($text[6])
				$url .= "&hash={$text[6]}";
			$imgurl = $url;
			
			switch ($text[2]) {
				case 'thumb':
					$imgurl .= "&t=1";
				case 'full':
					return "<a href='{$url}' target='_blank'><img alt=\"{$text[4]}\" src='{$imgurl}' class='imgtag'{$text[7]}/></a>";
				case 'url':
					return "<a href='{$url}' target='_blank'{$text[7]}>{$text[4]}</a>";
				case 'embed':
					return "[video]{$url}[/video]"; // will be handled later
			}
		}, $msg);
	
		$msg = preg_replace("'\[(b|i|u|s)\]'si",'<\\1>', $msg);
		$msg = preg_replace("'\[/(b|i|u|s)\]'si",'</\\1>', $msg);
		$msg = preg_replace("'\[img(.*?)\](.*?)\[/img\]'si", '<img class="imgtag" \\1 src="\\2">', $msg);
		$msg = preg_replace("'\[url\](.*?)\[/url\]'si", '<a href=\\1>\\1</a>', $msg);
		$msg = preg_replace("'\[url=(.*?)\](.*?)\[/url\]'si", '<a href=\\1>\\2</a>', $msg);
		

		
		$msg = preg_replace("'\[video\](.*?)\[/video\]'si", '<video src="\\1" width="640" controls loop>Video not supported &mdash; <a href="\\1">download</a></video>', $msg);
		
	}

	do {
		$msg	= preg_replace("/<(\/?)t(able|h|r|d)(.*?)>(\s+?)<(\/?)t(able|h|r|d)(.*?)>/si",
				"<\\1t\\2\\3><\\5t\\6\\7>", $msg, -1, $replaced);
	} while ($replaced >= 1);

	// Comment display
	if ($hacks['comments']) {
		$msg = str_replace("<!--", '<span style="color:#80ff80">&lt;!--', $msg);
		$msg = str_replace("-->", '--&gt;</span>', $msg);
	}

	// Cheap hack but convenient (it shouldn't be here)
	if (!$nosbr) sbr(0, $msg);

	return $msg;
}

/*
	dobreadcrumbs: create the navigation links at the top of the pagefooter
	$set: array in <label> => <link> format, where the if the link is NULL no link is printed
	$right: HTML printed on the right side
*/
function dobreadcrumbs($set, $right = "") {
	global $config;
	$set[count($set)-1][1] = null; // Last item never displays the URL
	$out = "<a href='index.php'>{$config['board-name']}</a>";
	foreach ($set as $link) {
		if ($link[1] !== NULL) {
			$out .= " - <a href='{$link[1]}'>".htmlspecialchars($link[0])."</a>";
		} else {
			$out .= " - ".htmlspecialchars($link[0]);
		}
	}
	return "<table class='font w'><tr><td>{$out}</td><td class='fonts right'>{$right}</td></tr></table>";
}



function doforumlist($id, $name = '', $shownone = ''){
	global $loguser,$sql;
	
	if (!$name) {
		$forumlinks = "
		<table>
			<tr>
				<td class='font'>Forum jump: </td>
				<td>
					<form>
						<select onChange='parent.location=\"forum.php?id=\"+this.options[this.selectedIndex].value'>
		";
	}
	else {
		$forumlinks = "";
	}
	
	// (`c.minpower` <= $power OR `c.minpower` <= 0) is not really necessary but whatever
	$forums = $sql->query("
		SELECT f.id, f.title, f.catid, f.hidden, c.name catname
		FROM forums f
		
		LEFT JOIN categories c ON f.catid = c.id
		
		WHERE 	(c.minpower <= {$loguser['powerlevel']} OR !c.minpower)
			AND (f.minpower <= {$loguser['powerlevel']} OR !f.minpower)
			AND (!f.hidden OR {$loguser['powerlevel']} >= 2 OR f.id = $id)
			AND !ISNULL(c.id)
			AND (!f.login OR {$loguser['id']})
			
		ORDER BY f.catid, f.forder, f.id
	");
	
	$prev 	= NULL;	// In case the current forum is in an invalid category, the non-existing category name won't be printed
	
	while ($forum = $sql->fetch($forums)) {
		// New category
		if ($prev != $forum['catid']) {
			$forumlinks .= "</optgroup><optgroup label=\"".escape_attribute($forum['catname'])."\">";
			$prev = $forum['catid'];
		}
		
		if ($forum['hidden']) {
			$forum['title'] = "({$forum['title']})";
		}
		
		$forumlinks .= "<option value={$forum['id']}".($forum['id'] == $id ? ' selected' : '').">".htmlspecialchars($forum['title'])."</option>";
	}
	
	// Multi-use forum list
	if ($name) {
		if ($shownone) $forumlinks = "<option value=0>$shownone</option>$forumlinks";
		return "<select name='$name'>$forumlinks</select>";
	}
	$forumlinks .= "	</optgroup>
					</select>
				</form>
			</td>
		</tr>
	</table>";
	
	return $forumlinks;
}

const SL_SHOWSPECIAL = 0b1;
const SL_SHOWNONE    = 0b10;
const SL_SHOWUSAGE   = 0b100;
function doschemelist($sel = 0, $name = 'scheme', $flags = 0){
	global $sql, $loguser;

	$sortmode = $loguser['schemesort'] ? "name" : "ord";
	$showcats = true; //$loguser['showschemecats'];
	
	// With scheme categories introduced...
	// TODO: Should the special flag just be removed entirely?
	$schemeq = "
		SELECT s.id, s.name, s.special, s.cat, c.title cat_title {usgFields}
		FROM schemes s
		LEFT JOIN schemes_cat c ON s.cat = c.id
		{usgJoin}
		WHERE s.id = '{$sel}' OR (".($flags & SL_SHOWSPECIAL ? "" : "s.special = 0 AND")." 
		      (!s.minpower OR s.minpower <= {$loguser['powerlevel']})
		  AND (!c.minpower OR c.minpower <= {$loguser['powerlevel']}))
		{usgGroup}
		ORDER BY ".($showcats ? "c.ord, " : "")."s.{$sortmode}, s.id
	";
	
	// Scheme usage stats, now part of the function
	if ($flags & SL_SHOWUSAGE) {
		$schemeq = strtr($schemeq, [
			"{usgFields}" => ", COUNT(u.scheme) used",
			"{usgJoin}"   => "LEFT JOIN users u ON s.id = u.scheme",
			"{usgGroup}"  => "GROUP BY s.id",
		]);
	} else {
		$schemeq = strtr($schemeq, [
			"{usgFields}" => "",
			"{usgJoin}"   => "",
			"{usgGroup}"  => "",
		]);
	}
	
	$scheme[$sel] = "selected";
	
	$input 	  = "";
	if (!$showcats)
		$input = "<optgroup label=\"Schemes\">";
	
	
	$last_cat = 0;
	$schemes = $sql->query($schemeq);
	while($x = $sql->fetch($schemes)){
		if ($showcats && $last_cat != $x['cat']) {
			$last_cat = $x['cat'];
			$input .= "</optgroup><optgroup label=\"{$x['cat_title']}\">";
		}
		$input	.= ""
			."<option value='{$x['id']}' ".filter_string($scheme[$x['id']]).">"
			.($x['special'] ? "*" : "").htmlspecialchars($x['name']).($flags & SL_SHOWUSAGE ? " ({$x['used']})" : "")
			."</option>";
	}
	return "<select name='$name'>".($flags & SL_SHOWNONE ? "<option value='' ".filter_string($scheme[null]).">None</option>" : "")."$input</optgroup></select>";
}
// logic for "parsing" the option from the above function when the "none" option is allowed
function get_scheme_opt(&$var) { return $var === "" ? null : (int)$var; }

// When it comes to this kind of code being repeated across files...
function dothreadiconlist($iconid = NULL, $customicon = '') {
	// Check if we have selected one of the default thread icons
	$posticons = file('posticons.dat');
	
	if (isset($iconid) && $iconid != -1)
		$selected = trim($posticons[$iconid]);
	else
		$selected = trim($customicon);
	
	
	$customicon = $selected;
	
	$posticonlist = "";
	
	for ($i = 0; isset($posticons[$i]);) {
		
		$posticons[$i] = trim($posticons[$i]);
		// Does the icon match?
		if ($selected == $posticons[$i]){
			$checked    = "checked='1'";
			$customicon	= "";	// If so, blank out the custom icon
		} else {
			$checked    = "";
		}

		$posticonlist .= "<label class='thread-icon-sel'><input type='radio' name='iconid' value='$i' $checked><img src=\"".escape_attribute($posticons[$i])."\"></label>";

		$i++;
		if($i % 10 == 0) $posticonlist .= '<br>';
	}

	// Blank or set to None?
	if (!$selected || $iconid == -1) $checked = 'checked=1';
	
	$posticonlist .= 	"<br>".
						"<input type=radio name=iconid value=-1 $checked>&nbsp; None &nbsp; &nbsp;".
						"Custom: <input type='text' name=custposticon VALUE=\"".htmlspecialchars($customicon)."\" SIZE=40 MAXLENGTH=100>";
	
	return $posticonlist;
}

const FILTER_NONE = 0;
const FILTER_ATTR = 1;
const FILTER_HTML = 2;
function row_display($headers, $values, $strings, $sel = NULL, $page = 0, $limit = -1, $rowcount = 0) {
	static $setid = 0;
	
	$colspan  = count($headers) + 2; // + Edit selection
	
	//-- 
	// Generate header text
	// And fix the colspan to be correct (account for non-displayed fields in the row list)
	$header_txt = "";
	foreach ($headers as $key => $x) {
		if (!isset($x['nodisplay'])) {
			$header_txt .= "<td class='tdbgh center b'".(isset($x['style']) ? " style=\"{$x['style']}\"" : "").">{$x['label']}</td>";
		} else {
			--$colspan;
		}
	}
	//--
	// Main row display
	$i = -1;
	$row_txt = "";
	foreach ($values as $id => $row) {
		$cell = (++$i % 2) + 1;
		$row_txt .= "
		<tr class='th' id='row{$setid}_{$id}'>
			<td class='tdbg{$cell} center b'>
				<input type='checkbox' name='del[]' value='{$id}'>
			</td>
			<td class='tdbg{$cell} center fonts'>
				<a href='{$strings['base-url']}&id={$id}' class='editCtrl_{$setid}' data-id='{$id}'>Edit</a>
			</td>";
		foreach ($headers as $key => $x) {
			if (!isset($x['nodisplay'])) {
				$val = $row[$key];
				if (isset($x['filter'])) {
					switch ($x['filter']) {
						case 'html': $val = htmlspecialchars($val); break;
						case 'xss': $val = xssfilters($val); break;
					}
				}
				$row_txt .= "<td class='tdbg{$cell} center'>{$val}</td>";
			}
		}
		$row_txt .= "
		</tr>";
	}
	//--
	$pagectrl = "";
	if ($limit > 0 && $rowcount > $limit) {
		$pagectrl = "
		<tr class='rh'>
			<td class='tdbg2 center fonts' colspan='{$colspan}'>
				".pagelist("?type={$_GET['type']}&fpp={$_GET['fpp']}", $rowcount, $limit)."
				 &mdash; <a href='?type={$_GET['type']}&fpp=-1'>Show all</a>
			</td>
		</tr>";
	}
	//--
	// Edit window
	$edit_txt   = "";
	if ($sel !== NULL) {
		
		// Before doing the enchilada, check if the value exists to set the default.
		if (!isset($values[$sel])) {
			$sel = -1;
			$action_name = "Creating a new {$strings['element']}";
		} else {
			$action_name = "Editing {$strings['element']} #{$sel}";
		}
		
		foreach ($headers as $key => $x) {
			if (isset($x['type'])) {
				
				$value = isset($values[$sel][$key]) ? $values[$sel][$key] : filter_string($x['default']);
				
				$editcss = isset($x['editstyle']) ? " style=\"{$x['editstyle']}\"" : "";
				switch ($x['type']) {
					case 'text':
					case 'color':
						$input = "<input type='{$x['type']}' name='{$key}' value=\"".htmlspecialchars($value)."\"{$editcss}>";
						break;
					case 'checkbox':
						$input = "<label><input type='checkbox' name='{$key}' value='1'".($value ? " checked" : "")."{$editcss}> {$x['editlabel']}</label>";
						break;
					case 'radio':
						$ch[$value] = "checked";
						$input = "";
						foreach ($x['choices'] as $xk => $xv)
							$input .= "<label><input name='{$key}' type='radio' value=\"{$xk}\" ".filter_string($ch[$xv]).">&nbsp;{$xv}</label>&nbsp; &nbsp; ";
						unset($ch);
						break;
					case 'select':
						$ch[$value] = "selected";
						$input = "";
						foreach ($x['choices'] as $xk => $xv)
							$input .= "<label><input name='{$key}' type='radio' value=\"{$xk}\" ".filter_string($ch[$xv]).">&nbsp;{$xv}</label>&nbsp; &nbsp; ";
						unset($ch);
						break;
										
				}
				
				$edit_txt .= "
				<tr class='rh'>
					<td class='tdbg1 center b'>{$x['label']}:</td>
					<td class='tdbg2'>{$input}</td>
				</tr>";
			}
		}
	}
	
	//--
	$css = "";
	if (!$setid) {
		$css = "
		<style type='text/css'>
			.rh {height: 19px}
			.nestedtable-container {
				padding: 0px;
				border-bottom: 0px;
				border-right: 0px;
			}
			.nestedtable-container > .sidebartable {
				border-left: 0px;
				border-top: 0px;
				height: 100%;
			}
			.nestedtable {
				border: 0px;
				height: 100%;
			}
		</style>";
	}
	
	//--
	// TODO: JS code for the alternate editor
	$js = "";
	/*
	if (!$setid) {
		$js = include_js("js/roweditor.js", true);
	}
	$headjson = json_encode($headers);
	*/
	
	++$setid;
	//--
	
	return "{$css}
	<table class='table'>
	<!-- <tr><td class='tdbgh center b' colspan='{$colspan}'>xxx - yyy</td></tr> -->
	".($edit_txt ? "
	<tr>
		<td class='tdbg2 nestedtable-container' colspan='{$colspan}'>
			<table class='table nestedtable'>
				<tr class='rh'><td class='tdbgh center b' colspan='2'>{$action_name}</td></tr>
				{$edit_txt}
				<tr class='rh'>
					<td class='tdbg1 center b' style='width: 150px'>&nbsp;</td>
					<td class='tdbg2'>
						<input type='submit' name='submit' value='Save and continue'> &nbsp; <input type='submit' name='submit2' value='Save and close'>
					</td>
				</tr>
				<tr><td class='tdbg2' colspan='2'></td></tr>			
			</table>
		</td>
	</tr>
	" : "")."
	
	<tr class='rh'>
		<td class='tdbgh center b' style='width: 30px'></td>
		<td class='tdbgh center b' style='width: 50px'>#</td>
		{$header_txt}
	</tr>
	{$row_txt}
	{$pagectrl}
	
	<tr class='rh'>
		<td class='tdbgc center' colspan='{$colspan}'>
			<input type='submit' style='height: 16px; font-size: 10px; float: left' name='setdel' value='Delete selected'>
			".auth_tag()."{$js}
			<a href=\"{$strings['base-url']}&id=-1\">&lt; Add a new {$strings['element']} &gt;</a>
		</td>
	</tr>
	</table>";
}

function getrank($rankset, $title, $posts, $powl, $bandate = NULL){
	global $hacks, $sql;
	$rank	= "";
	if ($rankset == 255) {   //special code for dots
		if (!$hacks['noposts']) {
			// Dot values - can configure
			$pr[5] = 5000;
			$pr[4] = 1000;
			$pr[3] =  250;
			$pr[2] =   50;
			$pr[1] =   10;

			if ($rank) $rank .= "<br>";
			$postsx = $posts;
			
			for ($i = max(array_keys($pr)); $i !== 0; --$i) {
				$dotnum[$i] = floor($postsx / $pr[$i]);		
				$postsx = $postsx - $dotnum[$i] * $pr[$i];	// Posts left
			}
			
			foreach($dotnum as $dot => $num) {
				for ($x = 0; $x < $num; ++$x) {
					$rank .= "<img src='images/dot". $dot .".gif' align='absmiddle'>";
				}
			}
			if ($posts >= 10) $rank = floor($posts / 10) * 10 ." ". $rank;
		}
	}
	else if ($rankset) {
		$posts %= 10000;
		$rank = $sql->resultq("
			SELECT text FROM ranks
			WHERE num <= $posts	AND rset = $rankset
			ORDER BY num DESC
			LIMIT 1
		", 0, 0, mysql::USE_CACHE);
	}

	$powerranks = array(
		-2 => 'Permabanned',
		-1 => 'Banned',
		//1  => '<b>Staff</b>',
		2  => '<b>Moderator</b>',
		3  => '<b>Administrator</b>'
	);

	// Separator
	if($rank && (in_array($powl, $powerranks) || $title)) $rank.='<br>';

	if($title)
		$rank .= xssfilters($title);
	elseif (in_array($powl, $powerranks))
		$rank .= filter_string($powerranks[$powl]);
		
	// *LIVE* ban expiration date
	if ($bandate && $powl == -1) {
		$rank .= "<br>Banned until ".printdate($bandate)."<br>Expires in ".timeunits2($bandate-time());
	}

	return $rank;
}
/* there's no gunbound rank
function updategb() {
	global $sql;
	$hranks = $sql->query("SELECT posts FROM users WHERE posts>=1000 ORDER BY posts DESC");
	$c      = mysql_num_rows($hranks);

	for($i=1;($hrank=$sql->fetch($hranks)) && $i<=$c*0.7;$i++){
		$n=$hrank[posts];
		if($i==floor($c*0.001))    $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=3%'");
		elseif($i==floor($c*0.01)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=4%'");
		elseif($i==floor($c*0.03)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=5%'");
		elseif($i==floor($c*0.06)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=6%'");
		elseif($i==floor($c*0.10)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=7%'");
		elseif($i==floor($c*0.20)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=8%'");
		elseif($i==floor($c*0.30)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=9%'");
		elseif($i==floor($c*0.50)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=10%'");
		elseif($i==floor($c*0.70)) $sql->query("UPDATE ranks SET num=$n WHERE rset=3 AND text LIKE '%=11%'");
	}
}
*/

/*
	valid_user: return the ID of the user if it's valid; 0 otherwise
	$user - user id or name
*/
function valid_user($user) {
	global $sql;
	if (!$user) {
		return 0;
	} else if (is_numeric($user)) {
		return (int) $sql->resultq("SELECT id FROM users WHERE id = '{$user}'");
	} else {
		return (int) $sql->resultp("SELECT id FROM users WHERE name = ?", [$user]);
	}
}

function checkuser($name, $pass) {
	global $hacks, $sql;

	if (!$name) return -1;
	//$sql->query("UPDATE users SET password = '".getpwhash($pass, 1)."' WHERE id = 1");
	$user = $sql->fetchp("SELECT id, password FROM users WHERE name = ?", [$name]);

	if (!$user) return -2;
	
	//if ($user['password'] !== getpwhash($pass, $user['id'])) {
	if (!password_verify(sha1($user['id']).$pass, $user['password'])) {
		// Also check for the old md5 hash, allow a login and update it if successful
		// This shouldn't impact security (in fact it should improve it)
		if (!$hacks['password_compatibility'])
			return -3;
		else {
			if ($user['password'] === md5($pass)) { // Uncomment the lines below to update password hashes
				$sql->query("UPDATE users SET `password` = '".getpwhash($pass, $user['id'])."' WHERE `id` = '$user[id]'");
				report_send(IRC_ADMIN, xk(3)."Password hash for ".xk(9)."{$name}".xk(3)." (uid ".xk(9).$user['id'].xk(3).") has been automatically updated.");
			}
			else return -4;
		}
	}
	
	return $user['id'];
}

function create_verification_hash($n,$pw) {
	$ipaddr = explode('.', $_SERVER['REMOTE_ADDR']);
	$vstring = 'verification IP: ';

	$tvid = $n;
	while ($tvid--)
		$vstring .= array_shift($ipaddr) . "|";

	// don't base64 encode like I do on my fork, waste of time (honestly)
	return $n . hash('sha256', $pw . $vstring);
}

function generate_token($div = TOKEN_MAIN, $extra = "") {
	global $config, $loguser; // adding back $_SERVER['REMOTE_ADDR'] to here for now
	return hash('sha256', $loguser['name'] . $_SERVER['REMOTE_ADDR'] . $config['salt-string'] . $div . $loguser['password']);
}

function check_token(&$var, $div = TOKEN_MAIN, $extra = "") {
	if (!$var) errorpage("No token sent, doofus.");
	$res = (trim($var) == generate_token($div, $extra));
	if (!$res) errorpage("Invalid token.");
}

function auth_tag($div = TOKEN_MAIN, $field = 'auth') {
	return '<input type="hidden" name="'.$field.'" value="'.generate_token($div).'">';
}

function getpwhash($pass, $id) {
	return password_hash(sha1($id).$pass, PASSWORD_BCRYPT);
}
/*
function shenc($str){
	$l=strlen($str);
	for($i=0;$i<$l;$i++){
		$n=(308-ord($str[$i]))%256;
		$e[($i+5983)%$l]+=floor($n/16);
		$e[($i+5984)%$l]+=($n%16)*16;
	}
	for($i=0;$i<$l;$i++) $s.=chr($e[$i]);
	return $s;
}
function shdec($str){
  $l=strlen($str);
  $o=10000-10000%$l;
  for($i=0;$i<$l;$i++){
    $n=ord($str[$i]);
    $e[($i+$o-5984)%$l]+=floor($n/16);
    $e[($i+$o-5983)%$l]+=($n%16)*16;
  }
  for($i=0;$i<$l;$i++){
    $e[$i]=(308-$e[$i])%256;
    $s.=chr($e[$i]);
  }
  return $s;
}
function fadec($c1,$c2,$pct) {
  $pct2=1-$pct;
  $cx1[r]=hexdec(substr($c1,0,2));
  $cx1[g]=hexdec(substr($c1,2,2));
  $cx1[b]=hexdec(substr($c1,4,2));
  $cx2[r]=hexdec(substr($c2,0,2));
  $cx2[g]=hexdec(substr($c2,2,2));
  $cx2[b]=hexdec(substr($c2,4,2));
  $ret=floor($cx1[r]*$pct2+$cx2[r]*$pct)*65536+
	 floor($cx1[g]*$pct2+$cx2[g]*$pct)*256+
	 floor($cx1[b]*$pct2+$cx2[b]*$pct);
  $ret=dechex($ret);
  return $ret;
}
*/

function getuserlink($u = NULL, $id = 0, $urlclass = '', $useicon = false) {
	global $sql, $loguser, $userfields;
	
	if (!$u) {
		if ($id == $loguser['id']) {
			$u = $loguser;
		} else {
			$u = $sql->fetchq("SELECT $userfields FROM users u WHERE id = $id", PDO::FETCH_ASSOC, mysql::USE_CACHE);
		}
	} else if (!$id) {
		$id = $u['id'];
	}
	
	// When the user is null it typically means the user has been deleted.
	// Print this so we don't just end up with a blank link.
	if (!$u || !$id) {
		return "<span style='color: #FF0000'><b>[Deleted user]</b></span>";
	}
	

	
	$username       = $u['displayname'] ? $u['displayname'] : $u['name'];
	$alsoKnownAs	= ($u['aka'] && $u['aka'] != $username) ? " title=\"Also known as: ".htmlspecialchars($u['aka'])."\"" : '';
	
	$username       = htmlspecialchars($username, ENT_NOQUOTES);
	
	if ($u['namecolor']) {
		if ($u['namecolor'] != 'rnbow' && is_birthday($u['birthday'])) { // Don't calculate birthday effect again
			$namecolor = 'rnbow';
		} else {
			$namecolor = $u['namecolor'];
		}
	} else {
		$namecolor = "";
	}
	
	$namecolor		= getnamecolor($u['sex'], $u['powerlevel'], $u['namecolor']);
	
	$minipic		= $useicon ? get_minipic($id, filter_string($u['minipic'])) : "";
	
	return "{$minipic}<a style='color:#{$namecolor}' class='{$urlclass} nobr' href='profile.php?id={$id}'{$alsoKnownAs}>{$username}</a>";
}

function getnamecolor($sex, $powl, $namecolor = ''){
	global $nmcol, $x_hacks;
	
	//--
	// stop the page execution (in debug mode, at least) as soon as this shit happens
	static $lolwtf = false;
	if ($nmcol === null && !$lolwtf) {
		global $config;
		$lolwtf = true;
		$errormsg = "Attempted to use an uninitialized name colors array. This probably happened because this function was called before pageheader().";
		if ($config['always-show-debug'])
			throw new Exception($errormsg);
		else
			trigger_error($errormsg, E_USER_WARNING);
	}
	//--
	
	// don't let powerlevels above admin have a blank color
	$powl = min(3, $powl);
	
	// Force rainbow effect on everybody
	if ($x_hacks['rainbownames']) $namecolor = 'rnbow';
	
	if ($powl < 0) // always dull drab banned gray.
		$output = $nmcol[0][$powl];
	else if ($namecolor) {
		switch ($namecolor) {
			case 'rnbow':
				// RAINBOW MULTIPLIER
				$stime = gettimeofday();
				// slowed down 5x
				$h = ((int)($stime['usec']/25) % 600);
				if ($h<100) {
					$r=255;
					$g=155+$h;
					$b=155;
				} elseif($h<200) {
					$r=255-$h+100;
					$g=255;
					$b=155;
				} elseif($h<300) {
					$r=155;
					$g=255;
					$b=155+$h-200;
				} elseif($h<400) {
					$r=155;
					$g=255-$h+300;
					$b=255;
				} elseif($h<500) {
					$r=155+$h-400;
					$g=155;
					$b=255;
				} else {
					$r=255;
					$g=155;
					$b=255-$h+500;
				}
				$output = substr(dechex($r*65536+$g*256+$b),-6);
				break;
			case 'random':
				$nc 	= mt_rand(0,0xffffff);
				$output = str_pad(dechex($nc), 6, "0", STR_PAD_LEFT);
				break;
			case 'time':
				$z 	= max(0, 32400 - (mktime(22, 0, 0, 3, 7, 2008) - time()));
				$c 	= 127 + max(floor($z / 32400 * 127), 0);
				$cz	= str_pad(dechex(256 - $c), 2, "0", STR_PAD_LEFT);
				$output = str_pad(dechex($c), 2, "0", STR_PAD_LEFT) . $cz . $cz;
				break;
			default:
				$output = $namecolor;
				break;
		}
	}
	else $output = $nmcol[$sex][$powl];
	
	/* old sex-dependent name color 
	switch ($sex) {
		case 3:
			//$stime=gettimeofday();
			//$rndcolor=substr(dechex(1677722+$stime[usec]*15),-6);
			//$namecolor .= $rndcolor;
			$nc = mt_rand(0,0xffffff);
			$output = str_pad(dechex($nc), 6, "0", STR_PAD_LEFT);
			break;
			
		case 4:
			$namecolor .= "ffffff"; break;
			
		case 5:
			$z = max(0, 32400 - (mktime(22, 0, 0, 3, 7, 2008) - time()));
			$c = 127 + max(floor($z / 32400 * 127), 0);
			$cz	= str_pad(dechex(256 - $c), 2, "0", STR_PAD_LEFT);
			$output = str_pad(dechex($c), 2, "0", STR_PAD_LEFT) . $cz . $cz;
			break;
			
		case 6:
			$namecolor .= "60c000"; break;
		case 7:
			$namecolor .= "ff3333"; break;
		case 8:
			$namecolor .= "6688aa"; break;
		case 9:
			$namecolor .= "cc99ff"; break;
		case 10:
			$namecolor .= "ff0000"; break;
		case 11:
			$namecolor .= "6ddde7"; break;
		case 12:
			$namecolor .= "e2d315"; break;
		case 13:
			$namecolor .= "94132e"; break;
		case 14:
			$namecolor .= "ffffff"; break;
		case 21: // Sofi
			$namecolor .= "DC143C"; break;
		case 22: // Nicole
			$namecolor .= "FFB3F3"; break;
		case 23: // Rena
			$namecolor .= "77ECFF"; break;
		case 24: // Adelheid
			$namecolor .= "D2A6E1"; break;
		case 41:
			$namecolor .= "8a5231"; break;
		case 42:
			$namecolor .= "20c020"; break;
		case 99:
			$namecolor .= "EBA029"; break;
		case 98:
			$namecolor .= $nmcol[0][3]; break;
		case 97:
			$namecolor .= "6600DD"; break;
			
		default:
			$output = $nmcol[$sex][$powl];
			break;
	}*/

	return $output;
}

// Banner 0 = automatic ban
function ipban($ip, $reason, $ircreason = NULL, $destchannel = IRC_STAFF, $expire = 0, $banner = 0) {
	global $sql;
	if ($expire > 0) {
		$expire = time() + 3600 * $expire;
	}
	$sql->queryp("
		INSERT INTO `ipbans` (`ip`,`reason`,`date`,`banner`,`expire`) 
		VALUES(?,?,?,?,?) 
		ON DUPLICATE KEY UPDATE 
			`reason` = VALUES(`reason`),
			`date`   = VALUES(`date`),
			`banner` = VALUES(`banner`),
			`expire` = VALUES(`expire`)
		", [$ip, $reason, time(), $banner, $expire]);
	if ($ircreason !== NULL) {
		report_send($destchannel, $ircreason);
	}
}

function ipban_edit($sourceip, $ip, $reason, $ircreason = NULL, $destchannel = IRC_STAFF, $expire = 0, $banner = 0) {
	global $sql;
	// UPDATE
	$values = array(
		'ip'     => $ip,
		'reason' => $reason,
		'banner' => $banner,
	);
	if ($expire >= 0) { // Ignore expired bans
		$values['expire'] = $expire ? time() + 3600 * $expire : 0;	
	}
	$phs = mysql::setplaceholders($values);
	
	// WHERE
	$values['sourceip'] = $sourceip;
	
	$sql->queryp("UPDATE `ipbans` SET {$phs} WHERE `ip` = :sourceip", $values);
	if ($ircreason !== NULL) {
		report_send($destchannel, $ircreason);
	}
}
function ipban_exists($ip) {
	global $sql;
	return $sql->resultp("SELECT COUNT(*) FROM ipbans WHERE ip = ?", [$ip]);
}

function userban($id, $reason = "", $ircreason = NULL, $expire = false, $permanent = false){
	global $sql;
	
	$new_powl		= $permanent ? -2 : -1;
	$expire         = $expire ? time() + 3600 * $expire : 0;
			
	$res = $sql->queryp("
		UPDATE users SET 
		    `powerlevel_prev` = `powerlevel`, 
		    `powerlevel`      = ?, 
		    `title`           = ?,
		    `ban_expire`      = ?,
		WHERE id = ?", [$new_powl, $reason, $expire, $id]);
		
	if ($ircreason !== NULL){
		report_send(IRC_STAFF, $ircreason);
	}
}

function forumban($forum, $user, $reason = "", $ircreason = NULL, $destchannel = IRC_STAFF, $expire = 0, $banner = 0) {
	global $sql;
	
	if ($expire > 0) {
		$expire = time() + 3600 * $expire;
	}
			
	$sql->queryp("
		INSERT INTO forumbans (user, forum, date, banner, expire, reason)
		VALUES(?,?,?,?,?,?)", [$user, $forum, time(), $banner, $expire, $reason]);
		
	if ($ircreason !== NULL){
		report_send($destchannel, $ircreason);
	}
}

function forumban_edit($source, $forum, $user, $reason = "", $ircreason = NULL, $destchannel = IRC_STAFF, $expire = 0) {
	global $sql;
	
	$values = array(
		'user'   => $user,
		'reason' => $reason,
	);
	if ($expire >= 0) {
		$values['expire'] = $expire ? time() + 3600 * $expire : 0;	
	}
	$sql->queryp("UPDATE forumbans SET ".mysql::setplaceholders($values)." WHERE id = {$source}", $values);
	
	if ($ircreason !== NULL) {
		report_send($destchannel, $ircreason);
	}
}

function check_forumban($forum, $user) {
	if ($wban = is_banned($forum, $user)) {
		$banner = ($wban['banner'] ? " by ".getuserlink($wban, $wban['uid']) : "");
		$reason = ($wban['reason'] ? xssfilters($wban['reason']) : "<i>No reason given.</i>");
		errorpage("Sorry, but you have been banned{$banner} from posting in this forum.<br/>Reason: {$reason}");
	}
}

function is_banned($forum, $user) {
	global $sql, $userfields;
	return $sql->fetchq("
		SELECT f.id, f.banner, f.expire, f.reason, $userfields uid
		FROM forumbans f
		LEFT JOIN users u ON f.banner = u.id
		WHERE f.user = {$user} AND f.forum = {$forum} AND (!f.expire OR f.expire > ".time().")
	");
}

function print_ban_time($ban) {
	if (!$ban['expire']) {
		return "Permanent";
	} else {
		return ($ban['expire'] < time() ? "Expired" : timeunits2($ban['expire'] - time()))
		       ."<br><small>(".printdate($ban['expire'])/*." - ".timeunits2($ban['expire'] - $ban['date'])*/.")</small>";
	}
}

function onlineusers($forum = NULL, $thread = NULL){
	global $loguser, $config, $meta, $sql, $userfields, $isadmin, $ismod, $numon;

	// compat hax
	if ($config['onlineusers-on-thread']) {
		$l = "'<i>";
		$r = "</i>'";
	} else {
		$thread = NULL; // Force disable thread bar mode
		$l = $r = "";
	}
	
	if ($thread) {
		$check     = " AND lastthread = {$thread['id']}";
		$update    = "lastforum = {$forum['id']}, lastthread = {$thread['id']}"; // For online users update
		$location  = "reading {$l}" . htmlspecialchars($thread['title']) . $r; // "users currently in <thread>"
	} else if ($forum) {
		$check     = " AND lastforum = {$forum['id']}";
		$update    = "lastforum = {$forum['id']}, lastthread = 0";
		$location  = "in {$l}" . htmlspecialchars($forum['title']) . $r;  // "users currently in <forum>"
	} else {
		$check     = "";
		$update    = "lastforum = 0, lastthread = 0";
		$location  = "online"; // "users currently online"
	}
	
	
	if ($loguser['id']) {
		if (!filter_bool($meta['notrack'])) {
			$sql->query("UPDATE users  SET {$update} WHERE id = {$loguser['id']}");
		}
	} else {
		$sql->query("UPDATE guests SET {$update} WHERE ip = '{$_SERVER['REMOTE_ADDR']}'");
	}
	
	$onlinetime		= time() - 300; // 5 minutes
	$onusers		= $sql->query("
		SELECT $userfields, hideactivity, (lastactivity <= $onlinetime) nologpost
		FROM users u
		WHERE (lastactivity > $onlinetime OR lastposttime > $onlinetime){$check} AND (".((int) $ismod)." OR !hideactivity)
		ORDER BY name
	");
	/*
		Online users
	*/	
	$onlineusers	= "";
	for ($numon = 0; $x = $sql->fetch($onusers); ++$numon) {
		
		if ($numon) $onlineusers .= ', ';

		/* if ((!is_null($hp_hacks['prefix'])) && ($hp_hacks['prefix_disable'] == false) && int($x['id']) == 5) {
			$x['name'] = pick_any($hp_hacks['prefix']) . " " . $x['name'];
		} */
		$minipic             = get_minipic($x['id'], $x['minipic']);
		$namelink            = getuserlink($x);
		//$onlineusers        .='<nobr>';
		
		if ($x['nologpost']) // Was the user posting without using cookies?
			$namelink="($namelink)";
			
		if ($x['hideactivity'])
			$namelink="[$namelink]";		
			
		if ($minipic)
			$namelink = "$minipic $namelink";
			
		$onlineusers .= "<span class='nobr'>{$namelink}</span>";
	}
	$p = ($numon ? ':' : '.');
	$s = ($numon != 1 ? 's' : '');
	
	/*
		Online guests
	*/
	$guests = $bpt_info = "";
	if (!$isadmin) {
		// Standard guest counter view
		$numguests = $sql->resultq("SELECT COUNT(*) FROM guests	WHERE date > {$onlinetime}{$check}");
	} else {
		// Detailed view of BPT (Bot/Proxy/Tor) flags
		$onguests = $sql->query("SELECT flags FROM guests WHERE date > {$onlinetime}{$check}");
		// Fill in the proper flag counters with the proper priority
		$pts = array_fill(0, 4, 0);
		for ($numguests = 0; $x = $sql->fetch($onguests); ++$numguests) {
			if      ($x['flags'] & BPT_TOR)         $pts[2]++;
			else if ($x['flags'] & BPT_IPBANNED)    $pts[0]++;
			else if ($x['flags'] & BPT_BOT)         $pts[3]++;
			//else if ($x['flags'] & BPT_PROXY)       $pts[1]++;
		}
		// Print out the flag info
		$specinfo = array(
			'IP banned', 
			'Prox'.($pts[1] == 1 ? 'ies' : 'y'), 
			'Tor banned', 
			'bot'.($pts[3] == 1 ? '' : 's')
		);
		//$guestcat = array();
		for ($i = 0; $i < 4; ++$i) {
			if ($pts[$i]) {
				$bpt_info .= ($bpt_info !== "" ? "" : ", ")."{$pts[$i]} {$specinfo[$i]}";
			}
		}
		if ($bpt_info !== "") {
			$bpt_info = "({$bpt_info})";
		}
		
		//$guests = $numguests ? " | <nobr>$numguests guest".($numguests>1?"s":"").($guestcat ? " (".implode(",", $guestcat).")" : "") : "";
	}
	
	if ($numguests) {
		$guests = "| $numguests guest" . ($numguests > 1 ? 's' : '') . $bpt_info;
	}
	
	return "$numon user$s currently $location$p $onlineusers $guests";
}

/* WIP
$jspcount = 0;
function jspageexpand($start, $end) {
	global $jspcount;

	if (!$jspcount) {
		echo '
			<script type="text/javascript">
				function pageexpand(uid,st,en)
				{
					var elem = document.getElementById(uid);
					var res = "";
				}
			</script>
		';
	}

	$entityid = "expand" . ++$jspcount;

	$js = "#todo";
	return $js;
}
*/

function redirect($url, $msg, $delay = 1){
	global $config;
	if ($config['no-redirects'] || $delay < 0) {
		return "Go back to <a href=$url>".htmlspecialchars($msg)."</a>."; //"Click <a href=\"{$url}\">here</a> to be continue to {$msg}.";
	} else {
		return "You will now be redirected to <a href=$url>".htmlspecialchars($msg)."</a>...<META HTTP-EQUIV=REFRESH CONTENT=".max(1,$delay).";URL=$url>";
	}
}

function postradar($userid){
	global $sql, $loguser, $userfields;
	if (!$userid) return "";
	
	$race = '';

	//$postradar = $sql->query("SELECT posts,id,name,aka,sex,powerlevel,birthday FROM users u RIGHT JOIN postradar p ON u.id=p.comp WHERE p.user={$userid} ORDER BY posts DESC", MYSQL_ASSOC);
	$postradar = $sql->query("
		SELECT u.posts, $userfields
		FROM postradar p
		INNER JOIN users u ON p.comp = u.id
		WHERE p.user = $userid
		ORDER BY posts DESC
	", PDO::FETCH_ASSOC);
	
	$rows = $sql->num_rows($postradar);
	
	if ($rows) {
		$race = 'You are ';

		function cu($a,$b) {
			global $hacks;

			$dif = $a-$b['posts'];
			if ($dif < 0)
				$t = (!$hacks['noposts'] ? -$dif : "") ." behind";
			else if ($dif > 0)
				$t = (!$hacks['noposts'] ?  $dif : "") ." ahead of";
			else
				$t = ' tied with';

			$namelink = getuserlink($b);
			$t .= " {$namelink}" . (!$hacks['noposts'] ? " ({$b['posts']})" : "");
			return "<nobr>{$t}</nobr>";
		}

		// Save ourselves a query if we're viewing our own post radar
		// since we already fetch all user fields for $loguser
		if ($userid == $loguser['id'])
			$myposts = $loguser['posts'];
		else
			$myposts = $sql->resultq("SELECT posts FROM users WHERE id = $userid");

		for($i = 0; $user2 = $sql->fetch($postradar); ++$i) {
			if ($i) 					$race .= ', ';
			if ($i && $i == $rows - 1) 	$race .= 'and ';
			$race .= cu($myposts, $user2);
		}
	}
	return $race;
}

/*
	load_user: load the userdata for a specified user
	$user - user id
	$all  - loads all the data; by default it's fetched only what's necessary to create an userlink
*/
function load_user($user, $all = false) {
	global $sql, $userfields;
	if (!$user) {
		return NULL;
	} else {
		return $sql->fetchq("SELECT ".($all ? "*" : $userfields)." FROM users u WHERE u.id = '{$user}'");
	}
}

function get_ppp($low = 1, $high = 500) {
	global $loguser, $config;
	$ppp = (isset($_GET['ppp']) ? ((int) $_GET['ppp']) : (($loguser['id']) ? $loguser['postsperpage'] : $config['default-ppp']));
	return max(min($ppp, $high), $low);
}

function get_tpp($low = 1, $high = 500) {
	global $loguser, $config;
	$tpp = (isset($_GET['tpp']) ? ((int) $_GET['tpp']) : (($loguser['id']) ? $loguser['threadsperpage'] : $config['default-tpp']));
	return max(min($tpp, $high), $low);
}

function get_ipp(&$ipp, $default, $min = 1, $max = 500) {
	return ($ipp ? numrange((int)$ipp, 1, 500) : $default);
}

// get the lowest and highest values of a single key
function get_id_range($data, $key = null) {
	$single = $key !== null ? array_column($data, $key) : $data;
	return [min($single), max($single)];
}
/*
function squot($t, &$src){
	switch($t){
		case 0: $src=htmlspecialchars($src); break;
		case 1: $src=urlencode($src); break;
		case 2: $src=str_replace('&quot;','"',$src); break;
		case 3: $src=urldecode('%22','"',$src); break;
	}
  switch($t){
    case 0: $src=str_replace('"','&#34;',$src); break;
    case 1: $src=str_replace('"','%22',$src); break;
    case 2: $src=str_replace('&#34;','"',$src); break;
    case 3: $src=str_replace('%22','"',$src); break;
  }
}*/
function sbr($t, &$src) {
	if (!$src)
		return;
	$src = $t 
		? str_replace('<br>', "\n", $src) // 1
		: str_replace("\n", '<br>', $src); // 0
}
/*
function mysql_get($query){
  global $sql;
  return $sql->fetchq($query);
}
*/
/*
function sizelimitjs(){
	// where the fuck is this used?!
	return "";
  return '
	<script>
	  function sizelimit(n,x,y){
		rx=n.width/x;
		ry=n.height/y;
		if(rx>1 && ry>1){
		if(rx>=ry) n.width=x;
		else n.height=y;
		}else if(rx>1) n.width=x;
		else if(ry>1) n.height=y;
	  }
	</script>
  '; 
}*/

function loadtlayout(){
	global $loguser, $tlayout, $sql;
	$tlayout    = $loguser['layout'] ? $loguser['layout'] : 1;
	$layoutfile = $sql->resultq("SELECT file FROM tlayouts WHERE id = $tlayout");
	if (!$layoutfile) {
		errorpage("The thread layout you've been using has been removed by the administration.<br/>You need to <a href='editprofile.php'>choose a new one</a> before you'll be able to view threads.");
	}
	if (!valid_filename($layoutfile)) {
		errorpage("The thread layout you're using points to a disallowed filename and has been blocked.<br/>You need to <a href='editprofile.php'>choose a new one</a> before you'll be able to view threads.");
	}
	require "tlayouts/$layoutfile.php";
}

function errorpage($text, $redirurl = '', $redir = '', $redirtimer = 4) {
	if (!defined('HEADER_PRINTED')) pageheader();

	print "<table class='table'><tr><td class='tdbg1 center'>$text";
	if ($redir)
		print '<br>'.redirect($redirurl, $redir, $redirtimer);
	print "</table>";

	pagefooter();
}

function boardmessage($text, $title = "Message", $layout = true) {
	if ($layout && !defined('HEADER_PRINTED')) pageheader();
	print "
	<table class='table'>
		<tr><td class='tdbgh center b'>$title</td></tr>
		<tr><td class='tdbg1 center' style='padding: 1em 0;'>$text</td></tr>
	</table>";
	if ($layout) pagefooter();
}


function confirmed($key, $token = TOKEN_MAIN) {
	$auth_key = "auth_{$key}";
	
	// Check if the auth was sent
	if (isset($_POST[$auth_key])) {
		check_token($_POST[$auth_key], $token);
		return true;
	}
	return false;
}

const BTN_SUBMIT = 0;
const BTN_URL = 1;
function confirm_message($key, $text, $title = "", $form_url = "", $buttons = NULL, $token = TOKEN_MAIN) {
	$auth = auth_tag($token, "auth_{$key}");
	
	// Button geneeration
	if ($buttons !== NULL) {
		$cmd = [];
		foreach ($buttons as $k => $btn) {
			switch ($btn[0]) {
				case BTN_SUBMIT:
					// type, text, name, value
					$cmd[] = "<button type='submit' name=\"".(isset($btn[2]) ? $btn[2] : "cmch_{$key}")."\" value=\"".(isset($btn[3]) ? $btn[3] : $btn[1])."\">{$btn[1]}</button>";
					break;
				case BTN_URL:
					// type, text, link
					$cmd[] = "<a href=\"{$btn[2]}\" class='button'>{$btn[1]}</a>";
					break;
			}
		}
		$commands = implode(" - ", $cmd);
	} else {
		$commands = "<input type='submit' name='cmch_{$key}' value='Yes'> - <a href='#' class='button' onclick='window.history.go(-1); return false;'>No</a>";
	}
	
	$form_tag = $form_url ? ["<form method='POST' action='{$form_url}'>","</form>"] : ["",""];
	
	if (!defined('HEADER_PRINTED')) {
		pageheader();
	}
	
	print "
	{$form_tag[0]}
	<table class='table' style='margin: auto; width: unset'>
		".($title ? "<tr><td class='tdbgh center b'>{$title}</td></tr>" : "")."
		<tr><td class='tdbg1 center' style='padding: 1em'>{$text}</td></tr>
		<tr><td class='tdbg2 center'>{$commands}".save_vars($_POST)."{$auth}</td></tr>
	</table>
	{$form_tag[1]}";
	
	pagefooter();
}

function notAuthorizedError($thing = 'forum') {
	global $loguser;
	$rreason = ($loguser['id'] ? 'don\'t have access to it' : 'are not logged in');
	$redir   = ($loguser['id'] ? 'index.php' : 'login.php');
	$rtext   = ($loguser['id'] ? 'the index page' : 'log in (then try again)');
	errorpage("Couldn't enter this restricted {$thing}, as you {$rreason}.", $redir, $rtext);
}

function ismod($forum = 0, $user = NULL) {
	global $loguser, $sql;
	if ($user === NULL) $user = $loguser;
	if ($user['powerlevel'] > 1) return true;
	return ($forum && $sql->resultq("SELECT COUNT(*) FROM forummods WHERE forum = '{$forum}' and user = '{$user['id']}'"));
}

function can_view_forum($forum) {
	global $loguser;
	return (
		   $forum // Forum exists
		&& (!$forum['minpower'] || $loguser['powerlevel'] >= $forum['minpower']) // You are allowed to view it
		&& ($loguser['id'] || !$forum['login']) // Logged in or forum not login-restricted
	);
}
function can_view_forum_query($f = 'f') {
	global $loguser;
	if ($f) $f .= "."; // Table alias
	return "((!{$f}minpower OR {$f}minpower <= '{$loguser['powerlevel']}') AND ('{$loguser['id']}' OR !{$f}login))";
}

function can_select_scheme($id) {
	global $sql, $loguser;
	return $sql->resultq("
		SELECT COUNT(*) 
		FROM schemes s 
		LEFT JOIN schemes_cat c ON s.cat = c.id
		WHERE '{$id}' = '{$loguser['scheme']}' OR (
				(!s.minpower OR s.minpower <= {$loguser['powerlevel']})
			AND (!c.minpower OR c.minpower <= {$loguser['powerlevel']})
			AND s.id = '{$id}'
		)");
}

function admincheck() {
	global $isadmin;
	if (!$isadmin) {
		if (!defined('HEADER_PRINTED')) pageheader();
		
		?><table class='table'>
			<tr>
				<td class='tdbg1 center'>
					This feature is restricted to administrators.<br>
					You aren't one, so go away.<br>
					<?=redirect('index.php','return to the board',0)?>
				</td>
			</tr>
		</table><?php
		
		pagefooter();
	}
}

function tree_draw($cats, $sel, $catlink = null, $pad = 0) {
	$res   = "";
	foreach ($cats as $catlbl => $cat) {
		
		$hastitle = is_string($catlbl);
		if ($hastitle) {
			if (!$catlink) // no URL? (topmost level)
				$title = "<b>{$catlbl}</b>";
			else if ($catlink == $sel) // Currently selected page?
				$title = "<b><i>{$catlbl}</i></b>";
			else // Selectable page?
				$title = "<a href='{$catlink}'>{$catlbl}</a>";
			$res .= "<div style='padding-left:{$pad}px'>&bull; {$title}</div>";
			$pad += 20;
		}
		
		foreach ($cat as $link => $item) {
			if (is_array($item)) // nested list?
				$res .= tree_draw($item, $sel, $link, $pad);
			else {
				if ($link == $sel)
					$itemurl = "<b><i>{$item}</i></b>";
				else
					$itemurl = "<a href='{$link}'>{$item}</a>";
				$res .= "<div style='padding-left:{$pad}px'>&bull; {$itemurl}</div>";
			}
		}
		
		if ($hastitle) {
			$pad -= 20;
		}
	}
	
	return $res;
}

function adminlinkbar($sel = null, $args = "") {
	global $isadmin;
	if (!$isadmin) return;
	
	if (!$sel) {
		// If no selection is passed, default to the current script
		global $scriptpath;
		$sel = $scriptpath;
	}
	$sel .= $args;
	
	global $_adminlinks;
	$_adminlinks = [
		[
			'admin.php'	              => "Admin Control Panel",
//			'admin-todo.php'          => "To-do list"],
			'admin-extensions.php'    => "Extensions manager",
			'admin-reporting.php'     => "Post Reporting",
		],
		'Quick jump' => [
			'announcement.php'        => "Go to Announcements",
		],
		'Configuration' => [
			'admin-editresources.php' => [
				"Edit Resources" => [
					'admin-editresources.php?type=1'  => "Smilies",
					'admin-editresources.php?type=2'  => "Post icons",
					'admin-editresources.php?type=3'  => "Syndromes/CSS",
				],
			],
			'admin-editfilters.php'   => [
				"Edit Filters" => [
					'admin-editfilters.php?type=1'  => "Generic",
					'admin-editfilters.php?type=2'  => "URLs",
					'admin-editfilters.php?type=3'  => "HTML/CSS",
					'admin-editfilters.php?type=4'  => "Bad words",
					'admin-editfilters.php?type=5'  => "Joke/Idiocy",
					'admin-editfilters.php?type=6'  => "Hidden Smilies",
					'admin-editfilters.php?type=7'  => "Security",
					//'admin-editfilters.php?type=99' => "Test",
				],
			],
			'admin-editforums.php'    => "Edit Forum List",
			'admin-editmods.php'      => "Edit Forum Moderators",
			'admin-forumbans.php'     => "Edit Forum Bans",
			'admin-editmods.php'      => "Edit Forum Moderators",
			'admin-forumbans.php'     => "Edit Forum Bans",
			'admin-attachments.php'   => "Edit Attachments",
		],
		'Management' => [
			'admin-repair.php'        => "Repair System",
			'admin-backup.php'        => "Board Backups",
		],
//		'Security' => [
//			'admin-downloader.php'    => "?",	// coming never ever
//			'admin-showlogs.php'      => "Log Viewer",
//		],
		'IP management' => [
			'admin-ipsearch.php'      => "IP Search",
			'admin-ipbans.php'        => "IP Bans",
		],
		'User management' => [
			'admin-pendingusers.php'  => "Pending Users",
			'admin-useragents.php'    => "User Agent History",
			'admin-slammer.php'       => "EZ Ban Button",
			'admin-deluser.php'       => "Delete User",
		]
	];
	hook_use('adminlinkbar');
	
	register_pagefooter_html("</td></tr></table>");
	return "<table class='pane-table w'><tr><td class='nobr'>
<table class='table'>
	<tr><td class='tdbgh center b'>Admin Functions</td></tr>
	<tr><td class='tdbg1 left vatop' style='padding-right: 15px'>".tree_draw($_adminlinks, $sel)."</td></tr>
</table>
	</td><td class='w'>";
}

function adminlinkbar_add($catname, $contents) {
	global $_adminlinks;
	if (!isset($_adminlinks[$catname])) {
		$_adminlinks[$catname] = $contents;
	} else {
		$_adminlinks[$catname] += $contents;
	}
}
function adminlinkbar_add_item($catname, $key, $label) {
	global $_adminlinks;
	if (!isset($_adminlinks[$catname])) {
		$_adminlinks[$catname] = [$key => $label];
	} else {
		$_adminlinks[$catname][$key] = $label;
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
			`time`     = ".time().",
			`filtered` = :filtered",
		[
		 ':ipaddr'   => $_SERVER['REMOTE_ADDR'], 
		 ':url'      => $_SERVER['REQUEST_URI'],
		 ':source'   => $before,
		 ':filtered' => $after
		]
	);

}
function include_js($fn, $as_tag = false) {
	// HANDY JAVASCRIPT INCLUSION FUNCTION
	if ($as_tag) {
		// include as a <script src="..."></script> tag
		return "<script src='$fn' type='text/javascript'></script>";
	} else {
		return '<script type="text/javascript">'.file_get_contents("js/{$fn}").'</script>';
	}
}

function register_pagefooter_html($str) {
	global $footer_extra;
	if (!isset($footer_extra))
		$footer_extra = $str;
	else
		$footer_extra .= $str;
}

function register_js($fn, $async = false) {
	// No more than 1 registration/js
	static $db = [];
	if (isset($db[$fn]))
		return;
	$db[$fn] = true;
	
	global $footer_extra;
	if (!$footer_extra)
		$footer_extra = "";
	$footer_extra .= "<script src='$fn' type='text/javascript'".($async ? " async" : "")."></script>";
}


function xssfilters($data, $validate = false){
	
	$diff = false;
	$orig = $data;
	
	// https://stackoverflow.com/questions/1336776/xss-filtering-function-in-php
	// Fix &entity\n;
	$data = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $data);
	$data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
	$data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
	$data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

	$temp = $data;
	// Remove any attribute starting with "on" or xmlns
	#$data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);
	do {
		$old_data	= $data;
		$data		= preg_replace('#(<[A-Za-z][^>]*?[\x00-\x20\x2F"\'])(on|xmlns)[A-Za-z]*=([^>]*+)>#iu', '$1DISABLED_$2$3>', $data);
	} while ($old_data !== $data);
	
	// Remove javascript: and vbscript: protocols
	$data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
	$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
	$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);
	if ($data !== $temp) $diff = true;

	// Remove namespaced elements (we do not need them)
	$data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);
	
	$temp = $data;
	do {
	    // Remove really unwanted tags
	    $old_data = $data;
	    $data = preg_replace('#<(/*(?:applet|b(?:ase|gsound)|embed|frame(?:set)?|i(?:frame|layer)|layer|meta|object|script|title|xml)[^>]*+)>#i', '&lt;$1&gt;', $data);
	} while ($old_data !== $data);
	if ($data !== $temp) $diff = true;
	
	if ($diff && $validate) {
		return NULL;
	}
	
	return $data;
	
}

// prepares dofilters for multiforum mode
function prepare_filters($forums = null) {
	global $sql, $forum_filters;
	$f = $forums ? implode(",", array_map('intval', array_unique($forums))) : null;
	
	$forum_filters = $sql->fetchq("
		SELECT forum, method, source, replacement
		FROM filters
		WHERE enabled = 1 AND forum ".($f ? "IN (0,{$f})" : "= 0")."
		ORDER BY ord ASC, id ASC
	", PDO::FETCH_GROUP, mysql::FETCH_ALL | mysql::USE_CACHE);
}

function dofilters($p, $f = 0, $multiforum = false){
	global $runtime;
	
	if (!$p) return $p;	
	if (!$multiforum) { // Basically, everything except "Show posts" (of user)
		global $sql, $hacks;
		$filters = $sql->fetchq("
			SELECT method, source, replacement
			FROM filters
			WHERE enabled = 1 AND forum ".($f ? "IN (0,{$f})" : "= 0")."
			ORDER BY ord ASC, id ASC
		", PDO::FETCH_ASSOC, mysql::FETCH_ALL | mysql::USE_CACHE);
	} else {
		global $forum_filters;
		
		// No filters, somehow...
		if (!isset($forum_filters[0]) && !isset($forum_filters[$f]))
			return $p;
		
		if (!isset($forum_filters[$f]))
			$filters = $forum_filters[0];
		else if (!isset($forum_filters[0]))
			$filters = $forum_filters[$f];
		else
			$filters = array_merge($forum_filters[0], $forum_filters[$f]);
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
	
	$p = xssfilters($p);

	/*
		Unsafe BBCode, must be after xssfilters.
		Ideally this should be elsewhere...
	*/
	
	// Simple check for skipping BBCode replacements, like last time
	if (strpos($p, "[") !== false) {
		$p = preg_replace("'\[youtube\]((https?://)?(www\.)?(youtube\.com/|youtu\.be/)?(embed/|v/|watch\?v=)?)?([\w_\-]+)(\?[t|start]=(\d+))?\[/youtube\]'si", '<iframe src="https://www.youtube.com/embed/\6?start=\8" width="560" height="315" frameborder="0" allowfullscreen="allowfullscreen"></iframe>', $p);
		$p = preg_replace("'\[twitter\](\d+)\[/twitter\]'si", '<blockquote class="twitter-tweet"><a href="https://twitter.com/username/status/\1">Loading tweet...</a></blockquote>', $p, -1, $count);
		if ($count)
			register_js("https://platform.twitter.com/widgets.js", true);
		
		$p = preg_replace("'\[vine\](\w+)\[/vine\]'si", '<iframe class="vine-embed" src="https://vine.co/v/\1/embed/simple" width="600" height="600" frameborder="0"></iframe><script async src=\"//platform.vine.co/static/scripts/embed.js\" charset=\"utf-8\"></script>', $p, -1, $count);
		if ($count)
			register_js("//platform.vine.co/static/scripts/embed.js", true);
		
		// Tindeck is offline
		//$p = preg_replace("'\[tindeck\](\w+)\[/tindeck\]'si", '<a href="http://tindeck.com/listen/\1"><img src="http://tindeck.com/image/$contents/stats.png" alt="Tindeck"/></a>', $p);
		$p = preg_replace("'\[tindeck\](\w+)\[/tindeck\]'si", '<a href="http://tindeck.com/listen/\1">Tindeck</a>', $p);
		
		$p = preg_replace("'\[dailymotion\](\w+)\[/dailymotion\]'si", '<iframe frameborder="0" width="640" height="360" src="https://www.dailymotion.com/embed/video/\1" allowfullscreen="" allow="autoplay"></iframe>', $p);
		$p = preg_replace("'\[bc\](\d+)\[/bc\]'si", '<iframe style="border: 0; width: 500px; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/track=\1/size=medium/bgcol=ffffff/linkcol=0687f5/transparent=true/" seamless></iframe>', $p);
		$p = preg_replace("'\[bca\](\d+)\[/bca\]'si", '<iframe style="border: 0; width: 500px; height: 120px;" src="https://bandcamp.com/EmbeddedPlayer/album=\1/size=medium/bgcol=ffffff/linkcol=0687f5/transparent=true/" seamless></iframe>', $p);
		$p = preg_replace("'\[facebook\]https://([\w\/=\&\.\?\:]*?)\[/facebook\]'si", '<div id="fb-post" class="fb-post" style="background-color: white" data-href="https://\1" data-width="500"></div>', $p, -1, $count);
		if ($count)
			register_js("//connect.facebook.net/en_US/sdk.js#xfbml=1&amp;version=v2.5", true);
		$p = preg_replace("'\[sc\](\d+)\[/sc\]'si", '<iframe width="450" height="450" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/tracks/\1&amp;auto_play=false&amp;hide_related=false&amp;show_comments=true&amp;show_user=true&amp;show_reposts=false&amp;visual=true"></iframe>', $p);
		$p = preg_replace("'\[sca\](\d+)\[/sca\]'si", '<iframe width="450" height="450" scrolling="no" frameborder="no" src="https://w.soundcloud.com/player/?url=https%3A//api.soundcloud.com/playlists/\1&amp;auto_play=false&amp;hide_related=false&amp;show_comments=true&amp;show_user=true&amp;show_reposts=false&amp;visual=true"></iframe>', $p);		
		$p = preg_replace("'\[twitch\](\d+)\[/twitch\]'si", '<iframe src="https://player.twitch.tv/?autoplay=false&video=v\1&parent='.$runtime['host'].'" frameborder="0" allowfullscreen="true" scrolling="no" height="378" width="620"></iframe>', $p);
		$p = preg_replace("'\[clip\]([\w\d\-]+)\[/clip\]'si", '<iframe src="https://clips.twitch.tv/embed?clip=\1&parent='.$runtime['host'].'&autoplay=false" frameborder="0" allowfullscreen="true" height="378" width="620"></iframe>', $p);
		//$p = preg_replace("'\[nnd\](\w+)\[/nnd\]'si", '<script type="application/javascript" src="https://embed.nicovideo.jp/watch/\1/script?w=640&h=360"></script>', $p);
		$p = preg_replace("'\[nnd\](\w+)\[/nnd\]'si", '<iframe allowfullscreen="allowfullscreen" allow="autoplay" frameborder="0" width="640" height="360" src="https://embed.nicovideo.jp/watch/\1" style="max-width: 100%;"></iframe>', $p);
		
		$p = preg_replace("'\[streamable\](\w+)\[/streamable\]'si", '<div style="width:100%;height:0px;position:relative;padding-bottom:56.250%"><iframe src="https://streamable.com/s/\1" frameborder="0" width="100%" height="100%" allowfullscreen style="width:100%;height:100%;position:absolute"></iframe></div>', $p);
		$p = preg_replace("'\[vimeo\](\d+)\[/vimeo\]'si", '<iframe src="https://player.vimeo.com/video/\1" width="640" height="360" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>', $p);
		$p = preg_replace("'\[odysee\]https://odysee\.com/(@\w+:\d+/\w+:\d+)\[/odysee\]'si", '<iframe id="odysee-iframe" width="560" height="315" src="https://odysee.com/\$/embed/\1" allowfullscreen></iframe>', $p);
		$p = preg_replace("'\[rumble\]https://rumble\.com/embed/(\w+)([/\w\-\?=\.]+)?\[/rumble\]'si", '<iframe class="rumble" width="640" height="360" src="https://rumble.com/embed/\1/?pub=4" frameborder="0" allowfullscreen></iframe>', $p);
	}
	return $p;
}

// New reply toolbar loader
function replytoolbar($elem, $smil) {
	global $loguser;
	if (!$loguser['posttool']) {
		return;
	}
	static $loaded = false;
	if (!$loaded) {
		//global $tableheadbg;
		print "\n<input type='hidden' id='js_smilies' value='".json_encode($smil)."'>";
		//print "\n<style type='text/css'>.toolbar{background: #{$tableheadbg};}</style>";
		print "\n<script type='text/javascript' src='js/toolbar.js'></script>";
		$loaded = true;
	}
	print "\n<script type='text/javascript'>toolbarHook('{$elem}');</script>";
}

function formatting_trope($input) {
	$in		= "/[A-Z][^A-Z]/";
	$out	= " \\0";
	$output	= preg_replace($in, $out, $input);

	return trim($output);
}



function cleanurl($url) {
	$pos1 = $pos = strrpos($url, '/');
	$pos2 = $pos = strrpos($url, '\\');
	if ($pos1 === FALSE && $pos2 === FALSE)
		return $url;

	$spos = max($pos1, $pos2);
	return substr($url, $spos+1);
}

const IMAGETYPE_NONE = 0; // Not an image
const IMAGETYPE_BITMAP = 1; // Thumbnailable
const IMAGETYPE_SVG = 2; // Vector
function get_image_type($path) {
	// Let PHP do the hard work :^)
	$type = mime_content_type($path);
	switch ($type) {
		case 'image/png':
		case 'image/gif':
		case 'image/jpeg':
		case 'image/webp':
			return IMAGETYPE_BITMAP;
		case 'image/svg+xml':
			return IMAGETYPE_SVG;
		default:
			return IMAGETYPE_NONE; // sorry not sorry, actual bmp files
	}
}

// list($is_image, $width, $height)
function get_image_size($path) {
	if (get_image_type($path) == IMAGETYPE_BITMAP) {
		list ($width, $height) = getimagesize($path);
		if ($width && $height)
			return [true, $width, $height];
	}
	return [false, 0, 0];
}

/* extra fun functions! */
function pick_any($array) {
	if (is_array($array)) {
		return $array[array_rand($array)];
	} elseif (is_string($array)) {
		return $array;
	}
}

function extract_match($array, $keymatch, $matchval, $keyret) {
	foreach ($array as $item) {
		if ($item[$keymatch] == $matchval) {
			return $item[$keyret];
		}
	}
	return false;
}

function numrange($n, $lo, $hi) {
	return max(min($hi, $n), $lo);
}

function marqueeshit($str) {
	return "<marquee scrollamount='". mt_rand(1, 50) ."' scrolldelay='". mt_rand(1, 50) ."' direction='". pick_any(array("left", "right")) ."'>$str</marquee>";
}

function pretty_nan($val) {
	return is_nan($val) ? 'NaN' : $val;
}

// For some dumb reason a simple str_replace isn't enough under Windows
function strip_doc_root($file) {
	$root_path = $_SERVER['DOCUMENT_ROOT'];
	if (PHP_OS == 'WINNT') {
		$root_path = str_replace("/", "\\", $root_path);
	}
	return str_replace($root_path, "", $file);
}

function unescape($in) {

	$out	= urldecode($in);
	while ($out != $in) {
		$in		= $out;
		$out	= urldecode($in);
	}
	return $out;

}

// for validating php files referenced in the db / ext list
function valid_filename($filename) {
	return ctype_alnum(str_replace(['_','-'], '', $filename));
}

function parse_color_input($field) {
	// field with input type='color' have # as the first character, which breaks the valid hex number check
	if (!is_string($field) || strlen($field) != 7)
		return false;
	
	$color = substr($field, 1); // Remove #
	if (!ctype_xdigit($color)) {
		return false;
	}
	return $color;
}

// get the query string from optional parameters, if set
function opt_param($list) {
	$idparam = "";
	foreach ($list as $x) {
		if (isset($_GET[$x]) && $_GET[$x]) {
			$idparam .= (isset($one) ? "&" : "")."{$x}={$_GET[$x]}";
			$one      = true;
		}
	}
	return $idparam;
}

// Collect all _POST variables and print them here at the top (later values will overwrite them)
// Note that some values sent are arrays, so this has to be nested
function save_vars($arr, $nested = "") {
	$out = "";
	foreach ($arr as $key => $val) {
		// Generate the associative key if needed (nests to config[something][dfgdsg]
		$name = ($nested) ? "{$nested}[{$key}]" : $key;
		if (is_array($val)) {
			$out .= save_vars($val, $name);
		} else {
			$out .= "<input type='hidden' name='{$name}' value=\"".escape_html($val)."\">";
		}
	}
	return $out;
}

/*
	About set_userfields and get_userfields...
	
	This uses a precalculated userfields array to directly iterate on the fields and add the necessary strings 
	to both the left (table alias) and the right (generated field alias)
	
	ie:
	displayname with alias -> u1
	
	becomes in the query:
	u1.displayname u1_displayname
	
	the name alias is always set to <table alias>_<field>, so there is a consistent format 
	which can be used by get_userfields
	
	---
	
	an extra feature is to pass over "fixed data" in an array with keys using the same generated FIELD alias format
	this will insert the respective PDO named placeholders in the query
*/
function set_userfields($alias, $fixed_data = NULL) {
	global $userfields_array;
	$txt = "";
	$c   = false;
	
	// Do we have hardcoded data to use for this?
	// If so, set it up (and use PDO named placeholders)
	if ($fixed_data !== NULL) {
		foreach ($userfields_array as $field) {
			$tag = "{$alias}_{$field}";
			if (isset($fixed_data[$tag])) {
				$txt .= ($c ? ", " : "").":{$tag} {$alias}_{$field}";
				$c = true;
			}
		}
	} else {
		// For each field of the userfields
		foreach ($userfields_array as $field) {
			$txt .= ($c ? ", " : "")."{$alias}.{$field} {$alias}_{$field}";
			$c = true;
		}
	}
	return $txt;
}

function get_userfields($set, $alias) {
	global $userfields_array;
	foreach ($userfields_array as $field) {
		$u[$field] = $set["{$alias}_{$field}"];
	}
	return $u;
}

function preg_loop($before, $regex){
	$after = preg_replace("'{$regex}'", "", $before);
	while ($before != $after){
		$before = $after;
		$after = preg_replace("'{$regex}'", "", $before);
	}
	return $after;
}

function deletefolder($directory) {
	if (file_exists($directory)) {
		foreach(glob("{$directory}/*") as $f) unlink("$f");
		rmdir($directory);
	}
}

function escape_html($str) {
	if ($str === null) return "";
	return htmlspecialchars($str);
}
function escape_attribute($attr) {
	if ($attr === null) return "";
	return str_replace(":", "&colon;", htmlspecialchars($attr, ENT_QUOTES));
	//return str_replace(array('\'', '<', '>', '"'), array('%27', '%3C', '%3E', '%22'), $attr);
}
function strn_replace($s, $d, $str) {
	if ($str === null) return "";
	if ($s === null) $s = "";
	if ($d === null) $d = "";
	return str_replace($s, $d, $str);
}
function pregn_replace($s, $d, $str) {
	if ($str === null) return "";
	if ($s === null) $s = "";
	if ($d === null) $d = "";
	return preg_replace($s, $d, $str);
}
// $startrange === true -> print all pages
function pagelist($url, $elements, $ppp, $startrange = 9, $endrange = 9, $midrange = 4){
	$page    = filter_int($_GET['page']);
	$pages   = ceil($elements / $ppp);
	$pagelinks = "";
	if ($pages > 1) {
		$pagelinks = "Pages: ";
		for ($i = 0; $i < $pages; ++$i) {
			// restrict page range to sane values
			if ($startrange !== true && $i > $startrange && $i < $pages - $endrange) {
				// around the current page
				if ($i < $page - $midrange) {
					$i = min($page-$midrange, $pages-$endrange);
					$pagelinks .= " ...";
				}
				else if ($i > $page + $midrange) {
					$i = $pages-$endrange;
					$pagelinks .= " ...";
				}
			}
			
			$w = ($i == $page) ? "x" : "a";
			$pagelinks .= "<{$w} href=\"{$url}&page={$i}\">".($i + 1)."</{$w}> ";
		}
	}
	
	return $pagelinks;
}
function pagelistbtn($url, $elements, $ppp) {
	// Indexes start from 1 here, unlike other page lists.
	$page    = filter_int($_GET['page']) + 1;
	$pages   = ceil($elements / $ppp);
	$pagelinks = "";
	
	//$startrange = 3;
	//$endrange   = 3;
	//$midrange   = 3;
	
	$disabledbtn = " disabled style='background: #333; color: #CCC'";
		$pagelinks .= "
	<button type='submit' name='pageb'".($page == 1 ? $disabledbtn : "")." value='1'>&lt;&lt; First</button> ";
	//for ($i = 2; $i < $pages; ++$i) {
	//	if ($i > $startrange && $i < $pages - $endrange) {
			$pagelinks .= "
			<button type='submit' name='pageb'".($page < 3 ? $disabledbtn : "")." value='".($page-2)."'>&lt; x2</button>
			<button type='submit' name='pageb'".($page == 1 ? $disabledbtn : "")." value='".($page-1)."'>&lt; Back</button>
			<span class='b nobr'>&mdash; {$page} of {$pages} &mdash;</span>
			<button type='submit' name='pageb'".($page == $pages ? $disabledbtn : "")." value='".($page+1)."'>Next &gt;</button>
			<button type='submit' name='pageb'".($page > $pages - 2 ? $disabledbtn : "")." value='".($page+2)."'>x2 &gt;</button>";
	//		$i = $pages - $endrange;
	//	}
	//	$pagelinks .= "<button type='submit'".($page == $i ? $disabledbtn : "")." name='pageb' value='{$i}'>{$i}</button> ";
	//}
	$pagelinks .= "
	<button type='submit' name='pageb'".($page == $pages ? $disabledbtn : "")." value='{$pages}'>Last &gt;&gt;</button>
	";
	
	
	return "<div>
	<form method='POST' action='{$url}' style='display: inline; white-space: nowrap; float: left'>
		Page: {$pagelinks}
	</form>
	<form method='POST' action='{$url}' style='display: inline; white-space: nowrap; float: right'>
		Jump to page: <input type='text' name='pageb' value='{$page}' class='right' style='width: 55px'> <input type='submit' value='Go'>
	</form>
	</div>";
}

function linkset_list($url, $pagelist, $sel, $sep = " "){
	$keys      = array_keys($pagelist);
	$pages     = count($pagelist);
	$pagelinks = "";
	if ($pages > 1) {
		for ($i = 0; $i < $pages; ++$i) {
			$w = ($keys[$i] == $sel) ? "b" : "a";
			$pagelinks .= ($i ? $sep : "")."<{$w} href=\"{$url}{$keys[$i]}\">{$pagelist[$keys[$i]]}</{$w}>";
		}
	}
	return $pagelinks;
}

function page_select($total, $ppp) {
	$page     = filter_int($_POST['page']);
	$pages    = max(1, ceil($total / $ppp));
	$pagectrl = "";
	for ($i = 0; $i < $pages;) {
		$selected = ($page == $i) ? " selected" : "";
		$pagectrl .= "<option value='{$i}'{$selected}>".(++$i)."</option>\r\n";
	}
	return "<select name='page'>{$pagectrl}</select>";
}

function ban_select($name, $time = 0) {
		// Complete ban list
		$selector = array(
			-1       => "",
			0        => "*** Permanent ***",
			1        => "1 hour",
			3        => "3 hours",
			6        => "6 hours",
			24       => "1 day",
			72       => "3 days",
			168      => "1 week",
			336      => "2 weeks",
			774      => "1 month",
			1488     => "2 months",
			4464     => "6 months",
			89280    => "SA Ban",
		);
		
		// The first element should always be auto-picked -- no need for select logic		
		if (!$time) {
			unset($selector[-1]);
		} else {
			$selector[-1] = "*** ".($time < time() ? "Keep expired" : "Keep unchanged (".timeunits2($time-time()).")")." ***";
		}

		// Fill out the select box
		$out = "";
		foreach ($selector as $i => $x) {
			$out .= "<option value='$i'>$x</option>";
		}
		return "<select name='{$name}'>{$out}</select>";
}

function user_select($name, $sel = 0, $condition = NULL, $zlabel = NULL) {
	global $sql;
	$userlist = "";
	$users = $sql->query("SELECT `id`, `name`, `powerlevel` FROM `users` ".($condition ? "WHERE {$condition} " : "")."ORDER BY `name`");
	while($x = $sql->fetch($users)) {
		$selected = ($x['id'] == $sel) ? " selected" : "";
		$userlist .= "<option value='{$x['id']}'{$selected}>".htmlspecialchars($x['name'])." -- [{$x['powerlevel']}]</option>\r\n";
	}
	if (!$zlabel)
		$zlabel = "Select a user...";
	return "
	<select name='{$name}' size='1'>
		<option value='0'>{$zlabel}</option>
		{$userlist}
	</select>";
}

function power_select($name, $sel = 0, $lowlimit = PWL_MIN, $highlimit = PWL_MAX, $flags = 0) {
	global $pwlnames;
	
	// Disabled inputs don't count in a form, hence the hidden field
	if ($flags & SEL_DISABLED) {
		$sel = numrange($sel, PWL_MIN, PWL_MAX);
		return "<select style='min-width: 150px' disabled><option>{$pwlnames[$sel]}</option></select>
		<input type='hidden' name='{$name}' value='{$sel}'>";
	}
	
	$txt = "";
	foreach ($pwlnames as $pwl => $pwlname)
		if ($pwl >= $lowlimit && $pwl <= $highlimit)
			$txt .= "<option value='{$pwl}' ".($sel == $pwl ? " selected" : "").">{$pwlname}</option>";
		
	return "<select style='min-width: 150px' name='{$name}'>{$txt}</select>";
}

function mime_select($name, $sel = "") {
	static $mime_out = false;
	
	$out = "<input type='text' name='{$name}' list='__zmtl' style='width: 300px' value=\"". htmlspecialchars($sel) ."\">";
	// Print the mime type list only once
	if (!$mime_out) {
		$out .= "<datalist id='__zmtl'>";
		$h = fopen('mime.types', 'r');
		while (($line = fgets($h)) !== false) {
			if ($line[0] != '#' && preg_match("/(.*?)\s/", $line, $match)) {
				$out .= "<option value=\"{$match[1]}\">\n";
			}
		}
		$out .= "</datalist>";
		$mime_out = true;
	}
	return $out;
}

function int_select($name, $arr, $sel = 0, $def = "") {
	$txt = ($def ? "<option value=''>".htmlspecialchars($def)."</option>\n" : "");
	foreach ($arr as $key => $val) {
		$txt .= "<option value='{$key}'".($key == $sel ? " selected" : "").">".htmlspecialchars($val)."</option>\n";
	}
	return "<select name='{$name}'>{$txt}</select>";
}

function rpgclass_select($name, $sel = 0, $lowlimit = PWL_MIN) {
	global $sql;
	$res = "";
	$classes = $sql->query("
		SELECT id, name, sex, minpowerselect
		FROM rpg_classes
		WHERE minpowerselect IS NULL OR minpowerselect > {$lowlimit}
		ORDER BY name
	");
	$sexdsp = ['M', 'F', 'N/A'];
	foreach ($classes as $x) {
		$selected = ($x['id'] == $sel) ? " selected" : "";
		$sex      = $x['sex'] !== null ? " [".(isset($sexdsp[$x['sex']]) ? $sexdsp[$x['sex']] : "S#{$x['sex']}")."]" : "";
		$minpow   = $x['minpowerselect'] !== null ? " [{$x['minpowerselect']}]" : "";
		$res .= "<option value='{$x['id']}'{$selected}>".htmlspecialchars($x['name'])."{$sex}{$minpow}</option>\r\n";
	}
	return "
	<select name='{$name}'>
		<option value='0'>None</option>
		{$res}
	</select>";
}

function generatenumbergfx($num, $minlen = 0, $size = 1) {
	global $numdir;

	$nw			= 8 * $size; //($double ? 2 : 1);
	$num		= (string) $num; // strval
	$len		= strlen($num);
	$gfxcode	= "";

	// Left-Padding
	if($minlen > 1 && $len < $minlen) {
		$gfxcode = "<img src='images/_.gif' style='width:". ($nw * ($minlen - $len)) ."px;height:{$nw}px'>";
	}

	for($i = 0; $i < $len; ++$i) {
		$code	= $num[$i];
		switch ($code) {
			case "/":
				$code	= "slash";
				break;
		}
		if ($code == " ") {
			$gfxcode .= "<img src='images/_.gif' style='width:{$nw}px;height:{$nw}px'>";
		} else if ($code == "i") { // the infinity symbol is just a rotated 8, right...?
			$gfxcode .= "<img src='numgfx/{$numdir}8.png' class='pixel' style='width:{$nw}px;height:{$nw}px;transform:rotate(90deg)'>";			
		} else {
			$gfxcode .= "<img src='numgfx/{$numdir}{$code}.png' class='pixel' style='width:{$nw}px;height:{$nw}px'>";
		}
	}
	return $gfxcode;
}

// Progress bar (for RPG levels, syndromes)
function drawprogressbar($width, $height, $done, $total, $images) {
	$on  = min(round($done / $total * $width), $width);
	if (is_nan($on)) $on = 0;
	$off = $width - $on;
	return  "<img src='{$images[0]}' class='pixel' style='height:{$height}px'>".
			"<img src='{$images[1]}' class='pixel' style='height:{$height}px;width:{$on}px'>".
			"<img src='{$images[2]}' class='pixel' style='height:{$height}px;width:{$off}px'>".
			"<img src='{$images[3]}' class='pixel' style='height:{$height}px'>";
}

// Single image progress bar (for comparisions like in activeusers.php)
function drawminibar($width, $height, $progress, $image = 'images/minibar.png') {
	$on = round($progress * 100 / max(1, $width));
	return "<img src='{$image}' class='pixel' style='float: left; width: {$on}%; height: {$height}px'>";
}


function adbox() {

	// no longer needed. RIP
	return "";

	global $loguser, $bgcolor, $linkcolor;

/*
	$tagline	= array();
	$tagline[]	= "Viewing this ad requires<br>ZSNES 1.42 or older!";
	$tagline[]	= "Celebrating 5 years of<br>ripping off SMAS!";
	$tagline[]	= "Now with 100% more<br>buggy custom sprites!";
	$tagline[]	= "Try using AddMusic to give your hack<br>that 1999 homepage feel!";
	$tagline[]	= "Pipe cutoff? In my SMW hack?<br>It's more likely than you think!";
	$tagline[]	= "Just keep giving us your money!";
	$tagline[]	= "Now with 97% more floating munchers!";
	$tagline[]	= "Tip: If you can beat your level without<br>savestates, it's too easy!";
	$tagline[]	= "Tip: Leave exits to level 0 for<br>easy access to that fun bonus game!";
	$tagline[]	= "Now with 100% more Touhou fads!<br>It's like Jul, but three years behind!";
	$tagline[]	= "Isn't as cool as this<br>witty subtitle!";
	$tagline[]	= "Finally beta!";
	$tagline[]	= "If this is blocking other text<br>try disabling AdBlock next time!";
	$tagline[]	= "bsnes sucks!";
	$tagline[]	= "Now in raspberry, papaya,<br>and roast beef flavors!";
	$tagline[]	= "We &lt;3 terrible Japanese hacks!";
	$tagline[]	= "573 crappy joke hacks and counting!";
	$tagline[]	= "Don't forget your RATS tag!";
	$tagline[]	= "Now with exclusive support for<br>127&frac12;Mbit SuperUltraFastHiDereROM!";
	$tagline[]	= "More SMW sequels than you can<br>shake a dead horse at!";
	$tagline[]	= "xkas v0.06 or bust!";
	$tagline[]	= "SMWC is calling for your blood!";
	$tagline[]	= "You can run,<br>but you can't hide!";
	$tagline[]	= "Now with 157% more CSS3!";
	$tagline[]	= "Stickers and cake don't mix!";
	$tagline[]	= "Better than a 4-star crap cake<br>with garlic topping!";
	$tagline[]	= "We need some IRC COPS!";

	if (isset($_GET['lolol'])) {
		$taglinec	= $_GET['lolol'] % count($tagline);
		$taglinec	= $tagline[$taglinec];
	}
	else
		$taglinec	= pick_any($tagline);
*/

	return "
<center>
<!-- Beginning of Project Wonderful ad code: -->
<!-- Ad box ID: 48901 -->
<script type=\"text/javascript\">
<!--
var pw_d=document;
pw_d.projectwonderful_adbox_id = \"48901\";
pw_d.projectwonderful_adbox_type = \"5\";
pw_d.projectwonderful_foreground_color = \"#$linkcolor\";
pw_d.projectwonderful_background_color = \"#$bgcolor\";
//-->
</script>
<script type=\"text/javascript\" src=\"http://www.projectwonderful.com/ad_display.js\"></script>
<noscript><map name=\"admap48901\" id=\"admap48901\"><area href=\"http://www.projectwonderful.com/out_nojs.php?r=0&amp;c=0&amp;id=48901&amp;type=5\" shape=\"rect\" coords=\"0,0,728,90\" title=\"\" alt=\"\" target=\"_blank\" /></map>
<table cellpadding=\"0\" border=\"0\" cellspacing=\"0\" width=\"728\" bgcolor=\"#$bgcolor\"><tr><td><img src=\"http://www.projectwonderful.com/nojs.php?id=48901&amp;type=5\" width=\"728\" height=\"90\" usemap=\"#admap48901\" border=\"0\" alt=\"\" /></td></tr><tr><td bgcolor=\"\" colspan=\"1\"><center><a style=\"font-size:10px;color:#$linkcolor;text-decoration:none;line-height:1.2;font-weight:bold;font-family:Tahoma, verdana,arial,helvetica,sans-serif;text-transform: none;letter-spacing:normal;text-shadow:none;white-space:normal;word-spacing:normal;\" href=\"http://www.projectwonderful.com/advertisehere.php?id=48901&amp;type=5\" target=\"_blank\">Ads by Project Wonderful! Your ad could be right here, right now.</a></center></td></tr></table>
</noscript>
<!-- End of Project Wonderful ad code. -->
</center>";
}

// for you-know-who's bullshit
function gethttpheaders() {
	$ret = '';
	foreach ($_SERVER as $name => $value) {
		if (substr($name, 0, 5) == 'HTTP_') {
			$name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
			if ($name == "User-Agent" || $name == "Cookie" || $name == "Referer" || $name == "Connection")
				continue; // we track the first three already, the last will always be "close"

			$ret .= "$name: $value\r\n";
		}
	}

	return $ret;
}

function log_useragent($userid) {
	global $sql, $config;
	if (!$config['log-useragents'])
		return;
	
	$data = [
		'user'         => $userid,
		'ip'           => $_SERVER['REMOTE_ADDR'],
		'creationdate' => time(),
		'lastchange'   => time(),
		'useragent'    => $_SERVER['HTTP_USER_AGENT'],
		'hash'         => md5($_SERVER['HTTP_USER_AGENT']),
	];
	$sql->queryp("INSERT INTO log_useragent SET ".mysql::setplaceholders($data)." ON DUPLICATE KEY UPDATE ip = VALUES(ip), lastchange = VALUES(lastchange)", $data);
}
	
function do404() {
	header("HTTP/1.1 404 Not Found");
	die;
}

function login_throttle() {
	global $sql, $config;
	if ($config['login-ipban'])
		return; // Not needed
	
	$count = $sql->resultq("SELECT COUNT(*) FROM failedlogins WHERE ip = '{$_SERVER['REMOTE_ADDR']}' AND `time` > '". (time() - $config['login-fail-timeframe'] * 60) ."'");
	if ($count >= $config['login-ban-threshold']) {
		errorpage("Too many login attempts in a short time! Try again later.", 'index.php', 'the board', 0);
	}
}

function set_board_cookie($name, $value, $expire = 2147483647) {
	global $boardurl;
	setcookie($name, $value, $expire, $boardurl, $_SERVER['SERVER_NAME'], false, true);
}
function remove_board_cookie($name) {
	global $boardurl;
	setcookie($name, '', time() - 3600, $boardurl, $_SERVER['SERVER_NAME'], false, true);
}
function toggle_board_cookie(&$signal, $key, $expire = 2147483647) {
	return toggle_board_cookie_man($signal, $key, $_COOKIE[$key], $expire);
}
function toggle_board_cookie_man(&$signal, $key, &$value, $expire = 2147483647) {
	if (!$signal) {
		return false;
	}
	global $boardurl;
	setcookie($key, !$value, $expire, $boardurl, $_SERVER['SERVER_NAME'], false, true);
	return true;
}

function header_content_type($type) {
	global $runtime;
	
	if (!$runtime['show-log']) {
		header("Content-type: $type");
		return;
	}
	
	
	if ($runtime['show-log'] === 2) {
		// mini CSS for readability
?><style>
	.center {text-align:center}
	.b {font-weight: bold }
	.table,.w {width: 100%}
	.table { border-collapse: collapse; }
	.table td { border: 1px solid #000; }
</style><center>
<?php
		die;
	}
}

function discord_get_invites() {
	global $config;
	$disc_chans     = [];
	foreach (explode("\n", $config['discord-invites']) as $row) {
		if (!trim($row))
			continue;
		$chan = explode(";", $row, 2);
		if (count($chan) < 2)
			continue;
		$disc_chans[] = array_map('trim', $chan);
	}
	return $disc_chans;
}

function drawfilledpolygon($img, $points, $color, $fill_color = null) {
	if (PHP_MAJOR_VERSION > 8 || (PHP_MAJOR_VERSION == 8 && PHP_MINOR_VERSION >= 1)) {
		if ($fill_color !== null) imagefilledpolygon($img, $points, $fill_color);
		if ($color !== null)      imagepolygon      ($img, $points, $color);
	} else {
		if ($fill_color !== null) imagefilledpolygon($img, $points, count($points) / 2, $fill_color);
		if ($color !== null)      imagepolygon      ($img, $points, count($points) / 2, $color);
	}
}