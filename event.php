<?php
	require 'lib/function.php';

	$meta['noindex'] = true;
	
	$_GET['id'] 	= filter_int($_GET['id']);
	$_GET['action'] = filter_string($_GET['action']);

	if (!$loguser['id']) {
		errorpage("You are not logged in.",'login.php', 'log in (then try again)');
	}
	if ($banned) {
		errorpage("You are banned and cannot create or edit events.",'calendar.php', 'return to the calendar');
	}
	//if (!$_GET['id']) {
	//	errorpage("No event ID specified.",'calendar.php', 'return to the calendar');
	//}

	// Editing an event?
	if ($_GET['id']) {
		$event     = $sql->fetchq("SELECT * FROM events WHERE id = {$_GET['id']}");
		if (!$event) {
			errorpage("Event ID #{$_GET['id']} doesn't exist.",'calendar.php', 'return to the calendar');
		}
		if (!$isadmin && $event['user'] != $loguser['id']) {
			errorpage("This isn't your event.",'calendar.php', 'return to the calendar');
		}
	} else {
		$date = getdate(time());
		$event = array('title'=>'','text'=>'','private'=>'','m'=>$date['mon'],'d'=>$date['mday'],'y'=>$date['year']);
	}
	
	
	if ($_GET['action'] == "delete") {
		if (!$_GET['id']) {
			errorpage("No event selected for deletion.",'calendar.php', 'return to the calendar',0);
		}
		if (isset($_POST['reallydelete'])) {
			check_token($_POST['auth']);
			$sql->query("DELETE FROM events WHERE id = {$_GET['id']}");
			errorpage("Thank you, {$loguser['name']}, for deleting the event.","calendar.php","return to the calendar",0);
		}
		
		pageheader();
		
		?>
		<form action='event.php?action=delete&id=<?=$_GET['id']?>' method='POST'>
		<table class='table'>
			<tr>
				<td class='tdbg1 center'>
					Are you sure you want to <b>DELETE</b> this event?<br>
					<br>
					<input type='hidden' name=auth value="<?=generate_token()?>">
					<input type='submit' class=submit name=reallydelete value='Delete event'> - <a href='calendar.php?event=<?=$_GET['id']?>'>Cancel</a>
				</td>
			</tr>
		</table>
		</form>
		<?php
		
		pagefooter();
	}
	
	
		
	if (isset($_POST['submit'])) {
		
		check_token($_POST['auth']);
		
		$title 		= filter_string($_POST['title'], true);
		$message	= filter_string($_POST['message'], true);
		$private	= filter_int($_POST['private']);
		$m = filter_int($_POST['m']);
		$d = filter_int($_POST['d']);
		$y = filter_int($_POST['y']);
		
		if (!$title) 				errorpage("The title of the event cannot be blank.",'calendar.php', 'return to the calendar');
		if (!$message) 				errorpage("The message of the event cannot be blank.",'calendar.php', 'return to the calendar');
		if (!checkdate($m,$d,$y))	errorpage("The date you have selected is invalid.",'calendar.php', 'return to the calendar');
		
		$values = array (
			'title'		=> xssfilters($title),
			'text'		=> xssfilters($message),
			'private'	=> $private,
			'm' => $m,
			'd' => $d,
			'y' => $y,
		);
		if (!$_GET['id']) 
			$q = "INSERT INTO events SET d=:d,m=:m,y=:y,title=:title,text=:text,private=:private,user={$loguser['id']}";
		else 
			$q = "UPDATE events SET d=:d,m=:m,y=:y,title=:title,text=:text,private=:private WHERE id = {$_GET['id']}";
		
		$sql->queryp($q, $values);

		if (!$_GET['id']) {
			$_GET['id'] = $sql->insert_id();
		}
		return header("Location: calendar.php?event={$_GET['id']}");
	} else {
		
		pageheader();
		?>
	<form method="POST" action="event.php?action=edit&id=<?=$_GET['id']?>">
	<table class="table">
		<tr><td class="tdbgh center" colspan=2><b><?=(!$_GET['id'] ? "New Event" : "Editing Event #{$_GET['id']}")?></b></td></tr>
		<tr>
			<td class='tdbg1 center'><b>Event title:</b></td>
			<td class='tdbg2'><input type="text" name="title" size=80 maxlength=200 value="<?=htmlspecialchars($event['title'])?>"></td>
		</tr>
		<tr>
			<td class='tdbg1 center'><b>Message:</b></td>
			<td class='tdbg2'><textarea wrap=virtual name=message ROWS=6 COLS=<?=$numcols?> style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($event['text'])?></textarea></td>
		</tr>
		<tr>
			<td class='tdbg1 center'><b>Event date:</b></td>
			<td class='tdbg2'>
				<input type="text" name="m" value="<?=$event['m']?>" size=1 maxlength=2 style='text-align: right'>- 
				<input type="text" name="d" value="<?=$event['d']?>" size=1 maxlength=2 style='text-align: right'>- 
				<input type="text" name="y" value="<?=$event['y']?>" size=3 maxlength=4 style='text-align: right'>
				<span class="fonts">(mm/dd/yyyy)</span>
			</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2'>
				<input type='hidden' name=auth VALUE="<?=generate_token()?>">
				<input type='submit' class=submit name=submit VALUE="Edit event">
			</td>
		</tr>		
		<tr>
			<td class='tdbg1 center'><b>Options:</b></td>
			<td class='tdbg2'>
				<input type='checkbox' name="private" id="private" value="1" <?=($event['private'] ? "checked" : "")?>><label for="private">Private</label>
			</td>
		</tr>
		
	</table>
	</form>
		<?php
	}
	
	pagefooter();

?>