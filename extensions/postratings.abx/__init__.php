<?php
require_once "files/lib/ratings.php";

add_hook('adminlinkbar', function() use ($extName) {
	adminlinkbar_add('Configuration', array(
		"{$extName}/admin-editratings.php" => "Edit Post Ratings",
	));
});

add_hook('header-css', function() use ($extName) {
	return "<link rel='stylesheet' href='{$extName}/base.css' type='text/css'>";
});

/*
	Post ratings overview in profile page.
*/
add_hook('profile-table-mini', function($_, $user) use ($extName) {
	global $isadmin;
	$ratings  = get_ratings(true);
	$ratedata = get_user_post_ratings($user['id']);
	$ptsin = $ptsout = 0;
	$ratinglist = "
	<table class='table'>
		<tr><td class='tdbgh center' colspan='3'>Post ratings</td></tr>
		<tr>
			<td class='tdbgh fonts center'>&nbsp;</td>
			<td class='tdbgh fonts center'>Received</td>
			<td class='tdbgh fonts center'>Given</td>
		</tr>
	";
	foreach ($ratings as $id => $data) {
		if ($data['enabled']) {
			$ptsin  += filter_int($ratedata[1][$id]) * $data['points'];
			$ptsout += filter_int($ratedata[0][$id]) * $data['points'];
		}
		else if (!$isadmin)
			continue;
		$ratinglist .= "
		<tr>
			<td class='tdbg1 fonts center'>".rating_image($data)."</td>
			<td class='tdbg2 fonts center'>".rating_colors(filter_int($ratedata[1][$id]), $data['points'])."</td>
			<td class='tdbg2 fonts center'>".rating_colors(filter_int($ratedata[0][$id]), $data['points'])."</td>
		</tr>
		";
	}
	return $ratinglist . "
		<tr><td class='tdbgh' colspan='3'></td></tr>
		<tr>
			<td class='tdbg1 fonts center'>Pts.</td>
			<td class='tdbg2 fonts center'>".rating_colors($ptsin, $ptsin)."</td>
			<td class='tdbg2 fonts center'>".rating_colors($ptsout, $ptsout)."</td>
		</tr>
	</table>";
});

// Post rating fetch query
add_hook('annc-extra-db', function($_, $searchon, $postids) {
	global $_pr_ratings, $_pr_mine;
	list($_pr_ratings, $_pr_mine) = load_ratings($searchon, $postids, MODE_ANNOUNCEMENT);
});
add_hook('pm-extra-db', function($_, $searchon, $postids) {
	global $_pr_ratings, $_pr_mine;
	list($_pr_ratings, $_pr_mine) = load_ratings($searchon, $postids, MODE_PM);
});
add_hook('post-extra-db', function($_, $searchon, $postids) {
	global $_pr_ratings, $_pr_mine;
	list($_pr_ratings, $_pr_mine) = load_ratings($searchon, $postids, MODE_POST);
});
// threadpost call setup
$postfieldset = function($_, &$post) {
	global $_pr_ratings, $_pr_mine;
	$post['showratings'] = true; // NOT NECESSARY
	$post['rating']      = isset($_pr_ratings[$post['id']]) ? $_pr_ratings[$post['id']] : [];
	$post['myratings']   = isset($_pr_mine[$post['id']]) ? $_pr_mine[$post['id']] : [];
};
add_hook('annc-extra-fields', $postfieldset);
add_hook('pm-extra-fields', $postfieldset);
add_hook('post-extra-fields', $postfieldset);
/*
// inside threadpost
add_hook('threadpost', function($_, &$set, $post, $mode) {
	$set['rating'] = $post['id'] ? ratings_html($post['id'], $post['rating'], $post['myratings'], $mode) : "";
});
add_hook('threadpost-deleted', function($_, &$set, $post, $mode) {
	$set['rating'] = "";
});*/

// :(
$tly_def = function ($_, $set, $post, $data) {
	if ($post['deleted']) return;
	add_topbar_entry($set['rating'], TOPBAR_RIGHT);
};

add_hook('tlayout-regular', function($_, $set, $post, $data) {
	if ($post['deleted'] || !$post['id']) return;
	if ($data->sidebar_one_cell) {
		$ratingside = "";
	} else {
		$ratingside = "<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$data->csskey}_opt fonts'></td>";
	}
	add_option_row("<tr>
		{$ratingside}
		<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$data->csskey}_opt fonts' style='height: 1px; width: 80%'>
			".ratings_html($post['id'], $post['rating'], $post['myratings'], $set['mode'])."
		</td>
	</tr>"); // &nbsp;<b>Post ratings:</b>
});
add_hook('tlayout-compact', function ($_, $set, $post, $data) {
	if ($post['deleted'] || !$post['id']) return;
	add_option_row("<tr><td class='tdbg{$set['bg']}' colspan='2'>".ratings_html($post['id'], $post['rating'], $post['myratings'], $set['mode'])."</td></tr>", OPTION_ROW_TOP);
});
add_hook('tlayout-ezboard', function($_, $set, $post, $data) {
	if ($post['deleted'] || !$post['id']) return;
	add_option_row("<tr>
			<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$data->csskey}_opt fonts'></td>
			<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$data->csskey}_opt fonts'>{$set['rating']}</td>
		</tr>");
});