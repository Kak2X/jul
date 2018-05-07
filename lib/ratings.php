<?php

// XenForo-like post ratings, except nowhere near as good
// :(

function get_ratings($all = false) {
	global $sql;
	return $sql->fetchq("SELECT ".($all ? "*" : "id, image, title, enabled")." FROM ratings ORDER BY id ASC", PDO::FETCH_UNIQUE, mysql::FETCH_ALL | mysql::USE_CACHE);
}

// Post rating HTML (rating list & post selection)
function ratings_html($post, $ratedata = array(), $mode = MODE_POST) {
	global $ismod, $loguser;
	$list = $vote = $sneak = "";
	$tokenstr = "&auth=".generate_token(TOKEN_VOTE);
	$typestr  = "&type=".($mode == MODE_POST ? "post" : "pm");
	$canrate  = ($loguser['id'] && !$loguser['rating_locked']);
	
	// Enumerate ratings and display the list
	$ratings = get_ratings();
	foreach ($ratings as $id => $data) {
		$picture = rating_image($data);
		if (isset($ratedata[$id]['total']))
			$list .= " &nbsp; {$picture}<span class='text-rating'>&nbsp;{$data['title']}</span>&nbsp;x&nbsp;<strong>{$ratedata[$id]['total']}</strong>";
		if ($canrate && $data['enabled'])
			$vote .= "<a href='postratings.php?action=rate&post={$post}&rating={$id}{$typestr}{$tokenstr}' class='icon-rating".(isset($ratedata['my'][$id]) ? " icon-rated" : " ")."'>{$picture}</a> ";
	}
	// Like user ratings, only staff (mods this time) can view the detailed list
	if ($ismod && $list)
		$sneak = " -- <a href='postratings.php?action=view&post={$post}{$typestr}'>Details</a>";
	
	return "<span class='rating-container'>{$list}<span style='float: right'>{$vote}{$sneak}</span></span>";
}

function rating_image($data) {
	return "<img src=\"{$data['image']}\" style='max-width: 16px; max-height: 16px' title=\"".htmlspecialchars($data['title'])."\" align='absmiddle'>";
}
function rating_colors($val, $pts) {
	if ($pts == 0) return $val;
	if ($pts > 0)  return "<span style='color: #0f0'>{$val}</span>";
	if ($pts < 0)  return "<span style='color: #f00'>{$val}</span>";
}

function load_ratings($searchon, $min, $ppp, $mode = MODE_POST) {
	global $sql, $loguser;
	if ($mode == MODE_PM) {
		$prefix = "pm_";
		$joinpf = "pm";
	} else {
		$prefix = "";
		$joinpf = "posts";
	}
	//--
	$ratings = $sql->query("
		SELECT a.post, a.rating, a.user
		FROM {$prefix}posts p
		INNER JOIN {$joinpf}_ratings a ON p.id = a.post
		WHERE {$searchon}
		ORDER BY p.id
		LIMIT $min,$ppp
	");
	$out = array();
	while ($x = $sql->fetch($ratings)) {
		// Keep a count of total ratings
		if (!isset($out[$x['post']][$x['rating']]))
			$out[$x['post']][$x['rating']]['total'] = 1;
		else
			$out[$x['post']][$x['rating']]['total']++;
		// Flag is the logged in user has selected that rating
		if ($x['user'] == $loguser['id'])
			$out[$x['post']]['my'][$x['rating']] = true;
	}
	return $out;
}


function rate_post($post, $rating, $mode = MODE_POST) {
	global $sql, $loguser;
	if ($mode == MODE_PM) {
		$joinpf = "pm";
	} else {
		$joinpf = "posts";
	}
	//--
	$data = $sql->fetchq("
		SELECT r.id, SUM(a.rating) voted
		FROM ratings r
		LEFT JOIN {$joinpf}_ratings a ON r.id = a.rating
		WHERE r.id = {$rating} AND r.enabled = 1 AND a.user = {$loguser['id']} AND a.post = {$post}
	");
	if (!$data['id']) { // whoop de whoop the rating doesn't exist
		return false;
	} else if ($data['voted']) {
		$sql->query("DELETE FROM {$joinpf}_ratings WHERE user = {$loguser['id']} AND post = {$post} AND rating = {$rating}");
	} else {
		$sql->query("INSERT INTO {$joinpf}_ratings (user, post, rating, `date`) VALUES ({$loguser['id']}, {$post}, {$rating}, ".ctime().")");
	}
	return true;
}

function delete_post_rating($user, $post, $rating, $mode = MODE_POST) {
	global $sql;
	if ($mode == MODE_PM) {
		$joinpf = "pm";
	} else {
		$joinpf = "posts";
	}
	//--
	$sql->query("DELETE FROM {$joinpf}_ratings WHERE user = {$user} AND post = {$post} AND rating = {$rating}");
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
// TODO: This *probably* needs to be cached
function get_user_post_ratings($user, $mode = MODE_POST) {
	global $sql;
	if ($mode == MODE_PM) {
		$prefix = "pm_";
		$joinpf = "pm";
	} else {
		$prefix = "";
		$joinpf = "posts";
	}
	$ratings = $sql->query("
		SELECT a.rating, IF(a.user = {$user},'given','received') `key`, COUNT(*) total
		FROM {$prefix}posts p
		INNER JOIN {$joinpf}_ratings a ON p.id = a.post
		WHERE p.user = {$user} OR a.user = {$user}
		GROUP BY `key`, a.rating
	");
	$out = array('received' => array(), 'given' => array());
	while ($x = $sql->fetch($ratings)) {
		$out[$x['key']][$x['rating']] = $x['total'];
	}
	return $out;
}

function resync_post_ratings() {
	global $sql;
	// WIP
	return;
}