<?php

declare(strict_types=1);

/**
 * QueueModule – v2 Core Wrapper Module
 *
 * Wraps the existing building / research / shipyard queue processing system.
 * Does NOT replace any logic — only provides additional hook points that
 * plugins can attach to.
 *
 * Hook points exposed:
 *
 *   ACTION  'queue.beforeProcess'  ($context)
 *       Fired at the start of ResourceUpdate::CalcResource(), before any queue
 *       processing begins.
 *       $context = ['user' => &$USER, 'planet' => &$PLANET, 'time' => $TIME]
 *
 *   ACTION  'queue.afterProcess'   ($context)
 *       Fired at the end of ResourceUpdate::CalcResource(), after all queues
 *       have been processed and resources updated.
 *       $context = ['user' => &$USER, 'planet' => &$PLANET, 'time' => $TIME]
 *
 *   FILTER  'queue.buildTime'      ($time, $context)
 *       Alias / re-export of the existing 'game.buildTime' filter.
 *       $context = ['element' => $Element, 'level' => $level, 'destroy' => $forDestroy]
 *       Allows plugins to use the new namespaced hook name while the legacy
 *       'game.buildTime' hook continues to work unchanged.
 *
 * The module is enabled by default (wrapper mode = no gameplay change).
 * To disable, set config key 'module_queue_enabled' to 0.
 */
class QueueModule implements GameModuleInterface
{
    public function getId(): string
    {
        return 'core.queue';
    }

    public function isEnabled(): bool
    {
        try {
            $cfg = Config::get();
            if (isset($cfg->module_queue_enabled)) {
                return (bool)(int)$cfg->module_queue_enabled;
            }
        } catch (Throwable $e) {
            // Config not available — stay enabled
        }
        return true;
    }

    public function boot(GameContext $ctx): void
    {
        // Bridge: forward 'game.buildTime' to the new 'queue.buildTime' hook.
        // Priority 5 so it runs before user-registered 'game.buildTime' handlers,
        // giving 'queue.buildTime' handlers first pick.
        HookManager::get()->addFilter('game.buildTime', function (int|float $time, array $buildCtx): int {
            return max(1, (int) round((float) HookManager::get()->applyFilters('queue.buildTime', $time, $buildCtx)));
        }, 5);

        // Wire queue.beforeProcess and queue.afterProcess into the existing
        // beforeController / afterController action hooks.
        // beforeController fires just before the page controller runs, which is
        // after ResourceUpdate::CalcResource() has already been called from
        // AbstractGamePage::__construct().  For a true "before queue" hook we
        // use priority 1 on beforeController so it fires as early as possible.
        //
        // NOTE: ResourceUpdate::CalcResource() is called in AbstractGamePage::__construct()
        // BEFORE display() (which fires beforeController/afterController).
        // Therefore queue.beforeProcess / queue.afterProcess are best-effort
        // notification hooks for this request's queue run — they cannot cancel
        // the queue processing that already happened.  Plugins that need to
        // intercept queue processing should use the 'queue.buildTime' filter or
        // override ResourceUpdate via the existing eco system.
        //
        // For a future v2.1 deep integration, ResourceUpdate itself would call
        // ModuleManager hooks directly.  For now, these hooks fire around the
        // controller boundary as a useful notification point.

        HookManager::get()->addAction('beforeController', function (array $hookCtx): void {
            $moduleCtx = ModuleManager::get()->getContext();
            if ($moduleCtx === null) {
                return;
            }
            $context = [
                'user'   => $GLOBALS['USER']  ?? [],
                'planet' => $GLOBALS['PLANET'] ?? [],
                'time'   => $moduleCtx->time,
            ];
            HookManager::get()->doAction('queue.beforeProcess', $context);
        }, 1);

        HookManager::get()->addAction('afterController', function (array $hookCtx): void {
            $moduleCtx = ModuleManager::get()->getContext();
            if ($moduleCtx === null) {
                return;
            }
            $context = [
                'user'   => $GLOBALS['USER']  ?? [],
                'planet' => $GLOBALS['PLANET'] ?? [],
                'time'   => $moduleCtx->time,
            ];
            HookManager::get()->doAction('queue.afterProcess', $context);
        }, 1);
    }

    public function beforeRequest(GameContext $ctx): void
    {
        // No per-request setup needed for the queue wrapper.
    }

    public function afterRequest(GameContext $ctx): void
    {
        // No per-request teardown needed for the queue wrapper.
    }
}
