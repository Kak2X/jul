<?php
	
	require "lib/function.php";
	
	if (!$loguser['id']) errorpage("You must be logged in to do this!", 'index.php', 'the index page');
	if ($banned)         errorpage("Banned users aren't allowed to do this!", 'index.php', 'the index page');
	
	$_GET['id']     = filter_int($_GET['id']);
	$_GET['act']    = filter_string($_GET['act']);
	$_GET['page']   = filter_int($_GET['page']);
	

	
	if ($_GET['act'] == 'add'){
		check_token($_POST['auth']);
		
		$_POST['text'] = filter_string($_POST['text']);
		$lastcomment   = $sql->resultq("SELECT date FROM users_comments WHERE userfrom = {$loguser['id']} ORDER BY id DESC");
		
		if (!valid_user($_GET['id']))
			errorpage("This user doesn't exist!", 'index.php', 'the index page');
		if (!$_POST['text'])
			errorpage("Your comment was blank.", "profile.php?id={$_GET['id']}", "the user's profile");	
		if (ctime() - $lastcomment < 15) 
			errorpage("You are commenting too fast!"); // No redirect, to allow refreshing
		
		$vals = array(
			'userfrom' => $loguser['id'], 
			'userto' => $_GET['id'],
			'date' => ctime(),
			'text' => xssfilters($_POST['text']),
		);
		$sql->queryp("INSERT INTO users_comments SET ".mysql::setplaceholders($vals), $vals);		
		return header("Location: profile.php?id={$_GET['id']}#comments");
	} else if ($_GET['act'] == 'del') {
		check_token($_GET['auth'], TOKEN_MGET);
		if (!$isadmin) 
			errorpage("You aren't allowed to do this!", 'index.php', 'the index page');
		if (!valid_user($_GET['id']))
			errorpage("This user doesn't exist!", 'index.php', 'the index page');
		
		$sql->query("DELETE FROM users_comments WHERE id = {$_GET['id']}");
		return header("Location: profile.php?id={$_GET['id']}#comments");
	} else {
		
		$ppp = get_ppp();
		$query = "?id={$_GET['id']}";
		if (isset($_GET['ppp'])) {
			$query .= "&ppp={$ppp}";
		}
		
		$user     = load_user($_GET['id']);
		$thisuser = getuserlink($user);
			
		if (isset($_GET['to'])) { // Viewing comments directed to this user?
			$query .= "&to";
			if (!valid_user($_GET['id']))
				errorpage("This user doesn't exist!", 'index.php', 'the index page');
			$join  = "userfrom";
			$assoc = "userto";
			$htitle = "Profile comments sent to {$thisuser}";
		} else { // Viewing comments sent by this user?
			if (!$_GET['id'] || !valid_user($_GET['id']))
				$_GET['id'] = $loguser['id'];
			$join  = "userto";
			$assoc = "userfrom";
			$htitle = "Profile comments by {$thisuser}";
		}
		

		
		$comments = $sql->query("
			SELECT c.id cid, c.userfrom, c.date, c.text, $userfields
			FROM users_comments c
			LEFT JOIN users u ON c.{$join} = u.id
			WHERE c.{$assoc} = {$_GET['id']}
			ORDER BY c.id DESC
			LIMIT ".($_GET['page'] * $ppp).", {$ppp}
		");
		$total = $sql->resultq("SELECT COUNT(*) FROM users_comments WHERE userfrom = {$_GET['id']}");
		$pagelinks = pagelist($query, $total, $ppp);
	
		$list  = "";
		$i     = 0;
		$token = generate_token(TOKEN_MGET);
		if (!$sql->num_rows($comments)) {
			$list = "<tr><td class='tdbg1 center' colspan=4>No comments found.</td></tr>";
		} else while ($x = $sql->fetch($comments)) {
			$dellink = $isadmin ? "<a href='usercomment.php?act=del&id={$x['cid']}&auth={$token}'>Remove</a>" : $x['cid'];
			$cell = ($i++ % 2) + 1;
			$list .= "<tr>
				<td class='tdbg{$cell} center fonts'>{$dellink}</td>
				<td class='tdbg{$cell} center nobr'>".getuserlink($x)."</td>
				<td class='tdbg{$cell} center nobr'>".printdate($x['date'])."</td>
				<td class='tdbg{$cell}'>".htmlspecialchars($x['text'])."</td>
			</tr>";
		}
		
		pageheader("Profile comments");
?>
	<?= $pagelinks ?>
	<table class="table">
		<tr><td class='tdbgh center b' colspan=4><?= $htitle ?></td></tr>
		<tr>
			<td class='tdbgh center' style='width: 70px'>#</td>
			<td class='tdbgh center nobr' style='width: 150px'>User</td>
			<td class='tdbgh center nobr' style='width: 200px'>Date</td>
			<td class='tdbgh center'>Message</td>
		</tr>
		<?= $list ?>
	</table>
	<?= $pagelinks ?>
<?php
		pagefooter();
	}