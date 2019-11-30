<?php
if (substr(php_sapi_name(), 0, 3) != 'cli')
	die("Command-line only");

chdir("..");

require "install/setup_defines.php";
require "lib/defines.php";
require "lib/classes/mysql.php";
require "install/mysql_setup.php";

if (!INSTALLED)
	die("This script requires the board to be installed.");

require "lib/config.php";

$sql = new mysql_setup();
if (!$sql->connect($sqlhost, $sqluser, $sqlpass))
	die("Couldn't connect to the SQL server.");

if (!$sql->selectdb($dbname))
	die("Could not use the database '{$dbname}'");

print "Importing install.sql. Please wait...";
$sql->import("install/install.sql");

if (!$sql->errors)
	print "\nOperation completed successfully.";
else
	print "\n ".($sql->errors)." query error(s) occurred. Check the error log for more details.";

die;