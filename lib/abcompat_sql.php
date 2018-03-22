<?php

	const MYSQL_ASSOC = PDO::FETCH_ASSOC;
	const MYSQL_NUM   = PDO::FETCH_NUM;
	const MYSQL_BOTH  = PDO::FETCH_BOTH;
		
	function mysql_query($query) {
		global $sql;
		return $sql->query($query);
	}
	function mysql_fetch_array($res) {
		global $sql;
		return $sql->fetch($res, PDO::FETCH_BOTH);
	}
	function mysql_fetch_assoc($res) {
		global $sql;
		return $sql->fetch($res, PDO::FETCH_ASSOC);
	}
	function mysql_fetch_row($res) {
		global $sql;
		return $sql->fetch($res, PDO::FETCH_NUM);
	}
	function mysql_fetch_object($res) {
		global $sql;
		return $sql->fetch($res, PDO::FETCH_OBJ);
	}
	function mysql_result($res) {
		global $sql;
		return $sql->result($res);
	}
	function mysql_data_seek($res) { return $res; }
	function mysql_escape_string($str) {
		global $sql;
		return $sql->escape($str);
	}
	function mysql_real_escape_string($str) {
		global $sql;
		return $sql->escape($str);
	}
	function mysql_insert_id() {
		global $sql;
		return $sql->insert_id();
	}
	function mysql_num_rows($res) {
		global $sql;
		return $sql->num_rows($res);
	}