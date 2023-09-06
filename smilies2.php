<?php
	require "lib/common.php";
  
	$s = readsmilies('smilies2.dat'); // Ok whatever
	
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
	
/*
   <tr>
	<td class='tdbg1 center'><img src=images/smilies2/smile.gif></td>
    <td class='tdbg1 center'>:)</td>
	<td class='tdbg1 center'><img src=images/smilies2/wink.gif></td>
    <td class='tdbg1 center'>;)</td>
	<td class='tdbg1 center'><img src=images/smilies2/biggrin.gif></td>
    <td class='tdbg1 center'>:D</td>
	<td class='tdbg1 center'><img src=images/smilies2/lol.gif></td>
    <td class='tdbg1 center'>:LOL:</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/glasses.gif></td>
    <td class='tdbg1 center'>8-)</td>
	<td class='tdbg1 center'><img src=images/smilies2/frown.gif></td>
    <td class='tdbg1 center'>:(</td>
	<td class='tdbg1 center'><img src=images/smilies2/mad.gif></td>
    <td class='tdbg1 center'>>:</td>
	<td class='tdbg1 center'><img src=images/smilies2/yuck.gif></td>
    <td class='tdbg1 center'>>_<</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/tongue.gif></td>
    <td class='tdbg1 center'>:P</td>
	<td class='tdbg1 center'><img src=images/smilies2/wobbly.gif></td>
    <td class='tdbg1 center'>:S</td>
	<td class='tdbg1 center'><img src=images/smilies2/eek.gif></td>
    <td class='tdbg1 center'>O_O</td>
	<td class='tdbg1 center'><img src=images/smilies2/bigeyes.gif></td>
    <td class='tdbg1 center'>o_O</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/bigeyes2.gif></td>
    <td class='tdbg1 center'>O_o</td>
	<td class='tdbg1 center'><img src=images/smilies2/cute.gif></td>
    <td class='tdbg1 center'>^_^</td>
	<td class='tdbg1 center'><img src=images/smilies2/cute2.gif></td>
    <td class='tdbg1 center'>^^;;;</td>
	<td class='tdbg1 center'><img src=images/smilies2/baby.gif></td>
    <td class='tdbg1 center'>~:o</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/sick.gif></td>
    <td class='tdbg1 center'>x_x</td>
	<td class='tdbg1 center'><img src=images/smilies2/eyeshift.gif></td>
    <td class='tdbg1 center'>:eyeshift:</td>
	<td class='tdbg1 center'><img src=images/smilies2/vamp.gif></td>
    <td class='tdbg1 center'>:vamp:</td>
	<td class='tdbg1 center'><img src=images/smilies2/blank.gif></td>
    <td class='tdbg1 center'>o_o</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/cry.gif></td>
    <td class='tdbg1 center'>;_;</td>
	<td class='tdbg1 center'><img src=images/smilies2/dizzy.gif></td>
    <td class='tdbg1 center'>@_@</td>
	<td class='tdbg1 center'><img src=images/smilies2/annoyed.gif></td>
    <td class='tdbg1 center'>-_-</td>
	<td class='tdbg1 center'><img src=images/smilies2/shiftright.gif></td>
    <td class='tdbg1 center'>>_></td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/shiftleft.gif></td>
    <td class='tdbg1 center'><_<</td>
	<td class='tdbg1 center'><img src=images/smilies2/rofl.gif></td>
    <td class='tdbg1 center'>:rofl:</td>
	<td class='tdbg1 center'><img src=images/smilies2/terror.gif></td>
    <td class='tdbg1 center'>:terror:</td>
	<td class='tdbg1 center'><img src=images/smilies2/approved.gif></td>
    <td class='tdbg1 center'>:approve:</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/denied.gif></td>
    <td class='tdbg1 center'>:deny:</td>
	<td class='tdbg1 center'><img src=images/smilies2/eyeshift2.gif></td>
    <td class='tdbg1 center'>:eyeshift2:</td>
	<td class='tdbg1 center'><img src=images/smilies2/meow.gif></td>
    <td class='tdbg1 center'>:meow:</td>
	<td class='tdbg1 center'><img src=images/smilies2/haw.gif></td>
    <td class='tdbg1 center'>:XD:</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/haw2.gif></td>
    <td class='tdbg1 center'>:gutbust:</td>
	<td class='tdbg1 center'><img src=images/smilies2/grin.gif></td>
    <td class='tdbg1 center'>:grin:</td>
	<td class='tdbg1 center'><img src=images/smilies2/duh.gif></td>
    <td class='tdbg1 center'>:duh:</td>
	<td class='tdbg1 center'><img src=images/smilies2/rolleyes.gif></td>
    <td class='tdbg1 center'>:roll:</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/evil.gif></td>
    <td class='tdbg1 center'>:evil:</td>
	<td class='tdbg1 center'><img src=images/smilies2/halo.gif></td>
    <td class='tdbg1 center'>:good:</td>
	<td class='tdbg1 center'><img src=images/smilies2/anime.gif></td>
    <td class='tdbg1 center'>:kawaii:</td>
	<td class='tdbg1 center'><img src=images/smilies2/anime2.gif></td>
    <td class='tdbg1 center'>;D</td>
   </tr><tr>
	<td class='tdbg1 center'><img src=images/smilies2/flush.gif></td>
    <td class='tdbg1 center'>:flush:</td>
	<td class='tdbg1 center'><img src=images/smilies2/krunk.gif></td>
    <td class='tdbg1 center'>:krunk:</td>
   </tr>
*/

?>				</tr>
			</table>
		</td>
	</tr>
</table>