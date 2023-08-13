<?php

	require "lib/common.php";
	
	$_GET['id']          = filter_int($_GET['id']);
	$_COOKIE['plp_aupd'] = filter_int($_COOKIE['plp_aupd']);
	
	
	if (!$_GET['id']) {
		errorpage("No user selected.");
	}
	
	
	pageheader("Post layouts");
	
	$user = $sql->fetchq("SELECT * FROM users u WHERE u.id = {$_GET['id']}");
	if (!$user) {
		errorpage("This user doesn't exist!");
	}
	
	
	if (isset($_POST['submit'])) {
		$user['postheader']  = filter_string($_POST['postheader']);
		$user['signature']   = filter_string($_POST['signature']);
		$user['css']         = filter_string($_POST['css']);
		$user['sidebar']     = filter_string($_POST['sidebar']);
		$user['sidebartype'] = numrange((filter_int($_POST['sidebartype']) << 1) + filter_int($_POST['sidebarcell']), 0, 5);
		$loguser['layout']   = filter_int($_POST['tlayout']);
	} else {
		// Force extended layout by default
		$loguser['layout'] = 6;
	}
	$_POST['moodid'] = filter_int($_POST['moodid']);
	
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
		'new'     => true,
		// (mod) Options
		'nosmilies' => 0,
		'nohtml'    => 0,
		'nolayout'  => 0,
		'moodid'    => $_POST['moodid'],
		'noob'      => 0,
		'revision'  => 0,
		// Attachments
		'attach_key' => NULL,
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
	
?>
<form method="POST" action="?id=<?= $_GET['id'] ?>">
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
			<textarea id="css" name="css" rows="10" style="resize:vertical; width: 100%"><?= htmlspecialchars($user['css']) ?></textarea>
		</td>
	</tr>
	
	<tr>
		<td class="tdbgh center b">Header</td>
		<td class="tdbgh center b" colspan="2">Signature</td>
	</tr>
	<tr>
		<td class="tdbg1 vatop">
			<textarea id="postheader" name="postheader" rows=4 style="resize:vertical; width: 100%"><?= htmlspecialchars($user['postheader']) ?></textarea>
		</td>
		<td class="tdbg1 vatop" colspan="2">
			<textarea id="signature" name="signature" rows=4 style="resize:vertical; width: 100%"><?= htmlspecialchars($user['signature']) ?></textarea>
		</td>
	</tr>
	
	<tr>
		<td class="tdbgh center b">Sidebar code (regular/extended only)</td>
		<td class="tdbgh center b">Sidebar options (regular/extended only)</td>
		<td class="tdbgh center b">Avatar preview</td>
	</tr>
	<tr>
		<td class="tdbg1 vatop">
			<?= ($sidetype == 2 && file_exists("sidebars/{$_GET['id']}.php")
			? "<div style='background: #fff; overflow: scroll; width: 50vw; height: 400px; resize: vertical'>".highlight_file("sidebars/{$_GET['id']}.php", true)."</div><div style='display: none'>"
			: "<div>") ?>
				<textarea id="signature" name="sidebar" rows=8 style="resize:vertical; width: 100%"><?= htmlspecialchars($user['sidebar']) ?></textarea>
			</div>
		</td>
		<td class="tdbg1 vatop">
			<?= $sidebartype ?>
		</td>
		<td class="tdbg1 vatop">
			<center><?=mood_layout(0, $user['id'], $_POST['moodid'])?></center>
		</td>		
	</tr>
	
	<tr>
		<td class="tdbg1" colspan="3">
			<span id="jsbtn">
				<label><input type="checkbox" id="autoupdate" value="1"<?= $_COOKIE['plp_aupd'] ? " checked" : ""?>> Auto update CSS</label> | 
				<input type="button" onclick="quickpreview(true)" value="Preview CSS"> 
			</span>
			<input type="submit" name="submit" value="Preview All"> | 
			Thread layout: <select name="tlayout">
				<?= $tlayouts ?>
			</select> | 
			<?=mood_layout(1, $user['id'], $_POST['moodid'])?>
		</td>
	</tr>
</table>
</form>

<noscript><style>#jsbtn{display:none}</style></noscript>

	
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
			css.addEventListener('input', quickpreview);
			document.cookie = "plp_aupd=1"; 
		} else {
			css.removeEventListener('input', quickpreview);
			document.cookie = "plp_aupd=; Max-Age=-99999999;";
		}
	});
	
	function quickpreview(scroll = false) {
		css_dest.innerHTML = css.value.replace(new RegExp("\.(top|side|main|cont)bar"+user+"", 'gi'), '.$1bar'+user+'_p0');
		if (scroll)
			postpreview.scrollIntoView();
	}
</script>
<?php
	
	
	
	pagefooter();