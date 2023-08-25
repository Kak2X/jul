<?php

	/*
		News Engine
		
		DESCRIPTION:
		A news engine with customizable settings.
		Test port of existing code to the extension system.

		Settings can be configured from the extension manager.
	*/
	
	require "lib/common.php";
	
	// Toggle the forced scheme?
	if (toggle_board_cookie($_GET['tglschrep'], 'news-noschrep')) {
		$params = preg_replace('/\&?tglschrep(=[0-9]+)/i','', $_SERVER['QUERY_STRING']);
		die(header("Location: news.php?{$params}"));
	}
	
	require "lib/news_function.php";
	
	$_GET['id']         = filter_int($_GET['id']);
	$_GET['user']       = filter_int($_GET['user']);
	$_GET['pin']        = filter_int($_GET['pin']); // Peek post ID
	$_GET['edit']       = filter_int($_GET['edit']); // Edit comment ID
	$_GET['cpin']       = filter_int($_GET['cpin']); // Peek comment ID
	
	// Tag filter
	$_GET['tag']        = filter_int($_GET['tag']);
	
	$_GET['cday']       = isset($_GET['cday']) ? (int)$_GET['cday'] : null;
	$_GET['cmonth']     = isset($_GET['cmonth']) ? (int)$_GET['cmonth'] : null;
	$_GET['cyear']      = isset($_GET['cyear']) ? (int)$_GET['cyear'] : null;
	
	// Search results
	$_GET['ord']        = filter_int($_GET['ord']);
	$_GET['search']     = filter_string($_GET['search']);
	$_GET['page']       = filter_int($_GET['page']);
	
	news_header("Main page");
	
	
	$tagfilter = $joins = $q_where = "";
	if ($_GET['id']) {
		// If only one post is selected, ignore all other filters
		$q_where = "WHERE n.id = ?";
		$vals    = array($_GET['id']);
		if (!$canwrite) 
			$q_where .= " AND n.deleted = 0";
	} else {
		
		$where = $vals = array();
		// Initial option for text search
		if ($_GET['search']) {
			$where[] = "n.text LIKE ?";
			$vals[]  = "%".mysql::filter_like_wildcards($_GET['search'])."%";
		}
		// Do not display deleted news to guests
		if (!$canwrite) 
			$where[] = "n.deleted = 0";
		// Filter by user
		if ($_GET['user'])
			$where[] = "n.user = {$_GET['user']}";
		// Filter by date
		if ($_GET['cday'] || $_GET['cmonth'] || $_GET['cyear']) {
			$from = mktime(0,0,0, $_GET['cmonth'], $_GET['cday'], $_GET['cyear']);
			if ($_GET['cday'] != 0) {
				$to = mktime(0,0,0, $_GET['cmonth'], $_GET['cday']+1, $_GET['cyear']);
			} else if ($_GET['cmonth'] != 0) {
				$to = mktime(0,0,0, $_GET['cmonth']+1, $_GET['cday'], $_GET['cyear']);
			} else {
				$to = mktime(0,0,0, $_GET['cmonth'], $_GET['cday'], $_GET['cyear']+1);
			}
			
			$where[] = "n.date > {$from} AND n.date < {$to}";
		}
		
		if ($where)
			$q_where = "WHERE ".implode(' AND ', $where);
		
		// Special filter since it should not be used on the $tags query
		if ($_GET['tag']) {
			$joins     .= "INNER JOIN news_tags_assoc a ON n.id = a.post";
			$tagfilter .= ($where ? " AND" : " WHERE")." a.tag = {$_GET['tag']}";
		}
		
		
		
	}
	
	// Get the total right away to possibly fix bad page numbers
	$total	= $sql->resultp("SELECT COUNT(*) FROM news n {$joins}{$q_where}{$tagfilter}", $vals);
	$ppp    = get_ppp();
	$pagelist = pagelist($url, $total, $ppp);
	
	$min = $_GET['page'] * $ppp;
	
	
	// Get the posts we need
	$news = $sql->queryp(set_avatars_sql("
		SELECT 	n.id, n.user, n.date, n.title, n.text, n.lastedituser, n.lasteditdate, n.deleted, n.nosmilies, n.nohtml, n.moodid,
				".set_userfields('u1')." {%AVFIELD%}, u1.id uid, ".set_userfields('u2').", COUNT(c.id) comments
		FROM news n
		LEFT JOIN users           u1 ON n.user         = u1.id
		LEFT JOIN users           u2 ON n.lastedituser = u2.id
		LEFT JOIN news_comments    c ON n.id           = c.pid AND c.deleted = 0
		{$joins}
		{%AVJOIN%}
		{$q_where}{$tagfilter}
		GROUP BY n.id
		ORDER BY n.date ".($_GET['ord'] ? "ASC" : "DESC")."
		LIMIT {$min}, {$ppp}
	", 'n'), $vals);
	
	// Tags have to be loaded separately
	$tagsq = $sql->queryp("
		SELECT a.post, t.id, t.title
		FROM news n
		INNER JOIN news_tags_assoc  a ON n.id  = a.post
		INNER JOIN news_tags        t ON a.tag = t.id
		{$q_where}
	", $vals);
	//$tags = $sql->fetchAll($tagsq, PDO::FETCH_GROUP);
	$tags = array();
	while ($x = $sql->fetch($tagsq))
		$tags[$x['post']][$x['id']] = $x;
		
	/*
		Number of posts (on this page)
	*/
	$foundres = "";
	if (!$_GET['id']){
		$foundres = "<div class='w center'>".
				"Showing {$total} post".($total == 1 ? "" : "s")." in total".
				($total > $ppp ? ", from ".($min + 1)." to ".min($total, $min + $ppp)." on this page" : "").".<br>".
				"Sorting from ".($_GET['ord'] ? "oldest to newest" : "newest to oldest").".".
			"</div>";
	}
	
	$url = actionlink("news.php?tag={$_GET['tag']}&user={$_GET['user']}".news_calendar_url());
	
?>
	<div>
		<div class="news-list">
			<?php
			if (!$sql->num_rows($news)) {
				?>
				<table class='table news-container'>
					<tr><td class="tdbg2 center">It looks like nothing was found. Do you want to try again?</td></tr>
				</table>
				<?php
			} else {
				print $pagelist;
				while ($post = $sql->fetch($news)) {
					$post['tags']         = filter_array($tags[$post['id']]);
					$post['userdata']     = get_userfields($post, 'u1');
					$post['edituserdata'] = get_userfields($post, 'u2');
					print news_format($post, !$_GET['id'], $_GET['pin'])."<br>";
					if ($_GET['id']) {
						if ($loguser['id']) {
							print news_comment_editor(null, $_GET['id'])."<br>";
							replytoolbar('nwedit', readsmilies());
						}
						print news_comments($_GET['id'], $post['user'], $_GET['edit'], 0, $_GET['cpin']);
					}
				}
				print $pagelist;
			}
			?>
		</div>
		<div class="news-options">
		<!-- sorting options and search box -->
<?php			if ($canwrite) { ?>
			<table class='table fonts small-shadow'>
				<tr><td class='tdbgh center i'>Special Controls Box</td></tr>
				<tr><td class="tdbg2"><a href="<?=actionlink("news-editpost.php?new")?>">New post</a></td></tr>
			</table>
			<br> 
<?php			} ?>
			<?= news_calendar("&search={$_GET['search']}&ord={$_GET['ord']}&tag={$_GET['tag']}&user={$_GET['user']}") ?>
			<br/>
			<form method='GET' action="<?= $url ?>">
			<table class='table fonts small-shadow'>
				<tr><td class="tdbgh center" colspan=2>Search</td></tr>
				<tr>
					<td class="tdbg1 center b">Text:</td>
					<td class="tdbg2">
						<input type='text' name='search' class='w' value="<?= htmlspecialchars($_GET['search']) ?>">
					</td>
				</td>
				<tr>
					<td class="tdbg1 center b">Sorting:</td>
					<td class="tdbg2">
						<label><input type="radio" name="ord" value=0<?= ($_GET['ord'] == 0 ? " checked" : "")?>> Newest to oldest</label>
					</td>
				</td>
				<tr>
					<td class="tdbg1 center b"></td>
					<td class="tdbg2">
						<label><input type="radio" name="ord" value=1<?= ($_GET['ord'] == 1 ? " checked" : "")?>> Oldest to newest</label>
					</td>
				</td>
				<!--<tr>
					<td class="tdbg1 center b">Page:</td>
					<td class="tdbg2"><?= $pagelist ?></td>
				</tr> -->
				<tr>
					<td class="tdbg1"></td>
					<td class="tdbg2"><input type='submit' value='Search'></td>
				</tr>
				<tr><td class="tdbgh center b" colspan=2></td></tr>
				<tr><td class='tdbg1' colspan=2><?= $foundres ?></td></tr>
			</table>
			</form>
			<br>
			<table class='table fonts small-shadow'>
				<tr><td class='tdbgh center'>Options</td></tr>
				<tr><td class="tdbg2">
				<a href="<?="{$url}&tglschrep=1"?>"><?=($_COOKIE['news-noschrep'] ? "Use special scheme file" : "Use board scheme file")?></a>
				</td></tr>
			</table>
			<br>
			<table class='table fonts small-shadow'>
				<tr><td class='tdbgh center'>Tags</td></tr>
				<tr><td class="tdbg2 tag-list"><?= main_news_tags(15) ?></td>
				</tr>
			</table>
			<br>
			<table class='table fonts small-shadow'>
				<tr><td class='tdbgh center'>Latest comments</td></tr>
				<tr><td class="tdbg2 comment-list"><?= recentcomments(5) ?></td>
				</tr>
			</table>
		</div>
	</div>
<?php
	
	news_footer();
