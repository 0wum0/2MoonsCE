-- Migration 15: Add combat engine tuning columns + bot_personalities table

ALTER TABLE `%PREFIX%config` ADD COLUMN `combat_rand_variance` tinyint(3) unsigned NOT NULL DEFAULT 20;
ALTER TABLE `%PREFIX%config` ADD COLUMN `combat_crit_chance` tinyint(3) unsigned NOT NULL DEFAULT 5;
ALTER TABLE `%PREFIX%config` ADD COLUMN `combat_crit_mult` float NOT NULL DEFAULT 2.0;
ALTER TABLE `%PREFIX%config` ADD COLUMN `combat_morale_enabled` tinyint(1) unsigned NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS `%PREFIX%bot_personalities` (
  `id`                 int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name`               varchar(64) NOT NULL DEFAULT 'balanced',
  `allow_raids`        tinyint(1) unsigned NOT NULL DEFAULT 0,
  `allow_expeditions`  tinyint(1) unsigned NOT NULL DEFAULT 1,
  `aggression`         float NOT NULL DEFAULT 0.3,
  `priority_mines`     float NOT NULL DEFAULT 0.7,
  `priority_energy`    float NOT NULL DEFAULT 0.5,
  `priority_storage`   float NOT NULL DEFAULT 0.3,
  `priority_research`  float NOT NULL DEFAULT 0.4,
  `priority_fleet`     float NOT NULL DEFAULT 0.3,
  `priority_defense`   float NOT NULL DEFAULT 0.2,
  `save_resources`     tinyint(1) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `%PREFIX%bot_personalities` (`name`, `allow_raids`, `allow_expeditions`, `aggression`, `priority_mines`, `priority_energy`, `priority_storage`, `priority_research`, `priority_fleet`, `priority_defense`, `save_resources`) VALUES
('balanced',   0, 1, 0.3,  0.7,  0.5, 0.3, 0.4, 0.3, 0.2, 0),
('farmer',     0, 1, 0.2,  0.9,  0.6, 0.4, 0.3, 0.2, 0.1, 0),
('raider',     1, 0, 0.8,  0.5,  0.4, 0.2, 0.3, 0.8, 0.3, 0),
('researcher', 0, 1, 0.2,  0.5,  0.4, 0.2, 0.9, 0.2, 0.2, 0),
('miner',      0, 0, 0.1,  0.95, 0.7, 0.5, 0.3, 0.1, 0.1, 1),
('turtle',     0, 0, 0.1,  0.6,  0.5, 0.4, 0.3, 0.2, 0.9, 1);

ALTER TABLE `%PREFIX%bots` ADD COLUMN IF NOT EXISTS `personality` varchar(64) NOT NULL DEFAULT 'balanced';

UPDATE `%PREFIX%system` SET `dbVersion` = 15;
