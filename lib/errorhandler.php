<?php

function error_reporter($type, $msg, $file, $line, $context) {
 	global $loguser, $errors;

	// They want us to shut up? (@ error control operator) Shut the fuck up then!
	if (!error_reporting())
		return true;
	

	switch($type) {
		case E_USER_ERROR:			$typetext = "User Error";   $irctypetext = xk(4) . "- Error";   break;
		case E_USER_WARNING:		$typetext = "User Warning"; $irctypetext = xk(7) . "- Warning"; break;
		case E_USER_NOTICE:			$typetext = "User Notice";  $irctypetext = xk(8) . "- Notice";  break;
		case E_ERROR:			 	$typetext = "Error"; 				break;
		case E_WARNING: 			$typetext = "Warning"; 				break;
		case E_NOTICE:				$typetext = "Notice"; 				break;
		case E_STRICT: 				$typetext = "Strict Notice";	 	break;
		case E_RECOVERABLE_ERROR:	$typetext = "Recoverable Error"; 	break;
		case E_DEPRECATED: 			$typetext = "Deprecated"; 			break;
		case E_USER_DEPRECATED: 	$typetext = "User Deprecated"; 		break;		
		default: $typetext = "Unknown type";
	}

	// Get the ACTUAL location of error for mysql queries
	if ($type == E_USER_NOTICE && substr($file, -9) === "mysql.php"){
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		for ($i = 1; substr($backtrace[$i]['file'], -9) === "mysql.php"; ++$i);
		$file = $backtrace[$i]['file'];
		$line = $backtrace[$i]['line'];
	}
	
	// Get the location of error for deprecation
	elseif ($type == E_USER_NOTICE && substr($msg, 0, 10) === "Deprecated") {
		$backtrace = debug_backtrace();
		$file = $backtrace[2]['file'];
		$line = $backtrace[2]['line'];
	}

	$errorlocation = str_replace($_SERVER['DOCUMENT_ROOT'], "", $file) .":$line";
	
	// Without $irctypetext the error is marked as "local reporting only"
	if (isset($irctypetext)) {
		xk_ircsend(IRC_ADMIN."|".($loguser['id'] ? xk(11) . $loguser['name'] .' ('. xk(10) . $_SERVER['REMOTE_ADDR'] . xk(11) . ')' : xk(10) . $_SERVER['REMOTE_ADDR']) .
				   " $irctypetext: ".xk()."($errorlocation) $msg");
	}

	// Local reporting
	$errors[] = array($type, $typetext, $msg, $errorlocation);
	
	return true;
}

function exception_reporter($err) {
	global $config;
	$type = E_ERROR;
	$msg  = $err->getMessage() . "\nStack trace:\n\n". highlight_trace($err->getTrace());
	$file = $err->getFile();
	$line = $err->getLine();
	
	/*
	// A special title goes with mysql exceptions.
	if (is_a($err, 'mysqlException')) {
		$title = $err->getTitle();
	} else {
		$title = 'Exception';
	}*/
	$title = 'Exception';
	
	unset($err);
	error_reporter($type, $msg, $file, $line, NULL);
	echo "<div style='position: fixed; left: 0px; top: 0px; width: 100%; height: 100vh; background: #000; padding: 20px'>";
	if (!has_perm('view-debugger') && !$config['always-show-debug']) {
		dialog("Sorry, an unexpected error has occurred.<br><br>Click <a href='?".urlencode(filter_string($_SERVER['QUERY_STRING']))."'>here</a> to try again.", "Technical difficulties II", "{$config['board-name']} -- Technical difficulties");
	} else {
		fatal_error($title, $msg, $file, $line);
	}
}

function highlight_trace($arr) {
	$out = "";
	foreach ($arr as $k => $v) {
		$out .= "<span style='color: #FFF'>{$k}</span><span style='color: #F44'>#</span> ".
		        "<span style='color: #0f0'>{$v['file']}</span>#<span style='color: #6cf'>{$v['line']}</span> ".
		        "<span style='color: #F44'>{$v['function']}<span style='color:#FFF'>(\n".print_r($v['args'], true)."\n)</span></span>\n";
	}
	//implode("<span style='color: #0F0'>,</span>", $v['args'])
	return $out;
}

function error_printer($trigger, $report, $errors){
	static $called = false;
	
	if (!$called){
		
		$called = true;
		
		if (!$report || empty($errors)){
			return $trigger ? "" : true;
		}
		
		if ($trigger != false){ // called by pagefooter()
			
			// would you have a prettier view now?
			$err_colors = array(
				E_USER_ERROR         => 'FC0',
				E_USER_WARNING       => 'FD6',
				E_USER_NOTICE        => 'FFC',
				E_ERROR              => 'F90',
				E_WARNING            => 'FC6',
				E_NOTICE             => 'FFF',
				E_STRICT             => 'FFF',
				E_RECOVERABLE_ERROR  => 'F90',
				E_DEPRECATED         => 'FFF',
				E_USER_DEPRECATED  	 => 'FFF'
			);
		
			$list = "<table class='table'>
				<tr><td class='tdbgh center b' colspan=4>Errors</td></tr>
				<tr>
					<td style='width: 20px'class='tdbgh center'>&nbsp;</td>
					<td class='tdbgh center'>Type</td>
					<td class='tdbgh center'>Message</td>
					<td class='tdbgh center'>Location</td>
				</tr>";
			foreach ($errors as $id => $error){
				
				$list .= "
					<tr>
						<td class='tdbg1 center' style='color: #{$err_colors[$error[0]]}'>{$id}</td>
						<td class='tdbg1 center nobr b' style='color: #{$err_colors[$error[0]]}'>".htmlspecialchars($error[1])."</td>
						<td class='tdbg2' style='color: #{$err_colors[$error[0]]}'>".htmlspecialchars($error[2])."</td>
						<td class='tdbg1' style='color: #{$err_colors[$error[0]]}'>".htmlspecialchars($error[3])."</td>
					</tr>";
			}
				
			return $list."</table><br>";
			
		}
		else {
				extract(error_get_last());
				$ok = error_reporter($type, $message, $file, $line)[0];
				fatal_error($ok[0], $ok[1], $ok[2], $ok[3]);
		}
	}
	
	return true;
}

function fatal_error($type, $message, $file, $line) {
?>
<style>body, #w{background: #000 !important; color: #fff !important}#w{padding: 0px !important}</style>
<div id='w'>
<pre>Fatal <?=$type?>

<span style='color: #0f0'><?=$file?></span>#<span style='color: #6cf'><?=$line?></span>

<span style='color: #f44'><?=$message?></span>
</pre></div>
<?php
	die;
}