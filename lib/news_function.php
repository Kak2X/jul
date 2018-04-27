<?php
	die("This is a file which used to be part of the boardc news engine.<br>The news engine hasn't been ported back yet.");
	/*
		Functions used by the news "plugin"
	*/
	
	define('NEWS_VERSION', "v0.3b -- 25/09/16");
	
	if (!$config['enable-news']){
		header("Location: index.php");
		x_die();
	}
	// Apply config.php news settings
	//$config['board-name'] 	= $config['news-name'];
	//$config['board-title'] 	= $config['news-title'];
	//$config['board-url']	= "news.php";
	// Load permissions
	if ($isbanned) $loguser['id'] = 0; // oh dear
	$isadmin	= ($loguser['id'] && $loguser['powerlevel'] >= $config['news-admin-perm']);
	$canwrite	= ($loguser['id'] && $loguser['powerlevel'] >= $config['news-write-perm']);
	
	// FORCED THEME
	$miscdata['theme'] = 5;
	
	// Not truly alphanumeric as it also allows spaces
	function alphanumeric($text){return preg_replace('/[^\da-z ]/i', '', $text);}
	function newserrorpage($text){news_header("Error");echo "<br><br><table class='main w c'><tr><td>$text</td></tr></table>";pagefooter(true);}
	
	function news_header($title){
		global $config;
		echo "<!doctype html>"; // We need to print this here, since the "hide header" flag in pageheader() doesn't print a doctype
		pageheader("$title - {$config['news-name']}", false, 0, true);
		?>
		<center>
		<table class='main c top-header'>
			<tr>
				<td class='nobr header-title-td'>
					<h1><a href='news.php' class='header-title'><?php echo $config['news-title'] ?></a></h1>
				</td>
			</tr>
			<tr>
				<td>
					<a class='header-link' href='/board'>boardc</a> - 
					<a class='header-link' href='/altboard'>altboard</a> - 
					<a class='header-link' href='http://jul.rustedlogic.net'>jul</a> - 
					<a class='header-link' href='http://board.kafuka.org'>kafuka</a>
					
				</td>
			</tr>
		</table>
		</center>
		<?php
	}
	
	function news_format($data, $preview = false, $showcomment = false){
		/*
			threadpost() replacement as the original function obviously wouldn't work for this
		*/
		global $loguser, $config, $isadmin, $sql, $userfields;
		global $page, $filter, $search, $token;
		static $theme;
		
		if (!isset($theme)){
			$theme = "new-post";
		} else {
			$theme = "";
		}
		
		$comment_txt = "";
		
		if ($preview){
			// Get message length to shrink it if it's a preview
			$charcount = strlen($data['text']);
			if ($charcount > $config['max-preview-length']){
				$data['text'] = news_preview($data['text'], $charcount)."...";//substr($data['text'], 0, $config['max-preview-length']-3)."...";
				$text_shrunk = TRUE;
			}
		} else {
			// Display comments
			if ($showcomment){
				$comments = $sql->query("
					SELECT c.*, $userfields uid
					FROM news_comments c
					LEFT JOIN users u ON c.user = u.id
					WHERE c.pid = {$data['id']}
					".($isadmin ? "" : "AND c.hide = 0")."
					ORDER BY c.id DESC
				");
				
				$comment_txt = "
					<br><br><br>
					<table class='main w small-shadow'>";
				
				if ($loguser['id'] && !isset($_GET['edit'])){
				$comment_txt .= "
						<tr>
							<td class='head bold c' colspan=2>New comment</td>
						</tr>
						<tr>
							<td colspan=2>
							<form method='POST' action='editcomment.php?act=new&id={$data['id']}'>
								<input type='hidden' name='auth' value='$token'>
								<textarea name='text' rows='3' style='resize:vertical; width: 850px' wrap='virtual'></textarea><br>
								<input type='submit' class='submit' name='submit' value='Submit comment'>
							</form>
							</td>
						</tr>";
				}
				
				$comment_txt .= "
						<tr>
							<td class='head bold c' colspan=2>Comments</td>
						</tr>";
				
				$doedit = filter_int($_GET['edit']);
				
				while ($c = $sql->fetch($comments)){
					
					$editlink = "";
					if ($isadmin || $loguser['id'] == $c['uid']){
						$editlink = "<a href='?id={$data['id']}&edit={$c['id']}#edit'>Edit</a>-".
									"<a href='editcomment.php?act=del&id={$c['id']}&auth=$token'>".($c['hide'] ? "Und" : "D")."elete</a>";
						$editcomment = ($doedit == $c['id']);
					} else {
						$editcomment = false;
					}
					if ($isadmin) $editlink .= "-<a class='danger' href='editcomment.php?act=erase&id={$c['id']}'>Erase</a>";
					
					// Edited posts are intentionally "marked" by a <br> tag (in part for readability)
					if ($c['lastedituser']) $extra = "<br>(Last edited by ".makeuserlink($c['lastedituser'])." at ".printdate($c['lastedittime']).")";
					else $extra = "";
					$comment_txt .= "
						<tr id='".($editcomment ? "edit" : $c['id'])."'>
							<td class='comment-userbar nobr'>".makeuserlink($c['uid'], $c, true).($c['uid'] == $data['uid'] ? " [S]" : "")."</td>
							<td class='comment-userbar r fonts'>$editlink ".printdate($c['time'])."$extra</td>
						</tr>";
						
					// Print edit textbox
					if ($editcomment){
						$comment_txt .= "
						<tr>
							<td colspan=2>
							<form method='POST' action='editcomment.php?act=edit&id={$c['id']}'>
								<input type='hidden' name='auth' value='$token'>
								<textarea name='text' rows='3' style='resize:vertical; width: 850px' wrap='virtual'>".htmlspecialchars($c['text'])."</textarea><br>
								<input type='submit' class='submit' name='doedit' value='Edit comment'>
							</form>
							</td>
						</tr>
						";						
					} else {
						$comment_txt .= "
						<tr>
							<td class='w' colspan=2>".output_filters($c['text'], true)."</td>
						</tr>";
					}
				}
				$comment_txt .= "</table>";
			}
		}
		
		$viewfull = isset($text_shrunk) ? "<tr><td class='fonts'>To read the full text, click <a href='news.php?id=".$data['id']."'>here</a>.</td></tr>" : "";
		
		$data['id'] = filter_int($data['id']);
		
		if ($data['id']){
			if ($isadmin || $loguser['id'] == $data['uid'])
				$editlink = "<a href='editnews.php?id=".$data['id']."&edit'>Edit</a>-<a href='editnews.php?id=".$data['id']."&del&auth=$token'>".($data['hide'] ? "Und" : "D")."elete</a>";
			else $editlink = "";
			
			if ($isadmin) $editlink .= "-<a class='danger' href='editnews.php?id=".$data['id']."&kill'>Erase</a>";
		}
		else $editlink = "";
		
		$lastedit = filter_int($data['lastedituser']) 
			? " (Last edited by ".makeuserlink($data['lastedituser'])." at ".printdate($data['lastedittime']).")" 
			: "";
		
		$usersort = "<a href='news.php?user=".$data['uid']."'>View all by this user</a>";
		
		
		return "
		<input type='hidden' name='id' value={$data['id']}>
		<table class='main w news-container $theme'>
			<tr>
				<td class='head w' colspan=2>
					<table style='border-spacing: 0'>
						<tr>
							<td class='nobr'>
								<a href='news.php?id={$data['id']}' class='headlink'>{$data['newsname']}</a>
							</td>
							<td class='fonts w r'>
								$editlink ".printdate($data['time'])."<br>
								$lastedit ".makeuserlink($data['uid'], $data)."
							</td>
						</tr>
					</table>
					<!--<hr/>-->
				</td>
			</tr>
			
			<tr><td class='dim' style='padding-bottom: 12px'>".output_filters($data['text'], true)."</td></tr>
			$viewfull
			<tr class='fonts light w'>
				<td>Comments: {$data['comments']}</td>
				<td class='nobr r'>$usersort</td>
			</tr>
			<tr>
				<td class='fonts light w' colspan=2>
					Tags: ".tag_format($data['cat'])."
				</td>
			</tr>
		</table>
		$comment_txt
		";
		
	}
	
	function news_preview($text, $length = NULL){
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
	
	function tag_format($list, $tags = false){
		if (!$tags) $tags = explode(";", $list);
		foreach($tags as $tag)
			$text[] = "<a href='news.php?cat=$tag'>$tag</a>";
		return implode(", ", $text);
	}
	
	// Return a list of tags, sorted by most used
	function showtags(){
		global $sql;
		$cats = $sql->query("SELECT cat FROM news");
		foreach($cats as $cat){
			$x = explode(";", $cat['cat']);
			foreach($x as $y){
				if (isset($catlist[$y])) $catlist[$y]++;
				else 					 $catlist[$y] = 1;
			}
		}
		
		if (!isset($catlist)) return "";
		
		arsort($catlist);
		$max = max($catlist);
		// Prevent exceeding max number of tags
		$cnt = count($catlist);
		if ($cnt > 15) $cnt = 15;
		
		$txt 	= "";
		$i = 0;
		foreach($catlist as $tag => $num){
			if ($i > $cnt) break;
			$px = 10 + round(pow($num/$cnt*100, 0.7));
			$txt .= "<a class='nobr tag-links' href='news.php?cat=$tag' style='font-size: {$px}px'>$tag</a> ";
			$i++;
		}
		
		return $txt;
	}
	
	function recentcomments($limit){
		global $sql, $userfields;
		//List with latest 5 (or 10?) comments showing user and thread
		// should use IF and log editing
		$list = $sql->query("
			SELECT c.user, c.id, c.time, c.pid, n.name title, $userfields uid
			FROM news_comments c
			INNER JOIN news  n ON c.pid  = n.id
			INNER JOIN users u ON c.user = u.id
			WHERE c.hide = 0
			ORDER BY c.time DESC
			LIMIT $limit
		");
		
		$txt = "";
		while($c = $sql->fetch($list)){
			$txt .= "
				<table class='light w' style='border-spacing: 0'>
					<tr><td>".makeuserlink($c['uid'], $c)."</td><td class='r'>".printdate($c['time'])."</td></tr>
					<tr><td colspan=2><a href='news.php?id={$c['pid']}#{$c['id']}'>Comment</a> posted on <a href='news.php?id={$c['pid']}'>".htmlspecialchars($c['title'])."</a></td></tr>
				</table><div style='height: 5px'></div>";
		}
		return $txt;
	}
	
?>