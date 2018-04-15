<?php
	
	require 'lib/function.php';
	
	$meta['noindex'] = true;
	const NUKE_ON_CHANGE = true;
	
	$_GET['id'] 		= filter_int($_GET['id']);
	load_thread($_GET['id']);
	
	$message = "";
	if (!$thread['poll']) 
		$message = "Good job idiot. This isn't a poll.";
	else if (!load_poll($thread['poll'], 0))
		$message = "This thread is a poll, but no poll data exists.";
	else if ($banned)
		$message = "You are banned so you can't edit polls.";
		
	if ($message) {
		errorpage($message,"thread.php?id={$_GET['id']}",'the thread');
	}
	
	$ismod = ismod($forum['id']);
	
	// Quick link to close a poll for thread authors
	if (isset($_GET['close'])) {
		check_token($_GET['auth'], TOKEN_MGET);
		
		if (!$ismod && $loguser['id'] != $thread['user'])
			errorpage("You aren't allowed to edit this poll.","thread.php?id={$_GET['id']}",'the thread');
		
		$sql->query("UPDATE poll SET closed = 1 - closed WHERE id = {$thread['poll']}");
		return header("Location: thread.php?id={$_GET['id']}");
	} else if (!$ismod) {
		// Trying to edit the actual poll without being a mod? I think not!
		errorpage("You aren't allowed to edit this poll.","thread.php?id={$_GET['id']}",'the thread');
	}
	
	if ($forum_error) {
		$forum_error = "<table class='table'>{$forum_error}</table>";
	}
	
	// Load previously sent or defaults	
	$question   = isset($_POST['question'])   ? $_POST['question'] 	 : $poll['question'];
	$briefing   = isset($_POST['briefing'])   ? $_POST['briefing'] 	 : $poll['briefing'];
	$doublevote = isset($_POST['doublevote']) ? $_POST['doublevote'] : $poll['doublevote'];
	$closed     = isset($_POST['closed'])     ? $_POST['closed']     : $poll['closed'];

	if (isset($_POST['chtext'])) {
		// Choice text and color counter
		$_POST['chtext'] 	= filter_array($_POST['chtext']);
		$_POST['chcolor'] 	= filter_array($_POST['chcolor']);
		$_POST['remove'] 	= filter_array($_POST['remove']);
		//print_r($_POST['chtext']);
		$choices    = merge_choice_arrays($_POST['chtext'], $_POST['chcolor'], $_POST['remove']);
		
		// Remove extra option if it is blank
		$maxval  = max(array_keys($choices)); // The extra option has the highest chtext ID
		if ($choices[$maxval]['remove']){
			unset($choices[$maxval]);
		}
	} else {
		$choices = $poll['choices'];
	}
	
	// Additional options to add
	$addopt  = (isset($_POST['addopt']) && $_POST['addopt']) ? (int) $_POST['addopt'] : count($choices) + 1;
		

	
	/*
		Save the changes
	*/

	if (isset($_POST['submit'])) {
		check_token($_POST['auth']);

		if (!$choices)		
			errorpage("You haven't specified the options!");
		
		$sql->beginTransaction();
		
		$insert = $sql->prepare("INSERT INTO poll_choices (poll, choice, color) VALUES (:poll,:choice,:color)");
		$update = $sql->prepare("UPDATE poll_choices SET choice = :choice, color = :color WHERE id = :id AND poll = {$thread['poll']}");
		
		
		// At this point we have both the original untouched choice list in $poll['choices']
		// and the edited one which has removed elements marked with the key 'removed'
		foreach ($choices as $key => $x) {
			if ($x['remove']) { // Remove current choice (also set on empty choice name)
				$sql->query("DELETE FROM pollvotes WHERE poll = {$thread['poll']} AND choice = {$key}");
				$sql->query("DELETE FROM poll_choices WHERE poll = {$thread['poll']} AND id = {$key}");
			} else if (!isset($poll['choices'][$key])) { // Add a new choice
				$sql->execute($insert,
				[
					'poll' 		=> $thread['poll'],
					'choice' 	=> xssfilters($x['choice']),
					'color' 	=> xssfilters($x['color']),
				]);
			} else { // Update an existing choice
				if (NUKE_ON_CHANGE && $poll['choices'][$key]['choice'] != $x['choice']) {
					$sql->query("DELETE FROM pollvotes WHERE poll = {$thread['poll']} AND choice = {$key}");
				}
				$sql->execute($update,
				[
					'id' 		=> $key,
					'choice' 	=> xssfilters($x['choice']),
					'color' 	=> xssfilters($x['color']),
				]);
			}
		}
		
		$sql->queryp("UPDATE poll SET question = :question, briefing = :briefing, doublevote = :doublevote, closed = :closed WHERE id = {$thread['poll']}",
		[
			'question' 		=> xssfilters($question),
			'briefing' 		=> xssfilters($briefing),
			'doublevote' 	=> $doublevote,
			'closed' 		=> $closed,
		]);
			
		$sql->commit();
		errorpage("Thank you, {$loguser['name']}, for editing the poll.","thread.php?id={$_GET['id']}",'return to the poll');
		
	}
	
	
	/*
		Poll choices
	*/
		
	$j = 1; // Choice number
	$choice_txt = "";
	foreach ($choices as $i => $x) {
		$choice_txt .= "
		Choice $j: <input name='chtext[$i]' size='30' maxlength='255' value=\"".htmlspecialchars($x['choice'])."\" type='text'> &nbsp;
		Color: <input name='chcolor[$i]' size='7' maxlength='25' value=\"".htmlspecialchars($x['color'])."\" type='text'> &nbsp;
		<input name='remove[$i]' id='remove[$i]' value=1 type='checkbox' ".(filter_bool($x['remove']) ? "checked" : "")."><label for='remove[$i]'>Remove</label><br>
		";
		++$j;
	}
	
	// Extra choices
	do {
		++$i;
		$choice_txt .= "
			Choice $j: <input name='chtext[$i]' size='30' maxlength='255' value='' type='text'> &nbsp;
			Color: <input name='chcolor[$i]' size='7' maxlength='25' value='' type='text'> &nbsp;
			<input name='remove[$i]' value=1 type='checkbox'><label for='remove[$i]'>Remove</label><br>
		";
		++$j;
	} while ($i < $addopt);
	
	pageheader("Edit poll", $forum['specialscheme'], $forum['specialtitle']);

	$links = array(
		$forum['title']  => "forum.php?id={$forum['id']}",
		$thread['title'] => "thread.php?id={$_GET['id']}",
		"Edit poll"      => NULL,
	);
	$barlinks = dobreadcrumbs($links); 
	
	if (isset($_POST['preview'])) {
		$_POST['choices']   = $choices;
		$_POST['usertotal'] = 0;
		print print_poll($_POST, 0, $forum['id']);
	}
	
	/*
		Layout
	*/
	
	$close_sel[$closed] 	= "checked";
	$vote_sel[$doublevote] 		= "checked";

	?>
	<?= $barlinks ?>
	<?= $forum_error ?>
	<form action='editpoll.php?&id=<?=$_GET['id']?>' method='POST'>
	<table class='table'>
		<tr>
			<td class='tdbgh' style='width: 150px'>&nbsp;</td>
			<td class='tdbgh'>&nbsp;</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Question:</td>
			<td class='tdbg2'>
				<input style='width: 600px;' type='text' name='question' value="<?=htmlspecialchars($question)?>">
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Briefing:</td>
			<td class='tdbg2'>
				<textarea name='briefing' rows='2' cols=<?=$numcols?> wrap='virtual'><?=htmlspecialchars($briefing)?></textarea>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Multi-voting:</td>
			<td class='tdbg2'>
				<input type='radio' name='doublevote' value=0 <?=filter_string($vote_sel[0])?>>Disabled&nbsp;&nbsp;&nbsp;&nbsp;
				<input type='radio' name='doublevote' value=1 <?=filter_string($vote_sel[1])?>>Enabled
			</td>
		</tr>
		
		<tr>
			<td class='tdbg1 center b'>Choices:</td>
			<td class='tdbg2'>
				<?=$choice_txt?>
				<input type='submit' name='changeopt' value='Submit changes'>&nbsp;and show
				&nbsp;<input type='text' name='addopt' value='<?=$addopt?>' size='4' maxlength='1'>&nbsp;options
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center b'>Poll status:</td>
			<td class='tdbg2'>
				<input type='radio' name='closed' value=0 <?=filter_string($close_sel[0])?>>Open&nbsp;&nbsp;&nbsp;&nbsp;
				<input type='radio' name='closed' value=1 <?=filter_string($close_sel[1])?>>Closed
			</td>
		</tr>
		<tr>
			<td class='tdbg1'>&nbsp;</td>
			<td class='tdbg2'>
				<input type='submit' class='submit' value='Edit poll' name='submit'>&nbsp;
				<input type='submit' class='submit' value='Preview poll' name='preview'>&nbsp;
				<?= auth_tag() ?>
			</td>
		</tr>
	</table>
	</form>
	<?= $barlinks ?>
	<?php
		
	pagefooter();
	