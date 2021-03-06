<?php

require_once __DIR__ . "/lib/random.php";

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
			$this->result['message'] = 'Invalid API Method';
		}
	}

	function EnrolUser($f3) {
		$userId = $f3->get('POST.userid');
		$current_time = new DateTime();

		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('id=?', $userId));

		$log = new \DB\SQL\Mapper($this->db, 'logs');

		if ( $user->dry() ) {
			$log->time = $current_time->format("Y-m-d H:i:s");
			$log->ipAddress = $f3->get('IP');
			$log->message = '[UserID: ' . $userId . '] Invalid Login Attempt';
			$log->save();


			$this->result['status'] = 500;
			$this->result['message'] = 'Invalid Login';
		} else {
			$token = new \DB\SQL\Mapper($this->db, 'otp_tokens');
			$token->load(array('userId=?', $user->id));

			$otp = mt_rand(100000, 999999);
			$token->userId = $user->id;
			$token->token = $otp;
			$token->status = 1;
			$token->issued = $current_time->format("Y-m-d H:i:s");
			$token->expiry = $current_time->add(new DateInterval("PT5M"))->format("Y-m-d H:i:s");
			$token->save();

			// Send email
			$smtp = new \SMTP($f3->get('SMTP_SERVER'), $f3->get('SMTP_PORT'), '', $f3->get('SMTP_USERNAME'), $f3->get('SMTP_PASSWORD'));
			$smtp->set('From', '"securelock@vfix.net" <securelock@vfix.net>');
			$smtp->set('To', '"' . $user->email . '" <' . $user->email . '>');
			$smtp->set('Subject', 'One-Time Password for SecureLock');
			if ( $smtp->send($otp) ) {
				$this->result['status'] = 200;
				$this->result['message'] = 'Token Issued';
				$this->result['expiry'] = $token->expiry;

				$log->time = $current_time->format("Y-m-d H:i:s");
				$log->ipAddress = $f3->get('IP');
				$log->message = '[UserID: ' . $userId . '] Token Issued and Expires ' . $token->expiry;
				$log->save();
			} else {
				$log->time = $current_time->format("Y-m-d H:i:s");
				$log->ipAddress = $f3->get('IP');
				$log->message = '[UserID: ' . $userId . '] Token Failed to Issue';
				$log->save();

				$this->result['status'] = 500;
				$this->result['message'] = 'Token Failed';
				$token->erase();
			}
		}
	}

	function EnrolVerify($f3) {
		$userId = $f3->get('POST.userid');
		$otp = $f3->get('POST.otp');

		$current_time = new DateTime();

		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('id=?', $userId));

		if ( $user->dry() ) {
			$this->result['status'] = 500;
			$this->result['message'] = 'Invalid User';
		} else {
			$token = new \DB\SQL\Mapper($this->db, 'otp_tokens');
			$token->load(array('userId=?', $user->id));

			if ( $token->status == 1 ) {
				$expiry = new DateTime($token->expiry);
				if ( $current_time <= $expiry ) {
					if ( $token->token == $otp ) {
						try {
							$secretkey = random_bytes(32);
							$otpsecret = random_bytes(128);
							$encoded_otpsecret = Base32\Base32::encode($otpsecret);

							$access_token = $this->GUID();
							$token->status = 0;

							$user->otpsecret = $encoded_otpsecret;

							$access = new \DB\SQL\Mapper($this->db, 'access_tokens');
							$access->load(array('userId=?', $user->id));
							$access->userId = $user->id;
							$access->token = $access_token;
							$access->secretkey = $secretkey;
							$access->issued = $current_time->format("Y-m-d H:i:s");
							$access->expiry = $current_time->add(new DateInterval("PT1H"))->format("Y-m-d H:i:s");

							$access->save();
							$token->save();
							$user->save();

							$this->result['status'] = 200;
							$this->result['message'] = 'Success';
							$this->result['access_token'] = $access_token;
							$this->result['secret_key'] = base64_encode($secretkey);
							$this->result['otp_key'] = $encoded_otpsecret;
						} catch (TypeError $e) {
						    // Well, it's an integer, so this IS unexpected.
						    die("An unexpected error has occurred");
						} catch (Error $e) {
						    // This is also unexpected because 32 is a reasonable integer.
						    die("An unexpected error has occurred");
						} catch (Exception $e) {
						    // If you get this message, the CSPRNG failed hard.
						    die("Could not generate a random string. Is our OS secure?");
						}
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

	function OTPVerify($f3) {
		$userId = $f3->get('POST.userid');
		$otp = $f3->get('POST.otp');

		$user = new \DB\SQL\Mapper($this->db, 'users');
		$user->load(array('id=?', $userId));

		$totp = new \OTPHP\TOTP(
		    $userId, 			// Label
		    $user->otpsecret,	// The secret
		    30,                 // The period (default value is 30)
		    'sha256',           // The digest algorithm (default value is 'sha1')
		    10                  // The number of digits (default value is 6)
		);

		if ( $totp->verify($otp) ) {
			$this->result['status'] = 200;
			$this->result['message'] = 'OTP is valid';
		} else {
			$this->result['status'] = 500;
			$this->result['message'] = 'OTP is invalid';
		}
	}

	function ExpelUser($f3) {
		$userId = $f3->get('POST.userid');
		$deviceId = $f3->get('POST.deviceid');
		$msg = trim($f3->get('POST.msg'));
		$current_time = new DateTime();

		$log = new \DB\SQL\Mapper($this->db, 'logs');

		$token = new \DB\SQL\Mapper($this->db, 'access_tokens');
		$token->load(array('userId=?', $userId));
		if ( $token->dry() || $token->expiry > $current_time ) {
			$log->time = $current_time->format("Y-m-d H:i:s");
			$log->ipAddress = $f3->get('IP');
			$log->message = '[UserID: ' . $userId . '] Failed to Reokve Device ' . $deviceId;
			$log->save();

			$this->result['status'] = 500;
			$this->result['message'] = 'Invalid Token';
		} else {
			// Check TOTP Accuracy
			$user = new \DB\SQL\Mapper($this->db, 'users');
			$user->load(array('id=?', $token->userId));
			$totp = new \OTPHP\TOTP(
			    $userId, 			// Label
			    $user->otpsecret,	// The secret
			    30,                 // The period (default value is 30)
			    'sha1',           // The digest algorithm (default value is 'sha1')
			    6                  // The number of digits (default value is 6)
			);

			$curotp = $totp->now();
		 	$hash = hash("sha256", $token->token . $curotp);

			if ( $hash == $msg ) {
				$log->time = $current_time->format("Y-m-d H:i:s");
				$log->ipAddress = $f3->get('IP');
				$log->message = '[UserID: ' . $userId . '] Device Revoked ' . $token->token;
				$log->save();

				$token->erase();
				$this->result['status'] = 200;
				$this->result['message'] = 'Success';
			} else {
				$log->time = $current_time->format("Y-m-d H:i:s");
				$log->ipAddress = $f3->get('IP');
				$log->message = '[UserID: ' . $userId . '] Failed to Revoke Device ' . $token->token;
				$log->save();

				$this->result['status'] = 500;
				$this->result['message'] = 'OTP or Token is invalid';
			}
		}
	}

	function AccessRoom($f3) {
		$userId = $f3->get('POST.userid');
		$lockId = $f3->get('POST.lockid');
		$msg = trim($f3->get('POST.msg'));
		$current_time = new DateTime();

		$log = new \DB\SQL\Mapper($this->db, 'logs');

		$token = new \DB\SQL\Mapper($this->db, 'access_tokens');
		$token->load(array('userId=?', $userId));
		if ( $token->dry() || $token->expiry > $current_time ) {
			$log->time = $current_time->format("Y-m-d H:i:s");
			$log->ipAddress = $f3->get('IP');
			$log->message = '[UserID: ' . $userId . '] Failed Access to Room ' . $lockId;
			$log->save();

			$this->result['status'] = 500;
			$this->result['message'] = 'Invalid Token';
		} else {
			// Check Access Matrix
			$access = new \DB\SQL\Mapper($this->db, 'access');
			$access->load(array('userId=? and lockId=?', $userId, $lockId));
			if ( $access->dry() || $access->access == 0 ) {
				$log->time = $current_time->format("Y-m-d H:i:s");
				$log->ipAddress = $f3->get('IP');
				$log->message = '[UserID: ' . $userId . '] Failed Access to Room ' . $lockId;
				$log->save();

				$this->result['status'] = 500;
				$this->result['message'] = 'No Access';
			} else {
				// Check TOTP Accuracy
				$user = new \DB\SQL\Mapper($this->db, 'users');
				$user->load(array('id=?', $token->userId));
				$totp = new \OTPHP\TOTP(
				    $userId, 			// Label
				    $user->otpsecret,	// The secret
				    30,                 // The period (default value is 30)
				    'sha1',           // The digest algorithm (default value is 'sha1')
				    6                  // The number of digits (default value is 6)
				);

				$curotp = $totp->now();
			 	$hash = hash("sha256", $token->token . $curotp);

				if ( $hash == $msg ) {
					$log->time = $current_time->format("Y-m-d H:i:s");
					$log->ipAddress = $f3->get('IP');
					$log->message = '[UserID: ' . $userId . '] Granted Access to Room ' . $lockId;
					$log->save();

					$this->result['status'] = 200;
					$this->result['message'] = 'Success';
				} else {
					$log->time = $current_time->format("Y-m-d H:i:s");
					$log->ipAddress = $f3->get('IP');
					$log->message = '[UserID: ' . $userId . '] Failed Access to Room ' . $lockId;
					$log->save();

					$this->result['status'] = 500;
					$this->result['message'] = 'OTP or Token is invalid';
				}
			}
		}
	}

	private function GUID()
	{
    	if (function_exists('com_create_guid') === true)
    	{
        	return trim(com_create_guid(), '{}');
    	}

		return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
	}
}

?>
