-- Migration 16: Bot-Setting Alliance & ACS configuration columns

-- Alliance settings
ALTER TABLE `%PREFIX%bot_setting`
  ADD COLUMN IF NOT EXISTS `can_alliance`          tinyint(1)   NOT NULL DEFAULT 1    COMMENT '1=Bots treten/gruenden Allianzen',
  ADD COLUMN IF NOT EXISTS `alliance_max_count`    int(11)      NOT NULL DEFAULT 1    COMMENT 'Max Anzahl Bot-Allianzen pro Server (0=unbegrenzt)',
  ADD COLUMN IF NOT EXISTS `alliance_max_members`  int(11)      NOT NULL DEFAULT 50   COMMENT 'Max Mitglieder pro Bot-Allianz',
  ADD COLUMN IF NOT EXISTS `alliance_name_pool`    text         DEFAULT NULL          COMMENT 'JSON-Array mit moeglichen Allianz-Namen, NULL=zufaellig generiert',
  ADD COLUMN IF NOT EXISTS `alliance_tag_pool`     text         DEFAULT NULL          COMMENT 'JSON-Array mit moeglichen Allianz-Tags, NULL=zufaellig generiert',
  ADD COLUMN IF NOT EXISTS `alliance_internal_tag` varchar(32)  NOT NULL DEFAULT ''   COMMENT 'Internes Erkennungszeichen (wird NICHT angezeigt), leer=deaktiviert';

-- ACS settings
ALTER TABLE `%PREFIX%bot_setting`
  ADD COLUMN IF NOT EXISTS `can_acs`               tinyint(1)   NOT NULL DEFAULT 1    COMMENT '1=Bots koennen koordinierte ACS-Angriffe starten',
  ADD COLUMN IF NOT EXISTS `acs_max_size`          int(11)      NOT NULL DEFAULT 3    COMMENT 'Max Bots pro ACS-Gruppe',
  ADD COLUMN IF NOT EXISTS `acs_chance_percent`    int(11)      NOT NULL DEFAULT 30   COMMENT 'Wahrscheinlichkeit (%) pro Tick dass Bot ACS versucht',
  ADD COLUMN IF NOT EXISTS `acs_min_loot`          bigint(20)   NOT NULL DEFAULT 50000 COMMENT 'Mindest-Ressourcen beim Ziel fuer ACS-Angriff';

-- Defense & save settings
ALTER TABLE `%PREFIX%bot_setting`
  ADD COLUMN IF NOT EXISTS `can_defense`           tinyint(1)   NOT NULL DEFAULT 1    COMMENT '1=Bots bauen Verteidigungsanlagen',
  ADD COLUMN IF NOT EXISTS `can_save_fleet`        tinyint(1)   NOT NULL DEFAULT 1    COMMENT '1=Bots safen Flotte bei eingehendem Angriff',
  ADD COLUMN IF NOT EXISTS `bot_min_fleet_slots`   int(11)      NOT NULL DEFAULT 5    COMMENT 'Mindest-Flotten-Slots fuer Bots (kompensiert computer_tech=0)',
  ADD COLUMN IF NOT EXISTS `spy_probes`            int(11)      NOT NULL DEFAULT 3    COMMENT 'Anzahl Spionage-Sonden pro Spio-Mission',
  ADD COLUMN IF NOT EXISTS `raid_min_loot`         bigint(20)   NOT NULL DEFAULT 10000 COMMENT 'Mindest-Loot fuer Solo-Raid';

-- Update defaults in existing row
UPDATE `%PREFIX%bot_setting` SET
  `can_alliance`       = 1,
  `alliance_max_count` = 1,
  `alliance_max_members` = 50,
  `can_acs`            = 1,
  `acs_max_size`       = 3,
  `acs_chance_percent` = 30,
  `can_defense`        = 1,
  `can_save_fleet`     = 1,
  `bot_min_fleet_slots` = 5,
  `spy_probes`         = 3,
  `raid_min_loot`      = 10000
WHERE 1;

UPDATE `%PREFIX%system` SET `dbVersion` = 16;
