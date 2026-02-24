-- sm-relics uninstall.sql
-- Drops plugin-owned tables and removes the cronjob entry.
-- PLANETS columns (smr_*) are left in place — additive columns are harmless.

DROP TABLE IF EXISTS %%RELICS_USER%%;
DROP TABLE IF EXISTS %%RELICS_LOG%%;
DROP TABLE IF EXISTS %%RELICS_SETTINGS%%;

DELETE FROM %%CRONJOBS%% WHERE `name` = 'relics_tick';
