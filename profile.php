<?php
	require 'lib/function.php';
	
	$id		= filter_int($_GET['id']);
	$action = filter_string($_GET['action']); // I am a bad person
	$user = $sql->fetchq("SELECT * FROM users WHERE id = $id");
	$windowtitle = "{$config['board-name']} -- Profile for {$user['name']}";

//	if ($_GET['id'] == 1 && !$x_hacks['host']) {
//		print "$header<br><center><img src='http://earthboundcentral.com/wp-content/uploads/2009/01/m3deletede.png'></center><br>$footer";
//		printtimedif($startingtime);
//		die();
//	}

	if (!$user) {
		errorpage("The specified user doesn't exist.");
	}

	$isblocked = $sql->resultq("SELECT 1 FROM blockedlayouts WHERE user = {$loguser['id']} AND blocked = $id");
	if ($loguser['id'] && $action) {
		check_token($_GET['auth'], 32);
		switch ($action) {
			case 'blocklayout':
				if ($isblocked) {
					$sql->query("DELETE FROM blockedlayouts WHERE user = {$loguser['id']} AND blocked = $id");
					$txt = "Layout unblocked!";
				} else {
					$sql->query("INSERT INTO blockedlayouts (user, blocked) VALUES ({$loguser['id']}, $id)");
					$txt = "Layout blocked!";
				}
				break;
			default: return header("Location: profile.php?id=$id");
		}
		errorpage($txt ,"profile.php?id=$id","{$user['name']}'s profile",0);
	}
	
	pageheader($windowtitle);
	
/*  User ratings (disabled)
	$users1=$sql->query("SELECT id,posts,regdate FROM users");
	while($u=$sql->fetch($users1)){
		$u['level']=calclvl(calcexp($u['posts'],(ctime()-$u['regdate'])/86400));
		if ($u['posts']<0 or $u['regdate']>ctime()) $u['level']=1;
		$users[$u['id']]=$u;
	}

	$ratescore=0;
	$ratetotal=0;
	$ratings=$sql->query("SELECT userfrom,rating FROM userratings WHERE userrated=$id");
	while($rating=@$sql->fetch($ratings)){
		$ratescore+=$rating['rating']*$users[$rating['userfrom']]['level'];
		$ratetotal+=$users[$rating['userfrom']]['level'];
	}
	$ratetotal*=10;
	$numvotes=mysql_num_rows($ratings);
	if($ratetotal) {
		$ratingstatus=(floor($ratescore*1000/$ratetotal)/100)." ($ratescore/$ratetotal, $numvotes votes)";
	} else { 
		$ratingstatus="None";
	}
	if($loguserid and $logpwenc and $loguserid!=$id)
		$ratelink=" | <a href=rateuser.php?id=$id>Rate user</a>";
*/
	$ratelink = "";
	$ratingstatus = "";
	
	$maxposts 		= $sql->resultq("SELECT posts FROM users ORDER BY posts DESC LIMIT 1");
	$userrank 		= getrank($user['useranks'],$user['title'],$user['posts'],$user['powerlevel'],$user['ban_expire']);
	$threadsposted 	= $sql->resultq("SELECT COUNT(id) FROM threads WHERE user = $id");
	if (!$maxposts) $maxposts = 1;
	//$i = 0;
	
	
	// Forum last post
	$lastpostdate = "None";
	$lastpostlink = "";
	if($user['posts']) {
		$lastpostdate = printdate($user['lastposttime']);

		//$postsfound=$sql->resultq("SELECT count(id) AS cnt FROM posts WHERE user=$id",0,"cnt");
		$post = $sql->fetchq("SELECT id, thread FROM posts WHERE user = $id AND date = {$user['lastposttime']}");

		if ($post && $thread = $sql->fetchq("SELECT title,forum FROM threads WHERE id = {$post['thread']}")) {
			$forum = $sql->fetchq("SELECT id,title FROM forums WHERE id = {$thread['forum']}");
			$forumperm = get_forum_perm($forum['id'], $loguser['id'], $loguser['group']);
			if (!is_array($forumperm) || !has_forum_perm('read', $forumperm))
				$lastpostlink = ", in a restricted forum";
			else
				$lastpostlink = ", in <a href=thread.php?pid={$post['id']}#{$post['id']}>".htmlspecialchars($thread['title'])."</a> (<a href='forum.php?id={$forum['id']}'>".htmlspecialchars($forum['title'])."</a>)";
		}
	}

	// Action links
	$sneek = "";
	if ($loguser['id']) {
		$token = generate_token(32);
		$sendpmsg = " | <a href='sendprivate.php?userid=$id'>Send private message</a>".
					" | <a href='profile.php?id=$id&action=blocklayout&auth=$token'>".($isblocked ? "Unb" : "B")."lock layout</a>";
		if (has_perm('admin-actions')) {
			if($user['lastip'])
				$lastip = " <br>with IP: <a href='ipsearch.php?ip={$user['lastip']}' style='font-style:italic;'>{$user['lastip']}</a>";
		}
		$sneek = "<tr><td class='tdbg1 fonts center' colspan=2>"
				.(has_perm('view-others-pms') ? "<a href='private.php?id={$id}' style='font-style:italic;'>View private messages</a> |" : "")
				.(has_perm('admin-actions') ? " <a href='forum.php?fav=1&user={$id}' style='font-style:italic;'>View favorites</a> |"
				//." <a href='rateuser.php?action=viewvotes&id={$id}' style='font-style:italic;'>View votes</a> |"
				." <a href='editprofile.php?id={$id}' style='font-style:italic;'>Edit user</a> |"
				." <a href='admin-editperms.php?mode=1&id={$id}' style='font-style:italic;'>Edit permissions</a>" : "");
	}

	$aim = str_replace(" ", "+", $user['aim']);
	$schname = $sql->resultq("SELECT name FROM schemes WHERE id=$user[scheme]");
	$numdays = (ctime()-$user['regdate'])/86400;

	$user['signature']  = doreplace($user['signature'],$user['posts'],$numdays, $id);
	$user['postheader'] = doreplace($user['postheader'],$user['posts'],$numdays, $id);
	
	$picture = $moodavatar = "";
	if ($user['picture']) $picture = "<img src=\"".htmlspecialchars($user['picture'])."\">";
	if ($user['moodurl']) $moodavatar = " | <a href='avatar.php?id=$id' class=\"popout\" target=\"_blank\">Preview mood avatar</a>";
	
	
	if (!$user['icq']) {
		$user['icq'] 	= "";
		$icqicon 		= "";
	} else {
		$icqicon = "<a href=\"http://wwp.icq.com/{$user['icq']}#pager\"><img src=\"http://wwp.icq.com/scripts/online.dll?icq={$user['icq']}&img=5\" border=0></a>";
	}
	
	$userlink = getuserlink($user, 0, '', true);	// With minipic
	
	$tzoffset = $user['timezone'];
	$tzoffrel = $tzoffset - $loguser['timezone'];
	$tzdate   = date($loguser['dateformat'],ctime()+$tzoffset*3600);
	if($user['birthday']){
		$birthday = date("l, F j, Y", $user['birthday']);
		$age = "(".floor((ctime()-$user['birthday'])/86400/365.2425)." years old)";
	} else {
		$birthday = $age = "";
	}

	// RPG fun shit
	$exp 		= calcexp($user['posts'],(ctime()-$user['regdate'])/86400);
	$lvl 		= calclvl($exp);
	$expleft 	= calcexpleft($exp);
	$expstatus 	= "Level: $lvl<br>EXP: $exp (for next level: $expleft)";
	
	if($user['posts'] > 0)
		$expstatus .= "<br>Gain: ".calcexpgainpost($user['posts'],(ctime()-$user['regdate'])/86400)." EXP per post, ".calcexpgaintime($user['posts'],(ctime()-$user['regdate'])/86400)." seconds to gain 1 EXP when idle";
	$postavg 	= sprintf("%01.2f",$user['posts']/(ctime()-$user['regdate'])*86400);
	$totalwidth = 116;
	$barwidth 	= floor(($user['posts']/$maxposts) * $totalwidth);
	$baron = $baroff = "";
	if ($barwidth < 0) $barwidth = 0;
	if ($barwidth) $baron = "<img src='images/{$numdir}bar-on.gif' width=$barwidth height=8>";
	if ($barwidth < $totalwidth) $baroff = "<img src=images/{$numdir}bar-off.gif width=".($totalwidth-$barwidth)." height=8>";
	$bar = "<img src='images/{$numdir}barleft.gif'>$baron$baroff<img src='images/{$numdir}barright.gif'><br>";
	
	
	$topposts = 5000;
	if($user['posts']) $projdate = ctime()+(ctime()-$user['regdate'])*($topposts-$user['posts'])/($user['posts']);
	if(!$user['posts'] || $user['posts'] >= $topposts || $projdate > 2000000000 || $projdate < ctime()) $projdate="";
	else $projdate = " -- Projected date for $topposts posts: ".printdate($projdate);

	
	$homepagename = htmlspecialchars($user['homepageurl']);
	if($user['homepagename']) $homepagename = htmlspecialchars($user['homepagename'])."</a> - ".htmlspecialchars($user['homepageurl']);
	
	loadtlayout();
	$user['headtext']=$user['postheader'];
	$user['signtext']=$user['signature'];
	$user['text'] = "Sample text. [quote=fhqwhgads]A sample quote, with a <a href=about:blank>link</a>, for testing your layout.[/quote]This is how your post will appear.";
	$user['uid']	= $id;
	$user['date']	= ctime();
	$user['moodid'] = 0;
	$user['options']= "0|0";
	$user['num']	= 0;
	$user['act']	= $sql->resultq("SELECT COUNT(*) FROM posts WHERE date > ".(ctime() - 86400)." AND user = $id");
	$user['noob']	= 0;
	
	// so that layouts show up regardless of setting (for obvious reasons)
	$loguser['viewsig'] = 1;
	$blockedlayouts[$id] = NULL;
	
	
	// shop/rpg such
	$eq = $sql->fetchq("SELECT * FROM users_rpg WHERE uid = $id");
	$shops 	= $sql->getresultsbykey("SELECT id, name FROM itemcateg");
	$q 		= "";
	foreach ($shops as $shopid => $shopname) $q .= " OR id = ".filter_int($eq['eq'.$shopid]);
	//$shops = $sql->query('SELECT * FROM itemcateg ORDER BY corder');
	//$itemids = array_unique(array($eq['eq1'], $eq['eq2'], $eq['eq3'], $eq['eq4'], $eq['eq5'], $eq['eq6'], $eq['eq7']));
	//$itemids = implode(',', $itemids);
	$items = $sql->getarraybykey("SELECT * FROM items WHERE id=0$q", 'id');
	$shoplist = "";
	foreach ($shops as $shopid => $shopname) {
		$shoplist.="
			<tr>
				<td class='tdbg1 fonts center'>$shopname</td>
				<td class='tdbg2 fonts center' width=100%>".filter_string($items[$eq['eq'.$shopid]]['name'])."&nbsp;</td>
			</tr>
		";
	}
		
	/* extra munging for whatever reason */
	$email = urlencode(htmlspecialchars($user['email']));
	$email = "<a href=\"mailto:$email\">$email</a>";

	switch ($user['privateemail']) {
		case 0: break; // Public
		case 1:
			if (!$loguser['id']) $email = "Email witheld from guests. Log in to see it.";
			break;
		case 2:
			if (!$isadmin || $loguser['id'] != $id) $email = "<i>Private</i>";
			break;
	}
	// AKA
	if ($user['aka'] && $user['aka'] != $user['name']) {
		$aka = "<tr><td class='tdbg1' width=150><b>Also known as</td><td class='tdbg2'>".htmlspecialchars($user['aka'])."</td></tr>";
	} else {
		$aka = '';
	}
	if ($user['group'] == GROUP_BANNED && $user['ban_expire']) {
		$bantime =  "<tr><td class='tdbg1' width=150><b>Banned until</td><td class='tdbg2'>".printdate($user['ban_expire'])." (".timeunits2($user['ban_expire']-ctime())." remaining)</td></tr>";
	} else {
		$bantime = "";
	}
	//($user['powerlevel']<0 ? ($user['ban_expire'] ? ." (".sprintf("%d",()." days remaining)" : "Never") : "")

	
	
?>
<div>Profile for <?=$userlink?></div>
<table cellpadding=0 cellspacing=0 border=0>
	<tr>
		<td width=100% valign=top>
		
		<table class='table'>
			<tr><td class='tdbgh center' colspan=2>General information</td></tr>
			<!-- <td class='tdbg1' width=150><b>Username</td>			<td class='tdbg2'><?=$user['name']?><tr> -->
			<?=$aka?>
			<?=$bantime?>
			<tr><td class='tdbg1' width=150><b>Total posts</td>			<td class='tdbg2'><?=$user['posts']?> (<?=$postavg?> per day) <?=$projdate?><br><?=$bar?></td></tr>
			<tr><td class='tdbg1' width=150><b>Total threads</td>		<td class='tdbg2'><?=$threadsposted?></td></tr>
			<tr><td class='tdbg1' width=150><b>EXP</td>					<td class='tdbg2'><?=$expstatus?></td></tr>
			<!--<tr><td class='tdbg1' width=150><b>User rating</td>			<td class='tdbg2'><?=$ratingstatus?></td></tr>-->
			<tr><td class='tdbg1' width=150><b>Registered on</td>		<td class='tdbg2'><?=printdate($user['regdate'])?> (<?=floor((ctime()-$user['regdate'])/86400)?> days ago)</td></tr>
			<tr><td class='tdbg1' width=150><b>Last post</td>			<td class='tdbg2'><?=$lastpostdate?><?=$lastpostlink?></td></tr>
			<tr><td class='tdbg1' width=150><b>Last activity</td>		<td class='tdbg2'><?=printdate($user['lastactivity'])?><?=$lastip?></td></tr>
		</table>
		<br>
		<table class='table'>
			<tr><td class='tdbgh center' colspan=2>Contact information</td></tr>
			<tr><td class='tdbg1' width=150><b>Email address</td>		<td class='tdbg2'><?=$email?>&nbsp;</td></tr>
			<tr><td class='tdbg1' width=150><b>Homepage</td>			<td class='tdbg2'><a href="<?=htmlspecialchars($user['homepageurl'])?>"><?=$homepagename?></a>&nbsp;</td></tr>
			<tr><td class='tdbg1' width=150><b>ICQ number</td>			<td class='tdbg2'><?=$user['icq']?> <?=$icqicon?>&nbsp;</td></tr>
			<tr><td class='tdbg1' width=150><b>AIM screen name</td>		<td class='tdbg2'><a href="aim:goim?screenname=<?=htmlspecialchars($aim)?>"><?=htmlspecialchars($user['aim'])?></a>&nbsp;</td></tr>
		</table>
		<br>
		<table class='table'>
			<tr><td class='tdbgh center' colspan=2>User settings</td></tr>
			<tr><td class='tdbg1' width=150><b>Timezone offset</td>		<td class='tdbg2'><?=$tzoffset?> hours from the server, <?=$tzoffrel?> hours from you (current time: <?=$tzdate?>)</td></tr>
			<tr><td class='tdbg1' width=150><b>Items per page</td>		<td class='tdbg2'><?=$user['threadsperpage']?> threads, <?=$user['postsperpage']?> posts</td></tr>
			<tr><td class='tdbg1' width=150><b>Color scheme</td>		<td class='tdbg2'><?=$schname?></td></tr>
		</table>
	</td>
	<td>&nbsp;&nbsp;&nbsp;</td>
	<td valign=top>
		<table class='table'>
			<tr><td class='tdbgh center'>RPG status</td></tr>
			<tr><td class='tdbg1'><img src='status.php?u=<?=$id?>'></td></tr>
		</table>
		<br>
		<table class='table'>
			<tr><td class='tdbgh center' colspan=2>Equipped Items</td></tr>
			<?=$shoplist?>
		</table>
	</td>
</table>
<br>
<table class='table'>
	<tr><td class='tdbgh center' colspan=2>Personal information</td></tr>
	<tr><td class='tdbg1' width=150><b>Real name</td>			<td class='tdbg2'><?=$user['realname']?>&nbsp;</td></tr>
	<tr><td class='tdbg1' width=150><b>Location</td>			<td class='tdbg2'><?=$user['location']?>&nbsp;</td></tr>
	<tr><td class='tdbg1' width=150><b>Birthday</td>			<td class='tdbg2'><?=$birthday?> <?=$age?>&nbsp;</td></tr>
	<tr><td class='tdbg1' width=150><b>User bio</td>			<td class='tdbg2'><?=dofilters(doreplace2(doreplace($user['bio'], $user['posts'], (ctime()-$user['regdate'])/86400, $id)))?>&nbsp;</td></tr>
</table>
<br>
<table class='table'>
<tr><td class='tdbgh center' colspan=2><center>Sample post</td></tr>
	<?=threadpost($user, 1)?>
</table>
<br>
<table class='table'>
	<tr><td class='tdbgh fonts center' colspan=2>Options</td></tr>
	<tr>
		<td class='tdbg2 fonts center' colspan=2>
			<a href="thread.php?user=<?=$id?>">Show posts</a> | 
			<a href="forum.php?user=<?=$id?>">View threads by this user</a>
			<?=$sendpmsg?>
			<?=$ratelink?>
			<?=$moodavatar?>
		</td>
	</tr>
	<tr>
		<td class='tdbg2 fonts center' colspan=2>
			<a href="postsbyuser.php?id=<?=$id?>">List posts by this user</a> |
			<a href="postsbytime.php?id=<?=$id?>">Posts by time of day</a> |
			<a href="postsbythread.php?id=<?=$id?>">Posts by thread</a> | 
			<a href="postsbyforum.php?id=<?=$id?>">Posts by forum</td><?=$sneek?>
		</td>
	</tr>
</table>
<?php

  pagefooter();
  
?>