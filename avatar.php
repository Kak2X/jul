<?php

// NoJS user switch
if (isset($_GET['go'])) {
	if (!isset($_GET['usel'])) $_GET['usel'] = 0;
	return header("Location: avatar.php?id={$_GET['usel']}");
}

require 'lib/function.php';

pageheader("Mood Avatar Preview", NULL, NULL, true); // Small header

$_GET['id'] = filter_int($_GET['id']);
$a	= array(1 => "neutral", "angry", "tired/upset", "playful", "doom", "delight", "guru", "hope", "puzzled", "whatever", "hyperactive", "sadness", "bleh", "embarrassed", "amused", "afraid");

$me = false;

$options = '';
$users = $sql->query("SELECT id, name, moodurl FROM users WHERE moodurl != '' ORDER BY id ASC");
while ($u = $sql->fetch($users)) {
  $selected = $fails = '';
  if ($u['id'] == $_GET['id']) {
    $me = $u;
    $selected = ' selected';
  }
  //if (strpos($u['moodurl'], '$') === FALSE)
  //  $fails = " (improper URL)";
  $options .= "\r\n  <option value='{$u['id']}'$selected>{$u['id']}: {$u['name']}$fails</option>";
}

if ($me) {
	?>
	<script type="text/javascript">
		function avatarpreview(uid,pic) {
			if (pic > 0) {
						var moodav="<?=htmlspecialchars($me['moodurl'])?>";
						document.getElementById('prev').src=moodav.replace("$", pic);
			}
			else {
				document.getElementById('prev').src="images/_.gif";
			}
		}
	</script>
	<noscript><style type="text/css">.hideme{display: none}</noscript></style>
	<?php

	$ret = "<tr><td class='tdbgh center' colspan=2>{$me['name']}: <i>".htmlspecialchars($me['moodurl'])."</i></td></tr>";
	$ret .= "<tr height=400px><td class='tdbg1' width=200px><b>Mood avatar list:</b><br>";

	foreach($a as $num => $name) {
		$jsclick = "onclick='avatarpreview({$me['id']},$num)'";
		$selected = (($num == 1) ? ' checked' : '');
		$ret .= "<span class='hideme'><input type='radio' name='moodid' value='$num' id='mood$num' tabindex='". (9000 + $num) ."' style=\"height: 12px;\" $jsclick $selected>
             <label for='mood$num' style=\"font-size: 12px;\">&nbsp;$num:&nbsp;$name</label></span>
			 <noscript>&nbsp;$num:&nbsp;<a href='?id={$_GET['id']}&amp;n=$num'>$name</a></noscript><br>\r\n";
	}
	$startnum = filter_int($_GET['n']);
	if (!$startnum) {
		$startnum = '1';
	}
	$startimg = htmlspecialchars(str_replace('$', $startnum, $me['moodurl']));

  $ret .= "</td><td class='tdbg2 center' width=400px><img src=\"$startimg\" id=prev></td></tr>";

}
else {
	$ret = '';
}


?>
<center>
<table height=100% valign=middle>
	<tr>
		<td>
		
			<table class='table'>
				<tr height=50px>
					<td class='tdbgh center' colspan=2>
						<b>Preview mood avatar for user...</b>
						<br>
						<form>
							<select name="usel" onChange="parent.location='avatar.php?id='+this.options[this.selectedIndex].value" style="width:500px;">
								<option value=0>&lt;Select a user&gt;</option>
								<?=$options?>
							</select>
							<noscript><input type="submit" name="go" value="Go"></noscript>
						</form>
					</td>
				</tr>
				<?=$ret?>
			</table>
			
		</td>
	<tr>
</table>
</center>
</body>
</html>
<?php
pagefooter(); // DEBUG