<?php
	die("Not implemented.");
	require "lib/function.php";
	
	$windowtitle = "SFS";
	
	if (!has_perm('sysadmin-actions')) {
		errorpage("No.");
	}	
	$tables = ['sfs_iplist', 'sfs_ip', 'sfs_user', 'sfs_email', 'sfs_domain'];
	

	if (isset($_POST['go'])){
		checktoken();
		
		$act = filter_int($_POST['actid']);
		
		switch ($act) {
			case 1: // Manually download
				pageheader("$windowtitle - Download");
				print adminlinkbar();
				echo "<table class='main w'><tr><td class='head c'>Downloader</td></tr><tr><td style='background: #000; color: #fff'>";
				chdir("ext");
				include "downloader.php"; // will chdir("..");
				echo "</td></tr></table>";
				break;
			case 2: // Truncate tables
				foreach ($tables as $table)	$sql->query("TRUNCATE $table");	
				redirect("?");
		}
	}
	else {
		pageheader($windowtitle);
		print adminlinkbar();
		
		$list = "";
		$tr = $tt = 0;
		// Get status of tables
		$status = $sql->query("SHOW TABLE STATUS IN $sqldb WHERE Name IN ('".implode("','", $tables)."')");
		while ($x = $sql->fetch($status)){
			$list .= "
			<tr>
				<td class='dim c'>{$x['Name']}</td>
				<td class='dim r'>".number_format($x['Rows'])."</td>
				<td class='dim r'>".number_format($x['Data_length'] + $x['Index_length'])."</td>
			</tr>";
			// Update counters
			$tr += $x['Rows'];
			$tt += $x['Data_length'] + $x['Index_length'];
		}
		$list .= "
		<tr>
			<td class='dim c'>Total</td>
			<td class='dim r'>".number_format($tr)."</td>
			<td class='dim r'>".number_format($tt)."</td>
		</tr>";
			
			?>
		<br>
		<form method='POST' action='?'>
		<input type='hidden' name='auth' value='<?= generate_token() ?>'>
		<center>
		<table class='main' style='width: 600px'>
		
			<tr><td class='head c'>SFS Info</td></tr>
			
			<tr>
				<td class='light'><center>
					<br>There are <?= count($tables) ?> SFS tables in the database.
					<br>&nbsp;
					<table class='main' style='width: 350px'>
						<tr><td class='head c' colspan=3>Table Status</td></tr>
						<tr>
							<td class='dark c'>Table name</td>
							<td class='dark c'>Rows</td>
							<td class='dark c'>Data size</td>
						</tr>
						<?= $list ?>
					</table>
					<br>
					<br>
					<table>
						<tr><td class='c' colspan=2>What do you want to do?</td></tr>
						<tr>
							<td><input type='radio' name='actid' value=1></td>
							<td>Redownload SFS files and rebuild tables</td>
						</tr>
						<tr>
							<td><input type='radio' name='actid' value=2></td>
							<td>Truncate tables</td>
						</tr>
					</table>
					
					<br><input type='submit' class='submit' value='Execute action' name='go'>
					<br>&nbsp;
				</center></td>
			</tr>
			
		</table>
		</center>
		</form>	
		<?php
	}
	
	pagefooter();
?>