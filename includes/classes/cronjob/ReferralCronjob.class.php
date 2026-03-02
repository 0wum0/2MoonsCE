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

require_once 'includes/classes/cronjob/CronjobTask.interface.php';

class ReferralCronjob implements CronjobTask
{
	function run(): void
	{		
		if(Config::get(ROOT_UNI)->ref_active != 1)
		{
			return;
		}
		/** @var $langObjects Language[] */
		$langObjects	= array();

		$db	= Database::get();

		$sql	= 'SELECT `username`, `ref_id`, `id`, `lang`, user.`universe`
		FROM %%USERS%% user
		INNER JOIN %%STATPOINTS%% as stats
		ON stats.`id_owner` = user.`id` AND stats.`stat_type` = :type AND stats.`total_points` >= :points
		WHERE user.`ref_bonus` = 1;';

		$userArray	= $db->select($sql, array(
			':type'		=> 1,
			':points'	=> Config::get(ROOT_UNI)->ref_minpoints
		));

		foreach($userArray as $user)
		{
			if(!isset($langObjects[$user['lang']]))
			{
				$langObjects[$user['lang']]	= new Language($user['lang']);
				$langObjects[$user['lang']]->includeData(array('L18N', 'INGAME', 'TECH', 'CUSTOM'));
			}

			$userConfig	= Config::get($user['universe']);
			
			$LNG	= $langObjects[$user['lang']];
			$sql	= 'UPDATE %%USERS%% SET `darkmatter` = `darkmatter` + :bonus WHERE `id` = :userId;';

			$db->update($sql, array(
				':bonus'	=> $userConfig->ref_bonus,
				':userId'	=> $user['ref_id']
			));

			$sql	= 'UPDATE %%USERS%% SET `ref_bonus` = 0 WHERE `id` = :userId;';

			$db->update($sql, array(
				':userId'	=> $user['id']
			));

			$Message	= sprintf($LNG['sys_refferal_text'], $user['username'], pretty_number($userConfig->ref_minpoints), pretty_number($userConfig->ref_bonus), $LNG['tech'][921]);
			PlayerUtil::sendMessage($user['ref_id'], '', $LNG['sys_refferal_from'], 4, sprintf($LNG['sys_refferal_title'], $user['username']), $Message, TIMESTAMP);
		}

	}
}
