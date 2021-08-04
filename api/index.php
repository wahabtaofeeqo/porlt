<?php

require '../init.php';

// Auth Controllers
use Porlts\App\Controllers\Controller;
use Porlts\App\Controllers\Auth\LoginController;
use Porlts\App\Controllers\Auth\SetupController;
use Porlts\App\Controllers\Auth\RegisterController;
use Porlts\App\Controllers\Auth\ForgotPasswordController;

// Package Controller
use Porlts\App\Controllers\Packages\PackageController;

// User
use Porlts\App\Controllers\Users\UserController;

// Wallet
use Porlts\App\Controllers\Wallets\WalletController;

// Carrier
use Porlts\App\Controllers\Carriers\CarrierController;

// Payment
use Porlts\App\Controllers\Payments\PaymentController;
use Porlts\App\Controllers\Payments\TransactionController;

// Routes
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = explode('/', $uri);
$requestMethod = $_SERVER["REQUEST_METHOD"];


// API
if (!array_key_exists(2, $route) || empty($route[2])) {
	echo json_encode("Welcome to Porlt API. Login and Enjoy!");
	exit();
}

// Auth
if (($route[2] == 'auth')) {

	switch ($route[3]) {

		case 'register':
			$controller = new RegisterController($requestMethod, $connection);
			$controller->processRequest();
			break;
		
		case 'login':
			$controller = new LoginController($requestMethod, $connection);
			$controller->processRequest();
			break;

		case 'setup':
			if ($route[4] == 'selfie') {
				$controller = new SetupController($requestMethod, $connection, 'selfie');
				$controller->processRequest();
			}

			if ($route[4] == 'gov-id') {
				$controller = new SetupController($requestMethod, $connection, 'gov-id');
				$controller->processRequest();
			}
			break;

		case 'forgot-password':
			$controller = new ForgotPasswordController($requestMethod, $connection);
			if ($route[4] == 'email') {
				$controller->processRequest('email');
			}

			if ($route[4] == 'reset') {
				$controller->processRequest('reset');
			}
			break;

		default:
			header("HTTP/1.1 404 Not Found");
			break;
	}

	exit();
}

// Protected Routes
$controller = new Controller();
if ($controller->isAuthenticated()) {
	
	switch ($route[2]) {

		case 'packages':
			$controller = new PackageController($requestMethod, $connection);
			$controller->processRequest($route);
			break;

		case 'user':
			$controller = new UserController($requestMethod, $connection);
			$controller->processRequest($route);
			break;

		case 'wallets':
			$controller = new WalletController($requestMethod, $connection);
			$controller->processRequest($route);
			break;

		case 'carriers':
			$controller = new CarrierController($requestMethod, $connection);
			$controller->processRequest($route);
			break;

		case 'payments':
			$controller = new PaymentController($requestMethod, $connection);
			$controller->processRequest($route);
			break;

		case 'transactions':
			$controller = new TransactionController($requestMethod, $connection);
			$controller->processRequest($route);
			break;
		
		default:
			$controller->routeNotFound();
			break;
	}
}
else {
	http_response_code($controller->notAuthorized());
	header('Content-Type: application/json');
	echo json_encode(['status' => false, 'message' => 'Unauthorized']);
}