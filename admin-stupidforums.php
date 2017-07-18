<?php
	
	require "lib/function.php";
	
	admincheck();
	
	pageheader("retarded forum owners");
	print adminlinkbar('admin-stupidforums');
	
	const GREEN_LIGHT = "0F0";
	const RED_LIGHT = "F00";
	
	
?>

	<table class="table">
		<tr><td class="tdbgh center b" colspan=7>...</td></tr>
		<tr>
			<td class="tdbg2 center" colspan=7>
				this list contains forum owners who are stupid enough to think they can be safe from the administration just by <i>denying</i> any kind of access to staff<br/>
				feel free to mock them for their idiocy<br/>
				<br/>
				<div class='fonts'>
					in case it isn't obvious, <span style="color: <?= RED_LIGHT ?>">RED</span> means disabled while with the <span style="color: #<?= GREEN_LIGHT ?>">GREEN</span> it's not
				</div>
			</td>
		</tr>
		
		<tr>
			<td class="tdbgc center b" style="width: 30px">#</td>
			<td class="tdbgc center b">Name</td>
			<td class="tdbgc center b">Owner</td>
			<td class="tdbgc center b">Hidden?</td>
			<td class="tdbgc center b">Denies <?= $grouplist[GROUP_MOD]['name'] ?>?</td>
			<td class="tdbgc center b">Denies <?= $grouplist[GROUP_ADMIN]['name'] ?>?</td>
			<td class="tdbgc center b">Denies <?= $grouplist[GROUP_SYSADMIN]['name'] ?>?</td>
		</tr>
			
	<?php
	$forums = $sql->query("
		SELECT  f.id, f.title, f.description, f.user, f.hidden,
		        pf.group".GROUP_MOD." modperm, pf.group".GROUP_ADMIN." adminperm, pf.group".GROUP_SYSADMIN." rootperm,
		        $userfields uid 
		FROM forums f 
		LEFT JOIN perm_forums pf ON f.id   = pf.id
		LEFT JOIN users        u ON f.user = u.id
		WHERE f.custom = 1 AND (
			pf.group".GROUP_MOD."      != ".PERM_FORUM_ALL." OR
			pf.group".GROUP_ADMIN."    != ".PERM_FORUM_ALL." OR
			pf.group".GROUP_SYSADMIN." != ".PERM_FORUM_ALL."
		)
	");
		
		
	while ($x = $sql->fetch($forums)) {
		?>
		<tr>
			<td class="tdbg2 center"><?= $x['id'] ?></td>
			<td class="tdbg1">
				<a href="forum.php?id=<?= $x['id'] ?>"><?= $x['title'] ?></a>
				<div class="fonts"><?= $x['description'] ?></div>
			</td>
			<td class="tdbg2 center"><?= getuserlink($x, $x['uid']) ?></td>
			<td class="tdbg1 center"><?= ($x['hidden'] ? "Yes" : "No") ?> </td>
			<td class="tdbg2 center b"><?= block_lights($x['modperm']) ?></td>
			<td class="tdbg2 center b"><?= block_lights($x['adminperm']) ?></td>
			<td class="tdbg2 center b"><?= block_lights($x['rootperm']) ?></td>
		</tr>
		<?php
	}
	?>
	</table>
	<?php

	pagefooter();
	
	
	function block_lights($value) {
		if ($value == PERM_FORUM_ALL) {
			return "<span style='color:#" . GREEN_LIGHT . "'>NO</span>";
		} else {
			return 
				"<span style='color:#" . RED_LIGHT . "'>YES</span>"
				."<div class='fonts right b'>"
				."<span style='color:#" . ($value & PERM_FORUM_READ   ? GREEN_LIGHT : RED_LIGHT) . "'>R</span>"
				."<span style='color:#" . ($value & PERM_FORUM_POST   ? GREEN_LIGHT : RED_LIGHT) . "'>P</span>"
				."<span style='color:#" . ($value & PERM_FORUM_EDIT   ? GREEN_LIGHT : RED_LIGHT) . "'>E</span>"
				."<span style='color:#" . ($value & PERM_FORUM_DELETE ? GREEN_LIGHT : RED_LIGHT) . "'>D</span>"
				."<span style='color:#" . ($value & PERM_FORUM_THREAD ? GREEN_LIGHT : RED_LIGHT) . "'>T</span>"
				."<span style='color:#" . ($value & PERM_FORUM_MOD    ? GREEN_LIGHT : RED_LIGHT) . "'>M</span>"
				."</div>";
		}
	}