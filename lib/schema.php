<?php

/*
	config.php schema utilities (+ universal config layout generator)
*/

class schema {
	
	// Variables applied to the schema
	public $vars;
	public $vars_fallback;
	
	
	public $schema;
	public $parent_name;

	
	// Padding for array keys in file
	const KEY_PAD = 30;
	
	public function __construct($parent_name, $schema_path) {
		// Open schema file as an array
		$this->schema = json_decode(file_get_contents($schema_path), true);
		$this->parent_name = $parent_name;
	}
	// Lazy functions for use only in setup, for the special case with the SQL Connection category
	public function include_var($var_name) {
		$this->schema = [$var_name => $this->schema[$var_name]];
	}
	public function exclude_var($var_name) {
		unset($this->schema[$var_name]);
	}
	
	// Generate PHP file
	public function make_php() {
		$out = "";
		
		// For each variable name (array)
		// $var_name SHOULD NEVER COME FROM USER INPUT.
		foreach ($this->schema as $var_name => $sections) {
			$out .= "\${$var_name} = [\r\n";
			
			// For each category
			foreach ($sections as $section) {
				$out .= "//\r\n// {$section['title']}\r\n//\r\n";
				
				// For each key in the array schema
				foreach ($section['fields'] as $key => $data) {
					$value = $this->php_escape_value($var_name, $key, $data['type']);
					$comment = $data['desc'] ? "// {$data['desc']}" : "";
					$out .= "\t".str_pad("'{$key}'", self::KEY_PAD)." => {$value}, {$comment}\r\n";
				}
			}
			$out .= "];\r\n";
		}
		return $out;
	}
	
	private function get_var($var_name, $key) {
		if (isset($this->vars[$var_name][$key]))
			return $this->vars[$var_name][$key];
		// There's only one fallback, we don't need more
		if (isset($this->vars_fallback[$var_name][$key]))
			return $this->vars_fallback[$var_name][$key];
		// Use the schema default
		return null;
	}
	
	// Escape schema values for writing into a PHP file
	private function php_escape_value($var_name, $key, $type) {
		// Some entries may have presets. If one is used, it overrides the custom value.
		$inpt = isset($this->vars[$var_name][$key."-preset"]) ? $this->vars[$var_name][$key."-preset"] : null;
		if (!$inpt)
			$inpt = $this->get_var($var_name, $key);
		if ($inpt === null)
			return "null";
				
		switch ($type) {
			case 'string':
				// Escape double quotes.
				return '"'.str_replace('"', '\\"', $inpt).'"';
			case 'int':
				return (int)$inpt;
			case 'bool':
				return $inpt ? "true" : "false";
			case 'array':
				// Escape double quotes. $inpt can also be an array.
				$sntz = str_replace('"', '\\"', $inpt);
				// Quote the in-between elements, converting the array if needed.
				$ardata = is_array($inpt)				
					? implode('","', $sntz)
					: str_replace(";", '","', $sntz);
				// Quote the outside elements
				return "[\"{$ardata}\"]";
			default:
				throw new Exception("Unrecognized type in schema: {$type} (key: {$key})");
		}
	}
	
	public function make_config_html() {
		$out = "<table class='table' style='margin: auto'>";
		foreach ($this->schema as $var_name => $sections) {
			foreach ($sections as $section) {
				$out .= "<tr><td class='tdbgh center b' colspan='3'>{$section['title']}</td></tr>";
				foreach ($section['fields'] as $key => $data) {
					$preset = isset($this->vars[$var_name][$key."-preset"]) ? $this->vars[$var_name][$key."-preset"] : null;
					$out .= "
					<tr>
						<td class='tdbg1 center b'>{$data['title']}:</td>
						<td class='tdbg2 fonts'>{$data['desc']}</td>
						<td class='tdbg2'>".input_html("{$this->parent_name}[{$var_name}][{$key}]", $this->get_var($var_name, $key), $data, "{$this->parent_name}[{$var_name}][{$key}-preset]", $preset)."</td>
					</tr>";
				}
			}
		}
		$out .= "</table>";
		return $out;
	}
	

}

	// Generic input helper.
	// Mostly intended for the schema but can be reused elsewhere.
	function input_html($field_name, $source_val, $data, $preset_name = "", $preset_val = null) {
		$input = $attrib = $desc_sfx = "";
		$datalist = false;
		
		//--
		// Default field
		$value = $source_val !== null ? $source_val : (isset($data['default']) ? $data['default'] : "");
		
		//--
		// Style options (optional)
		$style = "";
		
		if (isset($data['style']))
			$style .= "{$data['style']};";
		
		// Width rules
		if (isset($data['width']))
			$style .= "width: {$data['width']};";
		else if ($data['input'] == 'text' || $data['input'] == 'password') 
			$style .= "width: 250px;";
		else if ($data['input'] == 'textarea')
			$style .= "width: 100%;";

		if ($style)
			$attrib .= " style=\"{$style}\"";

		//--
		
		//--
		// Special overrides
		$options = isset($data['options']) ? $data['options'] : [];
		$presets = [];
		if (isset($data['special'])) {
			switch ($data['special']) {
				case 'yesno':
					$options = [1 => "Yes", 0 => "No"];
					break;
				case 'powerlevel':
					$options = $GLOBALS['pwlnames'];
					break;
				case 'dateformat':
					$desc_sfx = __($data['desc_sfx']);
					$presets  = input_date_presets();
					//$datalist = true;
					break;
				case 'dateshort':
					$desc_sfx = __($data['desc_sfx']);
					$presets  = input_date_short_presets();
					//$datalist = true;
					break;
			}
		}
		//--
		
		// Extra datalist if enabled
		if ($datalist && $options) {
			$listid = "o-{$field_name}";
			$attrib .= " list=\"{$listid}\"";
			
			$input .= "<datalist id=\"{$listid}\">";
			foreach ($options as $id => $label)
				$input .= "<option value='{$id}'>{$label}</option>";
			$input .= "</datalist>";
		}
		
		//--
		// Common attributes
		$key_attrib = " type=\"{$data['input']}\" name=\"{$field_name}\"";
		static $extra_attrs = ['maxlength', 'class', 'tabindex'];
		foreach ($extra_attrs as $x)
			if (isset($data[$x]))
				$attrib .= " {$x}=\"{$data[$x]}\"";
		
		if (is_array($value)) {
			$value = implode(";", $value);
		}
		
		switch ($data['input']) {
			case 'password':
				$input .= "<input style='display:none' type='text'><input style='display:none' type='password'>";
			case 'text':
			case 'color':
				$input .= "<input {$attrib}{$key_attrib} value=\"".htmlspecialchars($value)."\">";
				break;
			case 'textarea':
				$input = "<textarea {$attrib}{$key_attrib}>".htmlspecialchars($value)."</textarea>";
				break;
			case 'radio':
				$input = "";
				foreach ($options as $id => $label)
					$input .= "<label><input {$attrib}{$key_attrib} value=\"{$id}\"".($value == $id ? " checked" : "").">&nbsp;{$label}</label>";
				break;
			case 'checkbox':
				$input = "<label><input {$attrib}{$key_attrib} value=\"1\"".($value ? " checked" : "").">&nbsp;{$data['label']}</label>";
				break;
			case 'select':
				$input = "<select {$attrib}{$key_attrib}>";
				foreach ($options as $id => $label)
					$input .= "<option value='{$id}' ".($value == $id ? "selected" : "").">{$label}</option>";
				$input .= "</select>";
				break;
			case 'file':
				$accept = isset($data['accept']) ? $data['accept'] : "";
				$input = "<input {$attrib}{$key_attrib} id=\"{$field_name}\" accept=\"{$accept}\">";
				// For now, this is the only input with fancy JAVASCRIPT options.
				// You can read an uploaded file locally and supply a callback to execute when it is ready.
				if (isset($data['jsmode'])) {
					$input = "<noscript>{$input}</noscript>".
					"<span class=\"js\">".
						"<button type=\"button\" id=\"{$field_name}jsbtn\" class=\"vabase\">Browse...</button>".
						"<input {$attrib} type=\"file\" hidden id=\"{$field_name}js\" accept=\"{$accept}\">".
					"</span>";
					// Convert the accept attribute value to a quoted array, or null if it's empty
					$jsarg_allow = $accept ? "['".str_replace(",", "','", $accept)."']" : "null";
					// Build the callback for when the upload ends
					if (isset($data['jstarget'])) {
						$event_trigger = isset($data['jstrigger']) ? $data['jstrigger'] : 'change';
						$callback = "function(f){ debugger; var x = document.getElementById('{$data['jstarget']}'); x.value = f.target.result; x.dispatchEvent(new Event('{$event_trigger}')); }";
					} else {
						$callback = $data['jscallback']; // must be defined
					}
					add_js("addJsUploadBtn(\"{$field_name}\", {$data['maxsize']}, {$jsarg_allow}, \"{$data['jsmode']}\", {$callback})");
				}
				$input = "<div class=\"file-upload\">{$input}<br/><span class=\"fonts\">Max size: ".sizeunits($data['maxsize'])."</span></div>";
				break;
		}
		//--
		// Suffix text
		if ($desc_sfx)
			$input .= " {$data['desc_sfx']}";
		
		// These work by having a separate field with the same name but "-preset" appended to the key.
		if ($presets) {
			$input .= " - or a preset: <select name=\"{$preset_name}\"><option></option>";
			foreach ($presets as $id => $label)
				$input .= "<option value='{$id}' ".($preset_val == $id ? "selected" : "").">{$label}</option>";
			$input .= "</select>";
		}
		
		/*
		
			
			// TODO: Add the add/remove buttons for this, but I can't think of sane a way of doing it without JS
			// Since NoScript compatibility has priority, this one is on hold, so for now we we're "CSV"'ing it
			
			$attrib .= " type=\"{$data['input']}\"";
			_inpt(0, $value);
			
			//$attrib .= " type=\"{$data['input']}\" name=\"{$field_name}[]\"";
			//
			//foreach ($value as $i => $v) {
			//	$input .= "<div id='{$field_name}-{$i}'>"._inpt($i, $v, true)."</div>";
			//}
		} else {
			$attrib .= " type=\"{$data['input']}\" name=\"{$field_name}\"";
			_inpt(0, $value);
		}
		*/

		
		return $input;
		
		
		/*function _inpt($i, $v, $removebtn = false) {
			$array_idx = ""; //($data['type'] == 'array' ? "[{$i}]" : "");
			$attrib .= " type=\"{$data['input']}\"";
			
			switch ($data['input']) {
				case 'password':
					$input .= "<input style='display:none' type='text'><input style='display:none' type='password'>";
				case 'text':
				case 'color':
					$input .= "<input {$attrib} name=\"{$field_name}{$array_idx}\" value=\"".htmlspecialchars($v)."\">";
					break;
				case 'textarea':
					$input = "<textarea {$attrib} name=\"{$field_name}{$array_idx}\">".htmlspecialchars($v)."</textarea>";
					break;
				case 'radio':
					$input 	= "";
					foreach ($data['options'] as $id => $label)
						$input .= "<input {$attrib} name=\"{$field_name}{$array_idx}\" value=\"{$id}\"".($v == $id ? " checked" : "").">&nbsp;{$label} ";
					break;
				case 'select':
					$input = "<select {$attrib} name=\"{$field_name}{$array_idx}\">";
					foreach ($data['options'] as $id => $label)
						$input .= "<option value='{$id}' ".($v == $id ? "selected" : "").">{$label}</option>";
					$input .= "</select>";
					break;
			}
			//--
			// Suffix text
			if (isset($data['desc_sfx']))
				$input .= " {$data['desc_sfx']}";
			
			// These work by having a separate field with the same name but "-preset" appended to the key.
			if (isset($data['preset'])) {
				$input .= " - or a preset: <select name=\"{$preset_name}{$array_idx}\"><option></option>";
				foreach ($data['options'] as $id => $label)
					$input .= "<option value='{$id}' ".($v == $id ? "selected" : "").">{$label}</option>";
				$input .= "</select>";
			}
			
			//if ($removebtn) {
			//	$input .= " - <button type='submit' name='inpt_remove' value=''>Remove</button>";
			//}
		}*/
	}
	

	function input_date_presets() {
		static $p;
		if ($p == null) {
			$p = [];
			$time = time();
			foreach (DATE_FORMATS as $x)
				$p[$x] = "$x (" . date($x, $time) .")";
		}	
		return $p;
	}
	
	function input_date_short_presets() {
		static $p;
		if ($p == null) {
			$p = [];
			$time = time();
			foreach (DATE_SHORT_FORMATS as $x)
				$p[$x] = "$x (" . date($x, $time). ")";
		}	
		return $p;
	}