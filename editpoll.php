<?php
	
	// A port of the poll editor from boardc
	// because this board *sure* needed one
	require 'lib/function.php';
	
	$meta['noindex'] = true;
	const NUKE_ON_CHANGE = true;
	
	$_GET['id'] 		= filter_int($_GET['id']);

	$thread  = $sql->fetchq("
		SELECT 	t.forum, t.closed, t.title, t.user, t.poll,
				f.minpower, f.id valid, f.specialscheme, f.specialtitle, f.title ftitle,
				p.question, p.closed pollclosed, p.briefing, p.doublevote
				
		FROM threads t
		LEFT JOIN forums f ON t.forum = f.id
		LEFT JOIN poll   p ON t.poll  = p.id
		WHERE t.id = {$_GET['id']}
	", PDO::FETCH_ASSOC);
	
	
	// If the thread is in an invalid forum, don't bother checking if we're a local mod
	if ($thread && $thread['valid'] && !$ismod)
		$ismod = $sql->resultq("SELECT 1 FROM forummods WHERE forum = {$thread['forum']} and user = {$loguser['id']}");
	
	if (!$thread || (!$ismod && !$thread['valid']))
		$message = "This poll doesn't exist."; // or is in an invalid forum
	if (!$thread['poll'])
		$message = "Good job idiot. This isn't a poll.";
	else if ($thread['minpower'] && $thread['minpower'] > $loguser['powerlevel'])
		$message = "This poll is in a restricted forum.";
	else
		$message = NULL;
	
	if ($message) {
		if (!$ismod) errorpage("You aren't allowed to edit this poll.","thread.php?id={$_GET['id']}",'the thread');
		else errorpage($message);
	}
	
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
	
	
	// Load previously sent or defaults	
	$question 	= isset($_POST['question'])   ? $_POST['question'] 	 : $thread['question'];
	$briefing 	= isset($_POST['briefing'])   ? $_POST['briefing'] 	 : $thread['briefing'];
	$doublevote = isset($_POST['doublevote']) ? $_POST['doublevote'] : $thread['doublevote'];
	$pollclosed	= isset($_POST['pollclosed']) ? $_POST['pollclosed'] : $thread['pollclosed'];
	
	
	// Count votes for poll preview
	$votes	= $sql->query("SELECT choice FROM pollvotes WHERE poll = {$thread['poll']}");
	$total	= 0;
	$votedb = array();
	
	// TODO: Account for influence system?
	while ($vote = $sql->fetch($votes)){
		$votedb[$vote['choice']] = filter_int($votedb[$vote['choice']]) + 1;
		$total++;
	}
	
	

	if (isset($_POST['chtext'])) {
		// Choice text and color counter
		$chtext 	= filter_array($_POST['chtext']);
		$chcolor 	= filter_array($_POST['chcolor']);
		
		// Remove extra option if it is blank
		$maxval  = max(array_keys($chtext)); // The extra option has the highest chtext ID
		if (!$chtext[$maxval]){
			unset($chtext[$maxval]);
		}
		
		$origtext = $sql->getresultsbykey("SELECT id, choice FROM poll_choices WHERE poll = {$thread['poll']}");

	} else {
		// Get the existing choices and group them
		$choicelist = $sql->fetchq("SELECT id, choice, color FROM poll_choices WHERE poll = {$thread['poll']}", PDO::FETCH_GROUP | PDO::FETCH_UNIQUE, mysql::FETCH_ALL);
		foreach($choicelist as $i => $x){
			$chtext[$i] 	= $x['choice'];
			$chcolor[$i] 	= $x['color'];
		}
		unset($choicelist);
	}
	
	$addopt  = (isset($_POST['addopt']) && $_POST['addopt']) ? (int) $_POST['addopt'] : count($chtext)+1;
	
	$chlist  = array_keys($chtext);
		

	
	/*
		Save the changes
	*/

	if (isset($_POST['submit'])) {
		check_token($_POST['auth']);

		if (!isset($_POST['chtext']))		
			errorpage("You haven't specified the options!");
		
		$sql->beginTransaction();
		
		$update = $sql->prepare("INSERT INTO poll_choices (poll, choice, color) VALUES (:poll,:choice,:color)".
								"ON DUPLICATE KEY UPDATE choice = VALUES(choice), color = VALUES(color)");
		
		
		foreach ($chlist as $i){
			
			if (isset($_POST['remove'][$i]) || !$chtext[$i]){
				// Remove all the votes associated with this, then the actual choice
				$sql->query("DELETE FROM pollvotes WHERE poll = {$thread['poll']} AND choice = $i");
				$sql->query("DELETE FROM poll_choices WHERE id = $i");
			} else {
				
				// If the text of the choice changes, nuke all votes
				if (filter_string($origtext[$i]) != $chtext[$i] && NUKE_ON_CHANGE) {
					$sql->query("DELETE FROM pollvotes WHERE poll = {$thread['poll']} AND choice = $i");
				}
				// Update and insert in a single query
				$sql->execute($update,
				[
					//'id' 		=> $i,
					'poll' 		=> $thread['poll'],
					'choice' 	=> xssfilters($chtext[$i]),
					'color' 	=> xssfilters($chcolor[$i]),
				]);
			}
		}
		
		$sql->queryp("UPDATE poll SET question = :question, briefing = :briefing, doublevote = :doublevote, closed = :closed WHERE id = {$thread['poll']}",
		[
			'question' 		=> xssfilters($question),
			'briefing' 		=> xssfilters($briefing),
			'doublevote' 	=> $doublevote,
			'closed' 		=> $pollclosed,
		]);
			
		$sql->commit();
		errorpage("Thank you, {$loguser['name']}, for editing the poll.","thread.php?id={$_GET['id']}",'return to the poll');
		
	}
	
	
	/*
		Poll choices
	*/
		
	$choice_txt = "";
	$choice_out = ""; // DELETEME // this is actually for the preview page, but might as well build this here

	$j = 1;
	$choice_save = array();
	foreach ($chlist as $i) {
		//	We can't immediately delete entries marked as deleted
		//	Instead, just remove the flag
		$deleted = (isset($_POST['remove'][$i]) || !$chtext[$i]);
		
		$choice_txt .= "
		Choice $j: <input name='chtext[$i]' size='30' maxlength='255' value=\"".htmlspecialchars($chtext[$i])."\" type='text'> &nbsp;
		Color: <input name='chcolor[$i]' size='7' maxlength='25' value=\"".htmlspecialchars($chcolor[$i])."\" type='text'> &nbsp;
		<input name='remove[$i]' id='remove[$i]' value=1 type='checkbox' ".($deleted ? "checked" : "")."><label for='remove[$i]'>Remove</label><br>
		";
		
		if (!$deleted) {
			$choice_save[] = $i;
		}
		
		// --- DELETEME (TODO: use print_poll() instead)
		// Preview
		if (!$deleted) {
			$votes = filter_int($votedb[$i]);
			$width = $total ? sprintf("%.1f", $votes / $total * 100) : '0.0';
			$choice_out .= "
			<tr>
				<td class='tdbg1' width='20%'>
					{$chtext[$i]}
				</td>
				<td class='tdbg2' width='60%'>
					<table bgcolor='{$chcolor[$i]}' cellpadding='0' cellspacing='0' width='$width%'>
						<tr><td>&nbsp;</td></tr>
					</table>
				</td>
				<td class='tdbg1 center' width='20%'>
					$votes votes, $width%
				</td>
			</tr>
			";
		}
		// ---
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
	
	pageheader(NULL, $thread['specialscheme'], $thread['specialtitle']);
			
	// DELETEME
	$forum['title'] = $thread['ftitle'];
			
	$links = array(
		$forum['title']  => "forum.php?id={$thread['forum']}",
		$thread['title'] => "thread.php?id={$_GET['id']}",
		"Edit poll"      => NULL,
	);
	$barlinks = dobreadcrumbs($links); 
	
	if (isset($_POST['preview'])) {
		
		?>
	<br>
	<table class='table'>
	
		<tr><td class='tdbgh center' colspan=3>Poll Preview</td></tr>
		
		<tr>
			<td colspan='3' class='tdbgc center'>
				<b><?=$question?></b>
			</td>
		</tr>
		
		<tr>
			<td class='tdbg2 fonts' colspan='3'>
				<?php echo $briefing ?>
			</td>
		</tr>
		
		<?=$choice_out?>
		
		<tr>
			<td class='tdbg2 fonts' colspan='3'>
				Multi-voting is <?=($doublevote ? "enabled" : "disabled")?>.
			</td>
		</tr>
	</table>
		<?php

	}
	
	/*
		Layout
	*/
	
	$close_sel[$pollclosed] 	= "checked";
	$vote_sel[$doublevote] 		= "checked";

	?>
	<?=$barlinks?>
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
				<input type='radio' name='pollclosed' value=0 <?=filter_string($close_sel[0])?>>Open&nbsp;&nbsp;&nbsp;&nbsp;
				<input type='radio' name='pollclosed' value=1 <?=filter_string($close_sel[1])?>>Closed
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
	<?=$barlinks?>
	<?php
		
	pagefooter();
	
?>