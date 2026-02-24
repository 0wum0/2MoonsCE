-- Migration 9: Plugin System v1 – global plugins table

CREATE TABLE `%PREFIX%plugins` (
  `id`           VARCHAR(100)  NOT NULL,
  `name`         VARCHAR(255)  NOT NULL,
  `version`      VARCHAR(20)   NOT NULL,
  `type`         VARCHAR(20)   NOT NULL DEFAULT 'game',
  `is_active`    TINYINT(1)    NOT NULL DEFAULT 0,
  `installed_at` INT           NOT NULL DEFAULT 0,
  `updated_at`   INT           NOT NULL DEFAULT 0,
  `config_json`  LONGTEXT      NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `%PREFIX%system` SET `dbVersion` = 9;
