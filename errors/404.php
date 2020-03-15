<?php
	// Viewing the page directly?
	if (!isset($root)) {
		require "../lib/routing.php";
		fetch_root($root, $boardurl);
		$boardurl .= "/..";
	}
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
 <head>
  <title> where'd I put that damn thing </title>
  <base href="<?= $boardurl ?>/">
 </head>
 <body bgcolor="#ffffff">

	<center>
		<img src="errors/404.png" title="I swear I put that secret room switch around here SOMEWHERE.">
		<br><br>Whatever you were looking for isn't here.
		<br><!-- <a href="mailto:xkeeper+404@gmail.com">Let us know</a> or --><a href="<?= $boardurl ?>/">go back to the start</a>.
	</center>
  
 </body>
</html>
