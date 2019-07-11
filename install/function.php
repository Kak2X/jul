<?php
	const DBVER_PATH = "lib/dbsver.dat";
	const CONFIG_PATH = "lib/config.php";
	const CONFIG_LENGTH = 25; // Pad with spaces until char 29. Increase it when values aren't aligned anymore.

	function set_heading($desc) {
		return "<tr><td class='tdbgc b center' colspan=2>{$desc}</td></tr>";
	}
	function set_text($desc, $text, $classd = "", $classt = "") {
		return "
		<tr>
			<td class='tdbg1 b center {$classd}'>{$desc}</td>
			<td class='tdbg2 {$classt}'>{$text}</td>
		</tr>";
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
	const STA_VRESIZE = 1;
	const STA_HRESIZE = 2;
	const STA_RESIZE  = 3;
	function set_textarea($type, $name, $desc, $resize = STA_RESIZE, $default = "") {
		$field = get_input_val($name, $default);
		$key   = get_input_key($name);
		
		if (is_array($field)) $field = implode(";", $field);
		
		switch ($resize) {
			case STA_RESIZE:  $rzcss = ""; break;
			case STA_HRESIZE: $rzcss = "resize: horizontal"; break;
			case STA_VRESIZE: $rzcss = "resize: vertical"; break;
			default:          $rzcss = "resize: none"; break;
		}
		
		// NOTE THIS HAS TO BE ADDSLASHED BEFORE GOING IN CONFIG.PHP
		return "
			<tr>
				<td class='tdbg1 b center'>$desc</td>
				<td class='tdbg2'>
					<textarea name='{$key}[0]' style='width: 100%; $rzcss'>".htmlspecialchars($field)."</textarea>
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
	
	function main_array(&$val) {
		$val = str_replace("\"", "\\\"", $val);
		$val = str_replace(";", "\",\"", $val);
		return "[\"{$val}\"]";
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
	
				
	const TYPE_INT = 0;
	const TYPE_STR = 1;
	const TYPE_BOL = 2;
	const TYPE_ARR = 3;
				
	// mappings for config generation (they come from different sources in install and upgrade)
	function config_from_install() {
		global $sqldebuggers, $config;
		
		$first = [
			'sqlhost' => $_POST['sqlhost'],
			'sqluser' => $_POST['sqluser'],
			'sqlpass' => $_POST['sqlpass'],
			'dbname'  => $_POST['dbname'],
			'sqldebuggers' => isset($sqldebuggers) ? implode(";", $sqldebuggers) : $_POST['inptconf']['x_hacks']['adminip'][0], // not currently editable by the installer
		];
		$arrays = $_POST['inptconf'];
		return [$first, $arrays];
	}
	function config_from_update() {
		global $sqlhost, $sqluser, $sqlpass, $dbname, $sqldebuggers,
		       $config, $hacks, $x_hacks;
			   
		$first = [
			'sqlhost'      => $sqlhost,
			'sqluser'      => $sqluser,
			'sqlpass'      => $sqlpass,
			'dbname'       => $dbname,
			'sqldebuggers' => implode(";", $sqldebuggers),
		];
		
		$arrays = [
			'config'  => [], //$config,
			'hacks'   => [], //$hacks,
			'x_hacks' => [], //$x_hacks,
		];
		foreach ($arrays as $conf => $zzz) {
			$x = [];
			foreach ($$conf as $key => $val) {
				$val2 = $val;
				if (is_int($val))
					$type = TYPE_INT;
				else if (is_string($val))
					$type = TYPE_STR;
				else if (is_bool($val))
					$type = TYPE_BOL;
				else if (is_array($val)) {
					$type = TYPE_ARR;
					$val2 = implode(";", $val);
				}
				$x[$key] = [$val2, $type];
			}
			$arrays[$conf] = $x;
		}
		
		return [$first, $arrays];
	}
	
	// this is now used by both the installer and upgrader
	function generate_config($options) {
		$main = $options[0];
			
		// Write configuration file		
		$configfile = "<?php
".		"# this file is auto-generated by the installer and by the upgrade script
".		"# if you add any custom code here (which actually happens in some acmlmboards, believe it or not) it will be lost!
".		"# note that with the upgrade process custom \$config, \$hacks and \$x_hacks keys will be preserved
".		"	
".		"	// Sql database options
".		"	\$sqlhost = '".addslashes($main['sqlhost'])."';
".		"	\$sqluser = '".addslashes($main['sqluser'])."';
".		"	\$sqlpass = '".addslashes($main['sqlpass'])."';
".		"	\$dbname  = '".addslashes($main['dbname'])."';
".		"	
".		"	\$sqldebuggers = ".main_array($main['sqldebuggers']).";
".		"	
";
		// Config/hacks/x_hacks writer
		foreach ($options[1] as $configarr => $arr) {
			$configfile .= "	\${$configarr} = array(
";			foreach ($arr as $key => $val) {
				switch ($val[1]) {
					case TYPE_INT: $configfile .= config_int($key, $val[0]); break;
					case TYPE_STR: $configfile .= config_string($key, $val[0]); break;
					case TYPE_BOL: $configfile .= config_bool($key, $val[0]); break;
					case TYPE_ARR: $configfile .= config_array($key, $val[0]); break;
					default: throw new Exception("Invalid field type with id #{$val[1]} for key \${$configarr}['{$key}'].");
				}
			}
			$configfile .= "	);
";		}

		// Auto HTTP->HTTPS for the origin check
		$configfile .= "
".		"	
".		"	// Are we using SSL?
".		"	if (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] != 'off')
".		"		\$config['board-url'] = str_replace(\"http://\", \"https://\", \$config['board-url']);
";
		
		return $configfile;
	}
	
	function get_available_db_version() {
		return count(glob("update/*.php", GLOB_NOSORT));
	}
	
	function get_current_db_version() {
		return (file_exists(DBVER_PATH) ? (int) file_get_contents(DBVER_PATH) : 0);
	}