CREATE TABLE IF NOT EXISTS `%PREFIX%forum_categories` (
  `id`          int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id`   int(11) unsigned DEFAULT NULL,
  `title`       varchar(255) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `icon`        varchar(20) NOT NULL DEFAULT '📁',
  `color`       varchar(20) NOT NULL DEFAULT '#38bdf8',
  `sort_order`  int(11) unsigned NOT NULL DEFAULT 0,
  `is_locked`   tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at`  int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `%PREFIX%forum_topics` (
  `id`             int(11) unsigned NOT NULL AUTO_INCREMENT,
  `category_id`    int(11) unsigned NOT NULL,
  `user_id`        int(11) unsigned NOT NULL,
  `title`          varchar(255) NOT NULL DEFAULT '',
  `views`          int(11) unsigned NOT NULL DEFAULT 0,
  `is_sticky`      tinyint(1) unsigned NOT NULL DEFAULT 0,
  `is_locked`      tinyint(1) unsigned NOT NULL DEFAULT 0,
  `is_deleted`     tinyint(1) unsigned NOT NULL DEFAULT 0,
  `last_post_time` int(11) unsigned NOT NULL DEFAULT 0,
  `created_at`     int(11) unsigned NOT NULL DEFAULT 0,
  `updated_at`     int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `user_id` (`user_id`),
  KEY `last_post_time` (`last_post_time`),
  KEY `is_sticky` (`is_sticky`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `%PREFIX%forum_posts` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
  `topic_id`   int(11) unsigned NOT NULL,
  `user_id`    int(11) unsigned NOT NULL,
  `content`    text NOT NULL,
  `like_count` int(11) unsigned NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  `updated_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `topic_id` (`topic_id`),
  KEY `user_id` (`user_id`),
  KEY `is_deleted` (`is_deleted`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `%PREFIX%forum_post_likes` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id`    int(11) unsigned NOT NULL,
  `user_id`    int(11) unsigned NOT NULL,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_user` (`post_id`, `user_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `%PREFIX%forum_mentions` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
  `post_id`    int(11) unsigned NOT NULL,
  `user_id`    int(11) unsigned NOT NULL,
  `is_read`    tinyint(1) unsigned NOT NULL DEFAULT 0,
  `created_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `%PREFIX%system` SET `dbVersion` = 5;
