<?php

update_step();

print "<br>Adding the 'System' scheme...";
$res = add_scheme([
	'id'       => 52,
	'ord'      => 52,
	'name'     => 'System',
	'file'     => 'system.php',
	'special'  => 0,
	'cat'      => 8,
	'minpower' => 0,
]);

print checkres($res);

update_step();

print "<br>Adding the 'System (Windows 98)' scheme...";
$res = add_scheme([
	'id'       => 53,
	'ord'      => 53,
	'name'     => 'System (Windows 98)',
	'file'     => 'system-w98.php',
	'special'  => 0,
	'cat'      => 8,
	'minpower' => 0,
]);

print checkres($res);

update_step();

print "<br>Moving the 'Mario Movie' scheme...";
$res = $sql->query("UPDATE schemes SET cat = 1 WHERE file = 'mariomovie.php'");
print checkres($res);
