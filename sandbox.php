<?php
$s = "agshfdkjfskjd";
echo max(7, min(3, $s));

die;
echo "<pre>";
print_r($_SERVER);
die;
echo "<img src='f<script src='ffdg'>";
die;
function escape_codeblock($str) {
	$text[0] = $str;
	$len = strlen($text[0]);
	$intext = $escape = $noprint = false;
	$prev = $ret = '';
	for ($i = 0; $i < $len; ++$i) {
		
		if (isset($text[0][$i+1])) $next = $text[0][$i+1];
		else $next = NULL;
		
		switch ($text[0][$i]) {
			case '(':
			case ')':
			case '[':
			case ']':
			case '{':
			case '}':
			case '=':
			case '<':
			case '>':
			case ':':
			
				if ($intext) break;
				$ret .= "<span style='color: #007700'>".htmlentities($text[0][$i])."</span>";
				$noprint = true;
				break;	

			case '+':
			case '-':
			case '&':
			case '|':
			case '!':
				if ($intext) break;
				$ret .= "<span style='color: #C0C0FF'>".htmlentities($text[0][$i])."</span>";
				$noprint = true;
				break;	
				
			// Argbl
			case '*':
				if ($intext || $prev == '/' || $next == '/') break;
				$ret .= "<span style='color: #C0C0FF'>".htmlentities($text[0][$i])."</span>";
				$noprint = true;
				break;
				
			case '/':
				if ($intext || $prev == '/' || $next == '/' || $prev == '*' || $next == '*') break;
				$ret .= "<span style='color: #C0C0FF'>".htmlentities($text[0][$i])."</span>";
				$noprint = true;
				break;
				
			case '"':
			case '\'':
				if ($escape || ($intext && $intext != $text[0][$i])) break;
				
				if (!$intext) {
					$ret .= "<span style='color: #DD0000'>";
					$intext = $text[0][$i];
				}
				else {
					$ret .= htmlentities($text[0][$i])."</span>";
					$intext = false;
					$noprint = true;
				}
				break;
				
			case '\\':
				if ($escape) break;
				$escape = $i;
				
		}
		
		if (!$noprint) 	$ret .= htmlspecialchars($text[0][$i]);
		else 			$noprint = false;
		
		$prev = $text[0][$i];
		
		// Escape effect lasts for only one character
		if ($escape && $escape != $i)
			$escape = false;
	}
	
	/*
		Comment lines
	*/
	$ret = preg_replace("'\/\*(.*?)\*\/'si", "<span style='color: #FF8000'>/*$1*/</span>",$ret); /* */
	$ret = preg_replace("'\/\/(.*?)\r?\n'i", "<span style='color: #FF8000'>//$1\r\n</span>",$ret); //
	
	$ret = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $ret);
	
	return "[quote]<table><tr><td style='background: #000; color: #fff; font-face: Courier, monospace'><code>$ret</code></td></tr></table>[/quote]";
}

echo nl2br(escape_codeblock(file_get_contents('sandbox.php')));

?>