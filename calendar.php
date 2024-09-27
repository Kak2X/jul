<?php
	
	require "lib/common.php";

	const _MONTH_NAMES = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];

	$date 	= getdate(time());
	
	// Determine if we should use user input or the defaults (today)
	$year 	= filter_int($_GET['y']);
	$month 	= filter_int($_GET['m']);
	$day 	= filter_int($_GET['d']);
	if (!$year) 	$year 	= $date['year'];
	if (!$month) 	$month 	= $date['mon'];
	if (!$day) 		$day 	= $date['mday'];
	
	// View full event ID
	$_GET['event'] = filter_int($_GET['event']);
	if ($_GET['event']) {
		// An admin can see all events regardless of privacy setting
		$eventdata = $sql->fetchq("
			SELECT id, m, d, y, user, title, text, private
			FROM events
			WHERE id = {$_GET['event']} AND (!private OR user = {$loguser['id']} OR $isadmin)
		");
	} else {
		$eventdata = NULL;
	}
	if ($eventdata) {
		$month = $eventdata['m'];
		$day   = $eventdata['d'];
		$year  = $eventdata['y'];
	}
	
	// Get the day of the week the month starts
	// This is used to determine the amount blank cells before the first day of the month
	$date = getdate(mktime(0,0,0,$month,1,$year));
	$i    = 1 - $date['wday'];

	// Get the first day of the week for the next month, to determine blank cells after the last day of the month
	$date = getdate(mktime(0,0,0,$month+1,0,$year));
	$max  = $date['mday'];
	
	pageheader("Calendar for "._MONTH_NAMES[$month]." {$year}");

	// User birthdays for the month
	$bdaytext = array_fill(1, 31, "");
	$users = $sql->query("SELECT $userfields FROM users u WHERE u.birthday ORDER BY u.birthday ASC, u.name ASC");
	while ($user = $sql->fetch($users)) {
		$date = getdate($user['birthday']);
		if ($date['mon'] != $month) continue;

		$age = $year - $date['year'];
		$userlink = getuserlink($user);
		$bdaytext[$date['mday']] .= "<br>- {$userlink} turns {$age}";
	}

	// Events for the month (that you have access to)
	// (Originally this did not show the user before clicking on the event)
	$eventtext = array_fill(1, 31, "");
	$events = $sql->query("
		SELECT e.id, e.d, e.title, e.private, $userfields uid
		FROM events e
		LEFT JOIN users u ON e.user = u.id
		WHERE e.m = $month AND e.y = $year AND (!e.private OR e.user = {$loguser['id']} OR $isadmin)
		ORDER BY e.id
	");
	while ($event1 = $sql->fetch($events)) {
		$userlink = getuserlink($event1, $event1['uid']);
		if ($event1['private']) $userlink = "[$userlink]";
		$eventtext[$event1['d']] .= "<br>- $userlink | <a href='calendar.php?event={$event1['id']}'>".htmlspecialchars($event1['title'])."</a>";
	}
	
	?>
	<table style="width: 100%">
		<tr>
			<td class='font'><a href="index.php"><?=$config['board-name']?></a> - Calendar</td>
			<td class='font right'><a href="event.php">New event</a></td>
		</tr>
	<table class='table'>
	<?php

	// Show full event text if one is selected
	if ($eventdata) {
		$user 		= $sql->fetchq("SELECT $userfields FROM users u WHERE u.id = {$eventdata['user']}");
		$canedit 	= ($isadmin || $eventdata['user'] == $loguser['id']);
		print 
		"<tr>
			<td class='tdbgh center' colspan=7>
				<table style='width: 100%; border-spacing: 0px'>
					<tr>
						<td class='font center' style='width: 100%'><b>"._MONTH_NAMES[$month]." {$day}, {$year}: ".htmlspecialchars($eventdata['title'])."</b> - ".getuserlink($user)."</td>
						<td class='fonts nobr right'>".($canedit ? "<a href='event.php?id={$eventdata['id']}'>Edit</a> | <a href='event.php?id={$eventdata['id']}&action=delete'>Delete</a>" : "&nbsp;")."</td>
					</t>
				</table>
			</td>
		</tr>
		<tr><td class='tdbg1 center' colspan=7>".postfilter($eventdata['text'])."</td></tr>";
	}

	?>
		<tr><td class='tdbgh center' colspan=7><font size=5><?=_MONTH_NAMES[$month]?> <?=$year?></font></td></tr>
		<tr>
			<td class='tdbgh center' style='width: 14.3%'>S</td>
			<td class='tdbgh center' style='width: 14.3%'>M</td>
			<td class='tdbgh center' style='width: 14.3%'>T</td>
			<td class='tdbgh center' style='width: 14.3%'>W</td>
			<td class='tdbgh center' style='width: 14.3%'>T</td>
			<td class='tdbgh center' style='width: 14.3%'>F</td>
			<td class='tdbgh center' style='width: 14.3%'>S</td>
		</tr>
	<?php

	// Print the actual calendar, with birthdays and events
	for(; $i<=$max; $i+=7) {
		print "<tr>\r\n";
		for ($dn = 0; $dn <= 6; ++$dn) {
			$dd = $i + $dn;
			$daytext="<a href='calendar.php?y=$year&m=$month&d=$dd'>$dd</a>";

			if ($dd == $day && $day != 0) 	$cell = 'c';	// Selected day
			else if ($dn == 0 || $dn == 6) 	$cell = '2';	// First / Last day of a week
			else							$cell = '1';	// Everything else

			if ($dd < 1 || $dd > $max) // Blank cells for days not part of the month
				print "<td class='tdbg{$cell} fontt' style='width: 14.3%; height: 80px' valign=top>&nbsp;</td>\r\n";
			else
				print "<td class='tdbg{$cell} fontt' style='width: 14.3%; height: 80px' valign=top>{$daytext}<br>{$bdaytext[$dd]}{$eventtext[$dd]}</td>\r\n";
		}
		print "</tr>\r\n";
	}

	// Year / Month navigation links
	$monthlinks = $yearlinks = "";
	for($i = 1; $i <= 12; ++$i){
		if ($i == $month) $monthlinks .= " $i";
		else $monthlinks .= " <a href='calendar.php?y=$year&m=$i'>$i</a>";
	}
	for($i = $year - 2; $i <= $year + 2; ++$i){
		if ($i == $year)  $yearlinks .= " $i";
		else $yearlinks  .= " <a href='calendar.php?y=$i'>$i</a>";
	}

	?>
		<tr><td class='tdbg2 center fonts' colspan=7>Month:<?=$monthlinks?> | Year:<?=$yearlinks?></td></tr>
	</table>
	<?php

	pagefooter();
?>