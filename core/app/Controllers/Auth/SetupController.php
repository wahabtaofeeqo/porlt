<?php 

namespace Porlts\App\Controllers\Auth;

use Porlts\App\Logic\Dao;

class SetupController extends \Porlts\App\Controllers\Controller
{

	private $db;
	private $type;
	private $method;
	private $response;

	function __construct($method, $connection, $type)
	{
		$this->response = array(
			'code' => $this->ok(),
			'body' => []);

		$this->method = $method;
		$this->db = $connection;
		$this->type = $type;
	}

	public function processRequest()
	{
		
		switch ($this->method) {
			case 'POST':
				$this->upload();
				break;
			
			default:
				$this->response['code'] = $this->methodNotAllowed();
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'Get Request Method not allowed on this Route';
				break;
		}

		$this->sendResponse($this->response);
	}

	public function upload()
	{

		$selfiePath = "\\admin\\users\\";
		$govidPath  = "\\admin\\govt-id\\";

		$uploadPath = realpath(dirname(getcwd())) . (($this->type == 'selfie') ? $selfiePath : $govidPath);
		$mimes = array("image/jpeg","image/jpg","image/png");

		$email = $_POST['email'];
		$user = $this->checkEmail($this->db, $_POST['email']);
		if ($user) {
			$filename = time() . "_" . basename($_FILES["file"]["name"]);
			if (move_uploaded_file($_FILES['file']['tmp_name'], ($uploadPath . $filename))) {

				// Update Table
				if ($this->type == 'selfie') {
					$query = "UPDATE porlt_users SET pic = :filename WHERE id = :id";
				}
				else {
					$query = "UPDATE porlt_users SET govt_id = :filename WHERE id = :id";
				}

				$stm = $this->db->prepare($query);
				$stm->execute(['filename' => $filename, 'id' => $user->id]);

				$this->response['body']['status'] = true;
				$this->response['body']['message'] = 'File uploaded successfully';
			}
			else {
				$this->response['body']['status'] = false;
				$this->response['body']['message'] = 'File not uploaded';
			}
		}
		else {
			$this->response['body']['status'] = false;
			$this->response['body']['message'] = "User not recognised";
		}
	}
}