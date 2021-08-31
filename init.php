<?php 
require 'vendor/autoload.php';

define ('SITE_ROOT', realpath(dirname(__FILE__)));

use Porlts\App\Database;

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

// $dotenv->safeLoad();

$connection = (new Database())->connect();

function exceptionHandler($exception)
{
	header("Content-Type: application/json");
	echo json_encode([
		'status' => false,
		'message' => $exception->getMessage(),
		'trace' => $exception->getTrace()]);
}

function errorHandler($code, $str, $file, $line)
{
	header("Content-Type: application/json");
	echo json_encode([
		'status' => false,
		'message' => $str,
		'Line' => $line]);
}

set_error_handler("errorHandler");
set_exception_handler("exceptionHandler");