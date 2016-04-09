<?php

class Controller {
	protected $db;

	function beforeroute($f3) {
	}

	function afterroute() {
	}

	function __construct() {
		$f3 = Base::instance();
		
		$db = new DB\SQL('mysql:host=' . $f3->get('MYSQL_HOST') . ';port=3306;dbname=' . $f3->get('MYSQL_DATABASE') . '',
			$f3->get('MYSQL_USERNAME'),
			$f3->get('MYSQL_PASSWORD')
		);
		
		if (file_exists('setup.sql')) {
			$db->exec(explode(';', $f3->read('setup.sql')));
			rename('setup.sql', 'setup.$ql');
		}

		new DB\SQL\Session($db);
		$this->db = $db;
	}
}
