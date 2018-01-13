<?php

	// Default class for admin-counter utilities
	class Counter {
		// eugh
		protected $title       = "*** Title goes here ***";
		protected $description = "*** Description goes here ***";
		
		public function get_title() {return $this->title; }
		public function get_description() { return $this->description; }
		
		public function launch() {
			global $sql;
		}
	}