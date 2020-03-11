<?php
	require "lib/function.php";
	
	if (!$loguser['id'])
		errorpage("You need to be logged in to do this.", 'login.php', "log in (then try again)");
	if (!$config['enable-post-ratings'] || $loguser['rating_locked'])
		errorpage("Sorry, but you can't rate posts. Either post rating is disabled or you've been restricted from rating posts.");
	
	$_GET['post']   = filter_int($_GET['post']);
	$_GET['rating'] = filter_int($_GET['rating']);
	$_GET['type']   = filter_string($_GET['type']);
	$_GET['action'] = filter_string($_GET['action']);
	
	if (!$_GET['post'] || !$_GET['type'])
		errorpage("Required URL arguments missing.");
	
	
	// Check if we can view the thread the post is in
	if ($_GET['type'] == 'pm') {
		$post = $sql->fetchq("SELECT thread, user FROM pm_posts WHERE id = {$_GET['post']}");
		load_pm_thread($post['thread']);
		$mode = MODE_PM;
		$redirpage = "showprivate";
	} else {
		$post = $sql->fetchq("SELECT thread, user FROM posts WHERE id = {$_GET['post']}");
		load_thread($post['thread']);
		$mode = MODE_POST;
		$redirpage = "thread";
	}
	
	if (isset($thread['error']))
		errorpage("Cannot rate posts in broken threads.");
	
	if ($_GET['action'] == 'delete' && $ismod) {
		check_token($_GET['auth'], TOKEN_MGET);
		delete_post_rating(filter_int($_GET['u']), $_GET['post'], filter_int($_GET['r']), $mode);
		return header("Location: ?action=view&type={$_GET['type']}&post={$_GET['post']}");
	} else if ($_GET['action'] == 'rate') {
		check_token($_GET['auth'], TOKEN_VOTE);
		if ($post['user'] != $loguser['id'] || $isadmin) // Can't vote yourself
			rate_post($_GET['post'], $_GET['rating'], $mode);
		return header("Location: {$redirpage}.php?pid={$_GET['post']}#{$_GET['post']}");
	} else if ($_GET['action'] == 'view' && $ismod) {
		$ratings  = get_ratings(true);
		$ratedata = get_post_ratings($_GET['post'], $mode);
		$tokenstr = "&auth=".generate_token(TOKEN_MGET);
		
		
		if ($mode == MODE_PM) {
			$ftitle = "Private messages";
			$furl   = "private.php";
		} else {
			$ftitle = $forum['title'];
			$furl   = "forum.php?id={$forum['id']}";
		}
		
		$links = array(
			[$ftitle                             , $furl],
			[$thread['title']                    , "{$redirpage}.php?id={$thread['id']}"],
			["Ratings for post #{$_GET['post']}" , NULL],
		);
		$barlinks = dobreadcrumbs($links);
		
		pageheader("Rating details");
		
		print "
		<style type='text/css'>
			.rating-table {}
			.rating-table-sect {
				/* float: left; */
				display: inline-block;
				margin: 5px;
			}
			.rating-table-sect,.rating-table-userlist {
				width: 200px;
			}
			.rating-table-userlist {
				height: 150px;
				vertical-align: top;
			}
			.rating-div-userlist {
				overflow-y: auto;
				height: 100%;
			}
		</style>
		{$barlinks}
		<table class='table rating-table'>
			<tr><td class='tdbgh center b'>Rating details for post #{$_GET['post']}</td></tr>
			<tr><td class='tdbg2'><center>";
		foreach ($ratings as $id => $data) {
			// Users who rated the post with that rating, one for each line
			$userlist = "";
			if (isset($ratedata[$id]))
				foreach ($ratedata[$id] as $user)
					$userlist .= "<div>".getuserlink($user, $user['uid'])." <a href='?action=delete&type={$_GET['type']}&post={$_GET['post']}&r={$id}&u={$user['uid']}{$tokenstr}' style='float: right' title='Delete rating'>[X]</a></div>";
			print "
			<table class='table rating-table-sect'>
				<tr><td class='tdbgh center'><img src=\"".escape_attribute($data['image'])."\"> ".htmlspecialchars($data['title'])." <span style='float: right'>".rating_colors("[{$data['points']}]", $data['points'])."</span></td></tr>
				<tr><td class='tdbgc center fonts'>".xssfilters($data['description'])."</td></tr>
				<tr><td class='tdbg1 rating-table-userlist'><div class='rating-div-userlist'>{$userlist}</div></td></tr>
			</table>";
		}
		print "
			</center></td></tr>
		</table>
		{$barlinks}";
	} else {
		errorpage("No.");
	}
	
	pagefooter();