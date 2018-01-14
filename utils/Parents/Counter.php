<?php

	// Default class for admin-counter utilities
	class Counter {
		// eugh
		protected $title       = "*** Title goes here ***";
		protected $description = "*** Description goes here ***";
		protected $locks       = NULL;
		
		public function get_title() {return $this->title; }
		public function get_description() { return $this->description; }
		public function get_locks() { return $this->locks; }
		
		public function launch() {
			global $sql;
		}
	}