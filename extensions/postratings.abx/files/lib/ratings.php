<?php

// XenForo-like post ratings, except nowhere near as good
// :(

function get_ratings($all = false) {
	global $sql, $loguser;
	return $sql->getarraybykey("SELECT ".($all ? "*" : "id, image, title, enabled, minpower")." FROM ratings ORDER BY id ASC", "id", mysql::FETCH_ALL | mysql::USE_CACHE);
}

// Post rating HTML (rating list & post selection)
const PRH_NODETAIL = 0b1;
//const PRH_2LINES   = 0b10;
function ratings_html($post, $ratedata = array(), $my = array(), $mode = MODE_POST, $flags = 0) {
	global $ismod, $loguser, $xconf;
	static $modes = [MODE_POST => "post", MODE_PM => "pm", MODE_ANNOUNCEMENT => "annc"];
	$vote = $sneak = "";
	$tokenstr = "&auth=".generate_token(TOKEN_VOTE);
	$typestr  = "&type=".$modes[$mode];
	$canrate  = ($loguser['id'] && !$loguser['rating_locked']);
	
	// Enumerate ratings and display the list
	$ratings = get_ratings();
	$action = ($flags & PRH_NODETAIL) ? "irate" : "rate";
	$list   = [];
	$totals = [];
	foreach ($ratings as $id => $data) {
		$picture = rating_image($data);
		if (isset($ratedata[$id]['total'])) {
			$list[$id] = " &nbsp; <span class='rate-total'>{$picture}<span class='text-rating'>&nbsp;".htmlspecialchars($data['title'])."</span>&nbsp;x&nbsp;<strong>{$ratedata[$id]['total']}</strong></span>";
			$totals[$id] = $ratedata[$id]['total'];
		}
		if ($canrate && $data['enabled'] && $loguser['powerlevel'] >= $data['minpower'])
			$vote .= "<a href='".actionlink("postratings.php?action={$action}&post={$post}&rating={$id}{$typestr}{$tokenstr}")."' class='icon-rating".(isset($my[$id]) ? " icon-rated" : " ")."'>{$picture}</a> ";
	}
	
	// Display the existing rating list
	$curvotes = "";
	if (count($totals)) {
		// get the sort order
		arsort($totals);
		foreach ($totals as $id => $_) {
			$curvotes .= $list[$id];
		}
		// And the details link, if it isn't disabled
		if (!($flags & PRH_NODETAIL) && $xconf['view-detail-perm'] <= $loguser['powerlevel']) // && $ismod
			$sneak = " -- <a href='".actionlink("postratings.php?action=view&post={$post}{$typestr}")."'>Details</a>";
	}
	return "<span class='rating-container'>{$curvotes}"./*($flags & PRH_2LINES ? "<br>" : "").*/"<span style='float: right'>{$vote}{$sneak}</span></span>";
}


function rating_image($data, $double = false) {
	static $url_cache;
	$css = $double ? "lg" : "";
	// Avoid file_exist'ing all the time
	if (isset($url_cache[$data['id']])) {
		$url = $url_cache[$data['id']];
	} else if (file_exists(rating_path($data['id'], ".svg"))) {
		$url = rating_path($data['id'], ".svg");
		$url_cache[$data['id']] = $url;
	} else if (file_exists(rating_path($data['id']))) {
		$url = rating_path($data['id']);
		$url_cache[$data['id']] = $url;
	} else {
		$url = escape_attribute($data['image']);
		$url_cache[$data['id']] = $url;
	}
	// changed to XF2-like background CSS format because SVGs didn't play well with it
	return "<img src=\"images/_.gif\" class='icon-rating-image {$css}' style='background-image: url(\"{$url}\")' title=\"".htmlspecialchars($data['title'])."\" align='absmiddle'>";
}

function rating_path($id, $ext = "") {
	return "extensions/postratings.abx/files/images/ratings/uploads/{$id}{$ext}";
}

function upload_rating_image($file, $id) {
	if (!$file['tmp_name'])
		errorpage("No file selected.");

	if (!$file['size']) 
		errorpage("This is an 0kb file");
	
	$img_type = get_image_type($file['tmp_name']);
	if (!$img_type)
		errorpage("This isn't a supported image type.");
	
	// content-type requirements :(
	$extension = $img_type == IMAGETYPE_SVG ? ".svg" : "";
	
	// Delete existing file, if present
	delete_rating_image($id);
	
	return move_uploaded_file($file['tmp_name'], rating_path($id, $extension));
}

function delete_rating_image($id) {
	$p = rating_path($id);
	if (file_exists($p.".svg"))
		unlink($p.".svg");
	if (file_exists($p))
		unlink($p);
}

function rating_colors($val, $pts) {
	if ($pts == 0) return $val;
	if ($pts > 0)  return "<span style='color: #0f0'>{$val}</span>";
	if ($pts < 0)  return "<span style='color: #f00'>{$val}</span>";
}

function load_ratings($searchon, $qvals, $range, $mode = MODE_POST) {
	global $sql, $loguser;
	
	if ($mode == MODE_ANNOUNCEMENT) {
		// In announcement mode, $range is an array of post ids rather than the min and max post id
		// ...to simplify the query, that is
		// deja vu, anyone?
		
		if (!count($range))
			return [[],[]];
		
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
		
		$ratings = $sql->queryp("
			SELECT a.post, a.rating, a.user
			FROM {$prefix}posts p
			LEFT  JOIN {$prefix}threads  t ON p.thread = t.id
			INNER JOIN {$joinpf}_ratings a ON p.id     = a.post
			WHERE a.post BETWEEN '{$range[0]}' AND '{$range[1]}'
			".($searchon ? " AND {$searchon}" : "")."
			ORDER BY p.id
		", $qvals);
	}
	
	$out = [];
	$my  = [];
	foreach ($ratings as $x) {
		// Keep a count of total ratings
		if (!isset($out[$x['post']][$x['rating']]))
			$out[$x['post']][$x['rating']]['total'] = 1;
		else
			$out[$x['post']][$x['rating']]['total']++;
		// Flag if the logged in user has selected that rating
		if ($x['user'] == $loguser['id'])
			$my[$x['post']][$x['rating']] = true;
	}
	return [$out, $my];
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
		$sql->query("INSERT INTO {$joinpf}_ratings (user, post, rating, `date`) VALUES ({$loguser['id']}, {$post}, {$rating}, ".time().")");
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
	global $sql, $loguser, $userfields;
	if ($mode == MODE_PM) {
		$joinpf = "pm";
		$joinp  = "pm_";
	} else {
		$joinpf = "posts";
		$joinp  = "";
	}
	//--
	$grp = $sql->fetchq(set_avatars_sql("
		SELECT a.rating, a.date,p.moodid {%AVFIELD%}, {$userfields} uid
		FROM {$joinpf}_ratings a
		LEFT JOIN {$joinp}posts p ON a.post = p.id
		LEFT JOIN users u ON a.user = u.id
		{%AVJOIN%}
		WHERE a.post = {$post}
	"), PDO::FETCH_GROUP, mysql::FETCH_ALL);
	$my = [];
	
	foreach ($grp as $id => $vote) {
		foreach ($vote as $x) {
			if ($x['uid'] == $loguser['id']) {
				$my[$id] = true;
				break;
			}
		}
	}
	
	return [$grp, $my];
}

function get_post_ratings_xf2($post, $mode = MODE_POST) {
	global $sql, $loguser, $userfields;
	if ($mode == MODE_PM) {
		$joinpf = "pm";
		$joinp  = "pm_";
	} else {
		$joinpf = "posts";
		$joinp  = "";
	}
	
	$rats = $sql->query(set_avatars_sql("
		SELECT a.rating, a.date, 0 moodid {%AVFIELD%}, {$userfields} uid
		FROM {$joinpf}_ratings a
		LEFT JOIN users u ON a.user = u.id
		{%AVJOIN%}
		WHERE a.post = {$post}
		ORDER BY a.date ASC
	", 'a', true));
	
	// NOTE: $raw should not have ['total'] unlike the normal load_ratings
	//       this will hide the rating counts from rating_html, which is intentional
	$vot = $raw = $my = [];
	
	while ($x = $sql->fetch($rats)) {
		// Keep a count of total ratings
		if (!isset($vot[$x['rating']])) {
			$vot[$x['rating']] = 1;
			$grp[$x['rating']] = [];
		} else {
			$vot[$x['rating']]++;
		}
		
		// Flag if the logged in user has selected that rating
		if ($x['uid'] == $loguser['id'])
			$my[$x['rating']] = true;
		
		// Raw copy
		$raw[] = $x;
	}
	// Sort rating count in desc order
	arsort($vot);
	
	return [$raw, $vot, $my];
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