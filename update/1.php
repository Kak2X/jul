<?php

print "<br>Adding per-user uploader lock field...";
$res  = $sql->query("ALTER TABLE `users` ADD `uploader_locked` BOOLEAN NOT NULL DEFAULT FALSE AFTER `uploads_locked`");
$res2 = $sql->query("ALTER TABLE `delusers` ADD `uploader_locked` BOOLEAN NOT NULL DEFAULT FALSE AFTER `uploads_locked`");
print checkres($res && $res2);

// ====================================================

print "<br>Creating table 'uploader_cat'...";
$res  = $sql->query("DROP TABLE IF EXISTS `uploader_cat`");
$res2 = $sql->query("CREATE TABLE `uploader_cat` (
  `id` mediumint(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `files` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `downloads` int(10) unsigned NOT NULL DEFAULT '0',
  `lastfile` varchar(255) DEFAULT NULL,
  `lastfiledate` int(10) unsigned NOT NULL DEFAULT '0',
  `lastfileuser` int(10) unsigned NOT NULL DEFAULT '0',
  `minpowerread` tinyint(4) NOT NULL DEFAULT '0',
  `minpowerupload` tinyint(4) NOT NULL DEFAULT '0',
  `minpowermanage` tinyint(4) NOT NULL DEFAULT '0',
  `user` int(10) unsigned NOT NULL DEFAULT '0',
  `ord` smallint(5) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `private` (`minpowerread`),
  KEY `user` (`user`),
  KEY `ord` (`ord`),
  KEY `minpowerupload` (`minpowerupload`),
  KEY `minpowermanage` (`minpowermanage`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1");
print checkres($res && $res2);

// ====================================================

print "<br>Creating table 'uploader_files'...";
$res  = $sql->query("DROP TABLE IF EXISTS `uploader_files`");
$res2 = $sql->query("CREATE TABLE `uploader_files` (
  `id` mediumint(10) unsigned NOT NULL AUTO_INCREMENT,
  `user` smallint(5) unsigned NOT NULL,
  `cat` int(10) unsigned NOT NULL,
  `filename` varchar(255) NOT NULL,
  `description` text,
  `hash` varchar(120) NOT NULL,
  `private` tinyint(1) NOT NULL DEFAULT '0',
  `date` int(10) unsigned NOT NULL,
  `lastedituser` smallint(5) unsigned NOT NULL DEFAULT '0',
  `lasteditdate` int(10) unsigned NOT NULL DEFAULT '0',
  `mime` varchar(50) NOT NULL,
  `size` int(10) unsigned NOT NULL,
  `downloads` int(10) unsigned NOT NULL DEFAULT '0',
  `width` smallint(5) unsigned NOT NULL DEFAULT '0',
  `height` smallint(5) unsigned NOT NULL DEFAULT '0',
  `is_image` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `cat` (`cat`),
  KEY `private` (`private`),
  KEY `user` (`user`),
  KEY `filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1");
print checkres($res && $res2);

// ====================================================

print "<br>Creating default file uploader categories...";
$res = $sql->query("INSERT INTO `uploader_cat` VALUES (1,'Generic file storage','Everything and anything',0,0,NULL,0,0,-2,0,2,0,0)");
print checkres($res);

// ====================================================

print "<br>Adding configuration keys...";
$config['allow-uploader']           = false;
$config['uploader-all-origin']      = false;
$config['uploader-max-file-size']   = 2097152 * 5;
$config['uploader-allow-file-edit'] = false;
print checkres(true);
