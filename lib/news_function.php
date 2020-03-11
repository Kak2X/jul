<?php
	
	const NEWS_VERSION = "v0.4b -- 12/07/19";
	const SHOW_SPECIAL_HEADER = true;
	
	if (!$config['enable-news']){
		return header("Location: index.php");
	}
	
	// Load "permissions"
	if ($banned) $loguser['id'] = 0; // oh dear
	$ismod	    = ($loguser['id'] && $loguser['powerlevel'] >= $config['news-admin-perm']);
	$canwrite	= ($loguser['id'] && $loguser['powerlevel'] >= $config['news-write-perm']);
	
	// override absolutely any forced scheme
	// necessary because this scheme contains news-specific CSS missing from other schemes
	$miscdata['scheme'] = 211;
	
	// Not truly alphanumeric as it also allows spaces
	function alphanumeric($text) { return preg_replace('/[^\da-z ]/i', '', $text); }
	function escape_like_wildcards($text) { return strtr($text, array('%' => '\\%', '_' => '\\_')); }
	function news_errorpage($text) {
		if (!defined('HEADER_PRINTED'))	news_header("Error"); 
		print "<table class='table news-container'><tr><td class='tdbg2 center'>{$text}</td></tr></table>";
		//print "<table class='table'><tr><td class='tdbg1 center'>{$text}</td></tr></table>";
		news_footer();
	}
	
	function news_header($title) {
		if (SHOW_SPECIAL_HEADER) {
			global $config;
			print "<!doctype html>"; // We need to print this here, since the "deleted header" flag in pageheader() doesn't print a doctype
			pageheader($title, NULL, NULL, true);
			?>
			<center>
			<table class='table top-header'>
				<tr>
					<td class='center nobr header-title-td'>
						<h1><a href='news.php' class='header-title'><?= $config['news-title'] ?></a></h1>
					</td>
				</tr>
				<tr>
					<td class='center'>
						<a class='header-link' href='index.php'>board</a> - 
						<a class='header-link' href='/'>the index</a>
					</td>
				</tr>
			</table>
			</center>
			<?php
		} else {
			pageheader($title);
		}
	}
	
	function news_footer() {
		if (SHOW_SPECIAL_HEADER) {
			?>
			<br>
			<center>
			<table class='table center news-container new-post' style='padding: 5px 50px; width: 400px'>
				<tr>
					<td>
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
		global $loguser, $config, $ismod, $sysadmin, $sql, $userfields;
		
		
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
			if ($charcount > $config['max-preview-length']){
				$post['text'] = news_preview($post['text'], $charcount)."...";
				$text_shrunk = true;
			}
		}
		
		$editlink = $lastedit = $viewfull = "";
		// Preview to view full post
		if ($text_shrunk)
			$viewfull = "<tr><td class='fonts'>To read the full text, click <a href='news.php?id={$post['id']}'>here</a>.</td></tr>";
		
		// Post controls
		
		if ($post['id']) {
			if ($ismod || $loguser['id'] == $post['user']) {
				$editlink = "<a href='news-editpost.php?id={$post['id']}&edit'>Edit</a>";
				if ($post['deleted'])
					$editlink .= " - <a href='news-editpost.php?id={$post['id']}&del'>Undelete</a> - <a href='?id={$post['id']}&pin={$post['id']}'>Peek</a>";
				else
					$editlink .= " - <a href='news-editpost.php?id={$post['id']}&del'>Delete</a>";
			}				
			if ($sysadmin) 
				$editlink .= " - <a class='danger' href='news-editpost.php?id={$post['id']}&erase'>Erase</a>";
		}
		
		if (filter_int($post['lastedituser'])) {
			$lastedit     = " (Last edited by ".getuserlink($post['edituserdata'], $post['lastedituser'])." at ".printdate($post['lasteditdate']).")";
		}
		$usersort = "<a href='news.php?user={$post['user']}'>View all by this user</a>";
		
		$hideondel = ($post['deleted'] && $post['id'] != $pin);
		if ($hideondel) {
			$post['text'] = "<i>(post deleted)</i>";
			//$post['title'] = "<s>{$post['title']}</s>";
		}
		
		return "
		<input type='hidden' name='id' value={$post['id']}>
		<table class='table news-container {$theme}'>
			<tr>
				<td class='tdbgh' colspan=2>
					<table class='w' style='border-spacing: 0'>
						<tr>
							<td class='nobr'>
								<a href='news.php?id={$post['id']}' class='headlink'>".htmlspecialchars($post['title'])."</a>
							</td>
							<td class='fonts right'>
								$editlink ".printdate($post['date'])."<br>
								$lastedit ".getuserlink($post['userdata'], $post['user'])."
							</td>
						</tr>
					</table>
				</td>
			</tr>
			
			<tr><td class='tdbg2' style='padding-bottom: 12px' colspan='2'>".dofilters(doreplace2($post['text'], "{$post['nosmilies']}|{$post['nohtml']}"))."</td></tr>
			$viewfull
			".($hideondel ? "" : "
			<tr class='tdbg1 fonts'>
				<td>Comments: {$post['comments']}</td>
				<td class='nobr right'>$usersort</td>
			</tr>
			<tr><td class='tdbg1 fonts' colspan=2>Tags: ".news_tag_format($post['tags'])."</td></tr>
			")."
		</table>";
		
	}
	
	// Display the comment section for any given post
	function news_comments($post, $author, $edit = 0) {
		global $sql, $loguser, $ismod, $sysadmin, $userfields;
		$token = generate_token(TOKEN_MGET);
		
		$comments = $sql->query("
			SELECT c.*, ".set_userfields('u1').", ".set_userfields('u2')."
			FROM news_comments c
			LEFT JOIN users u1 ON c.user         = u1.id
			LEFT JOIN users u2 ON c.lastedituser = u2.id
			WHERE c.pid = {$post} ".($ismod ? "" : "AND c.deleted = 0")."
			ORDER BY c.id DESC
		");
		
		$txt = "";
		while ($x = $sql->fetch($comments)){
			// Check if we are editing this comment (and if we can do so)
			$editlink = $lastedit = $editcomment = "";
			if ($ismod || $loguser['id'] == $x['user']) {
				$editlink = "<a href='?id={$post}&edit={$x['id']}#{$x['id']}'>Edit</a> - ".
							"<a href='news-editcomment.php?act=del&id={$x['id']}&auth={$token}'>".($x['deleted'] ? "Und" : "D")."elete</a>";
				$editcomment = ($edit == $x['id']);
			}
			
			if ($sysadmin) 
				$editlink .= " - <a class='danger' href='news-editcomment.php?act=erase&id={$x['id']}'>Erase</a>";
			
			$author = getuserlink(get_userfields($x, 'u1'), $x['user']);
			if ($x['deleted'])
				$author = "<s>{$author}</s>";
			if ($x['lastedituser'])
				$lastedit = "<br>(Last edited by ".getuserlink(get_userfields($x, 'u2'), $x['lastedituser'])." at ".printdate($x['lasteditdate']).")";
			
			// Display comment info (comments by the post author marked with [S])
			$txt .= "
				<tr id='{$x['id']}'>
					<td class='comment-userbar nobr'>{$author}".($x['user'] == $author ? " [S]" : "")."</td>
					<td class='comment-userbar right fonts'>{$editlink} ".printdate($x['date'])."{$lastedit}</td>
				</tr>";
				
			// Display the actual message; print edit textbox instead if in edit mode
			if ($editcomment) {
				$txt .= "
				<tr>
					<td colspan=2>
					<form method='POST' action='news-editcomment.php?act=edit&id={$x['id']}'>
						<textarea name='text' rows='3' style='resize:vertical; width: 850px' wrap='virtual'>".htmlspecialchars($x['text'])."</textarea><br>
						<input type='submit' name='doedit' value='Edit comment'>
						".auth_tag()."
					</form>
					</td>
				</tr>";						
			} else {
				$txt .= "<tr><td class='w' colspan=2>".dofilters(doreplace2($x['text'], "0|0"))."</td></tr>";
			}
		}
		//$comment_txt .= "</table>";

		// Do not show new comment area if we're editing a comment
		$newcomment = "";
		if ($loguser['id'] && !$edit) {
			$newcomment .= "
			<tr><td class='tdbgh center b' colspan=2>New comment</td></tr>
			<tr>
				<td class='tdbg2' colspan=2>
					<form method='POST' action='news-editcomment.php?post={$post}'>
						<textarea name='text' rows='3' style='resize:vertical; width: 850px' wrap='virtual'></textarea>
						<br><input type='submit' name='submit' value='Submit comment'>".auth_tag()."
					</form>
				</td>
			</tr>";
		}
		
		return "
		<table class='table small-shadow'>
			{$newcomment}
			<tr><td class='tdbgh center b' colspan=2>Comments</td></tr>
			{$txt}
		</table>";
	}
	
	function news_preview($text, $length = NULL){
		// TODO: FIX THIS
		/*
			news_preview: shrinks a string without leaving open HTML tags
			currently this doesn't allow to use < signs, made worse by the board unescaping &lt; entities
		*/
		global $config;
		if (!isset($length)) $length = strlen($text);
		
		/*
			Reference:
				$i 			- character index
				$res 		- result that will be returned
				$buffer 	- contains the text. if a space is found and the text isn't inside a tag it will append its contents to $res
				$opentags 	- keeps count of open HTML tags
				$intag		- marks if a text is inside a tag
		
		*/
		
		for($i = 0, $res = "", $buffer = "", $opentags = 0, $intag = false; $i < $length && $i < $config['max-preview-length']; $i++){
			
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
			", 'id');
		} else {
			return $sql->getarraybykey("
				SELECT t.id, t.title, COUNT(*) cnt
				FROM news_tags t
				LEFT JOIN news_tags_assoc a ON t.id = a.tag
				GROUP BY t.id
				ORDER BY cnt DESC
				".($limit ? "LIMIT {$limit}" : "")."
			", 'id');
		}
	}
	
	function news_tag_format($tags){
		$text = array();
		foreach($tags as $id => $data)
			$text[] = "<a href='news.php?tag={$id}'>".htmlspecialchars($data['title'])."</a>";
		return implode(", ", $text);
	}
	
	
	function main_news_tags($num){
		global $sql;
		$tags = load_news_tags(0, $num); // Grab 15 most used tags, in order
		$total = count($tags);
		
		$txt 	= "";
		foreach($tags as $id => $data){
			$px = 10 + round(pow($data['cnt'] / $total * 100, 0.7)); // Gradually decreate font size
			$txt .= "<a class='nobr tag-links' href='news.php?tag={$id}' style='font-size: {$px}px'>".htmlspecialchars($data['title'])."</a> ";
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
						<td class='tdbg1'>".getuserlink($x, $x['uid'])."</td>
						<td class='right'>".printdate($x['date'])."</td>
					</tr>
					<tr>
						<td class='tdbg1' colspan=2>
							<a href='news.php?id={$x['pid']}#{$x['id']}'>Comment</a> posted on <a href='news.php?id={$x['pid']}'>".htmlspecialchars($x['title'])."</a>
						</td>
					</tr>
				</table>
				<div style='height: 5px'></div>";
		}
		return $txt;
	}
	
?>