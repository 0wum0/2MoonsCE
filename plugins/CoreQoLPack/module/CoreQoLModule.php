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
class CoreQoLModule implements GameModuleInterface
{
    public function getId(): string
    {
        return 'core_qol_pack.main';
    }

    public function isEnabled(): bool
    {
        // Always enabled when the plugin is active and we are in INGAME mode.
        return defined('MODE') && MODE === 'INGAME';
    }

    public function boot(GameContext $ctx): void
    {
        // No gameplay hooks. Frontend only.
    }

    public function beforeRequest(GameContext $ctx): void
    {
        try {
            if (!isset($GLOBALS['tplObj'])
                || !is_object($GLOBALS['tplObj'])
                || !method_exists($GLOBALS['tplObj'], 'assign_vars')
            ) {
                return;
            }

            // Provide qol.js with the resource-sync endpoint and a nonce.
            $apiUrl = defined('SERVER_URL')
                ? rtrim((string) SERVER_URL, '/') . '/game.php'
                : 'game.php';

            $GLOBALS['tplObj']->assign_vars([
                'qolConfig' => [
                    'apiUrl'       => $apiUrl,
                    'syncInterval' => 10000,
                    'autosaveMs'   => 5000,
                ],
                'coreQoLActive' => true,
            ], true);
        } catch (Throwable $e) {
            error_log('[CoreQoLModule] beforeRequest() error: ' . $e->getMessage());
        }
    }

    public function afterRequest(GameContext $ctx): void {}
}
