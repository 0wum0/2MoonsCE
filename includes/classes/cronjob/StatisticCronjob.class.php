<?php

declare(strict_types=1);

/**
 * SmartMoons Statistic Cronjob
 * PHP 8.3 Ready
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
