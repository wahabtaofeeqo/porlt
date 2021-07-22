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
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = "Route not recognised";
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
				$stm = $this->db->prepare("UPDATE wallets SET balance = :balance WHERE id = :id");
				$balance = intval($input['amount']) + $wallet->balance;
				$stm->execute(['id' => $wallet->id, 'balance' => $balance]);

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
				$amount = intval($input['amount']);

				if ($amount > $wallet->balance) {
					$this->response['body']['status'] = false;
					$this->response['body']['message'] = "Insufficient Amount";
				}
				else {
					$balance = $wallet->balance - $amount;
					$stm = $this->db->prepare("UPDATE wallets SET balance = :balance WHERE id = :id");
					$stm->execute(['balance' => $balance, 'id' => $wallet->id]);

					$this->response['body']['status'] = false;
					$this->response['body']['message'] = $amount . " has been withdrawn from your wallet";
				}
			}
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = "Insufficient Amount";
			}
		}
	}
}