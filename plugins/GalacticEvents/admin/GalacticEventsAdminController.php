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
require_once __DIR__ . '/../lib/GalacticEventsDb.php';

/**
 * GalacticEventsAdminController
 *
 * Handles the admin settings page for the GalacticEvents plugin.
 * Registered via PluginManager::registerAdminRoute() as a free function.
 *
 * Route: admin.php?page=plugin_galactic_events
 *
 * Supports:
 *   GET  → display settings form
 *   POST → save settings, redirect back
 */

/**
 * Entry point dispatched by PluginManager::dispatchAdminRoute().
 * Must be a free function matching the registered function name.
 */
function ShowGalacticEventsAdminPage(): void
{
    global $USER;

    // ── Auth guard ────────────────────────────────────────────────────────────
    if (empty($USER['authlevel']) || (int)$USER['authlevel'] < 1) {
        header('Location: admin.php');
        exit;
    }

    $db       = GalacticEventsDb::get();
    $settings = $db->getSettings();
    $errors   = [];
    $success  = false;

    // ── POST: save settings ───────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = [
            'enabled'                => (int)(isset($_POST['enabled']) ? 1 : 0),
            'check_interval'         => (int)($_POST['check_interval'] ?? 30),
            'trigger_chance_percent' => (int)($_POST['trigger_chance_percent'] ?? 20),
            'event_duration'         => (int)($_POST['event_duration'] ?? 60),
            'effect_type'            => trim((string)($_POST['effect_type'] ?? 'metal_production')),
            'effect_value'           => (float)str_replace(',', '.', (string)($_POST['effect_value'] ?? '10')),
        ];

        $validTypes = [
            'metal_production', 'crystal_production', 'deuterium_production',
            'energy_output', 'build_time', 'research_time',
        ];

        if ($raw['check_interval'] < 1 || $raw['check_interval'] > 10080) {
            $errors[] = 'Check-Intervall muss zwischen 1 und 10080 Minuten liegen.';
        }
        if ($raw['trigger_chance_percent'] < 1 || $raw['trigger_chance_percent'] > 100) {
            $errors[] = 'Wahrscheinlichkeit muss zwischen 1 und 100 liegen.';
        }
        if ($raw['event_duration'] < 1 || $raw['event_duration'] > 10080) {
            $errors[] = 'Event-Dauer muss zwischen 1 und 10080 Minuten liegen.';
        }
        if (!in_array($raw['effect_type'], $validTypes, true)) {
            $errors[] = 'Ungültiger Effekt-Typ.';
        }
        if ($raw['effect_value'] < -100.0 || $raw['effect_value'] > 500.0) {
            $errors[] = 'Effekt-Wert muss zwischen -100 und 500 liegen.';
        }

        if (empty($errors)) {
            $db->saveSettings($raw);
            $settings = $db->getSettings();
            $success  = true;
        }
    }

    // ── Twig render via core template class ──────────────────────────────────
    $recentEvents = $db->getRecentEvents(10);
    $now          = defined('TIMESTAMP') ? TIMESTAMP : time();
    $activeEvent  = $db->getActiveEvent();

    $effectLabels = [
        'metal_production'     => 'Metall-Produktion (%)',
        'crystal_production'   => 'Kristall-Produktion (%)',
        'deuterium_production' => 'Deuterium-Produktion (%)',
        'energy_output'        => 'Energie-Ausgabe (%)',
        'build_time'           => 'Bauzeit (%)',
        'research_time'        => 'Forschungszeit (%)',
    ];

    try {
        $template = new template();
        $template->assign_vars([
            'settings'     => $settings,
            'errors'       => $errors,
            'success'      => $success,
            'recentEvents' => $recentEvents,
            'activeEvent'  => $activeEvent,
            'effectLabels' => $effectLabels,
            'now'          => $now,
        ]);
        $template->show('@galactic_events/admin/settings.twig');

    } catch (Throwable $e) {
        error_log('[GalacticEventsAdmin] render error: ' . $e->getMessage());
        echo '<div style="color:red;padding:20px;">GalacticEvents Admin: Render-Fehler – '
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
