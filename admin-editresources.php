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

$redir_url = "?type={$_GET['type']}";

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
		
	
		$editwnd = "";
		if (isset($_GET['id'])) {
			if (!isset($res[$_GET['id']])) {
				$action_name = "Creating a new smiley";
				$cursmil     = array('','');
				$_GET['id']  = -1;
			} else {
				$action_name = "Editing smiley";
				$cursmil     = $res[$_GET['id']];
			}
			$editwnd = "
			<tr class='rh'><td class='tdbgh center b' colspan='4'>{$action_name}</td></tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>Code:</td>
				<td class='tdbg2' colspan='3'><input type='text' name='code' value=\"".htmlspecialchars($cursmil[0])."\" style='width: 150px'></td>
			</tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>Image URL:</td>
				<td class='tdbg2' colspan='3'><input type='text' name='url' value=\"".htmlspecialchars($cursmil[1])."\" style='width: 500px'></td>
			</tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>&nbsp;</td>
				<td class='tdbg2' colspan='3'><input type='submit' name='submit' value='Save and continue'> - <input type='submit' name='submit2' value='Save and close'></td>
			</tr>
			<tr><td class='tdbg2' colspan='4'></td></tr>
			";
		}
	
		
		
		$smilies = "";
		foreach ($res as $id => $x) {
			if (!$x) continue;
			$cell = ($id % 2) + 1;
			$smilies .= "
			<tr class='rh'>
				<td class='tdbg{$cell} center b'>
					<input type='checkbox' name='del[]' value='{$id}'> - <a href='{$redir_url}&id={$id}'>Edit</a>
				</td>
				<td class='tdbg{$cell} center'><img src=\"{$x[1]}\"></td>
				<td class='tdbg{$cell} center'>".htmlspecialchars($x[0])."</td>
				<td class='tdbg{$cell}'>".htmlspecialchars($x[1])."</td>
			</tr>";
		}			
		$html = "
		{$editwnd}
		<tr class='rh'>
			<td class='tdbgh center b' style='width: 100px'>#</td>
			<td class='tdbgh center b' style='width: 100px'>Preview</td>
			<td class='tdbgh center b'>Code</td>
			<td class='tdbgh center b'>Image link</td>
		</tr>
		{$smilies}
		<tr class='rh'>
			<td class='tdbgc' style='border-right: 0'>
				<input type='submit' style='height: 16px; font-size: 10px' name='setdel' value='Delete Selected'>
			</td>
			<td class='tdbgc center' colspan='3'>
				<a href=\"{$redir_url}&id=-1\">&lt; Add a new smiley &gt;</a>
			</td>
		</tr>";
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
		
	
		$editwnd = "";
		if (isset($_GET['id'])) {
			if (!isset($res[$_GET['id']])) {
				$action_name = "Creating a new post icon";
				$curicon     = '';
				$_GET['id']  = -1;
			} else {
				$action_name = "Editing post icon";
				$curicon     = $res[$_GET['id']];
			}
			$editwnd = "
			<tr class='rh'><td class='tdbgh center b' colspan='3'>{$action_name}</td></tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>Image URL:</td>
				<td class='tdbg2' colspan='2'><input type='text' name='url' value=\"".htmlspecialchars($curicon)."\" style='width: 500px'></td>
			</tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>&nbsp;</td>
				<td class='tdbg2' colspan='2'><input type='submit' name='submit' value='Save and continue'> - <input type='submit' name='submit2' value='Save and close'></td>
			</tr>
			<tr><td class='tdbg2' colspan='3'></td></tr>
			";
		}
	
		
		
		$smilies = "";
		foreach ($res as $id => $x) {
			if (!$x) continue;
			$cell = ($id % 2) + 1;
			$smilies .= "
			<tr class='rh'>
				<td class='tdbg{$cell} center b'>
					<input type='checkbox' name='del[]' value='{$id}'> - <a href='{$redir_url}&id={$id}'>Edit</a>
				</td>
				<td class='tdbg{$cell} center'><img src=\"{$x}\"></td>
				<td class='tdbg{$cell}'>".htmlspecialchars($x)."</td>
			</tr>";
		}			
		$html = "
		{$editwnd}
		<tr class='rh'>
			<td class='tdbgh center b' style='width: 100px'>#</td>
			<td class='tdbgh center b' style='width: 100px'>Preview</td>
			<td class='tdbgh center b'>Image link</td>
		</tr>
		{$smilies}
		<tr class='rh'>
			<td class='tdbgc' style='border-right: 0'>
				<input type='submit' style='height: 16px; font-size: 10px' name='setdel' value='Delete Selected'>
			</td>
			<td class='tdbgc center' colspan='2'>
				<a href=\"{$redir_url}&id=-1\">&lt; Add a new post icon &gt;</a>
			</td>
		</tr>";
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
			// TODO: Broken 0 post count syndrome
			if (!in_array(find_syndrome($res, $_POST['postcount']), array(-1, $_GET['id'])))
				errorpage("No post count duplicates allowed.".find_syndrome($res, $_POST['postcount']));
			
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
		
	
		$editwnd = "";
		if (isset($_GET['id'])) {
			if (!isset($res[$_GET['id']])) {
				$action_name = "Creating a new post syndrome";
				$cursyn      = array('0','#FFFFFF',"'Default syndrome' +");
				$_GET['id']  = -1;
			} else {
				$action_name = "Editing post syndrome";
				$cursyn      = $res[$_GET['id']];
			}
			$editwnd = "
			<tr class='rh'><td class='tdbgh center b' colspan='6'>{$action_name}</td></tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>Post count:</td>
				<td class='tdbg2' colspan='5'><input type='text' name='postcount' value=\"".htmlspecialchars($cursyn[0])."\" style='width: 150px'></td>
			</tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>Color:</td>
				<td class='tdbg2' colspan='5'><input type='color' name='color' value=\"".htmlspecialchars($cursyn[1])."\"></td>
			</tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>Text:</td>
				<td class='tdbg2' colspan='5'><input type='text' name='text' value=\"".htmlspecialchars($cursyn[2])."\" style='width: 500px'></td>
			</tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>Options:</td>
				<td class='tdbg2' colspan='5'><label><input type='checkbox' name='enabled' value='1'".(filter_int($cursyn[3]) ? "" : " checked")."> Enabled</label></td>
			</tr>
			<tr class='rh'>
				<td class='tdbg1 center b'>&nbsp;</td>
				<td class='tdbg2' colspan='5'><input type='submit' name='submit' value='Save and continue'> - <input type='submit' name='submit2' value='Save and close'></td>
			</tr>
			<tr><td class='tdbg2' colspan='6'></td></tr>
			";
		}
	
		
		
		$smilies = "";
		foreach ($res as $id => $x) {
			$cell = ($id % 2) + 1;
			$smilies .= "
			<tr class='rh'>
				<td class='tdbg{$cell} center b'>
					<input type='checkbox' name='del[]' value='{$id}'> - <a href='{$redir_url}&id={$id}'>Edit</a>
				</td>
				<td class='tdbg{$cell} center b'><span style='color:#".(!isset($x[3]) ? "0f0'>YES": "f00'>NO")."</span></td>
				<td class='tdbg{$cell} center'>".htmlspecialchars($x[0])."</td>
				<td class='tdbg{$cell} center'>".htmlspecialchars($x[1])."</td>
				<td class='tdbg{$cell}'>".htmlspecialchars($x[2])."</td>
				<td class='tdbg{$cell} fonts'>".str_replace("<br>", "", syndrome($x[0]))."</td>
			</tr>";
		}			
		$html = "
		{$editwnd}
		<tr class='rh'>
			<td class='tdbgh center b' style='width: 100px'>#</td>
			<td class='tdbgh center b' style='width: 50px'>Set</td>
			<td class='tdbgh center b'>Post count</td>
			<td class='tdbgh center b'>Color</td>
			<td class='tdbgh center b'>Text</td>
			<td class='tdbgh center b'>Preview</td>
		</tr>
		{$smilies}
		<tr class='rh'>
			<td class='tdbgc' style='border-right: 0'>
				<input type='submit' style='height: 16px; font-size: 10px' name='setdel' value='Delete Selected'>
			</td>
			<td class='tdbgc center' colspan='5'>
				<a href=\"{$redir_url}&id=-1\">&lt; Add a new post syndrome &gt;</a>
			</td>
		</tr>";
		break;
	default:
		$html = "
		<tr>
			<td class='tdbg1 center rh'>Select a resource type from the sidebar.</td>
		</tr>
		<tr><td class='tdbg2 center'>&nbsp;</td></tr>";
		break;
}


pageheader("Edit resources");
print adminlinkbar()
. "<form method='POST' action='{$redir_url}".(isset($_GET['id']) ? "&id={$_GET['id']}" : "")."' enctype='multipart/form-data'>"
. sidebar_table("Resources", $html, "Resource types", $resource_types, 'type', $_GET['type'])
. auth_tag()
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

// TODO: Move this to function file when needed
// TODO: Replace $html with $part (a number) to determine what part to display
function sidebar_table($title, $html, $linktitle, $linkset, $linkvar = 'type', $sel = 0) {
	static $printed;
	
	// Generate linksets to highlight selected option
	$linkshtml = "";
	$titlesel  = "";
	foreach ($linkset as $catlabel => $linkscat) {
		
		if (!$catlabel) {
			// Global
			$prefix = "";
		} else {
			// Named category
			$prefix = "&nbsp;&nbsp;";
			$linkshtml .= "<span class='b i'>{$catlabel}</span><br>";
		}
		
		foreach ($linkscat as $id => $linklabel) {
			$w = 'a';
			if ($sel == $id) { // Selected option is not a link and is also displayed in the title
				$w = 'b';
				$titlesel = " - {$linklabel}";
			}
			$linkshtml .= "{$prefix}<{$w} href='?{$linkvar}={$id}'>{$linklabel}</{$w}><br>";
		}
	}

	//--
	// Do not print the CSS definitions twice (in case of nested sidebar tables)
	$css = "";
	if ($printed === NULL) {
		$css = "
		<style type='text/css'>
			.rh {height: 19px}
			textarea {display: block}
			.nestedtable-container {
				padding: 0px;
			}
			.nestedtable {
				border: 0px;
				height: 100%;
			}
			.nestedtable tr td:last-child { border-right: 0px; }
			.nestedtable tr:last-child td { border-bottom: 0px; }
		</style>";
		$printed = true;
	}
	//--
	
	return "
	{$css}
	<table class='table'>
		<tr>
			<td class='tdbgh center b nobr' style='padding-right: 20px'>{$linktitle}</td>
			<td class='tdbgh center b w nobr'>
				{$title}{$titlesel}
			</td>
		</tr>
		<tr class='rh'>
			<td class='tdbg1 nobr vatop' style='padding-right: 20px'>{$linkshtml}</td>
			<td class='tdbg2 center nestedtable-container'>
				<table class='table nestedtable' style='border: 0px; height: 100%'>
				{$html}
				</table>
			</td>
		</tr>
	</table>";
}

// TODO: functions to generate the pagination / display / edit dialog
// This is currently repeated over and over and sigh
// Ideally this should also have a JavaScript variant with inline editing
function row_display($headers, $values, $labels, $page = 0, $limit = 20, $sel = 0) {
	return "";
}