<?php

// XenForo-like post ratings, except nowhere near as good
// :(

function get_ratings($all = false) {
	global $sql, $loguser;
	return $sql->fetchq("SELECT ".($all ? "*" : "id, image, title, enabled, minpower")." FROM ratings ORDER BY id ASC", PDO::FETCH_UNIQUE, mysql::FETCH_ALL | mysql::USE_CACHE);
}

// Post rating HTML (rating list & post selection)
function ratings_html($post, $ratedata = array(), $mode = MODE_POST) {
	global $ismod, $loguser;
	static $modes = [MODE_POST => "post", MODE_PM => "pm", MODE_ANNOUNCEMENT => "annc"];
	$list = $vote = $sneak = "";
	$tokenstr = "&auth=".generate_token(TOKEN_VOTE);
	$typestr  = "&type=".$modes[$mode];
	$canrate  = ($loguser['id'] && !$loguser['rating_locked']);
	
	// Enumerate ratings and display the list
	$ratings = get_ratings();
	foreach ($ratings as $id => $data) {
		$picture = rating_image($data);
		if (isset($ratedata[$id]['total']))
			$list .= " &nbsp; {$picture}<span class='text-rating'>&nbsp;".htmlspecialchars($data['title'])."</span>&nbsp;x&nbsp;<strong>{$ratedata[$id]['total']}</strong>";
		if ($canrate && $data['enabled'] && $loguser['powerlevel'] >= $data['minpower'])
			$vote .= "<a href='postratings.php?action=rate&post={$post}&rating={$id}{$typestr}{$tokenstr}' class='icon-rating".(isset($ratedata['my'][$id]) ? " icon-rated" : " ")."'>{$picture}</a> ";
	}
	// Like user ratings, only staff (mods this time) can view the detailed list
	if ($ismod && $list)
		$sneak = " -- <a href='postratings.php?action=view&post={$post}{$typestr}'>Details</a>";
	
	return "<span class='rating-container'>{$list}<span style='float: right'>{$vote}{$sneak}</span></span>";
}

function rating_image($data) {
	return "<img src=\"".escape_attribute($data['image'])."\" style='max-width: 16px; max-height: 16px' title=\"".htmlspecialchars($data['title'])."\" align='absmiddle'>";
}
function rating_colors($val, $pts) {
	if ($pts == 0) return $val;
	if ($pts > 0)  return "<span style='color: #0f0'>{$val}</span>";
	if ($pts < 0)  return "<span style='color: #f00'>{$val}</span>";
}

function load_ratings($searchon, $range, $mode = MODE_POST) {
	global $sql, $loguser;
	
	if ($mode == MODE_ANNOUNCEMENT) {
		// In announcement mode, $range is an array of post ids rather than the min and max post id
		// ...to simplify the query, that is
		// deja wu, anyone?
		
		if (!count($range))
			return array();
		
		$ratings = $sql->query("
			SELECT a.post, a.rating, a.user
			FROM posts p
			INNER JOIN posts_ratings a ON p.id = a.post
			WHERE a.post IN (".implode(",", $range).")
			ORDER BY p.id
		");
	} else {
		if ($mode == MODE_PM) {
			$prefix = "pm_";
			$joinpf = "pm";
		} else {
			$prefix = "";
			$joinpf = "posts";
		}
		
		$ratings = $sql->query("
			SELECT a.post, a.rating, a.user
			FROM {$prefix}posts p
			INNER JOIN {$joinpf}_ratings a ON p.id = a.post
			WHERE {$searchon} 
			  AND a.post BETWEEN '{$range[0]}' AND '{$range[1]}'
			ORDER BY p.id
		");
	}
	

	$out = array();
	while ($x = $sql->fetch($ratings)) {
		// Keep a count of total ratings
		if (!isset($out[$x['post']][$x['rating']]))
			$out[$x['post']][$x['rating']]['total'] = 1;
		else
			$out[$x['post']][$x['rating']]['total']++;
		// Flag if the logged in user has selected that rating
		if ($x['user'] == $loguser['id'])
			$out[$x['post']]['my'][$x['rating']] = true;
	}
	return $out;
}


function rate_post($post, $rating, $mode = MODE_POST) {
	global $sql, $loguser;
	if ($mode == MODE_PM) {
		$joinpf = "pm";
		$fmode  = 1;
	} else {
		$joinpf = "posts";
		$fmode  = 0;
	}
	//--
	$data = $sql->fetchq("
		SELECT r.id, SUM(a.rating) voted
		FROM ratings r
		LEFT JOIN {$joinpf}_ratings a ON r.id = a.rating
		WHERE r.id = {$rating} AND r.enabled = 1 AND r.minpower <= {$loguser['powerlevel']}
		  AND a.user = {$loguser['id']} AND a.post = {$post}
	");
	if (!$data['id']) { // whoop de whoop the rating doesn't exist
		return false;
	} else if ($data['voted']) {
		delete_post_rating($loguser['id'], $post, $rating, $mode);
	} else {
		$sql->query("INSERT INTO {$joinpf}_ratings (user, post, rating, `date`) VALUES ({$loguser['id']}, {$post}, {$rating}, ".ctime().")");
		// User cache update
		
		$res = $sql->query("
			UPDATE ratings_cache SET total = total + 1 
			WHERE (
				 (type = 0 AND user = {$loguser['id']}) -- given
			  OR (type = 1 AND user = (SELECT user FROM posts WHERE id = {$post})) -- received
			  )
			  AND rating = {$rating}
			  AND mode = {$fmode}
		");
		if ($sql->num_rows($res) != 2) { // Row not present in the cache yet
			//die("Missing cache on insert.");
			$rateduser = $sql->resultq("SELECT user FROM posts WHERE id = {$post}");
			if (!$sql->resultq("SELECT COUNT(*) FROM ratings_cache WHERE type = 0 AND user = {$loguser['id']} AND rating = {$rating} AND mode = {$fmode}"))
				$sql->query("INSERT INTO ratings_cache (user, mode, type, rating, total) VALUES ({$loguser['id']}, {$fmode}, 0, {$rating}, 1)");
			if (!$sql->resultq("SELECT COUNT(*) FROM ratings_cache WHERE type = 1 AND user = {$rateduser} AND rating = {$rating} AND mode = {$fmode}"))
				$sql->query("INSERT INTO ratings_cache (user, mode, type, rating, total) VALUES ({$rateduser}, {$fmode}, 1, {$rating}, 1)");	
		}			
	}
	return true;
}

function delete_post_rating($user, $post, $rating, $mode = MODE_POST) {
	global $sql;
	if ($mode == MODE_PM) {
		$joinpf = "pm";
		$fmode   = 1;
	} else {
		$joinpf = "posts";
		$fmode   = 0;
	}
	//--
	$sql->query("DELETE FROM {$joinpf}_ratings WHERE user = {$user} AND post = {$post} AND rating = {$rating}");
	// User cache update
	$sql->query("
		UPDATE ratings_cache SET total = total - 1 
		WHERE (
		     (type = 0 AND user = {$user}) -- given
		  OR (type = 1 AND user = (SELECT user FROM posts WHERE id = {$post})) -- received
		  )
		  AND rating = {$rating}
		  AND mode = {$fmode}
	");
}

// Detail view for a single post/pm
function get_post_ratings($post, $mode = MODE_POST) {
	global $sql, $userfields;
	if ($mode == MODE_PM) {
		$joinpf = "pm";
	} else {
		$joinpf = "posts";
	}
	//--
	return $sql->fetchq("
		SELECT a.rating, {$userfields} uid
		FROM {$joinpf}_ratings a
		LEFT JOIN users u ON a.user = u.id
		WHERE a.post = {$post}
	", PDO::FETCH_GROUP, mysql::FETCH_ALL);
}

// Here we DO calculate the total
function get_user_post_ratings($user, $mode = MODE_POST) {
	global $sql;
	$mode = (int)($mode == MODE_PM);
	//--
	
	$ratings = $sql->query("
		SELECT type, rating, total
		FROM ratings_cache
		WHERE user = {$user} AND mode = {$mode}
	");
	$out = array(array(),array());
	while ($x = $sql->fetch($ratings)) {
		$out[$x['type']][$x['rating']] = $x['total'];
	}
	return $out;
}

// CACHE TIME!
// type 0 -> given; 1 ->received
function resync_post_ratings() {
	global $sql;
	$prefixes = array('','pm_');
	$joinpfs  = array('posts','pm');
	$sql->query("TRUNCATE ratings_cache");
	
	$users = $sql->getresults("SELECT id FROM users");
	for ($i = 0; $i < 2; ++$i) {
		$resync = $sql->prepare("
			INSERT INTO ratings_cache (user, mode, type, rating, total)
			SELECT x.user, x.mode, x.`key`, x.rating, COUNT(*) total 
			FROM (
				SELECT IF(r.user = ?,r.user,p.user) user, {$i} mode, IF(r.user = ?,0,1) `key`, r.rating
				FROM {$prefixes[$i]}posts p
				INNER JOIN {$joinpfs[$i]}_ratings r ON p.id = r.post
				WHERE p.user = ? OR r.user = ?
				"./* 
				include the duplicate count in case of self-rated posts (r.user = p.user) 
				the query above always considers them as "Given", so below we also add a copy as "Received"
				
				(though honestly, it could be considered to outright disable self-rating, even if it's an admin-only "feature")
				*/"
				UNION ALL
				SELECT p.user, {$i} mode, 1 `key`, r.rating
				FROM {$prefixes[$i]}posts p
				INNER JOIN {$joinpfs[$i]}_ratings r ON p.id = r.post
				WHERE p.user = ? AND p.user = r.user
			) x
			GROUP BY x.`key`, x.rating
		");
		foreach ($users as $user)
			$sql->execute($resync, [$user, $user, $user, $user, $user]);
	}
}