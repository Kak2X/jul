<?php

	require "lib/common.php";
	
	$windowtitle	= "Admin Cruft";
	
	if (!$isadmin) {
		errorpage("Uh oh, you are not the admin go away.");
	}
		
	pageheader($windowtitle);
	
	$misc	= $sql -> fetchq("SELECT * FROM `misc`");
	
	if (isset($_POST['submit'])) {
		// Token check
		check_token($_POST['auth']);
		
		// The query
		$settings = [
			'hotcount'			=> filter_int($_POST['hotcount']),
			'disable' 			=> ($sysadmin ? filter_int($_POST['disable']) : $misc['disable']),
			'donations' 		=> filter_float($_POST['donations']),
			'ads' 				=> filter_float($_POST['ads']),
			'valkyrie' 			=> filter_float($_POST['valkyrie']),
			'scheme' 			=> get_scheme_opt($_POST['scheme']),
			'defaultscheme' 	=> filter_int($_POST['defaultscheme']),
			'specialtitle' 		=> filter_string($_POST['specialtitle']),
			'regmode' 			=> ($sysadmin ? filter_int($_POST['regmode']) : $misc['regmode']),
			'regcode' 			=> ($sysadmin ? filter_string($_POST['regcode']) : $misc['regcode']),
			'private'			=> ($sysadmin ? filter_int($_POST['private']) : $misc['private']),
			'attntitle'			=> filter_string($_POST['attntitle']),
			'attntext'			=> filter_string($_POST['attntext']),
		];
		
		
		if (filter_bool($_POST['recordeditok'])) {	
			if (filter_bool($_POST['maxusersreset'])) {
				$maxusers 		= 0;
				$maxusersdate 	= 0;
				$maxuserstext 	= NULL;
			} else {
				$maxusers 		= filter_int($_POST['maxusers']);
				$maxusersdate 	= fieldstotimestamp('maxusers_','_POST');
				$maxuserstext 	= $misc['maxuserstext'];
			}
		
			$settings += [
				'views'				=> filter_int($_POST['views']),
				'maxpostsday' 		=> filter_int($_POST['maxpostsday']),
				'maxpostshour' 		=> filter_int($_POST['maxpostshour']),
				'maxpostsdaydate' 	=> fieldstotimestamp('maxpostsday_','_POST'),
				'maxpostshourdate' 	=> fieldstotimestamp('maxpostshour_','_POST'),
				'maxusers' 			=> $maxusers,
				'maxusersdate' 		=> $maxusersdate,
				'maxuserstext' 		=> $maxuserstext,
			];
		}
		
		$sql->queryp("UPDATE misc SET ".mysql::setplaceholders($settings), $settings);
		
		errorpage("Settings saved!", 'admin.php', 'administration main page', 0);
	}
	

	// For read only entries
	$sysset = (!$sysadmin) ? "readonly disabled" : "";
	
	// Selections
	$reg_sel[$misc['regmode']] = 'selected';
	$prv_sel[$misc['private']] = 'selected';

	print adminlinkbar();
	
	?>
	<table class='table'>
		<tr><td class='tdbgh center'><b>Panel de Admin<br></td></tr>
		<tr><td class='tdbg1 center'>
			&nbsp;<br>
			There are a few features you can use. Select one from the panel on the left.<br>
			Alternatively you can change some general board options in the section below.
			<?= ($sysadmin ? "<br><br>To change the hard configuration, click <a href='install/'>here</a>." : "") ?>
			<br>&nbsp;
		</td></tr>
	</table>

	<br>
	
	<form action='admin.php' method='post'>
	<table class='table'>
		<tr><td class='tdbgh center' colspan=2><b>Setting up the Soft Dip</b></td></tr>
		
		<tr><td class='tdbgc center' colspan=2>Board settings</td></tr>		
		<tr>
			<td class='tdbg1 center' width='200'><b><?=$statusicons['hot']?> threshold</b></td>
			<td class='tdbg2'><input type='text' size=2 maxlength=3 name='hotcount' value='<?=$misc['hotcount']?>' class='right'> replies</td>
		</tr>
		<tr><td class='tdbg1 center' width='200'><b>Disable forum?</b></td>
			<td class='tdbg2'><input type='checkbox' name='disable' value='1' <?=$sysset?> <?=($misc['disable'] ? 'checked' : '')?>> Disable</td>
		</tr>
		<tr><td class='tdbg1 center' width='200'><b>Registration mode</b></td>
			<td class='tdbg2'>
				<select name='regmode' id="regmode" onchange="enacode()" <?=$sysset?>>
					<option value='0' <?=filter_string($reg_sel[0])?>>Open registration</option>
					<option value='1' <?=filter_string($reg_sel[1])?>>Disabled</option>
					<option value='2' <?=filter_string($reg_sel[2])?>>Pending membership</option>
					<option value='3' <?=filter_string($reg_sel[3])?>>Require passkey</option>
				</select>
			</td>
		</tr>
		<tr id="regcodetr"><td class='tdbg1 center' width='200'><b>Registration code</b></td>
			<td class='tdbg2'><input type='text' name='regcode' value="<?=htmlspecialchars($misc['regcode'])?>" <?=$sysset?>></td>
		</tr>
		<tr><td class='tdbg1 center' width='200'><b>Board access</b></td>
			<td class='tdbg2'>
				<select name='private' <?=$sysset?>>
					<option value='0' <?=filter_string($prv_sel[0])?>>Public</option>
					<option value='1' <?=filter_string($prv_sel[1])?>>Private</option>
					<option value='2' <?=filter_string($prv_sel[2])?>>Hidden (!)</option>
				</select>
			</td>
		</tr>
		
		<tr><td class='tdbgc center' colspan=2>Appareance</td></tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>Default scheme</b></td>
			<td class='tdbg2'><?=doschemeList($misc['defaultscheme'], 'defaultscheme', SL_SHOWSPECIAL)?></td>
		</tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>Force scheme</b></td>
			<td class='tdbg2'><?=doschemeList($misc['scheme'], 'scheme', SL_SHOWSPECIAL | SL_SHOWNONE)?></td>
		</tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>Custom header</b></td>
			<td class='tdbg2'><textarea name='specialtitle' rows='2'><?=escape_html($misc['specialtitle'])?></textarea></td>
		</tr>
		<tr>
			<td class='tdbg1 center'><b>Notice box title</b></td>
			<td class='tdbg2'><input type='text' name='attntitle' class='w' value="<?=escape_html($misc['attntitle'])?>"></td>
		</tr>
		<tr>
			<td class='tdbg1 center'><b>Notice box contents</b></td>
			<td class='tdbg2'><textarea name='attntext' rows='2'><?=escape_html($misc['attntext'])?></textarea></td>
		</tr>
		
		<tr><td class='tdbgc center' colspan=2>
			Records
			<br/><input type='checkbox' name='recordeditok' value='1'> To save the values in this section, you must check this box first.
		</td></tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>View count</b></td>
			<td class='tdbg2'><input type='text' name='views' value='<?=$misc['views']?>' class='right'> views</td>
		</tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>Max posts/day</b></td>
			<td class='tdbg2'><input type='text' name='maxpostsday' value='<?=$misc['maxpostsday']?>' class='right'> posts, at <?=datetofields($misc['maxpostsdaydate'],'maxpostsday_', DTF_DATE | DTF_TIME | DTF_NOLABEL)?></td>
		</tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>Max posts/hour</b></td>
			<td class='tdbg2'><input type='text' name='maxpostshour' value='<?=$misc['maxpostshour']?>' class='right'> posts, at <?=datetofields($misc['maxpostshourdate'],'maxpostshour_', DTF_DATE | DTF_TIME | DTF_NOLABEL)?></td>
		</tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>Most users online</b></td>
			<td class='tdbg2'>
				<input type='text' name='maxusers' value='<?=$misc['maxusers']?>' class='right'> users, at <?=datetofields($misc['maxusersdate'],'maxusers_', DTF_DATE | DTF_TIME | DTF_NOLABEL)?>
				<br><input type='checkbox' name='maxusersreset' value='1'> Reset user list
			</td>
		</tr>


		<tr><td class='tdbgc center' colspan=2><img src="images/ihateglennbeckbutistillthinkthisimagefitsquitenicelyundertheadminpanelmoneycounter.jpg" title="longest file name ever"><br>Monetary settings</td></tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>Donations</b></td>
			<td class='tdbg2'><input type='text' name='donations' value='<?=sprintf("%01.2f", $misc['donations'])?>' class='right'>$</td>
		</tr>
		<tr>
		<td class='tdbg1 center' width='200'><b>$$$ Ads $$$</b></td>
			<td class='tdbg2'><input type='text' name='ads' value='<?=sprintf("%01.2f", $misc['ads'])?>' class='right'>$</td>
		</tr>
		<td class='tdbg1 center' width='200'><b>VPS</b></td>
			<td class='tdbg2'><input type='text' name='valkyrie' value='<?=sprintf("%01.2f", $misc['valkyrie'])?>' class='right'>$</td>
		</tr>


		<tr><td class='tdbgc center' colspan=2>&nbsp;</td></tr>

		<tr>
			<td class='tdbg1 center' width='200'>&nbsp;</td>
			<td class='tdbg2'>
				<input type='submit' name='submit' value='Submit changes'>
				<?= auth_tag() ?>
			</td>
		</tr>
	</table>
	</form>
	<!-- Hide the Registration Code row if the respective registration mode isn't selected -->
	<script type="text/javascript">
		function enacode() {
			document.getElementById("regcodetr").style.display = (document.getElementById("regmode").selectedIndex == 3 ? "" : "none");
		}
		enacode();
	</script>
	<?php



	pagefooter();

?>