<?php

declare(strict_types=1);

/**
 * GalacticEvents – Plugin Bootstrap
 *
 * Registers:
 *  - Twig namespace  @galactic_events/…
 *  - Admin route     admin.php?page=plugin_galactic_events
 *  - Game AJAX route game.php?page=galactic_events_api
 *  - Cronjob class   GalacticEventsCronjob
 *  - CSS/JS asset hooks (head_end / body_end)
 *
 * The GameModuleInterface module (GalacticEventsModule) is loaded automatically
 * by PluginManager via manifest["modules"] – no manual register call needed here.
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
