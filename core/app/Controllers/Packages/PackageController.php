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

	public function processRequest($route) {

		switch ($this->method) {

			case 'GET':
				if (isset($route[5]) && !empty($route[5])) {
					$this->getPackagesWithConstraints($route[3], $route[4], $route[5]);
				}
				elseif (isset($route[4])) {

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

						case 'types':
							$this->getParcelTypes();
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
				if (isset($route[3]) && $route[3] == 'upload') {
					$this->uploadImage();
				}
				elseif (isset($route[3]) && $route[4]) {
					if ($route[4] == 'deliver') {
						$this->deliverPackage($route[3]);
					}
					else {
						$this->routeNotFound();
					}
				}
				else {
					$this->addPackage();
				}
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
		$user = $this->auth($this->db);

		if ($this->validate($input, $user)) {
			
			$response = null;

			// Determine cost
			if ($input['delivery'] == 'inter') {
				$stm = $this->db->prepare("SELECT * FROM inter_cost WHERE state1 = :origin AND state2 = :dest");
				$stm->execute([
					'dest' => $input['des_city'], 
					'origin' => $input['origin_city']
				]);
				$response = $stm->fetchObject();
			}
			else {
				$stm = $this->db->prepare("SELECT * FROM intra_cost WHERE origin_post_code = :origin AND destination_post_code = :destination");
				$stm->execute(['destination' => $input['delivery_postcode'], 'origin' => $input['pickup_postcode']]);
				$response = $stm->fetchObject();
			}

			if ($response) {

				$temp = explode("-", $response->kg);
				if ($response) {
					
					try {

						$query = "INSERT INTO drop_offs (parcel_id, parcel_code, user, delivery, parcel_type, origin_city, des_city, parcel_weight, receiver, receiver_phone, origin_terminal_address, des_terminal_address, amount, delivery_postcode, pickup_postcode, earned, insurance, discount, description, worth, origin_house_address, delivery_house_address) VALUES (:pid, :pcode, :user, :delivery, :ptype, :ocity, :dcity, :size, :receiver, :rphone, :pstop, :dstop, :amount, :dpostcode, :ppostcode, :earned, :insurance, :discount, :description, :worth, :oha, :dha)";

						$stm = $this->db->prepare($query);

						// Carrier Fee
						$earned = $response->earned + ($response->insurance - $response->discount);
						$amount = ($response->insurance + $response->cost) - $response->discount;

						$stm->execute([
								'pid' => 0,
								'pcode' => uniqid(),
								'user' => $user->email,
								'delivery' => $input['delivery'],
								'ptype' => $input['parcel_type'],
								'ocity' => $input['origin_city'],
								'dcity' => $input['des_city'],
								'size' => $input['parcel_weight'],
								'receiver' => $input['receiver'],
								'rphone' => $input['receiver_phone'],
								'pstop' => $input['origin_terminal_address'],
								'dstop' => $input['des_terminal_address'],
								'amount' => $amount,
								'dpostcode' => isset($input['delivery_postcode']) ? $input['delivery_postcode'] : '',
								'ppostcode' => isset($input['pickup_postcode']) ? $input['pickup_postcode'] : '',
								'earned' => $earned,
								'insurance' => $response->insurance,
								'discount' => $response->discount,
								'description' => isset($input['description']) ? $input['description'] : '',
								'worth' => isset($input['worth']) ? $input['worth'] : '',
								'oha' => isset($input['origin_house_address']) ? $input['origin_house_address'] : '',
								'dha' => isset($input['delivery_house_address']) ? $input['delivery_house_address'] : ''
							]
						);

						$id = $this->db->lastInsertId();
						if ($id) {
							$parcelID = "Qw" . $id . "z";
							$stm = $this->db->prepare("UPDATE drop_offs SET parcel_id = :pid WHERE id = :id");
							$stm->execute(['pid' => $parcelID, 'id' => $id]);

							$this->response['body']['status'] = true;
							$this->response['body']['message'] = 'Operation succeeded';
							$this->response['body']['data'] = [
							    'cost' => $response->cost,
							    'package_id' => $parcelID,
							    'insurance' => $response->insurance,
							    'earned' => $response->earned,
							    'discount' => $response->discount];
						}
						else {
							$this->response['body']['status'] = false;
							$this->response['body']['message'] = 'Package could not be created!';
						}
					} 
					catch (\Exception $e) {
						throw new \Exception("Error creating package", $e);
					}
				}
				else {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = 'Cost not available for this Route';
				}
			}
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'Cost could not be determined for this Route';
			}
		}
	}

	private function validate($input, $user)
	{

		if (empty(strtolower($user->status) != 'verified')) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'You account has not been verified';
			return false;
		}

		if (!isset($input['delivery']) || empty($input['delivery'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Delivery Type is required';
			return false;
		}

		if ($input['delivery'] == 'intra') {
			if (!isset($input['origin_house_address']) || empty($input['origin_house_address'])) {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'Origin House is required';
				return false;
			}

			if (!isset($input['delivery_house_address']) || empty($input['delivery_house_address'])) {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'Delivery House Address is required';
				return false;
			}
		}

		if (!isset($input['parcel_type']) || empty($input['parcel_type'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Item Type is required';
			return false;
		}

		if (!isset($input['origin_city']) || empty($input['origin_city'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Pickup State is required';
			return false;
		}

		if (!isset($input['des_city']) || empty($input['des_city'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Delivery City is required';
			return false;
		}

		if (!isset($input['parcel_weight']) || empty($input['parcel_weight'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Package weight is required';
			return false;
		}

		if (!isset($input['receiver']) || empty($input['receiver'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Receiver name is required';
			return false;
		}

		if (!isset($input['receiver_phone']) || empty($input['receiver_phone'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Receiver phone is required';
			return false;
		}

		if (!isset($input['origin_terminal_address']) || empty($input['origin_terminal_address'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Pickup Bus Stop is required';
			return false;
		}

		if (!isset($input['des_terminal_address']) || empty($input['des_terminal_address'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Delivery Bus Stop is required';
			return false;
		}

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

	private function deliverPackageValidate($input) {

		if (!isset($input['status']) || empty($input['status'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Status is required';
			return false;
		}

		if (!isset($input['delivery_code']) || empty($input['delivery_code'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Delivery Code is required';
			return false;
		}

		return true;
	}

	private function deliverPackage($str)
	{

		$id = $this->getID($str);
		$input = json_decode(file_get_contents('php://input'), true);
		if ($this->deliverPackageValidate($input)) {
			
			if ($input['status'] == 'delivered') {
				try {

					$code = $input['delivery_code'];

					$stm = $this->db->prepare("SELECT * FROM drop_offs WHERE id = :id AND delivery_code = :code");
					$stm->execute([
						'id' => $id,
						'code' => $code
					]);

					$package = $stm->fetchObject();
					if ($package) {

						// Confirm Delivery Code
						$stm = $this->db->prepare("UPDATE drop_offs SET status = :status WHERE id = :id");
						$stm->execute(['status' => $input['status'], 'id' => $package->id]);

						// Add Carrier Earning
						$user = $this->auth($this->db);
						$stm = $this->db->query("SELECT * FROM wallets WHERE user_id = $user->id");
						$wallet = $stm->fetchObject();
						if($wallet) {

							$stm = $this->db->prepare("UPDATE wallets SET balance = :balance, earnings = :earned WHERE id = :id");
							$stm->execute([
								'balance' => $wallet->balance + $package->earned,
								'earned' => $wallet->earnings + $package->earned,
								'id' => $wallet->id]);
						}
						else {

							// If User has no entry
							// in wallets Table
							$stm = $this->db->prepare("INSERT INTO wallets (user_id, balance, earnings, bonus, deposit) VALUES (:user, :balance, :earned, :bonus, :deposit)");
							$stm->execute([
								'user' => $user->id,
								'balance' => $package->earned,
								'earned' => $package->earned,
								'bonus' => 0,
								'deposit' => 0]);
						}

						// Transaction History
						$stm = $this->db->prepare("INSERT INTO transactions (user_id, type, amount) VALUES (:user, :type, :amount)");
						$stm->execute([
							'user' => $user->id,
							'type' => 'credit',
							'amount' => $package->earned]);

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
				$this->response['body']['message'] = 'Status ' . $input['status'] . ' is not allowed';
			}
		}
	}


	public function getPackages($str = null)
	{
		try {
			$user = $this->auth($this->db);
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

				$stm = $this->db->query("SELECT * FROM drop_offs WHERE status = 'pending' AND payment_status = 'paid' AND user != '$user->email'");
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

	private function checkProfileSetup($user) {

		if (empty(strtolower($user->status) != 'verified')) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'You account has been verified';
			return false;
		}
		return true;
	}

	public function acceptPackage($str)
	{

		$id = $this->getID($str);
		$stm = $this->db->query("SELECT * FROM drop_offs WHERE id = $id");
		$package = $stm->fetchObject();

		$user = $this->auth($this->db);
		
		if($this->checkProfileSetup($user)) {
			if ($package) {
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
		$stm = $this->db->query("SELECT * FROM states");
		$this->response['body']['status'] = true;
		$this->response['body']['message'] = 'States';
		$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ);
	}

	private function getPackagesWithConstraints($type, $origin, $destination)
	{

		$user = $this->auth($this->db);
		$stm = $this->db->prepare("SELECT * FROM drop_offs WHERE delivery = :type AND origin_city = :origin AND des_city = :dest AND payment_status = :pStatus AND user != :email AND status = :status");

		$stm->execute([
				'type' => $type,
				'origin' => $origin,
				'dest' => $destination,
				'pStatus' => 'paid',
				'email' => $user->email,
				'status' => 'pending'
			]
		);

		$this->response['body']['status'] = true;
		$this->response['body']['message'] = 'Packages';
		$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ);
	}

	public function uploadImage()
	{
		$user = $this->auth($this->db);
		$uploadPath = SITE_ROOT . "/uploads/packages/";

		if ($user) {
			$filename = time() . "_" . basename($_FILES["file"]["name"]);
			if (move_uploaded_file($_FILES['file']['tmp_name'], ($uploadPath . $filename))) {

				$stm = $this->db->prepare("INSERT INTO packages_image (user_id, package_id, filename) VALUES(:user, :package, :filename)");

				$id = intval($this->getID($_POST['package_id']));
				$stm->execute([
					'filename' => $filename, 
					'user' => $user->id,
					'package' => $id]);

				$this->response['body']['status'] = true;
				$this->response['body']['message'] = 'File uploaded successfully';
			}
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'File not uploaded ';
			}
		}
		else {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "User not recognised";
		}
	}

	public function getParcelTypes()
	{
		$stm = $this->db->query("SELECT * FROM parcel");
		$this->response['body']['status'] = true;
		$this->response['body']['message'] = 'Parcel Types';
		$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ);
	}
}