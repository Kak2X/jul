<?php

function get_install_input($varname, $key, $data) {
	$value = v($_POST['config'][$varname][$key]);
	return _get_input($value, $varname, $key, $data, true);
}

function get_update_input($varname, $key, $data) {
	$value = v($data['direct']) ? $GLOBALS[$key] : $GLOBALS[$varname][$key];
	return _get_input($value, $varname, $key, $data);
}

function _get_input($inpt, $varname, $key, $data, $semicolon = false) {
	switch ($data['type']) {
		case 'string':
			return "\"".str_replace("\"", "\\\"", $inpt)."\"";
		case 'int':
			$inpt = (int)$inpt;
			return "{$inpt}";
		case 'bool':
			return $inpt ? "true" : "false";
		case 'array':
			$sntz = str_replace("\"", "\\\"", $inpt);
			$ardata = $semicolon 
				? str_replace(";", "\",\"", $sntz)
				: implode("\",\"", $sntz);
			return "array(\"{$ardata}\")";
	}
	die("Unrecognized type in schema: {$data['type']} (key: {$key})");
}

function input($varname, $key, $data) {
	$input = $attrib = "";
	
	//--
	// Default field
	$direct = v($data['direct']);
	if (isset($_POST['config'][$varname][$key])) {
		// POST value 
		$default = $_POST['config'][$varname][$key];
	} else if ($direct && isset($GLOBALS[$key])) { 
		// board installed and the variable specified is directly accessible ($sqluser, $sqlpass,...)
		$default = $GLOBALS[$key];
	} else if (!$direct && isset($GLOBALS[$varname][$key])) { 
		// board installed and the $config / $hacks ... field is accessible
		$default = $GLOBALS[$varname][$key];
	} else {
		// use default value
		$default = $data['default'];
	}
	
	//--
	// Style options
	$style = "";
	
	if (isset($data['width']))
		$style .= "width: {$data['width']}px;";
	else if ($data['input'] == 'text' || $data['input'] == 'password') 
		$style .= "width: 250px;";
	else if ($data['input'] == 'textarea')
		$style .= "width: 100%;";

	if ($style)
		$attrib .= " style=\"{$style}\"";
	//--
	
	//--
	// Special overrides
	
	if (isset($data['special'])) {
		switch ($data['special']) {
			case 'yesno':
				$data['options'] = [1 => "Yes", 0 => "No"];
				break;
			case 'powerlevel':
				$data['options'] = $GLOBALS['pwlnames'];
				break;
			case 'dateformat':
				$data['desc_sfx'] = v($data['desc_sfx']) . "<!-- select list with defaults goes here -->";
		}
	}
	
	//--
	// Common attributes
	
	//--
	// TODO: List add / removal
	if ($data['type'] == 'array') {
		$attrib .= " type=\"{$data['input']}\" name=\"config[{$varname}][{$key}]\"";
	} else {
		$attrib .= " type=\"{$data['input']}\" name=\"config[{$varname}][{$key}]\"";
	}
	
	// Temporary
	if (is_array($default)) {
		$default = implode(";", $default);
	}

	//--
	// Input option
	switch ($data['input']) {
		case 'password':
			$input .= "<input style='display:none' type='text'><input style='display:none' type='password'>";
		case 'text':
			$input .= "<input {$attrib} value=\"".htmlspecialchars($default)."\">";
			break;
		case 'textarea':
			$input = "<textarea {$attrib}>".htmlspecialchars($default)."</textarea>";
			break;
		case 'radio':
			$input 	= "";
			foreach ($data['options'] as $id => $label)
				$input .= "<input {$attrib} value=\"{$id}\"".($default == $id ? " checked" : "").">&nbsp;{$label} ";
			break;
		case 'select':
			$input = "<select {$attrib}>";
			foreach ($data['options'] as $id => $label)
				$input .= "<option value='{$id}' ".($default == $id ? "selected" : "").">{$label}</option>";
			$input .= "</select>";
			break;
	}
	
	//--
	// Suffix text
	if (isset($data['desc_sfx']))
		$input .= " {$data['desc_sfx']}";
	
	return $input;
}

function input_section($var_name, $section) {
	$out = "";
	foreach ($section as $cat_label => $fields) {
		$out .= "<tr><td class='tdbgh center b' colspan='3'>{$cat_label}</td></tr>";
		foreach ($fields as $key => $data) {
			$out .= "
			<tr>
				<td class='tdbg1 center b'>{$data['title']}:</td>
				<td class='tdbg2 fonts'>{$data['desc']}</td>
				<td class='tdbg2'>".input($var_name, $key, $data)."</td>
			</tr>";
		}
	}
	return $out;
}

function get_schema() { return json_decode(file_get_contents("install/schema.json"), true); }

function get_config_sql_layout() {
	$schema = get_schema();
	$out = 
	"<table class='table' style='margin: auto'>"
		.input_section('__sql', $schema['__sql'])
	."</table>";
	return $out;
}

function get_config_layout() {
	$schema = get_schema();
	// We don't need the SQL connection options here
	unset($schema['__sql']);
	
	$out = "<table class='table' style='margin: auto'>";
	foreach ($schema as $var_name => $section) {
		$out .= input_section($var_name, $section);
	}
	$out .= "</table>";
	return $out;
}

function generate_config($mode = false) {
	$out    = "";
	$direct = "";
	
	$schema = get_schema();
	
	foreach ($schema as $var_name => $section) {
		$skip = ($var_name[0] == "_");
		if (!$skip) {
			$out .= "$".$var_name." = array(\r\n";
		}
		foreach ($section as $cat_label => $fields) {
			if (!$skip) {
				$out .= "//\r\n// {$cat_label}\r\n//\r\n";
			}
			foreach ($fields as $key => $data) {
				$value = $mode ? get_update_input($var_name, $key, $data) : get_install_input($var_name, $key, $data);
				$comment = $data['desc'] ? "// {$data['desc']}" : "";
				if (v($data['direct'])) {
					$direct .= "$".str_pad($key, 10)." = {$value}; {$comment}\r\n";
				} else {
					$out .= "\t".str_pad("'{$key}'", 30)." => {$value}, {$comment}\r\n";
				}
			}
		}
		if (!$skip) {
			$out .= ");\r\n\r\n";
		}
	}
	
	
	return 
"<?php
# this file is auto-generated by the installer and by the upgrade script
# if you add any custom code here (which actually happens in some acmlmboards, believe it or not) it will be lost!
# note that with the upgrade process custom \$config, \$hacks and \$x_hacks keys will be preserved

{$direct}

{$out}

	// Are we using SSL?
	if (isset(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] != 'off')
		\$config['board-url'] = str_replace(\"http://\", \"https://\", \$config['board-url']);
";
}