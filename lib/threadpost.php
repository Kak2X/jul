<?php
	
	function threadpost($post, $bg, $mode = MODE_POST, $forum = 0,$pthread = '', $multiforum = false) {
		
		global $config, $loguser, $tlayout, $blockedlayouts, $isadmin, $ismod, $statusicons, $warnpic;
		
		// Fetch an array containing all blocked layouts now
		if (!isset($blockedlayouts)) {
			global $sql;
			$blockedlayouts = $sql->getresultsbykey("SELECT blocked, 1 FROM blockedlayouts WHERE user = {$loguser['id']}");
			if (!$blockedlayouts)
				$blockedlayouts = 0;
		}
		
		
		$set['bg']       = $bg;
		$set['userlink'] = getuserlink($post, $post['uid'], "url".$post['uid']);;
		$set['date']     = printdate($post['date']);
		if (!isset($post['num'])) $post['num'] = 0;
		
		$post = setlayout($post);	
		
		if ($post['deleted']) { // Note: if a post is pinned we don't count it as deleted
			$by = $post['ud_id'] ? " by ".getuserlink(get_userfields($post, 'ud')) : "";
			$reason = $post['deletereason'] ? " for reason: ".htmlspecialchars($post['deletereason']) : "";
			$post['text'] = "<span class='fonts i'>(Post deleted{$by}{$reason})</span>";
			$set['userrank'] = $set['location'] = "";
			$set['picture']  = $set['userpic']  = "";
			$set['attach']   = "";
			//hook_use_ref('threadpost-deleted', $set, $post, $mode);
		} else {
		
			$set['userrank'] = getrank(
				filter_int($post['useranks']), 
				filter_string($post['title']),
				$post['posts'],
				$post['powerlevel'],
				$post['ban_expire']
			);
			
			
			$set['location'] = filter_string($post['location']) ? "<br>From: ". str_ireplace("&lt;br&gt;", "<br>", htmlspecialchars($post['location'])) : ""; 
			
			prepare_avatar($post, $set['picture'], $set['userpic']);

			if ($post['signtext']) {
				$post['signtext'] = SIGNSEP[$loguser['signsep']].$post['signtext'];
			}
			
			// Display the pretty attachment list
			if (filter_array($post['attach'])) {
				$set['attach'] = attachfield($post['attach']); //, ($isadmin || $post['uid'] == $loguser['id']));
			} else {
				$set['attach'] = "";
			}
			
			//hook_use_ref('threadpost', $set, $post, $mode);
			
			$post['text'] = domarkup($post['text'], $post, false, $mode);
		}

		// Thread marker for posts by thread / favourites view
		if ($pthread) { 
			$set['threadlink'] = "<a href=thread.php?id={$pthread['tid']}>".htmlspecialchars($pthread['title'])."</a>";
		}

		// Edit date and revision selector
		if (filter_int($post['editdate'])) {
			$set['edited'] = " (last edited by ".getuserlink(get_userfields($post, 'ue'))." at ".printdate($post['editdate']);
			if (!$ismod || $post['revision'] < 2) { // Hide selector
				$set['edited'] .= ")";
			} else if ($mode == MODE_POST) {
				// Display revision info if one is selected
				if (__($_GET['rev']) && $_GET['pin'] == $post['id']) {
					$set['edited'] .= "; this revision edited by ".getuserlink(NULL, $post['revuser'])." at ".printdate($post['revdate']);
					$sel = $_GET['rev'];
				} else { // Select max revision if none is specified
					$sel = $post['revision'];
				}
				$set['edited'] .= ") | Revisions:";
				// Revision selector
				for ($i = 1; $i < $post['revision']; ++$i) {
					$w = ($i == $sel) ? "z" : "a";
					$set['edited'] .= " <{$w} href='?pid={$post['id']}&pin={$post['id']}&rev={$i}#{$post['id']}'>{$i}</{$w}>";
				}
				$w = ($i == $sel) ? "z" : "a"; // Last revision
				$set['edited'] .= " <{$w} href='?pid={$post['id']}#{$post['id']}'>{$i}</{$w}>";
			}
		} else {
			$set['edited'] = "";
		}
		
		$set['new'] = __($post['new']) ? "{$statusicons['new']} | " : "";
		$set['mode'] = $mode;
		
		$set['userspan'] = $post['noob'] ? "<span class='userlink' style='display: inline; position: relative; top: 0; left: 0;'><img src='images/noob/noobsticker2-".mt_rand(1,6).".png' style='position: absolute; top: -3px; left: ".floor(strlen($post['name'])*2.5)."px;' title='n00b'>" : "<span class='userlink'>";
		$set['warntext'] = $post['warned'] ? "<div class='alert alert-error'>{$warnpic} ".($post['warntext'] ? domarkup($post['warntext']) : "(USER WAS WARNED FOR THIS POST)")."</div>" : "";
		
		if ($post['highlighted']) {
			if ($post['highlighted'] == PHILI_LOCAL) {
				$typetxt    = "<b>Highlight</b>";
			} else {
				$typetxt    = "<b>Featured</b>";
			}
			$urlback	= isset($post['highlightprev']) ? "<a href='{$post['highlightprev']}'>&lt;&lt;</a> " : "";
			$urlnext	= isset($post['highlightnext']) ? " <a href='{$post['highlightnext']}'>&gt;&gt;</a>" : "";
			
			$set['highlightctrl'] = "{$urlback}{$typetxt}{$urlnext} | ";
			$set['highlighttext'] = $post['highlighttext'] ? "<div class='alert alert-info'>".domarkup($post['highlighttext'])."</div>" : "";
			$set['highlightline'] = "<div class='td-highlight tdbgh'></div>";
		} else {
			$set['highlighttext'] = "";
			$set['highlightctrl'] = "";
			$set['highlightline'] = "";
		}
		
		
		if ($forum < 0) $forum = 0; // Restore actual forum value once we're done with PM Attachments
		
		return dofilters(postcode($post,$set), $forum, $multiforum);
	}
	
	function postlayout_fields() {
		global $loguser;
		
		switch ($loguser['viewsig']) {
			case 1:  return ',p.headid,p.signid,p.cssid,p.sidebarid,p.headtext,p.signtext,p.csstext,p.sidebartext,p.sidebartype';
			case 2:  return ',0 headid,0 signid,0 cssid,0 sidebarid,u.postheader headtext,u.signature signtext,u.css csstext,u.sidebar sidebartext,u.sidebartype';
			default: return '';
		}
	}

	function preplayouts($posts, $oldrev = null) {
		global $sql, $postl;
		
		// Just fetch everything now instead of hitting the DB for each new header/signature encountered
		$ids = [];
		foreach ($posts as $ps) {
			if ($ps['headid'])    $ids[] = $ps['headid'];
			if ($ps['signid'])    $ids[] = $ps['signid'];
			if ($ps['cssid'])     $ids[] = $ps['cssid'];
			if ($ps['sidebarid']) $ids[] = $ps['sidebarid'];
		}
		if ($oldrev) {
			if ($oldrev['headid'])    $ids[] = $oldrev['headid'];
			if ($oldrev['signid'])    $ids[] = $oldrev['signid'];
			if ($oldrev['cssid'])     $ids[] = $oldrev['cssid'];
			if ($oldrev['sidebarid']) $ids[] = $oldrev['sidebarid'];
		}

		if (!count($ids)) return;
		$postl = $sql->getresultsbykey("SELECT id, text FROM postlayouts WHERE id IN (".implode(",", array_unique($ids, SORT_NUMERIC)).")");
	}

	function setlayout($post) {
		global $loguser, $postl, $blockedlayouts;
		static $keys;
		
		$post['blockedlayout'] = isset($blockedlayouts[$post['uid']]);
		if (!$loguser['viewsig'] || $post['deleted'] || $post['blockedlayout'] || isset($post['nolayout'])) { // Disabled
			$post['headtext'] = $post['signtext'] = $post['csstext'] = $post['sidebartext'] = "";
			$post['headid']   = $post['signid']   = $post['cssid']   = $post['sidebarid']   = $post['sidebartype'] = 0;
			return $post;
		}
		
		if ($loguser['viewsig'] == 2) { // Autoupdate
			$post['headid']   = $post['signid']   = $post['cssid']   = $post['sidebarid'] = 0; // disable post layout assignment
		} else { // Not Autoupdate
			if (!isset($post['headid'])) {
				throw new Exception("The 'headid' field is missing, this shouldn't happen.");
			}
			if ($post['headid'])    $post['headtext']    = filter_string($postl[$post['headid']], "");
			if ($post['signid'])    $post['signtext']    = filter_string($postl[$post['signid']], "");
			if ($post['cssid'])     $post['csstext']     = filter_string($postl[$post['cssid']], "");
			if ($post['sidebarid']) $post['sidebartext'] = filter_string($postl[$post['sidebarid']], "");
			// sidebartype already in $post
		}
		
		// process tags 
		
		$gtopt = array(
			'mood' => $post['moodid'],
		);
		$tags = get_tags($post['tagval'], $gtopt);
		$post['headtext'] = replace_tags($post['headtext'],$tags);
		$post['signtext'] = replace_tags($post['signtext'],$tags);
		$post['csstext']  = replace_tags($post['csstext'], $tags);
		
		// Post header and signature filters are always handled in MODE_POST.
		// This guarantees that signature containing pid quotes will contain a link to the thread.
		$post['headtext'] = "<span id='body{$post['id']}'>".domarkup($post['headtext'], null, false, MODE_POST);
		$post['signtext'] = domarkup($post['signtext'], null, false, MODE_POST)."</span>";	
		
		// Only insert the unique CSS once, to save up on processing/sent bytes.
		$csskey = $post['uid'].getcsskey($post);
		if (!isset($keys[$csskey])) {
			$keys[$csskey] = true;
			if ($post['csstext']) {
				$post['headtext'] = "<style type='text/css' id='css{$post['id']}'>".domarkup($post['csstext'], null, true)."</style>{$post['headtext']}";
			}
		}
		
		// Legacy CSS - Prevent topbar CSS overlap for non-autoupdating layouts
		if ($post['headtext'])    $post['headtext']     = preg_replace("'\.(top|side|main|cont)bar{$post['uid']}'si", ".$1bar{$csskey}", $post['headtext']);
		// The sidebar shouldn't include CSS stylesheets
		//if ($post['sidebartext']) $post['sidebartext']  = preg_replace("'\.(top|side|main|cont)bar{$post['uid']}'si", ".$1bar{$csskey}", $post['sidebartext']);
		return $post;
	}
	
	// Determines the extra text appended to .mainbar/.topbar/... to prevent overlaps.
	function getcsskey($post) {
		$csskey = "";
		if ($post['headid'])    $csskey .= "_h{$post['headid']}";
		if ($post['cssid'])     $csskey .= "_c{$post['cssid']}";
		if (!$csskey)           $csskey .= "_p{$post['id']}"; // Failsafe: use current post id
		return $csskey;
	}
	
	function getpostlayoutid($text, $add = true) {
		global $sql;
		
		// Everything breaks on transactions if $text is blank
		if (!$text) return 0;
		
		$id = $sql->resultp("SELECT id FROM postlayouts WHERE text = ? LIMIT 1", [$text]);
		// Is this a new layout?
		if (!$id && $add) {
			$sql->queryp("INSERT INTO postlayouts (text) VALUES (?)", [$text]);
			$id = $sql->insert_id();
		}
		return $id;
	}
	
	// Gets the post layout fields for a specific (pm) post
	function getpostlayoutforedit($post) {
		global $sql;
		
		$to_search = [];
		if ($post['headid'])    $to_search[] = $post['headid'];
		if ($post['signid'])    $to_search[] = $post['signid'];
		if ($post['cssid'])     $to_search[] = $post['cssid'];
		if ($post['sidebarid']) $to_search[] = $post['sidebarid'];
		
		$data    = $to_search ? $sql->getresultsbykey("SELECT id, text FROM postlayouts WHERE id IN (".implode(",", $to_search).")") : [];
		
		$head    = filter_string($data[$post['headid']], $post['headtext']);
		$sign    = filter_string($data[$post['signid']], $post['signtext']);
		$css     = filter_string($data[$post['cssid']], $post['csstext']);
		$sidebar = filter_string($data[$post['sidebarid']], $post['sidebartext']);
		
		sbr(1, $head);
		sbr(1, $sign);
		
		return [$head, $sign, $css, $sidebar];
	}
	
	const PREVIEW_NEW     = 0;
	const PREVIEW_EDITED  = 1;
	const PREVIEW_PROFILE = 2;
	const PREVIEW_PM      = 3;
	const PREVIEW_GENERIC = 4;
	function preview_post($user, $data, $flags = PREVIEW_NEW, $title = "Post preview") {
		global $sql, $controls, $loguser, $config, $isadmin, $userfields_array;
		
		// $user should be an array with user data
		if (is_int($user)) {
			if ($user == $loguser['id']) {
				$user = $loguser;
			} else {
				$user = load_user($user, true);
			}
		}
		
		$currenttime    = time();
		$numdays		= ($currenttime - $user['regdate']) / 86400;
		
		if ($flags == PREVIEW_EDITED) {
			$posts     = $user['posts'];
		} else {
			if ($flags == PREVIEW_NEW) {
				$posts    = $user['posts'] + 1;
			} else {
				$posts	  = $user['posts'];
			}
			$data['date']        = $currenttime;
			$data['num']         = $posts;
			// A new post lacks most of the postlayout data
			$data['head']        = $user['postheader'];
			$data['sign']        = $user['signature'];
			$data['css']         = $user['css'];
			$data['sidebar']     = $user['sidebar'];
			$data['sidebartype'] = $user['sidebartype'];
		}
		
		loadtlayout();

		$ppost           = $user;
		$ppost['posts']  = $posts;
		$ppost['id']     = 0;
		$ppost['uid']    = $user['id'];
		$ppost['num']    = filter_int($data['num']); // Not sent on PMs
		$ppost['date']   = $data['date'];
		$ppost['moodid'] = $data['moodid'];
		$ppost['noob']   = filter_int($data['noob']);
		//--
		if ($ppost['highlighted'] = filter_int($data['highlighted'])) {
			$ppost['highlighttext'] = $data['highlighttext'];
			//$ppost['highlightdate'] = $data['highlightdate'];
		}
		if ($ppost['warned'] = filter_int($data['warned'])) {
			$ppost['warntext'] = $data['warntext'];
			//$ppost['warndate'] = $data['warndate'];
		}
		//--
		
		$gtopt = array(
			'posts' => $posts,
			'mood'  => $data['moodid'],
		);
		$tags = get_tags($user, $gtopt);
		$ppost['text']   = replace_tags($data['message'], $tags);
		$ppost['tagval'] = json_encode($tags);

		// tags for the post layout handled separately
		if ($data['nolayout']) {
			$ppost['headtext']    = "";
			$ppost['signtext']    = "";
			$ppost['csstext']     = "";
			$ppost['sidebartext'] = "";
		} else {
			$ppost['headtext']    = $data['head'];	
			$ppost['signtext']    = $data['sign'];
			$ppost['csstext']     = $data['css'];
			$ppost['sidebartext'] = $data['sidebar'];
		}
		$ppost['headid'] = $ppost['signid'] = $ppost['cssid'] = $ppost['sidebarid'] = 0;

		$ppost['deleted']       = 0;
		$ppost['revision']      = 0;
		$ppost['nosmilies']		= $data['nosmilies'];
		$ppost['nohtml']		= $data['nohtml'];
		$ppost['act'] 			= $sql->resultq("SELECT COUNT(*) num FROM posts WHERE date > ".(time() - 86400)." AND user = {$user['id']}");
		$ppost['new']           = filter_bool($data['new']);
		$ppost['piclink']       = get_weblink($user['id'], $data['moodid']);
		
		// Attachment preview stuff / edit marker
		if ($flags == PREVIEW_EDITED) {
			// If we're viewing the post preview when *EDITING* a new post/pm
			// the attachment list should contain the temp attachments and the already uploaded attachments
			// (and hide those marked as deleted)
			if ($config['allow-attachments'] && isset($data['attach_key'])) {
				$real = get_saved_attachments($data['id'], isset($data['attach_pm']), $data['attach_sel']);
				$temp = get_temp_attachments($data['attach_key'], $user['id']);
				$ppost['attach'] = array_merge($real, $temp);
			}
			// Edit marker
			$editedby           = isset($data['editedby']) ? $data['editedby'] : $loguser;
			foreach ($userfields_array as $x)
				$ppost["ue_{$x}"] = $editedby[$x];
			$ppost['editdate'] 	= $currenttime;
		} else if ($flags == PREVIEW_PROFILE) {
			$data['ip'] = $user['lastip'];
		} else if ($flags == PREVIEW_GENERIC) {
			// Generic post view mode
			if ($config['allow-attachments']) {
				$ppost['attach'] = get_saved_attachments($data['id'], isset($data['attach_pm']));
			}
		} else {
			// If we're viewing the post preview when creating a new post/pm/etc
			// the attachment list should contain the temp attachments
			if ($config['allow-attachments'] && isset($data['attach_key'])) {
				$ppost['attach'] = get_temp_attachments($data['attach_key'], $user['id']);
			}
			$data['ip'] = $_SERVER['REMOTE_ADDR'];
		}
		
		$controls = [];
		if ($isadmin) {
			$ip = htmlspecialchars($data['ip']);
			$controls[] = "IP: <a href=\"admin-ipsearch.php?ip={$ip}\">{$ip}</a>";
		}
	
	return ($title ? "
	<table class='table'>
		<tr>
			<td class='tdbgh center'>
				{$title}
			</td>
		</tr>
	</table>" : "")."
	".threadpost($ppost, 1, $flags == PREVIEW_PM ? MODE_PM : MODE_POST, $data['forum'])."
	<br>";
	}
	
function thread_history($thread, $num, $forum = 0) {
	global $sql, $userfields;
	
	if (!$forum) {
		$table = "pm_posts";
		$link  = "showprivate";
		$nf    = "0 ";
	} else {
		$table = "posts";
		$link  = "thread";
		$nf    = "p.";
	}
	
	$posts = $sql->query("
		SELECT {$userfields}, u.posts, p.user, p.text, p.nosmilies, p.nohtml, p.deleted, {$nf}num
		FROM {$table} p
		LEFT JOIN users u ON p.user = u.id
		WHERE p.thread = $thread
		ORDER BY p.id DESC
		LIMIT ".($num + 1)."
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
				$message  = $post['deleted'] ? '(Post deleted)' : dofilters(domarkup($post['text'], $post, false, $forum ? MODE_POST : MODE_PM), $forum);
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
	return $sql->getresultsbykey("SELECT user, COUNT(*) num FROM posts WHERE date > ".(time() - 86400)." GROUP BY user");
}

function read_syndromes($all = false) {
	static $syndromes;
	// We only need to read the file once
	// <post req>,<color>,<name>,[<disabled>]
	if ($syndromes === NULL) {
		// Load the syndromes on the first call (will place a dummy blank entry at the last position)
		$syndromes = array();
		$h = fopen('syndromes.dat', 'r');
		for ($i = 0; $row = fgetcsv($h, 100, ','); ++$i) 
			if ($all || !isset($row[3])) $syndromes[] = $row;
	}
	return $syndromes;
}

function syndrome($num, $double=false, $bar=true) {
	$bar	= false;
	$syn	= "";
	$syndromes = read_syndromes();
	
	// Find the first postcount < post req
	for ($i = 0; isset($syndromes[$i]) && $num >= $syndromes[$i][0]; ++$i);
	--$i;
	
	// If it exists, print out the syndrome text
	if (isset($syndromes[$i])) {
		if (isset($syndromes[$i+1])) {
			$next = $syndromes[$i+1][0];
			$last = $syndromes[$i+1][0] - $syndromes[$i][0];
		} else {
			$next = 0; // Reached the last entry?
		}
		
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
		$syn = "<br>
		<span style='font-style: italic; color: {$syndromes[$i][1]}'>Affected by ".htmlspecialchars($syndromes[$i][2])."</span>
		$bar
		<br>";
	}

	return $syn;
}

//--
// postcode extension specific functions

const TOPBAR_RIGHT = 0;
const TOPBAR_LEFT = 1;
const OPTION_ROW_TOP = 2;
const OPTION_ROW_BOTTOM = 3;

function add_option_row($html, $dir = OPTION_ROW_BOTTOM, $rowspan = 0) {
	if ($dir === OPTION_ROW_BOTTOM) {
		global $tloptrows;
		$tloptrows[] = [$html, $rowspan];
	} else {
		global $tloptrowst;
		$tloptrowst[] = [$html, $rowspan];
	}
}

function add_topbar_entry($html, $dir) {
	if ($dir === TOPBAR_RIGHT) {
		global $tltopr;
		$tltopr .= $html;
	} else {
		global $tltopl;
		$tltopl .= $html;
	}
}

function get_tlayout_opts($key, &$set, $post, $data) {
	// initialize/reset *all* of the threadpost extension variables
	global $tloptrows, $tloptrowst, $tltopr, $tltopl;
	$tloptrows = $tloptrowst = [];
	$tltopr = $tltopl = "";
	
	hook_use("tlayout-{$key}", $set, $post, $data);
	
	$out = new tlayout_ext_option($data);
	$out->top_left  = $tltopl;
	$out->top_right = $tltopr;
	
	// option rows
	foreach ($tloptrows as $opt) {
		$out->option_rows_bottom .= $opt[0];
		$out->rowspan            += $opt[1];
	}
	// option rows (top variant)
	foreach ($tloptrowst as $opt) {
		$out->option_rows_top    .= $opt[0];
		$out->rowspan            += $opt[1];
	}
	
	return $out;
}

class tlayout_ext_option {
	public $option_rows_bottom = "";
	public $option_rows_top = "";
	public $rowspan = 0;
	
	public $top_left = "";
	public $top_right = "";
	
	function __construct($inpt) { // tlayout_ext_input
		$this->rowspan = $inpt->rowspan;
	}
}

class tlayout_ext_input {
	public $csskey;
	public $sidebar_one_cell;
	public $rowspan;
}