<?php

// TODO: Make the metadata file contain everything instead of checking the file size over and over again
function quikattach($thread, $user, $showpost = NULL, $sel = NULL) {
	global $config, $numdir, $sql;
	
	if (!$config['allow-attachments']) {
		return "";
	}
	
	$cnt = get_attachments_index($thread, $user);
	// Existing attachments
	$out = "";
	$sizetotal = 0;
	for ($i = 0; $i < $cnt; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		$cell = ($i % 2) + 1;
		$size = filesize($path);
		$out .= "
		<tr>
			<td class='tdbg{$cell}'>
				".htmlspecialchars(file_get_contents("{$path}.dat"))."
			</td>
			<td class='tdbg{$cell}'>".sizeunits($size)."</td>
			<td class='tdbg{$cell}'>
				<input type='checkbox' name='remove{$i}' value=1>
				<label for='remove{$i}'>Remove</a><br>
			</td>
		</tr>";
		
		$sizetotal += $size;
	}
	
	$out_conf = "";
	if ($showpost !== NULL) {
		$j = $i;
		// Show uploaded attachments from a certain post
		// Used in editpost
		$attach = get_saved_attachments($showpost);
		if ($attach) {
			$out_conf .= "<tr><td class='tdbgh center b' colspan=3>Files uploaded</td></tr>";
		}
		foreach ($attach as $x) {
			$cell = ($j % 2) + 1;
			
			if (!isset($sel[$x['id']])) {
				$sizetotal += $x['size'];
				$delmark = "";
			} else {
				// Deletion mark
				$delmark = " style='text-decoration: line-through'";
			}
			
			$out_conf .= "
			<tr>
				<td class='tdbg{$cell}'{$delmark}>
					{$x['filename']}
				</td>
				<td class='tdbg{$cell}'{$delmark}>".sizeunits($x['size'])."</td>
				<td class='tdbg{$cell}'>
					<input type='checkbox' name='removec{$x['id']}' value=1 ".filter_string($sel[$x['id']]).">
					<label for='removec{$x['id']}'>Remove</a><br>
				</td>
			</tr>";			
			
			$j++;
		}
		
	}
	
	return "".
"<tr>
	<td class='tdbg1 center b'>
		Attachments:
	</td>
	<td class='tdbg2' colspan=2>
		<table class='table' style='border: none !important; width: auto !important'>
			<tr><td class='tdbgh center b' colspan=3>Files to upload</td></tr>
			<tr>
				<td class='tdbgh center'>Filename</td>
				<td class='tdbgh center'>File size</td>
				<td class='tdbgh center'></td>
			</tr>
			{$out}
			{$out_conf}
			<tr>
				<td class='tdbgc center b'>Total</td>
				<td class='tdbgc center b' colspan=2>
					".sizeunits($sizetotal)."/".sizeunits($config['attach-max-size'])."
				</td>
			</tr>
			<tr>
				<td class='tdbg2' colspan=3>
					<img src='images/{$numdir}bar-on.gif' style='height:8px; width:".ceil($sizetotal * 100 / $config['attach-max-size'])."%'>
				</td>
			</tr>
			<tr>
				<td colspan=3>
					<input type='file' class='w' name='attachment{$i}'>
				</td>
			</tr>
		</table>
		
	</td>
</tr>";
}

function attachdisplay($id, $filename, $size, $views, $is_image = false, $imgprev = NULL, $editmode = false) {

	if ($is_image) { // An image
		$thumb = ($imgprev !== NULL ? $imgprev : attachment_name($id, true));
	} else { // Not an image
		$thumb = "images/defaultthumb.png";
	}
	$controls = "";
	/*
	$controls = $editmode ? 
		"<a href='attachment.php?id={$id}&action=edit'>Edit</a> - ".
		"<a href='attachment.php?id={$id}&action=delete'>Delete</a> "
		: "";*/
	
	// id 0 is a magic value used for post previews
	$w = $id ? 'a' : 'b';
	
	return "
	<table class='attachment-box'>
		<tr>
		<td class='attachment-box-thumb' rowspan=2>
			<$w href='download.php?id={$id}'><img src='{$thumb}'></$w>
		</td>
		<td class='attachment-box-text fonts'>
			<div><$w href='download.php?id={$id}'>{$filename}</$w></div>
			<div>Size:<span style='float: right'>".sizeunits($size)."</span></div>
			<div>Views:<span style='float: right'>{$views}</span></div>
		</td>
	</tr>
	<tr>
		<td class='attachment-box-controls fonts right'>
			{$controls}
		</td>
	</tr>
	</table>";
	
	

}

// on preview, uploaded files are saved on temp/attach_<thread>_<user>_<i>
// once confirmed, they are simply identified by index

// Assumes to receive an array of elements fetched off the DB
function attachfield($list, $editmode = false) {
	$out = "";
	foreach ($list as $k => $x) {
		if (!isset($x['imgprev'])) $x['imgprev'] = NULL; // and this, which is only passed on post previews
		$out .= attachdisplay($x['id'], $x['filename'], $x['size'], $x['views'], $x['is_image'], $x['imgprev'], $editmode);
	}
	/* if ($editmode) {
		$out .= "
		<table class='attachment-box-addnew fonts'>
			<tr>
				<td>
					<a href='attachment.php?action=add'><big>[+]</big><br/><br/>Add attachment</a>
				</td>
			</tr>
		</table>";
	}*/
	return "<br/><br/><fieldset><legend>Attachments</legend>{$out}</fieldset>";
}


// Update the status of temp attachments after submit/preview is clicked
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
	if (
		   !filter_int($_POST["remove{$i}"])   // Make sure it's not marked to be removed beforehand
		&& isset($_FILES["attachment{$i}"])    // The attachment should exist
		&& !$_FILES["attachment{$i}"]['error'] // ""
	) {
		upload_attachment($_FILES["attachment{$i}"], $key, $user, $total, $extrasize);
		++$total;
	}
	return $total;
}


function get_saved_attachments($post) {
	global $sql;
	return $sql->getarray("
		SELECT a.post, a.id, a.filename, a.size, a.views, a.is_image
		FROM attachments a
		WHERE a.post = {$post}"
	, mysql::USE_CACHE);
}



function process_saved_attachments($post) {
	// Mark the already confirmed attachment just for deletion (will be properly removed on submit)
	// Recycle the same query which will be used later in quikattach()
	$extrasize = 0;
	$attach = get_saved_attachments($post);
	foreach ($attach as $x) {
		if (isset($_POST["removec{$x['id']}"])) {
			$attachsel[$x['id']] = 'checked';
		} else {
			$extrasize += $x['size']; // get_attachment_size() works for temp attachments only, so calculate the extra here
		}
	}
	return ['size' => $extrasize, 'del' => $attachsel];
}

function filter_attachments($list, $sel) {
	$filtered = array();
	// Include in the preview anything which isn't marked as deleted
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
	// Preserve given filename to an identically named .dat file
	file_put_contents("{$path}.dat", $file['name']);
	
	// Move the file and THEN generate the thumbnail
	$res = move_uploaded_file($file['tmp_name'], $path);
	
	list($width, $height) = getimagesize($path);
	$is_image = ($width && $height);
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
function save_attachments($thread, $user, $post_id) {
	global $sql;
	for ($i = 0; true; ++$i) {
		$path = attachment_tempname($thread, $user, $i);
		if (!file_exists($path)){
			break;
		}
		
		// Fill out extra metadata
		if (file_exists("{$path}_t")) {
			list($width, $height) = getimagesize("{$path}_t");
			$is_image = ($width && $height);
		} else {
			$is_image = false;
		}
		
		$sqldata = [
			'post'     => $post_id,
			'user'     => $user,
			'mime'     => mime_content_type($path),
			'filename' => file_get_contents("{$path}.dat"),
			'size'     => filesize($path),
			'views'    => 0,
			'is_image' => $is_image,
		];
		
		$sql->queryp("INSERT INTO attachments SET ".mysql::setplaceholders($sqldata), $sqldata);
		
		$rowid = $sql->insert_id();
		
		// Move the thumbnail we previously generated off the temp folder
		if ($is_image) {
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
		$is_image = file_exists("{$path}_t"); // Can cheat this one
		$res[] = [
			'id'       => 0,
			'filename' => file_get_contents("{$path}.dat"),
			'size'     => filesize($path), // File size
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

function remove_attachments($list, $post = NULL) {
	global $sql;
	if ($post !== NULL) {
		$sql->query("DELETE FROM attachments WHERE post = {$post}");
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