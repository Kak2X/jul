<?php
// hookya initialization script

add_hook('header-links-2', function() {
	return " - <a href='News/index.php'>News</a>";
	//Header::AddLink("<a href='index.php'><i>News</i></a>");
});