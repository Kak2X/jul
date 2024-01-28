<?php

function error_reporter($type, $msg, $file, $line) {
	
 	global $loguser, $errors;

	// They want us to shut up? (@ error control operator) Shut the fuck up then!
	if (!error_reporting())
		return true;
	
	if (is_string($type)) { // Received an exception?
		$typetext = $type;
		$irccol = 4; 
	} else switch ($type) {
		case E_USER_ERROR:			$typetext = "User Error";   		$irccol = 4; break;
		case E_USER_WARNING:		$typetext = "User Warning"; 		$irccol = 7; break;
		case E_USER_NOTICE:			$typetext = "User Notice";  		$irccol = 8; break;
		case E_ERROR:			 	$typetext = "Error"; 				$irccol = 4; break;
		case E_WARNING: 			$typetext = "Warning"; 				$irccol = 7; break;
		case E_NOTICE:				$typetext = "Notice"; 				$irccol = 8; break;
		case E_STRICT: 				$typetext = "Strict Notice";	 	$irccol = 8; break;
		case E_RECOVERABLE_ERROR:	$typetext = "Recoverable Error"; 	$irccol = 7; break;
		case E_DEPRECATED: 			$typetext = "Deprecated"; 			$irccol = 8; break;
		case E_USER_DEPRECATED: 	$typetext = "User Deprecated"; 		$irccol = 8; break;		
		default: 					$typetext = "Unknown type"; 		$irccol = 4;
	}

	// Get the ACTUAL location of error for mysql queries
	if ($type == E_USER_NOTICE && substr($file, -9) === "mysql.php"){
		$backtrace = debug_backtrace();
		for ($i = 1; substr($backtrace[$i]['file'], -9) === "mysql.php"; ++$i);
		$file = "[Parent] ".$backtrace[$i]['file'];
		$line = $backtrace[$i]['line'];
		$func = get_class($backtrace[$i]['object']).' # '.$backtrace[$i]['function'];
		$args = $backtrace[$i]['args'];
	} else if (in_array($type, [E_USER_NOTICE,E_USER_WARNING,E_USER_ERROR,E_USER_DEPRECATED], true)) {
		// And do the same for custom thrown errors
		$backtrace = debug_backtrace();
		if (isset($backtrace[2])) {
			// For functions that trigger the notice, we want the location of the code that called said function
			$loctype = "[Parent] ";
			$loc     = $backtrace[2];
		} else {
			// We only get here if the notice was triggered from the parent file directly (ie: the trigger_error calls directly inside admin-editforums.php)
			$loctype = "";
			$loc     = $backtrace[1];
		}
		$file = $loctype.filter_string($loc['file']);
		$line = filter_int($loc['line']);
		$func = filter_string($loc['function']);
		$args = filter_array($loc['args']);
	} else {
		$backtrace = debug_backtrace();
		$func = filter_string($backtrace[1]['function']);
		$args = filter_array($backtrace[1]['args']);
	}
	
	
	$file = strip_doc_root($file);
	
	// If the message fails to get sent, shrug and ignore any errors that may come out.
	@report_send(
		IRC_ADMIN, ($loguser['id'] ? xk(11)."{$loguser['name']} (".xk(10)."{$_SERVER['REMOTE_ADDR']}".xk(11).")" : xk(10)."{$_SERVER['REMOTE_ADDR']}")." ".xk($irccol)."- {$typetext}: ".xk()."({$file} #{$line}) {$msg}",
		IRC_ADMIN, ($loguser['id'] ? "**{$loguser['name']}** (**{$_SERVER['REMOTE_ADDR']}**)" : "**{$_SERVER['REMOTE_ADDR']}**")." **- {$typetext}**: ({$file} #{$line}) {$msg}"
	);

	// Local reporting
	$errors[] = array($typetext, $msg, $func, $args, $file, $line);
	
	return true;
}

// Chooses what to do with unhandled exceptions
function exception_reporter($err) {
	global $config, $sysadmin;
	
	// Convert the exception to an error so the reporter can digest it
	$type = "Exception";
	$msg  = $err->getMessage() . "\nStack trace:\n". $err->getTraceAsString();
	$file = $err->getFile();
	$line = $err->getLine();
	error_reporter($type, $msg, $file, $line, NULL);
	
	// Should we display the debugging screen?
	if (!$sysadmin && !$config['always-show-debug'] && !defined('INSTALL_FILE')) {
		dialog(
			"Something exploded in the codebase <i>again</i>.<br>".
			"Sorry for the inconvenience<br><br>".
			"Click <a href='?".urlencode(filter_string($_SERVER['QUERY_STRING']))."'>here</a> to try again.",
			"Technical difficulties II", 
			"{$config['board-name']} -- Technical difficulties");
	} else {
		fatal_error("Exception", $err->getMessage() . "\n\n<span style='color: #FFF'>Stack trace:</span>\n\n". highlight_trace($err->getTrace()), $file, $line);
	}
}

function highlight_trace($arr) {
	$out = "";
	foreach ($arr as $k => $v) {
		$out .= "<span style='color: #FFF'>{$k}</span><span style='color: #F44'>#</span> ".
		        "<span style='color: #0f0'>{$v['file']}</span>#<span style='color: #6cf'>{$v['line']}</span> ".
		        "<span style='color: #F44'>{$v['function']}<span style='color:#FFF'>(\n".(isset($v['args']) ? htmlspecialchars(print_r($v['args'], true)) : "")."\n)</span></span>\n";
	}
	//implode("<span style='color: #0F0'>,</span>", $v['args'])
	return $out;
}

function eof_printer() {
	// Return immediately if not enabled
	global $runtime;
	if (!$runtime['show-log'])
		return true;
	
	global $config, $sysadmin, $errors;
	
	// Error list
	if (count($errors) && ($sysadmin || $config['always-show-debug'])) {
		print print_error_table();
	}	
	
	// Query list
	if ($config['always-show-debug'] || in_array($_SERVER['REMOTE_ADDR'], $config['sqldebuggers'])) {
		print "<div class='fonts'><a href='".actionlink(null, "?{$_SERVER['QUERY_STRING']}".($_SERVER['QUERY_STRING'] ? "&" : "")."debugsql=1")."'>".(mysql::$debug_on ? "Disable" : "Enable")." MySQL query logger</a></div>";
		if (mysql::$debug_on) {
			print print_mysql_table();
		}
	}
	
	return true;
}

function print_error_table() {
	global $errors;
	//array($typetext, $msg, $func, $args, $file, $line);
	$cnt = count($errors);	
	$list = "<br/>
	<table class='table'>
		<tr>
			<td class='tdbgh center b' colspan='4'>
				Error list (Total: {$cnt})
			</td>
		</tr>
		<tr>
			<td class='tdbgh center' style='width: 20px'>&nbsp;</td>
			<td class='tdbgh center' style='width: 150px'>Error type</td>
			<td class='tdbgh center'>Function</td>
			<td class='tdbgh center'>Message</td>
		</tr>";
	
	for ($i = 0; $i < $cnt; ++$i) {
		$cell = ($i%2)+1;
		
		if ($errors[$i][2]) {
			$func = $errors[$i][2]."(".print_args($errors[$i][3]).")";
		} else {
			$func = "<i>(main)</i>";
		}
		
		$list .= "
			<tr>
				<td class='tdbg{$cell} center'>".($i+1)."</td>
				<td class='tdbg{$cell} center'>{$errors[$i][0]}</td>
				<td class='tdbg{$cell} center'>
					{$func}
					<div class='fonts'>{$errors[$i][4]}:{$errors[$i][5]}</div>
				</td>
				<td class='tdbg{$cell}'>{$errors[$i][1]}</td>						
			</tr>";
	}
		
	return $list."</table>";
}

function print_mysql_table() {
	// array($connId, $parentFunc, $fileLine, $query/$msg, $time, $noIncFlag, $msgType, $errorText)
	$res = "<br/>
	<table class='table'>
		<tr><td class='tdbgh center b' colspan='5'>SQL Debug</td></tr>
		<tr>
			<td class='tdbgh center' style='width: 20px'>&nbsp;</td>
			<td class='tdbgh center' style='width: 20px'>ID</td>
			<td class='tdbgh center' style='width: 300px'>Function</td>
			<td class='tdbgh center'>Query</td>
			<td class='tdbgh center' style='width: 90px'>Time</td>
		</tr>";
			
	$oldid    = NULL;
	$num      = 1;
	$transact = $transchg = false;
	foreach(mysql::$debug_list as $i => $d) {
		
		// Add a separator between connection ID changes
		if ($oldid != $d[0]) {
			$res .= "<tr><td class='tdbgc center' style='height: 4px' colspan='5'></td></tr>";
		}
		$oldid = $d[0];
		
		// Does the row *NOT* count towards the query count?
		if ($d[5]) {
			$c = "-";
		} else {
			$c = $num;
			++$num;
		}
		
		// Format the message text
		if ($d[6] & mysql::MSG_TRANSCHG) {
			// Transaction change
			$transchg = true;
			$transact = !$transact;
		} else if ($d[6] & mysql::MSG_QUERY) {
			// The error marker has a higher precedence (for obv. reasons)
			if ($d[7] !== NULL) {
				$color = "FF0000";
			} else if ($d[6] & mysql::MSG_CACHED) {
				$color = "00dd00";
			} else if ($d[6] & mysql::MSG_PREPARED) {
				$color = "ffff44";
			} else if ($d[6] & mysql::MSG_EXECUTE) {
				$color = "ffcc44";
			} else {
				$color = "";
			}
			
			// Set the color for non-standard queries
			if ($color !== NULL) {
				$d[3] = "<span style='color:#{$color}".
					   ( $d[7] !== NULL 
					   ? ";border-bottom:1px dotted {$color}' title=\"{$d[7]}\"" 
					   : "'" ).
					   ">{$d[3]}</span>";
				$d[4] = "<span style='color:#{$color}'>{$d[4]}</span>";
			}
			
			$color = NULL;
		} else { // Informative messages
			$d[3] = "<i>{$d[3]}</i>";
		}
		
		// Highlight queries in a transaction (by convention
		if ($transchg) {
			$cell = 'c fonts';
			$transchg = false;
		} else if ($transact) {
			$cell = 'h';
		} else {
			$cell = (($i & 1)+1); // Cycling tccell1/2
		}
		
		$res .= "
		<tr>
			<td class='tdbg{$cell} center'>{$c}</td>
			<td class='tdbg{$cell} center'>{$d[0]}</td>
			<td class='tdbg{$cell} center'>
				{$d[1]}
				<div class='fonts'>{$d[2]}</div>
			</td>
			<td class='tdbg{$cell}'>{$d[3]}</td>
			<td class='tdbg{$cell} center'>{$d[4]}</td>
		</tr>";
	}
	
	return $res."</table>";
}

function print_args($args) {
	$res = "";
	foreach ($args as $val) {
		if (is_object($val)) {
			$res .= ($res !== "" ? "," : "")."[class ".get_class($val)."]";
		} else if (is_resource($val)) {
			$res .= ($res !== "" ? "," : "")."[Resource]";
		} else if (is_array($val)) {
			//$tmp = print_args($val);
			//$res .= ($res !== "" ? "," : "")."<span class='fonts'>[{$tmp}]</span>";
			$res .= ($res !== "" ? "," : "")."[Array]";
		} else if ($val === null) {
			$res .= ($res !== "" ? "," : "")."null";
		} else {
			$res .= ($res !== "" ? "," : "")."'".htmlspecialchars($val)."'";
		}
	}
	return $res;
}

function d($var) {
	print "<pre>";
	print_r($var);
	die;
}