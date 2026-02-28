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
