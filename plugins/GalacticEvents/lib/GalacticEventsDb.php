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
class GalacticEventsDb
{
    private static ?GalacticEventsDb $instance = null;

    private string $tblEvents;
    private string $tblSettings;

    private function __construct()
    {
        $prefix            = defined('DB_PREFIX') ? DB_PREFIX : '';
        $this->tblEvents   = $prefix . 'galactic_events';
        $this->tblSettings = $prefix . 'galactic_events_settings';
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function tableEvents(): string   { return $this->tblEvents; }
    public function tableSettings(): string { return $this->tblSettings; }

    // ── Schema bootstrap ──────────────────────────────────────────────────────

    /**
     * Create plugin tables if they do not yet exist.
     * Called once from plugin.php bootstrap so every request gets the tables
     * even before the admin runs install.
     */
    public function ensureTables(): void
    {
        try {
            $db = Database::get();
            $te = $this->tblEvents;
            $ts = $this->tblSettings;

            $db->query("CREATE TABLE IF NOT EXISTS `{$te}` (
                `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `name`         VARCHAR(128)  NOT NULL DEFAULT '',
                `effect_type`  VARCHAR(64)   NOT NULL DEFAULT '',
                `effect_value` DECIMAL(8,4)  NOT NULL DEFAULT 0,
                `duration`     INT UNSIGNED  NOT NULL DEFAULT 60,
                `active_from`  INT UNSIGNED  NOT NULL DEFAULT 0,
                `active_until` INT UNSIGNED  NOT NULL DEFAULT 0,
                `created_at`   INT UNSIGNED  NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_active_until` (`active_until`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $db->query("CREATE TABLE IF NOT EXISTS `{$ts}` (
                `id`                     TINYINT UNSIGNED NOT NULL DEFAULT 1,
                `enabled`                TINYINT(1)       NOT NULL DEFAULT 1,
                `check_interval`         INT UNSIGNED     NOT NULL DEFAULT 30,
                `trigger_chance_percent` TINYINT UNSIGNED NOT NULL DEFAULT 20,
                `event_duration`         INT UNSIGNED     NOT NULL DEFAULT 60,
                `effect_type`            VARCHAR(64)      NOT NULL DEFAULT 'metal_production',
                `effect_value`           DECIMAL(8,4)     NOT NULL DEFAULT 10.0000,
                `last_check`             INT UNSIGNED     NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $db->query("INSERT IGNORE INTO `{$ts}` (`id`,`enabled`,`check_interval`,`trigger_chance_percent`,`event_duration`,`effect_type`,`effect_value`,`last_check`) VALUES (1, 1, 30, 20, 60, 'metal_production', 10.0000, 0);");

        } catch (Throwable $e) {
            error_log('[GalacticEvents] ensureTables() error: ' . $e->getMessage());
        }
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    public function getSettings(): array
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return [];
            }
            $row = $db->selectSingle(
                'SELECT * FROM `' . $this->tblSettings . '` WHERE `id` = 1;'
            );
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            error_log('[GalacticEvents] getSettings() error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public function saveSettings(array $data): void
    {
        try {
            $db = Database::get();
            $db->update(
                'UPDATE `' . $this->tblSettings . '` SET
                    `enabled`                = :enabled,
                    `check_interval`         = :check_interval,
                    `trigger_chance_percent` = :trigger_chance_percent,
                    `event_duration`         = :event_duration,
                    `effect_type`            = :effect_type,
                    `effect_value`           = :effect_value
                 WHERE `id` = 1;',
                [
                    ':enabled'                => (int) ($data['enabled'] ?? 1),
                    ':check_interval'         => max(1, (int) ($data['check_interval'] ?? 30)),
                    ':trigger_chance_percent' => min(100, max(1, (int) ($data['trigger_chance_percent'] ?? 20))),
                    ':event_duration'         => max(1, (int) ($data['event_duration'] ?? 60)),
                    ':effect_type'            => (string) ($data['effect_type'] ?? 'metal_production'),
                    ':effect_value'           => round((float) ($data['effect_value'] ?? 10), 4),
                ]
            );
        } catch (Throwable $e) {
            error_log('[GalacticEvents] saveSettings() error: ' . $e->getMessage());
        }
    }

    public function updateLastCheck(int $time): void
    {
        try {
            $db = Database::get();
            $db->update(
                'UPDATE `' . $this->tblSettings . '` SET `last_check` = :t WHERE `id` = 1;',
                [':t' => $time]
            );
        } catch (Throwable $e) {
            error_log('[GalacticEvents] updateLastCheck() error: ' . $e->getMessage());
        }
    }

    // ── Events ────────────────────────────────────────────────────────────────

    /**
     * Return the currently active event or null.
     * @return array<string,mixed>|null
     */
    public function getActiveEvent(): ?array
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return null;
            }
            $now = defined('TIMESTAMP') ? TIMESTAMP : time();
            $row = $db->selectSingle(
                'SELECT * FROM `' . $this->tblEvents . '`
                 WHERE `active_from` <= :now AND `active_until` > :now2
                 ORDER BY `id` DESC LIMIT 1;',
                [':now' => $now, ':now2' => $now]
            );
            return (is_array($row) && !empty($row)) ? $row : null;
        } catch (Throwable $e) {
            error_log('[GalacticEvents] getActiveEvent() error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Insert a new event row and return its id.
     * @param array<string,mixed> $settings  Current settings row
     */
    public function createEvent(array $settings): int
    {
        try {
            $now      = defined('TIMESTAMP') ? TIMESTAMP : time();
            $duration = max(1, (int) ($settings['event_duration'] ?? 60)) * 60;
            $type     = (string) ($settings['effect_type'] ?? 'metal_production');
            $value    = round((float) ($settings['effect_value'] ?? 10), 4);
            $name     = $this->buildEventName($type, $value);

            $db = Database::get();
            $db->insert(
                'INSERT INTO `' . $this->tblEvents . '`
                    (`name`,`effect_type`,`effect_value`,`duration`,`active_from`,`active_until`,`created_at`)
                 VALUES (:name,:type,:value,:duration,:from,:until,:created);',
                [
                    ':name'     => $name,
                    ':type'     => $type,
                    ':value'    => $value,
                    ':duration' => (int) ($settings['event_duration'] ?? 60),
                    ':from'     => $now,
                    ':until'    => $now + $duration,
                    ':created'  => $now,
                ]
            );
            return (int) $db->lastInsertId();
        } catch (Throwable $e) {
            error_log('[GalacticEvents] createEvent() error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Recent events (last 10) for admin display.
     * @return array<int,array<string,mixed>>
     */
    public function getRecentEvents(int $limit = 10): array
    {
        try {
            $db   = Database::get();
            $rows = $db->select(
                'SELECT * FROM `' . $this->tblEvents . '`
                 ORDER BY `id` DESC LIMIT ' . max(1, $limit) . ';'
            );
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            error_log('[GalacticEvents] getRecentEvents() error: ' . $e->getMessage());
            return [];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a human-readable event name from type + value.
     */
    public function buildEventName(string $type, float $value): string
    {
        $sign   = $value >= 0 ? '+' : '';
        $labels = [
            'metal_production'      => 'Metall-Produktion',
            'crystal_production'    => 'Kristall-Produktion',
            'deuterium_production'  => 'Deuterium-Produktion',
            'energy_output'         => 'Energie-Ausgabe',
            'build_time'            => 'Bauzeit',
            'research_time'         => 'Forschungszeit',
        ];
        $label = $labels[$type] ?? $type;
        return 'Galaktisches Event: ' . $label . ' ' . $sign . $value . '%';
    }
}
