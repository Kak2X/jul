<?php

class TreeView {
	private static $catid = 0;
	private static $total = 0;
	public $title;
	public $links;
	
	public function __construct($title, $links) {
		$this->title  = $title;
		$this->links  = $links;
	}
		
	public function DisplaySidebar($sel, $subSel = "", $extraOpt = null) {
		$linksHtml = self::Generate($this->links, $sel, $subSel, $extraOpt);
		
		$css = "";
		if (!self::$total) {
			$css = "<link rel='stylesheet' type='text/css' href='css/treeview.css'>";
		}
		++self::$total;
		
		return "{$css}
		<div style='display: flex'>
			<table class='table' style='width: 0px; margin-right: 10px'>
				<tr><td class='tdbgh center b' style='padding-right: 20px'>".$this->title."</td></tr>
				<tr><td class='tdbg1 nobr' style='padding-right: 20px; padding-left: 10px'>{$linksHtml}</td></tr>
			</table>
			<div class='font' style='flex-grow: 1'>
		";
	}
	
	public function DisplayBottom() {
		return "</div></div>";
	}
	private static function SetCat($key) {
		$out = "".
			"<input type='checkbox' class='treeview-check' checked id='_trv".self::$catid."'>".
			"<label for='_trv".self::$catid."'>".
			"	<span class='treeview-box'></span>".
			"	<span class='treeview-label b i'>{$key}</span>".
			"</label>".
			"<br><span class='treeview-items'>";
		++self::$catid;
		return $out;
	}
	public static function Generate($linkset, $sel = NULL, $subsel = NULL, $extraopt = NULL, $nesting = 0) {
		$out    = "";
		foreach ($linkset as $key => $val) {
			if (is_array($val)) {
				// Numeric labels are for uncategorized items.
				if (!is_int($key)) {
					$treectrl   = self::SetCat($key);
					$np         = $nesting + 1;
				} else {
					$np   	    = $nesting;
					$treectrl 	= "<span>";
				}
				$out .= "<span>".$treectrl.self::Generate($val, $sel, $subsel, $extraopt, $np)."</span></span>";
			} else {
				// Selected option is not a link
				if ($sel == $key) {
					// Extra options are appended after the main link
					if ($extraopt !== NULL) {
						$out .= "<span>".self::SetCat($val).self::Generate($extraopt, $sel.$subsel, NULL, NULL, $nesting + 1)."</span></span>";
						continue;
					} else {
						$w     = 'b';
					}
				} else {
					$w    = 'a';
				}				
				$out .= "<div class='treeview-item'><{$w} href='{$key}'>{$val}</{$w}></div>";
			}
		}
		return $out;
	}
	
	// For converting the page menu lists to the tree view format.
	public static function ParseSubmenu($submenu, $keyformat) {
		$newmenu = array();
		foreach ($submenu as $id => $label) {
			$newmenu[$keyformat.$id] = $label;
		}
		return $newmenu;
	}
}