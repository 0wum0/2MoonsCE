CREATE TABLE IF NOT EXISTS `%PREFIX%forum_reports` (
  `id`             int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id`        int(11) unsigned NOT NULL,
  `reporter_id`    int(11) unsigned NOT NULL,
  `reason`         varchar(500) NOT NULL DEFAULT '',
  `status`         enum('open','closed') NOT NULL DEFAULT 'open',
  `created_at`     int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `reporter_id` (`reporter_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `%PREFIX%system` SET `dbVersion` = 6;
