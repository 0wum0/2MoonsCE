<?php

declare(strict_types=1);

/**
 * CoreQoLPack – Plugin Bootstrap
 *
 * Registers:
 *  - CSS via head_end
 *  - Toast container div via ingame.header.after
 *  - JS  via footer_end
 *
 * Features (all handled in qol.js):
 *  1) AJAX Form Intercept  – forms with class="ajax-form"
 *  2) Forum Autosave       – localStorage, 5s interval, per thread-id
 *  3) Live Resource Sync   – fetch every 10s, DOM-update only
 *  4) Toast Notifications  – minimal, no external framework
 *
 * The GameModuleInterface module (CoreQoLModule) is loaded automatically
 * by PluginManager via manifest["modules"].
 */

(static function (): void {
    $hm            = HookManager::get();
    $pluginWebBase = 'plugins/CoreQoLPack/assets';
    $cssUrl        = $pluginWebBase . '/css/qol.css';
    $jsUrl         = $pluginWebBase . '/js/qol.js';

    // ── Toast container – injected once after <body> opens ────────────────────
    $hm->addAction('ingame.header.after', static function (array $ctx) : string {
        if (!defined('MODE') || MODE !== 'INGAME') {
            return '';
        }
        return '<div id="qol-toast-container" class="qol-toast-container" aria-live="polite" aria-atomic="false"></div>' . "\n";
    }, 5);

    // ── CSS in <head> ─────────────────────────────────────────────────────────
    $hm->addAction('head_end', static function (array $ctx) use ($cssUrl): string {
        if (!defined('MODE') || MODE !== 'INGAME') {
            return '';
        }
        return '<link rel="stylesheet" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }, 30);

    // ── JS before </body> ─────────────────────────────────────────────────────
    $hm->addAction('footer_end', static function (array $ctx) use ($jsUrl): string {
        if (!defined('MODE') || MODE !== 'INGAME') {
            return '';
        }
        return '<script src="' . htmlspecialchars($jsUrl, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
    }, 30);
})();
