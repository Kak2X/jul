<?php

hook_add('header-links-2', function() use ($extName) {
	return " - <a href='{$extName}'>Versions</a>";
});
