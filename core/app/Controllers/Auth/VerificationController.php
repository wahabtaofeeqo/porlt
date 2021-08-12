<?php 

namespace Porlts\App\Controllers\Auth;
use Firebase\JWT\JWT;

class VerificationController extends \Porlts\App\Controllers\Controller
{
	
	private $method;
	private $response;
	private $db;

	function __construct($method, $connection)
	{
		$this->response = array(
			'code' => $this->ok(),
			'body' => []);

		$this->method = $method;
		$this->db = $connection;
	}

	public function processRequest()
	{
		
		switch ($this->method) {
			case 'POST':
				$this->verify();
				break;
			
			default:
				$this->routeNotFound();
				break;
		}

		$this->sendResponse($this->response);
	}

	public function verify()
	{
		$input = (array) json_decode(file_get_contents("php://input"), true);
		if ($this->validate($input)) {

			try {

				$email = $input['email'];
				$stm = $this->db->query("SELECT * FROM porlt_users WHERE email = '$email'");
				$user = $stm->fetchObject();

				if ($user) {
					if ($user->verification_code == $input['code']) {
						$stm = $this->db->prepare("UPDATE porlt_users SET status = :status WHERE id = :id");
						$stm->execute([
							'status' => 'verified',
							'id' => $user->id]);

						$this->response['body']['status'] = true;
						$this->response['body']['message'] = "Your account has been verified";
					}
					else {
						$this->response['body']['status'] = false;
						$this->response['body']['message'] = "The Verification code is not correct";
					}
				}
				else {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = "Email is not correct";
				}
			} 
			catch (\PDOException $e) {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = $e->getMessage();
			}
		}
	}

	public function validate($input)
	{
		if (!isset($input['email']) || empty(trim($input['email']))) {
			$this->response['body']['message'] = 'Email is required';
			return false;
		}

		if (!isset($input['code']) || empty(trim($input['code']))) {
			$this->response['body']['message'] = 'Code is required';
			return false;
		}		

		return true;
	}
}