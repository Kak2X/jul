<?php

	require "lib/function.php";
	
	$_GET['id'] = filter_int($_GET['id']);
	if (!$_GET['id']) {
		errorpage("No forum selected.");
	}
	$forum = $sql->fetchq("SELECT title, user, custom FROM forums WHERE id = {$_GET['id']}");
	$forumperm = get_forum_perm($_GET['id'], $loguser['id'], $loguser['group']);
	if (!has_forum_perm('read', $forumperm)) {
		errorpage("Cannot view the forum access list. Either the forum doesn't exist or you're not allowed to view it.");
	}
	
	$canedit = (has_perm('forum-admin') || ($forum['custom'] && $loguser['id'] == $forum['user']));
	$editpage = (has_perm('forum-admin') ? "admin-editforums" : "editcustomforums");
	
	pageheader();

	// Generic group permissions
?>
	<style type="text/css">.y{color:#0F0}.n{color:#F00}</style>
	<span class="font">
		<a href="index.php"><?=$config['board-name']?></a> -- <a href="forum.php?id=<?=$_GET['id']?>"><?=htmlspecialchars($forum['title'])?></a> - Access list
	</span>
	<table class="table">
		<tr>
			<td class="tdbgh center b" colspan=8>
				Global Permissions<?= ($canedit ? " -- <a href='$editpage.php?id={$_GET['id']}'>Edit</a>" : "") ?>
			</td>
		</tr>
		<tr>
			<td class="tdbgc b" colspan=2>Group</td>
			<td class="tdbgc b">Read Forum</td>
			<td class="tdbgc b">Reply</td>
			<td class="tdbgc b">Edit own post</td>
			<td class="tdbgc b">Delete own posts</td>
			<td class="tdbgc b">Create thread</td>
			<td class="tdbgc b">Moderate forum</td>
		</tr>
<?php	
	$groupperm = $sql->fetchq("SELECT * FROM perm_forums WHERE id = {$_GET['id']}");

	foreach ($grouplist as $i => $x) {
?>	<tr>
			<td class="tdbg1" colspan=2><?= $x['name'] ?></td>
			<td class="tdbg2"><?= ($groupperm['group'.$i] & PERM_FORUM_READ   ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($groupperm['group'.$i] & PERM_FORUM_POST   ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($groupperm['group'.$i] & PERM_FORUM_EDIT   ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($groupperm['group'.$i] & PERM_FORUM_DELETE ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($groupperm['group'.$i] & PERM_FORUM_THREAD ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($groupperm['group'.$i] & PERM_FORUM_MOD    ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
		</tr>
<?php 
	}
?>
		<tr>
			<td class="tdbgc" style="width: 40px; border-right: none"></td>
			<td class="tdbgc" colspan=7></td>
		</tr>
		<tr>
			<td class="tdbgh center b" colspan=8>
				Exceptions to the global permissions
			</td>
		</tr>
		<tr>
			<td class="tdbgc b" colspan=2>User</td>
			<td class="tdbgc b">Read Forum</td>
			<td class="tdbgc b">Reply</td>
			<td class="tdbgc b">Edit own post</td>
			<td class="tdbgc b">Delete own posts</td>
			<td class="tdbgc b">Create thread</td>
			<td class="tdbgc b">Moderate forum</td>
		</tr>
		
		
<?php	
	$userperm = $sql->query("
		SELECT $userfields, p.forum, p.permset 
		FROM perm_forumusers p 
		INNER JOIN users u ON p.user = u.id
		WHERE p.forum = {$_GET['id']}");

	foreach ($userperm as $x) {
?>		<tr>
			<td class="tdbg2 center fonts"><?= ($canedit ? "<a href='admin-editperms.php?mode=1&id={$x['id']}&f={$x['forum']}'>Edit</a>" : "") ?></td>
			<td class="tdbg1"><?= getuserlink($x) ?></td>
			<td class="tdbg2"><?= ($x['permset'] & PERM_FORUM_READ   ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($x['permset'] & PERM_FORUM_POST   ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($x['permset'] & PERM_FORUM_EDIT   ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($x['permset'] & PERM_FORUM_DELETE ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($x['permset'] & PERM_FORUM_THREAD ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
			<td class="tdbg2"><?= ($x['permset'] & PERM_FORUM_MOD    ? "<span class='y'>Y</span>" : "<span class='n'>N</span>") ?></td>
		</tr>
<?php 
	}
?>
	</table>
<?php
	
	
	pagefooter();