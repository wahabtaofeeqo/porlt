<?php 

namespace Porlts\App\Handlers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

trait EmailHandler {

	public function welcomeEmail($email, $code)
	{
		$mail = new PHPMailer(true);
		try {
			
		   //Server settings
		    $mail->SMTPDebug = 0;                     
		    $mail->isSMTP();                                            
		    $mail->Host       = $_ENV['EMAIL_HOST'];                     
		    $mail->Username   = $_ENV['EMAIL_USERNAME'];                     
		    $mail->Password   = $_ENV['EMAIL_PASSWORD'];                              
		    $mail->SMTPAuth   = true;                                  
		    $mail->SMTPSecure = 'tls';            
		    $mail->Port       = 587;

		    //Recipients
		    $mail->setFrom($_ENV['EMAIL_FROM'], 'Porlt');
		    $mail->addAddress($email);     //Add a recipient

		    //Content
		    $mail->isHTML(true);                                  //Set email format to HTML
		    $mail->Subject = 'Welcome to Porlts';
		    $mail->Body    = 'Your account has been created. Your verification code is:  <b> ' . $code . ' </b>';

		    $mail->send();
		    return true;
		} catch (Exception $e) {
		    return false;
		}
	}
	
	public function sendTestEmail()
	{
		$mail = new PHPMailer(true);
		try {
			
		   //Server settings
		    $mail->SMTPDebug = 0;                     
		    $mail->isSMTP();                                            
		    $mail->Host       = $_ENV['EMAIL_HOST'];      
		    $mail->Username   = $_ENV['EMAIL_USERNAME'];
		    $mail->Password   = $_ENV['EMAIL_PASSWORD'];                              
		    $mail->SMTPAuth   = true;                                  
		    $mail->SMTPSecure = 'tls';            
		    $mail->Port = 587;

		    //Recipients
		    $mail->setFrom($_ENV['EMAIL_FROM'], 'Porlt'); //
		    $mail->addAddress('taofeekolamilekan218@gmail.com');     //Add a recipient

		    //Content
		    $mail->isHTML(true);                                  //Set email format to HTML
		    $mail->Subject = 'Welcome to Porlt';
		    $mail->Body    = '<h4> Testing 124 </h4>';

		    $mail->send();
		    return true;
		} catch (Exception $e) {
		    return false;
		}
	}
	
	public function sendEmail($email, $subject, $message)
	{
		
		$mail = new PHPMailer(true);
		try {

		    //Server settings
		    $mail->SMTPDebug = 0;                     
		    $mail->isSMTP();                                            
		    $mail->Host       = $_ENV['EMAIL_HOST'];                     
		    $mail->Username   = $_ENV['EMAIL_USERNAME'];                     
		    $mail->Password   = $_ENV['EMAIL_PASSWORD'];                              
		    $mail->SMTPAuth   = true;                                  
		    $mail->SMTPSecure = 'tls';            
		    $mail->Port       = 587;                                    

		    //Recipients
		    $mail->setFrom($_ENV['EMAIL_FROM'], 'Porlt');
		    $mail->addAddress($email);     //Add a recipient
	
		    //Content
		    $mail->isHTML(true);                                 
		    $mail->Subject = $subject;
		    $mail->Body    = $message;

		    $mail->send();
		    return true;
		} catch (Exception $e) {
		    return true;
		}
	}

	public function sendResetCode($email, $code)
	{
	    $message = 'Your reset code is: <b>' . $code . '</b>';
		return $this->sendEmail($email, 'Password Reset', $message);
	}
}