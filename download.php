<?php
	$meta['notrack'] = true;
	$meta['cache'] = true;
	
	require "lib/common.php";
	
	if (!$config['attachments-all-origin'] && !$runtime['same-origin']) {
		print "<title>{$config['board-name']}</title>";
		print "To continue to the attachment, click <a href='?{$_SERVER['QUERY_STRING']}'>here</a>.";
		die;
	}
	
	$_GET['t']      = filter_bool($_GET['t']);
		
	// Temporary file mode? 
	if (isset($_GET['tmp'])) {
		$_GET['tmp'] = basename(filter_string($_GET['tmp']));
		$_GET['hash'] = filter_string($_GET['hash']);
		
		if (!file_exists("temp/attach_{$_GET['tmp']}.dat"))
			die("Preview failed.");
		$meta = get_attachment_metadata("temp/attach_{$_GET['tmp']}");
		
		if ($meta['hash'] != $_GET['hash'])
			die("Preview failed.");
		
		//$_GET['t'] = false;
		download($meta, "temp/attach_{$_GET['tmp']}");
		die;
	}

	$_GET['id']     = filter_int($_GET['id']);
	$_GET['info']   = filter_bool($_GET['info']);
	
	if (!$_GET['id']) {
		errorpage("No attachment specified.");
	}
	
	$attachment = $sql->fetchq("SELECT * FROM attachments WHERE id = {$_GET['id']}");
	
	if (!$attachment) {
		errorpage("Cannot download the attachment.<br>Either it doesn't exist or you're not allowed to download it.");
	}
	
	if (!$attachment['pm']) {
		$post = $sql->fetchq("
			SELECT p.id pid, p.deleted, t.id tid, f.id fid, f.minpower
			FROM posts p
			LEFT JOIN threads t ON p.thread = t.id
			LEFT JOIN forums  f ON t.forum  = f.id
			WHERE p.id = {$attachment['post']}
		");
		if (!$ismod && $post && $post['fid']) {
			$ismod = $sql->resultq("SELECT COUNT(*) FROM forummods WHERE forum = {$post['fid']} AND user = {$loguser['id']}");
		}
		
		if (
			   !$post // Post doesn't exist
			|| (!$ismod && $post['deleted']) // Post deleted
			|| (!$ismod && !$post['tid']) // Post in invalid thread 
			|| (!$ismod && !$post['fid']) // Thread in invalid forum
			|| $loguser['powerlevel'] < $post['minpower'] // Can't view forum
			|| !file_exists(attachment_name($_GET['id'])) // File missing
		) {
			errorpage("Cannot download the attachment.<br>Either it doesn't exist or you're not allowed to download it.");
		}
	} else {
		$post = $sql->fetchq("
			SELECT p.id pid, p.deleted, t.id tid
			FROM pm_posts p
			LEFT JOIN pm_threads t ON p.thread = t.id
			WHERE p.id = {$attachment['pm']}
		");
		
		if (
			   !$post // Post doesn't exist
			|| (!$isadmin && $post['deleted']) // Post deleted
			|| (!$isadmin && !$post['tid']) // Post in invalid thread 
			|| (!$isadmin && !$sql->resultq("SELECT COUNT(*) FROM pm_access WHERE user = {$loguser['id']} AND thread = {$post['tid']}")) // Can't view forum
			|| !file_exists(attachment_name($_GET['id'], $_GET['t'])) // File missing
		) {
			errorpage("Cannot download the attachment.<br>Either it doesn't exist or you're not allowed to download it.");
		}
	}
	// All OK!
	
	if ($_GET['info']) {
		echo "<pre>Attachment display:\n\n";
		print_r($attachment);
		die;
	}
	
	$sql->query("UPDATE attachments SET views = views + 1 WHERE id = {$_GET['id']}");

	download($attachment, attachment_name($_GET['id'], $_GET['t']));
	die;
	
	function download($attachment, $path) {
		// Clear out any previous state
		if (ob_get_level()) ob_end_clean();
		
		// Set the correct headers to make this file downloadable
		header("Pragma: public");
		header("Cache-Control: public");
		header('Connection: Keep-Alive');
		header("Content-Security-Policy: script-src none");
		if (!$attachment['is_image']) {
			// Display download box if it isn't an image
			header("Content-Disposition: attachment");
			header('Content-Type: application/octet-stream');
		} else {
			header("Content-type: {$attachment['mime']}");
		}
		header("Content-Description: File Transfer");
		header("Content-Disposition: filename=\"{$attachment['filename']}\"");
		header("Content-Transfer-Encoding: binary");
		if (!$_GET['t'])
			header("Content-Length: {$attachment['size']}");
		
		readfile($path);
	}