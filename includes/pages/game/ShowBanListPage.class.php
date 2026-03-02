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

class ShowBanListPage extends AbstractGamePage
{
	public static $requireModule = MODULE_BANLIST;

	function __construct()
	{
		parent::__construct();
	}

	function show()
	{
		global $USER, $LNG;

		$page  	= HTTP::_GP('side', 1);
		$db 	= Database::get();

		$sql = "SELECT COUNT(*) as count FROM %%BANNED%% WHERE universe = :universe ORDER BY time DESC;";
		$banCount = $db->selectSingle($sql, array(
			':universe'	=> Universe::current()
		), 'count');

		$maxPage	= ceil($banCount / BANNED_USERS_PER_PAGE);
		$page		= max(1, min($page, $maxPage));

		$sql = "SELECT * FROM %%BANNED%% WHERE universe = :universe ORDER BY time DESC LIMIT :offset, :limit;";
		$banResult = $db->select($sql, array(
			':universe'	=> Universe::current(),
			':offset'   => (($page - 1) * BANNED_USERS_PER_PAGE),
			':limit'    => BANNED_USERS_PER_PAGE,
		));

		$banList	= array();

		foreach ($banResult as $banRow)
		{
			$banList[]	= array(
				'player'	=> $banRow['who'],
				'theme'		=> $banRow['theme'],
				'from'		=> _date($LNG['php_tdformat'], $banRow['time'], $USER['timezone']),
				'to'		=> _date($LNG['php_tdformat'], $banRow['longer'], $USER['timezone']),
				'admin'		=> $banRow['author'],
				'mail'		=> $banRow['email'],
				'info'		=> sprintf($LNG['bn_writemail'], $banRow['author']),
			);
		}

		$this->assign(array(
			'banList'	=> $banList,
			'banCount'	=> $banCount,
			'page'		=> $page,
			'maxPage'	=> $maxPage,
		));

		$this->display('page.banList.default.tpl');
	}
}