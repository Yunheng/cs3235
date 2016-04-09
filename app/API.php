<?php

class API extends Controller {
	private $result;
	
	function beforeroute($f3) {
	}

	function afterroute() {
		echo json_encode($this->result);
	}
	
	function exec($f3,$args) {
		$function = $args['function'];
		
		if ( method_exists($this, $function) ) {
			call_user_func_array(array($this, $function), array($f3));
		} else {
			$this->result['status'] = 500;
			$this->result['message'] = 'No such API Method';
		}
	}
	
	function EnrolUser($f3) {
		$userId = $f3->get('POST.userId');
		$current_time = time();
		
		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('id=?', $userId));
		
		if ( $user->dry() ) {
			$this->result['status'] = 500;
			$this->result['message'] = 'Invalid User';
		} else {
			$token = new \DB\SQL\Mapper($this->db, 'otp_tokens');
			$token->load(array('userId=?', $user->id));
			
			$otp = mt_rand(100000, 999999);
			$token->userId = $user->id;
			$token->token = $otp;
			$token->status = 1;
			$token->issued = $current_time;
			$token->expiry = $current_time + 300;
			$token->save();

			$this->result['status'] = 200;
			$this->result['message'] = 'Token Issued';
			$this->result['token'] = $otp;
		}
	}

	function EnrolVerify($f3) {
		$userId = $f3->get('POST.userId');
		$otp = $f3->get('POST.otp');
		$current_time = time();

		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('id=?', $userId));
		
		if ( $user->dry() ) {
			$this->result['status'] = 500;
			$this->result['message'] = 'Invalid User';
		} else {
			$token = new \DB\SQL\Mapper($this->db, 'otp_tokens');
			$token->load(array('userId=?', $user->id));
			
			if ( $token->status == 1 ) {
				if ( $current_time <= $token->expiry ) {
					if ( $token->token == $otp ) {
						$token->status = 0;
						$token->save();
						
						$access_token = "fda";
						
						$access = new \DB\SQL\Mapper($this->db, 'access_tokens');
						$access->userId = $user->id;
						$access->token = $access_token;
						$access->issued = $current_time;
						$access->expiry = $current_time + 3600;
						$access->save();

						$this->result['status'] = 200;
						$this->result['message'] = 'Success';
						$this->result['access_token'] = $access_token;
					} else {
						$this->result['status'] = 500;
						$this->result['message'] = 'Token Invalid';
					}						
				} else {
					$this->result['status'] = 500;
					$this->result['message'] = 'Token Expired';
				}
			} else {
				$this->result['status'] = 500;
				$this->result['message'] = 'Token Already Verified';
			}				
		}			
	}

	function ExpelUser($f3) {
		$userId = $f3->get('POST.userId');
		$deviceId = $f3->get('POST.deviceId');
		
		
	}
	
	function AccessRoom($f3) {
	}	
}

?>