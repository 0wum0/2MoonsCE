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
require_once __DIR__ . '/../lib/GalaxyMarkerDb.php';
require_once __DIR__ . '/../lib/GalaxyMarkerRegistry.php';

/**
 * GalaxyMarkerModule – GameModuleInterface v2 implementation.
 *
 * Responsibilities:
 *  - boot()          : registers galaxy.registerMarker and galaxy.renderOverlay
 *                      hooks (deduplication guard ensures they are only registered
 *                      once even if plugin.php and this module both load).
 *  - beforeRequest() : purge expired DB markers once per request (lightweight).
 *  - afterRequest()  : clear runtime registry.
 */
class GalaxyMarkerModule implements GameModuleInterface
{
    public function getId(): string
    {
        return 'galaxy_marker_api.main';
    }

    public function isEnabled(): bool
    {
        try {
            $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';
            $cnt = Database::get()->selectSingle(
                "SELECT COUNT(*) AS cnt FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :tname",
                [':tname' => $prefix . 'galaxy_markers'],
                'cnt'
            );
            return ((int)($cnt ?? 0)) > 0;
        } catch (Throwable $e) {
            error_log('[GalaxyMarkerModule] isEnabled() error: ' . $e->getMessage());
            return false;
        }
    }

    public function boot(GameContext $ctx): void
    {
        // Hooks are already registered in plugin.php.
        // Nothing additional needed in boot().
    }

    public function beforeRequest(GameContext $ctx): void
    {
        try {
            // Housekeeping: remove expired markers once per request.
            // This avoids a separate cron dependency.
            GalaxyMarkerDb::get()->purgeExpired();

            // Inject template variable so Twig can check availability.
            if (isset($GLOBALS['tplObj'])
                && is_object($GLOBALS['tplObj'])
                && method_exists($GLOBALS['tplObj'], 'assign_vars')
            ) {
                $GLOBALS['tplObj']->assign_vars([
                    'galaxyMarkerApiActive' => true,
                ], true);
            }
        } catch (Throwable $e) {
            error_log('[GalaxyMarkerModule] beforeRequest() error: ' . $e->getMessage());
        }
    }

    public function afterRequest(GameContext $ctx): void
    {
        try {
            GalaxyMarkerRegistry::get()->clear();
        } catch (Throwable $e) {
            error_log('[GalaxyMarkerModule] afterRequest() error: ' . $e->getMessage());
        }
    }
}
