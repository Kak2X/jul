<?php

	/*
		News Engine v0.4 -- 06/05/18
		
		DESCRIPTION:
		A news engine (read: alternate announcements page) that everybody can read, but only privileged or up can write.
		Any logged in user can create comments.
		The permission settings are stored in config.php
		(this is a test for forum integration)
	*/
	
	require "lib/function.php";
	require "lib/news_function.php";
	
	$_GET['id']         = filter_int($_GET['id']);
	$_GET['user']       = filter_int($_GET['user']);
	$_GET['pin']        = filter_int($_GET['pin']); // Peek post ID
	$_GET['edit']       = filter_int($_GET['edit']); // Edit comment ID

	// Tag filter
	$_GET['tag']        = filter_int($_GET['tag']);
	
	// Search results
	$_POST['ord']       = filter_int($_POST['ord']);
	$_POST['search']    = filter_string($_POST['search']);
	$_POST['page']      = filter_int($_POST['page']);
	
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
		if ($_POST['search']) {
			$where[] = "n.text LIKE ?";
			$vals[]  = "%".escape_like_wildcards($_POST['search'])."%";
		}
		// Do not display deleted news to guests
		if (!$canwrite) 
			$where[] = "n.deleted = 0";
		// Filter by user
		if ($_GET['user'])
			$where[] = "n.user = {$_GET['user']}";
		
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
	$pagelist = page_select($total, $ppp);
	
	$min = $_POST['page'] * $ppp;
	
	
	// Get the posts we need
	$news = $sql->queryp("
		SELECT 	n.id, n.user, n.date, n.title, n.text, n.lastedituser, n.lasteditdate, n.deleted, n.nosmilies, n.nohtml,
				".set_userfields('u1').", ".set_userfields('u2').", COUNT(c.id) comments
		FROM news n
		LEFT JOIN users           u1 ON n.user         = u1.id
		LEFT JOIN users           u2 ON n.lastedituser = u2.id
		LEFT JOIN news_comments    c ON n.id           = c.pid AND c.deleted = 0
		{$joins}
		{$q_where}{$tagfilter}
		GROUP BY n.id
		ORDER BY n.date ".($_POST['ord'] ? "ASC" : "DESC")."
		LIMIT {$min}, {$ppp}
	", $vals);
	
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
		$foundres = "<div class='fonts w center'>".
				"Showing {$total} post".($total == 1 ? "" : "s")." in total".
				($total > $ppp ? ", from ".($min + 1)." to ".min($total, $min + $ppp)." on this page" : "").".<br>".
				"Sorting from ".($_POST['ord'] ? "oldest to newest" : "newest to oldest").".".
			"</div>";
	}
	
	$url = "?tag={$_GET['tag']}&user={$_GET['user']}";
	
	?>
	<br>
	<table>
		<tr>
			<td class='w' style='vertical-align: top; padding: 10px 40px 0px 40px'>
			<?php
				if (!$sql->num_rows($news)) {
					?>
					<table class='table news-container'>
						<tr><td class="tdbg2 center">It looks like nothing was found. Do you want to try again?</td></tr>
					</table>
					<?php
				} else while ($post = $sql->fetch($news)) {
					$post['tags'] = $tags[$post['id']];
					$post['userdata']     = get_userfields($post, 'u1');
					$post['edituserdata'] = get_userfields($post, 'u2');
					print news_format($post, !$_GET['id'], $_GET['pin'])."<br>";
					if ($_GET['id'])
						print news_comments($_GET['id'], $post['user'], $_GET['edit']);
				}
			?>
				<br>
				<br>
			</td>
			<!-- sorting options and search box -->
			<td style='vertical-align: top'>
<?php			if ($canwrite) { ?>
				<table class='table fonts small-shadow'>
					<tr><td class='tdbgh center'>Options</td></tr>
					<tr><td><a href='news-editpost.php?new'>New post</a></td>
					</tr>
				</table>
				<br> 
<?php			} ?>
				<form method='POST' action="<?= $url ?>">
				<table class='table fonts small-shadow'>
					<tr><td class="tdbgh center" colspan=2>Search</td></tr>
					<tr>
						<td class="tdbg1 center b">Text:</td>
						<td class="tdbg2">
							<input type='text' name='search' size=40 value="<?= htmlspecialchars($_POST['search']) ?>">
						</td>
					</td>
					<tr>
						<td class="tdbg1 center b">Sorting:</td>
						<td class="tdbg2">
							<label><input type="radio" name="ord" value=0<?= ($_POST['ord'] == 0 ? " checked" : "")?>> Newest to oldest</label>
						</td>
					</td>
					<tr>
						<td class="tdbg1 center b"></td>
						<td class="tdbg2">
							<label><input type="radio" name="ord" value=1<?= ($_POST['ord'] == 1 ? " checked" : "")?>> Oldest to newest</label>
						</td>
					</td>
					<tr>
						<td class="tdbg1 center b">Page:</td>
						<td class="tdbg2"><?= $pagelist ?></td>
					</tr>
					<tr>
						<td class="tdbg1"></td>
						<td class="tdbg2"><input type='submit' name='dosearch' value='Search'></td>
					</tr>
					<tr><td class="tdbgh center b" colspan=2></td></tr>
					<tr><td class='tdbg1' colspan=2><?= $foundres ?></td></tr>
				</table>
				</form>
				<br>
				<table class='table fonts small-shadow'>
					<tr><td class='tdbgh center'>Tags</td></tr>
					<tr><td style='padding: 4px'><?= main_news_tags(15) ?></td>
					</tr>
				</table>
				<br>
				<table class='table fonts small-shadow'>
					<tr><td class='tdbgh center'>Latest comments</td></tr>
					<tr><td class='fonts' style='padding: 4px'><?= recentcomments(5) ?></td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	<?php
	
	news_footer();
