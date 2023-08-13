<?php

function error_reporter($type, $msg, $file, $line) {
	
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
		$backtrace = debug_backtrace();
		for ($i = 1; substr($backtrace[$i]['file'], -9) === "mysql.php"; ++$i);
		$file = "[Parent] ".$backtrace[$i]['file'];
		$line = $backtrace[$i]['line'];
		$func = get_class($backtrace[$i]['object']).' # '.$backtrace[$i]['function'];
		$args = $backtrace[$i]['args'];
	} else if (in_array($type, [E_USER_NOTICE,E_USER_WARNING,E_USER_ERROR,E_USER_DEPRECATED], true)) {
		// And do the same for custom thrown errors
		$backtrace = debug_backtrace();
		$file = "[Parent] ".filter_string($backtrace[2]['file']);
		$line = filter_int($backtrace[2]['line']);
		$func = filter_string($backtrace[2]['function']);
		$args = filter_array($backtrace[2]['args']);
	} else {
		$backtrace = debug_backtrace();
		$func = filter_string($backtrace[1]['function']);
		$args = filter_array($backtrace[1]['args']);
	}
	
	
	$file = strip_doc_root($file);
	
	// Without $irctypetext the error is marked as "local reporting only"
	if (isset($irctypetext)) {
		xk_ircsend("102|".($loguser['id'] ? xk(11) . $loguser['name'] .' ('. xk(10) . $_SERVER['REMOTE_ADDR'] . xk(11) . ')' : xk(10) . $_SERVER['REMOTE_ADDR']) .
				   " {$irctypetext}: ".xk()."({$file} #{$line}) {$msg}");
	}

	// Local reporting
	$errors[] = array($typetext, $msg, $func, $args, $file, $line);
	
	return true;
}

// Chooses what to do with unhandled exceptions
function exception_reporter($err) {
	global $config, $sysadmin;
	
	// Convert the exception to an error so the reporter can digest it
	$type = E_ERROR;
	$msg  = $err->getMessage() . "\n\n<span style='color: #FFF'>Stack trace:</span>\n\n". highlight_trace($err->getTrace());
	$file = $err->getFile();
	$line = $err->getLine();
	unset($err);
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
		fatal_error("Exception", $msg, $file, $line);
	}
}

function highlight_trace($arr) {
	$out = "";
	foreach ($arr as $k => $v) {
		$out .= "<span style='color: #FFF'>{$k}</span><span style='color: #F44'>#</span> ".
		        "<span style='color: #0f0'>{$v['file']}</span>#<span style='color: #6cf'>{$v['line']}</span> ".
		        "<span style='color: #F44'>{$v['function']}<span style='color:#FFF'>(\n".htmlspecialchars(print_r($v['args'], true))."\n)</span></span>\n";
	}
	//implode("<span style='color: #0F0'>,</span>", $v['args'])
	return $out;
}

function error_printer($trigger, $report, $errors){
	static $called = false; // The error reporter only needs to be called once
	
	if (!$called){
		$called = true;
		
		// Exit if we don't have permission to view the errors or there are none
		if (!$report || empty($errors)){
			return $trigger ? "" : true;
		}
		
		if ($trigger != false) { // called by printtimedif()
			//array($typetext, $msg, $func, $args, $file, $line);
			$cnt = count($errors);	
			$list = "<br>
			<table class='table'>
				<tr>
					<td class='tdbgh center b' colspan=4>
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
		else{
				extract(error_get_last());
				$ok = error_reporter($type, $message, $file, $line)[0];
				fatal_error($type, $message, $file, $line);				
		}
	}
	
	return true;
}

function print_args($args) {
	$res = "";
	foreach ($args as $val) {
		if (is_object($val)) {
			$res .= ($res !== "" ? "," : "")."<span class='fonts'>[class ".get_class($val)."]</span>";
		} else if (is_resource($val)) {
			$res .= ($res !== "" ? "," : "")."<span class='fonts'>[Resource]</span>";
		} else if (is_array($val)) {
			//$tmp = print_args($val);
			//$res .= ($res !== "" ? "," : "")."<span class='fonts'>[{$tmp}]</span>";
			$res .= ($res !== "" ? "," : "")."<span class='fonts'>[Array]</span>";
		} else if ($val === null) {
			$res .= ($res !== "" ? "," : "")."<span class='fonts'>null</span>";
		} else {
			$res .= ($res !== "" ? "," : "")."<span class='fonts'>'".htmlspecialchars($val)."'</span>";
		}
	}
	return $res;
}

function d($var) {
	print "<pre>";
	print_r($var);
	die;
}