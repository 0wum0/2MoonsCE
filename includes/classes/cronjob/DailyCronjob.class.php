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

class DailyCronjob implements CronjobTask
{
	function run(): void
	{
		$this->optimizeTables();
		$this->clearCache();
		$this->reCalculateCronjobs();
		$this->clearEcoCache();
	}
	
	function optimizeTables(): void
	{
		$sql			= "SHOW TABLE STATUS FROM `".DB_NAME."`;";
		$sqlTableRaw	= Database::get()->nativeQuery($sql);

		$prefixCounts	= strlen(DB_PREFIX);
		$dbTables		= array();

		foreach($sqlTableRaw as $table)
		{
			if (DB_PREFIX == substr($table['Name'], 0, $prefixCounts)) {
				$dbTables[] = $table['Name'];
			}
		}

		if(!empty($dbTables))
		{
			Database::get()->nativeQuery("OPTIMIZE TABLE ".implode(', ', $dbTables).";");
		}
	}

	function clearCache(): void
	{
		ClearCache();
	}
	
	function reCalculateCronjobs(): void
	{
		Cronjob::reCalculateCronjobs();
	}
	
	function clearEcoCache(): void
	{
		$sql	= "UPDATE %%PLANETS%% SET eco_hash = '';";
		Database::get()->update($sql);
	}
}
