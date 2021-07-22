<?php 

namespace Porlts\App\Controllers\Packages;

use Porlts\App\Logic\Dao;
use Porlts\App\Handlers\EmailHandler;

class PackageController extends \Porlts\App\Controllers\Controller
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

				if (isset($route[4])) {

					switch ($route[4]) {
						case 'accept':
							$this->acceptPackage($route[3]);
							break;

						case 'pickup-areas':
							$this->areas($route[3]);
							break;
						
						default:
							$this->routeNotFound();
							break;
					}
				}
				elseif (isset($route[3])) {

					switch ($route[3]) {
						case 'weights':
							$this->getWeights();
							break;

						case 'cities':
							$this->getCities();
							break;

						case 'states':
							$this->getServiceStates();
							break;
						
						default:
							$this->getPackages($route[3]);
							break;
					}
				}
				else {
					$this->getPackages();
				}
				break;
			
			case 'POST':
				$this->addPackage();
				break;

			case 'PATCH':

				if (isset($route[4])) {
					if ($route[4] == 'time') {
						$this->updateTime($route[3]);
					}
					else {
						$this->routeNotFound();
					}
				}
				elseif (isset($route[3])) {
					$this->changeStatus($route[3]);
				}
				else {
					$this->routeNotFound();
				}
				break;

			default:
				$this->response['body']['status'] = false;
				$this->response['code'] = $this->methodNotAllowed();
				$this->response['body']['message'] = "Method method not allowed";
				break;
		}

		$this->sendResponse($this->response);
	}

	private function addPackage()
	{
		$input = json_decode(file_get_contents('php://input'), true);
		if ($this->validate($input)) {
			$user = $this->auth($this->db);

			// Determine cost
			$query = "SELECT * FROM inter_cost WHERE state1 = :destination AND state2 = :origin";
			$stm = $this->db->prepare($query);
			$stm->execute(['destination' => $input['pickup_state'], 'origin' => $input['delivery_state']]);
			$response = $stm->fetchObject();

			if ($response) {
				$temp = explode("-", $response->kg);

				if (($input['weight'] >= $temp[0]) && ($input['weight'] <= $temp[1])) {

					$query = "INSERT INTO drop_offs (parcel_id, parcel_code, user, delivery, parcel_type, origin_city, des_city, parcel_weight, receiver, receiver_phone, origin_terminal_address, des_terminal_address, amount, delivery_postcode, pickup_postcode) VALUES (:pid, :pcode, :user, :delivery, :ptype, :ocity, :dcity, :size, :receiver, :rphone, :pstop, :dstop, :amount, :dpostcode, :ppostcode)";
					$stm = $this->db->prepare($query);

					$stm->execute([
						'pid' => 0,
						'pcode' => uniqid(),
						'user' => $user->email,
						'delivery' => $input['delivery'],
						'ptype' => $input['item_type'],
						'ocity' => $input['pickup_state'],
						'dcity' => $input['delivery_state'],
						'size' => $input['weight'],
						'receiver' => $input['receiver_name'],
						'rphone' => $input['receiver_phone'],
						'pstop' => $input['pickup_stop'],
						'dstop' => $input['delivery_stop'],
						'amount' => $response->cost,
						'dpostcode' => $input['delivery_postcode'],
						'ppostcode' => $input['pickup_postcode']]);

					$id = $this->db->lastInsertId();
					$parcelID = "Qw" . $id . "z";
					$stm = $this->db->prepare("UPDATE drop_offs SET parcel_id = :pid WHERE id = :id");
					$stm->execute(['pid' => $parcelID, 'id' => $id]);

					$this->response['body']['status'] = true;
					$this->response['body']['message'] = 'Operation succeeded';
					$this->response['body']['cost'] = $response->cost;
					$this->response['body']['package_id'] = $parcelID;
				}
				else {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = 'Cost could not be determined';
				}
			}
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'Cost could not be determined';
			}
		}
	}

	private function validate($input)
	{
		if (!isset($input['delivery']) || empty($input['delivery'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Delivery Type is required';
			return false;
		}

		if (!isset($input['item_type']) || empty($input['item_type'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Item Type is required';
			return false;
		}

		if (!isset($input['pickup_state']) || empty($input['pickup_state'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Pickup State is required';
			return false;
		}

		if (!isset($input['delivery_state']) || empty($input['delivery_state'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Item Type is required';
			return false;
		}

		if (!isset($input['weight']) || empty($input['weight'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Package weight is required';
			return false;
		}

		if (!isset($input['receiver_name']) || empty($input['receiver_name'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Receiver name is required';
			return false;
		}

		if (!isset($input['receiver_phone']) || empty($input['receiver_phone'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Receiver phone is required';
			return false;
		}

		if (!isset($input['pickup_stop']) || empty($input['pickup_stop'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Pickup Bus Stop is required';
			return false;
		}

		if (!isset($input['delivery_stop']) || empty($input['delivery_stop'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Delivery Bus Stop is required';
			return false;
		}

		// if (!isset($input['postcode']) || empty($input['postcode'])) {
		// 	$this->response['body']['status'] = false;
		// 	$this->response['body']['message'] = 'Postcode is required';
		// 	return false;
		// }

		return true;
	}

	private function changeStatus($str)
	{

		$id = $this->getID($str);
		$input = json_decode(file_get_contents('php://input'), true);
		if (isset($input['status']) && !empty($input['status'])) {
			
			try {
				$stm = $this->db->prepare("SELECT * FROM drop_offs WHERE id = :id");
				$stm->execute(['id' => $id]);
				$package = $stm->fetchObject();

				if ($package) {
					$stm = $this->db->prepare("UPDATE drop_offs SET status = :status WHERE id = :id");
					$stm->execute(['status' => $input['status'], 'id' => $package->id]);

					$this->response['body']['status'] = true;
					$this->response['body']['message'] = 'Operation succeeded';
				}
				else {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = 'Package ID not recognised';
				}
			} catch (\PDOException $e) {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = $e->getMessage();
			}
		}
		else {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Status is required';
		}
	}

	public function getPackages($str = null)
	{
		try {
			if ($str) {

				$id = $this->getID($str);
				$stm = $this->db->query("SELECT * FROM drop_offs WHERE id = $id");
				$package = $stm->fetchObject();
				if ($package) {
					$this->response['body']['status'] = true;
					$this->response['body']['message'] = "Package";
					$this->response['body']['data'] = $package;
				}
				else {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = "Package not found";
				}
			}
			else {
				$stm = $this->db->query("SELECT * FROM drop_offs WHERE status = 'pending'");
				$this->response['body']['status'] = true;
				$this->response['body']['message'] = "Packages";
				$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ);
			}
		} 
		catch (\PDOException $e) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = $e->getMessage();
		}
	}

	public function acceptPackage($str)
	{

		$id = $this->getID($str);
		$stm = $this->db->query("SELECT * FROM drop_offs WHERE id = $id");
		$package = $stm->fetchObject();

		if ($package) {
			$user = $this->auth($this->db);
			$query = "UPDATE drop_offs SET carrier = :carrier, carrier_id = :email, carrier_phone = :phone, status = :status WHERE id = :id";

			$stm = $this->db->prepare($query);
			$stm->execute(['carrier' => $user->fulname, 'email' => $user->email, 'phone' => $user->phone, 'status' => 'picked up', 'id' => $id]);

			$this->response['body']['status'] = true;
			$this->response['body']['message'] = "Package accepted";
		}
		else {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "Package is not found";
		}
	}

	public function getWeights()
	{
		try {
			$stm = $this->db->query("SELECT * FROM kg_range");
			$this->response['body']['status'] = true;
			$this->response['body']['message'] = "Weights";
			$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ);
		} catch (\PDOException $e) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = $e->getMessage();
		}
	}

	public function areas($state)
	{
		try {
			$stm = $this->db->query("SELECT * FROM pickup_cities WHERE cities = '$state'");
			$response = $stm->fetchObject();
			if ($response) {

				$stm = $this->db->query("SELECT * FROM pickup_area WHERE state_id = " . $response->id);
				$this->response['body']['status'] = true;
				$this->response['body']['message'] = "Areas";
				$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ);
			}
			else {
				$this->response['body']['status'] = true;
				$this->response['body']['message'] = "Pickup areas not found in " . $state;
			}
		} catch (\PDOException $e) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = $e->getMessage();
		}
	}

	private function getID($str)
	{
		$id = substr($str, 2); // remove the "Qw"
		return trim(str_replace("z", "", $id));
	}

	public function updateTime($str)
	{
		$id = $this->getID($str);
		$input = json_decode(file_get_contents('php://input'), true);

		if ($this->validateTime($input)) {
			
			try {
				$stm = $this->db->prepare("SELECT * FROM drop_offs WHERE id = :id");
				$stm->execute(['id' => $id]);
				$package = $stm->fetchObject();

				if ($package) {
					$stm = $this->db->prepare("UPDATE drop_offs SET time_t = :t, date_t = :d WHERE id = :id");
					$stm->execute(['t' => $input['time'], 'd' => $input['date'], 'id' => $package->id]);

					$this->response['body']['status'] = true;
					$this->response['body']['message'] = 'Package updated successfully';
				}
				else {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = 'Package ID not recognised';
				}
			} catch (\PDOException $e) {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = $e->getMessage();
			}
		}
	}

	public function validateTime($input)
	{
		if (!isset($input['time']) || empty($input['time'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Time is required';
			return false;
		}

		if (!isset($input['date']) || empty($input['date'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Date is required';
			return false;
		}

		return true;
	}

	private function getCities()
	{
		$stm = $this->db->query("SELECT * FROM des_cities");
		$this->response['body']['status'] = true;
		$this->response['body']['message'] = 'Cities';
		$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ);
	}

	private function getServiceStates()
	{
		$this->response['body']['status'] = true;
		$this->response['body']['message'] = 'States';
		$this->response['body']['data'] = [];
	}
}