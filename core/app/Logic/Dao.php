<?php

namespace Porlts\App\Logic;

trait Dao {

	public function query($db, $table, $projection, $params)
	{
		$query = "SELECT $projection FROM $table";
		if ($params) {
			$query = $query . " WHERE ";
			foreach ($params as $key => $value) {
				$query = $query . " $key = $value";
			}
		}

		$db->query($query);
	}


	public function checkEmail($db, $email)
	{
		$result = $db->query("SELECT * FROM porlt_users WHERE email = '$email'");
		return $result->fetchObject();
	}

	public function checkPhone($db, $phone)
	{
		$result = $db->query("SELECT * FROM porlt_users WHERE phone = '$phone'");
		return $result->fetch();
	}

	public function updateFields($db, $table, $data)
	{
		
	}
}