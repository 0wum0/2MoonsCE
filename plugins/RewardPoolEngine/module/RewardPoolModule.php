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
require_once __DIR__ . '/../lib/RewardPoolDb.php';

/**
 * RewardPoolModule – GameModuleInterface v2 implementation.
 *
 * Responsibilities:
 *  - boot()          : no gameplay filter hooks needed (engine is passive).
 *  - beforeRequest() : injects rewardPoolEngine template variable so Twig
 *                      templates can check availability.
 *  - afterRequest()  : no-op.
 *
 * Other plugins draw rewards via:
 *   $reward = HookManager::get()->applyFilters('rewardPool.draw', [], $poolName, $ctx);
 */
class RewardPoolModule implements GameModuleInterface
{
    public function getId(): string
    {
        return 'reward_pool_engine.main';
    }

    public function isEnabled(): bool
    {
        try {
            $db = Database::get();
            if ($db === null) {
                return false;
            }
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';
            $cnt = $db->selectSingle(
                "SELECT COUNT(*) AS cnt FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :tname",
                [':tname' => $prefix . 'reward_pools'],
                'cnt'
            );
            return ((int)($cnt ?? 0)) > 0;
        } catch (Throwable $e) {
            error_log('[RewardPoolModule] isEnabled() error: ' . $e->getMessage());
            return false;
        }
    }

    public function boot(GameContext $ctx): void
    {
        // No gameplay filter hooks: the engine exposes its API via rewardPool.draw
        // registered in plugin.php. Nothing to add here.
    }

    public function beforeRequest(GameContext $ctx): void
    {
        try {
            if (isset($GLOBALS['tplObj'])
                && is_object($GLOBALS['tplObj'])
                && method_exists($GLOBALS['tplObj'], 'assign_vars')
            ) {
                $GLOBALS['tplObj']->assign_vars([
                    'rewardPoolEngineActive' => true,
                ], true);
            }
        } catch (Throwable $e) {
            error_log('[RewardPoolModule] beforeRequest() error: ' . $e->getMessage());
        }
    }

    public function afterRequest(GameContext $ctx): void {}
}
