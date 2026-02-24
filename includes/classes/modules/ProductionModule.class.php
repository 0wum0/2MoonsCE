<?php

declare(strict_types=1);

/**
 * ProductionModule – v2 Core Wrapper Module
 *
 * Wraps the existing ResourceUpdate / production calculation system.
 * Does NOT replace any logic — only provides additional hook points that
 * plugins can attach to.
 *
 * Hook points exposed:
 *   FILTER  'production.calculate'  ($temp, $context)
 *       Called inside ReBuildCache() after the raw per-resource totals are
 *       assembled but before multipliers are applied.
 *       $temp  = array keyed by resource ID (901/902/903/911) with
 *               'plus', 'minus', 'max' sub-keys.
 *       $context = ['planet' => $PLANET, 'user' => $USER]
 *       (This is an alias / re-export of the existing 'game.production' hook.)
 *
 * The module is enabled by default (wrapper mode = no gameplay change).
 * To disable, set config key 'module_production_enabled' to 0.
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
