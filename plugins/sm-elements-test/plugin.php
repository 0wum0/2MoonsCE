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

// ── 1. Register element callbacks via Plugin System v1.2 ─────────────────────
PluginManager::get()->registerElementsCallback(
    'sm-elements-test',
    function (ElementRegistry $registry): void {

        // ── Building 950: Quantum Extractor ───────────────────────────────────
        $registry->register([
            'id'           => 950,
            'type'         => 'building',
            'nameKey'      => 'smet_quantum_extractor',
            'cost'         => [
                901 => 80000,   // Metal
                902 => 40000,   // Crystal
                903 => 20000,   // Deuterium
                911 => 0,
                921 => 0,
            ],
            'factor'       => 1.6,
            'max'          => 20,
            'requirements' => [
                1  => 5,    // Metal Mine level 5
                14 => 2,    // Robot Factory level 2
            ],
            'onPlanetType' => [1, 3],
            'stats'        => [
                'attack'      => 0,
                'shield'      => 0,
                'speed'       => 0,
                'capacity'    => 0,
                'consumption' => 0,
            ],
            'metadata'     => ['plugin' => 'sm-elements-test', 'version' => '1.2.0'],
        ]);

        // ── Ship 960: Stealth Drone ───────────────────────────────────────────
        $registry->register([
            'id'           => 960,
            'type'         => 'fleet',
            'nameKey'      => 'smet_stealth_drone',
            'cost'         => [
                901 => 12000,   // Metal
                902 => 6000,    // Crystal
                903 => 2000,    // Deuterium
                911 => 0,
                921 => 0,
            ],
            'factor'       => 1,
            'max'          => 0,
            'requirements' => [
                21  => 4,   // Shipyard level 4
                115 => 3,   // Combustion Engine level 3
            ],
            'onPlanetType' => [],
            'stats'        => [
                'attack'      => 50,
                'shield'      => 10,
                'speed'       => 8000,
                'speed2'      => 0,
                'capacity'    => 500,
                'consumption' => 15,
                'consumption2'=> 0,
            ],
            'metadata'     => ['plugin' => 'sm-elements-test', 'version' => '1.2.0'],
        ]);
    }
);

// ── 2. Planet defaults – inject 0 for registry-only columns ──────────────────
// Prevents E_WARNING when ShowBuildingsPage/ShowShipyardPage access
// $PLANET[$resource[$Element]] for elements that have no real DB column.
HookManager::get()->addFilter('game.planet', function(array $planet): array {
    if (!isset($planet['smet_quantum_extractor'])) {
        $planet['smet_quantum_extractor'] = 0;
    }
    if (!isset($planet['smet_stealth_drone'])) {
        $planet['smet_stealth_drone'] = 0;
    }
    return $planet;
}, 10);

// ── 3. Language injection ─────────────────────────────────────────────────────
// Inject element names into $LNG['tech'] so templates can render them.
// $LNG is available at plugin load time (plugins load after language init).
if (isset($GLOBALS['LNG']) && $GLOBALS['LNG'] instanceof Language) {
    $GLOBALS['LNG']->addData(['tech' => [
        950 => PluginManager::lang('sm-elements-test', 'building_name'),
        960 => PluginManager::lang('sm-elements-test', 'ship_name'),
    ]]);
}
