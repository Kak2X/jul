<?php
// hookya initialization script

hook_add('header-links-2', function() use ($extName, $xconf) {
	return " - <a href='{$extName}/index.php'>".htmlspecialchars($xconf['page-title'])."</a>";
});