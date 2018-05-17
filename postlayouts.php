<?php

	require "lib/function.php";
	
	$_GET['id'] = filter_int($_GET['id']);
	if (!$_GET['id']) {
		errorpage("No user selected.");
	}
	
	
	pageheader("Post layouts");
	
	$user = $sql->fetchq("SELECT * FROM users u WHERE u.id = {$_GET['id']}");
	if (!$user) {
		errorpage("This user doesn't exist!");
	}
	
	
	if (isset($_POST['submit'])) {
		$user['postheader'] = filter_string($_POST['postheader']);
		$user['signature']  = filter_string($_POST['signature']);
		$user['css']        = filter_string($_POST['css']);
	}
	
	// So that the layout shows up
	$loguser['viewsig'] = 1;
	$blockedlayouts = array();
	
	
	$data = array(
		// Text
		'message' => "Sample text. [quote=fhqwhgads]A sample quote, with a <a href=about:blank>link</a>, for testing your layout.[/quote]This is how your post will appear.",
		'head'    => $user['postheader'],
		'sign'    => $user['signature'],
		'css'     => $user['css'],
		// Post metadata
		'id'      => 0,
		'forum'   => 0,
		'ip'      => $_SERVER['REMOTE_ADDR'],
	//	'num'     => 0,
		'date'    => ctime(),
		// (mod) Options
		'nosmilies' => 0,
		'nohtml'    => 0,
		'nolayout'  => 0,
		'moodid'    => 0,
		'noob'      => 0,
		'revision'  => 0,
		// Attachments
		'attach_key' => NULL,
		//'attach_sel' => $attachsel,
	);
	
?>
<form method="POST" action="?id=<?= $_GET['id'] ?>">
<?= preview_post($user, $data, PREVIEW_EDITED) ?>
<table class="table">
	<tr><td class="tdbgh center b" colspan=2><?= getuserlink($user) ?>'s post layout</td></tr>
	<tr>
		<td class="tdbg1 center b">CSS:</td>
		<td class="tdbg1 vatop">
			<textarea wrap="virtual" id="css" name="css" rows=8 cols=<?=$numcols?> style="resize:vertical; min-height: 150px"><?= htmlspecialchars($user['css']) ?></textarea>
		</td>
	</tr>
	<tr>
		<td class="tdbg1 center b">Header:</td>
		<td class="tdbg1 vatop">
			<textarea wrap="virtual" id="postheader" name="postheader" rows=8 cols=<?=$numcols?> style="resize:vertical; min-height: 150px"><?= htmlspecialchars($user['postheader']) ?></textarea>
		</td>
	</tr>
	<tr>
		<td class="tdbg1 center b">Signature:</td>
		<td class="tdbg1 vatop">
			<textarea wrap="virtual" id="signature" name="signature" rows=8 cols=<?=$numcols?> style="resize:vertical; min-height: 150px"><?= htmlspecialchars($user['signature']) ?></textarea>
		</td>
	</tr>
	<tr>
		<td class="tdbg1"></td>
		<td class="tdbg1">
			<!--<span id="jsbtn"><input type="button" value="Preview CSS" onclick="quickpreview()"> - </span>-->
			<input type="submit" name="submit" value="Preview All">
		</td>
	</tr>
</table>
</form>
<?php
/*
<noscript><style>#jsbtn{display:none}</style></noscript>
		
<script type="text/javascript">
	// :rolleyes:
	var css  = document.getElementById('css');
	var user = <?= $_GET['id'] ?>;
	var css_dest  = document.getElementById('css0');
	//var head = document.getElementById('postheader');
	//var sign = document.getElementById('signature');
	
	var dest_css = document.getElementById('css0');
	function quickpreview() {
		css_dest.innerHTML = css.innerHTML.replace('/\.(top|side|main|cont)bar'+user+'/i', '.$1bar'+user+'_p0');
	}
	
</script>
*/
	
	
	
	pagefooter();