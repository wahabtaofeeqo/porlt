<?php 


namespace Porlts\App\Controllers;
use Porlts\App\Database;
use Firebase\JWT\JWT;
use Porlts\App\Logic\Dao;

class Controller
{
	use Dao;

	private $db = null;

	function __construct()
	{
		
	}

	public function methodNotAllowed()
	{
		return 405;
	}

	public function statusCreated()
	{
		return 201;
	}

	public function ok()
	{
		return 200;
	}

	public function notAuthorized()
	{
		return 401;
	}

	public function routeNotFound()
	{
		throw new \Exception("Route not found");
	}

	public function sendResponse($response)
	{
		http_response_code($response['code']);
		header('Content-Type: application/json');
		
		echo json_encode($response['body']);
	}

	public function getConnection()
	{
		return $this->db;
	}

	public function authPayload()
	{
		return array(
			"iss" => "http://porlts.com",
			"aud" => "http://porlts.com",
			"iat" => 1356999524,
			"nbf" => 1357000000
		);
	}

	public function getAuthToken($user)
	{
		$payload = array(
			"iss" => "http://porlts.com",
			"aud" => "http://porlts.com",
			"iat" => 1356999524,
			"nbf" => 1357000000,
			"username" => $user->email,
			"id" => $user->id
		);

		return JWT::encode($payload, $_ENV['SECRET_KEY']);
	}

	public function isAuthenticated()
	{
		$headers = apache_request_headers();
		if (isset($headers['Authorization'])) {

			$token = explode(" ", $headers['Authorization']);
			try {
				$auth = JWT::decode($token[1], $_ENV['SECRET_KEY'], ['HS256']);
				return $auth->username;
			} catch (\Exception $e) {
				return false;	
			}
		}

		return false;
	}

	public function auth($db)
	{
		$email = $this->isAuthenticated();
		return $this->checkEmail($db, $email);
	}
}