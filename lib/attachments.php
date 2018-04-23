<?php

/*
	Attachment field HTML
	$thread   - Thread ID
	$user     - User ID (used for attachment key)
	$showpost - Post ID (to show uploaded attachments in the list)
	$sel      - Array with attachment IDs marked for deletion
	$pm       - If true, attachment keys are handled in PM mode
*/ 
function quikattach($thread, $user, $showpost = NULL, $sel = NULL, $pm = false) {
	global $config, $numdir, $sql;
	
	if (!$config['allow-attachments'] || !$user) {
		return "";
	}
	
	$listtemp = $listreal = "";
	
	// Display removal options for temp attachments
	$cnt = get_attachments_index($thread, $user);
	if ($cnt) {
		$listtemp = "<tr><td class='tdbgc center b' colspan=3>Files to upload</td></tr>";
	}
	$sizetotal = 0;
	for ($i = 0; $i < $cnt; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		$cell = ($i % 2) + 1;
		$metadata = get_attachment_metadata($path);
		$listtemp .= "
		<tr>
			<td class='tdbg{$cell}'>".htmlspecialchars($metadata['filename'])."</td>
			<td class='tdbg{$cell}'>".sizeunits($metadata['size'])."</td>
			<td class='tdbg{$cell}'>
				<label><input type='checkbox' name='remove{$i}' value=1> Remove</label>
			</td>
		</tr>";
		
		$sizetotal += (int) $metadata['size'];
	}
	
	// Display removal options for real attachments associated to the post (when editing a post)

	if ($showpost !== NULL) {
		$j = $i;
		$attach = get_saved_attachments($showpost, $pm);
		if ($attach) {
			$listreal = "<tr><td class='tdbgc center b' colspan=3>Files uploaded</td></tr>";
		}
		foreach ($attach as $x) {
			$cell = ($j % 2) + 1;
			if (!isset($sel[$x['id']])) {
				$sizetotal += $x['size']; // Do not count attachments removed from the total
				$delmark = "";
			} else { // Deletion mark
				$delmark = " style='text-decoration: line-through'";
			}
			$listreal .= "
			<tr>
				<td class='tdbg{$cell}'{$delmark}>".htmlspecialchars($x['filename'])."</td>
				<td class='tdbg{$cell}'{$delmark}>".sizeunits($x['size'])."</td>
				<td class='tdbg{$cell}'>
					<label><input type='checkbox' name='removec{$x['id']}' value=1 ".filter_string($sel[$x['id']])."> Remove</label>
				</td>
			</tr>";			
			
			$j++;
		}
		
	}
	
	// Display fields option
	// Doesn't cut out attachments above the limit if they are already uploaded
	$numfields  = max(1, filter_int($_POST['attachfields']));
	$uploadinpt = "";
	do {
		$uploadinpt .= "<input type='file' class='w' name='attachment{$i}'><br/>";
		++$i;
	} while ($i < $numfields);
	
	return "
<tr>
	<td class='tdbg1 center b'>Attachments:</td>
	<td class='tdbg2' colspan=2>
		<table class='table' style='border: none !important; width: auto !important'>
			<tr>
				<td class='tdbgh center b'>File name</td>
				<td class='tdbgh center b'>Size</td>
				<td class='tdbgh center'></td>
			</tr>
			{$listtemp}
			{$listreal}
			<tr>
				<td class='tdbgc center b'>Total</td>
				<td class='tdbgc center b' colspan=2>".sizeunits($sizetotal)."/".sizeunits($config['attach-max-size'])."</td>
			</tr>
			<tr>
				<td class='tdbg2' colspan=3>
					".drawminibar($config['attach-max-size'], 8, $sizetotal, "images/bar/{$numdir}bar-on.png")."
				</td>
			</tr>
			<tr>
				<td colspan=3>
					Show <input type='text' name='attachfields' class='right' value='{$numfields}' size='5' maxlength='2'> fields
					{$uploadinpt}
				</td>
			</tr>
		</table>
	</td>
</tr>";
}

// Assumes to receive an array of elements fetched off the DB
function attachfield($list, $extra = "") {
	$out = "";
	foreach ($list as $k => $x) {
		if (!isset($x['imgprev'])) $x['imgprev'] = NULL; // and this, which is only passed on post previews
		
		if ($x['is_image']) { // An image
			$thumb = isset($x['imgprev']) ? $x['imgprev'] : attachment_name($x['id'], true);
		} else { // Not an image
			$thumb = "images/defaultthumb.png";
		}
		
		// id 0 is a magic value used for post previews
		$w = $x['id'] ? 'a' : 'b';
		
		$out .= "
		<table class='attachment-box'>
			<tr>
				<td class='attachment-box-thumb'>
					<$w href='download.php?id={$x['id']}{$extra}'><img src='{$thumb}'></$w>
				</td>
				<td class='attachment-box-text fonts'>
					<div><$w href='download.php?id={$x['id']}{$extra}'>{$x['filename']}</$w></div>
					<div>Size:<span style='float: right'>".sizeunits($x['size'])."</span></div>
					<div>Views:<span style='float: right'>{$x['views']}</span></div>
				</td>
			</tr>
		</table>";
	}
	return "<br/><br/><fieldset><legend>Attachments</legend>{$out}</fieldset>";
}

const ATTACH_PM     = 0b1;  // Handle attachments for PMs, not posts.
const ATTACH_INCKEY = 0b10; // Use incremented key mode (attachments are not shared between tabs of the same page)
function process_attachments(&$key, $user, $post = 0, $flags = 0) {
	
	 // Special mode where $key is handled as an incomplete "base key"
	if ($flags & ATTACH_INCKEY) {
		$attachids    = get_attachments_key($key, $user); // Get the base key to identify the correct files
		$attach_id    = $attachids[0]; // Cached ID to safely reuse attach_key across requests
		$key          = $attachids[1]; // String (base) key for file names
	}
	
	$mode = ($flags & ATTACH_PM) ? 'pm' : false;
	if ($post) { // Edit post mode
		$savedata  = process_saved_attachments($post, $mode); // MOVE FUNCTION
		$extrasize = $savedata['size'];
		$attachsel = $savedata['del'];	
	} else {
		$extrasize = 0;
		$attachsel = array();
	}
	
	// Total attachments returned:
	$attach_count = process_temp_attachments($key, $user, $extrasize); // MOVE FUNCTION	
	
	if ($flags & ATTACH_INCKEY) { // If some attachments exist, confirm the key for the next page
		if ($attach_count) {
			$input_tid = save_attachments_key($attach_id); // MOVE FUNCTION
		} else {
			$input_tid = "";
		}
		return $input_tid;
	}
	return $attachsel;
}

function confirm_attachments($key, $user, $post = 0, $flags = 0, $remove = array()) {
	if ($remove) {
		remove_attachments(array_keys($remove));
	}
	$mode = ($flags & ATTACH_PM) ? 'pm' : 'post';
	save_attachments($key, $user, $post, $mode); // MOVE FUNCTION
}

// Update the status of temp attachments after submit/preview is clicked
// Returns the resulting number of attachments
function process_temp_attachments($key, $user, $extrasize = 0) {
	// Get the total number of attachments / first free slot
	$total = get_attachments_index($key, $user);
	
	// Check if we're removing any temp attachments...
	$list = array();
	for ($i = 0; $i < $total; ++$i) {
		if (filter_int($_POST["remove{$i}"])) {
			$list[] = $i;
		}
	}
	
	// ...then mass-remove them at once
	if (!empty($list)) {
		remove_temp_attachments($key, $user, $list);
		$total -= count($list);
	}
	
	// Upload current attachment
	$showopt = max(1, filter_int($_POST['attachfields']));
	do {
		if (
			   !filter_int($_POST["remove{$i}"])   // Make sure it's not marked to be removed beforehand
			&& isset($_FILES["attachment{$i}"])    // The attachment should exist
			&& !$_FILES["attachment{$i}"]['error'] // same deal
		) {
			upload_attachment($_FILES["attachment{$i}"], $key, $user, $total, $extrasize);
			++$total;
		}
		++$i;
	} while ($i < $showopt);
	
	return $total;
}


function get_saved_attachments($post, $pm = false) {
	global $sql;
	return $sql->getarray("
		SELECT a.post, a.pm, a.id, a.filename, a.size, a.views, a.is_image
		FROM attachments a
		WHERE a.".($pm ? "pm" : "post")." = {$post}"
	, mysql::USE_CACHE);
}

function get_saved_attachments_ids($post, $pm = false) {
	global $sql;
	if (!$post) {
		return [];
	} else if (is_array($post)) {
		return $sql->getresults("SELECT id FROM attachments WHERE ".($pm ? "pm" : "post")." IN (".implode(',', $post).")");
	} else {
		return $sql->getresults("SELECT id FROM attachments WHERE ".($pm ? "pm" : "post")." = {$post}");
	}
}



function process_saved_attachments($post, $pm = false) {
	// Mark the already confirmed attachment just for deletion (will be properly removed on submit)
	// Recycle the same query which will be used later in quikattach()
	$extrasize = 0;
	$attach = get_saved_attachments($post, $pm);
	$attachsel = array();
	foreach ($attach as $x) {
		if (isset($_POST["removec{$x['id']}"])) {
			$attachsel[$x['id']] = 'checked';
		} else {
			$extrasize += $x['size']; // get_attachment_size() works for temp attachments only, so calculate the extra here
		}
	}
	return ['size' => $extrasize, 'del' => $attachsel];
}

// Remove from the attachment $list anything marked in $sel
// $list should be the saved attachment list, $sel the removal list
function filter_attachments($list, $sel) {
	$filtered = array();
	foreach ($list as $k => $val) {
		if (!isset($sel[$val['id']])) {
			$filtered[] = $list[$k];
		}
	}
	return $filtered;
}

// Upload to the temp area
// file_id should be sequential
function upload_attachment($file, $thread, $user, $file_id, $extra = 0) {
	global $config;
	
	if (!$file['size']) 
		errorpage("This is an 0kb file");
	if (get_attachments_size($thread, $user, $file['size'] + $extra) > $config['attach-max-size'])
		errorpage("The file you're trying to upload is over the file size limit.");	
	
	$path = attachment_tempname($thread, $user, $file_id);
	
	// Move the file and THEN generate the thumbnail
	$res = move_uploaded_file($file['tmp_name'], $path);
	
	list($width, $height) = getimagesize($path);
	$is_image = ($width && $height);
	
	// but first, get the metadata out of the way
	$metadata = array(
		'filename' => str_replace(array("\r", "\n"), '', $file['name']),
		'mime'     => mime_content_type($path),
		'size'     => $file['size'],
		'width'    => (int) $width,
		'height'   => (int) $height,
		'is_image' => (int) $is_image,
	);
	write_attachment_metadata($path, $metadata);
	
	
	// Generate a thumbnail
	if ($is_image) {
		$src_image = imagecreatefromstring(file_get_contents($path));
		if ($src_image) {
			$dst_image = resize_image($src_image, 80, 80);
		}
		if (!$src_image || !$dst_image) {
			// source image not found or resize error
			$dst_image = imagecreatefrompng("images/thumbnailbug.png");
		}
		imagedestroy($src_image);
		imagepng($dst_image, "{$path}_t");
		imagedestroy($dst_image);
	}
	
	return $res;
}

// Check if any current attachments are in the temp folder
// and move them to the proper attachment folder and save to the DB
function save_attachments($thread, $user, $post_id, $assoc = 'post') {
	global $sql;
	for ($i = 0; true; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		if (!file_exists($path)){
			break;
		}
		
		// Save the metadata to the database
		$metadata = get_attachment_metadata($path);
		$sqldata = [
			$assoc     => $post_id,
			'user'     => $user,
			'mime'     => $metadata['mime'],
			'filename' => $metadata['filename'],
			'size'     => (int) $metadata['size'],
			'views'    => 0,
			'is_image' => (int) $metadata['is_image'],
		];
		
		$sql->queryp("INSERT INTO attachments SET ".mysql::setplaceholders($sqldata), $sqldata);
		
		$rowid = $sql->insert_id();
		
		// Move the thumbnail we previously generated off the temp folder
		if ((int) $metadata['is_image']) {
			rename("{$path}_t", attachment_name($rowid, true));
		}
		rename($path, attachment_name($rowid));
		unlink("{$path}.dat");
	}
}

// For attachdisplay
function get_temp_attachments($thread, $user) {
	$cnt = get_attachments_index($thread, $user);
	$res = array();
	for ($i = 0; $i < $cnt; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		$metadata = get_attachment_metadata($path);
		$is_image = (int) $metadata['is_image']; //file_exists("{$path}_t"); // Can cheat this one
		$res[] = [
			'id'       => 0,
			'filename' => $metadata['filename'],
			'size'     => (int) $metadata['size'], // File size
			'views'    => 0,
			'is_image' => $is_image,
			'imgprev'  => $is_image ? "data:".mime_content_type("{$path}_t").";base64,".base64_encode(file_get_contents("{$path}_t")) : NULL, // Image preview hack
		];
	}
	return $res;
}

function remove_temp_attachments($thread, $user, $list) {
	$max = get_attachments_index($thread, $user); // Get this before it's too late
	// Remove attachments
	foreach ($list as $i) {
		$path = attachment_tempname($thread, $user, $i);
		unlink($path);
		unlink($path.'.dat');
		if (file_exists($path.'_t')) {
			unlink($path.'_t');
		}
		$del[$i] = true; // Removed elements
	}
	
	// Reorder the list since it's expected to not have any holes
	for ($i = $offset = 0; $i < $max; ++$i) {
		if (isset($del[$i])) {
			++$offset; // File deleted, add 1 to rename offset
		} else if ($offset) {
			$src_path  = attachment_tempname($thread, $user, $i);
			$dest_path = attachment_tempname($thread, $user, $i - $offset);
			
			rename($src_path, $dest_path); // Main file
			rename("{$src_path}.dat", "{$dest_path}.dat"); // Metadata
			if (file_exists("{$src_path}_t")) {
				rename("{$src_path}_t", "{$dest_path}_t"); // Thumbnail
			}

		}
	}
}

function remove_attachments($list, $post = NULL, $field = 'post') {
	global $sql;
	if ($post !== NULL) {
		$sql->query("DELETE FROM attachments WHERE {$field} = {$post}");
	} else {
		$sql->query("DELETE FROM attachments WHERE id IN (".implode(',', $list).")");
	}
	foreach ($list as $id) {
		unlink(attachment_name($id));
		$thumbpath = attachment_name($id, true);
		if (file_exists($thumbpath)) {
			unlink($thumbpath);
		}
	}
}

// Get the total size of all attachments uploaded in the temp area
// and in the actual area too
function get_attachments_size($thread, $user, $extra = 0) {
	$size = $extra;
	for ($i = 0; true; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		if (!file_exists($path)) {
			return $size;
		}
		$size += filesize($path);
	}
}

function get_attachments_index($thread, $user) {
	for ($i = 0; true; ++$i) {
		if (!file_exists(attachment_tempname($thread, $user, $i))) {
			return $i;
		}
	}
}

// $i is used to differentiate between attachments from different threads by the same user
// this is to make sure temp attachments aren't shared, even by the same user
function get_attachments_request_id($keyformat, $user) {
	$i = -1;
	do {
		++$i;
		$set = glob("temp/attach_{$keyformat}_{$i}_{$user}_*", GLOB_NOSORT);
		#echo "temp/attach_{$keyformat}_{$i}_{$user}_*<br/>";
		#print_r($set);
	} while($set);
	return $i;
}

function get_attachments_key($keyformat, $user) {
	if (!isset($_POST['attach_id'])) {		
		$callid = get_attachments_request_id("c{$keyformat}", $user); // No id acquired yet? Get the first available id then.
	} else {
		$callid = (int) $_POST['attach_id'];
	}
	#echo $callid;
	return [$callid, "c{$keyformat}_{$callid}"];
}
function save_attachments_key($key) {
	return "<input type='hidden' name='attach_id' value='{$key}'>";
}

function attachment_name ($id, $thumb = false) { return "attachments/".($thumb ? "t/{$id}.png" : "f/{$id}"); }
function attachment_tempname ($thread, $user, $file_id) { return "temp/attach_{$thread}_{$user}_{$file_id}"; }

function write_attachment_metadata($path, $data) {
	$output = "";
	foreach ($data as $key => $val) {
		$output .= "{$key}={$val}".PHP_EOL;
	}
	file_put_contents("{$path}.dat", $output);
}
function get_attachment_metadata($path) {
	$output = array();
	$h = fopen("{$path}.dat", 'r');
	while (($x = fgets($h)) !== false) {
		$pos = strpos($x, '=');
		$output[substr($x, 0, $pos)] = rtrim(substr($x, $pos+1));
	}
	return $output;
}


function sizeunits($bytes) {
	static $sizes = ['B', 'KB', 'MB', 'GB'];
	for ($i = $sbar = 1; $i < 5; ++$i, $sbar *= 1024) { // $sbar defines the size multiplier
		if ($bytes < $sbar * 1024) {
			// only .00 is really worthless to know so cut that out
			return $qseconds = str_replace('.00', '', sprintf("%04.2f", $bytes / $sbar)).' '.$sizes[$i-1];
		}
	}
}

function resize_image($image, $max_width, $max_height) {
	// Determine thumbnail size based on the aspect ratio
	$width     = imagesx($image);
	$height    = imagesy($image);
	
	// TODO: This obliterates the transparency for thumbnails. Fix this.
	// Don't bother if the image is already under the limits
	if ($width <= $max_width && $height <= $max_height) {
		$dst_image = imagecreatetruecolor($width, $height);
		imagecopy($dst_image, $image, 0, 0, 0, 0, $width, $height);
	} else {
		$ratio     = $width / $height;
		if ($ratio > 1) { // width > height
			$n_width    = $max_width;
			$n_height   = round($height * $max_width / $width);
		} else {
			$n_width    = round($width * $max_height / $height);
			$n_height   = $max_height;
		}
		
		$dst_image = imagecreatetruecolor($n_width, $n_height);
		imagecopyresampled($dst_image, $image, 0, 0, 0, 0, $n_width, $n_height, $width, $height);
	}
	return $dst_image;
}

function upload_error($file) {
	$err = filter_int($file['error']);
	$x = "Sorry, but the file could not be uploaded.<br/>";
	switch ($err) {
		case 0: return false;
		case 1: errorpage("{$x}The file you're trying to upload is too large."); // over php.ini
		case 2: errorpage("{$x}The file you're trying to upload is too large."); // over hidden tag
		case 3: errorpage("{$x}The file wasn't uploaded properly."); // partial upload
		case 4: errorpage("{$x}No file was selected."); // blank file input
		case 5: errorpage("{$x}An internal PHP error occurred."); // oops
		case 6: errorpage("{$x}An internal PHP error occurred."); // oops
		case 7: errorpage("{$x}An internal PHP error occurred."); // oops
		case 8: errorpage("{$x}An internal PHP error occurred."); // oops
		default: errorpage("Unknown error (id #{$err}).");
	}
}

function load_attachments($searchon, $min, $ppp, $mode = MODE_POST) {
	global $sql;
	if ($mode == MODE_ANNOUNCEMENT) { // heh welp
		return array();
		// TODO: Fix this
		return $sql->fetchq("
			SELECT p.id post, a.id, a.filename, a.size, a.views, a.is_image, MIN(p.id) pid
			FROM threads t
			LEFT JOIN posts       p ON p.thread = t.id
			LEFT JOIN attachments a ON p.id     = a.post
			WHERE {$searchon} AND a.id IS NOT NULL
			GROUP BY t.id
			ORDER BY p.date DESC
			LIMIT {$min},{$ppp}
		", PDO::FETCH_GROUP, mysql::FETCH_ALL);
	}
	
	if ($mode == MODE_PM) {
		$prefix = "pm_";
		$match  = "pm";
	} else {
		$prefix = "";
		$match  = "post";
	}
	return $sql->fetchq("
		SELECT p.id post, a.id, a.filename, a.size, a.views, a.is_image
		FROM {$prefix}posts p
		LEFT JOIN attachments a ON p.id = a.{$match}
		WHERE {$searchon} AND a.id IS NOT NULL
		ORDER BY p.id ASC
		LIMIT {$min},{$ppp}
	", PDO::FETCH_GROUP, mysql::FETCH_ALL);
}