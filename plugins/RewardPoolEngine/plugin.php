<?php

declare(strict_types=1);

/**
 * RewardPoolEngine – Plugin Bootstrap
 *
 * Registers:
 *  - Plugin DB tables via ensureTables()
 *  - rewardPool.request action hook (callable API for other plugins)
 *  - No ingame CSS/JS assets (pure server-side engine)
 *
 * The GameModuleInterface module (RewardPoolModule) is loaded automatically
 * by PluginManager via manifest["modules"].
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
