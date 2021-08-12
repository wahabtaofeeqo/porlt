<?php 

namespace Porlts\App\Controllers\Auth;

use Porlts\App\Controllers\Controller;
use Porlts\App\Logic\Dao;
use Porlts\App\Handlers\EmailHandler;

/**
 * 
 */
class RegisterController extends Controller
{
	use Dao, EmailHandler;

	private $db;
	private $method;
	private $response;

	function __construct($method, $db)
	{

		$this->db = $db;
		$this->method = $method;
		$this->response = array(
			'code' => $this->ok(),
			'body' => []);
	}

	public function processRequest()
	{
		switch ($this->method) {
			case 'POST':
				$this->register();
				break;
			
			default:
				$this->response['code'] = $this->methodNotAllowed();
				$this->response['body']['message'] = 'Request Method not allowed on this Route';
				break;
		}

		$this->sendResponse($this->response);
	}

	public function register()
	{
		$input = json_decode(file_get_contents('php://input'), true);
		if ($this->validate($input)) {
			
			$emailExist = $this->checkEmail($this->db, $input['email']);
			$phoneExist = $this->checkPhone($this->db, $input['phone']);

			if (!$emailExist && !$phoneExist) {
				try {

					$query = "INSERT INTO porlt_users (fulname, email, phone, password, code, date_t, status, referrer_id, verification_code) 
					VALUES(:fname, :email, :phone, :password, :code, :created, :status, :refId, :vCode)";

					if (isset($input['referrer_code']) && !empty($input['referrer_code'])) {
						$this->populateReferalBonus($input['referrer_code']);
						$code = $input['referrer_code'];
						$stm = $this->db->query("SELECT * FROM porlt_users WHERE code = '$code'");
						$referrer = $stm->fetchObject();
					}

					$code = substr($input['name'], 0, 2) . uniqid() . substr($input['name'], strlen($input['name']) - 2);
					$stm = $this->db->prepare($query);

					$vCode = rand();
					$stm->execute([
						'fname' => $input['name'],
						'email' => $input['email'],
						'phone' => $input['phone'],
						'password' => sha1($input['password']),
						'code' => $code,
						'created' => date('Y-m-d'),
						'refId' => isset($referrer) ? $referrer->id : '',
						'status' => 'registered',
						'vCode' => $vCode]);

					$stm->rowCount();

					// Send Email
					$this->welcomeEmail($input['email'], $vCode);

					$this->response['code'] = $this->statusCreated();
					$this->response['body']['message'] = "User account created";
					$this->response['body']['status'] = true;

				} catch (\PDOException $e) {
					$this->response['body']['message'] = $e->getMessage();
					$this->response['body']['status'] = false;
				}
			}
			else {
				$this->response['body']['message'] = (empty($emailExist)) ? ((!empty($phoneExist)) ? "Phone has already been taken" : "") : "Email has alreay been taken";
				$this->response['body']['status'] = false;
			}
		}
	}

	private function validate($input)
	{
		if (!isset($input['email']) || empty(trim($input['email']))) {
			$this->response['body']['message'] = 'Email is required';
			return false;
		}

		if (!isset($input['phone']) || empty(trim($input['phone']))) {
			$this->response['body']['message'] = 'Phone is required';
			return false;
		}

		if (!isset($input['password']) || empty(trim($input['password']))) {
			$this->response['body']['message'] = 'Password is required';
			return false;
		}		

		if (!isset($input['name']) || empty(trim($input['name']))) {
			$this->response['body']['message'] = 'Name is required';
			return false;
		}		

		return true;
	}

	public function populateReferalBonus($code)
	{
		
	}
}