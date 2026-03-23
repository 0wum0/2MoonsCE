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

// @admin-migrated (Phase 4 — AbstractAdminPage)
class ShowStatUpdatePage extends AbstractAdminPage
{
    public function __construct()
    {
        parent::__construct('ShowStatUpdatePage');
    }

    protected function run(): void
    {
        global $LNG;

        require_once 'includes/classes/class.statbuilder.php';

        $stat   = new statbuilder();
        $result = $stat->MakeStats();

        $memoryPeak    = str_replace(['%p', '%m'], $result['memory_peak'],    $LNG['sb_top_memory']);
        $memoryEnd     = str_replace(['%e', '%m'], $result['end_memory'],     $LNG['sb_final_memory']);
        $memoryInitial = str_replace(['%i', '%m'], $result['initial_memory'], $LNG['sb_start_memory']);
        $statsTime     = sprintf($LNG['sb_stats_update'], $result['totaltime']);
        $statsSql      = sprintf($LNG['sb_sql_counts'],   $result['sql_count']);

        $this->message(
            $LNG['sb_stats_updated'] . $statsTime . $memoryInitial . $memoryEnd . $memoryPeak . $statsSql,
            false,
            0,
            true
        );
    }
}
