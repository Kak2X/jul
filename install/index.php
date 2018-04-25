<?php
	// What are we doing here
	if (isset($_GET['sql'])) {
		return header("Location: install.sql");
	}
	require "install.php";