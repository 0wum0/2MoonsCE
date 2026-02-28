<?php

declare(strict_types=1);

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

// ── 0. Load shared DB helper ──────────────────────────────────────────────────
require_once __DIR__ . '/lib/GalacticEventsDb.php';

// Ensure plugin tables exist (idempotent CREATE TABLE IF NOT EXISTS).
// Wrapped in try/catch so a DB error here never crashes the core page load.
try {
    GalacticEventsDb::get()->ensureTables();
} catch (Throwable $e) {
    error_log('[GalacticEvents] ensureTables() failed in bootstrap: ' . $e->getMessage());
}

$pm = PluginManager::get();

// ── 0b. Read plugin config (stored via Plugin Settings UI) ────────────────────
define('GE_CFG_INTERVAL_MIN',   (int)    $pm->getConfig('galactic_events', 'event_interval_min',   60));
define('GE_CFG_INTERVAL_MAX',   (int)    $pm->getConfig('galactic_events', 'event_interval_max',  240));
define('GE_CFG_DURATION_MIN',   (int)    $pm->getConfig('galactic_events', 'event_duration_min',   30));
define('GE_CFG_DURATION_MAX',   (int)    $pm->getConfig('galactic_events', 'event_duration_max',  120));
define('GE_CFG_MAX_BONUS',      (int)    $pm->getConfig('galactic_events', 'max_bonus_percent',    50));
define('GE_CFG_ALLOW_MALUS',    (bool)   $pm->getConfig('galactic_events', 'allow_malus',         true));
define('GE_CFG_NOTIFY',         (bool)   $pm->getConfig('galactic_events', 'notify_players',      true));
define('GE_CFG_EVENT_TYPE',     (string) $pm->getConfig('galactic_events', 'event_type',         'all'));

// ── 1. Twig namespace ─────────────────────────────────────────────────────────
$pm->registerTwigNamespace('galactic_events', 'views');

// ── 2. Admin route ────────────────────────────────────────────────────────────
$pm->registerAdminRoute(
    'galactic_events',
    'plugin_galactic_events',
    'admin/GalacticEventsAdminController.php',
    'ShowGalacticEventsAdminPage'
);

// ── 3. Game AJAX / status route ───────────────────────────────────────────────
$pm->registerPageRoute(
    'galactic_events',
    'galactic_events_api',
    'game/GalacticEventsGameController.php',
    'GalacticEventsGameController'
);

// ── 4. Plugin cronjob class ───────────────────────────────────────────────────
$pm->registerCronjob(
    'galactic_events',
    'GalacticEventsCronjob',
    'cron/GalacticEventsCronjob.php'
);

// ── 5. Inject CSS + JS into every ingame page ─────────────────────────────────
// Use head_end for the stylesheet and body_end for the script so assets load
// only when the plugin is active (plugin.php is only included when active).
(static function (): void {
    $base = defined('ROOT_PATH')
        ? rtrim(str_replace('\\', '/', ROOT_PATH), '/')
        : '';

    // Derive a web-relative URL from the absolute plugin path.
    // ROOT_PATH is the filesystem root of the web app, so stripping it gives
    // the path relative to the document root.
    $pluginWebBase = 'plugins/GalacticEvents/assets';

    $cssUrl = $pluginWebBase . '/css/events.css';
    $jsUrl  = $pluginWebBase . '/js/events.js';

    $hm = HookManager::get();

    // CSS in <head>
    $hm->addAction('head_end', static function (array $ctx) use ($cssUrl): string {
        if (!defined('MODE') || MODE !== 'INGAME') {
            return '';
        }
        return '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }, 20);

    // JS before </body> — hook is footer_end in main.footer.twig
    $hm->addAction('footer_end', static function (array $ctx) use ($jsUrl): string {
        if (!defined('MODE') || MODE !== 'INGAME') {
            return '';
        }
        return '<script src="' . htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    }, 20);
})();
