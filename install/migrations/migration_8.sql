-- Migration 8: Convert alliance table to utf8mb4 to support full Unicode (emojis, etc.)

ALTER TABLE `%PREFIX%alliance`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Ensure text columns are large enough and use utf8mb4
ALTER TABLE `%PREFIX%alliance`
  MODIFY `ally_name`        varchar(50)     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  MODIFY `ally_tag`         varchar(20)     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  MODIFY `ally_description` mediumtext      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  MODIFY `ally_text`        mediumtext      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  MODIFY `ally_request`     mediumtext      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  MODIFY `ally_web`         varchar(255)    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  MODIFY `ally_image`       varchar(255)    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  MODIFY `ally_owner_range` varchar(32)     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  MODIFY `ally_events`      varchar(55)     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';

UPDATE `%PREFIX%system` SET `dbVersion` = 8;
