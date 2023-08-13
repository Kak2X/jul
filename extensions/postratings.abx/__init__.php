<?php
require_once "files/lib/ratings.php";

hook_add('adminlinkbar', function() use ($extName) {
	adminlinkbar_add('Configuration', array(
		"{$extName}/admin-editratings.php" => "Edit Post Ratings",
	));
});

hook_add('header-css', function() use ($extName) {
	return "<link rel='stylesheet' href='{$extName}/base.css' type='text/css'>";
});

/*
	Post ratings overview in profile page.
*/
hook_add('profile-table-mini', function($_, $user) use ($extName) {
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
hook_add('annc-extra-db', function($_, $searchon, $postids) {
	global $_pr_ratings, $_pr_mine;
	list($_pr_ratings, $_pr_mine) = load_ratings($searchon, $postids, MODE_ANNOUNCEMENT);
});
hook_add('pm-extra-db', function($_, $searchon, $postids) {
	global $_pr_ratings, $_pr_mine;
	list($_pr_ratings, $_pr_mine) = load_ratings($searchon, $postids, MODE_PM);
});
hook_add('post-extra-db', function($_, $searchon, $postids) {
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
hook_add('annc-extra-fields', $postfieldset);
hook_add('pm-extra-fields', $postfieldset);
hook_add('post-extra-fields', $postfieldset);
/*
// inside threadpost
hook_add('threadpost', function($_, &$set, $post, $mode) {
	$set['rating'] = $post['id'] ? ratings_html($post['id'], $post['rating'], $post['myratings'], $mode) : "";
});
hook_add('threadpost-deleted', function($_, &$set, $post, $mode) {
	$set['rating'] = "";
});*/

// :(
$tly_def = function ($_, $set, $post, $data) {
	if ($post['deleted']) return;
	add_topbar_entry($set['rating'], TOPBAR_RIGHT);
};

hook_add('tlayout-regular', function($_, $set, $post, $data) {
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
hook_add('tlayout-compact', function ($_, $set, $post, $data) {
	if ($post['deleted'] || !$post['id']) return;
	add_option_row("<tr><td class='tdbg{$set['bg']}' colspan='2'>".ratings_html($post['id'], $post['rating'], $post['myratings'], $set['mode'])."</td></tr>", OPTION_ROW_TOP);
});
hook_add('tlayout-ezboard', function($_, $set, $post, $data) {
	if ($post['deleted'] || !$post['id']) return;
	add_option_row("<tr>
			<td class='tdbg{$set['bg']} sidebar{$post['uid']}{$data->csskey}_opt fonts'></td>
			<td class='tdbg{$set['bg']} mainbar{$post['uid']}{$data->csskey}_opt fonts'>{$set['rating']}</td>
		</tr>");
});
