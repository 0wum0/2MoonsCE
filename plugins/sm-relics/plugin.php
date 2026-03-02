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

$_pm = PluginManager::get();

// ── 1. Ingame page route ──────────────────────────────────────────────────────
$_pm->registerPageRoute(
    'sm-relics',
    'relics',
    'pages/game/RelicsPage.php',
    'RelicsPage'
);

// ── 2. Admin page route ───────────────────────────────────────────────────────
$_pm->registerAdminRoute(
    'sm-relics',
    'relicsAdmin',
    'pages/admin/RelicsAdminPage.php',
    'smRelicsShowAdminPage'
);

// ── 3. Twig namespace (@sm-relics/...) ───────────────────────────────────────
$_pm->registerTwigNamespace('sm-relics', 'templates');

// ── 4. Cronjob path ───────────────────────────────────────────────────────────
$_pm->registerCronjob('sm-relics', 'RelicsTick', 'cron/RelicsTick.php');

// ── 5. Assets (scoped to relics page) ────────────────────────────────────────
if (class_exists('AssetRegistry')) {
    AssetRegistry::get()->registerCss(
        'sm-relics',
        'plugins/sm-relics/assets/css/relics.css',
        ['relics']
    );
    AssetRegistry::get()->registerJs(
        'sm-relics',
        'plugins/sm-relics/assets/js/relics.js',
        ['relics']
    );
}

// ── 6. Element Registration ───────────────────────────────────────────────────
PluginManager::get()->registerElementsCallback(
    'sm-relics',
    function (ElementRegistry $registry): void {

        // 910 – Relikt-Archiv
        // Generates relic points each tick. Requires: Metal Mine 5, Robot Factory 2
        $registry->register([
            'id'           => 910,
            'type'         => 'building',
            'nameKey'      => 'smr_relic_archive',
            'cost'         => [
                901 => 120000,
                902 => 60000,
                903 => 30000,
                911 => 0,
                921 => 0,
            ],
            'factor'       => 1.7,
            'max'          => 20,
            'requirements' => [
                1  => 5,
                14 => 2,
            ],
            'onPlanetType' => [1, 3],
            'stats'        => [],
            'metadata'     => ['plugin' => 'sm-relics', 'relic_generator' => true],
        ]);

        // 911 – Quantum-Reaktor
        // Boosts energy production. Requires: Solar Plant 5, Research Lab 3
        $registry->register([
            'id'           => 911,
            'type'         => 'building',
            'nameKey'      => 'smr_quantum_reactor',
            'cost'         => [
                901 => 200000,
                902 => 100000,
                903 => 50000,
                911 => 0,
                921 => 0,
            ],
            'factor'       => 1.6,
            'max'          => 15,
            'requirements' => [
                22 => 5,
                31 => 3,
            ],
            'onPlanetType' => [1, 3],
            'stats'        => [],
            'metadata'     => ['plugin' => 'sm-relics'],
        ]);

        // 912 – Raumzeit-Labor
        // Reduces build/research time. Requires: Research Lab 5, Nanite Factory 1
        $registry->register([
            'id'           => 912,
            'type'         => 'building',
            'nameKey'      => 'smr_spacetime_lab',
            'cost'         => [
                901 => 350000,
                902 => 180000,
                903 => 90000,
                911 => 0,
                921 => 0,
            ],
            'factor'       => 1.8,
            'max'          => 10,
            'requirements' => [
                31 => 5,
                15 => 1,
            ],
            'onPlanetType' => [1, 3],
            'stats'        => [],
            'metadata'     => ['plugin' => 'sm-relics', 'buildtime_reducer' => true],
        ]);

        // 913 – Militär-Akademie
        // Unlocks Kriegs-Doktrin. Requires: Shipyard 5, Research Lab 4
        $registry->register([
            'id'           => 913,
            'type'         => 'building',
            'nameKey'      => 'smr_military_academy',
            'cost'         => [
                901 => 500000,
                902 => 250000,
                903 => 100000,
                911 => 0,
                921 => 0,
            ],
            'factor'       => 1.9,
            'max'          => 10,
            'requirements' => [
                21 => 5,
                31 => 4,
            ],
            'onPlanetType' => [1, 3],
            'stats'        => [],
            'metadata'     => ['plugin' => 'sm-relics', 'doctrine_unlock' => 'war'],
        ]);

        // 914 – Wirtschaftszentrum
        // Unlocks Wirtschafts-Doktrin. Requires: Metal Mine 8, Crystal Mine 6
        $registry->register([
            'id'           => 914,
            'type'         => 'building',
            'nameKey'      => 'smr_economic_center',
            'cost'         => [
                901 => 400000,
                902 => 200000,
                903 => 80000,
                911 => 0,
                921 => 0,
            ],
            'factor'       => 1.75,
            'max'          => 10,
            'requirements' => [
                1 => 8,
                2 => 6,
            ],
            'onPlanetType' => [1, 3],
            'stats'        => [],
            'metadata'     => ['plugin' => 'sm-relics', 'doctrine_unlock' => 'economy'],
        ]);
    }
);

// ── 7. Planet defaults – inject 0 for registry-only columns ──────────────────
HookManager::get()->addFilter('game.planet', function (array $planet): array {
    $keys = [
        'smr_relic_archive',
        'smr_quantum_reactor',
        'smr_spacetime_lab',
        'smr_military_academy',
        'smr_economic_center',
    ];
    foreach ($keys as $key) {
        if (!isset($planet[$key])) {
            $planet[$key] = 0;
        }
    }
    return $planet;
}, 10);

// ── 8. Language injection ─────────────────────────────────────────────────────
if (isset($GLOBALS['LNG']) && $GLOBALS['LNG'] instanceof Language) {
    $GLOBALS['LNG']->addData(['tech' => [
        910 => PluginManager::lang('sm-relics', 'building_910'),
        911 => PluginManager::lang('sm-relics', 'building_911'),
        912 => PluginManager::lang('sm-relics', 'building_912'),
        913 => PluginManager::lang('sm-relics', 'building_913'),
        914 => PluginManager::lang('sm-relics', 'building_914'),
    ]]);
}

// ── 9b. Admin sidebar link (Module & Plugins section) ────────────────────────
HookManager::get()->addAction('admin.sidebar.modules', function (): void {
    $page   = \HTTP::_GP('page', '');
    $active = ($page === 'relicsAdmin') ? ' active' : '';
    echo '<a href="admin.php?page=relicsAdmin" class="sidebar-link' . $active . '">';
    echo '<span class="sidebar-link-icon">&#128142;</span> Relikte';
    echo '</a>';
}, 10);

// ── 9. Navigation sidebar link (via sidebar_end hook) ─────────────────────────
HookManager::get()->addAction('sidebar_end', function (): void {
    $page    = \HTTP::_GP('page', '');
    $active  = ($page === 'relics') ? ' active' : '';
    $label   = \PluginManager::lang('sm-relics', 'nav_label');
    if ($label === 'nav_label') {
        $label = 'Relikte';
    }
    echo '<div class="nav-section">';
    echo '<div class="nav-section-title"><span>' . htmlspecialchars($label) . '</span></div>';
    echo '<a href="game.php?page=relics" class="sidebar-link' . $active . '" data-label="' . htmlspecialchars($label) . '">';
    echo '<i class="fas fa-gem"></i><span>' . htmlspecialchars($label) . '</span>';
    echo '</a>';
    echo '</div>';
}, 10);

unset($_pm);
