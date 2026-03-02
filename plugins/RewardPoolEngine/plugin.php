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

require_once __DIR__ . '/lib/RewardPoolDb.php';

try {
    RewardPoolDb::get()->ensureTables();
} catch (Throwable $e) {
    error_log('[RewardPoolEngine] ensureTables() failed in bootstrap: ' . $e->getMessage());
}

// Register the rewardPool.request action so other plugins can call:
//   HookManager::get()->doAction('rewardPool.request', $poolName, $contextData);
// and receive a reward array back via the filter variant.
(static function (): void {
    $hm = HookManager::get();

    // rewardPool.draw filter: $result = applyFilters('rewardPool.draw', [], $poolName, $contextData)
    // Returns: ['type'=>..., 'value'=>..., 'meta'=>[...]] or [] on failure.
    $hm->addFilter('rewardPool.draw', static function (array $result, string $poolName, array $contextData = []): array {
        if (!defined('MODE') || MODE !== 'INGAME') {
            return $result;
        }
        try {
            return RewardPoolDb::get()->getRandomReward($poolName, $contextData);
        } catch (Throwable $e) {
            error_log('[RewardPoolEngine] rewardPool.draw error: ' . $e->getMessage());
            return [];
        }
    }, 10);
})();
