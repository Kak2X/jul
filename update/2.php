<?php


update_step();

// SMB3P fork detection
print "<br>Detecting old 'canattach' field...";
$custom = $sql->fetchq("SHOW COLUMNS FROM `forums` LIKE 'canattach'");
if ($custom) {
	print "<span class='highlight'>Detected</span><br>Converting 'canattach' field to 'attachmentmode'...";
	$res  = $sql->query("ALTER TABLE `forums` CHANGE `canattach` `attachmentmode` TINYINT NOT NULL DEFAULT '-1'");
	$res2 = $sql->query("UPDATE forums SET attachmentmode = attachmentmode - 2");	
	print checkres($res && $res2);
} else {
	print "<span class='highlight'>Not detected</span><br>Adding per-forum attachment lock field...";
	$res  = $sql->query("ALTER TABLE `forums` ADD `attachmentmode` TINYINT NOT NULL DEFAULT '-1' AFTER `login`;");
	print checkres($res);
}

update_step();
// This key doesn't serve any purpose anymore now that the SQL debug trigger is cookie-based
print "<br>Removing 'enable-sql-debugger'...";
unset($config['enable-sql-debugger']);
print checkres(true);

update_step();

// Apparently creating folders can have permission issues
$folders = glob("userpic/*", GLOB_NOSORT | GLOB_ONLYDIR);

foreach ($folders as $x) {
	
	print "<br>Processing '<span class='highlight'>{$x}</span>'...";
	$slash_pos = strlen($x); // Position of second slash (to convert to '_')
	$pics = glob($x."/*", GLOB_NOSORT);
	foreach ($pics as $oldpath) {
		
		if (isset($oldpath[$slash_pos]) && $oldpath[$slash_pos] == "/") { // just in case
			// how the file gets renamed:
			// 'userpic/8/12' -> 'userpic/8_12'
			$newpath = $oldpath;
			$newpath[$slash_pos] = "_";
			
			print "<br>&nbsp;->&nbsp;Moving '<span class='warn'>{$oldpath}</span>' to '<span class='highlight'>{$newpath}</span>'...";
			print checkres(rename($oldpath, $newpath));
		}
		
	}
	print "<br>&nbsp;->&nbsp;Removing directory...";
	print checkres(rmdir($x));
}
