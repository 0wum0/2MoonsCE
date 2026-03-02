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


class ShowLoginPage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct() 
	{
		parent::__construct();
	}
	
	function show() 
	{
		if (empty($_POST)) {
			HTTP::redirectTo('index.php');	
		}

		$db = Database::get();

		$username = HTTP::_GP('username', '', UTF8_SUPPORT);
		$password = HTTP::_GP('password', '', true);

		$sql = "SELECT id, password FROM %%USERS%% WHERE universe = :universe AND username = :username;";
		$loginData = $db->selectSingle($sql, array(
			':universe'	=> Universe::current(),
			':username'	=> $username
		));

		if (is_array($loginData))
		{
			$hashedPassword = PlayerUtil::cryptPassword($password);
			if($loginData['password'] != $hashedPassword)
			{
				// Fallback pre 1.7
				if($loginData['password'] == md5($password)) {
					$sql = "UPDATE %%USERS%% SET password = :hashedPassword WHERE id = :loginID;";
					$db->update($sql, array(
						':hashedPassword'	=> $hashedPassword,
						':loginID'			=> $loginData['id']
					));
				} else {
					HTTP::redirectTo('index.php?code=1');	
				}
			}

			$session	= Session::create();
			$session->userId		= (int) $loginData['id'];
			$session->adminAccess	= 0;
			$session->save();

			HTTP::redirectTo('game.php');	
		}
		else
		{
			HTTP::redirectTo('index.php?code=1');
		}
	}
}
