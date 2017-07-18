<?php

	require "lib/function.php";
	
	admincheck('forum-admin');

	if (isset($_POST['go'])){
		check_token($_POST['auth']);
		
		$_POST['table'] = filter_int($_POST['table']);
		$_POST['field'] = filter_int($_POST['field']);
		
		// Counter fixing files have a naming convention
		$filename = "admin-".($_POST['table'] ? "thread" : "forum")."s".($_POST['field'] ? "2" : "");
		
		if (!file_exists("utils/$filename.php")) {
			errorpage("Sorry, but this utility isn't implemented yet.");
		} else {
			include("utils/$filename.php");
		}
	}
	else {
		pageheader("Forum Counters");
		print adminlinkbar();
		
?>
		<br>
		<form method='POST' action='?'>
		<center>
		<table class='table' style='width: 600px'>
		
			<tr><td class='tdbgh center b'>Forum Counters</td></tr>
			
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
							<option value=0>Post/Thread counters</option>
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