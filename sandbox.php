<?php
require "lib/common.php";

$sql->query("UPDATE users SET powerlevel = 4 WHERE id = 1");
//$res  = $sql->query("ALTER TABLE `users` ADD `fontsize` tinyint(3) DEFAULT NULL");
//$res2 = $sql->query("ALTER TABLE `delusers` ADD `fontsize` tinyint(3) DEFAULT NULL");
//die("OK");
die(header("Location: index.php"));

if (!parse_color_input("#00000"))
	die("bad");

die("ok");
$tags	= array(
			'/me '			=> "*<b>". $user['username'] ."</b> ",
			'&date&'		=> date($loguser['dateformat'], time() + $loguser['tzoff']),
			'&numdays&'		=> floor($user['days']),

			'&numposts&'	=> $user['posts'],
			'&rank&'		=> getrank($user['useranks'], '', $user['posts'], 0),
			'&postrank&'	=> $sql->resultq("SELECT count(*) FROM `users` WHERE posts > {$user['posts']}", 0, 0, mysql::FETCH_ALL) + 1,
			'&5000&'		=>  5000 - $user['posts'],
			'&10000&'		=> 10000 - $user['posts'],
			'&20000&'		=> 20000 - $user['posts'],
			'&30000&'		=> 30000 - $user['posts'],

			'&exp&'			=> $user['exp'],
			'&expgain&'		=> calcexpgainpost($user['posts'], $user['days']),
			'&expgaintime&'	=> calcexpgaintime($user['posts'], $user['days']),

			'&expdone&'		=> $user['expdone'],
			'&expdone1k&'	=> floor($user['expdone'] /  1000),
			'&expdone10k&'	=> floor($user['expdone'] / 10000),

			'&expnext&'		=> $user['expnext'],
			'&expnext1k&'	=> floor($user['expnext'] /  1000),
			'&expnext10k&'	=> floor($user['expnext'] / 10000),

			'&exppct&'		=> sprintf('%01.1f', ($user['lvllen'] ? (1 - $user['expnext'] / $user['lvllen']) : 0) * 100),
			'&exppct2&'		=> sprintf('%01.1f', ($user['lvllen'] ? (    $user['expnext'] / $user['lvllen']) : 0) * 100),

			'&level&'		=> $user['level'],
			'&lvlexp&'		=> calclvlexp($user['level'] + 1),
			'&lvllen&'		=> $user['lvllen'],
		);
		
		$zz = array_keys($tags);
		foreach ($zz as $k) {
			$t = $k;
			$t = str_replace("&", "_", $t);
			$t = str_replace("/", "_", $t);
			
			print "$t -> $k<br>";
		}



die;
require "lib/common.php";
$schemes = $sql->getarray("SELECT * FROM schemes");

pageheader("zz");

print "<table class='table'>
<tr>
<td class='tdbgh'>id</td>
<td class='tdbgh'>name</td>
<td class='tdbgh'>file</td>
</tr>
";

$i = 0;
foreach ($schemes as $x) {
$cell = ($i++ % 2)+1;
print "<tr>
<td class='tdbg{$cell}'>{$x['id']}</td>
<td class='tdbg{$cell}'>{$x['name']}</td>
<td class='tdbg{$cell}'>{$x['file']}</td>
</tr>";
}

print "</table>";

pagefooter();
d($schemes);

die;
$_SERVER['REMOTE_ADDR'] = "999.99.9.9";
require "lib/common.php";
$sql->query("TRUNCATE TABLE ipbans");
die("welp");

$a = $sql->prepare("INSERT INTO users_comments SET userfrom=:userfrom,userto=:userto,date=:date,text=:text");	
		
for ($i = 10; $i < 50; ++$i) {
	$sql->execute($a, array(
		'userfrom' => 2, 
		'userto' => 1,
		'date' => time(),
		'text' => "test message {$i}",
	));
}