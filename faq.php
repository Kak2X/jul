<?php
	
	require "lib/common.php";
	
	pageheader("FAQ / Rules");
	
	#errorpage("There is no FAQ.");

	$topiclist	= "";
	$faq		= "";
  	if ($x_hacks['host']) {
		print "<table class='table'>
				<tr><td class='tdbgh center'>FAQ and Rules</td></tr>
				<tr><td class='tdbg1'>Generally, this forum is for a small group of people that know each other well. You should probably think twice about registering if you don't know who the regulars are already.				
				</td></tr>
			</table>";
			
	} else if (file_exists("faq.dat")) {
		print "<table class='table'>
				<tr><td class='tdbgh center'>FAQ and Rules</td></tr>
				<tr><td class='tdbg1'>";
		require "faq.dat";
		print "</td></tr></table>";
	} else {
	
	$faq	.= _faqformat("darules", "The Rules", "
		Our rules are really <i>really simple</i>, if you take the time to learn them. And you <i>should!</i>
		<ol>
			<li><b>Don't be an asshole.</b> If you don't have something constructive to say, <i>don't say it!</i> This is the big one.
			<li><b>This forum's official language is English</b>. Don't use chat-speak or other unfunny gimmicks.
			<li><b>Double-posting / bumping old threads.</b> Replying within minutes asking if anybody has read your post is a terrible idea. Double posting after a day or two <i>with something new or updated</i> is fine, though. If you want to bump an old thread, contribute something new to it.
			<li><b>Don't get in fights.</b> If you're having trouble with another user, contact an administrator.
			<li><b>Nothing illegal.</b> No warez, illegal crap, etc. There are exceptions for ROMs in certain cases.
			<li><b>The staff has the final say in everything.</b> We can ban you for any reason or no reason at all. Posting here is NOT a right.
		</ol>
		<br>Punishments are given on a case-by-case basis, from 'warning' to 'permanent ban'.
		<br>
		<br>We are a relaxed community, but constantly breaking the rules will get you banned fast.
		<br>
		<br>If you have any questions, feel free to ask <a href='memberlist.php?pow=3'>one of the admins</a> for help.
		<!--
		<center><img src='http://i55.photobucket.com/albums/g138/shalpp/1262546103597.jpg' title='NO. FUN. ALLOWED.'></center>
		-->
	");

	$faq	.= _faqformat("aboot", "About Jul", "
		Jul itself is a community made up of people who predominantly like to just hang around friendlies and talk about whatever, though we also like games and occasionally anime/other things.
	");

	$faq	.= _faqformat("newbies", "I'm new here. Where should I start?", "
		Always, by reading the rules... but since you're here, it's <i>probably</i> a safe bet that you've already done that. (If you haven't, <i>now is a great time.</i>)
		<br>
		<br>Once you've done that, <a href='register.php'>sign up for an account</a> (or <a href='login.php'>log in</a> if you've already made one). It's simple and very easy to do. After you're registered, you're more than welcome to just <a href='forum.php?id=1'>jump in and say hi</a> by posting in the Introductions thread, or even making your own. We're friendly people and won't bite (usually). Let us know about yourself, how you found us, or whatever's on your mind &mdash; or just jump in and start contributing to discussions.
	");
/*
	$faq	.= _faqformat("n00b", "I have this <img src='images/noob/noobsticker2-4.png' alt='n00b' title='TKEP regulars know this one' align='absmiddle' style='margin-top: -4px; margin-bottom: -4px;'> sticker on my post. What's up with that?", "
		The n00b sticker is our way of telling you that your post was pretty awful. Usually it's for one of the following reasons:
		<ol>
			<li>Complete disregard for our rules. If you show that you really can't even be bothered to read the small number of rules we have here, you're going to wear your welcome out <em>very</em> fast.</li>
			<li>Flagrant lack of basic knowledge. For example, if there's a sticky saying 'don't make a new thread for this' and you make a new thread for it, that's a big sign that you don't read the rules.</li>
			<li>Using dumb memes or bandwagoning. Everybody loves a laugh every now and then. Nobody loves it being rammed down their throat every five seconds.</li>
			<li>Terrible spelling or grammar. This is beyond the occasional misspelling (even the best of us make mistakes), but if you make a post loaded with \"Your a looser\", well...</li>
			<li>Your post is just mind-bogglingly terrible or groan-worthy.</li>
		</ol>
		The n00b sticker is something of a mark of shame. Usually it's an early warning indicator before we start taking issues with your actions on a broader scale, so if you see them, you should probably shape up. Note, however, that they can just as similarly be used as a joke.
		<br>
		<br><strong>Remember:</strong> The fastest way to get yourself stamped is to make a big deal out of it.
	");
*/
	$faq	.= _faqformat("layoutlowdown", "What are post layouts?", "
	Post layouts are like signatures on other forums, but on steroids. Rather than just some text, an image, and maybe a link, post layouts allow you to style your <em>entire post</em>! You too can turn your wonderful contributions into a GeoCities&trade;-esque abomination.
	<br>
	<br>You can enable or disable the showing of post layouts by choosing the relevant option in your <a href='editprofile.php'>profile settings</a>.
	<br>
	<br>(Particularly egregious/unreadable layouts will be removed by admins. Abuse of this feature will lead to you no longer being able to use one.)
	<br>
	<br>You can customize your layout with fun facts about your statistics by using &amp;tags&amp;, outlined below.
	");


	$faq	.= _faqformat("tags", "What are &amp;tags&amp;?", "
		These are variables that can be used in your post header or signature. Once you post, they'll get replaced with a value depending on the tag used.
		<br>
		<br>
		<table class='table' style='width: auto; margin: 0 auto;'>
		  <tr>
		    <td class='tdbgh center'>Tags</td>
		    <td class='tdbgh center'>Description</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>/me</td>
		    <td class='tdbg1'>Your username (must have a space after it)</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;date&amp;</td>
		    <td class='tdbg1'>Current date</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;numdays&amp;</td>
		    <td class='tdbg1'>Number of days since registration</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;numposts&amp;</td>
		    <td class='tdbg1'>Current post count</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;rank&amp;</td>
		    <td class='tdbg1'>Current rank, according to your amount of posts</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;postrank&amp;</td>
		    <td class='tdbg1'>Post ranking</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;5000&amp;</td>
		    <td class='tdbg1'>Posts left until you have 5000</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;10000&amp;</td>
		    <td class='tdbg1'>Posts left until you have 10000</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;20000&amp;</td>
		    <td class='tdbg1'>Posts left until you have 20000</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;30000&amp;</td>
		    <td class='tdbg1'>Posts left until you have 30000</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;exp&amp;</td>
		    <td class='tdbg1'>EXP</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;expgain&amp;</td>
		    <td class='tdbg1'>EXP gain per post</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;expgaintime&amp;</td>
		    <td class='tdbg1'>Seconds for 1 EXP when idle</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;expdone&amp;</td>
		    <td class='tdbg1'>EXP done in the current level</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;expdone1k&amp;</td>
		    <td class='tdbg1'>EXP done / 1000</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;expdone10k&amp;</td>
		    <td class='tdbg1'>EXP done / 10000</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;expnext&amp;</td>
		    <td class='tdbg1'>Amount of EXP left for next level</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;expnext1k&amp;</td>
		    <td class='tdbg1'>EXP needed / 1000</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;expnext10k&amp;</td>
		    <td class='tdbg1'>EXP needed / 10000</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;exppct&amp;</td>
		    <td class='tdbg1'>Percentage of EXP done in the level</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;exppct2&amp;</td>
		    <td class='tdbg1'>Percentage of EXP left in the level</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;level&amp;</td>
		    <td class='tdbg1'>Level</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;lvlexp&amp;</td>
		    <td class='tdbg1'>Total EXP amount needed for next level</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;lvllen&amp;</td>
		    <td class='tdbg1'>EXP needed to go through the current level</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>&amp;mood&amp;</td>
		    <td class='tdbg1'>Selected mood number for this post (default: 0)</td>
		  </tr>
		</table>
	");
	
	$faq	.= _faqformat("bbcode", "What is BBcode?", domarkup("
		BBcode is a simple syntax which you can use on your posts to format the text or add images and videos. Below is a list of the supported tags:
		<br>
		<br>
		<table class='table'style='width: auto; margin: 0 auto;'>
		  <tr>
		    <td class='tdbgh center' style='width: 50%;'>BBcode</th>
		    <td class='tdbgh center' style='width: 50%;'>Result</th>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[b<z>]Bolded text.[/b<z>]</td>
		    <td class='tdbg1'>[b]Bolded text.[/b]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[i<z>]Italicized text.[/i<z>]</td>
		    <td class='tdbg1'>[i]Italicized text.[/i]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[u<z>]Underlined text.[/u<z>]</td>
		    <td class='tdbg1'>[u]Underlined text.[/u]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[s<z>]Strikethrough text.[/s<z>]</td>
		    <td class='tdbg1'>[s]Strikethrough text.[/s]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[abbr<z>=Basic Input/Output System]BIOS[/abbr<z>]</td>
		    <td class='tdbg1'>[abbr=Basic Input/Output System]BIOS[/abbr]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[sp<z>=terrible]Great[/sp<z>] software.</td>
		    <td class='tdbg1'>[sp=terrible]Great[/sp] software.</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[url<z>]http://example.com/[/url<z>]</td>
		    <td class='tdbg1'>[url]http://example.com/[/url]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[url<z>=http://example.com/]Link text here.[/url<z>]</td>
		    <td class='tdbg1'>[url=http://example.com/]Link text here.[/url]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[im<z>g]https://tcrf.net/images/c/c4/SMB2-smiley.png[/img<z>]</td>
		    <td class='tdbg1'>[img]https://tcrf.net/images/c/c4/SMB2-smiley.png[/img]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[red<z>]Red color.[/color<z>]</td>
		    <td class='tdbg1'>[red]Red color.[/color]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[green<z>]Green color.[/color<z>]</td>
		    <td class='tdbg1'>[green]Green color.[/color]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[blue<z>]Blue color.[/color<z>]</td>
		    <td class='tdbg1'>[blue]Blue color.[/color]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[orange<z>]Orange color.[/color<z>]</td>
		    <td class='tdbg1'>[orange]Orange color.[/color]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[yellow<z>]Yellow color.[/color<z>]</td>
		    <td class='tdbg1'>[yellow]Yellow color.[/color]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[pink<z>]Pink color.[/color<z>]</td>
		    <td class='tdbg1'>[pink]Pink color.[/color]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[white<z>]White color.[/color<z>]</td>
		    <td class='tdbg1'>[white]White color.[/color]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[black<z>]Black color.[/color<z>] (bad idea)</td>
		    <td class='tdbg1'>[black]Black color.[/color]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[quote<z>=user]Quoted text.[/quote<z>]</td>
		    <td class='tdbg1'>[quote=user]Quoted text.[/quote]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[code<z>]Sample &lt;b&gt;code&lt;/b&gt;.[/code<z>]</td>
		    <td class='tdbg1'>[code]Sample <b>code</b>.[/code]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[spoiler<z>]Spoiler text.[/spoiler<z>]</td>
		    <td class='tdbg1'>[spoiler]Spoiler text.[/spoiler]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[spoileri<z>]Inline spoiler text.[/spoileri<z>]</td>
		    <td class='tdbg1'>[spoileri]Inline spoiler text.[/spoileri]</td>
		  </tr>
		  <tr><td class='tdbgh center' colspan='2'>Embeds</td></tr>
		  <tr>
		    <td class='tdbg2'>[youtube<z>]BrQn-O_zFRc[/youtube] (video ID)</td>
		    <td class='tdbg1' rowspan='4'>Various YouTube embeds.</td>
		  </tr>
		  <tr>
			<td class='tdbg2'>[youtube<z>]https://www.youtube.com/watch?v=vnAoTQEvrik[/youtube]</td></td>
		  </tr>
		  <tr>
			<td class='tdbg2'>[youtube<z>]https://youtu.be/xoTwj42szX0[/youtube]</td>
		  </tr>
		  <tr>
			<td class='tdbg2'>[youtube<z>]https://youtu.be/JCBB1zJQt2o?t=16[/youtube]</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[twitter<z>]614487585767993344[/twitter] (tweet number)</td>
		    <td class='tdbg1'>Twitter embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[vine<z>]MLZdle0OdmX[/vine] (video ID)</td>
		    <td class='tdbg1'>Vine embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[dailymotion<z>]xx1h83[/dailymotion] (video ID)</td>
		    <td class='tdbg1'>Dailymotion embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[bc<z>]3056541004[/bc] (track ID)</td>
		    <td class='tdbg1'>Bandcamp track embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[bca<z>]1889307725[/bca] (album ID)</td>
		    <td class='tdbg1'>Bandcamp album embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[facebook<z>]https://www.facebook.com/realwhatguyslike/videos/1120635061403078/[/facebook]</td>
		    <td class='tdbg1'>Facebook embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[sc<z>]283288473[/sc] (track ID)</td>
		    <td class='tdbg1'>SoundCloud track embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[sca<z>]257385526[/sca] (album ID)</td>
		    <td class='tdbg1'>SoundCloud album embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[twitch<z>]260289216[/twitch] (video ID)</td>
		    <td class='tdbg1'>Twitch video embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[clip<z>]ArtsyFuriousClipsmomEleGiggle[/clip] (clip ID)</td>
		    <td class='tdbg1'>Twitch clip embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[nnd<z>]sm31335196[/nnd] (video ID)</td>
		    <td class='tdbg1'>Nico Nico embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[streamable<z>]bkbav8[/streamable] (video ID)</td>
		    <td class='tdbg1'>Streamable embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[vimeo<z>]452926602[/vimeo] (video ID)</td>
		    <td class='tdbg1'>Vimeo embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[odysee<z>]https://odysee.com/@XyliboxFranceVXCVE:3/dendroid:7[/odysee]</td>
		    <td class='tdbg1'>Odysee embed</td>
		  </tr>
		  <tr>
		    <td class='tdbg2'>[rumble<z>]https://rumble.com/embed/v2lgdmv/?pub=4[/rumble] (Embed IFRAME URL)</td>
		    <td class='tdbg1'>Rumble embed</td>
		  </tr>
		</table>
	"));

	$faq	.= _faqformat("halp", "I've got a question and I need some help, or I found a bug somewhere.", "
		<a href='forum.php?id=39'>Post it in the forum here</a>, or alternatively just message the <a href='newpmthread.php?userid=1'>main administrator</a>. If it's a security bug in the code, we <i>really</i> recommend the latter.
	");

	$faq	.= _faqformat("band", "I've been banned. Now what?", "
		You can try checking your title (under your username in your posts) to find out the reason and when it expires. If there's no expiration, it's probably <i>permanent</i>. If you're post due for unbanning, <a href='newpmthread.php?userid=1'>let an admin know</a> and they'll take care of it.
		<br>
		<br>On the other hand, if it's permanent, you can always try to show us you've changed and request a <i>second chance</i>... but any further antics after that will usually get your account <b>deleted</b>.
	");

	$faq	.= _faqformat("cantpass", "I've lost/forgotten my password. Now what?", "
		The best thing you can do is to <a href='profile.php?id=1'>contact Xkeeper directly</a>. He can help you get it fixed.
	");


	$faq	.= _faqformat("frosteddonut", "I want to throw money at you guys. How do I do that?", "
			Really? How generous.
		<br>
		<br>Donations with this button go straight to the hosting bill, and we can't withdraw them, so you don't have to worry about us secretly buying drugs or other fancy stuff with your money.
		<br>
		<br>However, there is a slight fee involved, so suffice it to say it's often better to donate $20 at once intead of ten $2 donations.
		<br>
		<br><a href=\"http://www.dreamhost.com/donate.cgi?id=11617\"><img border=\"0\" alt=\"Donate towards Jul's web hosting!\" title='Click this and give us your money.' src=\"https://secure.newdream.net/donate1.gif\" /></a>
		<br>
		<br>Thanks in advance.
		<br>
		<br>At some point we plan on getting a 'donor star' for those who paid our bills... other than that, there isn't really any other benefit than a warm, fuzzy feeling.
	");

?>
	<table class='table'>
		<tr><td class='tdbgh center'>FAQ and Rules</td></tr>
		<tr><td class='tdbg1'><b>Table of Contents</b>:
		<ul>
			<?=$topiclist?>
		</ul></td></tr>
	</table>

	<?=$faq?>
<?php

	}

	pagefooter();

	function _faqformat($a, $title, $content) {
		global $topiclist;
	
		$topiclist	.= "\n\t\t<li><a href='#$a'>$title</a></li>";

		return "<br><br><a name='$a'></a>
		<table class='table'>
			<tr><td class='tdbgh center'><div style='float: right;'>[<a href='#top'>^</a>]</div><b>$title</b></td></tr>
			<tr><td class='tdbg1' style='padding: 4px;'>$content	
			</td></tr>
		</table>
		";
	}


?>