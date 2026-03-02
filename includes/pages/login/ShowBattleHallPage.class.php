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

class ShowBattleHallPage extends AbstractLoginPage
{
	public static $requireModule = 0;

	function __construct() 
	{
		parent::__construct();
        $this->setWindow('light');
	}
	
	function show() 
	{
		global $LNG;
		$db = Database::get();

		$sql = "SELECT *, (
			SELECT DISTINCT
			IF(%%TOPKB_USERS%%.username = '', GROUP_CONCAT(%%USERS%%.username SEPARATOR ' & '), GROUP_CONCAT(%%TOPKB_USERS%%.username SEPARATOR ' & '))
			FROM %%TOPKB_USERS%%
			LEFT JOIN %%USERS%% ON uid = %%USERS%%.id
			WHERE %%TOPKB_USERS%%.`rid` = %%TOPKB%%.`rid` AND `role` = 1
		) as `attacker`,
		(
			SELECT DISTINCT
			IF(%%TOPKB_USERS%%.username = '', GROUP_CONCAT(%%USERS%%.username SEPARATOR ' & '), GROUP_CONCAT(%%TOPKB_USERS%%.username SEPARATOR ' & '))
			FROM %%TOPKB_USERS%% INNER JOIN %%USERS%% ON uid = id
			WHERE %%TOPKB_USERS%%.`rid` = %%TOPKB%%.`rid` AND `role` = 2
		) as `defender`
		FROM %%TOPKB%% WHERE `universe` = :universe ORDER BY units DESC LIMIT 100;";

		$hallRaw = $db->select($sql, array(
			':universe'	=> Universe::current(),
		));

		$hallList	= array();
		foreach($hallRaw as $hallRow) {
			$hallList[]	= array(
				'result'	=> $hallRow['result'],
				'time'		=> _date($LNG['php_tdformat'], $hallRow['time']),
				'units'		=> $hallRow['units'],
				'rid'		=> $hallRow['rid'],
				'attacker'	=> $hallRow['attacker'],
				'defender'	=> $hallRow['defender'],
			);
		}

		$universeSelect	= $this->getUniverseSelector();
		
		$this->assign(array(
			'universeSelect'	=> $universeSelect,
			'hallList'			=> $hallList,
		));
		$this->display('page.battleHall.default.tpl');
	}
}