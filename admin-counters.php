<?php

	require "lib/function.php";
	
	admin_check('forum-admin');

	if (isset($_POST['go'])){
		check_token($_POST['auth']);
		
		$_POST['table'] = filter_int($_POST['table']);
		$_POST['field'] = filter_int($_POST['field']);
		
		// Counter fixing files have a naming convention
		$filename = "admin-fix-".($_POST['table'] ? "forum" : "thread")."s".($_POST['field'] ? "2" : "");
		
		if (!file_exists("utils/$filename.php")) {
			msg_holder::set_cookie("utils/{$filename}.php is not yet implemented.");
			return header("Location: ?");
		} else {
			include("utils/$filename.php"); // Get $util ref
			pageheader($util->get_title());
			print adminlinkbar();
			
			if (isset($_POST['run'])) {
?>
				<table class='table'>
					<tr><td class='tdbgh center'><?= $util->get_title() ?></td></tr>
					<tr><td class='tdbg1 center'>Now running.
					</td></tr>
				</table>
				<br>
<?php
				$count = $util->launch();
?>
				<table class="table" style="border-top: none">
					<tr><td class="tdbgc center">
					<?= ($count ? 
						"{$count} problem".($count != 1 ? "s" : "")." fixed." : 
						"Nothing to repair."
					)?>
					</td></tr>
				</table>
<?php
		
			} else {
?>
				<!--<div class="font"><a href="?">Forum counters</a> - <?= $util->get_title() ?></div>-->
				<form action="?" method="POST">  
				<table class="table">
					<tr><td class="tdbgh center b"><?= $util->get_title() ?></td></tr>
					<tr><td class="tdbg1 center">&nbsp;
						<br><?= nl2br($util->get_description()) ?>
						<br>&nbsp;
						<br><input type="submit" name="run" value="Start"> - <a href="?">Return</a>
						<br>
						<input type="hidden" name="auth" value="<?=generate_token()?>">&nbsp;
						<input type="hidden" name="table" value="<?=$_POST['table']?>">
						<input type="hidden" name="field" value="<?=$_POST['field']?>">
						<input type="hidden" name="go" value="1">
					</td></tr>
				</table>
				</form>
<?php
			}
		}
	}
	else {
		pageheader("Forum Counters");
		print adminlinkbar();
		print msg_holder::get_message();
?>
		<!--<div class="font">Forum counters</div>-->
		<form method='POST' action='?'>
		<center>
		<table class='table'>
		
			<tr><td class='tdbgh center b'>Fix Forum Counters</td></tr>
			
			<tr>
				<td class='tdbg1'><center>
					&nbsp;
					<br>This page is intended to repair thread and forum information.<br>Please don't flood it with requests.
					<br>&nbsp;
					<br>Select something to repair:
						<select name="table">
							<option value=0>Threads</option>
							<option value=1>Forums</option>
						</select>
						&nbsp;-&nbsp;
						<select name="field">
							<option value=0>Reply/total counters</option>
							<option value=1>Last reply date</option>
						</select>
					<br>&nbsp;
					<br><input type='submit' class='submit' value='Start' name='go'>
						<input type='hidden' name='auth' value='<?= generate_token() ?>'>
					<br>&nbsp;
				</center></td>
			</tr>
			
		</table>
		</center>
		</form>	
		<?php
	}
	
	pagefooter();