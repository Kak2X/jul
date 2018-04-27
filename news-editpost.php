<?php

	/*
		News editor
		Edits the contents in the news table
	*/
	
	require "lib/function.php";
	require "lib/news_function.php";
	
	if (!$canwrite){
		newserrorpage("You aren't allowed to edit posts.<br>
			Click <a href='news.php'>here</a> to return to the main page.
		");	
	}
	
	$id	= filter_int($_GET['id']);
	
	
	if (isset($_GET['edit'])){
		
		if (!$id) newserrorpage("No post ID specified.");
		
		$news = $sql->fetchq("
			SELECT 	n.id, n.name newsname, text, time, user, hide, cat,
					hide, lastedituser, lastedittime, $userfields uid, n.comments
			FROM news n
			LEFT JOIN users u ON n.user = u.id
			WHERE n.id = $id
		");
		
		if (!$news) 										newserrorpage("The post doesn't exist!");
		if (!$isadmin && $loguser['id'] != $news['user'])	newserrorpage("You have no permission to do this!");
		
		$name = isset($_POST['nname'])	? $_POST['newsname'] : $news['newsname'];
		$text = isset($_POST['text'])	? $_POST['text']	 : $news['text'];
		$tags = isset($_POST['cat'])	? $_POST['cat']		 : $news['cat'];
		
		if (isset($_POST['submit'])){
			checktoken();
			
			if (!$name || !$text) newserrorpage("You have left one of the required fields blank!");
			
			// Prevent creation of tags without alphanumeric characters
			$taglist = explode(";", $tags);
			foreach($taglist as $tag)
				if (alphanumeric($tag) != $tag)
					newserrorpage("One of the tags contains non-alphanumeric characters.");
			
			// Here we go
			$sql->queryp(
				"UPDATE news SET name = ?, text = ?, cat = ?, lastedituser = ?, lastedittime = ? WHERE id = $id",
				[$name, $text, $tags, $loguser['id'], time()]
			);
			header("Location: news.php?id=$id");
			x_die();
		}
		
		
		news_header("Edit post");
		
		if (isset($_POST['preview'])){
			print "<br>
				<table class='main w'><tr><td class='head c'>Message preview</td></tr>
				<tr><td class='dim'>".news_format(array_merge($news, $_POST))."</td></tr></table>";
		}
		
		print "<!-- <a href='news.php'>".$config['news-name']."</a> - Edit post --><br>
		<form method='POST' action='editnews.php?id=$id&edit'>
		<input type='hidden' name='auth' value='$token'>
		<center><table class='main'>
			<tr><td class='head c' colspan='2'>Edit post</td></tr>
			<!-- <tr>
				<td class='light c'><b>Post options:</b></td>
				<td class='dim'>[nothing yet]</td>
			</tr> -->
			<tr>
				<td class='light c'><b>Heading</b></td>
				<td class='dim'><input type='text' name='newsname' style='width: 580px' value=\"$name\"></td>
			</tr>
			<tr>
				<td class='light c'><b>Contents</b></td>
				<td class='dim'><textarea name='text' rows='21' cols='80' width='800px' style='resize:both;' wrap='virtual'>".htmlspecialchars($text)."</textarea></td>
			</tr>
			<tr>
				<td class='light c'>
					<b>Tags:</b><small><br>
					Only alphanumeric characters and spaces allowed<br>
					Multiple tags should be separated by ;
					</small>
				</td>
				<td class='dim'><textarea name='cat' rows='3' cols='80' width='800px' style='resize:both;' wrap='virtual'>".htmlspecialchars($tags)."</textarea></td>
			</tr>	
			<tr>
				<td class='dim' colspan='2'><input type='submit' name='submit' class='submit' value='Save changes'> <input type='submit' name='preview' class='submit' value='Preview'></td>
			</tr>
		</table></center>
		</form>
		";
		
	}
	else if (isset($_GET['new'])){
		// ACTION : New news
		
		$name = filter_string($_POST['newsname']);
		$text = filter_string($_POST['text']);
		$tags = filter_string($_POST['cat']);
		
		// hack hack
		$_POST['uid'] 		= $loguser['id'];
		$_POST['id'] 		= false;
		$_POST['time']		= time();
		$_POST['hide']		= 0;
		$_POST['comments'] 	= 0;
		
		if (isset($_POST['submit'])){
			checktoken();
			
			if (!$name || !$text)
				newserrorpage("You have left one of the required fields blank!");
			// Prevent creation of tags without alphanumeric characters
			$taglist = explode(";", $tags);
			foreach($taglist as $tag)
				if (alphanumeric($tag) != $tag)
					newserrorpage("One of the tags contains non-alphanumeric characters.");
				
			// Here we go
			$sql->queryp(
				"INSERT INTO news (name, text, cat, user, time) VALUES (?, ?, ?, ?, ?)",
				array($name, $text, $tags, $loguser['id'], time())
			);
			
			$id = $sql->lastInsertId();
			header("Location: news.php?id=$id");
			x_die();
		}
		
		
		news_header("Create post");
		
		if (isset($_POST['preview']))
			print "<br>
				<table class='main w'><tr><td class='head c'>Message preview</td></tr>
				<tr><td class='dim'>".news_format(array_merge($loguser,$_POST))."</td></tr></table>";

		
		print "<!-- <a href='news.php'>".$config['news-name']."</a> - Create post --><br>
		<form method='POST' action='editnews.php?new'>
		<input type='hidden' name='auth' value='$token'>
		<center><table class='main'>
			<tr><td class='head c' colspan='2'>Create post</td></tr>
			<!-- <tr>
				<td class='light c'><b>Post options:</b></td>
				<td class='dim'>[nothing yet]</td>
			</tr> -->			
			<tr>
				<td class='light c'><b>Heading</b></td>
				<td class='dim'><input type='text' name='newsname' style='width: 580px' value=\"$name\"></td>
			</tr>
			<tr>
				<td class='light c'><b>Contents</b></td>
				<td class='dim'><textarea name='text' rows='21' cols='80' width='800px' style='resize:both;' wrap='virtual'>".htmlspecialchars($text)."</textarea></td>
			</tr>
			<tr>
				<td class='light c'>
					<b>Tags:</b><small><br>
					Only alphanumeric characters and spaces allowed<br>
					Multiple tags should be separated by ;
					</small>
				</td>
				<td class='dim'><textarea name='cat' rows='3' cols='80' width='800px' style='resize:both;' wrap='virtual'>".htmlspecialchars($tags)."</textarea></td>
			</tr>	
			<tr>
				<td class='dim' colspan='2'><input type='submit' name='submit' class='submit' value='Create'> <input type='submit' name='preview' class='submit' value='Preview'></td>
			</tr>
		</table></center>
		</form>
		";
		
	}
	else if (isset($_GET['del'])){
		checktoken(true); // ?
		// ACTION: Hide/Unhide from normal users and guests
		if (!$id) newserrorpage("No news ID specified.");
		
		// Sanity check. Don't allow this unless you're the news author or an admin
		$news = $sql->resultq("SELECT user FROM news WHERE id = $id");
		
		if (!$news) 					newserrorpage("The post doesn't exist!");
		if ($loguser['id'] != $news)	newserrorpage("You have no permission to do this!");
		
		$sql->query("UPDATE news SET hide = NOT hide WHERE id = $id");
		
		redirect("news.php");
	}
	else if (isset($_GET['kill'])){
		if (isset($_POST['remove'])){
			checktoken();
			// ACTION: Delete from database
			if (!$id) 		newserrorpage("No post ID specified.");
			if (!$isadmin)  newserrorpage("You're not allowed to do this!");
			$news = $sql->resultq("SELECT 1 FROM news WHERE id = $id");
			if (!$news) 	newserrorpage("The post doesn't exist!");
			
			$sql->query("DELETE FROM news WHERE id = $id");
			redirect("news.php");
		}
		
		news_header("Erase post");
		?>
		<br><br>
		<form method='POST' action='<?php echo "?id=$id&kill" ?>'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		<center>
		
		<table class='main c'>
			<tr><td class='head'>WARNING</td></tr>
			<tr>
				<td>
					You are about to delete a post from the database.<br>
					<br>
					Are you sure you want to continue?<br>
					There's no going back!
				</td>
			</tr>
			<tr>
				<td class='light'>
					<input type='submit' class='submit' name='remove' value='Delete'>&nbsp;-&nbsp;
					<a href='news.php'>Return</a>
				</td>
			</tr>
		</table>
		
		</center>
		</form>
		<?php
	}
	else {
		newserrorpage("No action specified.");
	}
	
	pagefooter(true);
	
?>
