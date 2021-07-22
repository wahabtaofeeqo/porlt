<?php 

namespace Porlts\App\Controllers\Carriers;

use Porlts\App\Logic\Dao;
use Porlts\App\Handlers\EmailHandler;

class CarrierController extends \Porlts\App\Controllers\Controller
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

				if (isset($route[3]) && !empty($route[3])) {
					$this->getPackages($route[3]);
				}
				else {
					$this->getPackages();
				}
				break;

			default:
				$this->response['code'] = $this->methodNotAllowed();
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'POST Request Method not allowed on this Route';
				break;
		}

		$this->sendResponse($this->response);
	}

	public function getPackages($status = null)
	{
		$user = $this->auth($this->db);
		if (!$status) {
			$stm = $this->db->prepare("SELECT * FROM drop_offs WHERE carrier_id = :carrier");
			$stm->execute(['carrier' => $user->email]);

			$packages = $stm->fetchAll(\PDO::FETCH_OBJ);
			if ($packages) {
			 	$this->response['body']['status'] = true;
				$this->response['body']['message'] = "Packages";
				$this->response['body']['data'] = $packages;
			} 
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = "No Packages found 1";
			}
		}
		else {
			$stm = $this->db->prepare("SELECT * FROM drop_offs WHERE carrier_id = :carrier AND status = :status");
			$status = ($status == 'picked') ? 'picked up' : $status;
			$stm->execute(['carrier' => $user->email, 'status' => $status]);

			$packages = $stm->fetchAll(\PDO::FETCH_OBJ);
			if ($packages) {
			 	$this->response['body']['status'] = true;
				$this->response['body']['message'] = "Packages";
				$this->response['body']['data'] = $packages;
			} 
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = "No Packages found 2";
			}
		}
	}
}