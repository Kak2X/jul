<?php
	
	function get_thread_from_post($pid, $prefix = '') {
		global $sql;
		
		// Linking to a post ID
		$id		= $sql->resultq("SELECT `thread` FROM `{$prefix}posts` WHERE `id` = '{$pid}'");
		if (!$id) {
			$meta['noindex'] = true; // prevent search engines from indexing
			errorpage("Couldn't find a post with ID #{$pid}. Perhaps it's been deleted?", "index.php", 'the index page');
		}
		return $id;
	}
		
	// Load thread/forum data and appropriately handle errors
	function load_forum($id, $check_errors = true) {
		global $sql, $meta, $loguser, $ismod, $forum, $forum_error;
		$error        = 0;
		$forum_error = "";
		
		$forum = $sql->fetchq("SELECT * FROM forums WHERE id = '{$id}'");
		if ($check_errors) {
			if (!$forum) {
				if (!$ismod) {
					trigger_error("Attempted to access invalid forum {$id}", E_USER_NOTICE);
					$meta['noindex'] = true; // prevent search engines from indexing what they can't access
					notAuthorizedError();
				}
				
				// Mod+ can see a list of threads assigned to the bad forum
				$badthreads = $sql->resultq("SELECT COUNT(*) FROM `threads` WHERE `forum` = '{$id}'");
				if ($badthreads <= 0) {
					errorpage("Forum ID #{$id} doesn't exist, and no threads are associated with the invalid forum ID.", "index.php", 'the index page');
				}
				
				$error = INVALID_FORUM;
				$forum = array(
					'id'             => $id,
					'title'          => "[ BAD FORUM ID #{$id} ]",
					'numthreads'     => $badthreads,
					'specialscheme'  => NULL,
					'specialtitle'   => NULL,
					'pollstyle'      => 0,
					'minpower'       => 2,
					'minpowerreply'  => 2,
					'minpowerthread' => 2,
					'error'          => true,
				);
			} else if ($forum['minpower'] && $forum['minpower'] > $loguser['powerlevel']) {
				trigger_error("Attempted to access level-{$forum['minpower']} restricted forum {$id} (".($loguser['id'] ? "user's powerlevel: {$loguser['powerlevel']}; user's name: ".$loguser['name'] : "guest's IP: ".$_SERVER['REMOTE_ADDR']).")", E_USER_NOTICE);
				$meta['noindex'] = true; // prevent search engines from indexing what they can't access
				notAuthorizedError();
			}
			if ($error) {
				switch ($error) {
					case INVALID_FORUM: $errortext='This forum does not exist, but threads exist that are associated with this invalid forum ID.'; break;
				}
				$forum_error = "<tr><td style='background:#cc0000;color:#eeeeee;text-align:center;font-weight:bold;'>{$errortext}</td></tr>";
			}
		}
	}
	
	function load_thread($id, $check_forum = true) { // we boardc now
		global $sql, $meta, $loguser, $ismod, $thread, $forum, $forum_error;
		$error        = 0;
		$forum_error = "";
		
		$thread = $sql->fetchq("SELECT * FROM threads WHERE id = $id");

		if (!$thread) {
			$meta['noindex'] = true; // prevent search engines from indexing
			if (!$ismod) {
				trigger_error("Accessed nonexistant thread number #$id", E_USER_NOTICE);
				notAuthorizedError();
			}

			$badposts = $sql->resultq("SELECT COUNT(*) FROM `posts` WHERE `thread` = '{$id}'");
			if ($badposts <= 0) {
				errorpage("Thread ID #{$id} doesn't exist, and no posts are associated with the invalid thread ID.", "index.php", 'the index page');
			}

			// Mod+ can see and possibly remove bad posts
			$error = INVALID_THREAD;
			$thread = array(
				'id'      => $id, // For breadcrumbs support, see load_forum above
				'closed'  => true,
				'replies' => $badposts - 1,
				'title'   => "[ BAD THREAD ID #{$id} ]",//"Bad posts with ID #{$id}",
				'error'   => true,
			);
			$forum = array(
				'id'             => 0,
				'title'          => "",
				'specialscheme'  => NULL,
				'specialtitle'   => NULL,
				'pollstyle'      => 0,
				'minpower'       => 2,
				'minpowerreply'  => 2,
				'minpowerthread' => 2,
			);
			$check_forum = false;
		}
		if ($check_forum) {
			$forum = $sql->fetchq("SELECT * FROM forums WHERE id = '{$thread['forum']}'");

			if (!$forum) {
				$meta['noindex'] = true; // prevent search engines from indexing
				if (!$ismod) {
					trigger_error("Accessed thread number #{$id} with bad forum ID {$thread['forum']}", E_USER_WARNING);
					notAuthorizedError();
				}
				$error = INVALID_FORUM;
				$forum = array(
					'id'             => $thread['forum'], // ID should point to the existing value
					'title'          => "[ BAD FORUM ID #{$thread['forum']} ]",
					'specialscheme'  => NULL,
					'specialtitle'   => NULL,
					'pollstyle'      => 0,
					'minpower'       => 2,
					'minpowerreply'  => 2,
					'minpowerthread' => 2,
					'error'          => true,
				);
			} else if ($forum['minpower'] && $forum['minpower'] > $loguser['powerlevel']) {
				$meta['noindex'] = true; // prevent search engines from indexing what they can't access
				trigger_error("Attempted to access thread $id in level-{$forum['minpower']} restricted forum {$thread['forum']} (".($loguser['id'] ? "user's powerlevel: {$loguser['powerlevel']}; user's name: ".$loguser['name'] : "guest's IP: ".$_SERVER['REMOTE_ADDR']).")", E_USER_NOTICE);
				notAuthorizedError();
			}
		}
		
		if ($error) {
			switch ($error) {
				case INVALID_THREAD: $errortext='This thread does not exist, but posts exist that are associated with this invalid thread ID.'; break;
				case INVALID_FORUM:  $errortext='This thread has an invalid forum ID; it is located in a forum that does not exist.'; break;
			}
			$forum_error = "<tr><td style='background:#cc0000;color:#eeeeee;text-align:center;font-weight:bold;'>{$errortext}</td></tr>";
		}
		
	}
	
	function load_poll($id, $pollstyle = -1) {
		global $sql, $poll, $loguser;
		$poll = $sql->fetchq("SELECT * FROM poll WHERE id = '{$id}'");
		if (!$poll) return NULL;
		
		// Determine the user's poll votes
		if ($loguser['id']) {
			$poll['myvotes'] = $sql->getresultsbykey("SELECT `choice`, 1 FROM `pollvotes` WHERE `poll` = '{$poll['id']}' AND `user` = '{$loguser['id']}'");
		} else {
			$poll['myvotes'] = array();
		}
		
		// If we're not forcing a poll style, use the user provided one
		$poll['style'] = ($pollstyle >= 0) ? $pollstyle : $loguser['pollstyle'];
		
		// Get normal poll data (blank index will contain total)
		$poll['votes'] = $sql->getresultsbykey("
			SELECT choice, COUNT(*) cnt
			FROM pollvotes
			WHERE poll = {$id}
			GROUP BY choice WITH ROLLUP
		");
		$poll['votes']['total'] = filter_int($poll['votes'][""]);
		unset($poll['votes'][""]);
		
		if ($pollstyle) { // Influence data is only necessary with the infuence poll style (but not vice versa)
			$poll['influ'] = $sql->getresultsbykey("
				SELECT choice, SUM(u.influence) inf
				FROM pollvotes p
				LEFT JOIN users u ON p.user = u.id
				WHERE poll = {$id}
				GROUP BY choice WITH ROLLUP
			");
			$poll['influ']['total'] = filter_int($poll['influ'][""]);
			unset($poll['influ'][""]);
		}
		// Users who have voted
		$poll['usertotal'] = (int) $sql->resultq("SELECT COUNT(DISTINCT `user`) FROM pollvotes WHERE poll = {$poll['id']}");
		$poll['choices']   = array();
		
		// Enumerate through the poll choices and filter the missing votes
		$pollcs = $sql->query("SELECT * FROM poll_choices WHERE poll = {$id}");
		while ($x = $sql->fetch($pollcs)) {
			$poll['votes'][$x['id']] = filter_int($poll['votes'][$x['id']]);
			if ($pollstyle) {
				$poll['influ'][$x['id']] = filter_int($poll['influ'][$x['id']]);
			}
			$poll['choices'][$x['id']] = $x;
		}
		return true;
	}
	
	function preview_poll($in, $forum) {
		$out = array(
			'id'         => NULL,
			'question'   => $in['question'],
			'briefing'   => $in['briefing'],
			'doublevote' => $in['doublevote'],
			'closed'     => 0,
			'myvotes'    => array(),
			'votes'      => array(),
			'style'      => 0, // Always standard
			'usertotal'  => 0,
			'choices'    => merge_choice_arrays($in['chtext'], $in['chcolor'], filter_array($in['remove'])),
		);
		return print_poll($out, 0, $forum);
	}
	
	function merge_choice_arrays($chtext, $chcolor, $remove = array()) {
		$out = array();
		foreach ($chtext as $key => $val) {
			$out[$key] = array(
				'id'     => $key,
				'poll'   => NULL, 
				'choice' => $chtext[$key], 
				'color'  => $chcolor[$key],
				'remove' => (!$val || isset($remove[$key])), // Mark blank entries or those marked for deletion
			);
		}
		return $out;
	}
	
	function print_poll($poll, $thread = 0, $forum = 0) {
		global $loguser, $ismod;
		
		$confirm = generate_token(TOKEN_VOTE);
		$choices = "";
		// For each choice calculate the votes
		foreach ($poll['choices'] as $id => $choice) {
			if (filter_bool($choice['remove'])) continue; // Edit poll support
			
			$link = '';
			if ($thread) { // No links or real vote counter for poll previews
				// poll['votes'][<choice>] -> normal votes
				// poll['influ'][<choice>] -> influence votes
				if ($poll['style']) { // Influence
					if ($poll['influ']['total']) { // $poll['usertotal'] && 
						$pct  = $pct2 = sprintf('%02.1f', $poll['influ'][$id] / $poll['influ']['total'] * 100);
					} else {
						$pct  = $pct2 = "0.0"; // No votes!
					}
					// <infl> points (<norm>)
					$votes = "{$poll['influ'][$id]} point".($poll['influ'][$id] == 1 ? '' : 's')." ({$poll['votes'][$id]})";
				} else { // Normal
					if ($poll['votes']['total']) { // $poll['usertotal'] && 
						$pct  = sprintf('%02.1f', $poll['votes'][$id] / $poll['votes']['total'] * 100);
						$pct2 = sprintf('%02.1f', $poll['votes'][$id] / $poll['usertotal'] * 100);
					} else
						$pct  = $pct2 = "0.0";
					// <norm> votes
					$votes = "{$poll['votes'][$id]} vote".($poll['votes'][$id] == 1 ? '' : 's');
				}

				// Has the logged in user voted on this choice?
				if (isset($poll['myvotes'][$id])) {
					$linkact = 'del';
					$dot = "<img src='images/dot4.gif' align='absmiddle'> ";
				} else {
					$linkact = 'add';
					$dot = "<img src='images/_.gif' width=8 height=8 align='absmiddle'> ";
				}

				if ($loguser['id'] && !$poll['closed']) {
					$link = "<a href='?id={$thread['id']}&auth={$confirm}&vact={$linkact}&vote={$id}'>";
				}
				
				// Edit poll linkery
				if ($ismod) {
					$polledit = "-- <a href='editpoll.php?id={$thread['id']}'>Edit poll</a>";
				} else if ($loguser['id'] == $thread['user']) {
					$polledit = "-- <a href='editpoll.php?id={$thread['id']}&close&auth=".generate_token(TOKEN_MGET)."'>".($poll['closed'] ? "Open" : "Close")." poll</a>";
				} else {
					$polledit = "";
				}
				
			} else {
				// Poll previews show the colors
				$pct = $pct2 = "50.0"; // mt_rand(30, 100).".0";
				$votes = "? votes";
				$polledit = $dot = "";
			}
			
			
			// Generate the bar graphics
			$barpart = "<table cellpadding=0 cellspacing=0 width=$pct% bgcolor='".($choice['color'] ? $choice['color'] : "cccccc")."'><td>&nbsp;</table>";
			if ($pct == "0.0") {
				$barpart = '&nbsp;';
			}

			
			$choices	.= "
			<tr>
				<td class='tdbg1' width=20%>{$dot}{$link}".xssfilters($choice['choice'])."</a></td>
				<td class='tdbg2' width=60%>{$barpart}</td>
				<td class='tdbg1 center' width=20%>".($poll['doublevote'] ? "{$pct}% of users, {$votes} ({$pct2}%)" : "{$pct}%, {$votes}")."</td>
			</tr>";
		}


		if ($poll['closed']) {
			$polltext = 'This poll is closed.';
		} else {
			$polltext = 'Multi-voting is '.(($poll['doublevote']) ? 'enabled.' : 'disabled.');
		}                 
		if ($poll['usertotal'] != 1) {
			$s_have = 's have';
		} else {
			$s_have = ' has';
		} 
		
		return " 
			<table class='table'>
				<tr><td class='tdbgc center b' colspan=3>".htmlspecialchars($poll['question'])."</td></tr>
				<tr><td class='tdbg2 fonts' colspan=3>".nl2br(dofilters($poll['briefing']), $forum)."</td></tr>
				{$choices}
				<tr><td class='tdbg2 fonts' colspan=3>&nbsp;{$polltext} {$poll['usertotal']} user{$s_have} voted. {$polledit}</td></tr>
			</table>
			<br>";
	}
	
	function get_poll_from_thread($id) {
		global $sql;
		return $sql->resultq("SELECT poll FROM threads WHERE id = {$id}");
	}
	
	function vote_poll($pollid, $choice, $user, $action) {
		global $sql;
		if (!$user) return false;
		$poll  = $sql->fetchq("SELECT * FROM poll WHERE id = {$pollid}");
		if (!$poll || $poll['closed']) return false;
		$valid = $sql->resultq("SELECT COUNT(*) FROM `poll_choices` WHERE `poll` = '{$pollid}' AND `id` = '{$choice}'");
		if (!$valid) return false;
		
		if ($action == 'add') {
			if (!$poll['doublevote']) {
				$sql->query("DELETE FROM `pollvotes` WHERE `user` = '{$user}' AND `poll` = '$pollid'");
			}
			$sql->query("INSERT INTO pollvotes (poll,choice,user) VALUES ($pollid,$choice,{$user})");
		} else {
			$sql->query("DELETE FROM `pollvotes` WHERE `user` = '{$user}' AND `poll` = '$pollid' AND `choice` = '$choice'");
		}
		return true;
	}
	
	function create_thread($user, $forum, $title, $description, $posticon, $pollid = 0, $closed = 0, $sticky = 0, $announcement = 0) {
		global $sql;
			// $user consistency support
			if (is_array($user)) {
				$user = filter_int($user['id']);
				if (!$user) return 0;
			}
			$currenttime = ctime();
			
			// Insert thread
			$vals = array(
				'forum'             => $forum,
				'user'              => $user,
				
				'closed'            => $closed,
				'sticky'            => $sticky,
				'announcement'      => $announcement,
				
				'poll'              => $pollid,
				
				'title'             => xssfilters($title),
				'description'       => xssfilters($description),
				'icon'              => $posticon,
				
				'views'             => 0,
				'replies'           => 0,
				'firstpostdate'     => $currenttime,
				'lastpostdate'      => $currenttime,
				'lastposter'        => $user,
			);
			$sql->queryp("INSERT INTO `threads` SET ".mysql::setplaceholders($vals), $vals);
			$tid = $sql->insert_id();
			$sql->query("UPDATE `forums` SET `numthreads` = `numthreads` + 1 WHERE id = {$forum}");
			return $tid;
	}
	
	function create_post($user, $forum, $thread, $message, $ip, $moodid = 0, $nosmilies = 0, $nolayout = 0, $threadupdate = "") {
		global $sql;
		
		// $user consistency support
		if (!is_array($user)) {
			$user = $sql->fetchq("SELECT id, posts, regdate, postheader, postsignature FROM users WHERE id = {$user}");
			if (!$user) return 0;
		}
		
		$numposts 		= $user['posts'] + 1;
		$numdays 		= (ctime() - $user['regdate']) / 86400;
		$tags			= array();
		$message 		= doreplace($message, $numposts, $numdays, $user['id'], $tags);
		$tagval			= json_encode($tags);
		
		if ($nolayout) {
			$headid = 0;
			$signid = 0;
		} else {
			$headid = getpostlayoutid($user['postheader']);
			$signid = getpostlayoutid($user['signature']);
		}
		$currenttime = ctime();
		
		// Update posts & stats
		$vals = array(
			'thread'        => $thread,
			'user'          => $user['id'],
			'date'          => $currenttime,
			'ip'            => $ip,
			'num'           => $numposts,
			
			'headid'        => $headid,
			'signid'        => $signid,
			'moodid'        => $moodid,
			
			'text'          => xssfilters($message),
			'tagval'        => $tagval,
			'options'       => $nosmilies . "|" . $nolayout,
		);
		$sql->queryp("INSERT INTO `posts` SET ".mysql::setplaceholders($vals), $vals);
		$pid = $sql->insert_id();
		$sql->query("UPDATE `users` SET `posts` = posts + 1, `lastposttime` = '{$currenttime}' WHERE `id` = '{$user['id']}'");
		$sql->query("UPDATE `forums` SET `numposts` = `numposts` + 1, `lastpostdate` = '{$currenttime}', `lastpostuser` = '{$user['id']}', `lastpostid` = '{$pid}' WHERE `id` = '{$forum}'");
		if ($sql->resultq("SELECT COUNT(*) FROM posts WHERE thread = {$thread}") > 1) {
			// Not the first post: update other stats
			$sql->query("UPDATE `threads` SET {$threadupdate} `replies` = `replies` + 1, `lastpostdate` = '{$currenttime}', `lastposter` = '{$user['id']}' WHERE `id` = '{$thread}'");
			$sql->query("UPDATE `threadsread` SET `read` = '0' WHERE `tid` = '{$thread}'");
			$sql->query("REPLACE INTO threadsread SET `uid` = '{$user['id']}', `tid` = '{$thread}', `time` = '{$currenttime}', `read` = '1'");
		}
		return $pid;	
	}
	
	function create_poll($question, $briefing, $chtext, $chcolor, $doublevote = 0) {
		global $sql;

		// Process main poll data
		$vals =	array(
			'question'			=> xssfilters($question),
			'briefing'			=> xssfilters($briefing),
			'closed'			=> 0,
			'doublevote'		=> $doublevote,
		);
		$sql->queryp("INSERT INTO `poll` SET ".mysql::setplaceholders($vals), $vals);
		$pollid = $sql->insert_id();
		// Process choices
		$addchoice = $sql->prepare("INSERT INTO `poll_choices` (`poll`, `choice`, `color`) VALUES (?,?,?)");
		for ($i = 1; isset($chtext[$i]); ++$i) {
			if (!isset($chtext[$i]) || !trim($chtext[$i])) { // No blank options
				continue;
			} else if (!isset($chcolor[$i]) || !trim($chcolor[$i])) {
				$chcolor[$i] = 'red';
			}
			$sql->execute($addchoice, array($pollid, $chtext[$i], $chcolor[$i]));
		}
		
		return $pollid;
	}
	
	function move_thread($id, $dest_forum, $thread = NULL) {
		global $sql, $config, $loguser, $ismod, $isfullmod;
		if (!$ismod) return false;
		
		if ($thread === NULL) {
			$thread = $sql->fetchq("SELECT id, forum, replies FROM threads WHERE id = '{$id}'");
			if (!$thread) {
				return false;
			}
		}
		if ($dest_forum != $thread['forum']) {
			$valid = $sql->resultq("SELECT COUNT(*) FROM forums WHERE id = '{$dest_forum}' AND (!minpower OR minpower <= {$loguser['powerlevel']})");
			if (!$valid) {
				return false;
			}
			// Are we mods in this forum?
			if (!$isfullmod) {
				$allowed = $sql->getresults("SELECT forum FROM forummods WHERE user = {$loguser['id']}");
				$allowed[] = $config['trash-forum'];
				if (!in_array($dest_forum, $allowed)) {
					return false;
				}
			}
			
			$sql->query("UPDATE threads SET forum = {$dest_forum} WHERE id = {$id}");
			
			// Update the forum counters appropriately
			//$numposts = $sql->resultq("SELECT COUNT(*) FROM posts WHERE thread = $id");
			$numposts = $thread['replies'] + 1; // Hopefully this won't break anything
			$t1 = $sql->fetchq("SELECT lastpostdate, lastposter FROM threads WHERE forum = {$thread['forum']} ORDER BY lastpostdate DESC LIMIT 1");
			$t2 = $sql->fetchq("SELECT lastpostdate, lastposter FROM threads WHERE forum = {$dest_forum}      ORDER BY lastpostdate DESC LIMIT 1");
			$sql->queryp("UPDATE forums SET numposts=numposts-$numposts,numthreads=numthreads-1,lastpostdate=?,lastpostuser=? WHERE id={$thread['forum']}", [(int) $t1['lastpostdate'], (int) $t1['lastposter']]);
			$sql->queryp("UPDATE forums SET numposts=numposts+$numposts,numthreads=numthreads+1,lastpostdate=?,lastpostuser=? WHERE id={$dest_forum}", [$t2['lastpostdate'],$t2['lastposter']]);
		}
		return true;
	}
	
	function move_posts($posts, $thread, $dest_thread) {
		global $sql, $config, $loguser, $ismod, $isfullmod;
		if (!$ismod) return false;
		// Base for thread merge functionality added
		// Of course, the tricky part is providing an interface for it
		if ($posts && $thread != $dest_thread) {
			// All the posts must exist in the same thread (the counts have to match), otherwise assume tampering
			$posts = array_map('intval', $posts);
			$post_count = $sql->resultq("SELECT COUNT(*) FROM posts WHERE thread = '{$thread}' AND id IN (".implode(',', $posts).")");
			if (count($posts) != $post_count) {
				return false;
			}
			
			// ..
			$data = $sql->fetchq("
				SELECT t.id, t.forum, t.replies, f.id valid_forum
				FROM threads t
				LEFT JOIN forums f ON t.thread = f.id
				WHERE (t.id = '{$thread}' OR t.id = '{$dest_thread}')
				  AND (f.minpower IS NULL OR !f.minpower OR f.minpower <= {$loguser['powerlevel']})
			", PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
			
			if (
				   (!$ismod && (!isset($data[$thread]) || !$data[$thread]['valid_forum'])) // Source thread invalid (still allow full mods to move posts off invalid threads)
				|| !$data[$dest_thread]['id'] // The dest thread doesn't exist
				|| !$data[$dest_thread]['valid_forum'] // The destination forum doesn't exist
			) {
				return false;
			}	

			// Require mod status in dest thread
			$allowed = $sql->getresults("SELECT forum FROM forummods WHERE user = {$loguser['id']}");
			$allowed[] = $config['trash-forum'];
			if (!in_array($dest_forum, $allowed)) {
				return false;
			}
			
			// All OK; move the posts
			$sql->query("UPDATE posts SET thread = {$dest_thread} WHERE id IN (".implode(',', $posts).")");
			
			if (isset($data[$thread])) {
				// Update the data for thread last post info (we already have the postcount offset)
				$p1 = $sql->fetchq("SELECT date, user FROM threads WHERE thread = {$thread} ORDER BY date ASC LIMIT 1");
				$toffset = min($data[$thread]['replies'], $post_count); // The replies value should never be negative or the query will break
				$sql->query("UPDATE threads SET replies=replies-{$toffset},lastpostdate=?,lastposter=? WHERE id = {$thread}", [(int) $p1['lastpostdate'], (int) $p1['lastposter']]);
				
				// Do the same for forum data (if the forum is real and we're moving posts through different forums)
				if ($data[$thread]['valid_forum'] && $data[$thread]['forum'] != $data[$dest_thread]['forum']) {
					$t1 = $sql->fetchq("SELECT lastpostdate, lastposter FROM threads WHERE forum = {$data[$thread]['forum']} ORDER BY lastpostdate DESC LIMIT 1");
					$sql->queryp("UPDATE forums SET numposts=numposts-{$post_count},lastpostdate=?,lastpostuser=? WHERE id={$data[$thread]['forum']}", [(int) $t1['lastpostdate'], (int) $t1['lastposter']]);
				}
			}
			
			// Do the same with the other thread)
			$p2 = $sql->fetchq("SELECT date, user FROM threads WHERE thread = {$dest_thread} ORDER BY date ASC LIMIT 1");
			$sql->query("UPDATE threads SET replies=replies+{$post_count},lastpostdate=?,lastposter=? WHERE id = {$dest_thread}", [(int) $p2['lastpostdate'], (int) $p2['lastposter']]);
			
			if ($data[$thread]['forum'] != $data[$dest_thread]['forum']) {
				$t2 = $sql->fetchq("SELECT lastpostdate, lastposter FROM threads WHERE forum = {$data[$dest_thread]['forum']} ORDER BY lastpostdate DESC LIMIT 1");
				$sql->queryp("UPDATE forums SET numposts=numposts+{$post_count},lastpostdate=?,lastpostuser=? WHERE id={$data[$dest_thread]['forum']}", [(int) $t2['lastpostdate'], (int) $t2['lastposter']]);
			}
		}
		return true;
	}