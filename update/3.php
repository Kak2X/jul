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
