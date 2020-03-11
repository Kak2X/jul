<?php

require "lib/function.php";
require "lib/classes/TreeView.php";

$resource_types = array(
	1 => "Smilies",
	2 => "Post icons",
	3 => "Syndromes",
);

admincheck();

$_GET['id']   = isset($_GET['id']) ? (int)$_GET['id'] : NULL;
$_GET['type'] = filter_int($_GET['type']);
// For pagination (future use?)
//$_GET['page']   = filter_int($_GET['page']);
//$_GET['fpp']    = filter_int($_GET['fpp']);
//if (!$_GET['fpp']) $_GET['fpp'] = 20;


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
				
		$headers = array(
			-1 => [
				'label' => 'Preview',
				'style' => 'width: 100px',
			],
			'code' => [
				'label'     => 'Code',
				'type'      => 'text',
				'editstyle' => 'width: 150px',
				'filter'    => 'html',
			],
			'url' => [
				'label'     => 'Image URL',
				'type'      => 'text',
				'editstyle' => 'width: 500px',
				'filter'    => 'html',
			]
		);
		$values = array();
		foreach ($res as $id => $x) {
			$values[$id] = array(
				-1     => "<img src=\"".escape_attribute($x[1])."\">",
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
				'filter'    => 'html',
			]
		);
		$values = array();
		foreach ($res as $id => $x) {
			$values[$id] = array(
				-1     => "<img src=\"".escape_attribute($x)."\">",
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
			
			if (!parse_color_input($_POST['color']))
				errorpage("You didn't enter a valid syndrome color.");
			
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
				'filter'    => 'html',
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
		$html = "<div class='font center'>Select a resource type from the sidebar.</div>";
		break;
}


if (!isset($html)) {
	$html = row_display($headers, $values, $strings, $_GET['id']); //, $_GET['page'], $_GET['fpp'], count($values));
}



$extramenu = TreeView::ParseSubmenu($resource_types, 'admin-editresources.php?type=');

pageheader("Edit resources");
print adminlinkbar($scriptname, "?type={$_GET['type']}", $extramenu)
. "<form method='POST' action='{$redir_url}".(isset($_GET['id']) ? "&id={$_GET['id']}" : "")."' enctype='multipart/form-data'>"
. $html 
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