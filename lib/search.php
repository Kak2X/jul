<?php
	/* Search functions - not included by default */
	
	const SDATE_ALL   = 0;
	const SDATE_LAST  = 1;
	const SDATE_RANGE = 2;

	const ORDER_FIELDS = [
		0 => null,   
		1 => "p.id",
		2 => "p.date",
		3 => "p.user",
		4 => "u.name",
	];
	/*
		parse_search
		
		Converts the search query into a portion of a prepared SQL query.
		$var    -> search query
		           Supports quoted text, removed and added words.
		$field  -> the field on the database to search in
		$qwhere -> array of WHERE statements
		$qvals  -> array of values passed to the prepared statement
		$opts   -> search modifiers (search_opt class)
		
	*/
	function parse_search($query, $field, &$qwhere, &$qvals, $opts = null) {
		if ($opts == null) $opts = new search_opt();
		
		// Filter the SQL LIKE wildcards since they may get in the way
		if ($opts->filter_sql)
			$query = strtr($query, ['%' => '\\%', '_' => '\\_']);
		
		if ($opts->raw) {
			// Perform a much simpler search if raw mode is on
			$qwhere[] = "{$field} LIKE ?";
			$qvals[]  = "%{$query}%";
		} else {
			$words_include = $words_exclude = [];
			// This is a "test" string
			// This ia -" test "
			
			// First, extract the raw contents of the open/closed quotes (optionally prefixed by a -)
			$query = preg_replace_callback("'\-?\"(.*?)\"'si", function ($x) use (&$words_include, &$words_exclude) {
				// Determine if the matched quoted substring goes in the include or exclude list
				if ($x[0][0] == '-') {
					$words_exclude[] = $x[1];
				} else {
					$words_include[] = $x[1];
				}
				// Delete match from source string
				return "";
			}, $query);
			
			
			// Then, parse the remaining words by one by one
			// Get non-empty words
			$words = array_filter(array_map('trim', explode(" ", $query)), 'strlen');
			foreach ($words as $word) {
				// Words that start with a - go in the exclude list
				if (strlen($word) > 1 && $word[0] == '-') {
					$words_exclude[] = substr($word, 1);
				} else {
					$words_include[] = $word;
				}
			}
			
			if (count($words_include) + count($words_exclude) > $opts->limit)
				return false;
			
			// Build the include/exclude lists, all AND'd over
			foreach ($words_include as $x) {
				$qwhere[] = "{$field} LIKE ?";
				$qvals[] = "%{$x}%";
			}
			foreach ($words_exclude as $x) {
				$qwhere[] = "{$field} NOT LIKE ?";
				$qvals[] = "%{$x}%";
			}
		}
		
		return true;
	}
	
	// search option flags
	class search_opt {
		// Filters the SQL LIKE wildcards
		public $filter_sql = true;
		// If set, the entire string is searched for.
		public $raw = false;
		// Max searches allowed
		public $limit = 20;
	}
	
	function post_search_table() {
		global $isadmin, $datefrom, $dateto;
		$ORDER_FIELDS_DESC = [
			0 => "*** None ***",
			1 => "Post ID",
			2 => "Post Date", 
			3 => "User ID",
			4 => "Username",
		];

return "
<form method='GET' action='thread.php'>
<table class='table'>
	<tr><td class='tdbgh center b' colspan='2'>Search</td></tr>
	<tr>
		<td class='tdbg1 center b'>Search for:</td>
		<td class='tdbg2'>".input_html("text", $_GET['text'], ['input' => 'text', 'width' => '500px'])."</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Search in thread ID:</td>
		<td class='tdbg2'>".input_html("id", $_GET['id'], ['input' => 'text', 'width' => '100px'])."</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Search in thread title:</td>
		<td class='tdbg2'>".input_html("title", $_GET['title'], ['input' => 'text', 'width' => '450px'])."</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Search in forum:</td>
		<td class='tdbg2'>".doforumlist($_GET['forum'], 'forum', '*** Any forum ***')."</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Posted by user:</td>
		<td class='tdbg2'>".user_select('user', $_GET['user'])."</td>
	</tr>
".($isadmin ? "
	<tr>
		<td class='tdbg1 center b'>IP mask:</td>
		<td class='tdbg2'>
			".input_html("ipmask", $_GET['ipmask'], ['input' => 'text', 'width' => '300px', 'maxlength' => 46])."
			<small>use * as wildcard</small>
		</td>
	</tr>
" : "")."
	<tr>
		<td class='tdbg1 center b'>Date:</td>
		<td class='tdbg2'>
			".input_html("date", $_GET['date'], ['input' => 'radio', 'options' => [
				0 => 'Any date<br>',
				1 => 'Last '.input_html("datedays", $_GET['datedays'], ['input' => 'text', 'width' => '50px', 'maxlength' => 4, 'class' => 'right']).' days<br>',
				2 => "From ".datetofields($datefrom, 'f', DTF_DATE|DTF_TIME|DTF_NOLABEL)." &nbsp; to &nbsp; ".datetofields($dateto, 't', DTF_DATE|DTF_TIME|DTF_NOLABEL)." (mm/gg/yyyy hh:mm:ss)<br>"
			]])." 
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Post flags:</td>
		<td class='tdbg2'>
			Highlights: ".input_html("hi", $_GET['hi'], ['input' => 'select', 'options' => [
				0 => 'Show all',
				1 => 'Only highlights and featured',
				2 => 'Only featured'
			]])." 
			&mdash; ".input_html("warn", $_GET['warn'], ['input' => 'checkbox', 'label' => 'Only warned'])."
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'>Order:</td>
		<td class='tdbg2'>
			By field: ".input_html('order', $_GET['order'], ['input' => 'select', 'options' => $ORDER_FIELDS_DESC])."
			&mdash; Direction: ".input_html("dir", $_GET['dir'], ['input' => 'select', 'options' => ['*** Default ***','Ascending','Descending']])."
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center b'></td>
		<td class='tdbg2'>
			<button type='submit'>Search</button>
			<input type='hidden' name='mode' value='search'>
		</td>
	</tr>
</table>
</form>";
	}