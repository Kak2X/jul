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

// ====================
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

// ====================
update_step();

print "<br>Moving the 'Mario Movie' scheme...";
$res = $sql->query("UPDATE schemes SET cat = 1 WHERE file = 'mariomovie.php'");

print checkres($res);

// ====================
update_step();

print "<br>Adding the 'Rugged Blue (Yoshi Dude)' scheme...";
$res = add_scheme([
	'id'       => 54,
	'ord'      => 54,
	'name'     => 'Rugged Blue (Yoshi Dude)',
	'file'     => 'ruggedb.php',
	'special'  => 0,
	'cat'      => 8,
	'minpower' => 0,
]);

print checkres($res);

// ====================
update_step();

print "<br>Renaming various other schemes...";

$ren = $sql->prepare("UPDATE schemes SET name = :new WHERE id = :id AND name = :old");
// Jul updated the scheme's name some time after 2016
$sql->execute($ren, ['id' => 10, 'old' => "The Horrible Forced Scheme", 'new' => "Card Captor Sakura"]);
// True names not known at the time
$sql->execute($ren, ['id' => 152, 'old' => "Aceboard", 'new' => "Acmlmboard 1.B"]);
$sql->execute($ren, ['id' => 158, 'old' => "FF9", 'new' => "FF9 (Sir Elric)"]);
$sql->execute($ren, ['id' => 178, 'old' => "Yoshi", 'new' => "Yoshi's Island"]);
$sql->execute($ren, ['id' => 175, 'old' => "Twilight", 'new' => "Twilight Princess"]);

print checkres($res);
