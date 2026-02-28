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

class StatisticCronjob implements CronjobTask
{
	private function log(string $msg): void
	{
		$logPath = ROOT_PATH . 'cache/cron_debug.log';
		@file_put_contents($logPath, '[' . date('Y-m-d H:i:s') . '] [StatisticCronjob] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
	}

	function run(): void
	{
		$this->log('run() start');

		// Log universe and DB context
		try {
			$unis = Universe::availableUniverses();
			$this->log('available universes: [' . implode(',', $unis) . ']');
			$this->log('DB_NAME=' . (defined('DB_NAME') ? DB_NAME : 'n/a') . ' DB_PREFIX=' . (defined('DB_PREFIX') ? DB_PREFIX : 'n/a'));
		} catch (Throwable $e) {
			$this->log('WARNING: could not read universe/DB info: ' . $e->getMessage());
		}

		require_once 'includes/classes/class.statbuilder.php';

		try {
			$stat   = new statbuilder();
			$this->log('statbuilder instantiated — calling MakeStats()');
			$result = $stat->MakeStats();
			$this->log('MakeStats() complete — totaltime=' . ($result['totaltime'] ?? 'n/a')
				. 's sql_count=' . ($result['sql_count'] ?? 'n/a')
				. ' stats_time=' . ($result['stats_time'] ?? 'n/a'));
		} catch (Throwable $e) {
			$this->log('ERROR in MakeStats(): ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
			throw $e;
		}

		$this->log('run() done');
	}
}
