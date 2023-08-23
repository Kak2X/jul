<?php

print "<br>Changing option flag format...";
$res  = $sql->query("ALTER TABLE `pm_posts` 
	ADD `nosmilies` TINYINT(1) NOT NULL DEFAULT '0' AFTER `moodid`, 
	ADD `nohtml` TINYINT(1) NOT NULL DEFAULT '0' AFTER `nosmilies`");
$res &= $sql->query("ALTER TABLE `pm_posts` DROP `options`");
$res &= $sql->query("ALTER TABLE `posts` 
	ADD `nosmilies` TINYINT(1) NOT NULL DEFAULT '0' AFTER `moodid`, 
	ADD `nohtml` TINYINT(1) NOT NULL DEFAULT '0' AFTER `nosmilies`");
$res &= $sql->query("ALTER TABLE `posts` DROP `options`");
print checkres($res);