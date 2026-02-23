-- Migration 7: Forum subscriptions + topic unreads for notification system

CREATE TABLE IF NOT EXISTS `%PREFIX%forum_subscriptions` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id`   int(11) unsigned NOT NULL,
  `user_id`    int(11) unsigned NOT NULL,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `%PREFIX%forum_topic_unreads` (
  `id`               int(11) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id`         int(11) unsigned NOT NULL,
  `user_id`          int(11) unsigned NOT NULL,
  `last_post_id`     int(11) unsigned NOT NULL DEFAULT 0,
  `updated_at`       int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_user` (`topic_id`, `user_id`),
  KEY `user_id` (`user_id`),
  KEY `topic_id` (`topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add by_user_id to forum_mentions if not already present (idempotent via IF NOT EXISTS workaround)
ALTER TABLE `%PREFIX%forum_mentions`
  ADD COLUMN IF NOT EXISTS `by_user_id` int(11) unsigned NOT NULL DEFAULT 0;

UPDATE `%PREFIX%system` SET `dbVersion` = 7;
