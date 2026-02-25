<?php

declare(strict_types=1);

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
            $result = Database::get()->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = '{$prefix}galaxy_markers'"
            );
            if (!$result) {
                return false;
            }
            $row = Database::get()->fetch_array($result);
            return ((int)($row['cnt'] ?? 0)) > 0;
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
