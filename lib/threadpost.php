<?php
	
	function threadpost($post, $bg, $controls, $forum = 0, $pthread = '') {
		
		global $loguser, $sep, $tlayout, $blockedlayouts;
		
		// Fetch an array containing all blocked layouts now
		if (!isset($blockedlayouts)) {
			global $sql;
			$blockedlayouts = $sql->fetchq("SELECT blocked, 1 FROM blockedlayouts WHERE user = {$loguser['id']}", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
			if (!$blockedlayouts)
				$blockedlayouts = 0;
		}
		
		$post = setlayout($post);
		
		$set['bg']    = $bg;

		$userlink = getuserlink($post, $post['uid'], "url".$post['uid']);
		$set['userlink'] = "<a name={$post['uid']}></a>{$userlink}";
		$set['date']     = printdate($post['date']);
			
		if (!isset($post['deleted'])) {
			trigger_error("threadpost - \$post['deleted'] key not sent", E_USER_WARNING);
			$post['deleted'] = 0;
		}
		
		if ($post['deleted']) {
			// Peeking a post temporarily sets $post['deleted'] to false
			$set['userrank'] = "";
			$set['location'] = "";
			$post['signtext'] = "";
			$post['headtext'] = "";
			$post['text'] = "(Post deleted)";
		} else {
			
			// $post['group'] -> get_usergroup($post)
			$set['userrank'] = getrank($post['useranks'], $post['title'], $post['posts'], get_usergroup($post), $post['ban_expire']); 

			$set['location'] = $post['location'] ? "<br>From: {$post['location']}" : "";

			$set['userpic'] = "<img class='avatar' src='".avatarpath($post['uid'], $post['moodid'])."'>";

			if($post['signtext']) {
				$post['signtext'] = $sep[$loguser['signsep']].$post['signtext'];
			}
			
			// thread link for for threads by user mode
			if($pthread) { 
				$set['threadlink'] = "<a href=thread.php?id={$pthread['id']}>{$pthread['title']}</a>";
			}

			$post['text'] = format_post($post['text'], $post['options']);
			
			if (filter_int($post['editdate'])) {
				$post['edited'] = " (last edited by {$post['edited']} at ".printdate($post['editdate']);
				
				// If multiple revisions are supported, display revision info / selector
				if (isset($post['rev'])) {
					if (!isset($post['sel_rev'])) { // No post revision explicitly specified (from _GET['rev'])
						$post['sel_rev'] = filter_int($post['rev']); // Imply max revision
					} else if ($post['sel_rev'] != $post['rev']) { // peeking at old revision
						$post['edited'] .= " | Revision {$post['sel_rev']} by ".getuserlink(NULL, $post['old_user'])." at ".printdate($post['old_date']).")";
					}
					/*
						Post revision jump
					*/
					global $ismod; // ok right
					if ($ismod) {
						$revjump = "Revision: ";
						for ($i = 0; $i <= $post['rev']; ++$i) {
							$a 		  = ($post['sel_rev'] == $i) ? "z" : "a"; 
							$revjump .= "<{$a} href='?pid={$post['id']}&pin={$post['id']}&rev={$i}#{$post['id']}'>".($i+1)."</{$a}> ";
						}
					}
				} else {
					$revjump = "";
				}
				
				$post['edited'] .= ") {$revjump}";
			}
		}
		
		
		return dofilters(postcode($post, $set, $controls), $forum);
	}

	function preplayouts($posts) {
		global $sql, $postl;

		$ids = array();

		// Just fetch everything now instead of hitting the DB for each new header/signature encountered
		while ($ps = $sql->fetch($posts)) {
			if ($ps['headid']) $ids[] = $ps['headid'];
			if ($ps['signid']) $ids[] = $ps['signid'];
		}

		if (count($ids)) {
			$postl = $sql->fetchq("SELECT id, text FROM postlayouts WHERE id IN (".implode(",", array_unique($ids, SORT_NUMERIC)).")", PDO::FETCH_KEY_PAIR, mysql::FETCH_ALL);
		}
	}

	function setlayout($post) {
		global $sql,$loguser,$postl,$blockedlayouts;
		
		
		if($loguser['viewsig']!=1) { // Autoupdate
			$post['headid']=$post['signid']=0;
		}
		
		if(!$loguser['viewsig'] || isset($blockedlayouts[$post['uid']])){ // Disabled
			$post['headtext']=$post['signtext']='';
			return $post;
		}

		if($loguser['viewsig']!=2){ // Not Autoupdate
			if($headid=filter_int($post['headid'])) {
				// just in case
				if(!isset($postl[$headid])) $postl[$headid]=$sql->resultq("SELECT text FROM postlayouts WHERE id=$headid");
				$post['headtext']=$postl[$headid];
			}
			if($signid=filter_int($post['signid'])) {
				// just in case
				if(!isset($postl[$signid])) $postl[$signid]=$sql->resultq("SELECT text FROM postlayouts WHERE id=$signid");
				$post['signtext']=$postl[$signid];
			}
		}

		$post['headtext']=settags($post['headtext'],filter_string($post['tagval']));
		$post['signtext']=settags($post['signtext'],filter_string($post['tagval']));

		if($loguser['viewsig']==2){ // Autoupdate
			$post['headtext']=prepare_tags($post['headtext'],$post['num'],($post['date']-$post['regdate'])/86400,$post['uid']);
			$post['signtext']=prepare_tags($post['signtext'],$post['num'],($post['date']-$post['regdate'])/86400,$post['uid']);
		}
		
		// Prevent topbar CSS overlap for non-autoupdating layouts
		if ($post['headid'])
			$post['headtext'] = preg_replace("'\.(top|side|main|cont)bar{$post['uid']}'si", ".$1bar{$post['uid']}_{$post['headid']}", $post['headtext']);
		else
			$post['headtext'] = preg_replace("'\.(top|side|main|cont)bar{$post['uid']}'si", ".$1bar{$post['uid']}_x{$post['id']}", $post['headtext']);
		
		// fuck plain text headers, CSS is supposed to be here
		$post['headtext']=format_post($post['headtext'], '0|0', true);
		$post['signtext']=format_post($post['signtext']);
		//	$post['text']=format_post($post['text'], $post['options']);
		return $post;
	}

function syndrome($cur_posts, $size_multiplier = 1, $disable_bar = false){
	global $config, $syndromes;
	if (!isset($synlist)) {
		static $synlist, $syncount;
		$synlist  = array_keys($syndromes);
		$syncount = count($syndromes);
	}
	
	for ($i = 0; $i < $syncount; ++$i) {
		if ($i != $syncount - 1 && $cur_posts >= $synlist[$i + 1]) { // just in case we actually reach the last syndrome.
			continue; // We (somehow) haven't reached the end yet. Simply go on.
		} else if ($cur_posts >= $synlist[$i]) {
			$syn = array(
				'font-color'     => $syndromes[$synlist[$i]][0],
				'title'          => $syndromes[$synlist[$i]][1],
				'bar-image'      => $syndromes[$synlist[$i]][2],
				'posts-required' => $synlist[$i],
				'length'         => ($i == $syncount-1 ? 0 : $synlist[$i+1] - $synlist[$i]) // On the last syndrome, the bar should be maxed out rather than hidden
			);
			break;
		} else {
			return ""; // No syndromes awarded. Get out of here immediately.
		}
	}

	if (!$disable_bar && $config['draw-syndrome-bar']) {
		
		$bar_width     = 150 * $size_multiplier;
		$bar_height    =   8 * $size_multiplier;
		
		if ($syn['length']) {
			$progress   = round(($cur_posts - $syn['posts-required']) / $syn['length'] * 100); // Done / Total * 100
			$done_posts = $cur_posts;
			$rest_posts = $syn['length'] - ($cur_posts - $syn['posts-required']);
		} else {
			$progress   = 100; // Bar filled
			$done_posts = "NAN"; // Not needed
			$rest_posts = "i"; // infinity			
		}
		
		$bar_images = array(
			'left'  => 'images/num1/barleft.png',
			'on' => 'images/num1/bar-on'.$syn['bar-image'],
			'off'    => 'images/num1/bar-off.png',
			'right'   => 'images/num1/barright.png'
		);

		$bar	= "<br>
			<span class='nobr'>
				". generatenumbergfx($done_posts, 3, $size_multiplier) ."
				". drawprogressbar($bar_width, $bar_height, $progress, $bar_images)."
				". generatenumbergfx($rest_posts, 3, $size_multiplier) ."
			</span>";
	} else {
		$bar = "";
	}
	
	return "<br><i><span style='color: #{$syn['font-color']}'>Affected by {$syn['title']}</span></i>$bar<br>";
}
