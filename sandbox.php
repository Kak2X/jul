<?php
die;
require "lib/function.php";


$a = $sql->prepare("INSERT INTO users_comments SET userfrom=:userfrom,userto=:userto,date=:date,text=:text");	
		
for ($i = 10; $i < 50; ++$i) {
	$sql->execute($a, array(
		'userfrom' => 2, 
		'userto' => 1,
		'date' => ctime(),
		'text' => "test message {$i}",
	));
}