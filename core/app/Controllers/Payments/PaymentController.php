<?php 

namespace Porlts\App\Controllers\Payments;

use Porlts\App\Logic\Dao;
use Porlts\App\Handlers\EmailHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class PaymentController extends \Porlts\App\Controllers\Controller
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
			case 'POST':

				if (isset($route[3]) && !empty($route[3])) {
					switch ($route[3]) {
						case 'verify':
							$this->verifyPayment();
							break;
						
						default:
							$this->savePaymentInfo($route[3]);
							break;
					}
				}
				else {
					$this->makePayment();
				}
				break;

			case 'GET':

				if (isset($route[3]) && $route[3] == 'keys') {
					$this->getApiKey();
				}
				else {
					$this->routeNotFound();
				}
				break;

			default:
				$this->routeNotFound();
				break;
		}

		$this->sendResponse($this->response);
	}

	public function makePayment()
	{
		$client = new Client();
		$post = [
			"card_number" => "5531886652142950",
			"cvv" => "564",
			"expiry_month" => "09",
			"expiry_year" => "32",
			"currency" => "NGN",
			"amount" => "100",
			"fullname" => "Taofeeq",
			"email" => "taofeeq@yahoo.com",
			"tx_ref" => "MC-3243e",
			"redirect_url" => "localhost:8000",
			"type" => "card",
			"authorization" => [
				'mode' => 'pin',
				'pin' => 1234]
		];

		$data = $this->encode3D(json_encode($post));
		$post_data = array(
  	        'client' => $data
  	    );

		$headers = [
			'Authorization' => 'Bearer FLWSECK_TEST-77ac41d89ae881b44497824a3c4306b9-X', 
			'Content-Type' => 'application/json'
		];

		// 
		$request = new Request(
			'POST',
			"https://api.flutterwave.com/v3/charges?type=card", 
			$headers,
			json_encode($post_data)
		);

		$res = $client->send($request);
		$this->response['body']['status'] = true;
		$this->response['body']['message'] = "Payment Successful";
		$this->response['body']['data'] = $data;
		$this->response['body']['folo'] = $res;
	}

	public function encode3D($data)
	{
		$key = 'FLWSECK_TEST-77ac41d89ae881b44497824a3c4306b9-X';
		$encData = openssl_encrypt($data, 'DES-EDE3', $this->getKey($key), OPENSSL_RAW_DATA);
        return base64_encode($encData);
	}

	function getKey($seckey) {

        $hashedkey = md5($seckey);
        $hashedkeylast12 = substr($hashedkey, -12);

        $seckeyadjusted = str_replace("FLWSECK-", "", $seckey);
        $seckeyadjustedfirst12 = substr($seckeyadjusted, 0, 12);

        return ($seckeyadjustedfirst12 . $hashedkeylast12);
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

    public function savePaymentInfo($packageID)
    {

    	$user = $this->auth($this->db);
    	$id = $this->getID($packageID);

    	$input = json_decode(file_get_contents("php://input"), true);

    	if ($this->validate($input)) {

			$stm = $this->db->query("SELECT * FROM drop_offs WHERE id = $id");
			$package = $stm->fetchObject();
			if ($package) {
				
				if ($package->amount <= $input['amount']) {

					// Payment Info
					$stm = $this->db->prepare("INSERT INTO payments (user_id, package_id, amount, transaction_ref) VALUES (:user, :package, :amount, :transaction)");
					$stm->execute([
						'user' => $user->id,
						'package' => $package->id,
						'amount' => $input['amount'],
						'transaction' => $input['transaction_ref']]);

					// Transaction History
						$stm = $this->db->prepare("INSERT INTO transactions (user_id, type, amount) VALUES (:user, :type, :amount)");
						$stm->execute([
							'user' => $user->id,
							'type' => 'package-payment',
							'amount' => $input['amount'],
						]);

					// Updates
					$dCode = rand();
					$stm = $this->db->prepare("UPDATE drop_offs SET payment_status = :status, delivery_code = :code WHERE id = :id");
					$stm->execute([
						'status' => 'paid',
						'code' => $dCode,
						'id' => $id]);

					// Referrer
					$this->handleReferralBonus($user);

					$this->response['body']['status'] = true;
					$this->response['body']['message'] = "Payment added Successfully";
					$this->response['body']['data'] = [
						'deliveryCode' => $dCode];
				}
				else {
					$this->response['body']['status'] = true;
					$this->response['body']['message'] = "Amount is less than package amount";
					$this->response['body']['data'] = [
						'packageAmount' => $package->amount,
						'amount' => $input['amount']
					];
				}
			}
			else {
				$this->response['body']['status'] = true;
				$this->response['body']['message'] = "Package not found";
			}
		}
    }

    private function getApiKey()
    {
    	$this->response['body']['status'] = false;
		$this->response['body']['message'] = "Payment keys";
		$this->response['body']['data'] = array(
			'secret' => $_ENV['WAVE_SECRET_KEY'],
			'public' => $_ENV['WAVE_PUBLIC_KEY']);
    }

    private function verifyPayment()
    {
    	$input = json_decode(file_get_contents('php://input'), true);
    	if (isset($input['transaction_ref']) && !empty($input['transaction_ref'])) {

    		$headers = [
				'Authorization' => 'Bearer ' . $_ENV['WAVE_SECRET_KEY'], 
				'Content-Type' => 'application/json'
			];

			$ref = $input['transaction_ref'];
			$url = "https://api.flutterwave.com/v3/transactions/$ref/verify";
			$request = new Request(
				'GET',
				$url, 
				$headers
			);

			try {
				$client = new Client();
			    $client->send($request);

			    $this->response['body']['status'] = false;
				$this->response['body']['message'] = "Payment info";
				$this->response['body']['data'] = $res;
			} 
			catch (\Exception $e) {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = $e->getMessage();
			}
    	}
    	else {
    		$this->response['body']['status'] = false;
			$this->response['body']['message'] = "Transaction Ref is required";
    	}
    }

    public function handleReferralBonus($user)
    {
    	try {
    		$stm = $this->db->query("SELECT * FROM referral_bonus");
		    $bonus = $stm->fetchObject();

			if($user->referrer_id && !$user->referrer_paid) {

				$stm = $this->db->query("SELECT * FROM porlt_users WHERE id = $user->referrer_id");
				$referrer = $stm->fetchObject();

				if ($referrer) {
					
					$stm = $this->db->prepare("SELECT * FROM wallets WHERE user_id = :user");
					$stm->execute([
						'user' => $user->id]);
					$wallet = $stm->fetchObject();

					// Add Bonus to wallet
					$stm = $this->db->prepare("UPDATE wallets SET balance = :balance, bonus = :bonus WHERE id = :id");
					$stm->execute([
						'balance' => $wallet->balance + $bonus->amount,
						'bonus' => $wallet->bonus + $bonus->amount,
						'id' => $wallet->id]);

					// Update User
					$stm = $this->db->prepare("UPDATE porlt_users SET referrer_paid = :status WHERE id = :id");
					$stm->execute([
						'status' => true,
						'id' => $user->id]);

					// Transaction
					$stm = $this->db->prepare("INSERT INTO transactions (user_id, type, amount) VALUES (:user, :type, :amount)");
					$stm->execute([
							'type' => 'bonus credit',
							'user' => $user->referrer_id,
							'amount' => $bonus->amount]);

					// Email
					$message = "<p>Dear Porlt user, we are glad to inform you that one of your referrals $user->fullname has earned you NGN $bonus->amount </p>";
					$message .= "<p>Thank you for using Porlt. Refer more friends to earn more referral bonus.</p><br><br><br>";
					$message .= "<p><b>Porlt</b>  Where senders meet travelers! </p>";
						
					$this->sendEmail($referrer->email, 'Bonus Alert', $message);
				}
			}
    	} catch (\Exception $e) {
    		
    	}
    }
}