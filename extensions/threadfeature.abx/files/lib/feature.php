<?php

	function feature_thread($id, $change = true, $archive = true) {
		global $sql;
		if ($change) 
			$sql->query("UPDATE threads SET featured = 1 WHERE id = {$id}");
		if ($archive) 
			$sql->query("INSERT INTO threads_featured (thread, date, enabled) VALUES ({$id}, ".time().", 1) ON DUPLICATE KEY UPDATE date = VALUES(date), enabled = 1");
	}
	
	function unfeature_thread($id, $change = true, $archive = true, $hard = false) {
		global $sql;
		if ($change)
			$sql->query("UPDATE threads SET featured = 0 WHERE id = {$id}");
		if ($archive) { 
			if (!$hard)
				$sql->query("UPDATE threads_featured SET enabled = 0 WHERE thread = {$id}");
			else
				$sql->query("DELETE FROM threads_featured WHERE thread = {$id}");
		}
	}