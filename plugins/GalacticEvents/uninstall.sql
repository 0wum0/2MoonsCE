-- GalacticEvents Plugin – uninstall.sql
-- Removes all plugin-owned tables and the cronjob entry.

DROP TABLE IF EXISTS %%GALACTIC_EVENTS%%;
DROP TABLE IF EXISTS %%GALACTIC_EVENTS_SETTINGS%%;

DELETE FROM %%CRONJOBS%% WHERE class = 'GalacticEventsCronjob';
