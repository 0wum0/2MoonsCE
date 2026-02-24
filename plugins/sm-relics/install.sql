-- sm-relics install.sql
-- All plugin-owned tables use %%TABLE%% placeholders.
-- RELICS_USER, RELICS_LOG, RELICS_SETTINGS are registered in dbtables.php
-- by the plugin engine extension (see includes/dbtables.php comment below).
--
-- NOTE: Because plugin table names must be in dbtables.php for %%ALIAS%% to work,
-- this install.sql uses the %%PLANETS%% and %%CRONJOBS%% aliases (already registered)
-- and creates plugin tables with explicit names using the same prefix convention.
-- The plugin's PHP code accesses its tables via Database::get()->query() with
-- the full prefixed name obtained from DB_PREFIX constant at runtime.
-- We use %%PLANETS%% and %%CRONJOBS%% here since those ARE registered.

-- User relic data (one row per user)
CREATE TABLE IF NOT EXISTS %%RELICS_USER%% (
    `user_id`            INT(11)      NOT NULL,
    `relic_points`       INT(11)      NOT NULL DEFAULT 0,
    `doctrine`           VARCHAR(32)  NULL     DEFAULT NULL,
    `doctrine_changed_at` INT(11)     NOT NULL DEFAULT 0,
    `updated_at`         INT(11)      NOT NULL DEFAULT 0,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relic point change log
CREATE TABLE IF NOT EXISTS %%RELICS_LOG%% (
    `id`         INT(11)     NOT NULL AUTO_INCREMENT,
    `user_id`    INT(11)     NOT NULL,
    `delta`      INT(11)     NOT NULL DEFAULT 0,
    `reason`     VARCHAR(64) NOT NULL DEFAULT '',
    `created_at` INT(11)     NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plugin settings key/value store
CREATE TABLE IF NOT EXISTS %%RELICS_SETTINGS%% (
    `k` VARCHAR(64) NOT NULL,
    `v` TEXT        NOT NULL DEFAULT '',
    PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT IGNORE INTO %%RELICS_SETTINGS%% (`k`, `v`) VALUES
    ('tick_interval',          '*/10 * * * *'),
    ('base_points_per_tick',   '5'),
    ('archive_multiplier',     '2'),
    ('doctrine_build_bonus',   '10'),
    ('doctrine_research_bonus','10'),
    ('doctrine_prod_bonus',    '5'),
    ('doctrine_combat_bonus',  '5'),
    ('debug_mode',             '0'),
    ('enabled',                '1');

-- Register the RelicsTick cronjob (every 10 minutes)
INSERT IGNORE INTO %%CRONJOBS%%
    (`name`, `class`, `min`, `hours`, `dom`, `month`, `dow`, `isActive`, `nextTime`, `lock`, `lockTime`)
VALUES
    ('relics_tick', 'RelicsTick', '*/10', '*', '*', '*', '*', 1, 0, NULL, NULL);

-- Add relic building columns to PLANETS table (safe: IF NOT EXISTS)
ALTER TABLE %%PLANETS%%
    ADD COLUMN IF NOT EXISTS `smr_relic_archive`    TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `smr_quantum_reactor`  TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `smr_spacetime_lab`    TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `smr_military_academy` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `smr_economic_center`  TINYINT(3) UNSIGNED NOT NULL DEFAULT 0;
