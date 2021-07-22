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

				if (!array_key_exists(3, $route) || empty($route[3])) {
					$this->makePayment();
				}
				else {
					throw new \Exception("Route not found");
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
}