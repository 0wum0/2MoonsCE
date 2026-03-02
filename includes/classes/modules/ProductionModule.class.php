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
class ProductionModule implements GameModuleInterface
{
    public function getId(): string
    {
        return 'core.production';
    }

    public function isEnabled(): bool
    {
        try {
            $cfg = Config::get();
            // Default: enabled (1). Only disabled when explicitly set to 0.
            if (isset($cfg->module_production_enabled)) {
                return (bool)(int)$cfg->module_production_enabled;
            }
        } catch (Throwable $e) {
            // Config not available (e.g. INSTALL mode) — stay enabled
        }
        return true;
    }

    public function boot(GameContext $ctx): void
    {
        // Bridge: forward the existing 'game.production' filter to the new
        // 'production.calculate' hook so plugins can use either name.
        // Priority 5 ensures this runs before any plugin-registered handlers
        // on 'game.production', giving 'production.calculate' handlers a chance
        // to run first (they are called inside the game.production chain).
        HookManager::get()->addFilter('game.production', function (array $temp, array $prodCtx): array {
            return HookManager::get()->applyFilters('production.calculate', $temp, $prodCtx);
        }, 5);
    }

    public function beforeRequest(GameContext $ctx): void
    {
        // Nothing to do before the request for the production wrapper.
        // The actual production calculation happens inside ResourceUpdate::CalcResource()
        // which is called from AbstractGamePage::__construct() — the hook registered
        // in boot() is already in place at that point.
    }

    public function afterRequest(GameContext $ctx): void
    {
        // Nothing to do after the request for the production wrapper.
    }
}
