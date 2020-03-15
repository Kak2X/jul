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
  <title> wonderful </title>
  <base href="<?= $boardurl ?>/">
 </head>
 <body color="#000" bgcolor="#ffffff">

	<center>
		
		<strong>Error 500</strong>
		<img src="errors/500.png" title="catch hold of server breaking">
		<br><br>Something exploded. <a href="<?= $boardurl ?>/">Try again from the start</a>.
	</center>
  
 </body>
</html>
