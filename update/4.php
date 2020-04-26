<?php

update_step();

print "<br>Migrating configuration for 'news'...";
$extConfig['news'] = [
	'page-title'                   => $config['news-name'],
	'page-header'                  => $config['news-title'],
	'show-special-header'          => true,
	'header-links'                 => "<a href='index.php'>board</a> - \n<a href='/'>the index</a>",
	'max-preview-length'           => $config['max-preview-length'],
	'write-perm'                   => $config['news-write-perm'],
	'admin-perm'                   => $config['news-admin-perm'],
];
$badkeys = ['enable-news', 'news-name', 'news-title', 'max-preview-length', 'news-write-perm', 'news-admin-perm'];
foreach ($badkeys as $x)
	unset($config[$x]);

print checkres(true);

// ====================
update_step();

print "<br>Migrating configuration for 'uploader'...";

$extConfig['uploader'] = [
	'all-origin'          => $config['uploader-all-origin'],
	'max-file-size'       => $config['uploader-max-file-size'],
	'allow-file-edit'     => $config['uploader-allow-file-edit'],
];
$badkeys = ['allow-uploader', 'uploader-all-origin', 'uploader-max-file-size', 'uploader-allow-file-edit'];
foreach ($badkeys as $x)
	unset($config[$x]);

print checkres(true);

// ====================

update_step();


print "<br>Adding mood avatar field to news posts...";
$res  = $sql->query("ALTER TABLE `news` ADD `moodid` tinyint(3) NOT NULL DEFAULT '0'");
print checkres($res);

print "<br>Adding mood avatar field to news items...";
$res  = $sql->query("ALTER TABLE `news_comments` ADD `moodid` tinyint(3) NOT NULL DEFAULT '0'");
print checkres($res);

update_step();