<?php

declare(strict_types=1);

/**
 * sm-elements-test – Plugin System v1.2 Dynamic Element Registry Test
 *
 * Registers two new elements purely via ElementRegistry (no DB, no SQL):
 *
 *   ID 950 – Quantum Extractor (building, planet type 1+3)
 *            Requires: Metal Mine (ID 1) level 5, Robot Factory (ID 14) level 2
 *
 *   ID 960 – Stealth Drone (fleet/ship)
 *            Requires: Shipyard (ID 21) level 4, Combustion Engine tech (ID 115) level 3
 *
 * Both elements will appear in their respective menus automatically because
 * exportLegacyReslist() injects them into $reslist['build'] / $reslist['fleet']
 * and exportLegacyPricelist() / exportLegacyResourceMap() make them fully
 * accessible to BuildFunctions, ShowBuildingsPage, ShowShipyardPage, etc.
 *
 * Language strings are injected into $LNG['tech'] so templates can render names.
 * The nameKey (planet column) is set to a safe dummy value; since no DB column
 * exists, $PLANET['smet_quantum_extractor'] will be NULL/0 — the building shows
 * at level 0 and can be queued (the queue stores the level, not a planet column).
 * For a production plugin you would add the column via install.sql.
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
