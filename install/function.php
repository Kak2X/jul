<?php
	function set_heading($desc) {
		return "<tr><td class='tdbgc b center' colspan=2>{$desc}</td></tr>";
	}
	
	function get_input_val($name, $default) {
		global $configvar,${$configvar};
		if      (isset($_POST['inptconf'][$configvar][$name])) return $_POST['inptconf'][$configvar][$name][0]; // Passed via page
		else if (isset(${$configvar}[$name]))                  return ${$configvar}[$name]; // Board already installed; use from defined config var
		else                                                   return $default; // Not installed, use default
	}
	
	// The idea is to have nested arrays set up so that printing the config file manually isn't needed anymore
	function get_input_key($name) {
		global $configvar;
		return "inptconf[{$configvar}][{$name}]";
	}
	
	function set_input($type, $name, $desc, $width = 250, $default = "", $extra = ""){
		$field = get_input_val($name, $default);
		$key   = get_input_key($name);
		
		if ($extra) $extra = "&nbsp;$extra"; // I'm picky about this
		if (is_array($field)) $field = implode(";", $field);

		
		// NOTE THIS HAS TO BE ADDSLASHED BEFORE GOING IN CONFIG.PHP
		return "
			<tr>
				<td class='tdbg1 b center'>$desc</td>
				<td class='tdbg2'>
					<input type='text' name='{$key}[0]' style='width: {$width}px' value=\"".htmlspecialchars($field)."\">$extra
					<input type='hidden' name='{$key}[1]' value='{$type}'>
				</td>
			</tr>";
	}
	
	function set_radio($type, $name, $desc, $vals, $default = 0){
		$field = get_input_val($name, $default);
		$key   = get_input_key($name);
		
		$sel[$field] = 'checked';
		
		$list 	= explode("|", $vals);
		$txt 	= "";
		
		foreach($list as $i => $x)
			$txt .= "<input type='radio' name='{$key}[0]' value='$i' ".filter_string($sel[$i]).">&nbsp;$x ";
		
		return "
			<tr>
				<td class='tdbg1 b center'>$desc</td>
				<td class='tdbg2'>
					$txt
					<input type='hidden' name='{$key}[1]' value='{$type}'>
				</td>
			</tr>";
	}
	
	function set_powl($type, $name, $desc, $default = 0){
		global $pwlnames;
		$field = get_input_val($name, $default);
		$key   = get_input_key($name);
		
		$list = "";
		foreach ($pwlnames as $id => $name) {
			$list .= "<option value='{$id}' ".($field == $id ? "selected" : "").">{$name}</option>";
		}
		return "
			<tr>
				<td class='tdbg1 b center'>$desc</td>
				<td class='tdbg2'>
					<select name='{$key}[0]'>{$list}</select>
					<input type='hidden' name='{$key}[1]' value='{$type}'>
				</td>
			</tr>";
	}
	
	function set_psw($type, $name, $desc, $width = 250){
		$field = get_input_val($name, '');
		$key   = get_input_key($name);
		
		return "
			<tr>
				<td class='tdbg1 b center'>$desc</td>
				<td class='tdbg2'>
					<input type='password' name='{$key}[0]' style='width: {$width}px' value=\"$field\">
					<input type='hidden' name='{$key}[1]' value='{$type}'>
				</td>
			</tr>";
	}
	
	// Formatting of config.php, str_pad'd to keep a clean layout
	function config_bool  ($key, &$val){return "\t\t".str_pad("'$key'", CONFIG_LENGTH)."=> ".($val ? (string) "true" : (string) "false").",\r\n";}
	function config_int   ($key, &$val){return "\t\t".str_pad("'$key'", CONFIG_LENGTH)."=> ".filter_int($val).",\r\n";}
	function config_string($key, &$val){return "\t\t".str_pad("'$key'", CONFIG_LENGTH)."=> \"".str_replace("\"", "\\\"", $val)."\",\r\n";}
	function config_array ($key, &$val){
		$val = str_replace("\"", "\\\"", $val);
		$val = str_replace(";", "\",\"", $val);
		return "\t\t".str_pad("'$key'", CONFIG_LENGTH)."=> [\"{$val}\"],\r\n";
	}
	// Query successful or not
	function checkres($r){return $r ? "<span class='ok'>OK!</span>\n" : "<span class='warn'>ERROR!</span>\n";}
	
	function filter_int(&$v) 		{ return (int) $v; }
	function filter_float(&$v)		{ return (float) $v; }
	function filter_bool(&$v) 		{ return (bool) $v; }
	function filter_array (&$v)		{ return (array) $v; }
	function filter_string(&$v) 	{ return (string) $v; }
	
	// Collect all _POST variables and print them here at the top (later values will overwrite them)
	// Note that some values sent are arrays, so this has to be nested
	function savevars($arr, $nested = "") {
		$out = "";
		foreach ($arr as $key => $val) {
			// Generate the associative key if needed (nests to config[something][dfgdsg]
			$name = ($nested) ? "{$nested}[{$key}]" : $key;
			if (is_array($val)) {
				$out .= savevars($val, $name);
			} else {
				$out .= "<input type='hidden' name='{$name}' value=\"".htmlspecialchars($val)."\">";
			}
		}
		return $out;
	}