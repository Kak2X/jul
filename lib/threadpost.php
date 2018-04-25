<?php
	
	function threadpost($post,$bg,$forum = 0,$pthread='') {
		
		global $config, $loguser, $sep, $tlayout, $blockedlayouts, $isadmin, $ismod;
		
		// Fetch an array containing all blocked layouts now
		if (!isset($blockedlayouts)) {
			global $sql;
			$blockedlayouts = $sql->getresultsbykey("SELECT blocked, 1 FROM blockedlayouts WHERE user = {$loguser['id']}");
			if (!$blockedlayouts)
				$blockedlayouts = 0;
		}
		
		
		$set['bg']    = $bg; //${"tablebg$bg"};
		$userlink = getuserlink($post, $post['uid'], "url".$post['uid']);
		$set['userlink'] = "<a name={$post['uid']}></a>{$userlink}";
		$set['date']     = printdate($post['date']);
		if (!isset($post['num'])) $post['num'] = 0;
		
		$post = setlayout($post);	
		
		if ($post['deleted']) { // Note: if a post is pinned we don't count it as deleted
			$post['text'] = "(Post deleted)";
			$set['userrank'] = $set['location'] = "";
			$set['picture']  = $set['userpic']  = "";
			$set['attach'] = "";
		} else {
		
			$set['userrank'] = getrank(
				filter_int($post['useranks']), 
				filter_string($post['title']),
				$post['posts'],
				$post['powerlevel'],
				$post['ban_expire']
			);
			
			
			$set['location'] = filter_string($post['location']) ? "<br>From: ". htmlspecialchars($post['location']) : ""; 

			if ($config['allow-avatar-storage']) {
				if ($post['piclink']) {
					$set['picture'] = escape_attribute($post['piclink']);
					$set['userpic'] = "<img class='avatar' src=\"{$set['picture']}\">"; 
				} else if (file_exists(avatar_path($post['uid'], $post['moodid']))) {
					$set['picture'] = avatar_path($post['uid'], $post['moodid']);
					$set['userpic'] = "<img class='avatar' src=\"{$set['picture']}\">"; 
				} else {
					$set['picture'] = $set['userpic'] = "";
				}
				
			} else {
				// $set['picture'] doesn't seem to be used...
				if ($post['moodid'] && $post['moodurl']) { // mood avatar
					$set['picture'] = str_replace('$', $post['moodid'], escape_attribute($post['moodurl']));
					$set['userpic'] = "<img class='avatar' src=\"{$set['picture']}\">";
				} else if (isset($post['picture'])) { // default avatar
					$set['picture'] = escape_attribute($post['picture']);
					$set['userpic'] = "<img class='avatar' src=\"{$set['picture']}\">";
				} else { // null
					$set['userpic'] = $set['picture'] = "";
				}
			}

			if ($post['signtext']) {
				$post['signtext'] = $sep[$loguser['signsep']].$post['signtext'];
			}
			
			// Display the pretty attachment list
			if (filter_array($post['attach'])) {
				$set['attach'] = attachfield($post['attach'], ($forum < 0 ? "&pm" : "")); //, ($isadmin || $post['uid'] == $loguser['id']));
			} else {
				$set['attach'] = "";
			}
			
			$post['text'] = doreplace2($post['text'], $post['options']);
		}

		// Thread marker for posts by thread / favourites view
		if ($pthread) { 
			$set['threadlink'] = "<a href=thread.php?id={$pthread['id']}>{$pthread['title']}</a>";
		}

		// Edit date and revision selector
		if (filter_int($post['editdate'])) {
			$post['edited'] = " (last edited by {$post['edited']} at ".printdate($post['editdate']);
			if (!$ismod || $post['revision'] < 2) { // Hide selector
				$post['edited'] .= ")";
			} else {
				// Display revision info if one is selected
				if ($_GET['rev'] && $_GET['pin'] == $post['id']) {
					$post['edited'] .= "; this revision edited by ".getuserlink(NULL, $post['revuser'])." at ".printdate($post['revdate']);
					$sel = $_GET['rev'];
				} else { // Select max revision if none is specified
					$sel = $post['revision'];
				}
				$post['edited'] .= ") | Revisions:";
				// Revision selector
				for ($i = 1; $i < $post['revision']; ++$i) {
					$w = ($i == $sel) ? "z" : "a";
					$post['edited'] .= " <{$w} href='?pid={$post['id']}&pin={$post['id']}&rev={$i}#{$post['id']}'>{$i}</{$w}>";
				}
				$w = ($i == $sel) ? "z" : "a"; // Last revision
				$post['edited'] .= " <{$w} href='?pid={$post['id']}#{$post['id']}'>{$i}</{$w}>";
			}
		} else {
			$post['edited'] = "";
		}
		
		if ($forum < 0) $forum = 0; // Restore actual forum value once we're done with PM Attachments
		
		return dofilters(postcode($post,$set), $forum);
	}

	function preplayouts($posts) {
		global $sql, $postl;

		$ids = array();

		// Just fetch everything now instead of hitting the DB for each new header/signature encountered
		while ($ps = $sql->fetch($posts)) {
			if ($ps['headid']) $ids[] = $ps['headid'];
			if ($ps['signid']) $ids[] = $ps['signid'];
		}

		if (!count($ids)) return;
		$postl = $sql->getresultsbykey("SELECT id, text FROM postlayouts WHERE id IN (".implode(",", array_unique($ids, SORT_NUMERIC)).")");
	}

	function setlayout($post) {
		global $sql,$loguser,$postl,$blockedlayouts;
		
		
		if ($loguser['viewsig']!=1) { // Autoupdate
			$post['headid']=$post['signid']=0;
		}

		$post['blockedlayout'] = isset($blockedlayouts[$post['uid']]);
		if (!$loguser['viewsig'] || $post['deleted'] || $post['blockedlayout']) { // Disabled
			$post['headtext']=$post['signtext']='';
			$post['headid']=$post['signid']=0;
			return $post;
		}

		if ($loguser['viewsig']!=2) { // Not Autoupdate
			if ($headid=filter_int($post['headid'])) {
				// just in case
				if($postl[$headid] === NULL) $postl[$headid]=$sql->resultq("SELECT text FROM postlayouts WHERE id=$headid");
				$post['headtext']=$postl[$headid];
			}
			if ($signid=filter_int($post['signid'])) {
				// just in case
				if($postl[$signid] === NULL) $postl[$signid]=$sql->resultq("SELECT text FROM postlayouts WHERE id=$signid");
				$post['signtext']=$postl[$signid];
			}
		}

		$post['headtext'] = settags($post['headtext'],filter_string($post['tagval']));
		$post['signtext'] = settags($post['signtext'],filter_string($post['tagval']));

		if ($loguser['viewsig'] == 2) { // Autoupdate
			$post['headtext'] = doreplace($post['headtext'],$post['num'],($post['date']-$post['regdate'])/86400,$post['uid']);
			$post['signtext'] = doreplace($post['signtext'],$post['num'],($post['date']-$post['regdate'])/86400,$post['uid']);
		}
		
		// Prevent topbar CSS overlap for non-autoupdating layouts
		if ($post['headid']) {
			$post['headtext'] = preg_replace("'\.(top|side|main|cont)bar{$post['uid']}'si", ".$1bar{$post['uid']}_{$post['headid']}", $post['headtext']);
		} else {
			$post['headtext'] = preg_replace("'\.(top|side|main|cont)bar{$post['uid']}'si", ".$1bar{$post['uid']}_x{$post['id']}", $post['headtext']);
		}
		$post['headtext'] = doreplace2($post['headtext']);
		$post['signtext'] = doreplace2($post['signtext']);
		//	$post['text'] = doreplace2($post['text'], $post['options']);
		return $post;
	}
	
	function getpostlayoutid($text) {
		global $sql;
		
		// Everything breaks on transactions if $text is blank
		if (!$text) return 0;
		
		$id = $sql->resultp("SELECT id FROM postlayouts WHERE text = ? LIMIT 1", [$text]);
		// Is this a new layout?
		if (!$id) {
			$sql->queryp("INSERT INTO postlayouts (text) VALUES (?)", [$text]);
			$id = $sql->insert_id();
		}
		return $id;
	}
	
	const PREVIEW_NEW     = 0;
	const PREVIEW_EDITED  = 1;
	const PREVIEW_PROFILE = 2;
	const PREVIEW_PM      = 3;
	function preview_post($user, $data, $flags = PREVIEW_NEW, $title = "Post preview") {
		global $sql, $controls, $loguser, $config, $isadmin;
		
		// $user should be an array with user data
		if (is_int($user)) {
			if ($user == $loguser['id']) {
				$user = $loguser;
			} else {
				$user = $sql->fetchq("SELECT * FROM users WHERE id = {$user}");
			}
		}
		//$data           = array_merge($user, $data);
		$currenttime    = ctime();
		$numdays		= ($currenttime - $user['regdate']) / 86400;
		
		if ($flags == PREVIEW_EDITED) {
			$posts     = $user['posts'];
			$tags      = NULL;
		} else {
			if ($flags == PREVIEW_PROFILE) {
				$posts	  = $user['posts'];
			} else {
				$posts    = $user['posts'] + 1;
			}
			$data['date'] = $currenttime;
			$data['num']  = $posts;
			$data['head'] = $user['postheader'];
			$data['sign'] = $user['signature'];
			$tags         = array();
		}
		
		loadtlayout();

		$ppost           = $user;
		$ppost['posts']  = $posts;
		$ppost['uid']    = $user['id'];
		$ppost['num']    = filter_int($data['num']); // Not sent on PMs
		$ppost['date']   = $data['date'];
		$ppost['moodid'] = $data['moodid'];
		$ppost['noob']   = filter_int($data['noob']);
		$ppost['text']   = doreplace($data['message'],$posts,$numdays,$user['id'],$tags);
		$ppost['tagval'] = $tagval = json_encode($tags);

		if ($data['nolayout']) {
			$ppost['headtext'] = "";
			$ppost['signtext'] = "";
		} else {
			$ppost['headtext'] = doreplace($data['head'],$posts,$numdays,$user['id']);	
			$ppost['signtext'] = doreplace($data['sign'],$posts,$numdays,$user['id']);
		}

		$ppost['deleted']       = 0;
		$ppost['options']		= "{$data['nosmilies']}|{$data['nohtml']}";
		$ppost['act'] 			= $sql->resultq("SELECT COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." AND user = {$user['id']}");
		
		// Save ourselves a query if we're (somehow) not needing the picture link
		if ($config['allow-avatar-storage']) {
			$ppost['piclink']   = $sql->resultq("SELECT weblink FROM users_avatars WHERE user = {$user['id']} AND file = {$data['moodid']}");
		} else {
			$ppost['piclink']   = "";
		}
		
		// Attachment preview stuff / edit marker
		if ($flags == PREVIEW_EDITED) {
			// If we're viewing the post preview when *EDITING* a new post/pm
			// the attachment list should contain the temp attachments and the already uploaded attachments
			// (and hide those marked as deleted)
			if ($config['allow-attachments'] && $data['attach_key'] !== NULL) {
				$real = get_saved_attachments($data['id'], isset($data['attach_pm']), $data['attach_sel']);
				$temp = get_temp_attachments($data['attach_key'], $user['id']);
				$ppost['attach'] = array_merge($real, $temp);
			}
			// Edit marker
			$ppost['edited']	= getuserlink($loguser);
			$ppost['editdate'] 	= $currenttime;
		} else if ($flags == PREVIEW_PROFILE) {
			$data['ip'] = $user['lastip'];
		} else {
			// If we're viewing the post preview when creating a new post/pm/etc
			// the attachment list should contain the temp attachments
			if ($config['allow-attachments'] && $data['attach_key'] !== NULL) {
				$ppost['attach'] = get_temp_attachments($data['attach_key'], $user['id']);
			}
			$data['ip'] = $_SERVER['REMOTE_ADDR'];
		}
		
		$controls = array();
		$controls['ip'] = $controls['quote'] = $controls['edit'] = "";
		if ($isadmin) {
			$controls['ip'] = " | IP: <a href='admin-ipsearch.php?ip={$data['ip']}'>{$data['ip']}</a>";
		}
		
		
	return ($title ? "
	<table class='table'>
		<tr>
			<td class='tdbgh center'>
				{$title}
			</td>
		</tr>
	</table>" : "")."
	<table class='table'>
		".threadpost($ppost, 1, $data['forum'])."
	</table>
	<br>";
	}
	
function thread_history($thread, $num, $pm = false) {
	global $sql, $userfields;
	
	if ($pm) {
		$table = "pm_posts";
		$link  = "showprivate";
		$nf    = "0 ";
	} else {
		$table = "posts";
		$link  = "thread";
		$nf    = "p.";
	}
	
	$posts = $sql->query("
		SELECT {$userfields}, u.posts, p.user, p.text, p.options, p.deleted, {$nf}num
		FROM {$table} p
		LEFT JOIN users u ON p.user = u.id
		WHERE p.thread = $thread
		ORDER BY p.id DESC
		LIMIT {$num}
	");
	$i = 0;
	
	$postlist = "";

	
	if ($sql->num_rows($posts)) {
		
		$postlist .= "
		<tr>
			<td class='tdbgh center' style='width: 150px'>User</td>
			<td class='tdbgh center'>Post</td>
		</tr>";
	
		while ($post = $sql->fetch($posts)) {
			
			$bg = (($i++) % 2) + 1; //((($i++) & 1) ? 'tdbg2' : 'tdbg1');
			
			if ($num-- > 0){
				$postnum  = ($post['num'] ? "{$post['num']}/" : '');
				$userlink = getuserlink($post);
				$message  = $post['deleted'] ? '(Post deleted)' : doreplace2(dofilters($post['text'], $thread['forum']), $post['options']);
				$postlist .=
					"<tr>
						<td class='tdbg$bg' valign=top>
							{$userlink}
							<span class='fonts'><br>
								Posts: {$postnum}{$post['posts']}
							</span>
						</td>
						<td class='tdbg$bg' valign=top>
							{$message}
						</td>
					</tr>";
			} else {
				$postlist .= "<tr><td class='tdbgh center' colspan=2>This is a long thread. Click <a href='{$link}.php?id={$thread}'>here</a> to view it.</td></tr>";
			}
		}
		
	} else {
		$postlist .= "<tr><td class='tdbg1 center' colspan=2><i>There are no posts in this thread.</i></td></tr>";
	}
	
	return "
	<table class='table'>
		<tr>
			<td class='tdbgh center' colspan=2 style='font-weight:bold'>
				Thread history
			</td>
		</tr>
		{$postlist}
	</table>";
}

// Jul numgfx don't include the extra graphics (ie: Posts / EXP text), so it's necessary to redirect those
function get_complete_numdir() {
	global $numdir;
	switch ($numdir) {
		case 'ccs/':   return "num3/";
		//case 'death/': return "num1/";
		case 'jul/':   return "num2/";
		case 'ymar/':  return "num1/";
		default:       return $numdir;
	}
}

function load_syndromes() {
	global $sql;
	return $sql->getresultsbykey("SELECT user, COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." GROUP BY user");
}

function syndrome($num, $double=false, $bar=true){
	$bar	= false;
	$a		= '\'>Affected by';
	$syn	= "";
	if($num>=75)  {  $syn="83F3A3$a 'Reinfors Syndrome'";           $last=  75; $next=  25;	}
	if($num>=100) {  $syn="FFE323$a 'Reinfors Syndrome' +";         $last= 100; $next=  50;	}
	if($num>=150) {  $syn="FF5353$a 'Reinfors Syndrome' ++";        $last= 150; $next=  50;	}
	if($num>=200) {  $syn="CE53CE$a 'Reinfors Syndrome' +++";       $last= 200; $next=  50;	}
	if($num>=250) {  $syn="8E83EE$a 'Reinfors Syndrome' ++++";      $last= 250; $next=  50;	}
	if($num>=300) {  $syn="BBAAFF$a 'Wooster Syndrome'!!";          $last= 300; $next=  50;	}
	if($num>=350) {  $syn="FFB0FF$a 'Wooster Syndrome' +!!";        $last= 350; $next=  50;	}
	if($num>=400) {  $syn="FFB070$a 'Wooster Syndrome' ++!!";       $last= 400; $next=  50;	}
	if($num>=450) {  $syn="C8C0B8$a 'Wooster Syndrome' +++!!";      $last= 450; $next=  50;	}
	if($num>=500) {  $syn="A0A0A0$a 'Wooster Syndrome' ++++!!";     $last= 500; $next= 100;	}
	if($num>=600) {  $syn="C762F2$a 'Anya Syndrome' +++++!!!";      $last= 600; $next= 200;	}
	if($num>=800) {  $syn="62C7F2$a 'Xkeeper Syndrome' +++++!!";/*  $last= 600; $next= 200;		}
	if($num>=1000) {  $syn="FFFFFF$a 'Something higher than Xkeeper Syndrome' +++++!!";*/		}

	if($syn) {
		if ($next && $bar) {
			$special = ($next >= 100) ? "special" : ""; 
			$barimg = array(
				"images/bar/num1/barleft.png",
				"images/bar/num1/bar-on{$special}.png",
				"images/bar/num1/bar-off.png",
				"images/bar/num1/barright.png",
			);
			
			$multi  = ($double) ? 2 : 1;
			$bar	= "<br>
				<nobr>
					". generatenumbergfx($num, 3, $double) ."
					". drawprogressbar(150 * $multi, 8 * $multi, ($num - $last), $next, $barimg) ."
					". generatenumbergfx($next - ($num - $last), 3, $double) ."
				</nobr>";
		}
		$syn="<br><i><span style='color: #$syn</span></i>$bar<br>";
	}

	return $syn;
}
