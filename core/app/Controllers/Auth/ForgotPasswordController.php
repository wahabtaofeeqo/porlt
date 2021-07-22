<?php 

namespace Porlts\App\Controllers\Auth;

use Porlts\App\Logic\Dao;
use Porlts\App\Handlers\EmailHandler;

class ForgotPasswordController extends \Porlts\App\Controllers\Controller
{
	use Dao, EmailHandler;

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

	public function processRequest($type = null)
	{
		
		switch ($this->method) {
			case 'GET':
				$this->response['code'] = $this->methodNotAllowed();
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'Get Request Method not allowed on this Route';
				break;
			
			default:
				if ($type == 'email') {
					$this->process();
				}
				else {
					$this->resetPassword();
				}

				break;
		}

		$this->sendResponse($this->response);
	}

	public function process()
	{
		$input = json_decode(file_get_contents("php://input"), true);
		if ($this->validate($input)) {
			
			$user = $this->checkEmail($this->db, $input['email']);
			if ($user) {

				$code = 1235;
				if ($this->sendResetCode($user->email, $code)) {

					$query = "INSERT INTO resets (email, code) VALUES (:email, :code)";
					$stm = $this->db->prepare($query);
					$stm->execute(['email' => $user->email, 'code' => $code]);

					$this->response['body']['status'] = true;
					$this->response['body']['message'] = 'We have sent a code to your email';
				}
				else {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = 'Operation not succeeded';
				}
			}
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'Email not recognised';
			}
		}
	}

	public function validate($input)
	{
		if (!isset($input['email']) || empty(trim($input['email']))) {

			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Email is required';
			return false;
		}

		return true;
	}

	public function resetPassword()
	{
		$input = json_decode(file_get_contents("php://input"), true);
		if ($this->validate($input)) {

			$user = $this->checkEmail($this->db, $input['email']);
			$code = isset($input['code']) ? $input['code'] : '';
			$password = isset($input['password']) ? $input['password'] : '';

			if ($user) {

				if (empty($code)) {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = "Code is required";
				}
				else if (empty($password)) {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = "Password is required";
				}
				else {

					$query = "SELECT * FROM resets WHERE email = :email AND code = :code";
					$stm = $this->db->prepare($query);
					$stm->execute(['email' => $input['email'], 'code' => $code]);
					$response = $stm->fetchObject();

					if ($response) {

						// Reset 
						$query = "UPDATE porlt_users SET password = :password WHERE id = :id";
						$stm = $this->db->prepare($query);
						$stm->execute(['password' => sha1($password), 'id' => $user->id]);

						// Delete code
						$this->db->exec("DELETE FROM resets WHERE id = " . $response->id);

						$this->response['body']['status'] = true;
						$this->response['body']['message'] = "Your Password has been reseted";
					}
					else {
						$this->response['body']['status'] = false;
						$this->response['body']['message'] = "Code is not correct";
					}
				}
			}
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = "Email is not recognised";
			}
		}
	}
}