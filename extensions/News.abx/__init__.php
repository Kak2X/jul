<?php
// hookya initialization script

add_hook('header-links-2', function() use ($extName, $xconf) {
	return " - <a href='{$extName}/index.php'>".htmlspecialchars($xconf['page-title'])."</a>";
	//Header::AddLink("<a href='index.php'><i>News</i></a>");
});