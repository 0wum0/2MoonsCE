<?php

declare(strict_types=1);

class LiveFleetDb
{
    private static ?LiveFleetDb $instance = null;

    private string $tblSettings;
    private string $tblNpc;
    private string $tblWarp;
    private string $tblIntercept;

    private function __construct()
    {
        $p = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';
        $this->tblSettings  = $p . 'lft_settings';
        $this->tblNpc       = $p . 'lft_npc_attacks';
        $this->tblWarp      = $p . 'lft_warp_events';
        $this->tblIntercept = $p . 'lft_interceptions';
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Schema bootstrap ──────────────────────────────────────────────────────

    public function ensureTables(): void
    {
        $db = Database::get();
        if (!$db->isConnected()) {
            return;
        }
        $ts = $this->tblSettings;
        $tn = $this->tblNpc;
        $tw = $this->tblWarp;
        $ti = $this->tblIntercept;

        $db->query("CREATE TABLE IF NOT EXISTS `{$ts}` (
            `id`                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `enabled`              TINYINT(1)       NOT NULL DEFAULT 1,
            `npc_enabled`          TINYINT(1)       NOT NULL DEFAULT 1,
            `npc_chance_percent`   TINYINT UNSIGNED NOT NULL DEFAULT 5,
            `warp_risk_enabled`    TINYINT(1)       NOT NULL DEFAULT 1,
            `warp_risk_percent`    TINYINT UNSIGNED NOT NULL DEFAULT 3,
            `interception_enabled` TINYINT(1)       NOT NULL DEFAULT 1,
            `poll_interval_ms`     INT UNSIGNED     NOT NULL DEFAULT 3000,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->query("INSERT IGNORE INTO `{$ts}`
            (`id`,`enabled`,`npc_enabled`,`npc_chance_percent`,`warp_risk_enabled`,`warp_risk_percent`,`interception_enabled`,`poll_interval_ms`)
            VALUES (1,1,1,5,1,3,1,3000);");

        $db->query("CREATE TABLE IF NOT EXISTS `{$tn}` (
            `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `fleet_id`     INT UNSIGNED  NOT NULL,
            `owner_id`     INT UNSIGNED  NOT NULL,
            `galaxy`       TINYINT       NOT NULL DEFAULT 1,
            `system`       SMALLINT      NOT NULL DEFAULT 1,
            `planet`       TINYINT       NOT NULL DEFAULT 1,
            `loss_percent` TINYINT       NOT NULL DEFAULT 0,
            `resolved`     TINYINT(1)    NOT NULL DEFAULT 0,
            `notified`     TINYINT(1)    NOT NULL DEFAULT 0,
            `created_at`   INT UNSIGNED  NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_owner`    (`owner_id`),
            KEY `idx_fleet`    (`fleet_id`),
            KEY `idx_resolved` (`resolved`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->query("CREATE TABLE IF NOT EXISTS `{$tw}` (
            `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `fleet_id`      INT UNSIGNED  NOT NULL,
            `owner_id`      INT UNSIGNED  NOT NULL,
            `delay_seconds` INT UNSIGNED  NOT NULL DEFAULT 0,
            `resolved`      TINYINT(1)    NOT NULL DEFAULT 0,
            `notified`      TINYINT(1)    NOT NULL DEFAULT 0,
            `created_at`    INT UNSIGNED  NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_fleet` (`fleet_id`),
            KEY `idx_owner` (`owner_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->query("CREATE TABLE IF NOT EXISTS `{$ti}` (
            `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `target_fleet_id`      INT UNSIGNED NOT NULL,
            `interceptor_id`       INT UNSIGNED NOT NULL,
            `interceptor_fleet_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `status`               ENUM('pending','launched','resolved','failed') NOT NULL DEFAULT 'pending',
            `created_at`           INT UNSIGNED NOT NULL DEFAULT 0,
            `resolved_at`          INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_target`      (`target_fleet_id`),
            KEY `idx_interceptor` (`interceptor_id`),
            KEY `idx_status`      (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    public function getSettings(): array
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return [];
            }
            $row = $db->selectSingle("SELECT * FROM `{$this->tblSettings}` WHERE `id` = 1;");
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            error_log('[LiveFleetTracker] getSettings() error: ' . $e->getMessage());
            return [];
        }
    }

    public function saveSettings(array $data): void
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return;
            }
            $db->update(
                "UPDATE `{$this->tblSettings}` SET
                    `enabled`              = :enabled,
                    `npc_enabled`          = :npc_enabled,
                    `npc_chance_percent`   = :npc_chance,
                    `warp_risk_enabled`    = :warp_enabled,
                    `warp_risk_percent`    = :warp_risk,
                    `interception_enabled` = :intercept,
                    `poll_interval_ms`     = :poll_ms
                 WHERE `id` = 1;",
                [
                    ':enabled'     => (int)($data['enabled']              ?? 1),
                    ':npc_enabled' => (int)($data['npc_enabled']          ?? 1),
                    ':npc_chance'  => min(50, max(1, (int)($data['npc_chance_percent']  ?? 5))),
                    ':warp_enabled'=> (int)($data['warp_risk_enabled']    ?? 1),
                    ':warp_risk'   => min(20, max(1, (int)($data['warp_risk_percent']   ?? 3))),
                    ':intercept'   => (int)($data['interception_enabled'] ?? 1),
                    ':poll_ms'     => max(1000, (int)($data['poll_interval_ms'] ?? 3000)),
                ]
            );
        } catch (Throwable $e) {
            error_log('[LiveFleetTracker] saveSettings() error: ' . $e->getMessage());
        }
    }

    // ── NPC Attacks ───────────────────────────────────────────────────────────

    public function createNpcAttack(int $fleetId, int $ownerId, int $galaxy, int $system, int $planet, int $lossPct): int
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return 0;
            }
            $now = defined('TIMESTAMP') ? TIMESTAMP : time();
            $db->insert(
                "INSERT INTO `{$this->tblNpc}`
                    (`fleet_id`,`owner_id`,`galaxy`,`system`,`planet`,`loss_percent`,`created_at`)
                 VALUES (:fleet,:owner,:g,:s,:p,:loss,:now);",
                [
                    ':fleet' => $fleetId,
                    ':owner' => $ownerId,
                    ':g'     => $galaxy,
                    ':s'     => $system,
                    ':p'     => $planet,
                    ':loss'  => $lossPct,
                    ':now'   => $now,
                ]
            );
            return (int)$db->lastInsertId();
        } catch (Throwable $e) {
            error_log('[LiveFleetTracker] createNpcAttack() error: ' . $e->getMessage());
            return 0;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function getUnnotifiedNpcAttacks(int $ownerId): array
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return [];
            }
            return $db->select(
                "SELECT * FROM `{$this->tblNpc}` WHERE `owner_id` = :owner AND `notified` = 0 ORDER BY `created_at` DESC LIMIT 20;",
                [':owner' => $ownerId]
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function markNpcNotified(int $ownerId): void
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return;
            }
            $db->update(
                "UPDATE `{$this->tblNpc}` SET `notified` = 1 WHERE `owner_id` = :owner AND `notified` = 0;",
                [':owner' => $ownerId]
            );
        } catch (Throwable $e) {
            error_log('[LiveFleetTracker] markNpcNotified() error: ' . $e->getMessage());
        }
    }

    // ── Warp Events ───────────────────────────────────────────────────────────

    public function createWarpEvent(int $fleetId, int $ownerId, int $delaySeconds): int
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return 0;
            }
            $now = defined('TIMESTAMP') ? TIMESTAMP : time();
            $db->insert(
                "INSERT INTO `{$this->tblWarp}` (`fleet_id`,`owner_id`,`delay_seconds`,`created_at`)
                 VALUES (:fleet,:owner,:delay,:now);",
                [':fleet' => $fleetId, ':owner' => $ownerId, ':delay' => $delaySeconds, ':now' => $now]
            );
            return (int)$db->lastInsertId();
        } catch (Throwable $e) {
            error_log('[LiveFleetTracker] createWarpEvent() error: ' . $e->getMessage());
            return 0;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function getUnnotifiedWarpEvents(int $ownerId): array
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return [];
            }
            return $db->select(
                "SELECT * FROM `{$this->tblWarp}` WHERE `owner_id` = :owner AND `notified` = 0 ORDER BY `created_at` DESC LIMIT 20;",
                [':owner' => $ownerId]
            ) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function markWarpNotified(int $ownerId): void
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return;
            }
            $db->update(
                "UPDATE `{$this->tblWarp}` SET `notified` = 1 WHERE `owner_id` = :owner AND `notified` = 0;",
                [':owner' => $ownerId]
            );
        } catch (Throwable $e) {
            error_log('[LiveFleetTracker] markWarpNotified() error: ' . $e->getMessage());
        }
    }

    // ── Interceptions ─────────────────────────────────────────────────────────

    public function createInterception(int $targetFleetId, int $interceptorId): int
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return 0;
            }
            $now = defined('TIMESTAMP') ? TIMESTAMP : time();
            // Only allow one pending interception per target fleet
            $existing = $db->selectSingle(
                "SELECT `id` FROM `{$this->tblIntercept}` WHERE `target_fleet_id` = :t AND `status` IN ('pending','launched');",
                [':t' => $targetFleetId],
                'id'
            );
            if ($existing !== null) {
                return 0;
            }
            $db->insert(
                "INSERT INTO `{$this->tblIntercept}` (`target_fleet_id`,`interceptor_id`,`created_at`)
                 VALUES (:t,:i,:now);",
                [':t' => $targetFleetId, ':i' => $interceptorId, ':now' => $now]
            );
            return (int)$db->lastInsertId();
        } catch (Throwable $e) {
            error_log('[LiveFleetTracker] createInterception() error: ' . $e->getMessage());
            return 0;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function getActiveInterceptions(): array
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return [];
            }
            $now = defined('TIMESTAMP') ? TIMESTAMP : time();
            return $db->select(
                "SELECT i.*, f.fleet_start_galaxy AS tgt_g, f.fleet_start_system AS tgt_s,
                        f.fleet_end_galaxy AS dst_g, f.fleet_end_system AS dst_s,
                        f.fleet_end_time AS tgt_end_time, f.fleet_owner AS tgt_owner_id,
                        u.username AS tgt_owner_name
                 FROM `{$this->tblIntercept}` i
                 LEFT JOIN %%FLEETS%% f ON f.fleet_id = i.target_fleet_id
                 LEFT JOIN %%USERS%%  u ON u.id = f.fleet_owner
                 WHERE i.`status` IN ('pending','launched')
                   AND (f.fleet_end_time IS NULL OR f.fleet_end_time > :now)
                 ORDER BY i.`created_at` DESC;",
                [':now' => $now]
            ) ?: [];
        } catch (Throwable $e) {
            error_log('[LiveFleetTracker] getActiveInterceptions() error: ' . $e->getMessage());
            return [];
        }
    }

    public function resolveInterception(int $id, string $status): void
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return;
            }
            $now = defined('TIMESTAMP') ? TIMESTAMP : time();
            $db->update(
                "UPDATE `{$this->tblIntercept}` SET `status` = :s, `resolved_at` = :now WHERE `id` = :id;",
                [':s' => $status, ':now' => $now, ':id' => $id]
            );
        } catch (Throwable $e) {
            error_log('[LiveFleetTracker] resolveInterception() error: ' . $e->getMessage());
        }
    }

    /** Check if a fleet already has been hit (NPC or Warp) this cron run – avoid double-hits */
    public function fleetAlreadyHitThisRun(int $fleetId, int $windowSecs = 300): bool
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return false;
            }
            $since = (defined('TIMESTAMP') ? TIMESTAMP : time()) - $windowSecs;
            $cntNpc = (int)($db->selectSingle(
                "SELECT COUNT(*) AS c FROM `{$this->tblNpc}` WHERE `fleet_id` = :f AND `created_at` >= :s;",
                [':f' => $fleetId, ':s' => $since], 'c'
            ) ?? 0);
            $cntWarp = (int)($db->selectSingle(
                "SELECT COUNT(*) AS c FROM `{$this->tblWarp}` WHERE `fleet_id` = :f AND `created_at` >= :s;",
                [':f' => $fleetId, ':s' => $since], 'c'
            ) ?? 0);
            return ($cntNpc + $cntWarp) > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
