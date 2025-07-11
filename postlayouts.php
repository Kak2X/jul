<?php

	require "lib/common.php";
	
	$_GET['id']          = isset($_GET['id']) ? (int)$_GET['id'] : $loguser['id'];
	$_COOKIE['plp_aupd'] = filter_int($_COOKIE['plp_aupd']);
	
	if (!$_GET['id']) {
		errorpage("You need to be logged in to do this."); // close enough
	}
	


	
	$user = $sql->fetchq("SELECT * FROM users u WHERE u.id = {$_GET['id']}");
	if (!$user) {
		errorpage("This user doesn't exist!");
	}
	
	define('CAN_SAVE_LAYOUT', $isadmin || ($loguser['id'] == $_GET['id'] && !$user['profile_locked']));
	
	pageheader("Post layout ". (CAN_SAVE_LAYOUT ? "editor" : "viewer") . " - {$user['name']}");
	
	// Fake out the $user values when submitting the form, including when saving the changes.
	// The latter is convenient so we don't have to recalculate shit like sidebartype twice.
	if (isset($_POST['submit']) || isset($_POST['save'])) {
		$user['postheader']  = filter_string($_POST['postheader']);
		$user['signature']   = filter_string($_POST['signature']);
		if (!($user['css'] = parse_css_upload($_FILES['cssfile'])))
			$user['css'] = filter_string($_POST['css']);
		$user['sidebar']     = filter_string($_POST['sidebar']);
		$user['sidebartype'] = sidebartype_db($_POST['sidebartype'], $_POST['sidebarcell']);
		$loguser['layout']   = filter_int($_POST['tlayout']);
	} else {
		// Force extended layout by default
		$loguser['layout'] = 6;
	}
	
	if (isset($_POST['save'])) {
		if (!CAN_SAVE_LAYOUT)
			errorpage("You aren't allowed to do this!");
	
		$set = [
			'postheader'  => $user['postheader'],
			'signature'   => $user['signature'],
			'css'         => $user['css'],
		];
		
		// Only Normal+ and above can save sidebar changes
		if ($issuper) {
			$set['sidebar'] = $user['sidebar'];
			$set['sidebartype'] = $user['sidebartype'];
		}
		
		$sql->queryp("UPDATE users SET ".mysql::setplaceholders($set)." WHERE id = {$_GET['id']}", $set);
		
		if ($_GET['id'] == $loguser['id']) {
			errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for editing your post layout.","postlayouts.php?id={$_GET['id']}",'view your post layout',0);
		} else { 
			errorpage("Thank you, ".htmlspecialchars($loguser['name']).", for editing this post layout.","postlayouts.php?id={$_GET['id']}","view {$user['name']}'s post layout",0);
		}
	}
	
	$_POST['moodid'] = filter_int($_POST['moodid']);
	
	// So that the layout shows up
	$loguser['viewsig'] = 1;
	$blockedlayouts = array();
	

	
	$data = array(
		// Text
		'message'     => "Sample text. [quote=fhqwhgads]A sample quote, with a <a href=about:blank>link</a>, for testing your layout.[/quote]This is how your post will appear.",
		'head'        => $user['postheader'],
		'sign'        => $user['signature'],
		'css'         => $user['css'],
		'sidebar'     => $user['sidebar'],
		'sidebartype' => $user['sidebartype'],

		// Post metadata
		'id'      => 0,
		'forum'   => 0,
		'ip'      => $_SERVER['REMOTE_ADDR'],
	//	'num'     => 0,
		'date'    => time(),
		'new'     => true,
		// (mod) Options
		'nosmilies' => 0,
		'nohtml'    => 0,
		'nolayout'  => 0,
		'moodid'    => $_POST['moodid'],
		'noob'      => 0,
		'revision'  => 0,
		// Attachments
		'attach_key' => PREVIEWOPT_DUMMYATTACH,
		//'attach_sel' => $attachsel,
		
		// Override this option to avoid always printing out the current user
		// (which is not valid when viewing the page as a guest)
		'editedby' => $loguser['id'] ? $loguser : $user, 
	);
	
	
	// Sidebar options.
	// Copied from the edit profile page.
	
	$sidecell = $user['sidebartype'] & 1;
	$sidetype = $user['sidebartype'] >> 1;		
	$sidebartype = "
	<style>
		.pl_left{padding-left:20px;}
		.pl_title{font-weight: bold;}
	</style>
	<div class='pl_title'>Sidebar type:</div>
	<div class='pl_left'>
	<input name='sidebartype' type='radio' value=0".($sidetype == 0 ? " checked" : "").">Normal<br>
	<input name='sidebartype' type='radio' value=1".($sidetype == 1 ? " checked" : "").">Without options<br>
	".(file_exists("sidebars/{$_GET['id']}.php") ? "<input name='sidebartype' type='radio' value=2".($sidetype == 2 ? " checked" : "").">PHP Code<br>" : "")."
	</div>
	<div class='pl_title'>Cell count:</div>
	<div class='pl_left'>
	<input name='sidebarcell' type='radio' value=0".($sidecell == 0 ? " checked" : "").">Two cell (default)<br>
	<input name='sidebarcell' type='radio' value=1".($sidecell == 1 ? " checked" : "").">Single cell<br>
	</div>";
	
	
	// Thread layouts
	$tlayoutq = $sql->query("SELECT id, name FROM tlayouts ORDER BY ord ASC, id ASC");
	$tlayouts = "";
	while ($x = $sql->fetch($tlayoutq)) {
		$tlayouts .= "<option value='{$x['id']}'".($x['id'] == $loguser['layout'] ? " selected" : "").">".htmlspecialchars($x['name'])."</option>";
	}
	
	// Save button!
	$savebtn = $nosavemark = "";
	if (CAN_SAVE_LAYOUT) {
		$savebtn = "";
		$nosavemark = " (these won't get saved)";
	}

?>
<form method="POST" action="?id=<?= $_GET['id'] ?>" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="<?= CSS_UPLOAD_MAX ?>">
<div id="postpreview">
<?= preview_post($user, $data, PREVIEW_EDITED, getuserlink($user)."'s post layout") ?>
</div>
<table class="table">
	<colgroup>
		<col style="width: 50%">
		<col style="width: 25%">
		<col style="width: 25%">
	</colgroup>
	<tr><td class="tdbgh center b" colspan=3>CSS</td></tr>
	<tr>
		<td class="tdbg1 vatop" colspan="3">
			<textarea id="css" name="css" rows="10"><?= escape_html($user['css']) ?></textarea><br/>
			...or import: <?= input_html('cssfile', null, ['input' => 'file', 'maxsize' => CSS_UPLOAD_MAX, 'accept' => "text/css", 'jsmode' => 'text', 'jstarget' => 'css', 'jstrigger' => 'input' /*'jscallback' => 'setCss'*/]) ?>
		</td>
	</tr>
	
	<tr>
		<td class="tdbgh center b">Header</td>
		<td class="tdbgh center b" colspan="2">Signature</td>
	</tr>
	<tr>
		<td class="tdbg1 vatop">
			<textarea id="postheader" name="postheader" rows="4"><?= escape_html($user['postheader']) ?></textarea>
		</td>
		<td class="tdbg1 vatop" colspan="2">
			<textarea id="signature" name="signature" rows="4"><?= escape_html($user['signature']) ?></textarea>
		</td>
	</tr>
	
	<tr>
		<td class="tdbgh center b" colspan="2">Sidebar code &amp; options (regular/extended only)</td>
		<td class="tdbgh center b">Avatar preview</td>
	</tr>
	<tr>
		<td class="tdbg1 vatop" colspan="2">
			<?= ($sidetype == 2 && file_exists("sidebars/{$_GET['id']}.php")
			? "<div style='background: #fff; overflow: scroll; width: 50vw; height: 400px; resize: vertical'>".highlight_file("sidebars/{$_GET['id']}.php", true)."</div><div style='display: none'>"
			: "<div>") ?>
				<textarea id="signature" name="sidebar" rows="8"><?= escape_html($user['sidebar']) ?></textarea>
			</div>
		</td>
		<td class="tdbg1 vamid" rowspan="2">
			<?= mood_preview() ?>
		</td>		
	</tr>
	<tr>
		<td class="tdbg1 right" colspan="2">
			<?= sidebartype_html($_GET['id'], $user['sidebartype']) ?>
		</td>
	</tr>
	<tr>
		<td class="tdbg1" colspan="3">
			<span class="js">
				<label><input type="checkbox" id="autoupdate" value="1"<?= $_COOKIE['plp_aupd'] ? " checked" : ""?>> Auto update CSS</label> | 
				<input type="button" onclick="quickPreview(true)" value="Preview CSS"> 
			</span>
			<input type="submit" name="submit" value="Preview All"> <?= $savebtn ?> | 
			Thread layout: <select name="tlayout">
				<?= $tlayouts ?>
			</select> | 
			<?=mood_list($user['id'], $_POST['moodid'])?>
		</td>
	</tr>
<?php if (CAN_SAVE_LAYOUT) { ?>
	<tr>
		<td class="tdbg1 right" colspan="3">
			<div class="fonts">To save the changes to the <?= ($issuper ? "sidebar, " : "") ?>CSS, header and footer. The <?= ($issuper ? "" : "sidebar, ") ?>thread layout and avatar options are for local preview only and won't get saved.</div> <input type="submit" name="save" value="Save changes"/>
		</td>
	</tr>
<?php } ?>

</table>
</form>
	
<script type="text/javascript">
	var user = <?= $_GET['id'] ?>;
	
	// Text area and destination CSS field
	var css  = document.getElementById('css');
	var css_dest  = document.getElementById('css0');
	
	// For seamless scrolling
	var postpreview  = document.getElementById('postpreview');
	
	// Determine if to autoupdate the CSS
	var autoupdate = document.getElementById('autoupdate');
	
	autoupdate.addEventListener('change', function() {
		if (this.checked) {
			css.addEventListener('input', quickPreview);
			document.cookie = "plp_aupd=1"; 
		} else {
			css.removeEventListener('input', quickPreview);
			document.cookie = "plp_aupd=; Max-Age=-99999999;";
		}
	});
	
	if (getCookie('plp_aupd')) 
		css.addEventListener('input', quickPreview);
	
	function quickPreview(scroll = false) {
		css_dest.innerHTML = css.value.replace(new RegExp("\.(top|side|main|cont)bar"+user+"", 'gi'), '.$1bar'+user+'_p0');
		if (scroll)
			postpreview.scrollIntoView();
	}
</script>
<?php
	
	pagefooter();