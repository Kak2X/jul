<?php

/*
	TODO TODO TODO
	This currently a copy of install/schema.php but a bit more generic
	
	eventually the installer part should have its own functions implemented as wrappers to these 
	(ie: install_generate_config() in installer/schema.php will call generate_config() here with the install-specific schema)
	The "// EDITED" comments mark the functions made more generic
	
*/

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

// EDITED
function input($varname, $key, $data, $value) {
	$input = $attrib = "";
	
	//--
	// Default field
	$default = $value !== null ? $value : $data['default'];
	
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
				$data['desc_sfx'] = __($data['desc_sfx']) . "<!-- select list with defaults goes here -->";
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

// EDITED
function input_section($var_name, $section, $inputfunc) {
	$out = "";
	foreach ($section as $cat_label => $fields) {
		$out .= "<tr><td class='tdbgh center b' colspan='3'>{$cat_label}</td></tr>";
		foreach ($fields as $key => $data) {
			$out .= "
			<tr>
				<td class='tdbg1 center b'>{$data['title']}:</td>
				<td class='tdbg2 fonts'>{$data['desc']}</td>
				<td class='tdbg2'>".$inputfunc($var_name, $key, $data)."</td>
			</tr>";
		}
	}
	return $out;
}

// EDITED
function get_config_layout($schema, $inputfunc) {
	$out = "<table class='table' style='margin: auto'>";
	foreach ($schema as $var_name => $section) {
		$out .= input_section($var_name, $section, $inputfunc);
	}
	$out .= "</table>";
	return $out;
}

// EDITED
function generate_config($schema, $inputfunc) {
	$out    = "";
	$direct = "";
	
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
				$value = $inputfunc($var_name, $key, $data);
				$comment = $data['desc'] ? "// {$data['desc']}" : "";
				if (__($data['direct'])) {
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
	
	return $direct."\r\n".$out;
}