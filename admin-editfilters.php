<?php

	require "lib/function.php";
	
	admin_check();	
	
	$filter_types = array(
		1 => "Generic",
		2 => "URLs",
		3 => "HTML/CSS",
		4 => "Bad words",
		5 => "Joke/Idiocy",
		6 => "Hidden Smilies",
		7 => "Security",
		//99 => "Test"
	);
	$max_filters = count($filter_types);
	
	$_GET['id']     = filter_int($_GET['id']);
	$_GET['type']   = numrange(filter_int($_GET['type']), 0, $max_filters);
	
	if (isset($_POST['setdel']) && isset($_POST['del'])) {
		// Delete multiple filters (from the overview page)
		check_token($_POST['auth']);
		
		if (is_array($_POST['del'])) {
			$q = $sql->prepare("DELETE FROM filters WHERE id = ?");
			$i = 0;
			foreach ($_POST['del'] as $delid) { 
				$sql->execute($q, [$delid]); // The value is already safe as is, no need for further checks
				++$i;
			}
			msg_holder::set_cookie("{$i} filter".($i != 1 ? 's' : '')." deleted.");
		}
		return header("Location: ?type={$_GET['type']}");	
	} else if (isset($_POST['qdelid'])) {
		// Delete a single filter (from the edit page)
		check_token($_POST['auth']);
		
		$delid = filter_int($_POST['qdelid']);
		if ($delid > 0) {
			$sql->query("DELETE FROM filters WHERE id = {$delid}");
			msg_holder::set_cookie("1 filter deleted.");
		}
		return header("Location: admin-editfilters.php?type={$_GET['type']}");
	} else if (isset($_POST['edit'])) {
		check_token($_POST['auth']);
		
		$source         = filter_string($_POST['source']);
		$forum          = filter_int($_POST['forum']);
		if ($forum && !$sql->resultq("SELECT 1 FROM forums WHERE id = $forum")) {
			$forum = 0;
		}
		if (!$source) {
			errorpage("No source string specified.");
		}
		
		$type = numrange(filter_int($_POST['type']), 1, $max_filters);
		if ($type != $_POST['type']) {
			errorpage("Invalid type selected.");
		}
		
		if  ($_GET['id'] <= -1) {
			$q = "INSERT INTO filters SET ".mysql::setplaceholders('source','replacement','comment','method','forum','enabled','type');
			msg_holder::set_cookie("Added a filter to '{$filter_types[$type]}'.");
		} else {
			$q = "UPDATE filters SET ".mysql::setplaceholders('source','replacement','comment','method','forum','enabled','type')." WHERE id = {$_GET['id']}";
			msg_holder::set_cookie("Edited filter from '{$filter_types[$type]}'.");
		}
		
		$sql->queryp($q, 
		[
			'source'      => $source,
			'replacement' => filter_string($_POST['replacement']),
			'comment'     => filter_string($_POST['comment']),
			'method'      => numrange(filter_int($_POST['method']), 0, 3),
			'forum'       => $forum,
			'enabled'     => filter_int($_POST['enabled']),
			'type'        => $type,
		]);
		
		return header("Location: ?type={$_GET['type']}");
	}


	
	pageheader("Board Filters");
	
	print adminlinkbar();
	print msg_holder::get_message();
	
	// Layout stuff
	$hl = array();
	
	$hl['filter-title'] = ($_GET['type'] ? " - ".$filter_types[$_GET['type']] : "");
	
	$hl['filter-sel'] = "";
	foreach ($filter_types as $i => $filter_name) {
		$hl['filter-sel'] .= "<a href='?type={$i}'>{$filter_name}</a><br>";
	}

?>
<style type="text/css">
	.rh {height: 19px;}
	textarea {display: block}
</style>
<form method="POST" action="?type=<?= $_GET['type'] ?>&id=<?= $_GET['id'] ?>">
<?= auth_tag() ?>

<table class="table">
	<tr>
		<td class="tdbgh center b" style="width: 120px">
			Filter types
		</td>
		<td class="tdbgh center b" colspan=7>
			Filters<?= $hl['filter-title'] ?>
		</td>
	</tr>
	<tr class="rh">
		<td class="tdbg1 nobr vatop" rowspan=999999>
			<?= $hl['filter-sel'] ?>
		</td>

<?php	if (!$_GET['type']) { ?>
		<td class="tdbg1 center rh">Select a filter type.</td>
	</tr>
	<tr><td class="tdbg2 center">&nbsp;</td></tr>
<?php
	
		} else {
			// Edit / New filter
			if ($_GET['id']) {
				//////////////////////////////////////
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
		<td class="tdbgc center b" colspan=7><?= $editAction ?></td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center b" rowspan=3 colspan=2>Search for:</td>
		<td class="tdbg2 vatop" rowspan=3 colspan=2>
			<textarea wrap=virtual name="source" ROWS=3 maxlength=127 style="width: 100%; resize:vertical"><?= 
				htmlspecialchars($x['source']) 
			?></textarea>
		</td>
		<td class="tdbgh center b" colspan=3>Options</td>
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
		<td class="tdbgh center b">Forum:</td>
		<td class="tdbg1" colspan=2><?= doforumlist((int) $x['forum'], 'forum', '[Global Filter]') ?></td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center b" rowspan=3 colspan=2>Replace with:</td>
		<td class="tdbg2 vatop" rowspan=3 colspan=2>
			<textarea wrap=virtual name="replacement" ROWS=3 maxlength=127 style="width: 100%; resize:vertical"><?= htmlspecialchars($x['replacement']) ?></textarea>
		</td>
		<td class="tdbgh center b">Method:</td>
		<td class="tdbg1" colspan=2>
			<select name="method">
				<option value=0 <?= filter_string($sel_method[0]) ?>>Case sensitive replacement</option>
				<option value=1 <?= filter_string($sel_method[1]) ?>>Case insensitive replacement</option>
				<option value=2 <?= filter_string($sel_method[2]) ?>>RegEx pattern</option>
			</select>
		</td>
	</tr>
	<tr class="rh">
		<td class="tdbgh center b">Type:</td>
		<td class="tdbg1" colspan=2>
			<select name='type'>
			<?php for ($i = 1, $m = count($filter_types); $i <= $m; ++$i) { ?>
				<option value="<?= $i ?>" <?= filter_string($sel_type[$i]) ?>> <?= $filter_types[$i] ?></option>
			<?php }	?>
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
		<td class="tdbgh center b" colspan=2>Comment:</td>
		<td class="tdbg2 vatop" colspan=5>
			<textarea wrap=virtual name="comment" ROWS=1 maxlength=255 style="width: 100%; resize:vertical"><?= htmlspecialchars($x['comment']) ?></textarea>
		</td>
	</tr>
	
	<tr><td class="tdbgh" colspan=7></td></tr>
	<tr class="rh">

<?php 			//////////////////////////////////////
			} ?>

		<td class="tdbgc center b" style="width: 60px">&nbsp;</td>
		<td class="tdbgc center b" style="width: 40px">Set</td>
		<td class="tdbgc center b" style="width: 350px">Search</td>
		<td class="tdbgc center b" style="width: 350px">Replacement</td>
		<td class="tdbgc center b" style="width: 150px">Forum</td>
		<td class="tdbgc center b" style="width: 230px">Method</td>
		<td class="tdbgc center b" style="width: 10px">?</td>
		
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
			<td class="tdbg1 center b"><span style=color:<?= ($x['enabled'] ? "#0F0>ON" : "#F00>OFF") ?></span>
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
<?php }	?>
		</td>
	</tr>
</table>
</form>
<?php

	pagefooter();
	