-- Migration 17: Convert news table to utf8mb4 to support emoji and full Unicode in title/text

ALTER TABLE `%PREFIX%news`
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `%PREFIX%news`
  MODIFY `title` varchar(255)  NOT NULL,
  MODIFY `text`  mediumtext    NOT NULL;

UPDATE `%PREFIX%system` SET `dbVersion` = 17;
