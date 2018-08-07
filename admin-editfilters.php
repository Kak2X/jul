<?php

	require "lib/function.php";
	
	admincheck();	
	
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
	$_GET['page']   = filter_int($_GET['page']);
	$_GET['type']   = numrange(filter_int($_GET['type']), 0, $max_filters);
	$_GET['fpp']    = filter_int($_GET['fpp']);
	if (!$_GET['fpp']) $_GET['fpp'] = 6;
	
	$redirurl = "?type={$_GET['type']}&fpp={$_GET['fpp']}&page={$_GET['page']}";
	if (isset($_POST['setdel']) && isset($_POST['del'])) {
		// Delete multiple filters (from the overview page)
		check_token($_POST['auth']);
		
		if (is_array($_POST['del'])) {
			$q = $sql->prepare("DELETE FROM filters WHERE id = ?");
			//$i = 0;
			foreach ($_POST['del'] as $delid) { 
				$sql->execute($q, [$delid]);
			//	++$i;
			}
			//msg_holder::set_cookie("{$i} filter".($i != 1 ? 's' : '')." deleted.");
		}
		return header("Location: {$redirurl}");	
	} else if (isset($_POST['qdelid'])) {
		// Delete a single filter (from the edit page)
		check_token($_POST['auth']);
		
		// Make sure the filter is valid
		$delid = filter_int($_POST['qdelid']);
		$sql->query("DELETE FROM filters WHERE id = {$delid}");
		//msg_holder::set_cookie("1 filter deleted.");
		
		return header("Location: {$redirurl}");
	} else if (isset($_POST['edit'])) {
		// Creating / updating a filter
		check_token($_POST['auth']);
		
		$source         = filter_string($_POST['source']);
		$forum          = filter_int($_POST['forum']);
		
		// Is the forum valid?
		if ($forum && !$sql->resultq("SELECT 1 FROM forums WHERE id = $forum")) {
			$forum = 0;
		}
		
		// Make sure the fields are filled in
		if (!$source) {
			errorpage("No source string specified.");
		}
		
		$type = numrange(filter_int($_POST['type']), 1, $max_filters);
		if ($type != $_POST['type']) {
			errorpage("Invalid type selected.");
		}
		
		
		$values = array(
			'source'      => $source,
			'replacement' => filter_string($_POST['replacement']),
			'comment'     => filter_string($_POST['comment']),
			'method'      => numrange(filter_int($_POST['method']), 0, 3),
			'forum'       => $forum,
			'enabled'     => filter_int($_POST['enabled']),
			'type'        => $type,
			'ord'         => filter_int($_POST['ord']),
		);
		// Are we updating or creating a filter
		$phs = mysql::setplaceholders($values);
		if  ($_GET['id'] <= -1) {
			$q = "INSERT INTO filters SET {$phs}";
			//msg_holder::set_cookie("Added a filter to '{$filter_types[$type]}'.");
		} else {
			$q = "UPDATE filters SET {$phs} WHERE id = {$_GET['id']}";
			//msg_holder::set_cookie("Edited filter from '{$filter_types[$type]}'.");
		}
		
		$sql->queryp($q, $values);
		
		return header("Location: {$redirurl}");
	}


	
	pageheader("Board Filters");
	
	print adminlinkbar();
	//print msg_holder::get_message();
	
	// Layout stuff
	$l = array();
	
	$l['filter-title'] = ($_GET['type'] ? " - ".$filter_types[$_GET['type']] : "");
	
	$l['filter-sel'] = "";
	foreach ($filter_types as $i => $filter_name) {
		$l['filter-sel'] .= "<a href='?type={$i}'>{$filter_name}</a><br>";
	}

?>
<style type="text/css">
	.rh {height: 19px;}
	textarea {display: block}
</style>
<form method="POST" action="<?= $redirurl ?>&id=<?= $_GET['id'] ?>">
<?= auth_tag() ?>

<table class="table">
	<tr>
		<td class="tdbgh center b" style="width: 120px">
			Filter types
		</td>
		<td class="tdbgh center b" colspan=8>
			Filters<?= $l['filter-title'] ?>
		</td>
	</tr>
	<tr class="rh">
		<td class="tdbg1 nobr vatop" rowspan=999999>
			<?= $l['filter-sel'] ?>
		</td>

<?php	if (!$_GET['type']) { ?>
		<td class="tdbg1 center rh">Select a filter type from the sidebar.</td>
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
						'ord'         => 0,
					);
				} else {
					$editAction = "Editing filter";
					$x = $sql->fetchq("SELECT * FROM filters WHERE id = {$_GET['id']}");
					$sel_method[$x['method']] = 'selected';
					$sel_type[$x['type']]     = 'selected';
					
				}
				
?>
		<td class="tdbgc center b" colspan=8><?= $editAction ?></td>
	</tr>
	
	<tr class="rh">
		<td class="tdbgh center b" rowspan=3 colspan=2>Search for:</td>
		<td class="tdbg2 vatop" rowspan=3 colspan=3>
			<textarea wrap=virtual name="source" ROWS=3 maxlength=127 style="width: 100%; resize:vertical"><?= 
				htmlspecialchars($x['source']) 
			?></textarea>
		</td>
		<td class="tdbgh center b" colspan=3>Options</td>
	</tr>
	
	<tr class="rh">
		<td class="tdbg1" colspan=4>
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
		<td class="tdbg2 vatop" rowspan=3 colspan=3>
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
		<td class="tdbgh center b">Priority:</td>
		<td class="tdbg1" colspan=2>
			<input type="text" name="ord" value="<?= $x['ord'] ?>" maxlength=4 size=4>
			<input type="submit" style="float: right" class="submit" name="edit" value="Save Changes">
		</td>
	<!--
		<td class="tdbg2 right" style="vertical-align: bottom" colspan=3>
			
		</td> -->
	</tr>
	<tr></tr>
	<tr><td class="tdbg2" colspan=8></td></tr>
	
	<tr class="rh">
		<td class="tdbgh center b" colspan=2>Comment:</td>
		<td class="tdbg2 vatop" colspan=6>
			<textarea wrap=virtual name="comment" ROWS=1 maxlength=255 style="width: 100%; resize:vertical"><?= htmlspecialchars($x['comment']) ?></textarea>
		</td>
	</tr>
	
	<tr><td class="tdbgh" colspan=8></td></tr>
	<tr class="rh">

<?php 			//////////////////////////////////////
			} ?>

		<td class="tdbgc center b" style="width: 60px">&nbsp;</td>
		<td class="tdbgc center b" style="width: 40px">Set</td>
		<td class="tdbgc center b" style="width: 50px">Priority</td>
		<td class="tdbgc center b" style="width: 350px">Search</td>
		<td class="tdbgc center b" style="width: 350px">Replacement</td>
		<td class="tdbgc center b" style="width: 150px">Forum</td>
		<td class="tdbgc center b" style="width: 230px">Method</td>
		<td class="tdbgc center b" style="width: 10px">?</td>
		
	</tr>
<?php

		// Filter list
		$filters = $sql->query("
			SELECT f.id, f.method, f.enabled, f.source, f.replacement, f.comment, f.ord, x.title ftitle, x.id fid
			FROM filters f
			LEFT JOIN forums x ON f.forum = x.id
			WHERE f.type = {$_GET['type']}
			ORDER BY f.ord ASC, f.id ASC
			".($_GET['fpp'] > 0 ? "LIMIT ".($_GET['page'] * $_GET['fpp']).",{$_GET['fpp']}" : "")."
		");
		$filtercount = $sql->resultq("SELECT COUNT(*) FROM filters WHERE type = {$_GET['type']}");
		for ($i = 0; $x = $sql->fetch($filters); ++$i) {	
			#################
?>
	<tr class="rh">
		<td class="tdbg1 center fonts">
			<input type="checkbox" name="del[]" value=<?= $x['id'] ?>> - <a href="<?= $redirurl ?>&id=<?= $x['id'] ?>">Edit</a>
		</td>
		<td class="tdbg1 center b">
			<span style=color:<?= ($x['enabled'] ? "#0F0>ON" : "#F00>OFF") ?></span>
		</td>
		<td class="tdbg1 center b"><?= $x['ord'] ?></td>
		<td class="tdbg2"><textarea ROWS=1 style="width: 100%; resize:none" readonly><?= htmlentities($x['source']) ?></textarea></td>
		<td class="tdbg2"><textarea ROWS=1 style="width: 100%; resize:none" readonly><?= htmlspecialchars($x['replacement']) ?></textarea></td>
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
	<tr>
		<td class="tdbg2 center" colspan=7>&nbsp;
			<?php if ($filtercount > $_GET['fpp'] && $_GET['fpp'] > 0) { ?>
				<?= pagelist("?id={$_GET['id']}&type={$_GET['type']}&fpp={$_GET['fpp']}", $filtercount, $_GET['fpp']) ?>
				 -- <a href="?id=<?= $_GET['id'] ?>&type=<?= $_GET['type'] ?>&fpp=-1">Show all</a>
			<?php } ?>
		</td>
	</tr>

	<tr class="rh">
		<td class="tdbgc" style="border-right: 0" colspan=2><input type="submit" style="height: 16px; font-size: 10px" name="setdel" value="Delete Selected"></td>
		<td class="tdbgc center" colspan=5><a href="<?= $redirurl ?>&id=-1">&lt; Add a new filter &gt;</a></td>
	</tr>
<?php }	?>
		</td>
	</tr>
</table>
</form>
<?php

	pagefooter();
	