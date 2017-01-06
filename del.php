<?php

	require 'lib/function.php';

	$allowedusers	= array(
		$x_hacks['adminip'],		// Xkeeper
		//"24.234.157.232",			// also me
		
		);

	if (!in_array($_SERVER['REMOTE_ADDR'], $allowedusers)) errorpage("Nein.");
  
	pageheader();
	
	print adminlinkbar('del.php');
	
	if (filter_array($_POST['deluser'])) { //($loguserid==1 or $loguserid==2)){
		
		check_token($_POST['auth'], 65);
		
		$dellist = array_keys($_POST['deluser']);
		$delusercnt = 0;
		$delusertext = "";

		$querycheck = array();
		$sql->beginTransaction();
		
		foreach($dellist as $id) {
			
			// Although this is a sysadmin-only tool, filter that id properly just in case
			$id = (int) $id;

			$userdata = $sql->query("SELECT $userfields FROM users u WHERE u.id = $id");

			while ($user = $sql->fetch($userdata)) {
				$id		= $user['id'];
				$name 	= $user['name'];
				
				$namecolor = getnamecolor($user['sex'],$user['powerlevel'],$user['namecolor']);
				$line = addslashes("<br><br>===================<br>[Posted by <span style='color:#$namecolor'><b>$name</b></span>]<br>");
				// Backup the user's data just in case
				$sql->query("INSERT INTO `delusers` ( SELECT * FROM `users` WHERE `id` = '$id' )", false, $querycheck);
				$sql->query("UPDATE posts SET user = {$config['deleted-user-id']}, headid = 0, signid = 0, signtext = CONCAT_WS('','$line',signtext) WHERE user = $id", false, $querycheck);
//				$ups=$sql->query("SELECT id FROM posts WHERE user=$id");
//				while($up=mysql_fetch_array($ups)) $sql->query("UPDATE posts_text SET signtext=CONCAT_WS('','$line',signtext) WHERE pid=$up[id]") or print mysql_error();
				$sql->query("UPDATE threads SET user={$config['deleted-user-id']} WHERE user=$id", false, $querycheck);
				$sql->query("UPDATE threads SET lastposter={$config['deleted-user-id']} WHERE lastposter=$id", false, $querycheck);
				$sql->query("UPDATE forums SET lastpostuser={$config['deleted-user-id']} WHERE lastpostuser=$id", false, $querycheck);
				$sql->query("UPDATE events SET user={$config['deleted-user-id']} WHERE user=$id", false, $querycheck);
				$sql->query("UPDATE pmsgs SET userfrom = {$config['deleted-user-id']}, headid = 0, signid = 0, signtext = CONCAT_WS('','$line',signtext) WHERE userfrom = $id");
				$sql->query("UPDATE pmsgs SET userto = {$config['deleted-user-id']} WHERE userto=$id", false, $querycheck);
				$sql->query("UPDATE users SET posts = -1 * (SELECT COUNT(*) FROM posts WHERE user = {$config['deleted-user-id']}) WHERE id = {$config['deleted-user-id']}", false, $querycheck);
				
				$sql->query("DELETE FROM forummods WHERE user=$id", false, $querycheck);
				$sql->query("DELETE FROM userratings WHERE userrated=$id OR userfrom=$id", false, $querycheck);
				$sql->query("DELETE FROM pollvotes WHERE user=$id", false, $querycheck);
				$sql->query("DELETE FROM users WHERE id=$id", false, $querycheck);
				$sql->query("DELETE FROM users_rpg WHERE uid=$id", false, $querycheck);
				
				$delusertext	.= "\r\n<tr><td class='tdbg1 center' width=120>$id</td><td class='tdbg2'><span style='color:#$namecolor'><b>{$user['name']}</b></span></td></tr>";
				$delusercnt		++;
			}
		}
		
		if (!$sql->checkTransaction($querycheck)) {
			errorpage("Couldn't delete the specified users.");
		}

		?>
		<table class='table'>
			<tr>
				<td class='tdbgc center' colspan=2>
					<b><?=$delusercnt?> user<?=($delusercnt != 1 ? "s" : "")?> deleted.</b>
				</td>
			</tr>
			<?=$delusertext?>
		</table>
		<br>
		<?php
	}


	
	$_POST['searchname']		= filter_string($_POST['searchname']);
	$_POST['searchip']			= filter_string($_POST['searchip']);
	$_POST['maxposts']			= filter_int($_POST['maxposts']);
	
	$_POST['sortpowerlevel'] 	= filter_string($_POST['sortpowerlevel']);
	$_POST['sortord'] 			= filter_int($_POST['sortord']);
	$_POST['sorttype'] 			= filter_int($_POST['sorttype']);
	// Variable defaults
	if (!$_POST['sortpowerlevel']) 	$_POST['sortpowerlevel'] = "ab";
	if (!$_POST['sortord']) 		$_POST['sortord']		 = 0;
	$powerselect[$_POST['sortpowerlevel']]	= 'selected';
	$sortsel[$_POST['sorttype']]			= 'selected';
	$ordsel[$_POST['sortord']]				= 'checked';
 
	
	
?>
<form action='del.php' method=post>
<table class='table'>
	<tr><td class='tdbgh center' colspan=2>Sort Options</td></tr>
	<tr><td class='tdbg1 center' width=300><b>User Search:</b></td>
		<td class='tdbg2'><input type='text' name=searchname size=30 maxlength=25 value="<?=htmlspecialchars($_POST['searchname'])?>"></td></tr>
	<tr><td class='tdbg1 center' width=300><b>IP Search:</b></td>
		<td class='tdbg2'><input type='text' name=searchip size=30 maxlength=32 value="<?=htmlspecialchars($_POST['searchip'])?>"></td></tr>
	<tr><td class='tdbg1 center' width=300><b>Show users with less than:</b></td>
		<td class='tdbg2'><input type='text' name=maxposts size=15 maxlength=9 value="<?=htmlspecialchars($_POST['maxposts'])?>"> posts</td></tr>
	<tr><td class='tdbg1 center'><b>Powerlevel:</b></td>
		<td class='tdbg2'>
			<select name='sortpowerlevel'>
				<option value='aa'  <?=filter_string($powerselect['aa']) ?>>* Any powerlevel</option>
				<option value='ab'  <?=filter_string($powerselect['ab']) ?>>* All banned</option>
				<option value='s3'  <?=filter_string($powerselect['s3']) ?>>Administrator</option>
				<option value='s2'  <?=filter_string($powerselect['s2']) ?>>Moderator</option>
				<option value='s1'  <?=filter_string($powerselect['s1']) ?>>Local Moderator</option>
				<option value='s0'  <?=filter_string($powerselect['s0']) ?>>Normal User</option>
				<option value='s-1' <?=filter_string($powerselect['s-1'])?>>Banned</option>
				<option value='s-2' <?=filter_string($powerselect['s-2'])?>>Permabanned</option>
			</select>
		</td>
	</tr>
	<tr><td class='tdbg1 center' width=300><b>Sort by:</b></td>
		<td class='tdbg2'>
			<select name='sorttype'>
				<option value='0' <?=filter_string($sortsel[0])?>> Last activity </option>
				<option value='1' <?=filter_string($sortsel[1])?>> Register date </option>
				<option value='2' <?=filter_string($sortsel[2])?>> Posts </option>
				<option value='3' <?=filter_string($sortsel[3])?>> Powerlevel </option>
				<option value='4' <?=filter_string($sortsel[4])?>> IP address</option>
			</select>, 
			<input type=radio class='radio' name=sortord value='0' <?=filter_string($ordsel[0])?>> Descending&nbsp;&nbsp;
			<input type=radio class='radio' name=sortord value='1' <?=filter_string($ordsel[1])?>> Ascending
		</td>
	</tr>
	<tr>
		<td class='tdbg1 center'>&nbsp;</td>
		<td class='tdbg2'>
			<input type=submit value='Apply filters'>
		</td>
	</tr>
</table>
</form>
<?php


//	print_r($_POST);
	$sqlquery	= "";
	$values		= array();

	if ($_POST['maxposts']) {
		$sqlquery	= "`posts` <= :posts";
		$values['posts']	= $_POST['maxposts'];
	}
	if ($_POST['searchip']) {
		if ($sqlquery)	$sqlquery	.= " AND ";
		//$sqlquery	.= "`lastip` LIKE '". $_POST['searchip'] ."%'";
		$sqlquery	.= "`lastip` LIKE :searchip";
		$values['searchip']	= $_POST['searchip'].'%';
	}
	if ($_POST['searchname']) {
		if ($sqlquery)	$sqlquery	.= " AND ";
		$sqlquery	.= "`name` LIKE :searchname";
		$values['searchname']	= '%'.$_POST['searchname'].'%';
	}

	if ($_POST['sortpowerlevel'] != "aa") {
		if ($sqlquery)	$sqlquery	.= " AND ";

		if ($_POST['sortpowerlevel'] == "ab") 
			$sqlquery	.= "`powerlevel` < '0'";
		else {
			$sqlquery	.= "`powerlevel` = :powerlevel";
			$values['powerlevel'] = str_replace("s", "", $_POST['sortpowerlevel']);
		}
	}

	switch ($_POST['sorttype']) {
		case 0:
			$sortfield	= "lastactivity";
			break;
		case 1:
			$sortfield	= "regdate";
			break;
		case 2:
			$sortfield	= "posts";
			break;
		case 3:
			$sortfield	= "powerlevel";
			break;
		case 4:
			$sortfield	= "lastip";
			break;
		default:
			$sortfield	= "lastactivity";
			break;
	}

	$sortorder = $_POST['sortord'] ? "ASC" : "DESC";
	
	if ($sqlquery) $sqlquery	= "WHERE ". $sqlquery;
	$sqlquery	.= " ORDER BY `$sortfield` $sortorder";


/*  if(!$p) $p=0;
  if ($ip) $q = "lastip = '$ip'";
	else $q = "posts=$p";
*/
	$users		= $sql->queryp("SELECT * FROM `users` $sqlquery", $values);
	$usercount	= $sql->num_rows($users);
	?>
<form action=del.php method=post>
<table class='table'>
	<tr><td class='tbl tdbgc font center' colspan=8><b><?=$usercount?> user(s) found.</b></td></tr>
	<tr>
		<td class='tdbgh center'>&nbsp;</td>
		<td class='tdbgh center'>Name</td>
		<td class='tdbgh center'>Posts</td>
		<td class='tdbgh center'>Regdate</td>
		<td class='tdbgh center'>Last post</td>
		<td class='tdbgh center' width=200>Last activity</td>
		<td class='tdbgh center'>Last URL</td>
		<td class='tdbgh center'>IP</td>
	</tr>
	<?php
	while ($user=$sql->fetch($users)) {
		$userlink = getuserlink($user);
		
		if($user['lastposttime']) $lastpost	= printdate($user['lastposttime'], true);
			else $lastpost		= '-';
		if($user['lastactivity'] != $user['regdate']) $lastactivity	= printdate($user['lastactivity']);
			else $lastactivity	= '-';
		if($user['regdate']) $regdate = printdate($user['regdate'], true);
			else $regdate		= '-';

		$textid	= str_pad($user['id'], 5, "x", STR_PAD_LEFT);
		$textid	= str_replace("x", "<font color=#606060>0</font>", $textid);
		$textid	= str_replace("</font><font color=#606060>", "", $textid);

		?>
	<tr>
		<td class='tdbg1 center'><input type=checkbox name=deluser[<?=$user['id']?>] value='1'></td>
		<td class='tdbg2'><?=$textid?> - <?=$userlink?></td>
		<td class='tdbg1 center' width=0><?=$user['posts']?></td>
		<td class='tdbg1 center' width=120><?=$regdate?></td>
		<td class='tdbg1 center' width=120><?=$lastpost?></td>
		<td class='tdbg1 center' width=120><?=$lastactivity?></td>
		<td class='tdbg2'><?=$user['lasturl']?>&nbsp;</td>
		<td class='tdbg2 center'><?=$user['lastip']?></td>
	</tr>
		<?php
	}

  ?>
	<tr>
		<td class='tdbg1' colspan=8>
			<input type='submit' class=submit name=submit value=Submit>
			<input type='hidden' name='auth' value='<?=generate_token(65)?>'>
		</td>
	</tr>
</table>
</form>
<?php
  
  pagefooter();
?>