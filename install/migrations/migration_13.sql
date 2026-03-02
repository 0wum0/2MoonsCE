-- Migration 13: Add lockTime column to cronjobs table
ALTER TABLE `%PREFIX%cronjobs` ADD COLUMN IF NOT EXISTS `lockTime` int(11) DEFAULT NULL;
