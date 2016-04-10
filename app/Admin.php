<?php

class Admin extends Controller {
	function beforeroute($f3) {
		$function = strtolower($f3->get('PARAMS.function'));
		if (empty($function)) {
			$function = "main";
		}
		if ( method_exists($this, $function) ) {
			if ($function == 'login') {
			} else if ($function == 'logout' ) {
				// Do nothing
			} else {
				$f3->reroute('/admin/login');
			}

			// Update session data
			$f3->set('SESSION.lastseen', time());
		}
	}

	function exec($f3, $args) {
		$function = strtolower($args['function']);
		if (empty($function)) {
			$function = "main";
		}

		if ( method_exists($this, $function) ) {
			call_user_func_array(array($this, $function), array($f3));
		} else {
			$f3->error(404);
		}
	}

	function main($f3) {
	}

	function setup($f3) {
		$this->db->exec(explode(';', $f3->read('setup.sql')));
		echo "Setup Complete";
	}

	function login($f3) {
		$username = $f3->get('POST.username');
		$password = $f3->get('POST.password');

		if ( !empty($username) && !empty($password) ) {
			// Attempting to Login
			if ( !$f3->get('COOKIE.login') ) {
				$f3->set('message','Cookies must be enabled to enter this area');
			} else {
				$captcha = $f3->get('SESSION.captcha');
				if ( $captcha && strtoupper($f3->get('POST.captcha')) != $captcha ) {
					$f3->set('message','Invalid CAPTCHA code');
				} else if ( $username != $f3->get('ADMIN_USERNAME') || $password != $f3->get('ADMIN_PASSWORD') ) {
					$f3->set('message','Invalid Username or Password');
				} else {
					$f3->clear('COOKIE.sent');
					$f3->clear('SESSION.captcha');
					$f3->set('SESSION.username', $f3->get('ADMIN_USERNAME'));
					$f3->set('SESSION.crypt', $f3->get('ADMIN_PASSWORD'));
					$f3->set('SESSION.lastseen', time());
					$f3->reroute('/admin');
				}
			}
		}

		$f3->clear('SESSION');
		$f3->set('COOKIE.login', true);

		if ($f3->get('message')) {
			$img = new Image;
			$f3->set('captcha', $f3->base64(
				$img->captcha('fonts/thunder.ttf',18,5,'SESSION.captcha')->dump(),'image/png')
			);
		}

		$f3->set('inc','login.html');
	}

	function logout($f3) {
		$f3->clear('SESSION');
		$f3->reroute('/admin/login');
	}
}

?>
