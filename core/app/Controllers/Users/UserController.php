<?php 

namespace Porlts\App\Controllers\Users;

use Porlts\App\Logic\Dao;
use Porlts\App\Handlers\EmailHandler;

class UserController extends \Porlts\App\Controllers\Controller
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

	public function processRequest($route)
	{
		switch ($this->method) {
			case 'GET':

				if (isset($route[4]) && !empty($route[4])) {
					$this->getPackages($route[4]);
				}
				elseif (isset($route[3]) && !empty($route[3])) {
					switch ($route[3]) {
						case 'packages':
							$this->getPackages();
							break;

						case 'referred':
							$this->getReferred();
							break;
						
						default:
							$this->getProfile($route[3]);
							break;
					}
				}
				else {
					$this->getProfile();
				}
				break;
			
			case 'PATCH':
				$this->update();
				break;

			default:
				$this->response['code'] = $this->methodNotAllowed();
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'POST Request Method not allowed on this Route';
				break;
		}

		$this->sendResponse($this->response);
	}

	public function getProfile($id = null)
	{
		if ($id) {
			
			$stm = $this->db->query("SELECT * FROM porlt_users WHERE id = $id");
			$user = $stm->fetchObject();
			$this->response['body']['status'] = true;
			$this->response['body']['message'] = 'Profile';
			$this->response['body']['data'] = $user;
		}
		else {
			$this->response['body']['status'] = true;
			$this->response['body']['message'] = 'Profile';
			$this->response['body']['data'] = $this->auth($this->db);
		}
	}

	public function getPackages($status = null)
	{
		$user = $this->auth($this->db);
		if ($status) {
			$stm = $this->db->prepare("SELECT * FROM drop_offs WHERE user = :email AND status = '$status'");
		}
		else {
			$stm = $this->db->prepare("SELECT * FROM drop_offs WHERE user = :email AND status != 'delivered'");
		}

		$stm->execute(['email' => $user->email]);

		$this->response['body']['status'] = true;
		$this->response['body']['message'] = 'Packages';
		$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ);
	}

	public function update()
	{
		$user = $this->auth($this->db);
		$input = (array) json_decode(file_get_contents('php://input'), true);

		$isUpdating = false;
		$query = "UPDATE porlt_users";

		if (isset($input['fullname']) && !empty($input['fulname'])) {
			$query .= " SET fulname = " . $input['fullname'];
			$isUpdating = true;
		}

		if (isset($input['email']) && !empty($input['email'])) {
			if ($isUpdating) {
				$query .= ", email = " . $input['email']; 
			}
			else {
				$query .= " SET email = " . $input['email']; 
				$isUpdating = true;
			}
		}

		if (isset($input['phone']) && !empty($input['phone'])) {
			if ($isUpdating) {
				$query .= ", phone = " . $input['phone']; 
			}
			else {
				$query .= " SET phone = " . $input['phone']; 
				$isUpdating = true;
			}
		}

		if (isset($input['address']) && !empty($input['address'])) {
			if ($isUpdating) {
				$query .= ", address = " . $input['address']; 
			}
			else {
				$query .= " SET address = " . $input['address']; 
				$isUpdating = true;
			}
		}

		if ($isUpdating) {
			$query .= " WHERE id = :id";
			$stm = $this->db->prepare($query);
			$stm->execute(['id' => $user->id]);

			$this->response['body']['status'] = true;
			$this->response['body']['message'] = "Profile updated successfully";
		}
		else {

			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "Nothing to update";
		}
	}

	public function getReferred()
	{
		$user = $this->auth($this->db);

		$stm = $this->db->query("SELECT * FROM porlt_users WHERE referrer_id = " . $user->id);
		$this->response['body']['status'] = true;
		$this->response['body']['message'] = "My referred users";
		$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ); 
	}
}