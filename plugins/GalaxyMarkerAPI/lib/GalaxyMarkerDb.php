<?php
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
class GalaxyMarkerDb
{
    private static ?GalaxyMarkerDb $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    public function ensureTables(): void
    {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';

        Database::get()->query("
            CREATE TABLE IF NOT EXISTS `{$prefix}galaxy_markers` (
                `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `galaxy`     TINYINT       NOT NULL DEFAULT 1,
                `system`     SMALLINT      NOT NULL DEFAULT 1,
                `position`   TINYINT       NOT NULL DEFAULT 1,
                `type`       VARCHAR(32)   NOT NULL DEFAULT 'info',
                `icon`       VARCHAR(64)   NOT NULL DEFAULT 'fa-map-marker-alt',
                `color`      VARCHAR(16)   NOT NULL DEFAULT '#38bdf8',
                `tooltip`    VARCHAR(255)  NOT NULL DEFAULT '',
                `expires_at` INT UNSIGNED  NOT NULL DEFAULT 0,
                `created_at` INT UNSIGNED  NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_coords` (`galaxy`, `system`, `position`),
                KEY `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    // ── Marker API ────────────────────────────────────────────────────────────

    /**
     * Insert or replace a persisted marker.
     * @param array<string,mixed> $data
     */
    public function upsertMarker(array $data): bool
    {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';
        $db     = Database::get();
        $now    = defined('TIMESTAMP') ? TIMESTAMP : time();

        $db->insert(
            "INSERT INTO `{$prefix}galaxy_markers`
                (`galaxy`, `system`, `position`, `type`, `icon`, `color`, `tooltip`, `expires_at`, `created_at`)
             VALUES
                (:galaxy, :system, :position, :type, :icon, :color, :tooltip, :expires_at, :created_at)
             ON DUPLICATE KEY UPDATE
                `type`       = :type2,
                `icon`       = :icon2,
                `color`      = :color2,
                `tooltip`    = :tooltip2,
                `expires_at` = :expires_at2",
            [
                ':galaxy'      => (int)($data['galaxy']     ?? 1),
                ':system'      => (int)($data['system']     ?? 1),
                ':position'    => (int)($data['position']   ?? 1),
                ':type'        => (string)($data['type']    ?? 'info'),
                ':icon'        => (string)($data['icon']    ?? 'fa-map-marker-alt'),
                ':color'       => (string)($data['color']   ?? '#38bdf8'),
                ':tooltip'     => (string)($data['tooltip'] ?? ''),
                ':expires_at'  => (int)($data['expires_at'] ?? 0),
                ':created_at'  => $now,
                ':type2'       => (string)($data['type']    ?? 'info'),
                ':icon2'       => (string)($data['icon']    ?? 'fa-map-marker-alt'),
                ':color2'      => (string)($data['color']   ?? '#38bdf8'),
                ':tooltip2'    => (string)($data['tooltip'] ?? ''),
                ':expires_at2' => (int)($data['expires_at'] ?? 0),
            ]
        );

        return true;
    }

    /**
     * Return all non-expired markers as arrays.
     * @return array<int, array<string,mixed>>
     */
    public function getActiveMarkers(): array
    {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';
        $now    = defined('TIMESTAMP') ? TIMESTAMP : time();

        return Database::get()->select(
            "SELECT * FROM `{$prefix}galaxy_markers`
             WHERE `expires_at` = 0 OR `expires_at` > :now
             ORDER BY `galaxy`, `system`, `position`",
            [':now' => $now]
        ) ?: [];
    }

    /**
     * Remove all expired markers (housekeeping).
     */
    public function purgeExpired(): void
    {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';
        $now    = defined('TIMESTAMP') ? TIMESTAMP : time();

        Database::get()->delete(
            "DELETE FROM `{$prefix}galaxy_markers` WHERE `expires_at` > 0 AND `expires_at` <= :now",
            [':now' => $now]
        );
    }
}
