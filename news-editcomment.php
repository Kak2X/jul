<?php
	require "lib/function.php";
	require "lib/news_function.php";
	
	/*
		Note: this page only applies the comment actions.
		The forms are shown in the newsheader function
	*/
	
	$id     = filter_int($_GET['id']);
	$action = filter_string($_GET['act']);
	
	if (!$loguser['id']) newserrorpage("You aren't allowed to do this!");
	
	if ($action == 'new'){
		
		// Has to send this
		if (isset($_POST['submit'])){
			checktoken();
			
			$text = prepare_string($_POST['text']);
			if (!$text)  newserrorpage("Your comment was blank!");
			$valid = $sql->resultq("SELECT 1 FROM news WHERE id = $id");
			if (!$valid) newserrorpage("You can't comment to a nonexisting post!");
			$lastcomment = $sql->resultq("SELECT time FROM news_comments WHERE user = {$loguser['id']} ORDER BY id DESC");
			if (time() - $lastcomment < $config['post-break']) newserrorpage("You are commenting too fast!");
			
			$sql->queryp("INSERT INTO news_comments (pid, user, text, time) VALUES (?,?,?,?)",
			[$id, $loguser['id'], $text, time()]);
			
			$pid = $sql->lastInsertId();
			redirect("news.php?id=$id#$pid");
			
		} else {
			newserrorpage("I don't get what you're trying to do here.");
		}
		
	}
	
	if ($action == 'edit'){
		
		if (isset($_POST['doedit'])){
			checktoken();
			
			$text = prepare_string($_POST['text']);
			if (!$text)  newserrorpage("You've edited the comment to be blank.");
			$c = checkcomment($id, 1); // Admin action / Edit own comment
			
			$sql->queryp("
				UPDATE news_comments SET
					text         = ?,
					lastedituser = ?,
					lastedittime = ?
				WHERE id = $id",
			[$text, $loguser['id'], time()]);
			
			redirect("news.php?id={$c['pid']}#$id");
			
		} else {
			newserrorpage("I <i>still</i> don't get what you're trying to do here.");
		}		
	}
	
	if ($action == 'del'){
		checktoken(true);
		$c = checkcomment($id, 1);
		$sql->query("UPDATE news_comments SET hide = NOT hide WHERE id = $id");
		redirect("news.php?id={$c['pid']}#$id");
	}

	if ($action == 'erase'){
		$c = checkcomment($id, 2);
		
		if (isset($_POST['remove'])){
			checktoken();
			$sql->query("DELETE FROM news_comments WHERE id = $id");
			redirect("news.php?id={$c['pid']}");
		}
		
		news_header("Erase comment");
		?>
		<br><br>
		<form method='POST' action='?act=erase&id=<?php echo "$id" ?>'>
		<input type='hidden' name='auth' value='<?php echo $token ?>'>
		<center>
		
		<table class='main c'>
			<tr><td class='head'>WARNING</td></tr>
			<tr>
				<td>
					You are about to delete a comment from the database.<br>
					<br>
					Are you sure you want to continue?<br>
					There's no going back!
				</td>
			</tr>
			<tr>
				<td class='light'>
					<input type='submit' class='submit' name='remove' value='Delete'>&nbsp;-&nbsp;
					<a href='news.php?id=<?php echo $c['pid'] ?>#<?php echo $id ?>'>Return</a>
				</td>
			</tr>
		</table>
		
		</center>
		</form>
		<?php
	}	
	
	pagefooter(true);
	
	// Load comment and check privileges
	function checkcomment($id, $privilege = 0){
		global $sql, $isadmin, $loguser;
		$c = $sql->fetchq("SELECT text, user, hide, pid FROM news_comments WHERE id = $id");
		if (!$c) 															newserrorpage("This post doesn't exist.");
		if (!$isadmin && $c['hide'])										newserrorpage("You aren't allowed to do this.");
		if (!$isadmin && !$c['pid'])										newserrorpage("You aren't allowed to do this.");
		if ($privilege == 1 && $loguser['id'] != $c['user'] && !$isadmin)	newserrorpage("You aren't allowed to do this.");
		if ($privilege == 2 && !$isadmin)									newserrorpage("You aren't allowed to do this!");
		return $c;
	}
?>