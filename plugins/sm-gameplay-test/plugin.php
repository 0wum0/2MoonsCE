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

// ── 0. Deploy building image to all theme gebaeude/ folders ──────────────────
// Copies assets/900.gif into every styles/theme/*/gebaeude/ directory so the
// standard {dpath}gebaeude/900.gif path resolves correctly in all templates.
(static function(): void {
    $src = ROOT_PATH . 'plugins/sm-gameplay-test/assets/900.gif';
    if (!file_exists($src)) {
        return;
    }
    $themeBase = ROOT_PATH . 'styles/theme/';
    if (!is_dir($themeBase)) {
        return;
    }
    foreach (scandir($themeBase) as $theme) {
        if ($theme === '.' || $theme === '..') {
            continue;
        }
        $dest = $themeBase . $theme . '/gebaeude/900.gif';
        if (!file_exists($dest)) {
            @copy($src, $dest);
        }
    }
})();

// ── 1. game.resourceMap – map element 900 to its planet column name ───────────
// VarsBuildCache reads $resource[] from %%VARS%%.name, but the base resource IDs
// (901–921) are set in vars.php. Element 900's name comes from the DB cache, so
// this hook is only needed if the cache was built before the plugin was installed.
// It is safe to always register it as a guard.
HookManager::get()->addFilter('game.resourceMap', function(array $resource): array {
    if (!isset($resource[900])) {
        $resource[900] = 'smgt_advanced_mine';
    }
    return $resource;
}, 10);

// ── 2. Language injection – building name into $LNG['tech'] ──────────────────
// $LNG is available at plugin load time (plugins load after language init).
if (isset($GLOBALS['LNG']) && $GLOBALS['LNG'] instanceof Language) {
    $GLOBALS['LNG']->addData(['tech' => [
        900 => PluginManager::lang('sm-gameplay-test', 'building_name'),
    ]]);
}

// ── 3. game.production – global +5% metal production bonus ───────────────────
// $temp[901]['plus'] is the summed raw metal production before multipliers.
HookManager::get()->addFilter('game.production', function(array $temp, array $ctx): array {
    if (isset($temp[901]['plus']) && $temp[901]['plus'] > 0) {
        $temp[901]['plus'] *= 1.05;
    }
    return $temp;
}, 10);
