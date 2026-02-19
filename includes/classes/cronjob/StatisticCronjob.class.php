<?php

/**
 * SmartMoons Statistic Cronjob
 * PHP 8.3 Ready
 */

require_once 'includes/classes/cronjob/CronjobTask.interface.php';

class StatisticCronjob implements CronjobTask
{
	function run(): void
	{
		require_once 'includes/classes/class.statbuilder.php';
		$stat = new Statbuilder();
		$stat->MakeStats();
	}
}
