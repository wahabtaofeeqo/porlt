<?php 

namespace Porlts\App\Controllers\Wallets;

use Porlts\App\Logic\Dao;
use Porlts\App\Handlers\EmailHandler;

class WalletController extends \Porlts\App\Controllers\Controller
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
				$this->wallet();
				break;
			
			case 'PATCH':
				$this->update();
				break;

			case 'POST':
				if (isset($route[3]) && $route[3] == 'deposit') {
					$this->deposit();
				}
				elseif (isset($route[3]) && $route[3] == 'withdraw') {
					$this->withdraw();
				}
				else {
					$this->routeNotFound();
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

	public function wallet()
	{
		$user = $this->auth($this->db);
		$stm = $this->db->prepare("SELECT * FROM wallets WHERE user_id = :uid");
		$stm->execute(['uid' => $user->id]);
		$wallet = $stm->fetchObject();

		if ($wallet) {
			$this->response['body']['status'] = true;
			$this->response['body']['message'] = "Wallet";
			$this->response['body']['data'] = $wallet;
		}
		else {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "No Wallet Found";
		}
	}

	public function deposit()
	{
		$input = json_decode(file_get_contents('php://input') ,true);
		if (!isset($input['amount']) || empty($input['amount'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "Amount is required";
		}
		else {
			$user = $this->auth($this->db);
			$stm = $this->db->prepare("SELECT * FROM wallets WHERE user_id = :id");
			$stm->execute(['id' => $user->id]);

			$wallet = $stm->fetchObject();
			if ($wallet) {
				$stm = $this->db->prepare("UPDATE wallets SET balance = :balance, deposit = :deposit WHERE id = :id");
				
				$amount = intval($input['amount']);
				$stm->execute([
						'id' => $wallet->id, 
						'balance' => $wallet->balance + $amount,
						'deposit' => $wallet->deposit + $amount
					]
				);

				$this->response['body']['status'] = true;
				$this->response['body']['message'] = $input['amount'] ." Naira has been deposited in your wallet";
			}
			else {

				// Create a wallet
				$stm = $this->db->prepare("INSERT INTO wallets (user_id, balance) VALUES (:id, :balance)");
				$stm->execute(['id' => $user->id, 'balance' => $input['amount']]);

				$this->response['body']['status'] = true;
				$this->response['body']['message'] = $input['amount'] ." Naira has been deposited in your wallet";
			}
		}
	}

	public function withdraw()
	{
		$input = json_decode(file_get_contents('php://input') ,true);
		
		if ($this->validate($input)) {

			$user = $this->auth($this->db);
			$stm = $this->db->prepare("SELECT * FROM wallets WHERE user_id = :id");
			$stm->execute(['id' => $user->id]);

			$wallet = $stm->fetchObject();
			if ($wallet) {
				$amount = intval($input['amount']);

				if ($amount > $wallet->balance) {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = "Insufficient Amount";
				}
				else {

					// Insert withdraw request
					$stm = $this->db->prepare("INSERT INTO withdrawal (user, amount, bank, account_num, account_name, status) VALUES (:user, :amount, :bank, :aNum, :aName, :status)");
					$stm->execute([
						'user' => $user->email,
						'amount' => $amount,
						'bank' => $input['bank_name'],
						'aNum' => $input['account_number'],
						'aName' => $input['account_name'],
						'status' => 'new'
					]);

					$balance = $wallet->balance - $amount;
					$stm = $this->db->prepare("UPDATE wallets SET balance = :balance WHERE id = :id");
					$stm->execute(['balance' => $balance, 'id' => $wallet->id]);

					$this->response['body']['status'] = true;
					$this->response['body']['message'] = $amount . " has been withdrawn from your wallet";
				}
			}
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = "Insufficient Amount";
			}
		}
	}

	private function validate($input) {
		if (!isset($input['amount']) || empty($input['amount'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "Amount is required";
			return false;
		}

		if (!isset($input['bank_name']) || empty($input['bank_name'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "Bank Name is required";
			return false;
		}

		if (!isset($input['account_name']) || empty($input['account_name'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "Account Name is required";
			return false;
		}

		if (!isset($input['account_number']) || empty($input['account_number'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "Account Number is required";
			return false;
		}

		return true;
	}
}