<?php
	require "lib/common.php";
  
	$s = readsmilies();
	
	pageheader("Smilies", true, true); // Small header

?>
<table class="tablevc">
	<tr>
		<td>
			<table class='table'>
				<tr>
		<?php
		
	// Display 4 smilies on each row
	foreach($s as $i => $v) {
		if (!($i % 4)) print "<tr></tr>";	
		if ($v) print "<td class='tdbg1 center'><img src=\"". escape_attribute($v[1]) ."\"></td><td class='tdbg2 center'>". htmlspecialchars($v[0]) ."</td>";
	}



/*"   <tr>
	<td class='tdbg1 center'><img src=images/smilies/smile.gif></td>
    <td class='tdbg1 center'>:)</td>
	<td class='tdbg1 center'><img src=images/smilies/wink.gif></td>
    <td class='tdbg1 center'>;)</td>
	<td class='tdbg1 center'><img src=images/smilies/biggrin.gif></td>
    <td class='tdbg1 center'>:D</td>
	<td class='tdbg1 center'><img src=images/smilies/lol.gif></td>
    <td class='tdbg1 center'>:LOL:</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies/glasses.gif></td>
    <td class='tdbg1 center'>8-)</td>
	<td class='tdbg1 center'><img src=images/smilies/frown.gif></td>
    <td class='tdbg1 center'>:(</td>
	<td class='tdbg1 center'><img src=images/smilies/mad.gif></td>
    <td class='tdbg1 center'>>:</td>
	<td class='tdbg1 center'><img src=images/smilies/yuck.gif></td>
    <td class='tdbg1 center'>>_<</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies/tongue.gif></td>
    <td class='tdbg1 center'>:P</td>
	<td class='tdbg1 center'><img src=images/smilies/wobbly.gif></td>
    <td class='tdbg1 center'>:S</td>
	<td class='tdbg1 center'><img src=images/smilies/eek.gif></td>
    <td class='tdbg1 center'>O_O</td>
	<td class='tdbg1 center'><img src=images/smilies/bigeyes.gif></td>
    <td class='tdbg1 center'>o_O</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies/bigeyes2.gif></td>
    <td class='tdbg1 center'>O_o</td>
	<td class='tdbg1 center'><img src=images/smilies/cute.gif></td>
    <td class='tdbg1 center'>^_^</td>
	<td class='tdbg1 center'><img src=images/smilies/cute2.gif></td>
    <td class='tdbg1 center'>^^;;;</td>
	<td class='tdbg1 center'><img src=images/smilies/baby.gif></td>
    <td class='tdbg1 center'>~:o</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies/sick.gif></td>
    <td class='tdbg1 center'>x_x</td>
	<td class='tdbg1 center'><img src=images/smilies/eyeshift.gif></td>
    <td class='tdbg1 center'>:eyeshift:</td>
	<td class='tdbg1 center'><img src=images/smilies/vamp.gif></td>
    <td class='tdbg1 center'>:vamp:</td>
	<td class='tdbg1 center'><img src=images/smilies/blank.gif></td>
    <td class='tdbg1 center'>o_o</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies/cry.gif></td>
    <td class='tdbg1 center'>;_;</td>
	<td class='tdbg1 center'><img src=images/smilies/dizzy.gif></td>
    <td class='tdbg1 center'>@_@</td>
	<td class='tdbg1 center'><img src=images/smilies/annoyed.gif></td>
    <td class='tdbg1 center'>-_-</td>
	<td class='tdbg1 center'><img src=images/smilies/shiftright.gif></td>
    <td class='tdbg1 center'>>_></td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies/shiftleft.gif></td>
    <td class='tdbg1 center'><_<</td>
	<td class='tdbg1 center'><img src=images/smilies/rofl.gif></td>
    <td class='tdbg1 center'>:rofl:</td>
	<td class='tdbg1 center'><img src=images/smilies/terror.gif></td>
    <td class='tdbg1 center'>:terror:</td>
	<td class='tdbg1 center'><img src=images/smilies/approved.gif></td>
    <td class='tdbg1 center'>:approve:</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies/denied.gif></td>
    <td class='tdbg1 center'>:deny:</td>
	<td class='tdbg1 center'><img src=images/smilies/eyeshift2.gif></td>
    <td class='tdbg1 center'>:eyeshift2:</td>
	<td class='tdbg1 center'><img src=images/smilies/meow.gif></td>
    <td class='tdbg1 center'>:meow:</td>
   </tr>
*/
?>				</tr>
			</table>
		</td>
	</tr>
</table>