<?php

declare(strict_types=1);

/**
 * sm-combat-test – SmartMoons Combat Hook Test Plugin
 *
 * Demonstrates that CombatFramework hook points fire correctly.
 * Does NOT modify any combat values – result stays identical to vanilla.
 *
 * Hooks registered:
 *   combat.pre_calculate  – logs attacker/defender fleet sizes in debug mode
 *   combat.post_calculate – stamps $result['meta']['sm_combat_test'] = true
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
