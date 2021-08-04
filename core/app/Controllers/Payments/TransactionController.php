<?php 

namespace Porlts\App\Controllers\Payments;

use Porlts\App\Logic\Dao;
use Porlts\App\Handlers\EmailHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class TransactionController extends \Porlts\App\Controllers\Controller
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
				if (array_key_exists(3, $route) && !empty($route[3])) {
					$this->transactions($route[3]);
				}
				else {
					$this->transactions();
				}
				break;

			default:
				$this->routeNotFound();
				break;
		}

		$this->sendResponse($this->response);
	}

	private function transactions($userID = null)
	{
		if ($userID) {
			$sql = "SELECT * FROM transactions WHERE user_id = $userID";
		}
		else {
			$user = $this->auth($this->db);
			$sql = "SELECT * FROM transactions WHERE user_id = $user->id";
		}

		$stm = $this->db->query($sql);

		$this->response['body']['status'] = true;
		$this->response['body']['message'] = 'Transactions';
		$this->response['body']['data'] = $stm->fetchAll(\PDO::FETCH_OBJ);
	}


    private function validate($input)
    {
    	if (!isset($input['amount']) || empty($input['amount'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Amount is required';
			return false;
		}

		if (!isset($input['transaction_ref']) || empty($input['transaction_ref'])) {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = 'Transaction Ref is required';
			return false;
		}

		return true;
    }
}