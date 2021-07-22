<?php 

namespace Porlts\App\Controllers\Auth;
use Firebase\JWT\JWT;

class LoginController extends \Porlts\App\Controllers\Controller
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
			case 'GET':
				$this->response['code'] = $this->methodNotAllowed();
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'Get Request Method not allowed on this Route';
				break;
			
			default:
				$this->login();
				break;
		}

		$this->sendResponse($this->response);
	}

	public function login()
	{
		$input = (array) json_decode(file_get_contents("php://input"), true);
		if ($this->validate($input)) {

			try {

				$query = "SELECT * FROM porlt_users WHERE email = :email AND password = :password";

				$stm = $this->db->prepare($query);
				$stm->execute(['email' => $input['username'], 'password' => sha1($input['password'])]);
				$user = $stm->fetchObject();

				if ($user) {
					if (strtolower($user->status) != 'verified') {
						$this->response['body']['status'] = true;
						$this->response['body']['message'] = "Your account has not been verified";
						$this->response['body']['verification_status'] = $user->status;
					}
					else {
						$this->response['body']['status'] = true;
						$this->response['body']['access_token'] = $this->getAuthToken($user);
					}
				}
				else {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = "Username OR Password not connect";
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
		if (!isset($input['username']) || empty(trim($input['username']))) {
			$this->response['body']['message'] = 'Username is required';
			return false;
		}

		if (!isset($input['password']) || empty(trim($input['password']))) {
			$this->response['body']['message'] = 'Password is required';
			return false;
		}		

		return true;
	}
}