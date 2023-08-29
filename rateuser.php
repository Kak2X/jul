<?php
	// WELCOME TO THE PORTING WORLD
	// PORTING AND MORE PORTING AND MORE PORTING AND MORE PORTING

	require "lib/common.php";
	
	if (!$config['enable-ratings']) {
		errorpage("User ratings are disabled.", 'index.php', 'the index page');
	}
	if (!$loguser['id']) {
		errorpage("You need to be logged in to rate users.", 'login.php', 'log in');
	}
	
	$_GET['id']     = filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);
	$valid = $sql->resultq("SELECT 1 FROM users WHERE id = {$_GET['id']}");
	if (!$valid) {
		errorpage("This user doesn't exist.", 'index.php', 'the index page');
	}
	
	pageheader();
	
	if (isset($_POST['submit'])) {
		check_token($_POST['auth']);
		
		$_POST['rating'] = numrange(filter_int($_POST['rating']), 0, 10);
		if ($_GET['id'] == $loguser['id']) {
			errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for attempting to rate yourself.", 'index.php', 'return to the board');
		}
		$sql->query("
			INSERT INTO userratings (userfrom, userrated, rating) 
			VALUES ({$loguser['id']}, {$_GET['id']}, {$_POST['rating']})
			ON DUPLICATE KEY UPDATE rating = VALUES(rating)
		");
		errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for rating this user.", "profile.php?id={$_GET['id']}", "the user's profile");
	} else if ($_GET['action'] == 'viewvotes' && $isadmin) {
		$userlink = getuserlink(NULL, $_GET['id']);
		
		// Ratings to this user
		$ratings = $sql->query("
			SELECT r.userfrom, r.rating, {$userfields}
			FROM userratings r
			LEFT JOIN users u ON r.userfrom = u.id
			WHERE r.userrated = {$_GET['id']}
		");
		if ($sql->num_rows($ratings)) {
			$fromlist = "";
			while ($x = $sql->fetch($ratings)) {
				$fromlist .= "<b>{$x['rating']}</b> from ".getuserlink($x).'<br>';
			}
		} else {
			$fromlist = "None.";
		}
		
		// Ratings by this user
		$ratings = $sql->query("
			SELECT r.userfrom, r.rating, {$userfields}
			FROM userratings r
			LEFT JOIN users u ON r.userrated = u.id
			WHERE r.userfrom = {$_GET['id']}
		");
		if ($sql->num_rows($ratings)) {
			$votelist = "";
			while ($x = $sql->fetch($ratings)) {
				$votelist .= "<b>{$x['rating']}</b> for ".getuserlink($x).'<br>';
			}
		} else {
			$votelist = 'None.';
		}
		
?>
		<center>
		<table class="table" style="max-width: 1000px">
			<tr>
				<td class="tdbgh center b" style="width: 50%">Votes for <?= $userlink ?>:</td>
				<td class="tdbgh center b" style="width: 50%">Votes from <?= $userlink ?>:</td>
			</tr>
			<tr class="vatop">
				<td class="tdbg1"><?= $fromlist ?></td>
				<td class="tdbg1"><?= $votelist ?></td>
			</tr>
		</table>
		</center>
<?php
	} else {
		$ratesel = $sql->fetchq("SELECT 1 rated, rating FROM userratings WHERE userfrom = {$loguser['id']} AND userrated = {$_GET['id']}");
		if ($ratesel && $ratesel['rated']) {
			$sel[$ratesel['rating']] = " checked";
		}
		
		$ratelist = "";
		for ($i = 0; $i <= 10; $i++) {
			$ratelist .= "<input type='radio' name='rating' ".filter_string($sel[$i])." value='{$i}'> {$i} &nbsp";
		}
?>
		<form method="POST" action="?action=rateuser&id=<?=$_GET['id']?>">
		<table class="table">
			<tr>
				<td class="tdbgh center" style="width: 150px">&nbsp;</td>
				<td class="tdbgh center">&nbsp;</td>
			</tr>
			<tr>
				<td class="tdbg1 center b">Rating:</td>
				<td class="tdbg2"><?= $ratelist ?></td>
			</tr>
			<tr>
				<td class="tdbg1">&nbsp;</td>		
				<td class="tdbg2">
					<input type="submit" name="submit" VALUE="Give rating!">
					<?= auth_tag() ?>
				</td>
			</tr>
		</table>
		</form>
<?php	
	}
	
	pagefooter();