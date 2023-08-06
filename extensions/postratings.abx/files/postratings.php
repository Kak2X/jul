<?php
	require "lib/function.php";
	
	if (isset($_GET['view'])) {
		set_board_cookie('ratingViewMode', $_GET['view'] == 2);		
		$redirStr = "";
		if (isset($_GET['post']))    $redirStr .= "&post={$_GET['post']}";
		if (isset($_GET['rating']))  $redirStr .= "&rating={$_GET['rating']}";
		if (isset($_GET['type']))    $redirStr .= "&type={$_GET['type']}";
		if (isset($_GET['action']))  $redirStr .= "&action={$_GET['action']}";
		
		return header("Location: ?{$redirStr}");
	}
	
	$_GET['action']  = filter_string($_GET['action']); // Current action
	$_GET['post']    = filter_int($_GET['post']); // Selected post id
	$_GET['rating']  = filter_int($_GET['rating']); // voted rating
	$_GET['type']    = filter_string($_GET['type']); // post or pm
	$_COOKIE['mode'] = filter_bool($_COOKIE['ratingViewMode']); // view detail mode (0 -> xf1-like; 1 -> xf2-like)
	$_GET['ratid']   = filter_int($_GET['ratid']); // filtered rating id
	$_GET['fmode']   = filter_bool($_GET['fmode']); // filter mode (0 -> skip filtered elements; 1 -> hide filtered elements (autoset on js))
	
	if (!$_GET['post'] || !$_GET['type'])
		errorpage("Required URL arguments missing.");
	
	
	if ($_GET['action'] != 'view') {
		if (!$loguser['id'])
			errorpage("You need to be logged in to do this.", 'login.php', "log in (then try again)");
		if ($loguser['rating_locked'])
			errorpage("Sorry, but you've been restricted from rating posts, which is quite an accomplishment. Good job!");
	} else {
		if ($xconf['view-detail-perm'] > $loguser['powerlevel']) {
			errorpage("You can't do this.");
		}
	}
	
	// Check if we can view the thread the post is in
	if ($_GET['type'] == 'pm') {
		$post = $sql->fetchq("SELECT thread, user FROM pm_posts WHERE id = {$_GET['post']}");
		load_pm_thread($post['thread']);
		$mode = MODE_PM;
		$redirpage = "../showprivate";
	} else {
		$post = $sql->fetchq("SELECT thread, user FROM posts WHERE id = {$_GET['post']}");
		load_thread($post['thread']);
		$mode = MODE_POST;
		$redirpage = "../thread";
	}
	
	if (isset($thread['error']))
		errorpage("Cannot rate posts in broken threads.");
	
	if ($_GET['action'] == 'delete' && $ismod) {
		check_token($_GET['auth'], TOKEN_MGET);
		delete_post_rating(filter_int($_GET['u']), $_GET['post'], filter_int($_GET['r']), $mode);
		return header("Location: ?action=view&type={$_GET['type']}&post={$_GET['post']}&ratid={$_GET['ratid']}&fmode={$_GET['fmode']}");
	} else if ($_GET['action'] == 'rate' || $_GET['action'] == 'irate') {
		check_token($_GET['auth'], TOKEN_VOTE);
		if ($post['user'] != $loguser['id'] || $isadmin) // Can't rate yourself
			rate_post($_GET['post'], $_GET['rating'], $mode);
		if ($_GET['action'] == 'rate')
			return header("Location: {$redirpage}.php?pid={$_GET['post']}#{$_GET['post']}");
		else
			return header("Location: ?action=view&type={$_GET['type']}&post={$_GET['post']}&ratid={$_GET['ratid']}");
	} else if ($_GET['action'] == 'view') {
		$ratings  = get_ratings(true);
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
			[$thread['title']                    , actionlink("{$redirpage}.php?id={$thread['id']}")],
			["Ratings for post #{$_GET['post']}" , NULL],
		);
		$linkbase = "?action=view&post={$_GET['post']}&type={$_GET['type']}";
		$z = $_COOKIE['mode'] ? ['b','a'] : ['a','b']; 
		$right = "View mode: 
		<{$z[0]} href=\"".actionlink(null, "{$linkbase}&view=2")."\">XF2</{$z[0]}> - 
		<{$z[1]} href=\"".actionlink(null, "{$linkbase}&view=1")."\">XF1</{$z[1]}>";
		$barlinks = dobreadcrumbs($links, $right);
		
		pageheader("Rating details");
		
		$score = 0;
		
		if (!$_COOKIE['mode']) {
			list($ratedata, $myvotes) = get_post_ratings($_GET['post'], $mode);
			$out = "";
			foreach ($ratings as $id => $data) {
				// Users who rated the post with that rating, one for each line
				$userlist = "";
				if (isset($ratedata[$id])) {
					foreach ($ratedata[$id] as $user) {
						$score += $data['points'];
						$userlist .= "<div>".getuserlink($user, $user['uid']).($ismod ? " <a href='?action=delete&type={$_GET['type']}&post={$_GET['post']}&r={$id}&u={$user['uid']}{$tokenstr}' style='float: right' title='Delete rating'>[X]</a>" : "")."</div>";
					}
					$out .= "
					<table class='table rating-table-sect'>
						<tr><td class='tdbgh center'>".rating_image($data)." ".htmlspecialchars($data['title'])." <span style='float: right'>".rating_colors("[{$data['points']}]", $data['points'])."</span></td></tr>
						<tr><td class='tdbgc rating-table-desc center fonts' title=\"".escape_attribute($data['description'])."\">".xssfilters($data['description'])."</td></tr>
						<tr><td class='tdbg1 rating-table-userlist'><div class='rating-div-userlist'>{$userlist}</div></td></tr>
					</table>";
				}
			}
?>
			<style type='text/css'>
				.rating-table {}
				.rating-table-sect {
					display: inline-block;
					margin: 5px;
					vertical-align: top;
				}
				.rating-table-sect,.rating-table-userlist {
					width: 200px;
				}
				.rating-table-userlist {
					height: 150px;
					vertical-align: top;
				}
				.rating-table-desc {
					height: 30px;
				}
				.rating-div-userlist {
					overflow-y: auto;
					height: 100%;
				}
			</style>
			<?= $barlinks ?>
			<table class='table rating-table'>
				<tr><td class='tdbgh center b'>Rating details for post #<?= $_GET['post'] ?></td></tr>
				<tr><td class='tdbg1 center b'>Total score: <?= rating_colors($score, $score) ?></td></tr>
				<tr><td class='tdbg2' colspan='3'>
					<span style="float: right">Rate this post: &nbsp; <?= ratings_html($_GET['post'], [], $myvotes, $mode, true) ?></span>
				</td></tr>
				<tr><td class='tdbg2'><center>
				<?= $out ?>
				</center></td></tr>
			</table>
			<?= $barlinks ?>
<?php
		} else {
			
			list($ratedata, $votes, $myvotes) = get_post_ratings_xf2($_GET['post'], $mode);
			
			// Generate user ratings list
			$userlist = "";
			$cell     = 0;
			$score    = 0;
			foreach ($ratedata as $user) {
				$cell   = ($cell%2)+1;
				$id     = $user['rating'];
				$data   = $ratings[$id];
				$score += $data['points'];
				
				$skip = $_GET['ratid'] && $_GET['ratid'] != $user['rating'];
				if ($skip && !$_GET['fmode']) // rating filter option
					continue;
				
				prepare_avatar($user, $picture, $userpic);
				$userlist .= "
				<tr class='rat-user-row' data-id='{$id}' ".($skip ? "hidden='true'" : "").">
					<td class='tdbg{$cell} center nbdr del-col'>
						".($ismod ? "<a href='".actionlink(null, "?action=delete&type={$_GET['type']}&post={$_GET['post']}&r={$id}&u={$user['uid']}&ratid={$_GET['ratid']}&{$tokenstr}")."' title='Delete rating'>[X]</a>" : "")."
					</td>
					<td class='tdbg{$cell} w nbdl'>
						<img src=\"{$picture}\" class='user-rating-thumbnail' />
						".getuserlink($user, $user['uid'])."
					</td>
					<td class='tdbg{$cell} right'>
						".rating_image($data, true)."<br/><span class='underline' title=\"".printdate($user['date'])."\">".printdate($user['date'], true)."</span>
					</td>
				</tr>";
			}
			
			// Generate rating summary list and css
			$css     = "";
			$ratlist = "<a href='".actionlink(null, "{$linkbase}&ratid=0")."' data-id='0' class='rat-summary-item tdbg1'> All (".array_sum($votes).")</a>";
			$cell    = 1;
			foreach ($votes as $id => $cnt) {
				$cell   = ($cell%2)+1;
				$data = $ratings[$id];
				$ratlist .= "<a href='".actionlink(null, "{$linkbase}&ratid={$id}")."' data-id='{$id}' class='rat-summary-item tdbg{$cell}'> ".rating_image($data)." ".htmlspecialchars($data['title'])." ({$cnt})</a>";
			}
			
?>
			<style type='text/css'>
				.rat-table-v2 {
					max-width: 800px;
					margin: auto;
				}
				.rat-summary-item {
					display: inline-block;
					padding: 5px 10px;
					height: 30px;
				}
				.del-col {
					min-width: 25px;
				}
			</style>
			<?= $barlinks ?>
			<table class='table rat-table-v2'>
				<tr><td class='tdbgh center b' colspan='3'>Users who reacted to post #<?= $_GET['post'] ?></td></tr>
				<tr><td class='tdbg1 center b' colspan='3'>Total score: <?= rating_colors($score, $score) ?></td></tr>
				<tr><td class='tdbg2' colspan='3'>
					Rating filter: <b id='rat-flt'><?= (isset($ratings[$_GET['ratid']]) ? $ratings[$_GET['ratid']]['title'] : "(none)") ?></b>
					<span style="float: right">Rate this post: &nbsp; <?= ratings_html($_GET['post'], [], $myvotes, $mode, true) ?></span>
				</td></tr>
				<tr><td class='tdbg2' colspan='3'><?= $ratlist ?></td></tr>
				<tbody id='rat-table-body'>
				<?= $userlist ?>
				</tbody>
			</table>
			<?= $barlinks ?>
			
			<script type='text/javascript'>
				// heh
				var ratTxt = {
				<?php 
				foreach ($ratings as $id => $x) {
					print "{$id}: \"".htmlspecialchars($x['title'])."\",";
				} 
				?>
					0: "(none)"
				};
				
				// Get all deletion links
				var links = document.getElementById('rat-table-body').getElementsByTagName("A");
				// flag them to enable the alternate server-side filtering
				// this is because, normally, the filter would not include the filtered elements in the html
				// this doesn't work well with the js-based show/hide way
				for (var i = 0; i < links.length; i++) {
					links[i].href += "&fmode=1";
				}
				
				// Get the list of rating rows and filter indicator
				var rows = document.getElementsByClassName('rat-user-row');
				var filterTxt = document.getElementById('rat-flt');
				
				// Function to show or hide rating rows
				var ratingTableViewRows = function (e) {
					// disable link fallback
					e.preventDefault();
											
					// get the id from the data-id field of the clicked link
					var target = (typeof e.target.dataset['id'] === 'undefined') ? e.target.parentElement : e.target;
					var id = parseInt(target.dataset['id']);
					
					for (var i = 0; i < rows.length; i++) {
						// id 0 = show all; otherwise id == datasetId to show
						rows[i].hidden = (id !== 0 && id !== parseInt(rows[i].dataset['id']));
					}
					
					// update filter text
					filterTxt.innerHTML = ratTxt[id];
					
					// update links to return to the correct 
					for (var i = 0; i < links.length; i++) {
						links[i].href = links[i].href.replace(/&ratid=\d*/, "&ratid="+id+"");
					}
					
					// chrome pls
					return false;
				}
				
				// Register all of the links
				{
					var btns = document.getElementsByClassName('rat-summary-item');
					for (var i = 0; i < btns.length; i++) {
						btns[i].addEventListener('click', ratingTableViewRows);
					}
				}
			</script>
<?php
		}
		
	} else {
		errorpage("No.");
	}
	
	pagefooter();