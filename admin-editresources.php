<?php

require "lib/function.php";
admincheck();

$resource_types = array(
	array(
		1 => "Smilies",
		2 => "Post icons",
		3 => "Syndromes"
	)
);

$_GET['id']   = isset($_GET['id']) ? (int)$_GET['id'] : NULL;
$_GET['type'] = filter_int($_GET['type']);
// For pagination (future use?)
//$_GET['page']   = filter_int($_GET['page']);
//$_GET['fpp']    = filter_int($_GET['fpp']);
//if (!$_GET['fpp']) $_GET['fpp'] = 20;


$redir_url = "?type={$_GET['type']}";
$stc = new SidebarTable("Resources", "Resource types", $resource_types, 'type', $_GET['type']); 

switch ($_GET['type']) {
	case 1: // Smilies
		$res = readsmilies();
		
		if (isset($_POST['setdel']) && isset($_POST['del'])) {
			check_token($_POST['auth']);
			
			foreach ($_POST['del'] as $del) {
				unset($res[$del]);
			}
			if ($err = writesmilies($res)) {
				errorpage("Failed to insert the following smileys:{$err}");
			}
			return header("Location: {$redir_url}");
		}
		
		if (isset($_POST['submit']) || isset($_POST['submit2'])) {
			check_token($_POST['auth']);
			
			$_POST['code']      = filter_string($_POST['code']);
			$_POST['url']       = filter_string($_POST['url']);
			
			if (!$_POST['code'] || !$_POST['url'])
				errorpage("All the fields are required.");
			
			// If the new option is specified, pick the first "free" ID
			$newid = isset($res[$_GET['id']]) ? $_GET['id'] : count($res);
			$res[$newid] = array($_POST['code'], $_POST['url']);
			
			// Save the changes now and display failed
			if ($err = writesmilies($res)) {
				errorpage("Failed to insert the following smileys:{$err}");
			}
			
			$editlink = isset($_POST['submit']) ? "&id={$newid}" : ""; // Save and continue?
			return header("Location: {$redir_url}{$editlink}");
		}
				
		$headers = array(
			-1 => [
				'label' => 'Preview',
				'style' => 'width: 100px',
			],
			'code' => [
				'label'     => 'Code',
				'type'      => 'text',
				'editstyle' => 'width: 150px',
			],
			'url' => [
				'label'     => 'Image URL',
				'type'      => 'text',
				'editstyle' => 'width: 500px',
			]
		);
		$values = array();
		foreach ($res as $id => $x) {
			$values[$id] = array(
				-1     => "<img src=\"{$x[1]}\">",
				'code' => $x[0],
				'url'  => $x[1],
			);
		}
		$strings = array(
			'element'  => "smiley",
			'base-url' => $redir_url,
		);
		
		break;
	case 2: // Post icons
		$res = array_map('trim', file('posticons.dat'));
		
		if (isset($_POST['setdel']) && isset($_POST['del'])) {
			check_token($_POST['auth']);
			
			foreach ($_POST['del'] as $del) {
				unset($res[$del]);
			}
			if (writeposticons($res) === false) {
				errorpage("Failed to save changes to 'posticons.dat'.");
			}
			return header("Location: {$redir_url}");
		}
		
		if (isset($_POST['submit']) || isset($_POST['submit2'])) {
			check_token($_POST['auth']);
			
			$_POST['url']       = filter_string($_POST['url']);
			
			if (!$_POST['url'])
				errorpage("All the fields are required.");
			
			// If the new option is specified, pick the first "free" ID
			$newid = isset($res[$_GET['id']]) ? $_GET['id'] : count($res);
			$res[$newid] = $_POST['url'];
			
			// Save the changes now
			if (writeposticons($res) === false) {
				errorpage("Failed to save changes to 'posticons.dat'.");
			}
			
			$editlink = isset($_POST['submit']) ? "&id={$newid}" : ""; // Save and continue?
			return header("Location: {$redir_url}{$editlink}");
		}
		
		
		$headers = array(
			-1 => [
				'label' => 'Preview',
				'style' => 'width: 100px',
			],
			'url' => [
				'label'     => 'Image URL',
				'type'      => 'text',
				'editstyle' => 'width: 500px',
			]
		);
		$values = array();
		foreach ($res as $id => $x) {
			$values[$id] = array(
				-1     => "<img src=\"{$x}\">",
				'url'  => $x,
			);
		}
		$strings = array(
			'element'  => "post icon",
			'base-url' => $redir_url,
		);
		
		break;
	case 3:
		$res = read_syndromes(true); // Include disabled
		
		if (isset($_POST['setdel']) && isset($_POST['del'])) {
			check_token($_POST['auth']);
			foreach ($_POST['del'] as $del) {
				unset($res[$del]);
			}
			if ($err = write_syndromes($res)) {
				errorpage("Failed to insert the following syndromes:{$err}");
			}
			return header("Location: {$redir_url}");
		}
		
		if (isset($_POST['submit']) || isset($_POST['submit2'])) {
			check_token($_POST['auth']);
			
			$_POST['postcount'] = filter_int($_POST['postcount']);
			$_POST['color']     = filter_string($_POST['color']);
			$_POST['text']      = filter_string($_POST['text']);
			$_POST['enabled']   = filter_int($_POST['enabled']);
			
			if (!$_POST['color'] || !$_POST['text'])
				errorpage("You didn't enter the required fields.");
			
			if (!in_array(find_syndrome($res, $_POST['postcount']), array(-1, $_GET['id'])))
				errorpage("No post count duplicates allowed.");
			
			// If the new option is specified, pick the first "free" ID
			$newid = isset($res[$_GET['id']]) ? $_GET['id'] : count($res);
			$res[$newid] = array($_POST['postcount'], $_POST['color'], $_POST['text']);
			if (!$_POST['enabled']) $res[$newid][3] = 1; // Extra optional value for disabled options
			
			// Save the changes now and display failed
			if ($err = write_syndromes($res)) {
				errorpage("Failed to insert the following syndromes:{$err}");
			}
			
			// Determine new position now that it has been reshuffled
			if (isset($_POST['submit'])) {
				$newid = find_syndrome($res, $_POST['postcount']);
			}
				
			$editlink = isset($_POST['submit']) ? "&id={$newid}" : ""; // Save and continue?
			return header("Location: {$redir_url}{$editlink}");
		}
		
		$headers = array(
			-2 => [
				'label' => 'Set',
				'style' => 'width: 50px',
			],
			'postcount' => [
				'label'     => 'Post count',
				'type'      => 'text',
				'editstyle' => 'width: 150px',
				'default'   => 0,
			],
			'color' => [
				'label'     => 'Color',
				'type'      => 'color',
				'default'   => '#FFFFFF',
			],
			'text' => [
				'label'     => 'Text',
				'type'      => 'text',
				'editstyle' => 'width: 500px',
				'default'   => "'Default syndrome' +",
			],
			'enabled' => [
				'label'     => 'Options', // lazy; will not work for multiple options
				'type'      => 'checkbox',
				'editlabel' => 'Enabled',
				'nodisplay' => true,
			],
			-1 => [
				'label' => 'Preview'
			],
		);
		$values = array();
		
		foreach ($res as $id => $x) {
			$values[$id] = array(
				-1          => "<span class='fonts'>".str_replace("<br>", "", syndrome($x[0]))."</span>",
				-2          => "<span class='b' style='color:#".(!isset($x[3]) ? "0f0'>YES": "f00'>NO")."</span>",
				'postcount' => $x[0],
				'color'     => $x[1],
				'text'      => $x[2],
				'enabled'   => !isset($x[3]),
			);
		}
		
		$strings = array(
			'element'  => "post syndrome",
			'base-url' => $redir_url,
		);

		break;
	default:
		$html = SidebarTable::Message("Select a resource type from the sidebar.");
		break;
}


if (!isset($html)) {
	$html = row_display($headers, $values, $strings, $_GET['id']); //, $_GET['page'], $_GET['fpp'], count($values));
}

pageheader("Edit resources");
print adminlinkbar()
. "<form method='POST' action='{$redir_url}".(isset($_GET['id']) ? "&id={$_GET['id']}" : "")."' enctype='multipart/form-data'>"
. $stc->DisplayTop() . $html . $stc->DisplayBottom()
. "</form>";
pagefooter();


function writesmilies($res) {
	$err = "";
	$h = fopen('smilies.dat', 'w');
	foreach ($res as $row) {
		if ($row && !fputcsv($h, $row, ',')) {
			$err .= "<br>{$row[0]}";
		}
	}
	fclose($h);
	return $err;
}

function writeposticons($res) {
	return file_put_contents('posticons.dat', implode(PHP_EOL, $res));
}

function write_syndromes(&$res) {
	// First, order the syndromes by post count requirement (the first value in the array)
	usort($res, function ($a,$b) { return ($a[0] - $b[0]); } );
	
	$err = "";
	$h = fopen('syndromes.dat', 'w');
	foreach ($res as $row) {
		if ($row && !fputcsv($h, $row, ',')) {
			$err .= "<br>{$row[2]}";
		}
	}
	fclose($h);
	return $err;
}

function find_syndrome($res, $find) {
	foreach ($res as $key => $var) 
		if ($var[0] == $find) 
			return $key;
	return -1;
}


////------------------

function row_display($headers, $values, $strings, $sel = NULL, $page = 0, $limit = -1, $rowcount = 0) {
	static $setid = 0;
	
	$colspan  = count($headers) + 2; // + Edit selection
	
	//-- 
	// Generate header text
	// And fix the colspan to be correct (account for non-displayed fields in the row list)
	$header_txt = "";
	foreach ($headers as $key => $x) {
		if (!isset($x['nodisplay'])) {
			$header_txt .= "<td class='tdbgh center b'".(isset($x['style']) ? " style=\"{$x['style']}\"" : "").">{$x['label']}</td>";
		} else {
			--$colspan;
		}
	}
	//--
	// Main row display
	$i = -1;
	$row_txt = "";
	foreach ($values as $id => $row) {
		$cell = (++$i % 2) + 1;
		$row_txt .= "
		<tr class='th' id='row{$setid}_{$id}'>
			<td class='tdbg{$cell} center b'>
				<input type='checkbox' name='del[]' value='{$id}'>
			</td>
			<td class='tdbg{$cell} center fonts'>
				<a href='{$strings['base-url']}&id={$id}' class='editCtrl_{$setid}' data-id='{$id}'>Edit</a>
			</td>";
		foreach ($headers as $key => $x) {
			if (!isset($x['nodisplay'])) {
				$row_txt .= "<td class='tdbg{$cell} center'>{$row[$key]}</td>";
			}
		}
		$row_txt .= "
		</tr>";
	}
	//--
	$pagectrl = "";
	if ($limit > 0 && $rowcount > $limit) {
		$pagectrl = "
		<tr class='rh'>
			<td class='tdbg2 center fonts' colspan='{$colspan}'>
				".pagelist("?type={$_GET['type']}&fpp={$_GET['fpp']}", $rowcount, $limit)."
				 &mdash; <a href='?type={$_GET['type']}&fpp=-1'>Show all</a>
			</td>
		</tr>";
	}
	//--
	// Edit window
	$edit_txt   = "";
	if ($sel !== NULL) {
		
		// Before doing the enchilada, check if the value exists to set the default.
		if (!isset($values[$sel])) {
			$sel = -1;
			$action_name = "Creating a new {$strings['element']}";
		} else {
			$action_name = "Editing {$strings['element']} #{$sel}";
		}
		
		foreach ($headers as $key => $x) {
			if (isset($x['type'])) {
				
				$value = isset($values[$sel][$key]) ? $values[$sel][$key] : filter_string($x['default']);
				
				$editcss = isset($x['editstyle']) ? " style=\"{$x['editstyle']}\"" : "";
				switch ($x['type']) {
					case 'text':
					case 'color':
						$input = "<input type='{$x['type']}' name='{$key}' value=\"".htmlspecialchars($value)."\"{$editcss}>";
						break;
					case 'checkbox':
						$input = "<label><input type='checkbox' name='{$key}' value='1'".($value ? " checked" : "")."{$editcss}> {$x['editlabel']}</label>";
						break;
					case 'radio':
						$ch[$value] = "checked";
						$input = "";
						foreach ($x['choices'] as $xk => $xv)
							$input .= "<label><input name='{$key}' type='radio' value=\"{$xk}\" ".filter_string($ch[$xv]).">&nbsp;{$xv}</label>&nbsp; &nbsp; ";
						unset($ch);
						break;
					case 'select':
						$ch[$value] = "selected";
						$input = "";
						foreach ($x['choices'] as $xk => $xv)
							$input .= "<label><input name='{$key}' type='radio' value=\"{$xk}\" ".filter_string($ch[$xv]).">&nbsp;{$xv}</label>&nbsp; &nbsp; ";
						unset($ch);
						break;
										
				}
				
				$edit_txt .= "
				<tr class='rh'>
					<td class='tdbg1 center b'>{$x['label']}:</td>
					<td class='tdbg2'>{$input}</td>
				</tr>";
			}
		}
	}
	//--
	// TODO: JS code for the alternate editor
	$js = "";
	/*
	if (!$setid) {
		$js = include_js("js/roweditor.js", true);
	}
	$headjson = json_encode($headers);
	*/
	
	++$setid;
	//--
	
	return "
	".($edit_txt ? "
	<tr>
		<td class='tdbg2 nestedtable-container' colspan='{$colspan}'>
			<table class='table nestedtable'>
				<tr class='rh'><td class='tdbgh center b' colspan='2'>{$action_name}</td></tr>
				{$edit_txt}
				<tr class='rh'>
					<td class='tdbg1 center b' style='width: 150px'>&nbsp;</td>
					<td class='tdbg2'>
						<input type='submit' name='submit' value='Save and continue'> &nbsp; <input type='submit' name='submit2' value='Save and close'>
					</td>
				</tr>
				<tr><td class='tdbg2' colspan='2'></td></tr>			
			</table>
		</td>
	</tr>
	" : "")."
	
	<tr class='rh'>
		<td class='tdbgh center b' style='width: 30px'></td>
		<td class='tdbgh center b' style='width: 50px'>#</td>
		{$header_txt}
	</tr>
	{$row_txt}
	{$pagectrl}
	
	<tr class='rh'>
		<td class='tdbgc center' colspan='{$colspan}'>
			<input type='submit' style='height: 16px; font-size: 10px; float: left' name='setdel' value='Delete selected'>
			".auth_tag()."{$js}
			<a href=\"{$strings['base-url']}&id=-1\">&lt; Add a new {$strings['element']} &gt;</a>
		</td>
	</tr>";
}




class SidebarTable {
	private static $total = 0;
	
	public $title;
	public $linkTitle;
	public $linkSet;
	public $linkVar;
	public $sel;
	
	public function __construct($title, $linkTitle, $linkSet, $linkVar = 'type', $sel = 0) {
		$this->title     = $title;
		$this->linkTitle = $linkTitle;
		$this->linkSet   = $linkSet;
		$this->linkVar   = $linkVar;
		$this->sel       = $sel;
	}
	
	public function DisplayTop() {
		$linksHtml = self::GenerateLinks($this->linkSet, $this->linkVar, $this->sel, $titleSel);
		
		// CSS Displayed the first time
		$css = "";
		if (!self::$total) {
			$css = "
			<style type='text/css'>
				.rh {height: 19px}
				.nestedtable-container {
					padding: 0px;
					border-bottom: 0px;
					border-right: 0px;
				}
				.nestedtable-container > .sidebartable {
					border-left: 0px;
					border-top: 0px;
				}
				.nestedtable {
					border: 0px;
					height: 100%;
				}
			</style>";
		}
		++self::$total;
		
		return "{$css}
		<table class='table sidebartable'>
			<tr>
				<td class='tdbgh center b nobr' style='padding-right: 20px'>".$this->linkTitle."</td>
				<td class='tdbgh center b w nobr'>
					".$this->title . $titleSel ."
				</td>
			</tr>
			<tr class='rh'>
				<td class='tdbg1 nobr vatop' style='padding-right: 20px'>{$linksHtml}</td>
				<td class='tdbg2 center nestedtable-container'>
					<table class='table nestedtable'>";
	}
	
	public function DisplayBottom() {
		return "
					</table>
				</td>
			</tr>
		</table>";
	}
	
	public static function GenerateLinks($linkSet, $linkVar = 'type', $sel = 0, &$titleSel) {
		// Generate linksets to highlight selected option
		$out = "";
		
		foreach ($linkSet as $catLabel => $linksCat) {
			if (!$catLabel) {
				// Global
				$prefix = "";
			} else {
				// Named category
				$prefix = "&nbsp;&nbsp;";
				$out .= "<span class='b i'>{$catLabel}</span><br>";
			}
			
			foreach ($linksCat as $id => $linkLabel) {
				$w = 'a';
				if ($sel == $id) { // Selected option is not a link and is also displayed in the title
					$w = 'b';
					$titleSel = " - {$linkLabel}";
				}
				$out .= "{$prefix}<{$w} href='?{$linkVar}={$id}'>{$linkLabel}</{$w}><br>";
			}
		}
		return $out;
	}
	
	public static function Message($text) {
		return "
		<tr><td class='tdbg1 center rh'>{$text}</td></tr>
		<tr><td class='tdbg2 center'>&nbsp;</td></tr>";
	}
}	
