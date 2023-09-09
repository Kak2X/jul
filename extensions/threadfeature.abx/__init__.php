<?php

require_once "files/lib/feature.php";

hook_add('header-links-2', function() {
	return " - <a href='forum.php?feat=2'>Featured threads</a> <a href='forum.php?feat=1'>(archive)</a>";
});

// index.php - random featured thread
const EXT_WND_FEATURED = -3;
if ($xconf['index-window'] || $xconf['index-force-thread']) {
	hook_add('index-window', function () use ($xconf) {
		global $sql, $userfields, $poll, $isadmin;
		

		$hidden = filter_int($_COOKIE['hcat'][EXT_WND_FEATURED]);
		if (!$xconf['index-force-thread'] && $hidden) {
			// Simpler query if featured threads are disabled
			$total = $sql->resultq("
				SELECT COUNT(*) FROM threads t 
				INNER JOIN forums f ON t.forum = f.id
				WHERE t.featured = 1 AND ".can_view_forum_query()."
			");
			if (!$total)
				return "";
			
			if ($total > 1) {
				$title = "Featured threads ($total)";
			} else {
				$title = "Featured thread";
			}
			
			ob_start();
?>
			<br/>
			<table class="table">
				<tr><td class="tdbgh center fonts"><?= $title . _collapse_toggle(EXT_WND_FEATURED, $hidden) ?></td></tr>
			</table>
<?php
		} else {
			
			// If we're overriding the result, force the single specified thread to be featured.
			// Otherwise, grab a random featured thread.
			$featured = $xconf['index-force-thread'] 
				? [$xconf['index-force-thread']]
				: $sql->getresults("
					SELECT t.id FROM threads t 
					INNER JOIN forums f ON t.forum = f.id
					WHERE t.featured = 1 AND ".can_view_forum_query()."
				");
			if (!$featured)
				return "";
			
			$featid = pick_any($featured);
			
			$fthread = $sql->fetchq("
				SELECT t.id, t.title, t.description, t.firstpostdate, t.replies, t.icon, t.forum, t.poll, t.user,
					   f.pollstyle, p.text, p.nosmilies, p.nohtml, {$userfields} uid
				FROM threads t
				LEFT JOIN forums f ON t.forum = f.id
				LEFT JOIN users  u ON t.user  = u.id
				LEFT JOIN posts  p ON t.id    = p.thread
				WHERE t.id = {$featid} AND ".can_view_forum_query()."
				 
				ORDER BY p.id ASC
				LIMIT 1
			");
			
			// Just in case the override was set incorrectly, alert the admins
			if (!$fthread)
				return $isadmin ? "<div class='center'>The featured thread with ID #$featid doesn't exist.</div>" : "";
			
			// Counters
			$total  = count($featured);
			if ($total > 1) {
				$cur    = array_search($featid, $featured) + 1;
				$title = "Random featured thread";
				$counter = " ({$cur}/{$total})";
			} else {
				$title = "Featured thread";
				$counter = "";
			}
			
			// Poll Display
			$polltbl = "";
			if ($fthread['pollstyle'] != -2 && $fthread['poll']) {
				if (load_poll($fthread['poll'], $fthread['pollstyle'])) {
					// CSS Hack around removing the <br> tag, which is unnecessary here
					$polltbl = "<tr><td class='tdbg2 welp' colspan='2'>
						".print_poll($poll, $fthread, $fthread['forum'])."
						<style>.welp > br {display: none}</style>
					</td></tr>";
				}
			}
			ob_start();
?>
			<br/>
			<table class="table">
				<tr><td class="tdbgh center fonts" colspan="2"><?= $title . _collapse_toggle(EXT_WND_FEATURED, $hidden) ?></td></tr>
				<tr>
					<td class="tdbg1 center thread-icon-td">
						<div class="thread-icon">
							<?= ($fthread['icon'] ? "<img src=\"".htmlspecialchars($fthread['icon'])."\" alt='->'>" : "->") ?>
						</div>
					</td>
					<td class="tdbg1">
						<a href="thread.php?id=<?= $fthread['id'] ?>"><?= htmlspecialchars($fthread['title']) ?></a>
						<br><span class="fonts"><?= htmlspecialchars($fthread['description']) ?></span>
					</td>
				</tr>
				<?= $polltbl ?>
				<tr>
					<td class="tdbg1"></td>
					<td class="tdbg2">
						<div style="max-height: 100px; overflow-y: scroll">
							<?= dofilters(domarkup($fthread['text'], $fthread), $fthread['forum']) ?>
						</div>
					</td>
				</tr>
				<tr>
					<td class="tdbg2" colspan="2">
						<b><a href="forum.php?feat=2">Featured thread</a><?= $counter ?></b> - <?= getuserlink($fthread) ?> - <?= printdate($fthread['firstpostdate']) ?>
						<span style="float: right">Replies: <?= $fthread['replies'] ?> - <a href="thread.php?id=<?= $fthread['id'] ?>">Read More</a></span>
					</td>
				</tr>
			</table>
<?php
		}
		
		$res = ob_get_contents();
		ob_end_clean();
		return $res;
	});
}

const QMOD_ACT_FEATURE = "qfeat";
const QMOD_ACT_UNFEATURE = "qunfeat";

// Edit thread quickmod
hook_add('thread-quickmod-act', function($_, $action) {
	switch ($action) {
		case QMOD_ACT_FEATURE:   feature_thread($_GET['id']);   return true;
		case QMOD_ACT_UNFEATURE: unfeature_thread($_GET['id']); return true;
		default: return false;
	}
});
hook_add('thread-quickmod-link', function($_, &$actions) {
	global $thread;
	$actions[] = !$thread['featured'] ? [QMOD_ACT_FEATURE, "Feature"] : [QMOD_ACT_UNFEATURE, "Unfeature"];
});

// newthread.php/newreply.php - moderator options
hook_add('thread-mod-opt', function() {
	global $ismod, $thread;
	if (!$ismod) return false;
	
	if (isset($_POST['tfeat'])) // Sent through the form?
		$match = $_POST['tfeat'];
	else if (isset($thread['featured'])) // Use existing default? (won't exist on newthread)
		$match = $thread['featured'];
	else 
		$match = false;
	
	$seltfeat = $match ? " checked" : "";
	return " - <input type='checkbox' name='tfeat' id='tfeat' value='1'{$seltfeat}><label for='tfeat'>Featured</label>";
});

// newthread.php - additional fields to update in thread table
hook_add('thread-create-fields', function($_, $treq) {
	global $ismod;
	$treq->vals['featured'] = $ismod ? filter_int($_POST['tfeat']) : 0;
});
// newthread.php - actions after the thread/post/poll are created, before committing the transaction
hook_add('thread-create-precommit', function($_, $treq) {
	// Add to the featured threads archive
	if ($treq->vals['featured']) {
		feature_thread($treq->id, false, true);
	}
});

// newreply.php - additional fields to update in thread table
hook_add('post-create-fields', function($_, $preq) {
	global $ismod, $thread;
	// Save a query if it wasn't changed, as it would call (un)feature_thread()
	$_POST['tfeat'] = filter_int($_POST['tfeat']);
	if ($ismod && $_POST['tfeat'] != $thread['featured']) { 
		$preq->threadupdate['featured'] = $_POST['tfeat'];
	}
});
// newreply.php - actions after the thread is posted
hook_add('post-create-precommit', function($_, $preq) {
	global $ismod, $thread;
	// Update featured thread archive
	if ($ismod && isset($preq->threadupdate['featured'])) {
		if ($preq->threadupdate['featured']) {
			feature_thread($preq->vals['thread'], false, true);
		} else {
			unfeature_thread($preq->vals['thread'], false, true);
		}
	}
});

// editthread.php
hook_add('thread-edit-act', function($_, &$data) {
	global $ismod, $thread;
	if (!$ismod) return; // Not a mod
	
	$_POST['featured'] = filter_int($_POST['featured']);
	$_POST['fdelarch'] = filter_int($_POST['fdelarch']);
	
	// Only if the "featured" flag changes or the archive deletion flag is set when the thread is set to unfeatured
	if ($thread['featured'] != $_POST['featured'] || (!$_POST['featured'] && $_POST['fdelarch'])) {
		
		// Set the thread column as needed
		$data['featured'] = $_POST['featured'];
		
		// Update the featured thread history list.
		// Weird dance with reusing the queries from *feature_thread
		if ($data['featured']) {
			feature_thread($_GET['id'], false);
		} else {
			unfeature_thread($_GET['id'], false, true, $_POST['fdelarch']);
		}
	}
});

hook_add('thread-edit-form-flag', function() {
	global $ismod, $thread;
	if (!$ismod) return;
			
	$check[$thread['featured']] = 'checked="checked"';
	ob_start();
?>
	<tr>
		<td class='tdbg1'></td>
		<td class='tdbg2'>
			<input type="radio" name="featured" value="0" <?=filter_string($check[0])?> onclick="hideArch(0)"> Unfeatured &nbsp; &nbsp;
			<input type="radio" name="featured" value="1" <?=filter_string($check[1])?> onclick="hideArch(1)"> Featured &nbsp; &nbsp;
			<input type="checkbox" name="fdelarch" id="fdelarch" value="1"> Delete from archives
			
			<script type="text/javascript">
				var choice = document.getElementById('fdelarch');
				function hideArch(sel) {
					if (choice !== undefined) {
						if (sel) {
							choice.disabled = true;
							choice.checked = false;
						} else {
							choice.disabled = false;
						}
					}
				}
				hideArch(<?= $thread['featured'] ?>);
			</script>
		</td>
	</tr>
<?php
	$res = ob_get_contents();
	ob_end_clean();
	return $res;
});

// forum.php - Featured threads view mode (?feat=)
hook_add('forum-mode', function() {
	global $sql;
	$_GET['feat']   = filter_int($_GET['feat']);
	if (!$_GET['feat']) return null;
	
	$opt = new forum_mode_opt();
	
	$opt->pagetitle = "Featured threads";
	$opt->pageurl   = "feat={$_GET['feat']}";
	$opt->sepsticky = false;
	
	$where          = "tf.enabled = ".($_GET['feat']-1);
	$userurl        = "";
	if ($_GET['user']) {
		$username = $sql->resultq("SELECT name FROM users WHERE id = {$_GET['user']}");
		if (!$username) {
			global $meta;
			$meta['noindex'] = true;
			errorpage("No user with that ID exists.",'index.php','the index page');
		}
		$userurl = "&user={$_GET['user']}";
		$opt->pageurl   .= $userurl;
		$opt->pagetitle .= " by {$username}";
		$where          .= " AND t.user = {$_GET['user']}";
	}
	if ($_GET['feat'] == 1) {
		$opt->pagetitle .= " (Archive)";
	}
	$opt->barright  = "Show: <a href='?feat=2{$userurl}'>Current</a> - <a href='?feat=1{$userurl}'>Archive</a>";
	
	$opt->threadcount = $sql->resultq("
		SELECT COUNT(*) 
		FROM threads_featured tf
		LEFT JOIN threads t ON tf.thread = t.id
		WHERE {$where}");
	$opt->query = "
		SELECT 	t.*, f.id forumid, f.minpower, f.login,
				".set_userfields('u1').", 
				".set_userfields('u2')."
				{%TRVAL%}
		
		FROM threads t
		LEFT JOIN threads_featured  tf ON t.id         = tf.thread
		LEFT JOIN users             u1 ON t.user       = u1.id
		LEFT JOIN users             u2 ON t.lastposter = u2.id
		LEFT JOIN forums             f ON t.forum      =  f.id
		{%TRJOIN%}
		
		WHERE (tf.enabled = ".($_GET['feat']-1).")".($_GET['user'] ? " AND t.user = {$_GET['user']}" : "")."
		ORDER BY t.lastpostdate DESC
				
		LIMIT {%MIN%},{%TPP%}	
	";
	return $opt;
});
/* (disabled for now since it gets unused. there's no ?feat= view that displays both current and past featured threads)
// forum.php - extra thread separator rules
hook_add('forum-thread-separator', function ($_, $thread) {
	if (!$_GET['feat']) return;
	static $featlast = 0;
	$res = $featlast && !$thread['featured'];
	$featlast = $thread['featured'];
	return $res;
});
*/
// forum.php - thread status
hook_add('forum-thread-stat', function ($_, $stat, $thread) {
	if ($thread['featured']) {
		$stat->title_l .= "<b>Featured</b> | ";
	}
});