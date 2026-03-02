<?php

declare(strict_types=1);

/**
 *	SmartMoons / 2Moons Community Edition (2MoonsCE)
 * 
 *	Based on the original 2Moons project:
 *	
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * Modernization, PHP 8.3/8.4 compatibility, Twig Migration (Smarty removed)
 * Refactoring and feature extensions:
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 * @link https://github.com/0wum0/2MoonsCE
 * @eMail info.browsergame@gmail.com
 * 
 * Licensed under the MIT License.
 * See LICENSE for details.
 * @visit http://makeit.uno/
 */

class Mail
{	
	static public function send($mailTarget, $mailTargetName, $mailSubject, $mailContent)
	{		
		$mail	= self::getMailObject();
			 
        $mail->CharSet	= 'UTF-8';
        $mail->Subject	= $mailSubject;
        $mail->Body		= $mailContent;
        $mail->AddAddress($mailTarget, $mailTargetName);
        $mail->Send(); 
	}

	static public function multiSend($mailTargets, $mailSubject, $mailContent = NULL)
	{
		$mail	= self::getMailObject();

		$mail->Subject	= $mailSubject;

		foreach($mailTargets as $address => $data)
		{
			$content = isset($data['body']) ? $data['body'] : $mailContent;
			
			$mail->AddAddress($address, $data['username']);
			$mail->MsgHTML($content);
			$mail->Send(); 
			$mail->ClearAddresses();
		}
	}

	static private function getMailObject()
	{
        require_once 'includes/libs/phpmailer/class.phpmailer.php';

        $mail               = new PHPMailer(true);
		$mail->PluginDir	= 'includes/libs/phpmailer/';

		$config				= Config::get();

        if($config->mail_use == 2) {
			$mail->IsSMTP();  
			$mail->SMTPSecure       = $config->smtp_ssl;                                            
			$mail->Host             = $config->smtp_host;
			$mail->Port             = $config->smtp_port;
			
			if($config->smtp_user != '')
			{
				$mail->SMTPAuth         = true; 
				$mail->Username         = $config->smtp_user;
				$mail->Password         = $config->smtp_pass;
			}
        } elseif($config->mail_use == 0) {
			$mail->IsMail();
        } else {
			throw new Exception("sendmail is deprecated, use SMTP instead!");
		}

		$mailFromAddress	= $config->smtp_sendmail;
		$mailFromName		= $config->game_name;

		$mail->CharSet	= 'UTF-8';
		$mail->SetFrom($mailFromAddress, $mailFromName);
		
		return $mail;
	}
}