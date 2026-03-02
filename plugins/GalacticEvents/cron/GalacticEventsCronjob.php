<?php
/**
 *	SmartMoons / 2Moons Community Edition (2MoonsCE)
 * 
 *	Based on the original 2Moons project:
 *	
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * Modernization, PHP 8.3/8.4 compatibility, Twig Migration (Smarty removed)
 * Refactoring and feature extensions:
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 * @link https://github.com/0wum0/2MoonsCE
 * @eMail info.browsergame@gmail.com
 * 
 * Licensed under the MIT License.
 * See LICENSE for details.
 * @visit http://makeit.uno/
 */
// The cronjob is loaded directly by Cronjob::execute() without plugin.php,
// so the shared DB helper must be required explicitly here.
require_once __DIR__ . '/../lib/GalacticEventsDb.php';
require_once ROOT_PATH . 'includes/classes/cronjob/CronjobTask.interface.php';

/**
 * GalacticEventsCronjob – Plugin-internal cron task.
 *
 * Registered via PluginManager::registerCronjob() in plugin.php.
 * Runs every 5 minutes (cron expression: * /5 * * * *) as inserted in install.sql.
 *
 * Logic:
 *  1. Load settings; abort if disabled.
 *  2. Check whether check_interval minutes have elapsed since last_check.
 *  3. Roll random 1-100 against trigger_chance_percent.
 *  4. If triggered: create a new event row.
 *  5. Always: expire any events whose active_until has passed (clean-up).
 */
class GalacticEventsCronjob implements CronjobTask
{
    public function run(): void
    {
        try {
            $db       = GalacticEventsDb::get();
            $settings = $db->getSettings();

            if (empty($settings)) {
                error_log('[GalacticEventsCronjob] No settings row found – skipping.');
                return;
            }

            if (!(bool)(int)($settings['enabled'] ?? 0)) {
                return;
            }

            $now           = defined('TIMESTAMP') ? TIMESTAMP : time();
            $lastCheck     = (int)($settings['last_check'] ?? 0);
            $intervalSecs  = max(1, (int)($settings['check_interval'] ?? 30)) * 60;

            if (($now - $lastCheck) < $intervalSecs) {
                return;
            }

            // Update last_check immediately to prevent concurrent double-trigger
            $db->updateLastCheck($now);

            // Random trigger roll
            $chance = min(100, max(1, (int)($settings['trigger_chance_percent'] ?? 20)));
            $roll   = random_int(1, 100);

            if ($roll <= $chance) {
                $eventId = $db->createEvent($settings);
                if ($eventId > 0) {
                    error_log('[GalacticEventsCronjob] New event created: id=' . $eventId
                        . ' type=' . ($settings['effect_type'] ?? '?')
                        . ' value=' . ($settings['effect_value'] ?? '?')
                        . ' duration=' . ($settings['event_duration'] ?? '?') . 'min');
                }
            }

        } catch (Throwable $e) {
            error_log('[GalacticEventsCronjob] run() error: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
