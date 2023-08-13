<?php

require "lib/common.php";

pageheader("Forum Moderators");

admincheck();
print adminlinkbar('admin-editmods.php');

if (isset($_POST['action'])) {
	check_token($_POST['auth']);
//  print "DEBUG: Asked to ".$action." a moderator of forum: ".${$action."modforum"}." and user: ".${$action."moduser"};
	switch($_POST['action']) {
		case "Remove Moderator":
			$removemod = filter_string($_POST['removemod']);
			$removemod 		= explode("|", $removemod);
			$removemoduser 	= filter_int($removemod[1]);
			$removemodforum = filter_int($removemod[0]);
			
			if (!$removemoduser || !$removemodforum)
				errorpage("Invalid options sent.");

			$sql->query("DELETE FROM forummods WHERE user = $removemoduser AND forum = $removemodforum");
			errorpage("You successfully deleted user $removemoduser from forum $removemodforum.","admin-editmods.php",'go back to Edit Mods',0);
		case "Add Moderator":
			$forum 	= filter_int($_POST['addmodforum']);
			$user 	= filter_int($_POST['addmoduser']);
			if (!$forum || !$user)
				errorpage("Invalid request.");
			
			$sql->query("INSERT INTO forummods VALUES ($forum, $user)");
			errorpage("You successfully added user $user to forum $forum.","admin-editmods.php",'go back to Edit Mods',0);
		default:
			errorpage("No, doofus.");
	}
} else {
	$forums = $sql->query("SELECT id, title, description, catid FROM forums ORDER BY catid");
	$fa = "";
	$forumselect 		= "<option value=\"0\">Select a forum...</option>\r\n";
	$forumselectforrem 	= "<option value=\"0|0\">Select a forum and moderator...</option>\r\n";
	// Create a list of local mods for each forum (to remove / view)
	while ($forum = $sql->fetch($forums)) {
		$m = 0;
		$modlist = "";
		$forumselect .= "<option value=\"{$forum['id']}\">".htmlspecialchars($forum['title'])."</option>";
		$mods = $sql->query("SELECT user FROM forummods WHERE forum = {$forum['id']}");
		while($mod = $sql->fetch($mods)) {
			$usermod = $sql->fetchq("SELECT $userfields FROM users u WHERE u.id = {$mod['user']}");
			$modlist .= ($m++ ? ", " : "") . getuserlink($usermod);
			$forumselectforrem .= "<option value=\"{$forum['id']}|{$usermod['id']}\">".htmlspecialchars($forum['title'])." -- {$usermod['name']}</option>\r\n";
		}
		if ($m) {
			$fa .= "
			<tr>
				<td class='tdbg2 center fonts'>{$forum['id']}</td>
				<td class='tdbg1 center fonts'>".htmlspecialchars($forum['title'])."</td>
				<td colspan=3 class='tdbg2 fonts'>$modlist</td>
			</tr>";
		}
	}

?>

<table class='table'>
	<tr>
		<td class='tbl tdbgh center fonts' width=50>ID</td>
		<td class='tbl tdbgh center fonts' width=30%>Forum Name</td>
		<td class='tbl tdbgh center fonts' width=65%>Moderators</td>
	</tr>
	<?=$fa?>
</table>

<form action="admin-editmods.php" method="POST">
<?= auth_tag() ?>
<br>
<table class='table'>
	<tr><td class='tdbgh center' colspan="2">Add Moderator:</td></tr>
	<tr>
		<td class='tdbg1 center' width=15%>Forum:</td>
		<td class='tdbg2' width=85%>
			<select name="addmodforum" size="1"><?=$forumselect?></select>
		</td>
	</tr> 
	<tr>
		<td class='tdbg1 center' width=15%>User:</td>
		<td class='tdbg2' width=85%>
			<?= user_select('addmoduser', 0, 'powerlevel > '.(isset($_POST['showall']) ? '-1' : '0')) ?>
			<?=(isset($_POST['showall']) ? 
				"<input type='submit' class='submit' name='hidesome' value='Show Normal+ and above'>" : 
				"<span class='fonts'>(note: this only shows Member+ and above)</span> <input type='submit' class='submit' name='showall' value='Show All'>")
			?>
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center' width=15%>&nbsp;</td>
		<td class='tdbg2' width=85%>
			<input type='submit' class=submit name="action" value="Add Moderator">
		</td>
	</tr>
</table>
</form>
<?php

	if ($forumselectforrem) {
		?>
	<form action="admin-editmods.php" method="POST">
	<?= auth_tag() ?>
	<table class='table'>
		<tr><td class='tdbgh center' colspan="2">Remove Moderator:</td></tr>
		<tr>
			<td class='tdbg1 center' width=15%>Forum and Moderator:</td>
			<td class='tdbg2' width=85%>
				<select name="removemod" size="1"><?=$forumselectforrem?></select>
			</td>
		</tr> 
		<tr>
			<td class='tdbg1 center' width=15%>&nbsp;</td>
			<td class='tdbg2' width=85%>
				<input type='submit' class=submit name="action" value="Remove Moderator">
			</td>
		</tr>
	</table>
	</form>
		<?php
	}
}

pagefooter();


/*            <tr>
	 <td class='tdbgh center'><b><font class='fonts'> Delete a mod.</td>
         <td class='tdbgh center'><b><font class='fonts'> Add Moderator.</td>

 </td>
<tr>
	<td class='tdbg1 center'> User ID: <input type=\"text\" name=\"dm_uid\"></td>
<td class='tdbg1 center'>            User ID: <input type=\"text\" name=\"nm_uid\"></td>

<tr>
	<td class='tdbg1 center'> Forum ID: <input type=\"text\" name=\"dm_fid\"></td>
<td class='tdbg1 center'>            Forum ID: <input type=\"text\" name=\"nm_fid\">

<tr>
	    <td class='tdbg1 center'> <input type=\"submit\" name=\"action\" value=\"Delete Mod\"></td>
<td class='tdbg1 center'>            <input type=\"submit\" name=\"action\" value=\"Add Mod\">*/

/*            <tr>
	 <td class='tdbgh center'><b><font class='fonts'> Delete a mod.</td>
         <td class='tdbgh center'><b><font class='fonts'> Add Moderator.</td>

 </td>
<tr>
	<td class='tdbg1 center'> User ID: <input type=\"text\" name=\"dm_uid\"></td>
<td class='tdbg1 center'>            User ID: <input type=\"text\" name=\"nm_uid\"></td>

<tr>
	<td class='tdbg1 center'> Forum ID: <input type=\"text\" name=\"dm_fid\"></td>
<td class='tdbg1 center'>            Forum ID: <input type=\"text\" name=\"nm_fid\">

<tr>
	    <td class='tdbg1 center'> <input type=\"submit\" name=\"action\" value=\"Delete Mod\"></td>
<td class='tdbg1 center'>            <input type=\"submit\" name=\"action\" value=\"Add Mod\">*/

?>
