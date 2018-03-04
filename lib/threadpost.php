<?php
	
	function threadpost($post,$bg,$forum = 0,$pthread='') {
		
		global $config, $loguser, $sep, $tlayout, $blockedlayouts, $isadmin;
		
		// Fetch an array containing all blocked layouts now
		if (!isset($blockedlayouts)) {
			global $sql;
			$blockedlayouts = $sql->getresultsbykey("SELECT blocked, 1 FROM blockedlayouts WHERE user = {$loguser['id']}");
			if (!$blockedlayouts)
				$blockedlayouts = 0;
		}
		
		$post = setlayout($post);
		
		$set['bg']    = $bg; //${"tablebg$bg"};

		$userlink = getuserlink($post, $post['uid'], "url".$post['uid']);

		$set['userrank'] = getrank(
			filter_int($post['useranks']), 
			filter_string($post['title']),
			$post['posts'],
			$post['powerlevel'],
			$post['ban_expire']
		);
		
		$set['userlink'] = "<a name={$post['uid']}></a>{$userlink}";
		$set['date']     = printdate($post['date']);

		$set['location'] = filter_string($post['location']) ? "<br>From: {$post['location']}" : "";

		if ($config['allow-avatar-storage']) {
			if ($post['piclink']) {
				$set['picture'] = escape_attribute($post['piclink']);
				$set['userpic'] = "<img class='avatar' src=\"{$set['picture']}\">"; 
			} else if (file_exists(avatarpath($post['uid'], $post['moodid']))) {
				$set['picture'] = avatarpath($post['uid'], $post['moodid']);
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

		if($post['signtext']) {
			$post['signtext'] = $sep[$loguser['signsep']].$post['signtext'];
		}

		if($pthread) { 
			$set['threadlink'] = "<a href=thread.php?id={$pthread['id']}>{$pthread['title']}</a>";
		}

		$post['text'] = doreplace2($post['text'], $post['options']);
		
		if (filter_int($post['editdate'])) {
			$post['edited'] = " (last edited by {$post['edited']} at ".printdate($post['editdate']).")";
		} else {
			$post['edited'] = "";
		}
		
		if (filter_array($post['attach'])) {
			$set['attach'] = attachfield($post['attach'], ($isadmin || $post['uid'] == $loguser['id']));
		} else {
			$set['attach'] = "";
		}
		
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
		
		
		if($loguser['viewsig']!=1) { // Autoupdate
			$post['headid']=$post['signid']=0;
		}
		
		//$blocked = $sql->resultq("SELECT 1 FROM blockedlayouts WHERE user = {$loguser['id']} AND blocked = {$post['uid']}", 0, 0, true); // Enable caching

		if(!$loguser['viewsig'] || isset($blockedlayouts[$post['uid']])){ // Disabled
			$post['headtext']=$post['signtext']='';
			return $post;
		}

		if($loguser['viewsig']!=2){ // Not Autoupdate
			if($headid=filter_int($post['headid'])) {
				// just in case
				if($postl[$headid] === NULL) $postl[$headid]=$sql->resultq("SELECT text FROM postlayouts WHERE id=$headid");
				$post['headtext']=$postl[$headid];
			}
			if($signid=filter_int($post['signid'])) {
				// just in case
				if($postl[$signid] === NULL) $postl[$signid]=$sql->resultq("SELECT text FROM postlayouts WHERE id=$signid");
				$post['signtext']=$postl[$signid];
			}
		}

		$post['headtext']=settags($post['headtext'],filter_string($post['tagval']));
		$post['signtext']=settags($post['signtext'],filter_string($post['tagval']));

		if($loguser['viewsig']==2){ // Autoupdate
			$post['headtext']=doreplace($post['headtext'],$post['num'],($post['date']-$post['regdate'])/86400,$post['uid']);
			$post['signtext']=doreplace($post['signtext'],$post['num'],($post['date']-$post['regdate'])/86400,$post['uid']);
		}
		
		// Prevent topbar CSS overlap for non-autoupdating layouts
		if ($post['headid'])
			$post['headtext'] = preg_replace("'\.(top|side|main|cont)bar{$post['uid']}'si", ".$1bar{$post['uid']}_{$post['headid']}", $post['headtext']);
		else
			$post['headtext'] = preg_replace("'\.(top|side|main|cont)bar{$post['uid']}'si", ".$1bar{$post['uid']}_x{$post['id']}", $post['headtext']);
		
		$post['headtext']=doreplace2($post['headtext']);
		$post['signtext']=doreplace2($post['signtext']);
		//	$post['text']=doreplace2($post['text'], $post['options']);
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
		$ppost['num']    = $data['num'];
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

		//$ppost['text']			= $message;
		$ppost['options']		= "{$data['nosmilies']}|{$data['nohtml']}";
		$ppost['act'] 			= $sql->resultq("SELECT COUNT(*) num FROM posts WHERE date > ".(ctime() - 86400)." AND user = {$user['id']}");
		
		// Save ourselves a query if we're (somehow) not needing the picture link
		if ($config['allow-avatar-storage']) {
			$ppost['piclink']   = $sql->resultq("SELECT weblink FROM users_avatars WHERE user = {$user['id']} AND file = {$data['moodid']}");
		} else {
			$ppost['piclink']   = "";
		}
		
		// Attachment stuff / edit marker
		if ($flags == PREVIEW_EDITED) {
			//$ppost['id'] = $data['id'];
			if ($config['allow-attachments'] && $data['attach_key'] !== NULL) {
				$attach = get_saved_attachments($data['id']);
				$ppost['attach'] = array_merge(filter_attachments($attach, $data['attach_sel']), get_temp_attachments($data['attach_key'], $user['id']));
			}
			// Edit marker
			$ppost['edited']	= getuserlink($loguser);
			$ppost['editdate'] 	= $currenttime;
		} else if ($flags == PREVIEW_PROFILE) {
			$data['ip'] = $user['lastip'];
		} else {
			//$ppost['id'] = 0; // Not used; should be setting up $quote
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
			$barw1	= min(round(($num - $last) / $next * 150), 150); // Done / Total * Max bar size
			$barw2	= 150 - $barw1;
			$barimg	= "red.png";

			if ($double == true) {
				$hi = 16;
				$barw1 *= 2;
				$barw2 *= 2;
			} else {
				$hi	= 8;
			}

			if ($next	>= 100) $barimg	= "special.gif";
			$bar	= "<br>
				<nobr>
					". generatenumbergfx($num, 3, $double) ."
					<img src='images/num1/barleft.png' height=$hi>
					<img src='images/num1/bar-on$barimg' width=$barw1 height=$hi>
					<img src='images/num1/bar-off.png' width=$barw2 height=$hi>
					<img src='images/num1/barright.png' height=$hi>
					". generatenumbergfx($next - ($num - $last), 3, $double) ."
				</nobr>";
		}
		$syn="<br><i><span style='color: #$syn</span></i>$bar<br>";
	}

	return $syn;
}
