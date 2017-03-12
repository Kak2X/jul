<?php

	require "lib/function.php";
	
	if (!$config['allow-custom-forums']) {
		return header("Location: index.php");
	}
	
	if (!has_perm('create-custom-forums')) {
		errorpage("Sorry, but you aren't allowed to manage custom forums.");
	}
	
	
	$_GET['id'] = filter_int($_GET['id']); // Selected filter
	$_GET['f'] 	= filter_int($_GET['f']); // Selected forum
	$_GET['u'] 	= filter_int($_GET['u']); // Change user selection
	
	$isadmin = has_perm('forum-admin');
	if ($isadmin && $_GET['u']) {
		$user = $sql->fetchq("SELECT * FROM users WHERE id = {$_GET['u']}");
		if (!$user) {
			errorpage("This user doesn't exist.");
		}
		$uparam = "&u={$_GET['u']}";
	} else {
		$user = $loguser;
		$uparam = "";
	}
	
	$forumlist 	= $sql->getresultsbykey("SELECT id, title FROM forums WHERE custom = 1 AND user = {$user['id']}");
	$allowed 	= " AND f.custom = 1 AND f.user = {$user['id']}";
	
	if (isset($_POST['setdel']) && isset($_POST['del'])) {
		check_token($_POST['auth']);
		
		if (is_array($_POST['del'])) {
			$q = $sql->prepare("
				DELETE x.* FROM filters x 
				LEFT JOIN forums f ON x.forum = f.id 
				WHERE x.id = ?{$allowed}
			");
			foreach ($_POST['del'] as $delid) {
				$delid = (int) $delid;
				if ($delid > 0) {
					$sql->execute($q, [$delid]);
				}
			}
		}
		return header("Location: editcustomfilters.php?f={$_GET['f']}{$uparam}");	
	} else if (isset($_POST['qdelid'])) {
		check_token($_POST['auth']);
		$delid = filter_int($_POST['qdelid']);
		if ($delid > 0) {
			$sql->query("
				DELETE x.* FROM filters x 
				LEFT JOIN forums f ON x.forum = f.id 
				WHERE x.id = $delid{$allowed}
			");
		}
		return header("Location: editcustomfilters.php?f={$_GET['f']}{$uparam}");
	} else if (isset($_POST['edit'])) {
		check_token($_POST['auth']);
		
		$source 		= filter_string($_POST['source']);
		$forum			= filter_int($_POST['forum']);
		if (!$forum || !$sql->resultq("SELECT 1 FROM forums f WHERE f.id = {$forum}{$allowed}")) {
			errorpage("No valid forum specified.");
		}
		if ($_GET['id'] > -1 && !$isadmin && !$sql->resultq("SELECT 1 FROM filters x LEFT JOIN forums f ON x.forum = f.id WHERE x.id = {$_GET['id']}{$allowed}")) {
			errorpage("You aren't allowed to edit this filter.");
		}
		if (!$source) {
			errorpage("No source string specified.");
		}
		
		if  ($_GET['id'] <= -1) {
			$q = "INSERT INTO filters SET ".$sql->setplaceholders('source','replacement','comment','method','forum','enabled','type');
		} else {
			$q = "UPDATE filters SET ".$sql->setplaceholders('source','replacement','comment','method','forum','enabled','type')." WHERE id = {$_GET['id']}";
		}
		
		$sql->queryp($q, 
		[
			'source'      => $source,
			'replacement' => filter_string($_POST['replacement']),
			'comment'     => filter_string($_POST['comment']),
			'method'      => numrange(filter_int($_POST['method']), 0, 1), // Force case insensitive filter
			'forum'       => $forum,
			'enabled'     => filter_int($_POST['enabled']),
			'type'        => 8,
		]);
		
		return header("Location: editcustomfilters.php?f={$_GET['f']}{$uparam}");
	}


	
	pageheader("{$config['board-name']} -- Forum Filters");

?>
<style type="text/css">
	.rh {height: 19px;}
	.va {vertical-align: top}
</style>
<span class="font"><a href="index.php"><?= $config['board-name'] ?></a> - Forum Filters</span>
<form method="POST" action="?f=<?= $_GET['f'] ?>&id=<?= $_GET['id'] ?><?= $uparam ?>">
<input type="hidden" name="auth" value="<?= generate_token() ?>">
<table class="table">
	<tr>
		<td class="tdbgh center" style="width: 120px">
			<b>Forum Selection</b>
		</td>
		<td class="tdbgh center" colspan=7>
			<b>Filters<?= ($_GET['f'] && isset($forumlist[$_GET['f']]) ? " - ".$forumlist[$_GET['f']] : "") ?></b>
		</td>
	</tr>
	<tr class="rh">
		<td class="tdbg1 nobr va" rowspan=999999>
<?php	foreach ($forumlist as $fid => $ftitle) {
			echo "<a href='?f=$fid{$uparam}'>$ftitle</a><br>";
		}
?>		</td>

<?php
			
			
		if (!$_GET['f']) {
?>
		<td class="tdbg1 center rh">Select a forum.</td>
	</tr>
	<tr><td class="tdbg2 center">&nbsp;</td></tr>
<?php
	
		} else if (isset($forumlist[$_GET['f']])) {
			
			// Edit / New filter
			if ($_GET['id']) {
				// If the filter exists and is valid allow to edit it
				if ($_GET['id'] > 0 && $sql->resultq("SELECT 1 FROM filters x LEFT JOIN forums f ON x.forum = f.id WHERE x.id = {$_GET['id']}{$allowed}")) {
					$editAction = "Editing filter";
					$x = $sql->fetchq("SELECT * FROM filters WHERE id = {$_GET['id']}");
					$sel_method[$x['method']] = 'checked';
					$sel_enabled[$x['enabled']] = 'checked';
				} else {
					$_GET['id'] = -1; // Explicitly set "new filter" status
					$editAction = "Creating a new filter";
					$sel_method[1] = 'checked';
					$sel_enabled[1] = 'checked';
					
					$x = array(
						'source'      => '',
						'replacement' => '',
						'comment'     => '',
						'forum'       => 0,
					);
				}
				
?>
		<td class="tdbgc center" colspan=7><b><?= $editAction ?></b></td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center" colspan=2><b>Search for:</b></td>
		<td class="tdbg2 va" colspan=2>
			<input type="text" class="w" maxlength=127 name="source" value="<?= htmlspecialchars($x['source']) ?>">
		</td>
		<td class="tdbgh center" colspan=3><b>Options</b></td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center" colspan=2><b>Replace with:</b></td>
		<td class="tdbg2 va" colspan=2>
			<input type="text" class="w" maxlength=127 name="replacement" value="<?= htmlspecialchars($x['replacement']) ?>">
		</td>
		<td class="tdbgh center"><b>Enable filter:</b></td>
		<td class="tdbg1" colspan=2>
			<input type='radio' name='enabled' value='0' <?= filter_string($sel_enabled[0]) ?>> No &nbsp; 
			<input type='radio' name='enabled' value='1' <?= filter_string($sel_enabled[1]) ?>> Yes
		</td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center" colspan=2><b>Comment:</b></td>
		<td class="tdbg2 va" colspan=2>
			<input type="text" class="w" maxlength=255 name="comment" value="<?= htmlspecialchars($x['comment']) ?>">
		</td>
		<td class="tdbgh center"><b>Forum:</b></td>
		<td class="tdbg1" colspan=2>
			<select name="forum">
<?php 			foreach ($forumlist as $fid => $ftitle) {
					echo "<option value=$fid ".($x['forum'] == $fid ? "selected" : "").">$ftitle</option>\n\r";
				}
?>			</select>
		</td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center" colspan=2></td>
		<td class="tdbg2 va" colspan=2></td>
		<td class="tdbgh center"><b>Case sensitive:</b></td>
		<td class="tdbg1" colspan=2>
			<input type='radio' name='method' value='1' <?= filter_string($sel_method[1]) ?>> No &nbsp; 
			<input type='radio' name='method' value='0' <?= filter_string($sel_method[0]) ?>> Yes
		</td>
	</tr>

	<tr class="rh">
		<td class="tdbg2 right" colspan=7>
		<?php	if ($_GET['id'] > 0) {	?>	
				<span style="float: left"><input type="checkbox" name="qdelid" value="<?= $x['id'] ?>"> Delete</span> 
	<?php	} ?>
			<input type="submit" class="submit" name="edit" value="Save Changes">
		</td>
	</tr>
	<tr><td class="tdbgh" colspan=7></td></tr>
	<tr class="rh">

<?php	} ?>

		<td class="tdbgc center" style="width: 60px">&nbsp;</td>
		<td class="tdbgc center" style="width: 40px"><b>Set</b></td>
		<td class="tdbgc center" style="width: 350px"><b>Search for</b></td>
		<td class="tdbgc center" style="width: 350px"><b>Replace with</b></td>
		<td class="tdbgc center" style="width: 150px"><b>Forum</b></td>
		<td class="tdbgc center" style="width: 120px"><b>Case Sensitive</b></td>
		<td class="tdbgc center" style="width: 10px">&nbsp;</td>
		
	</tr>
<?php
			// Filter list
			$filters = $sql->query("
				SELECT f.id, f.method, f.enabled, f.source, f.replacement, f.comment, x.title ftitle, x.id fid
				FROM filters f
				LEFT JOIN forums x ON f.forum = x.id
				WHERE f.forum = {$_GET['f']}
				ORDER BY f.source ASC
			");
			for ($i = 0; $x = $sql->fetch($filters); ++$i) {	
				#################
?>
	<tr class="rh">
		<td class="tdbg1 center fonts">
			<input type="checkbox" name="del[]" value=<?= $x['id'] ?>> - <a href="?f=<?= $_GET['f'] ?>&id=<?= $x['id'] ?><?=$uparam?>">Edit</a>
		</td>
			<td class="tdbg1 center"><b><span style=color:<?= ($x['enabled'] ? "#0F0>ON" : "#F00>OFF") ?></b></span>
		</td>
		<td class="tdbg2"><input type="text" class="w" readonly value="<?= htmlspecialchars($x['source']) ?>"></td>
		<td class="tdbg2"><input type="text" class="w" readonly value="<?= htmlspecialchars($x['replacement']) ?>"></td>
		<td class="tdbg1 center"><a href='forum.php?id=<?= $x['fid'] ?>'><?= htmlspecialchars($x['ftitle'])  ?></a></td>
		<td class="tdbg1 center"><?= ($x['method'] == 1 ? "No" : "Yes") ?></td>
		<td class="tdbg2 center">
			<?= ($x['comment'] 
			? "<span style='border-bottom: 1px dotted #f00; font-weight: bold' title=\"".str_replace('"', "'", $x['comment'])."\"><b>*</span>"
			: "&nbsp;") ?>
		</td>
	</tr>
<?php
				################
			}
?>
	<tr><td class="tdbg2 center" colspan=7>&nbsp;</td></tr>

	<tr class="rh">
		<td class="tdbgc" style="border-right: 0" colspan=2><input type="submit" style="height: 16px; font-size: 10px" name="setdel" value="Delete Selected"></td>
		<td class="tdbgc center" colspan=5><a href="?f=<?= $_GET['f'] ?>&id=-1<?= $uparam ?>">&lt; Add a new filter &gt;</a></td>
	</tr>
<?php
		} else {
?>
	<td class="tdbg1 center rh">You have selected a bad forum.</td>
	</tr>
	<tr><td class="tdbg2 center">&nbsp;</td></tr>
<?php			
		}

		
			?>
		</td>
	</tr>
</table>
</form>
<?php

	pagefooter();
	