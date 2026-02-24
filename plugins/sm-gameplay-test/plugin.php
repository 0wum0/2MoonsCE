<?php

declare(strict_types=1);

/**
 * sm-gameplay-test – SmartMoons Gameplay Test Plugin v1.1
 *
 * Demonstrates Plugin System v1.1 game hooks:
 *   - Adds a new building (ID 900) via game.resourceMap, game.pricelist,
 *     game.requirements, game.reslist, game.prodGrid
 *   - Increases metal production by 5% via game.production
 *
 * NO core files are modified. NO database tables are added.
 * The building is purely in-memory via the cache hooks.
 */

// ── 1. game.resourceMap – register the element name ──────────────────────────
HookManager::get()->addFilter('game.resourceMap', function(array $resource): array {
    $resource[900] = 'smgt_advanced_mine';
    return $resource;
}, 10);

// ── 2. game.pricelist – register costs, factor, stats ────────────────────────
HookManager::get()->addFilter('game.pricelist', function(array $pricelist): array {
    $pricelist[900] = [
        'cost'    => [
            901 => 80000,   // metal
            902 => 40000,   // crystal
            903 => 20000,   // deuterium
            911 => 0,
            921 => 0,
        ],
        'factor'        => 1.5,
        'max'           => 0,       // 0 = no level cap
        'consumption'   => 0,
        'consumption2'  => 0,
        'speed'         => 0,
        'speed2'        => 0,
        'capacity'      => 0,
        'tech'          => 0,
        'time'          => 0,
        'bonus'         => [
            'Attack'            => [0, 0],
            'Defensive'         => [0, 0],
            'Shield'            => [0, 0],
            'BuildTime'         => [0, 0],
            'ResearchTime'      => [0, 0],
            'ShipTime'          => [0, 0],
            'DefensiveTime'     => [0, 0],
            'Resource'          => [0, 0],
            'Energy'            => [0, 0],
            'ResourceStorage'   => [0, 0],
            'ShipStorage'       => [0, 0],
            'FlyTime'           => [0, 0],
            'FleetSlots'        => [0, 0],
            'Planets'           => [0, 0],
            'SpyPower'          => [0, 0],
            'Expedition'        => [0, 0],
            'GateCoolTime'      => [0, 0],
            'MoreFound'         => [0, 0],
        ],
    ];
    return $pricelist;
}, 10);

// ── 3. game.requirements – building 900 requires Metal Mine (ID 1) level 15 ──
HookManager::get()->addFilter('game.requirements', function(array $requeriments): array {
    $requeriments[900] = [
        1  => 15,   // Metal Mine level 15
        113 => 5,   // Energy Tech level 5
    ];
    return $requeriments;
}, 10);

// ── 4. game.prodGrid – building 900 produces metal ───────────────────────────
// Production formula: 30 * level * 1.1^level  (similar to Metal Mine)
// Stored as eval()-able string using $BuildLevel (current level) and
// $BuildLevelFactor (throttle 0–10 slider, 10 = 100%).
HookManager::get()->addFilter('game.prodGrid', function(array $ProdGrid): array {
    $ProdGrid[900] = [
        'production' => [
            901 => '30 * $BuildLevel * pow(1.1, $BuildLevel) * ($BuildLevelFactor / 10)',
            911 => '-15 * $BuildLevel * pow(1.1, $BuildLevel) * ($BuildLevelFactor / 10)',
        ],
        'storage' => [],
    ];
    return $ProdGrid;
}, 10);

// ── 5. game.reslist – register building 900 in the correct lists ─────────────
HookManager::get()->addFilter('game.reslist', function(array $reslist): array {
    // class 0 = building, available on planet type 1 (normal planet)
    $reslist['build'][]      = 900;
    $reslist['allow'][1][]   = 900;
    $reslist['prod'][]       = 900;   // has production formula
    return $reslist;
}, 10);

// ── 6. Language injection – register building name in $LNG['tech'] ───────────
// $LNG is available as a global at plugin load time (plugins load after language init).
// addData() uses array_replace_recursive, so this safely merges into existing keys.
if (isset($GLOBALS['LNG']) && $GLOBALS['LNG'] instanceof Language) {
    $GLOBALS['LNG']->addData(['tech' => [
        900 => PluginManager::lang('sm-gameplay-test', 'building_name'),
    ]]);
}

// ── 7. game.production – increase metal production by 5% globally ────────────
// $temp[901]['plus'] is the total raw metal production before multipliers.
HookManager::get()->addFilter('game.production', function(array $temp, array $ctx): array {
    if (isset($temp[901]['plus']) && $temp[901]['plus'] > 0) {
        $temp[901]['plus'] *= 1.05;
    }
    return $temp;
}, 10);
