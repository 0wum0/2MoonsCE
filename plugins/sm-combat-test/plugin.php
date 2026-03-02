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

HookManager::get()->addFilter('combat.pre_calculate', function (array $ctx): array {
    if (defined('DEBUG_COMBAT') && DEBUG_COMBAT) {
        $attackerCount = 0;
        $defenderCount = 0;
        foreach ($ctx['attackers'] as $fleet) {
            $attackerCount += array_sum($fleet['unit'] ?? []);
        }
        foreach ($ctx['defenders'] as $fleet) {
            $defenderCount += array_sum($fleet['unit'] ?? []);
        }
        error_log('[sm-combat-test] pre_calculate: attackers=' . $attackerCount . ' defenders=' . $defenderCount);
    }
    return $ctx;
}, 10);

HookManager::get()->addFilter('combat.post_calculate', function (array $result, array $context): array {
    $result['meta']['sm_combat_test'] = true;
    return $result;
}, 10);
