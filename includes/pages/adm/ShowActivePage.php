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

if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) throw new Exception("Permission error!");

function ShowActivePage()
{
	global $LNG, $USER;
	$id = HTTP::_GP('id', 0);
	if(isset($_GET['action']) && $_GET['action'] == 'delete' && !empty($id))
		$GLOBALS['DATABASE']->query("DELETE FROM ".USERS_VALID." WHERE `validationID` = '".$id."' AND `universe` = '".Universe::getEmulated()."';");

	$query = $GLOBALS['DATABASE']->query("SELECT * FROM ".USERS_VALID." WHERE `universe` = '".Universe::getEmulated()."' ORDER BY validationID ASC");

	$Users	= array();
	while ($User = $GLOBALS['DATABASE']->fetch_array($query)) {
		$Users[]	= array(
			'id'			=> $User['validationID'],
			'name'			=> $User['userName'],
			'date'			=> _date($LNG['php_tdformat'], $User['date'], $USER['timezone']),
			'email'			=> $User['email'],
			'ip'			=> $User['ip'],
			'password'		=> $User['password'],
			'validationKey'	=> $User['validationKey'],
		);
	}

	$template	= new template();

	$template->assign_vars(array(	
		'Users'				=> $Users,
		'uni'				=> Universe::getEmulated(),
	));
	
	$template->show('ActivePage.tpl');
}