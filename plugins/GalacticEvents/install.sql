-- GalacticEvents Plugin – install.sql
-- Only uses %%CRONJOBS%% which is already registered in core dbtables.php.
-- Plugin-owned tables (galactic_events, galactic_events_settings) are created
-- via PHP in plugin.php using DB_PREFIX directly (see GalacticEventsDb::ensureTables()).

INSERT IGNORE INTO %%CRONJOBS%%
    (name, class, min, hours, dom, month, dow, isActive, `lock`, lockTime, nextTime)
VALUES
    ('Galactic Events Tick', 'GalacticEventsCronjob', '*/5', '*', '*', '*', '*', 1, NULL, NULL, 0);
