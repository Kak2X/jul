<?php
	
	const NEWS_VERSION = "v0.6 -- 09/04/20";
	
	// Load "permissions"
	if ($banned) $loguser['id'] = 0; // oh dear
	$ismod	    = ($loguser['id'] && $loguser['powerlevel'] >= $xconf['admin-perm']);
	$canwrite	= ($loguser['id'] && $loguser['powerlevel'] >= $xconf['write-perm']);
	
	// override the scheme, if enabled
	// while the news page now also works with other schemes, it isn't the way it was intended to look like
	if (!filter_int($_COOKIE['news-noschrep']))
		$miscdata['scheme'] = 211;
	
	// Not truly alphanumeric as it also allows spaces
	// function alphanumeric($text) { return preg_replace('/[^\da-z ]/i', '', $text); }
	
	function news_confirm_message($key, $text, $title = "", $form_url = "", $buttons = NULL, $token = TOKEN_MAIN) {
		if (!defined('HEADER_PRINTED'))	news_header(); 
		confirm_message($key, $text, $title, $form_url, $buttons, $token);
	}
	
	function news_errorpage($text) {
		if (!defined('HEADER_PRINTED'))	news_header("Error"); 
		print "<table class='table news-container'><tr><td class='tdbg2 center'>{$text}</td></tr></table>";
		//print "<table class='table'><tr><td class='tdbg1 center'>{$text}</td></tr></table>";
		news_footer();
	}
	
	function news_header($title = "") {
		global $xconf, $meta;
		$meta['baserel'] = "<link rel='stylesheet' href='".actionlink("css/news.css")."' type='text/css'>";
		if ($xconf['show-special-header']) {
			print "<!doctype html>"; // We need to print this here, since pageheader() doesn't set one
			pageheader($title, NULL, NULL, true);
			?>
			
			<center>
			<table class='table top-header'>
				<tr>
					<td class='tdbg1 center nobr header-title-td'>
						<h1><a href="<?=actionlink("news.php")?>" class="header-title"><?= $xconf['page-header'] ?></a></h1>
					</td>
				</tr>
				<tr>
					<td class='tdbg2 center header-links'>
						<?= xssfilters($xconf['header-links']) ?>
					</td>
				</tr>
			</table>
			</center>
			<br/>
			<?php
		} else {
			pageheader($title);
		}
	}
	
	function news_footer() {
		global $xconf;
		if ($xconf['show-special-header']) {
			?>
			<br>
			<center>
			<table class='table center news-container new-post news-footer'>
				<tr>
					<td class="tdbg2">
						News Engine (<?= NEWS_VERSION ?>)
					</td>
				</tr>
			</table>
			</center>
			<?php
			pagefooter(false);
		}
		
		pagefooter();
	}
	
	function news_format($post, $preview = false, $pin = 0) {
		/*
			threadpost() replacement as the original function obviously wouldn't work for this
		*/
		global $loguser, $config, $xconf, $ismod, $sysadmin, $sql, $userfields;
		
		// The first post is rendered in a different (blue) color scheme.
		static $theme;
		if ($theme === NULL){
			$theme = "new-post";
		} else {
			$theme = "";
		}
		
		$text_shrunk = false;
		if ($preview) {
			// Get message length to shrink it if it's a preview
			$charcount = strlen($post['text']);
			if ($charcount > $xconf['max-preview-length']){
				$post['text'] = news_preview($post['text'], $charcount)."...";
				$text_shrunk = true;
			}
		}
		
		$editlink = $lastedit = $viewfull = "";
		// Preview to view full post
		if ($text_shrunk)
			$viewfull = "<tr><td class='tdbg2 fonts'>To read the full text, click <a href='".actionlink("news.php?id={$post['id']}")."'>here</a>.</td></tr>";
		
		// Post controls
		
		if ($post['id']) {
			if ($ismod || $loguser['id'] == $post['user']) {
				$editlink = "<a href='".actionlink("news-editpost.php?id={$post['id']}&edit")."'>Edit</a>";
				if ($post['deleted'])
					$editlink .= " - <a href='".actionlink("news-editpost.php?id={$post['id']}&del")."'>Undelete</a> - <a href='".actionlink("news.php?id={$post['id']}&pin={$post['id']}")."'>Peek</a>";
				else
					$editlink .= " - <a href='".actionlink("news-editpost.php?id={$post['id']}&del")."'>Delete</a>";
			}				
			if ($sysadmin) 
				$editlink .= " - <a class='danger' href='".actionlink("news-editpost.php?id={$post['id']}&erase")."'>Erase</a>";
		}
		
		if (filter_int($post['lastedituser'])) {
			$lastedit     = " (Last edited by ".getuserlink($post['edituserdata'], $post['lastedituser'])." at ".printdate($post['lasteditdate']).")";
		}
		$usersort = "<a href='".actionlink("news.php?user={$post['user']}")."'>View all by this user</a>";
		
		$hideondel = ($post['deleted'] && $post['id'] != $pin);
		if ($hideondel) {
			$post['text'] = "<i>(post deleted)</i>";
			//$post['title'] = "<s>{$post['title']}</s>";
		}
		
		prepare_avatar($post, $picture, $userpic);
		
		return "
		<input type='hidden' name='id' value={$post['id']}>
		<table class='table news-container {$theme}'>
			<tr>
				<td class='tdbgh' colspan='2'>
					<table class='w' style='border-spacing: 0'>
						<tr>
							<td class='nobr left'>
								<a href='".actionlink("news.php?id={$post['id']}")."' class='headlink'>".htmlspecialchars($post['title'])."</a>
							</td>
							<td class='fonts right'>
								$editlink ".printdate($post['date'])."<br>
								$lastedit ".getuserlink($post['userdata'], $post['user'])."
							</td>
						</tr>
					</table>
				</td>
			</tr>
			
			"./*
			<!-- standard mode (more consistent with regular tlayout) -->
			<tr>
				<td class='tdbg2 center vatop' style='width: {$config['max-avatar-size-x']}px'>
					{$userpic}
				</td>
				<td class='tdbg2 vatop' style='padding-bottom: 12px'>
					".dofilters(doreplace2($post['text'], "{$post['nosmilies']}|{$post['nohtml']}"))."
				</td>
			</tr>
			*/"
			<tr>
				<td class='tdbg2 vatop' style='padding-bottom: 12px' colspan='2'>
					<img src=\"{$picture}\" style='float:right'>
					".dofilters(doreplace2($post['text'], "{$post['nosmilies']}|{$post['nohtml']}"))."
				</td>
			</tr>
			
			$viewfull
			".($hideondel ? "" : "
			<tr class='fonts news-item-summary'>
				<td class='tdbg1' colspan='2'>
					<div>Comments: <b>{$post['comments']}</b><span style='float:right'>$usersort</span></div>
					<div>Tags: ".news_tag_format($post['tags'])."</div>
				</td>
			</tr>
			")."
		</table>";
		
	}
	
	function news_post_preview($data) {
		global $loguser, $sql;
		
		// Complete the list of existing tags
		$tags = [];
		$source = load_news_tags();
		foreach ($data['tags'] as $tagid)
			$tags[$tagid] = $source[$tagid];
		// and the new tags as well
		$extra = explode(",", $data['newtags']);
		for ($i = 0, $c = count($extra); $i < $c; ++$i)
			$tags[] = ['title' => $extra[$i]];
		
		$preview = array(
			'id'        => $data['id'],
			'user'      => $data['user'],
			'date'      => isset($data['date']) ? $data['date'] : ctime(),
			'userdata'  => $data['userdata'],
			'deleted'   => 0,
			'comments'  => 0,
			'tags'      => $tags,
			'text'      => $data['text'],
			'title'     => $data['title'],
			'nosmilies' => $data['nosmilies'],
			'nohtml'    => $data['nohtml'],
			'moodid'    => $data['moodid'],
			'piclink'   => get_weblink($data['user'], $data['moodid']),
			'uid'       => $data['user'],
		);
		if ($data['id']) {
			$preview['comments']     = $sql->resultq("SELECT COUNT(*) FROM news_comments WHERE pid = {$data['id']}");
			$preview['lastedituser'] = $loguser['id'];
			$preview['lasteditdate'] = ctime();
			$preview['edituserdata'] = $loguser;
		}
		
		return "<table class='table'>
				<tr><td class='tdbgh center b'>Message preview</td></tr>
				<tr><td class='tdbg1'>".news_format($preview)."</td></tr>
			</table>
			<br>";
	}
	
	// Display the comment section for any given post
	function news_comments($post, $author, $edit = 0) {
		global $sql, $config, $loguser, $ismod, $sysadmin, $userfields;
		$token = generate_token(TOKEN_MGET);
		
		$comments = $sql->query(set_avatars_sql("
			SELECT c.*, ".set_userfields('u1').", u1.id uid {%AVFIELD%}, ".set_userfields('u2')."
			FROM news_comments c
			LEFT JOIN users u1 ON c.user         = u1.id
			LEFT JOIN users u2 ON c.lastedituser = u2.id
			{%AVJOIN%}
			WHERE c.pid = {$post} ".($ismod ? "" : "AND c.deleted = 0")."
			ORDER BY c.id DESC
		", 'c'));
		
		$txt = "";
		while ($x = $sql->fetch($comments)){
			// Check if we are editing this comment (and if we can do so)
			$editlink = $lastedit = $editcomment = "";
			if ($ismod || $loguser['id'] == $x['user']) {
				$editlink = "<a href='".actionlink("news.php?id={$post}&edit={$x['id']}#{$x['id']}")."'>Edit</a> - ".
							"<a href='".actionlink("news-editcomment.php?act=del&id={$x['id']}&auth={$token}")."'>".($x['deleted'] ? "Und" : "D")."elete</a>";
				$editcomment = ($edit == $x['id']);
			}
			
			if ($sysadmin) 
				$editlink .= " - <a class='danger' href='".actionlink("news-editcomment.php?act=erase&id={$x['id']}")."'>Erase</a>";
			
			$author = getuserlink(get_userfields($x, 'u1'), $x['user']);
			if ($x['deleted'])
				$author = "<s>{$author}</s>";
			if ($x['lastedituser'])
				$lastedit = "<br>(Last edited by ".getuserlink(get_userfields($x, 'u2'), $x['lastedituser'])." at ".printdate($x['lasteditdate']).")";
			
			// Display comment info (comments by the post author marked with [S])
			$txt .= "
			<table class='table small-shadow'>
				<tr id='{$x['id']}'>
					<td class='comment-userbar tdbgh vatop nbdr left nobr'>{$author}".($x['user'] == $author ? " [S]" : "")."</td>
					<td class='comment-userbar tdbgh vatop nbdl right fonts' colspan='2'>{$editlink} ".printdate($x['date'])."{$lastedit}</td>
				</tr>";
				
			// Display the actual message; print edit textbox instead if in edit mode
			if ($editcomment) {
				$txt .= "
				<form method='POST' action='".actionlink("news-editcomment.php?act=edit&id={$x['id']}")."'>
				<tr>
					<td class='tdbg2 vatop' id='nwedittd' colspan='2'>
						<textarea id='nwedittxt' name='text' rows='6' class='w' style='resize:vertical' wrap='virtual'>".htmlspecialchars($x['text'])."</textarea>
					</td>
					<td class='tdbg2' style='width: {$config['max-avatar-size-x']}px'>
						<center>".mood_layout(0, $x['user'], $x['moodid'])."</center>
					</td>
				</tr>
				<tr>
					<td class='tdbg2' colspan='3'>
						<input type='submit' name='doedit' value='Edit comment'>".mood_layout(1, $x['user'], $x['moodid'])."
						".auth_tag()."
					</td>
				</tr>
				</form>
				";
			} else {
				prepare_avatar($x, $picture, $userpic);
				$txt .= "<tr><td class='tdbg1' style='width: 100px'><img src=\"{$picture}\" style='max-width: 100px; max-height: 100px' /></td><td class='tdbg2 vatop w' colspan='2'>".dofilters(doreplace2($x['text'], "0|0"))."</td></tr>";
			}
			$txt .= "</table><div style='height:5px'></div>";
		}
		

		// Do not show new comment area if we're editing a comment
		$newcomment = "";
		if ($loguser['id'] && !$edit) {
			$newcomment .= "
			<form method='POST' action='".actionlink("news-editcomment.php?post={$post}")."'>
			<table class='table small-shadow'>
			<tr><td class='tdbgh center b' colspan='2'>New comment</td></tr>
			<tr>
				<td class='tdbg2 vatop' id='nwedittd'>
					<textarea id='nwedittxt' name='text' rows='6' class='w' style='resize:vertical; height: 100%' wrap='virtual'></textarea>
				</td>
				<td class='tdbg2' style='width: {$config['max-avatar-size-x']}px'>
					<center>".mood_layout(0, $loguser['id'], 0)."</center>
				</td>
			</tr>
			<tr>
				<td class='tdbg2' colspan='2'>
					<input type='submit' name='submit' value='Submit comment'>".mood_layout(1, $loguser['id'], 0)."
					".auth_tag()."
				</td>
			</tr>
			</table>
			</form>
			<br/>
			";
		}
		
		return "
		
			{$newcomment}
		<table class='table small-shadow'>
			<tr><td class='tdbgh center b' colspan=3>Comments</td></tr>
			</table>
			{$txt}
		";
	}
	
	function news_preview($text, $length = NULL){
		// TODO: FIX THIS
		/*
			news_preview: shrinks a string without leaving open HTML tags
			currently this doesn't allow to use < signs, made worse by the board unescaping &lt; entities
		*/
		global $xconf;
		if (!isset($length)) $length = strlen($text);
		
		/*
			Reference:
				$i 			- character index
				$res 		- result that will be returned
				$buffer 	- contains the text. if a space is found and the text isn't inside a tag it will append its contents to $res
				$opentags 	- keeps count of open HTML tags
				$intag		- marks if a text is inside a tag
		
		*/
		
		for($i = 0, $res = "", $buffer = "", $opentags = 0, $intag = false; $i < $length && $i < $xconf['max-preview-length']; $i++){
			
			$buffer .= $text[$i];
			
			if ($text[$i] == " " && !$opentags && !$intag){
				$res 	.= $buffer;
				$buffer  = "";
			}
			// only change the $opentags count when the tag starts
			else if ($text[$i] == "<"){
				if (!$intag) $opentags++;
				$intag = true;
			}
			else if ($text[$i] == ">"){
				if (!$intag) $opentags--;
				$intag = false;
			}
			
		}

		return $res;
	}
	
	
	function load_news_tags($post = 0, $limit = 0) {
		global $sql;
		if ($post) {
			return $sql->getarraybykey("
				SELECT t.id, t.title
				FROM news_tags_assoc a
				INNER JOIN news_tags t ON a.tag = t.id
				WHERE a.post = {$post}
			", 'id', mysql::USE_CACHE);
		} else {
			return $sql->getarraybykey("
				SELECT t.id, t.title, COUNT(*) cnt
				FROM news_tags t
				LEFT JOIN news_tags_assoc a ON t.id = a.tag
				GROUP BY t.id
				ORDER BY cnt DESC
				".($limit ? "LIMIT {$limit}" : "")."
			", 'id', mysql::USE_CACHE);
		}
	}
	
	function news_tag_format($tags){
		$text = array();
		foreach($tags as $id => $data)
			$text[] = "<a href=\"".actionlink("news.php?tag={$id}")."\">".htmlspecialchars($data['title'])."</a>";
		return implode(", ", $text);
	}
	
	
	function main_news_tags($num){
		global $sql;
		$tags = load_news_tags(0, $num); // Grab 15 most used tags, in order
		$total = count($tags);
		
		$txt 	= "";
		foreach($tags as $id => $data){
			$px = 10 + round(pow($data['cnt'] / $total * 100, 0.7)); // Gradually decreate font size
			$txt .= "<a class='tdbgc nbd nobr tag-links' href=\"".actionlink("news.php?tag={$id}")."\" style='font-size: {$px}px'>".htmlspecialchars($data['title'])."</a> ";
		}
		return $txt;
	}
	
	function recentcomments($limit){
		global $sql, $userfields;
		//List with latest 5 (or 10?) comments showing user and thread
		// should use IF and log editing
		$list = $sql->query("
			SELECT c.user, c.id, c.date, c.pid, n.title, $userfields uid
			FROM news_comments c
			INNER JOIN news  n ON c.pid  = n.id
			INNER JOIN users u ON c.user = u.id
			WHERE c.deleted = 0
			ORDER BY c.date DESC
			LIMIT $limit
		");
		
		$txt = "";
		while($x = $sql->fetch($list)){
			$txt .= "
				<table class='table fonts' style='border-spacing: 0'>
					<tr>
						<td class='tdbgh nbdr'>".getuserlink($x, $x['uid'])."</td>
						<td class='tdbgh nbdl right'>".printdate($x['date'])."</td>
					</tr>
					<tr>
						<td class='tdbg1' colspan=2>
							<a href='".actionlink("news.php?id={$x['pid']}#{$x['id']}")."'>Comment</a> posted on <a href='".actionlink("news.php?id={$x['pid']}")."'>".htmlspecialchars($x['title'])."</a>
						</td>
					</tr>
				</table>";
		}
		return $txt;
	}
	
	function news_calendar($extraparams = "") {
		$months = ["", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
		
		$out = "";
		
		// Initialize values
		$cur   = getdate();
		$day   = isset($_GET['cday']) ? (int)$_GET['cday'] : $cur['mday'];
		$month = isset($_GET['cmonth']) ? (int)$_GET['cmonth'] : $cur['mon'];
		$year  = isset($_GET['cyear']) ? (int)$_GET['cyear'] : $cur['year'];
		unset($cur);
		
		// Get the amount of days in the previous month
		$last = getdate(mktime(0,0,0,$month,0,$year));
		$lastdays = $last['mday'] + 1;
		
		// Get the day of the week the month starts
		// This is used to determine the amount blank cells before the first day of the month
		$date  = getdate(mktime(0,0,0,$month,1,$year));
		$start = $date['wday'];
		
		// Get the amount of days in the current month *and* the week day for the first day of the next month
		$date  = getdate(mktime(0,0,0,$month+1,0,$year) - 1);
		$days  = $date['mday'] + 1;
		$rest  = $date['wday'] + 2; // to determine the extra days at the end of the list
		
		$out = "<table class='table small-shadow'>
		<tr><td class='tdbgh center b' colspan='7'><big>{$date['month']} {$date['year']}</big></td></tr>
		<tr>
			<td class='tdbgh center b'>S</td>
			<td class='tdbgh center b'>M</td>
			<td class='tdbgh center b'>T</td>
			<td class='tdbgh center b'>W</td>
			<td class='tdbgh center b'>T</td>
			<td class='tdbgh center b'>F</td>
			<td class='tdbgh center b'>S</td>
		";
		
		// Leftover days from the previous month
		// These are selectable too, because why not
		if ($start > 0) {
			$out .= "</tr><tr>";
			for ($i = 0, $ld = $lastdays - $start, $lm = $last['mon'], $ly = $last['year']; $i < $start; ++$i, ++$ld)
				$out .= "<td class='tdbg2 center fonts'><a href='".actionlink(null,"?cday={$ld}&cmonth={$lm}&cyear={$ly}{$extraparams}")."'>{$ld}</a></td>";
		}
		
		// Days from the current month
		for ($dt = 1, $total = $days + $i; $i < $total; ++$i, ++$dt) {
			if ($i % 7 == 0)
				$out .= "</tr><tr>";
			if ($day == $dt) $c = "c";
			else if ($i % 7 == 0 || $i % 7 == 6) $c = "1";
			else $c = "2";
			$out .= "<td class='tdbg{$c} center'><a href='".actionlink(null,"?cday={$dt}&cmonth={$month}&cyear={$year}{$extraparams}")."'>{$dt}</a></td>";
		}
		
		// Leftover days from the next month until the week ends
		for ($i = 0, $total = 7 - $rest, $ld = 1, $lm = ($month%12)+1, $ly = $year + (int)($lm === 1); $i < $total; ++$i, ++$ld)
			$out .= "<td class='tdbg2 center fonts'><a href='".actionlink(null,"?cday={$ld}&cmonth={$lm}&cyear={$ly}{$extraparams}")."'>{$ld}</a></td>";
		
		
		// Print month selector
		$out .= "</tr><tr><td class='tdbgh' colspan='7'></td></tr>
		<tr><td class='tdbg1 center fonts' colspan='7'>";
		for ($i = 1; $i < 13; ++$i) {
			$w = $month == $i ? "b" : "a";
			$out .= "<$w href='".actionlink(null,"?cday={$day}&cmonth={$i}&cyear={$year}{$extraparams}")."'>{$months[$i]}</$w> ";
		}
		$out .= "</td></tr>";
		
		// Print year selector
		$out .= "</tr><tr><td class='tdbg1 center fonts' colspan='7'>... ";
		for ($i = $year - 3; $i < $year + 4; ++$i) {
			$w = $year == $i ? "b" : "a";
			$out .= "<$w href='".actionlink(null,"?cday={$day}&cmonth={$month}&cyear={$i}{$extraparams}")."'>{$i}</$w> ";
		}
		$out .= "...</td></tr>";
		
		// Print filter reset link (to make it obvious when the filter is active)
		
		if (isset($_GET['cday']) || isset($_GET['cmonth']) || isset($_GET['cyear'])) {
			$out .= "
			<tr>
				<td class='tdbg2 center fonts' colspan='7'>
					Only posts from <b>{$day} {$months[$month]} {$year}</b> will be shown.<br/>
					Click <a href='".actionlink(null, "?$extraparams")."'>here</a> to reset the date filter.
				</td>
			</tr>";
		}
		$out .= "</table>";
		
		return $out;
	}
	function news_calendar_url() {
		$out = "";
		if (isset($_GET['cday']))
			$out .= "&cday={$_GET['cday']}";
		if (isset($_GET['cmonth']))
			$out .= "&cmonth={$_GET['cmonth']}";
		if (isset($_GET['cyear']))
			$out .= "&cyear={$_GET['cyear']}";
		return $out;
	}
?>