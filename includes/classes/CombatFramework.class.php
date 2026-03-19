<?php

declare(strict_types=1);

/**
 *  SmartMoons / 2Moons Community Edition (2MoonsCE)
 *
 *  Based on the original 2Moons project:
 *
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
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

if (class_exists('CombatFramework', false)) {
    return;
}

/**
 * CombatFramework – Central entry point for all combat in SmartMoons / 2MoonsCE.
 *
 * CHANGES vs original version:
 *
 *  1. BUG FIX: `$result['meta'] = []` after calculateAttack() overwrote the
 *     engine's own 'meta' key (battle summary, round count, morale breaks, …).
 *     Fixed: the engine 'meta' is preserved.  Plugin pre-hook meta is stored
 *     in a separate 'plugin_meta' key so nothing collides.
 *
 *  2. ADDED: normaliseFleetArray() runs before the engine.
 *     Fills null/missing sub-keys (player.military_tech, unit amounts, etc.)
 *     so calculateAttack() never sees unexpected types on PHP 8.3/8.4.
 *     Empty-fleet entries are removed to avoid division-by-zero in the engine.
 *
 *  3. ADDED: ensureResultIntegrity() runs after the engine.
 *     Guarantees all expected result keys are present and correctly typed.
 *     Includes the 'techs' fallback that prevents GenerateReport() crashes.
 *
 *  4. ADDED: emptyResult() for the edge-case where one side has no units
 *     after normalisation (avoids calling the engine with an empty array).
 *
 *  5. Hook names 'combat.pre_calculate' and 'combat.post_calculate' are
 *     UNCHANGED – all existing plugins continue to work without modification.
 */
class CombatFramework
{
    /**
     * Run the combat engine with pre/post hook points.
     *
     * @param array $attackers  Attacker fleet array (passed by reference to calculateAttack)
     * @param array $defenders  Defender fleet array (passed by reference to calculateAttack)
     * @param float $fleetTF    Fleet debris percentage (Fleet_Cdr config value)
     * @param float $defTF      Defense debris percentage (Defs_Cdr config value)
     * @return array            Combat result – backward-compatible return contract:
     *                          ['won', 'debris', 'rw', 'unitLost', 'meta', 'plugin_meta']
     */
    public static function run(
        array &$attackers,
        array &$defenders,
        float  $fleetTF,
        float  $defTF
    ): array {
        // ── Input normalisation ───────────────────────────────────────────
        // Fills missing/null sub-keys; removes fleet entries with no valid units.
        // Snapshot pre-normalisation so emptyResult() can still show player names/coords
        // even when a side has zero units (e.g. undefended planet).
        $attackersSnapshot = $attackers;
        $defendersSnapshot = $defenders;

        self::normaliseFleetArray($attackers);
        self::normaliseFleetArray($defenders);

        // Edge-case: one side had nothing valid to fight with after normalisation
        if (empty($attackers)) {
            return self::emptyResult('r', $attackersSnapshot, $defendersSnapshot); // defender wins by default
        }
        if (empty($defenders)) {
            return self::emptyResult('a', $attackersSnapshot, $defendersSnapshot); // attacker wins by default
        }

        $hook = HookManager::get();

        // ── Hook 1: combat.pre_calculate ─────────────────────────────────
        // Plugins may inspect or modify the input context.
        // We pass copies so plugins cannot accidentally corrupt the by-ref arrays
        // before we hand them to calculateAttack(); we apply any changes back after.
        $ctx = [
            'attackers' => $attackers,
            'defenders' => $defenders,
            'fleetTF'   => $fleetTF,
            'defTF'     => $defTF,
            'meta'      => [],
        ];

        $ctx = $hook->applyFilters('combat.pre_calculate', $ctx);

        // Apply any plugin modifications back to the by-ref arrays
        if (isset($ctx['attackers']) && is_array($ctx['attackers'])) {
            $attackers = $ctx['attackers'];
        }
        if (isset($ctx['defenders']) && is_array($ctx['defenders'])) {
            $defenders = $ctx['defenders'];
        }
        $fleetTF = isset($ctx['fleetTF']) ? (float)$ctx['fleetTF'] : $fleetTF;
        $defTF   = isset($ctx['defTF'])   ? (float)$ctx['defTF']   : $defTF;

        // ── Core engine ───────────────────────────────────────────────────
        require_once 'includes/classes/missions/functions/calculateAttack.php';
        $result = calculateAttack($attackers, $defenders, $fleetTF, $defTF);

        // ── Result integrity guard ────────────────────────────────────────
        $result = self::ensureResultIntegrity($result);

        // FIX: Do NOT overwrite $result['meta'] with [].
        // The engine already populated 'meta' with the battle summary.
        // Plugin pre-hook metadata goes into a separate 'plugin_meta' key.
        $result['plugin_meta'] = $ctx['meta'] ?? [];

        // ── Hook 2: combat.post_calculate ────────────────────────────────
        // Plugins may inspect or annotate the result.
        // They must NOT change 'won', 'debris', 'rw', 'unitLost' to preserve
        // game integrity — but the framework does not enforce this; it is a
        // plugin author responsibility.
        $result = $hook->applyFilters('combat.post_calculate', $result, ['ctx' => $ctx]);

        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Normalise a fleet array so the engine never receives null/missing keys.
     *
     * Each fleet entry must have:
     *   'player'      => array (id, username, military_tech, defence_tech,
     *                            shield_tech, factor[])
     *   'fleetDetail' => array (fleet_start_galaxy/system/planet/type,
     *                            fleet_resource_metal/crystal/deuterium)
     *   'unit'        => array (elementID => positive integer amount)
     *
     * Fleet entries whose unit array is empty after cleaning are removed.
     */
    private static function normaliseFleetArray(array &$fleets): void
    {
        foreach ($fleets as $fid => &$fleet) {
            // ── player ───────────────────────────────────────────────────
            if (!isset($fleet['player']) || !is_array($fleet['player'])) {
                $fleet['player'] = [];
            }
            $fleet['player']['id']            = (int)($fleet['player']['id']            ?? 0);
            $fleet['player']['username']      = (string)($fleet['player']['username']   ?? 'Unknown');
            $fleet['player']['military_tech'] = (int)($fleet['player']['military_tech'] ?? 0);
            $fleet['player']['defence_tech']  = (int)($fleet['player']['defence_tech']  ?? 0);
            $fleet['player']['shield_tech']   = (int)($fleet['player']['shield_tech']   ?? 0);

            if (!isset($fleet['player']['factor']) || !is_array($fleet['player']['factor'])) {
                $fleet['player']['factor'] = [];
            }
            $fleet['player']['factor']['Attack']      = (float)($fleet['player']['factor']['Attack']      ?? 0.0);
            $fleet['player']['factor']['Defensive']   = (float)($fleet['player']['factor']['Defensive']   ?? 0.0);
            $fleet['player']['factor']['Shield']      = (float)($fleet['player']['factor']['Shield']      ?? 0.0);
            $fleet['player']['factor']['ShipStorage'] = (float)($fleet['player']['factor']['ShipStorage'] ?? 0.0);
            $fleet['player']['factor']['SpyPower']    = (float)($fleet['player']['factor']['SpyPower']    ?? 0.0);

            // ── fleetDetail ──────────────────────────────────────────────
            if (!isset($fleet['fleetDetail']) || !is_array($fleet['fleetDetail'])) {
                $fleet['fleetDetail'] = [];
            }
            $fleet['fleetDetail']['fleet_start_galaxy']       = (int)($fleet['fleetDetail']['fleet_start_galaxy']       ?? 0);
            $fleet['fleetDetail']['fleet_start_system']       = (int)($fleet['fleetDetail']['fleet_start_system']       ?? 0);
            $fleet['fleetDetail']['fleet_start_planet']       = (int)($fleet['fleetDetail']['fleet_start_planet']       ?? 0);
            $fleet['fleetDetail']['fleet_start_type']         = (int)($fleet['fleetDetail']['fleet_start_type']         ?? 1);
            $fleet['fleetDetail']['fleet_resource_metal']     = (float)($fleet['fleetDetail']['fleet_resource_metal']     ?? 0.0);
            $fleet['fleetDetail']['fleet_resource_crystal']   = (float)($fleet['fleetDetail']['fleet_resource_crystal']   ?? 0.0);
            $fleet['fleetDetail']['fleet_resource_deuterium'] = (float)($fleet['fleetDetail']['fleet_resource_deuterium'] ?? 0.0);

            // ── unit ─────────────────────────────────────────────────────
            if (!isset($fleet['unit']) || !is_array($fleet['unit'])) {
                $fleet['unit'] = [];
            }
            foreach ($fleet['unit'] as $el => $amt) {
                $casted = (int)$amt;
                if ($casted <= 0) {
                    unset($fleet['unit'][$el]);
                } else {
                    $fleet['unit'][$el] = $casted;
                }
            }

            if (empty($fleet['unit'])) {
                unset($fleets[$fid]);
            }
        }
        unset($fleet);
    }

    /**
     * Ensure the result array has all expected keys with correct types.
     * Acts as a safety net against edge-cases and older engine versions.
     */
    private static function ensureResultIntegrity(array $result): array
    {
        // 'won'
        if (!isset($result['won']) || !in_array($result['won'], ['a', 'r', 'w'], true)) {
            $result['won'] = 'w';
        }

        // 'debris'
        // CRITICAL: array_merge() with integer keys (901, 902) REINDEXES them
        // to 0, 1, 2, 3 - destroying the 901/902 keys the rest of the code reads.
        // Must use array_replace() which preserves integer keys correctly.
        if (!isset($result['debris']) || !is_array($result['debris'])) {
            $result['debris'] = [];
        }
        $result['debris']['attacker'] = array_replace(
            [901 => 0.0, 902 => 0.0],
            (array)($result['debris']['attacker'] ?? [])
        );
        $result['debris']['defender'] = array_replace(
            [901 => 0.0, 902 => 0.0],
            (array)($result['debris']['defender'] ?? [])
        );

        // 'unitLost'
        if (!isset($result['unitLost']) || !is_array($result['unitLost'])) {
            $result['unitLost'] = [];
        }
        $result['unitLost']['attacker'] = max(0.0, (float)($result['unitLost']['attacker'] ?? 0.0));
        $result['unitLost']['defender'] = max(0.0, (float)($result['unitLost']['defender'] ?? 0.0));

        // 'rw' – must exist with at least round 0 (GenerateReport reads rw[0])
        if (!isset($result['rw']) || !is_array($result['rw']) || empty($result['rw'])) {
            $result['rw'] = [
                0 => [
                    'attackers' => [],
                    'defenders' => [],
                    'infoA'     => [],
                    'infoD'     => [],
                    'attackA'   => ['total' => 0],
                    'defenseA'  => ['total' => 0],
                ],
            ];
        }

        if (!isset($result['rw'][0]['attackers'])) { $result['rw'][0]['attackers'] = []; }
        if (!isset($result['rw'][0]['defenders'])) { $result['rw'][0]['defenders'] = []; }
        if (!isset($result['rw'][0]['infoA']))     { $result['rw'][0]['infoA']     = []; }
        if (!isset($result['rw'][0]['infoD']))     { $result['rw'][0]['infoD']     = []; }

        // Ensure every fleet in every round has 'techs' (GenerateReport reads techs[0/1/2])
        // and every unit has a slot in infoA/infoD
        foreach ($result['rw'] as &$roundData) {
            foreach (['attackers' => 'infoA', 'defenders' => 'infoD'] as $side => $infoKey) {
                if (!isset($roundData[$side]))   { $roundData[$side]   = []; }
                if (!isset($roundData[$infoKey])){ $roundData[$infoKey] = []; }

                foreach ($roundData[$side] as $fid => &$fleetData) {
                    if (!isset($fleetData['techs'])
                        || !is_array($fleetData['techs'])
                        || count($fleetData['techs']) < 3) {
                        $p  = $fleetData['player'] ?? [];
                        $mt = (float)($p['military_tech']       ?? 0);
                        $dt = (float)($p['defence_tech']        ?? 0);
                        $st = (float)($p['shield_tech']         ?? 0);
                        $fa = (float)($p['factor']['Attack']    ?? 0.0);
                        $fd = (float)($p['factor']['Defensive'] ?? 0.0);
                        $fs = (float)($p['factor']['Shield']    ?? 0.0);
                        $fleetData['techs'] = [
                            1.0 + 0.1 * $mt + $fa,
                            1.0 + 0.1 * $dt + $fd,
                            1.0 + 0.1 * $st + $fs,
                        ];
                    }
                    foreach ((array)($fleetData['unit'] ?? []) as $el => $amt) {
                        if (!isset($roundData[$infoKey][$fid][$el])) {
                            $roundData[$infoKey][$fid][$el] = ['att' => 0.0, 'def' => 0.0, 'shield' => 0.0];
                        }
                    }
                }
                unset($fleetData);
            }
        }
        unset($roundData);

        // 'meta' – must be an array; engine v3.1 fills this, do NOT overwrite
        if (!isset($result['meta']) || !is_array($result['meta'])) {
            $result['meta'] = ['engine' => 'v3.1', 'rounds' => count($result['rw'])];
        }

        return $result;
    }

    /**
     * Trivial "no-fight" result when one side is empty after normalisation.
     * Fleet data is embedded in rw[0] so the battle report shows the ships.
     */
    private static function emptyResult(string $won, array $attackers = [], array $defenders = []): array
    {
        $roundAttackers = [];
        foreach ($attackers as $fleetID => $fleet) {
            $roundAttackers[$fleetID] = [
                'player'      => [
                    'id'       => $fleet['player']['id']       ?? 0,
                    'username' => $fleet['player']['username'] ?? '',
                ],
                'fleetDetail' => [
                    'fleet_start_galaxy' => $fleet['fleetDetail']['fleet_start_galaxy'] ?? 0,
                    'fleet_start_system' => $fleet['fleetDetail']['fleet_start_system']  ?? 0,
                    'fleet_start_planet' => $fleet['fleetDetail']['fleet_start_planet']  ?? 0,
                    'fleet_start_type'   => $fleet['fleetDetail']['fleet_start_type']    ?? 1,
                ],
                'techs' => [
                    1.0 + 0.1 * (float)($fleet['player']['military_tech'] ?? 0) + (float)($fleet['player']['factor']['Attack']    ?? 0.0),
                    1.0 + 0.1 * (float)($fleet['player']['defence_tech']  ?? 0) + (float)($fleet['player']['factor']['Defensive'] ?? 0.0),
                    1.0 + 0.1 * (float)($fleet['player']['shield_tech']   ?? 0) + (float)($fleet['player']['factor']['Shield']    ?? 0.0),
                ],
                'unit' => $fleet['unit'],
            ];
        }

        $roundDefenders = [];
        foreach ($defenders as $fleetID => $fleet) {
            $roundDefenders[$fleetID] = [
                'player'      => [
                    'id'       => $fleet['player']['id']       ?? 0,
                    'username' => $fleet['player']['username'] ?? '',
                ],
                'fleetDetail' => [
                    'fleet_start_galaxy' => $fleet['fleetDetail']['fleet_start_galaxy'] ?? 0,
                    'fleet_start_system' => $fleet['fleetDetail']['fleet_start_system']  ?? 0,
                    'fleet_start_planet' => $fleet['fleetDetail']['fleet_start_planet']  ?? 0,
                    'fleet_start_type'   => $fleet['fleetDetail']['fleet_start_type']    ?? 1,
                ],
                'techs' => [
                    1.0 + 0.1 * (float)($fleet['player']['military_tech'] ?? 0) + (float)($fleet['player']['factor']['Attack']    ?? 0.0),
                    1.0 + 0.1 * (float)($fleet['player']['defence_tech']  ?? 0) + (float)($fleet['player']['factor']['Defensive'] ?? 0.0),
                    1.0 + 0.1 * (float)($fleet['player']['shield_tech']   ?? 0) + (float)($fleet['player']['factor']['Shield']    ?? 0.0),
                ],
                'unit' => $fleet['unit'],
            ];
        }

        return [
            'won'         => $won,
            'debris'      => [
                'attacker' => [901 => 0.0, 902 => 0.0],
                'defender' => [901 => 0.0, 902 => 0.0],
            ],
            'rw'          => [
                0 => [
                    'attackers' => $roundAttackers,
                    'defenders' => $roundDefenders,
                    'infoA'     => [],
                    'infoD'     => [],
                    'attackA'   => ['total' => array_sum(array_map(fn($f) => array_sum($f['unit']), $attackers))],
                    'defenseA'  => ['total' => array_sum(array_map(fn($f) => array_sum($f['unit']), $defenders))],
                ],
            ],
            'unitLost'    => ['attacker' => 0.0, 'defender' => 0.0],
            'meta'        => ['engine' => 'v3.1', 'rounds' => 0, 'note' => 'no_combat_empty_fleet'],
            'plugin_meta' => [],
        ];
    }
}
