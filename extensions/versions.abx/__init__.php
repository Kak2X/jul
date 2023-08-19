<?php

// How to access the page.
// Two methods provided since the canonical 'boardinfo-ver' modifies the footer in a way that can cause issues with multiple extensions.
if (!$xconf['link-type']) {
	hook_add('header-links-2', function() use ($extName) {
		return " - <a href='{$extName}'>Versions</a>";
	});
} else {
	hook_add('boardinfo-ver', function($_, $ver) use ($extName) {
		return "<a href='{$extName}'>{$ver}</a>";
	});
}
