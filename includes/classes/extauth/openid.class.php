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

require_once 'includes/libs/OpenID/openid.php';

class OpenIDAuth implements externalAuth
{
	private $oidObj	= NULL;

	public function __construct()
	{
		$this->oidObj	= new LightOpenID(PROTOCOL.HTTP_HOST);
		if(!$this->oidObj->mode)
		{
			if(isset($_REQUEST['openid_identifier']))
			{
				$this->oidObj->identity = $_REQUEST['openid_identifier'];
				$this->oidObj->required = array('namePerson/friendly', 'contact/email', 'pref/language');
				$this->oidObj->optional = array('namePerson');

				HTTP::sendHeader('Location', $this->oidObj->authUrl());
				exit;
			} else {
				HTTP::redirectTo('index.php?code=4');
			}
		}
	}

	public function isActiveMode()
	{
		return false;
	}

	public function isValid()
	{
		return $this->oidObj->mode && $this->oidObj->mode != 'cancel';
	}

	public function getAccount()
	{
		$user	= $this->oidObj->getAttributes();
		
		if(!empty($user['contact/email']))
		{
			return $user['contact/email'];
		}
		
		if(!empty($user['namePerson/friendly']))
		{
			return $user['namePerson/friendly'];
		}
		
		if(!empty($user['namePerson']))
		{
			return $user['namePerson'];
		}		
		
		HTTP::redirectTo('index.php?code=4');

		return false;
	}
	
	public function register()
	{
		$uid	= $this->getAccount();
		$user	= $this->oidObj->getAttributes();
		
		if(empty($user['contact/email']))
		{
			HTTP::redirectTo('index.php?code=4');
		}

		$sql	= 'SELECT validationID, validationKey FROM %%USERS_VALID%%
		WHERE universe = :universe AND email = :email;';

		$registerData	= Database::get()->selectSingle($sql, array(
			':universe'	=> Universe::current(),
			':email'	=> $user['contact/email']
		));

		if(!empty($registerData))
		{
			$url	= sprintf('index.php?uni=%s&page=reg&action=valid&i=%s&validationKey=%s',
				Universe::current(), $registerData['validationID'], $registerData['validationKey']);

			HTTP::redirectTo($url);
		}

		$sql	= 'INSERT INTO %%USERS_AUTH%% SET
		id = (SELECT id FROM %%USERS%% WHERE email = :email OR email_2 = :email),
		account = :accountId
		mode = :mode;';

		Database::get()->insert($sql, array(
			':email'		=> $user['contact/email'],
			':accountId'	=> $uid,
			':mode'			=> $this->oidObj->identity,
		));
	}

	public function getLoginData()
	{
		$user	= $this->oidObj->getAttributes();

		$sql	= 'SELECT
		user.id, user.username, user.dpath, user.authlevel, user.id_planet
		FROM %%USERS_AUTH%%
		INNER JOIN %%USERS%% user ON auth.id = user.id AND user.universe = :universe
		WHERE auth.account = :email AND mode = :mode;';
		
		return Database::get()->select($sql, array(
			':universe'	=> Universe::current(),
			':email'	=> $user['contact/email'],
			':mode'		=> $this->oidObj->identity
		));
	}

	public function getAccountData()
	{
		$user	= $this->oidObj->getAttributes();

		return array(
			'id'		=> $user['contact/email'],
			'name'		=> $this->getAccount(),
			'locale'	=> $user['pref/language']
		);
	}
}