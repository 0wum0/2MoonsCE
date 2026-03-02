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

require_once __DIR__ . '/lib/GalaxyMarkerDb.php';
require_once __DIR__ . '/lib/GalaxyMarkerRegistry.php';

try {
    GalaxyMarkerDb::get()->ensureTables();
} catch (Throwable $e) {
    error_log('[GalaxyMarkerAPI] ensureTables() failed in bootstrap: ' . $e->getMessage());
}

(static function (): void {
    $hm             = HookManager::get();
    $pluginWebBase  = 'plugins/GalaxyMarkerAPI/assets';
    $cssUrl         = $pluginWebBase . '/css/markers.css';
    $jsUrl          = $pluginWebBase . '/js/markers.js';

    // ── galaxy.registerMarker action ─────────────────────────────────────────
    // Other plugins call:
    //   HookManager::get()->doAction('galaxy.registerMarker', $markerData);
    // $markerData keys: galaxy, system, position, type, icon, color, tooltip, expires_at
    $hm->addAction('galaxy.registerMarker', static function (array $markerData): void {
        GalaxyMarkerRegistry::get()->push($markerData);
    }, 10);

    // ── galaxy.renderOverlay filter ──────────────────────────────────────────
    // Returns JSON string of all markers for the current request.
    // Galaxy map template calls: applyFilters('galaxy.renderOverlay', '[]')
    $hm->addFilter('galaxy.renderOverlay', static function (string $json): string {
        try {
            $markers = GalaxyMarkerDb::get()->getActiveMarkers();
            // Merge runtime-registered markers (pushed via galaxy.registerMarker)
            $runtime = GalaxyMarkerRegistry::get()->all();
            $all     = array_merge($markers, $runtime);
            return (string) json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            error_log('[GalaxyMarkerAPI] galaxy.renderOverlay error: ' . $e->getMessage());
            return '[]';
        }
    }, 10);

    // ── CSS in <head> ─────────────────────────────────────────────────────────
    $hm->addAction('head_end', static function (array $ctx) use ($cssUrl): string {
        if (!defined('MODE') || MODE !== 'INGAME') {
            return '';
        }
        return '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }, 25);

    // ── JS before </body> ─────────────────────────────────────────────────────
    $hm->addAction('footer_end', static function (array $ctx) use ($jsUrl): string {
        if (!defined('MODE') || MODE !== 'INGAME') {
            return '';
        }
        // Only inject on galaxy map page
        $page = (string)(isset($_GET['page']) ? $_GET['page'] : '');
        if ($page !== 'galaxyMap') {
            return '';
        }
        $overlayJson = HookManager::get()->applyFilters('galaxy.renderOverlay', '[]');
        $escaped     = htmlspecialchars($overlayJson, ENT_QUOTES, 'UTF-8');
        return '<script>window.GalaxyMarkerData=' . $overlayJson . ';</script>' . "\n"
             . '<script src="' . htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    }, 25);
})();
