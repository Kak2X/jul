<?php

	require "lib/function.php";
	
	admincheck();	
	
	$_GET['id'] 	= filter_int($_GET['id']);
	$_GET['type'] 	= filter_int($_GET['type']);
	$_GET['sort']	= filter_bool($_GET['sort']); // Sort by type (0) or forum (1). will change type column to forum in preview mode
	
	if (isset($_POST['setdel']) && isset($_POST['del'])) {
		check_token($_POST['auth']);
		
		if (is_array($_POST['del'])) {
			$q = $sql->prepare("DELETE FROM filters WHERE id = ?");
			foreach ($_POST['del'] as $delid) {
				$delid = (int) $delid;
				if ($delid > 0) {
					$sql->execute($q, [$delid]);
				}
			}
		}
		header("Location: admin-editfilters.php?type={$_GET['type']}");
		die;		
	} else if (isset($_POST['qdelid'])) {
		check_token($_POST['auth']);
		$delid = filter_int($_POST['qdelid']);
		if ($delid > 0) {
			$sql->query("DELETE FROM filters WHERE id = $delid");
		}
		header("Location: admin-editfilters.php?type={$_GET['type']}");
		die;
	} else if (isset($_POST['edit'])) {
		check_token($_POST['auth']);
		
		$source 		= filter_string($_POST['source']);
		$forum			= filter_int($_POST['forum']);
		if ($forum && !$sql->resultq("SELECT 1 FROM forums WHERE id = $forum")) {
			$forum = 0;
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
			'method'      => numrange(filter_int($_POST['method']), 0, 3),
			'forum'       => $forum,
			'enabled'     => filter_int($_POST['enabled']),
			'type'        => numrange(filter_int($_POST['type']), 1, 7),
		]);
		
		header("Location: admin-editfilters.php?type={$_GET['type']}");
		die;
	}


	
	pageheader("Board Filters");
	
	print adminlinkbar();
	
	const FILTER_TYPES = array(
		1 => "Generic",
		2 => "URLs",
		3 => "HTML/CSS",
		4 => "Bad words",
		5 => "Joke/Idiocy",
		6 => "Hidden Smilies",
		7 => "Security",
	);		

	


?>
<style type="text/css">
	.rh {height: 19px;}
	.va {vertical-align: top}
</style>
<form method="POST" action="?type=<?= $_GET['type'] ?>&id=<?= $_GET['id'] ?>">
<input type="hidden" name="auth" value="<?= generate_token() ?>">
<table class="table">
	<tr>
		<td class="tdbgh center" style="width: 120px">
			<b>Filter types</b>
		</td>
		<td class="tdbgh center" colspan=7>
			<b>Filters<?= ($_GET['type'] ? " - ".FILTER_TYPES[$_GET['type']] : "") ?></b>
		</td>
	</tr>
	<tr class="rh">
		<td class="tdbg1 nobr va" rowspan=999999>
			<a href="?type=1"><?= FILTER_TYPES[1] ?></a><br>
			<a href="?type=2"><?= FILTER_TYPES[2] ?></a><br>
			<a href="?type=3"><?= FILTER_TYPES[3] ?></a><br>
			<a href="?type=4"><?= FILTER_TYPES[4] ?></a><br>			
			<a href="?type=5"><?= FILTER_TYPES[5] ?></a><br>
			<a href="?type=6"><?= FILTER_TYPES[6] ?></a><br>
			<a href="?type=7"><?= FILTER_TYPES[7] ?></a>
		</td>

<?php
			
			
		if (!$_GET['type']) {
?>
		<td class="tdbg1 center rh">Select a filter type.</td>
	</tr>
	<tr><td class="tdbg2 center">&nbsp;</td></tr>

	
<?php
	
		} else {
			
			// Edit / New filter
			if ($_GET['id']) {
				if ($_GET['id'] <= -1) {
					$editAction = "Creating a new filter";
					$sel_method[2] = 'selected';
					$sel_type[$_GET['type']]   = 'selected';
					
					$x = array(
						'enabled'     => 1,
						'source'      => '',
						'replacement' => '',
						'comment'     => '',
						'forum'       => 0,
					);
				} else {
					$editAction = "Editing filter";
					$x = $sql->fetchq("SELECT * FROM filters WHERE id = {$_GET['id']}");
					$sel_method[$x['method']] = 'selected';
					$sel_type[$x['type']]     = 'selected';
					
				}
				
?>
		<td class="tdbgc center" colspan=7><b><?= $editAction ?></b></td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center" rowspan=3 colspan=2><b>Search for:</b></td>
		<td class="tdbg2 va" rowspan=3 colspan=2>
			<textarea wrap=virtual name="source" ROWS=3 maxlength=127 style="width: 100%; resize:vertical"><?= htmlspecialchars($x['source']) ?></textarea>
		</td>
		<td class="tdbgh center" colspan=3><b>Options</b></td>
	</tr>
	
	<tr class="rh">
		<td class="tdbg1" colspan=3>
			<input type="checkbox" id="enabled" name="enabled" value=1 <?= ($x['enabled'] ? "checked" : "") ?>>
			<label for="enabled">Enabled</label>
<?php	if ($_GET['id'] > 0) {	?>	
			<span style="float: right"><input type="checkbox" name="qdelid" value="<?= $x['id'] ?>"> Delete&nbsp;</span>
<?php	} ?>
		</td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center"><b>Forum:</b></td>
		<td class="tdbg1" colspan=2><?= doforumlist((int) $x['forum'], 'forum', '[Global Filter]') ?></td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center" rowspan=3 colspan=2><b>Replace with:</b></td>
		<td class="tdbg2 va" rowspan=3 colspan=2>
			<textarea wrap=virtual name="replacement" ROWS=3 maxlength=127 style="width: 100%; resize:vertical"><?= htmlspecialchars($x['replacement']) ?></textarea>
		</td>
		<td class="tdbgh center"><b>Method:</b></td>
		<td class="tdbg1" colspan=2>
			<select name="method">
				<option value=0 <?= filter_string($sel_method[0]) ?>>Case sensitive replacement</option>
				<option value=1 <?= filter_string($sel_method[1]) ?>>Case insensitive replacement</option>
				<option value=2 <?= filter_string($sel_method[2]) ?>>RegEx pattern</option>
			</select>
		</td>
	</tr>
	<tr class="rh">
		<td class="tdbgh center"><b>Type:</b></td>
		<td class="tdbg1" colspan=2>
			<select name='type'>
				<?php
				for ($i = 1, $m = count(FILTER_TYPES); $i <= $m; ++$i) {
					echo "<option value=$i ".filter_string($sel_type[$i]).">".FILTER_TYPES[$i]."</option>";
				}
				?>
			</select>
		</td>
	</tr>
	<tr class="rh">
		<td class="tdbg2 right" style="vertical-align: bottom" colspan=3>
			<input type="submit" class="submit" name="edit" value="Save Changes">
		</td>
	</tr>
	<tr></tr>
	<tr><td class="tdbg2" colspan=7></td></tr>
	
	<tr class="rh">
		<td class="tdbgh center" colspan=2><b>Comment:</b></td>
		<td class="tdbg2 va" colspan=5>
			<textarea wrap=virtual name="comment" ROWS=1 maxlength=255 style="width: 100%; resize:vertical"><?= htmlspecialchars($x['comment']) ?></textarea>
		</td>
	</tr>
	
	<tr><td class="tdbgh" colspan=7></td></tr>
	<tr class="rh">

<?php	} ?>

		<td class="tdbgc center" style="width: 60px">&nbsp;</td>
		<td class="tdbgc center" style="width: 40px"><b>Set</b></td>
		<td class="tdbgc center" style="width: 350px"><b>Search</b></td>
		<td class="tdbgc center" style="width: 350px"><b>Replacement</b></td>
		<td class="tdbgc center" style="width: 150px"><b>Forum</b></td>
		<td class="tdbgc center" style="width: 230px"><b>Method</b></td>
		<td class="tdbgc center" style="width: 10px"><b>?</b></td>
		
	</tr>
<?php
			// Filter list
			$filters = $sql->query("
				SELECT f.id, f.method, f.enabled, f.source, f.replacement, f.comment, x.title ftitle, x.id fid
				FROM filters f
				LEFT JOIN forums x ON f.forum = x.id
				WHERE f.type = {$_GET['type']}
				ORDER BY f.source ASC
			");
			for ($i = 0; $x = $sql->fetch($filters); ++$i) {	
				#################
?>
	<tr class="rh">
		<td class="tdbg1 center fonts">
			<input type="checkbox" name="del[]" value=<?= $x['id'] ?>> - <a href="?type=<?= $_GET['type'] ?>&id=<?= $x['id'] ?>">Edit</a>
		</td>
			<td class="tdbg1 center"><b><span style=color:<?= ($x['enabled'] ? "#0F0>ON" : "#F00>OFF") ?></b></span>
		</td>
		<td class="tdbg2"><textarea wrap=virtual ROWS=1 style="width: 100%; resize:none" readonly><?= htmlentities($x['source']) ?></textarea></td>
		<td class="tdbg2"><textarea wrap=virtual ROWS=1 style="width: 100%; resize:none" readonly><?= htmlspecialchars($x['replacement']) ?></textarea></td>
		<td class="tdbg1 center"><?= ($x['fid'] ? "<a href='forum.php?id={$x['fid']}'>".htmlspecialchars($x['ftitle'])."</a>" : "Global") ?></td>
		<td class="tdbg1 center"><?= ($x['method'] == 2 ? "RegEx" : "Replace").($x['method'] == 1 ? "<div class='fonts'>Case Insensitive</div>" : "") ?></td>
		<td class="tdbg2 center">
			<?= ($x['comment'] 
			? "<span style='border-bottom: 1px dotted #f00; font-weight: bold' title=\"".str_replace('"', "'", $x['comment'])."\">?</span>"
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
		<td class="tdbgc center" colspan=5><a href="?type=<?= $_GET['type'] ?>&id=-1">&lt; Add a new filter &gt;</a></td>
	</tr>
<?php
		}

		
			?>
		</td>
	</tr>
</table>
</form>
<?php

	pagefooter();
	