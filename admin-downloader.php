<?php

	require "lib/function.php";
	
	$windowtitle = "SFS";
	
	if (!has_perm("sysadmin-actions")) {
		errorpage("No.");
	}
	
	const MANUAL_DOWNLOAD = true;
	$tables = array(
		"sfs_iplist", 
		"sfs_ip", 
		"sfs_user", 
		"sfs_email", 
		"sfs_domain",
		"sfs_iprange"
	);

	if (isset($_POST['go'])){
		
		check_token($_POST['auth']);
		
		$act = filter_int($_POST['actid']);
		
		switch ($act) {
			case 1: // Manually download
				pageheader("$windowtitle - Download");
				print adminlinkbar();
?>
<table class="main w">
	<tr><td class="tdbgh center">Downloader</td></tr>
	<tr><td style="background: #000; color: #fff">
<?php
				chdir("ext");
				include "downloader.php"; // will chdir("..");
 ?>
	</td></tr>
</table>
<?php
				break;
			case 2: // Truncate tables
				foreach ($tables as $table)	$sql->query("TRUNCATE $table");	
			default:
				return header("Location: ?");
		}
	} else {
		pageheader($windowtitle);
		print adminlinkbar();
		
		$table_info = "";
		$row_total = $data_total = 0;
		
		// Get status of SFS tables
		$status = $sql->query("SHOW TABLE STATUS WHERE Name IN ('" . implode("','", $tables) . "')");
		
		while ($x = $sql->fetch($status)) {
			$table_info .= "
				<tr>
					<td class='tdbg1 center b'>{$x["Name"]}</td>
					<td class='tdbg2 right'>".number_format($x["Rows"])."</td>
					<td class='tdbg2 right'>".number_format($x["Data_length"] + $x["Index_length"])."</td>
				</tr>";
			// Update counters
			$row_total  += $x["Rows"];
			$data_total += $x["Data_length"] + $x["Index_length"];
		}
			
			?>
		<br>
		<form method="POST" action="?">
		<center>
		<table class="table" style="width: 600px">
		
			<tr><td class="tdbgh center">SFS Lists</td></tr>
			
			<tr>
				<td class="tdbg1"><center>
					<br>There are <?= count($tables) ?> SFS tables in the database.
					<br>&nbsp;
					<table class="table" style="width: 350px">
						<tr><td class="tdbgh center" colspan=3>Table Status</td></tr>
						<tr>
							<td class="tdbgc center b">Table name</td>
							<td class="tdbgc center b">Rows</td>
							<td class="tdbgc center b">Data size</td>
						</tr>
						<?= $table_info ?>
						<tr>
							<td class="tdbg1 center b">Total</td>
							<td class="tdbg2 right b"><?= number_format($row_total)  ?></td>
							<td class="tdbg2 right b"><?= number_format($data_total) ?></td>
						</tr>	
					</table>
					<br>
					<br>
					<table>
						<tr><td class="center" colspan=2>What do you want to do?</td></tr>
						<tr>
							<td><input type="radio" name="actid" value=1></td>
							<td>Redownload SFS files and rebuild tables</td>
						</tr>
						<tr>
							<td><input type="radio" name="actid" value=2></td>
							<td>Truncate tables</td>
						</tr>
					</table>
					
					<br><input type="submit" class="submit" value="Execute action" name="go">
					<br><?= auth_tag() ?>&nbsp;
				</center></td>
			</tr>
			
		</table>
		</center>
		</form>	
		<?php
	}
	
	pagefooter();