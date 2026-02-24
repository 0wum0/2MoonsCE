<?php

declare(strict_types=1);

/**
 * sm-gameplay-test – SmartMoons Gameplay Test Plugin v1.1
 *
 * Building 900 (Advanced Metal Mine) is defined in install.sql:
 *   - Row in %%VARS%%            → pricelist, prodGrid, reslist picked up by VarsBuildCache
 *   - Rows in %%VARS_REQURIEMENTS%% → requirements
 *   - Columns in %%PLANETS%%     → smgt_advanced_mine, smgt_advanced_mine_porcent
 *
 * This file only registers the two runtime hooks that cannot come from the DB:
 *   1. game.resourceMap  – maps element ID 900 to the planet column name at runtime
 *   2. game.production   – adds a global +5% metal production bonus
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
