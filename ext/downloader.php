<?php

	/*
		SFS File Downloader
	*/
	
	if (substr(php_sapi_name(), 0, 3) != 'cli') {
		if (!defined('MANUAL_DOWNLOAD')) die("No."); // If not called from cli or admin-downloader, die instantly
		echo "<pre>";
		set_time_limit(0);
	} else {
		$startingtime = microtime(true);
		echo "SFS Download Script v1.0";
		echo "\n=====================\n\n";

	}
	
	// Configuration
	
	const SFS_MULTI    = 0;
	const SFS_SINGLE   = 1;
	const SFS_RANGE    = 2;
	// <url>, <db table>, <explode marker>, <characters to remove from start>, <parse mode>, <extracted extension>
	$files = array(
		["http://www.stopforumspam.com/downloads/bannedips.zip",			 'sfs_iplist',   ','  , 0,  SFS_SINGLE, 'csv'], // CSV (.csv), one line
		["http://www.stopforumspam.com/downloads/spamdomains.zip", 			 'sfs_domain',   ', ' , 1,  SFS_SINGLE, 'csv'],
		["http://www.stopforumspam.com/downloads/listed_ip_1_all.zip", 		 'sfs_ip',	     NULL , 0,  SFS_MULTI, 'txt'], // CSV (.txt), multiple lines
		["http://www.stopforumspam.com/downloads/listed_username_1_all.zip", 'sfs_user',     NULL , 0,  SFS_MULTI, 'txt'],
		["http://www.stopforumspam.com/downloads/listed_email_1_all.zip", 	 'sfs_email',    NULL , 0,  SFS_MULTI, 'txt'],
		["http://www.stopforumspam.com/downloads/toxic_ip_range.txt", 	     'sfs_iprange',  NULL , 0,  SFS_RANGE], // <ip>-<ip>
	);
	
	chdir("..");
	
	require_once "lib/config.php";
	require_once "lib/mysql.php";
	
	echo "Connecting to database...";
	$sql 			= new mysql;
	$connection 	= $sql->connect($sqlhost, $sqluser, $sqlpass, $dbname) or die("\nConnection error.");
	echo "OK!";
	
	print "\nDownloading StopForumSpam data...\n";
	
	$zip = new ZipArchive;
	
	foreach ($files as $file) {
		echo "\nDownloading and extracting {$file[0]}...\n";
		
		$filename  = substr($file[0], strrpos($file[0], '/', -4) + 1, -4);
		$extension = substr($file[0], -3);
		$destpath  = "temp/{$filename}.{$extension}";
		
		#echo "[DEBUG] Extension is {$extension}\n";

		if ($extension == 'zip') {
			// Download and extract the contents in the temp directory
			#echo "[DEBUG] Downloading ZIP {$file[0]}\n";
			$res = download($file[0], "temp/sfsdat");
			if (!$res) continue;
			#echo "[DEBUG] Extracting temp/sfsdat";
			$res = zip_extract("temp/sfsdat", "temp");
			if (!$res) continue;
			#echo "[DEBUG] Extension changed to {$extension}\n";
			$extension = $file[5];
			$destpath  = "temp/{$filename}.{$extension}"; // meh
		} else if ($extension == 'txt') {
			// toxic_*.txt
			#echo "[DEBUG] Downloading TXT {$file[0]}\n";
			download($file[0], $destpath);
		}
		
		#echo "[DEBUG] Destpath is {$destpath}\n";
		
		echo "Importing to SQL database...";
		
		
		// Erase existing records since they are outdated
		#echo "[DEBUG] Truncating {$file[1]}\n";
		$sql->query("TRUNCATE {$file[1]}");
		$sql->connection->beginTransaction();
		
		if ($file[4] == SFS_SINGLE) { // Single line CSV file
			
			// Don't pass through the mysql class function
			$addp = $sql->connection->prepare("INSERT INTO {$file[1]} (field) VALUES (?)");
			#echo "[DEBUG] Reading contents of {$destpath}\n";
			$rows = explode($file[2], file_get_contents($destpath));
			// Due to how these files are structured, the last key is always going to be empty
			array_pop($rows);
			
			$i = 0;
			if ($file[3]) { // Need to remove characters from the start?
				foreach($rows as $x) {
					$i++;
					$addp->execute([substr($x, $file[3])]);
				}
			} else {
				foreach($rows as $x) {
					$i++;
					$addp->execute([$x]);
				}				
			}
		} else if ($file[4] == SFS_MULTI) {
			
			// Multi line csv
			$addp = $sql->connection->prepare("INSERT INTO {$file[1]} (field, reports, lastseen) VALUES (?,?,?)");
			#echo "[DEBUG] Opening {$destpath}\n";
			$handle = fopen($destpath, 'r');
			$i = 0;
			while($data = fgetcsv($handle, 192, ',', '"')){
				$addp->execute($data);
				$i++;
			}
			#echo "[DEBUG] Closing {$destpath}\n";
			fclose($handle);
		} else {
			// Multi line IP Range, not comma delimited
			$addp = $sql->connection->prepare("INSERT INTO {$file[1]} (ipstart, ipend) VALUES (?,?)");
			#echo "[DEBUG] Opening {$destpath}\n";
			$handle = fopen($destpath, 'r');
			$i = 0;
			while ($row = fgetcsv($handle, 68, '-')) {
				$addp->execute($row);
				$i++;
			}
			#echo "[DEBUG] Closing {$destpath}\n";
			fclose($handle);		
		}
		
		echo " $i rows inserted.\n\n";
		#echo "[DEBUG] Unlinking {$destpath}\n";
		unlink($destpath);
		$sql->connection->commit();
	}
	
	// Remove temporary zip file
	if (file_exists("temp/sfsdat"))
		unlink("temp/sfsdat");
	
	
	echo "\nFinished!";
	
	// TODO: Move these to /ext/function.php ?
	function download($url, $destination) {
		
		$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);		
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$contents = curl_exec($ch);
		curl_close($ch);
		
		// true or false means the same thing: file didn't download
		if ($contents !== true && $contents !== false) {
			if (($handle = fopen($destination ,'wb')) !== false) {
				fwrite($handle, $contents);
				fclose($handle);
				print "[OK] Downloaded {$url} to {$destination}\n";
				return true;
			}
			else print "[ERROR] File {$url} was downloaded, but the file {$destination} could not be opened; skipping\n";
		}
		else print "[ERROR] Couldn't download {$url}; skipping\n";
		return false;
	}
	
	function zip_extract($archive, $destination) {
		global $zip;
		
		if ($handle = $zip->open($archive, ZipArchive::CHECKCONS) !== false){
			$extr = $zip->extractTo($destination);
			print ($extr !== false) ? 
				"[OK] Extracted {$archive} to {$destination}\n" : 
				"[ERROR] Coudln't extract {$file} to {$destination}; skipping\n";
			$zip->close();
			return true;
		}
		else print "[ERROR] ZipArchive open Error - {$handle}; skipping\n";
		return false;
	}
