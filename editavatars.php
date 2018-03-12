<?php

	require "lib/function.php";
	
	if (!$config['allow-avatar-storage']) errorpage("The avatar storage is disabled.<br/>To edit your avatars, use the <a href='editprofile.php'>edit profile</a> page.");
	if (!$loguser['id'])                  errorpage("You need to be logged in to edit your avatars.");
	if ($banned)                          errorpage("Banned users aren't allowed to edit avatars.");
	if ($loguser['avatar_locked'])        errorpage("You aren't allowed to upload avatars.");
	
	
	const DEFAULT_STR    = "[Default Avatar]";
	//const DELAYED_CRAP = " <br>(the changes may not take effect immediately)";
	
	
	$_GET['id']   = filter_int($_GET['id']);
	if (isset($_GET['edit'])) {
		$_GET['edit'] = (int) $_GET['edit'];
		if ($_GET['edit'] < -1) { // Restrict edit arg to real values
			$_GET['edit'] = -1;
		}
	} else {
		$_GET['edit'] = NULL;
	}
	
	
	// Editing another user?
	if (!$_GET['id'] || $_GET['id'] == $loguser['id']) {
		$_GET['id']   = $loguser['id'];
		$user         = $loguser;
	} else {
		if (!$isadmin) {
			errorpage("You aren't allowed to do this!");
		}
		$user = $sql->fetchq("SELECT {$userfields} FROM users u WHERE id = {$_GET['id']}");
		if (!$user) {
			errorpage("This user doesn't exist.");
		}	
	}
	
	// Deletions and whatever
	if (isset($_GET['del'])) {
		$_GET['del'] = (int) $_GET['del'];
		$avatar = $sql->fetchq("SELECT * FROM users_avatars WHERE user = {$_GET['id']} AND file = {$_GET['del']}");
		if (!$avatar) {
			errorpage("You are trying to delete a nonexisting avatar.");
		}
		
		$message       = "Are you sure you want to delete this avatar?";
		$form_link     = "?id={$_GET['id']}&del={$_GET['del']}";
		$buttons       = array(
			0 => ["Delete avatar"],
			1 => ["Cancel", "?id={$_GET['id']}"]
		);
		if (confirmpage($message, $form_link, $buttons)) {
			delete_avatar($_GET['id'], $_GET['del']);
			//msg_holder::set_cookie("Avatar '<i>{{$data['title']}}</i>' deleted!");
			return header("Location: ?id={$_GET['id']}");
		}
	}
	
	// Apparently Kafuka has an avatar limit, which is a nice thing to have I guess
	if ($config['avatar-limit']) {
		$limit = $sql->resultq("SELECT COUNT(*) FROM users_avatars WHERE user = {$_GET['id']}");
		define('LIMIT_REACHED', $limit >= $config['avatar-limit']);
		unset($limit);
	} else {
		define('LIMIT_REACHED', false);
	}
	
	// Editing and whatever
	if (isset($_POST['save'])) {
		check_token($_POST['auth']);
		
		if (!$_GET['edit']) {
			// Upload / edit default avatar
			$_POST['title']   = "Default";
			$_POST['hidden']  = 0;
			$newid            = 0;
		} else {
			if ($_GET['edit'] == -1) {
				// Add a new non-default avatar
				if (LIMIT_REACHED) {
					errorpage("Really think this would work, huh?");
				}

				// Pick the "first" available slot
				$newid = (int) $sql->resultq("SELECT MAX(file) FROM users_avatars WHERE user = {$_GET['id']}");
				$newid++; // Do it here to account for possible NULL result
			} else { 
				// Edit existing avatar
				$avatar = $sql->resultq("SELECT 1 FROM users_avatars WHERE user = {$_GET['id']} AND file = {$_GET['edit']}");
				if (!$avatar) {
					errorpage("You are trying to edit a nonexisting avatar.");
				}
				$newid     = $_GET['edit']; // Pick the selected avatar
			}
			
			$_POST['title']   = xssfilters(filter_string($_POST['title']));
			$_POST['hidden']  = filter_int($_POST['hidden']);
			if (!$_POST['title']) {
				errorpage("The avatar title cannot be blank.");
			}
		}
		
		$_FILES['upload'] = filter_array($_FILES['upload']);
		$_POST['weblink'] = xssfilters(trim(filter_string($_POST['weblink'])));
		
		// Make sure you aren't uploading something blank
		$valid_file = (isset($_FILES['upload']) && !filter_int($_FILES['upload']['error']));
		if (!$valid_file && !file_exists(avatar_path($_GET['id'], $newid)) && !$_POST['weblink']) {
			upload_error($_FILES['upload']);
			errorpage("You need to either upload an avatar or specify an URL.");
		}
		

		
		// actually create / update the avatar
		$qdata = [$_GET['id'], $newid, $_POST['title'], $_POST['hidden'], $_POST['weblink']];
		if ($valid_file) {
			$res = upload_avatar(
				$_FILES['upload'], // the file
				$config['max-avatar-size-bytes'], // some
				$config['max-avatar-size-x'], // image
				$config['max-avatar-size-y'], // limits
				avatar_path($_GET['id'], $newid), // image path
				$qdata // db data (user id, file id, title,...)
			);
			
			if (!$res) {
				errorpage("Sorry, but the avatar could not be uploaded. Try again later.");
			}
		} else {
			save_avatar($qdata);
		}
		
		//msg_holder::set_cookie("Avatar '<i>".htmlspecialchars($title)."</i>' uploaded!".DELAYED_CRAP);
		return header("Location: editavatars.php?id={$_GET['id']}");
	}
	
	
	// Iterate between avatars (always show the default regardless it if exists or not)
	$usermood = get_avatars($_GET['id'], AVATARS_ALL);
	if (!isset($usermood[0])) {
		$usermood[0] = dummy_avatar(DEFAULT_STR, 0);
		$usermood[0]['new'] = true;
		ksort($usermood);
	} else {
		$usermood[0]['title'] = DEFAULT_STR;
	}
	
	
	$txt = "";
	foreach ($usermood as $file => $data) {
		$txt .= avbox($_GET['id'], $file, $data);
	}
	
	pageheader("Edit avatars");
	
	?>
	<!-- extra global css for avatar tables -->
	<style type='text/css'>
		.avatarbox{
			width: <?= $config['max-avatar-size-x'] ?>px;
			height: <?= $config['max-avatar-size-y'] ?>px;
		}
		.sect {
			width: <?= $config['max-avatar-size-y']?>px;
			display: inline-block;
		}
		.sizex{width: <?= $config['max-avatar-size-x'] ?>px}
	</style>

	<div class="font center b">User avatars for <?= getuserlink(NULL, $_GET['id'], '', true) ?></div>
	<br/>
	<!--< ?=msg_holder::get_message() ?> -->
	<center>
	<?= $txt ?>
	<br/>
	<table class="table sect">
		<tr><td class="tdbgh sizex center b">New Avatar</td></tr>
		<tr><td class="tdbgc center"><a href="?id=<?=$_GET['id']?>&edit=-1">Upload</a></td></tr>
	</table>
	<?php
	
	// Upload form
	if ($_GET['edit'] !== NULL && ($_GET['edit'] < 1 || isset($usermood[$_GET['edit']]))) {
		
		$sel_hidden = "";
		$noopt      = "";
		$noview     = "";
		
		switch ($_GET['edit']) {
			case -1:
				$edit_title = "Upload a new avatar";
				$file = array(
					'title'   => "",
					'weblink' => '',
					'hidden'  => 0,
				);
				break;
			case 0: // Note: this is also used to upload a new default avatar
				$edit_title = "Editing the default avatar";
				$noopt      = " disabled readonly";
				$noview     = " style='display: none'";
				$file       = $usermood[0];
				break;
			default:
				$file       = $usermood[$_GET['edit']];
				$edit_title = "Editing '".htmlspecialchars($file['title'])."'";
				if ($file['hidden']) {
					$sel_hidden = " checked";
				}
		}
		
		
?>
	<br/>
	<br/>
	<form method="POST" action="?id=<?=$_GET['id']?>&edit=<?=$_GET['edit']?>" enctype="multipart/form-data">
	<table class="table" style="max-width: 800px">
		<tr><td class="tdbgh center" colspan=2><?= $edit_title ?></td></tr>
		<tr <?= $noview ?>>
			<td class="tdbg1 center b">Avatar title:</td>
			<td class="tdbg2">
				<input type="text" name="title" size=35 maxlength=30 value="<?= htmlspecialchars($file['title']) ?>"<?= $noopt ?>>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b" style="min-width: 150px">Image:</td>
			<td class="tdbg2">
				<input type="hidden" name="MAX_FILE_SIZE" value="<?= $config['max-avatar-size-bytes'] ?>">
				<input name="upload" type="file">
				<div class="fonts">
					Max size: <?= $config['max-avatar-size-x'] ?>&times;<?= $config['max-avatar-size-y'] ?> | <?= sizeunits($config['max-avatar-size-bytes']) ?>
				</div>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">External URL:</td>
			<td class="tdbg2">
				<input type="text" name="weblink" size=80 maxlength=127 value="<?= htmlspecialchars($file['weblink']) ?>"> &nbsp; 
				<span class="fonts nobr">This takes precedence over the uploaded image.</span>
			</td>
		</tr>
		<tr <?= $noview ?>>
			<td class="tdbg1 center b">Options:</td>
			<td class="tdbg2">
				<input type="checkbox" name="hidden" id="hidden" value=1<?= $sel_hidden.$noopt ?>>
				<label for="hidden">Hidden</label>
			</td>
		</tr>
		<tr>
			<td class="tdbg1 center b">&nbsp;</td>
			<td class="tdbg2">
				<input type="submit" class="submit" name="save" value="Save changes">
				<?= auth_tag() ?>
			</td>
		</tr>	
	</table>
	</form>

<?php		
	}
	
	
	pagefooter();
	
	function avbox($user, $file, $data, $options = 0) {	
		$data['title']   = htmlspecialchars($data['title']);
		$data['weblink'] = htmlspecialchars($data['weblink']);
		$options = $data['hidden'] ? "<b>Hidden</b>" : "";
		if (isset($data['new'])) {
			$links = "<a href='?id={$user}&edit={$file}'>Upload</a>";
			$image = "images/_.gif";
		} else {
			$links = "<a href='?id={$user}&edit={$file}'>Edit</a> - <a href='?id={$user}&del={$file}'>Delete</a>";
			$image = avatar_path($user, $file, $data['weblink']);
			if ($data['hidden']) {
				$data['title'] = "<i>{$data['title']}</i>";
			}
		}
		
		return "
			<table class='table sect left'>
				<tr><td class='tdbgh b center' style='min-width: 100px'>{$data['title']}</td></tr>
				<tr><td class='tdbg2 avatarbox center'><img class='avatar' src=\"{$image}\"></td></tr>
				<tr><td class='tdbgc center'>{$links}</td></tr>
			</table>
		";
	}
	
// TODO: Move out of this file
function confirmpage($message, $form_link, $buttons = NULL, $token = TOKEN_MAIN) {
	
	// Get the indicator
	static $i = 0; // just in case
	$key = "nk_page_confirm".($i++);
	$status = filter_bool($_POST[$key]);
	
	if ($status) { // All ok; do not print anything (unless the token is bad, that is)
		check_token($_POST['auth'], $token);
		return true;
	}
	
	if ($buttons !== NULL) {
		$commands = "";
		for ($i = 0, $cnt = count($buttons); $i < $cnt; ++$i) {
			if ($i) {
				$commands .= " - ";
			}
			// Support both links and buttons
			if (!isset($buttons[$i][1])) {
				$commands .= "<input type='submit' class='submit' name='{$key}' value=\"{$buttons[$i][0]}\">";
			} else {
				$commands .= "<a href=\"{$buttons[$i][1]}\">{$buttons[$i][0]}</a>";
			}
		}
	} else {
		$commands = "<input type='submit' class='submit' name='{$key}' value='Yes'> - <a href='#' onclick='window.history.go(-1); return false;'>No</a>";
	}
	
	if (!defined('HEADER_PRINTED')) {
		pageheader();
	}	
?>
	<form method="POST" action="<?= $form_link ?>">
	<center>
	<div class="table center tdbg1" style="max-width: 600px">
		<?= $message ?><br/>
		<br/>
		<?= $commands ?><br/>
		<?= auth_tag($token) ?>
	</div>
	</center>
	</form>
<?php

	pagefooter();
	return false;
}