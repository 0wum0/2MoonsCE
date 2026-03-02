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
