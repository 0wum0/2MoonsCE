-- Migration 10: SmartChat – global/alliance chat system
-- CHAT_MES = {prefix}chat_messages, CHAT_BAN = {prefix}chat_bans (see dbtables.php)

CREATE TABLE IF NOT EXISTS `%PREFIX%chat_messages` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `channel`     ENUM('global','alliance') NOT NULL DEFAULT 'global',
  `alliance_id` INT UNSIGNED  NOT NULL DEFAULT 0,
  `user_id`     INT UNSIGNED  NOT NULL,
  `username`    VARCHAR(64)   NOT NULL,
  `message`     TEXT          NOT NULL,
  `created_at`  INT UNSIGNED  NOT NULL,
  `deleted_at`  INT UNSIGNED  NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_channel_alliance` (`channel`, `alliance_id`, `deleted_at`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `%PREFIX%chat_bans` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `user_id`     INT UNSIGNED  NOT NULL,
  `banned_by`   INT UNSIGNED  NOT NULL,
  `reason`      VARCHAR(255)  NOT NULL DEFAULT '',
  `created_at`  INT UNSIGNED  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `%PREFIX%system` SET `dbVersion` = 10;
