<?php
	if (!isset($config)) { die("The required libraries have not been defined, you dumbass.<br/>(require function file first)"); }
	function libdec() { return true; }
	
	// magic fucking quotes
	if (!get_magic_quotes_gpc()) {
		$_GET = addslashes_array($_GET);
		$_POST = addslashes_array($_POST);
		$_COOKIE = addslashes_array($_COOKIE);
	}
	
	// register fucking globals
	if(!ini_get('register_globals')){
		$supers=array('_ENV', '_SERVER', '_GET', '_POST', '_COOKIE',);
		foreach($supers as $__s) if (isset($$__s) && is_array($$__s)) extract($$__s, EXTR_SKIP);
		unset($supers);
	}
	$id = filter_int($id);
	$page = filter_int($page);
	
	// pdo class wrapper
	if (!extension_loaded('mysql')) {
		require "lib/abcompat_sql.php";
	}
	
	// layout declarations. oh god
	$tablewidth = '100%';
	$fonttag    = '<font class="font">';
	$fonthead   = '<font class="fonth">';
	$smallfont  = '<font class="fonts">';
	$tinyfont   = '<font class="fontt">';
	foreach(array('1','2','c','h') as $celltype){
		$cell="<td class='tbl tdbg$celltype font";
		$celln="tccell$celltype";
		$$celln     =$cell." center'";
		${$celln.'s'} =$cell."s center'";
		${$celln.'t'} =$cell."t center'";
		${$celln.'l'} =$cell."'";
		${$celln.'r'} =$cell." right'";
		${$celln.'ls'}=$cell."s'";
		${$celln.'lt'}=$cell."t'";
		${$celln.'rs'}=$cell."s right'";
		${$celln.'rt'}=$cell."t right'";
	}
	$inpt='<input type="text" name';
	$inpp='<input type="password" name';
	$inph='<input type="hidden" name';
	$inps='<input type="submit" class=submit name';
	$inpc="<input type=checkbox name";
	$radio='<input type=radio class=radio name';
	$txta='<textarea wrap=virtual name';
	$tblstart='<table class="table" cellspacing=0>';
	$tblend='</table>';
	$br="\n";
	
	$boardname = $config['board-name'];
	$bconf = $config;
	
	// why was this being used in the first place?
	$log = $loguserid = $loguser['id'];
	
	// placeholder headers
	$header = ""; // <table class='table'><tr><td class='tdbg1 center b' style='height: 300px'>PAGE HEADER</td></tr></table>
	$footer = "";
	$stamptime = true;
	// Removed / unused functions follow
	// Only used to check if an user exists
	function checkusername($name){
		global $sql;
		if (!$name) return -1;
		$u = $sql->resultp("SELECT id FROM users WHERE name = ?", [$name]);
		if (!$u) $u = -1;
		return $u;
	}
	function loaduser($id,$type=1){	return load_user($id); }
	function printtimedif($x=0) { pagefooter(); }
	function makeheader() { return ""; }
	pageheader();