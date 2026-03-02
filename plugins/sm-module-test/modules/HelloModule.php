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
class HelloModule implements GameModuleInterface
{
    public function getId(): string
    {
        return 'sm-module-test.hello';
    }

    public function isEnabled(): bool
    {
        try {
            $cfg = Config::get();
            if (isset($cfg->module_hello_enabled)) {
                return (bool)(int)$cfg->module_hello_enabled;
            }
        } catch (Throwable $e) {
            // Config not available — stay enabled
        }
        return true;
    }

    public function boot(GameContext $ctx): void
    {
        // Register a content_top action hook that renders a small debug banner.
        // This is visible in the game UI when the plugin is active.
        HookManager::get()->addAction('content_top', function (array $hookCtx): string {
            if (!defined('MODE') || MODE !== 'INGAME') {
                return '';
            }
            return '<div style="background:#1a3a1a;color:#7fff7f;padding:4px 10px;font-size:11px;border-bottom:1px solid #2a5a2a;">'
                . '&#x1F9E9; HelloModule (sm-module-test) active &mdash; v2 ModuleManager OK'
                . '</div>';
        }, 5);

        // Log that boot ran (only in debug mode to avoid log spam)
        if (defined('MODE')) {
            error_log('[HelloModule] boot() called, mode=' . MODE);
        }
    }

    public function beforeRequest(GameContext $ctx): void
    {
        // Inject a template variable so Twig templates can read it.
        // Uses the global $tplObj if available (set by AbstractGamePage).
        // This is a demonstration — the variable 'helloModuleActive' will be
        // available in any Twig template as {{ helloModuleActive }}.
        if (isset($GLOBALS['tplObj']) && is_object($GLOBALS['tplObj']) && method_exists($GLOBALS['tplObj'], 'assign_vars')) {
            $GLOBALS['tplObj']->assign_vars(['helloModuleActive' => true], true);
        }

        // Store a value in the shared context bag for other modules to read
        $ctx->set('sm-module-test.hello.booted', true);
        $ctx->set('sm-module-test.hello.bootTime', $ctx->time);

        error_log('[HelloModule] beforeRequest() called, user_id=' . ($ctx->user['id'] ?? 'n/a'));
    }

    public function afterRequest(GameContext $ctx): void
    {
        $bootTime = $ctx->get('sm-module-test.hello.bootTime', 0);
        $elapsed  = $ctx->time - (int)$bootTime;
        error_log('[HelloModule] afterRequest() called, elapsed=' . $elapsed . 's');
    }
}
