-- Migration 15: Add combat engine tuning columns to config table

ALTER TABLE `%PREFIX%config` ADD COLUMN `combat_rand_variance` tinyint(3) unsigned NOT NULL DEFAULT 20;
ALTER TABLE `%PREFIX%config` ADD COLUMN `combat_crit_chance` tinyint(3) unsigned NOT NULL DEFAULT 5;
ALTER TABLE `%PREFIX%config` ADD COLUMN `combat_crit_mult` float NOT NULL DEFAULT 2.0;
ALTER TABLE `%PREFIX%config` ADD COLUMN `combat_morale_enabled` tinyint(1) unsigned NOT NULL DEFAULT 1;

UPDATE `%PREFIX%system` SET `dbVersion` = 15;
