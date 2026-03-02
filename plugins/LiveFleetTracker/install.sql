-- LiveFleetTracker Plugin Tables
-- NPC pirate attack log
CREATE TABLE IF NOT EXISTS `%%PREFIX%%lft_npc_attacks` (
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
    KEY `idx_owner`   (`owner_id`),
    KEY `idx_fleet`   (`fleet_id`),
    KEY `idx_resolved`(`resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warp disruptions (delay events)
CREATE TABLE IF NOT EXISTS `%%PREFIX%%lft_warp_events` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `fleet_id`     INT UNSIGNED  NOT NULL,
    `owner_id`     INT UNSIGNED  NOT NULL,
    `delay_seconds`INT UNSIGNED  NOT NULL DEFAULT 0,
    `resolved`     TINYINT(1)    NOT NULL DEFAULT 0,
    `notified`     TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`   INT UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_fleet`   (`fleet_id`),
    KEY `idx_owner`   (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Interception requests
CREATE TABLE IF NOT EXISTS `%%PREFIX%%lft_interceptions` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `target_fleet_id` INT UNSIGNED  NOT NULL,
    `interceptor_id`  INT UNSIGNED  NOT NULL,
    `interceptor_fleet_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `status`          ENUM('pending','launched','resolved','failed') NOT NULL DEFAULT 'pending',
    `created_at`      INT UNSIGNED  NOT NULL DEFAULT 0,
    `resolved_at`     INT UNSIGNED  NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_target`      (`target_fleet_id`),
    KEY `idx_interceptor` (`interceptor_id`),
    KEY `idx_status`      (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plugin settings (single row)
CREATE TABLE IF NOT EXISTS `%%PREFIX%%lft_settings` (
    `id`                   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `enabled`              TINYINT(1)       NOT NULL DEFAULT 1,
    `npc_enabled`          TINYINT(1)       NOT NULL DEFAULT 1,
    `npc_chance_percent`   TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `warp_risk_enabled`    TINYINT(1)       NOT NULL DEFAULT 1,
    `warp_risk_percent`    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    `interception_enabled` TINYINT(1)       NOT NULL DEFAULT 1,
    `poll_interval_ms`     INT UNSIGNED     NOT NULL DEFAULT 3000,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `%%PREFIX%%lft_settings`
    (`id`,`enabled`,`npc_enabled`,`npc_chance_percent`,`warp_risk_enabled`,`warp_risk_percent`,`interception_enabled`,`poll_interval_ms`)
VALUES (1, 1, 1, 5, 1, 3, 1, 3000);
