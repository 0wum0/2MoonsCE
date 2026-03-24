-- Migration 18: Create events table for in-game events displayed on overview page

CREATE TABLE IF NOT EXISTS `%PREFIX%events` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `title`       varchar(255) NOT NULL,
  `text`        mediumtext   NOT NULL,
  `start_time`  int(11)      NOT NULL DEFAULT 0,
  `end_time`    int(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_end_time` (`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `%PREFIX%system` SET `dbVersion` = 18;
