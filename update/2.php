<?php

// SMB3P fork detection
print "<br>Detecting old 'canattach' field...";
$custom = $sql->fetchq("SHOW COLUMNS FROM `forums` LIKE 'canattach'");
if ($custom) {
	print "<span c";
	print "<span class='highlight'>Detected</span><br>Converting 'canattach' field to 'attachmentmode'...";
	$res  = $sql->query("ALTER TABLE `forums` CHANGE `canattach` `attachmentmode` TINYINT NOT NULL DEFAULT '-1'");
	$res2 = $sql->query("UPDATE forums SET attachmentmode = attachmentmode - 2");	
	print checkres($res && $res2);
} else {
	print "<span class='highlight'>Not detected</span><br>Adding per-forum attachment lock field...";
	$res  = $sql->query("ALTER TABLE `forums` ADD `attachmentmode` TINYINT NOT NULL DEFAULT '-1' AFTER `login`;");
	print checkres($res);
}

// This key doesn't serve any purpose anymore now that the SQL debug trigger is cookie-based
print "<br>Removing 'enable-sql-debugger'...";
unset($config['enable-sql-debugger']);
print checkres(true);