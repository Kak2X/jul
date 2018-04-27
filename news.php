<?php

	/*
		News Engine v0.3b -- 25/09/16
		
		DESCRIPTION:
		A news engine (read: alternate announcements page) that everybody can read, but only privileged or up can write.
		Any logged in user can create comments.
		The permission settings are stored in config.php
		(this is a test for forum integration)
	*/
	
	require "lib/function.php";
	require "lib/news_function.php";
	
	$id			= filter_int($_GET['id']);
	$page		= filter_int($_GET['page']);
	$usersort	= filter_int($_GET['user']);
	$ord		= filter_int($_GET['ord']);
	$filter		= filter_string($_GET['cat'], true);
	
	if (filter_string($_GET['search'])){
		$search		= filter_string($_GET['search'], true);
	} else if (isset($_POST['search'])){
		// Refreshing with _POST is bad
		redirect("?search=".urlencode($_POST['search']));
	} else {
		$search		= "";
	}
	
	$q_filter = "";
	if ($id) {
		$q_filter = "AND n.id = $id";
	} else{
		if ($filter){
			if (alphanumeric($filter) !== $filter){
				errorpage("Invalid characters in tag.");
				//header("Location: index.php?sec=1");
			}
			$q_filter = "AND n.cat REGEXP '(;|^)$filter(;|$)' "; // Changed to check first and last characters
		}
		if ($usersort){
			// Sort by user ID
			$q_filter .= "AND n.user = $usersort";
		}
	}

	
	/* 	Table name 	: news
		Columns		: id, user, name, text, cat, hide
	 "Cat" value is an *alphanumeric string* only used for filtering - this isn't normally used the main page
	 as such, using the \0 merge trick isn't necessary. multiple categories are delimited by ;
	 
	 "Hide" marks deleted news. These can only be seen by users with write privileges, not by guests
	*/
	
	news_header("Main page");
	
	// Notice: This does NOT store old news revisions yet. Maybe it will in the future...
	
	$q_where 	= "WHERE ".($canwrite ? "1" : "n.hide = 0")." $q_filter";
	$offset		= $page * $loguser['ppp'];
	
	$news = $sql->query("
		SELECT 	n.id, n.time, n.name newsname, n.text, n.lastedituser, n.lastedittime, n.cat, n.hide,
				$userfields uid, COUNT(c.id) comments
		FROM news n
		LEFT JOIN users         u ON n.user = u.id
		LEFT JOIN news_comments c ON n.id   = c.pid
		$q_where AND n.text LIKE '%".addslashes($search)."%'
		GROUP BY n.id
		ORDER BY n.time ".($ord ? "ASC" : "DESC")."
		LIMIT $offset, {$loguser['ppp']}
	");
	

	$newpost = $canwrite ? "<tr><td>Options:</td><td><a href='editnews.php?new'>New post</a></td></tr>" : "";
	$news_count	= $sql->resultp("SELECT COUNT(n.id) FROM news n $q_where AND INSTR(n.text, ?) > 0 ", [$search]);
	
	/*
		Number of posts (on this page)
	*/
	if (!$id){
		$foundres = "<div class='fonts w c'>Showing $news_count post".($news_count == 1 ? "" : "s")." in total".
			( /* all those little details I put here (that are making this code block bloated) are making me sad */
				$news_count > $loguser['ppp'] ?
				", from ".($offset + 1)." to ".($offset + $loguser['ppp'] > $news_count ? $news_count : $offset + $loguser['ppp'])." on this page" :
				""
			).".<br>Sorting from ".($ord ? "oldest to newest" : "newest to oldest").".</div>";
	} else {
		$foundres = "";
	}
	$pagectrl	= dopagelist($news_count, $loguser['ppp'], "news", "&cat=$filter&user=$usersort&search=$search");
	
	?>
	<br>
	<table>
		<tr>
			<td class='w' style='vertical-align: top; padding: 10px 40px 0px 40px'>
				
			<?php
			/*
				Posts
			*/
	
			echo $pagectrl;
			for($i = 0; $post = $sql->fetch($news); $i++){
				print news_format($post, (!$id), true)."<br><br>"; // Don't show the preview if you're viewing a specific post ID
			}
			if (!$i){
				?>
				<table class='main w c news-container'>
					<tr>
						<td>
							It looks like nothing was found. Do you want to try again?
						</td>
					</tr>
				</table>
				<?php
			}
			echo $pagectrl;
			
			?>
			</td>
			<!-- sorting options and search box -->
			<td style='vertical-align: top'>
				<form method='POST' action='?'>
				<table class='main w small-shadow'>
				
					<tr><td class='head c'>Options</td></tr>
					<tr>
						<td class='dark fonts'>
							<!-- we want an aligned layout for this! -->
							<table>
								<?php echo $newpost ?>
								<tr>
									<td rowspan=2 style='vertical-align: top'>Sorting:</td>
									<td>
										<a href='<?php echo "?ord=0&cat=$filter&user=$usersort&search=$search" ?>'>
											From newest to oldest
										</a>
									</td>
								</tr>
								<tr>
									<td>
										<a href='<?php echo "?ord=1&cat=$filter&user=$usersort&search=$search" ?>'>
											From oldest to newest
										</a>
									</td>
								</tr>
							</table>
						</td>
					</tr>
					
					<tr><td class='head c'>Search</td></tr>
					<tr>
						<td class='dim'>
							<input type='text' name='search' size=43 value='<?php echo htmlspecialchars($search) ?>'>
							<div style='padding: 3px'>
								<input type='submit' class='submit' name='dosearch' value='Search'>
							</div>
							<?php echo $foundres ?>	
						</td>
					</tr>
				</table>
				</form>
				<br>
				<table class='main w small-shadow'>
					<tr><td class='head c'>Tags</td></tr>
					<tr>
						<td style='padding: 4px'>
							<?php echo showtags() ?>
						</td>
					</tr>
				</table>
				<br>
				<table class='main w small-shadow'>
					<tr><td class='head c'>Latest comments</td></tr>
					<tr>
						<td class='fonts' style='padding: 4px'>
							<?php echo recentcomments(5) ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
	<?php
	
	pagefooter(true);
	
?>
