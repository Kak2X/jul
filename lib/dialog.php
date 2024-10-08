<?php

if ($pagetitle === NULL) $pagetitle = $title; 

?><!doctype html>
<html>
	<head>
		<title><?=$pagetitle?></title>
		<link rel="shortcut icon" href="images/favicon/favicon.ico" type="image/x-icon">
		<link rel='stylesheet' href='schemes/base.css' type='text/css'>
		<style type='text/css'>
			a,.buttonlink                   { color: #BEBAFE; }
			a:visited,.buttonlink:visited   { color: #9990c0; }
			a:active,.buttonlink:active     { color: #CFBEFF; }
			a:hover,.buttonlink:hover 	    { color: #CECAFE; }
			body {
				color: #DDDDDD;
				font-family: verdana;
				background: #000F1F url('schemes/night/starsbg.png');
			}
			.font 	{font:100% verdana}
			.fonth	{font:100% verdana;color:FFEEFF}
			.fonts	{font:75% verdana}
			.fontt	{font:75% tahoma}
			.tdbg1	{background:#111133}
			.tdbg2	{background:#11112B}
			.tdbgc	{background:#2F2F5F}
			.tdbgh	{background:#302048; color:FFEEFF}
			.table	{empty-cells:	show; width: 100%;
					 border-top:	#000000 1px solid;
					 border-left:	#000000 1px solid;
					 border-spacing: 0px;
					 font-family: verdana;}
			.tdbg1,.tdbg2,.tdbgc,.tdbgh	{
					 border-right:	#000000 1px solid;
					 border-bottom:	#000000 1px solid}
		
			textarea,input,select{
			  border:	#663399 solid 1px;
			  background:#000000;
			  color:	#DDDDDD;
			  font:	100% verdana;}
			textarea:focus {
			  border:	#663399 solid 1px;
			  background:#000000;
			  color:	#DDDDDD;
			  font:	100 verdana;}
			input[type='radio']{
			  border:	none;
			  background:none;
			  color:	#DDDDDD;
			  font:	100 verdana;}
			input[type='submit']{
			  border:	#663399 solid 2px;
			  font:	100 verdana;}
			body, #w {
				padding: 0px !important;
				margin: 0px !important;
				color: #fff !important;
				position: fixed !important;
			}
			#w {
				background: #000F1F url('schemes/night/starsbg.png');
				left: 0px !important;
				top: 0px !important;
				width: 100%;
				height: 100%;
			}
		</style>
	</head>
	<body>
		<div id='w' class='flexhvc'>
			<table class="tablevc">
				<tr>
					<td>
					<table class="table">
						<tr>
							<td class='tdbgh center b' style="padding: 3px;">
								<?=$title?>
							</td>
						</tr>
						<tr>
						  <td class='tdbg1 center'>
							&nbsp;<br><?=$message?><br>&nbsp;
						  </td>
						</tr>
					</table>
					</td>
				</tr>
			</table>
		</div>
	</body>
</html>