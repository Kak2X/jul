<?php
	
	require 'lib/function.php';
	$windowtitle	= "Admin Cruft -- {$config['board-name']}";
	pageheader($windowtitle);
	
	if (!has_perm('admin-actions')) {
		errorpage("Uh oh, you are not the admin go away.");
	}
	
	$sysadmin = has_perm('sysadmin-actions');
	$misc     = $sql->fetchq("SELECT * FROM `misc`");
	
	if (isset($_POST['submit'])) {
		// Token check
		check_token($_POST['auth']);
		
		// (NULL -> do nothing ; 0 -> force theme 0)
		$scheme = filter_int($_POST['scheme']);
		if ($scheme == '-1') $scheme = NULL;
		
		if (filter_bool($_POST['maxusersreset'])) {
			$maxusers 		= 0;
			$maxusersdate 	= 0;
			$maxuserstext 	= NULL;
		} else {
			$maxusers 		= filter_int($_POST['maxusers']);
			$maxusersdate 	= fieldstotimestamp('maxusers_','_POST');
			$maxuserstext 	= $misc['maxuserstext'];
		}
		
		// The rest
		$sql->queryp("UPDATE misc SET ".mysql::setplaceholders(
			"views","hotcount","maxpostsday","maxpostshour","maxpostsdaydate","maxpostshourdate","maxusers","maxusersdate",
			"maxuserstext","disable","donations","ads","valkyrie","scheme","specialtitle","regmode","regcode",
			"announcementforum","trashforum","maxcustomforums","daysforcustomforum","postsforcustomforum","private"),
			[
				'views'					=> filter_int($_POST['views']),
				'hotcount'				=> filter_int($_POST['hotcount']),
				'maxpostsday' 			=> filter_int($_POST['maxpostsday']),
				'maxpostshour' 			=> filter_int($_POST['maxpostshour']),
				'maxpostsdaydate' 		=> fieldstotimestamp('maxpostsday_','_POST'),
				'maxpostshourdate' 		=> fieldstotimestamp('maxpostshour_','_POST'),
				'maxusers' 				=> $maxusers,
				'maxusersdate' 			=> $maxusersdate,
				'maxuserstext' 			=> $maxuserstext,
				'disable' 				=> ($sysadmin ? filter_int($_POST['disable']) : $misc['disable']),
				'donations' 			=> filter_float($_POST['donations']),
				'ads' 					=> filter_float($_POST['ads']),
				'valkyrie' 				=> filter_float($_POST['valkyrie']),
				'scheme' 				=> $scheme,
				'specialtitle' 			=> xssfilters(filter_string($_POST['specialtitle'], true)),
				'regmode' 				=> ($sysadmin ? filter_int($_POST['regmode']) : $misc['regmode']),
				'regcode' 				=> ($sysadmin ? filter_string($_POST['regcode'], true) : $misc['regcode']),
				'announcementforum' 	=> filter_int($_POST['announcementforum']),
				'trashforum' 			=> filter_int($_POST['trashforum']),
				'maxcustomforums'		=> filter_int($_POST['maxcustomforums']),
				'daysforcustomforum'	=> filter_int($_POST['daysforcustomforum']),
				'postsforcustomforum'	=> filter_int($_POST['postsforcustomforum']),
				'private'				=> ($sysadmin ? filter_int($_POST['private']) : $misc['private']),
			]);
		
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
			There are a few features you can use. Select one from above.<br>
			Alternatively you can change some general board options in the panel below.
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
				<select name='regmode' id='regmode' onchange="enacode()" <?=$sysset?>>
					<option value='0' <?=filter_string($reg_sel[0])?>>Open registration</option>
					<option value='1' <?=filter_string($reg_sel[1])?>>Disabled</option>
					<option value='2' <?=filter_string($reg_sel[2])?>>Pending membership</option>
					<option value='3' <?=filter_string($reg_sel[3])?>>Require passkey</option>
				</select>
			</td>
		</tr>
		<tr><td class='tdbg1 center' width='200'><b>Board access</b></td>
			<td class='tdbg2'>
				<select name='private' <?=$sysset?>>
					<option value='0' <?=filter_string($prv_sel[0])?>>Public</option>
					<option value='1' <?=filter_string($prv_sel[1])?>>Private</option>
					<option value='2' <?=filter_string($prv_sel[2])?>>Secret</option>
				</select>
			</td>
		</tr>
		<tr id="regcodetr"><td class='tdbg1 center' width='200'><b>Registration code</b></td>
			<td class='tdbg2'><input type='text' name='regcode' value="<?=htmlspecialchars($misc['regcode'])?>" <?=$sysset?>></td>
		</tr>
		
		<tr><td class='tdbg1 center' width='200'><b>Announcement forum</b></td>
			<td class='tdbg2'><?=doforumList($misc['announcementforum'], 'announcementforum', 'None')?></td>
		</tr>
		<tr><td class='tdbg1 center' width='200'><b>Trash forum</b></td>
			<td class='tdbg2'><?=doforumList($misc['trashforum'], 'trashforum', 'None')?></td>
		</tr>
		<tr><td class='tdbgc center' colspan=2>Custom forums<?=($config['allow-custom-forums'] ? "" : " [Disabled]")?></td></tr>		
		<tr><td class='tdbg1 center' width='200'><b>Account age requirement</b></td>
			<td class='tdbg2'><input type='text' name='daysforcustomforum' value='<?=$misc['daysforcustomforum']?>' class='right' size=2 maxlength=5> days</td>
		</tr>
		</tr>
		<tr><td class='tdbg1 center' width='200'><b>Postcount requirement</b></td>
			<td class='tdbg2'><input type='text' name='postsforcustomforum' value='<?=$misc['postsforcustomforum']?>' class='right' size=3 maxlength=5> posts</td>
		</tr>
		<tr><td class='tdbg1 center' width='200'><b>Max forums per user</b></td>
			<td class='tdbg2'><input type='text' name='maxcustomforums' value='<?=$misc['maxcustomforums']?>' class='right' size=2 maxlength=3></td>
		</tr>
		
		
		<tr><td class='tdbgc center' colspan=2>Appareance</td></tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>Force scheme</b></td>
			<td class='tdbg2'><?=doschemeList(true, $misc['scheme'], 'scheme')?></td>
		</tr>
		<tr>
			<td class='tdbg1 center' width='200'><b>Custom header</b></td>
			<td class='tdbg2'><textarea wrap=virtual name='specialtitle' ROWS=2 COLS=80 style="width: 100%; max-width: 800px; resize:vertical;"><?=htmlspecialchars($misc['specialtitle'])?></textarea></td>
		</tr>
		
		<tr><td class='tdbgc center' colspan=2>Records</td></tr>
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
				<input type='submit' class=submit name='submit' value='Submit changes'>
				<input type='hidden' name=auth value="<?=generate_token()?>">
			</td>
		</tr>
	</table>
	</form>
	<?php/* Hide the Registration Code row if the respective registration mode isn't selected */?>
	<script type="text/javascript">
		function enacode() {
			document.getElementById("regcodetr").style.display = (document.getElementById("regmode").selectedIndex == 3 ? "" : "none");
		}
		enacode();
	</script>
	<?php



	pagefooter();

?>