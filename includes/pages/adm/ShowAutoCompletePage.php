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

if ($USER['authlevel'] == AUTH_USR)
{
	throw new Exception("Permission error!");
}

function ShowAutoCompletePage()
{
	$searchText	= HTTP::_GP('term', '', UTF8_SUPPORT);
	$searchList	= array();
	
	if(empty($searchText) || $searchText === '#') {
		echo json_encode(array());
		exit;
	}
	
	if(substr($searchText, 0, 1) === '#')
	{
		$where = 'id = '.((int) substr($searchText, 1));
		$orderBy = ' ORDER BY id ASC';
	}
	else
	{
		$where = "username LIKE '%".$GLOBALS['DATABASE']->escape($searchText, true)."%'";
		$orderBy = " ORDER BY (IF(username = '".$GLOBALS['DATABASE']->sql_escape($searchText, true)."', 1, 0) + IF(username LIKE '".$GLOBALS['DATABASE']->sql_escape($searchText, true)."%', 1, 0)) DESC, username";
	}
	
	$userRaw		= $GLOBALS['DATABASE']->query("SELECT id, username FROM ".USERS." WHERE universe = ".Universe::getEmulated()." AND ".$where.$orderBy." LIMIT 20");
	while($userRow = $GLOBALS['DATABASE']->fetch_array($userRaw))
	{
		$searchList[]	= array(
			'label' => $userRow['username'].' (ID:'.$userRow['id'].')', 
			'value' => $userRow['username']
		);
	}
	
	echo json_encode($searchList);
	exit;
}