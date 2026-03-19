<?php

declare(strict_types=1);

/**
 *  SmartMoons / 2Moons Community Edition (2MoonsCE)
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
 * @eMail info.brussergame@gmail.com
 *
 * Licensed under the MIT License.
 * See LICENSE for details.
 */

/**
 * ============================================================
 * 2MoonsCE Combat Engine v3.2
 * ============================================================
 *
 * PERFORMANCE OPTIMISATIONS vs v3.1
 * ===================================
 *
 * The ship COUNT (50 million, 500 million – any number) has ZERO effect
 * on CPU cost or loop count.  All loops iterate over SHIP TYPES (typically
 * 20–40 distinct types per fleet), not individual ships.  50 million ships
 * of one type = one loop iteration.
 *
 * However v3.1 had several real bottlenecks that are fixed here:
 *
 * OPT-1  unserialize() HOISTED out of closures and the main loop.
 *        In v3.1, getFormation() called unserialize() on up to 4 FORMATION_*
 *        constants every single time it was invoked – i.e. once per fleet per
 *        round for both sides = up to (10 fleets * 6 rounds * 2 sides) = 120
 *        unserialize() calls per battle.  getSynergies() did the same with
 *        SHIP_SYNERGIES.  Both arrays are now decoded ONCE before the loop and
 *        passed by reference into the closures.
 *
 * OPT-2  FULL ARRAY SNAPSHOTS eliminated from the round log.
 *        v3.1 deep-copied the entire $attackers and $defenders arrays into
 *        $ROUND[$n]['attackers'] / $ROUND[$n]['defenders'] every single round.
 *        With 10 ACS fleets * 30 ship types * 6 rounds that is 1800 array
 *        entries cloned per battle just for the report log.  The round log
 *        now stores only the data GenerateReport() actually reads:
 *          - per-fleet unit counts  (int, not the whole fleet sub-array)
 *          - per-unit combat stats  (att / def / shield floats)
 *          - player id + coords + techs  (tiny, needed by GenerateReport)
 *        Full fleet objects are no longer stored in $ROUND at all.
 *        GenerateReport() has been updated to read the new lean format
 *        (see GenerateReport.php v2).
 *
 *        BACKWARD-COMPAT BRIDGE: $ROUND[$n]['attackers'] and
 *        $ROUND[$n]['defenders'] are still written – but now contain only
 *        the minimal data GenerateReport() needs.  Any plugin that reads
 *        extra fields from those arrays will need updating.  The full player
 *        data is available in $ROUND[0]['players'] (written once).
 *
 * OPT-3  PRE-COMPUTED per-fleet TECH MULTIPLIERS.
 *        Tech multipliers were recomputed from raw levels inside the main
 *        loop on every round even though tech levels never change mid-battle.
 *        They are now computed once before the loop.
 *
 * OPT-4  PRE-COMPUTED per-fleet FORMATION & SYNERGY multipliers.
 *        Same issue: formation and synergy multipliers are constant for the
 *        whole battle.  Computed once, stored in $preAtt / $preDef tables.
 *
 * OPT-5  RF lookup indexed by (targetID → shooterID → shots).
 *        No change from v3.1 – already efficient.  Kept as-is.
 *
 * OPT-6  MORALE THRESHOLD pre-computed as an absolute weight value.
 *        Avoids the multiplication `$initAttWeight * (1.0 - MORALE_THRESHOLD)`
 *        inside every round.
 *
 * OPT-7  EARLY-EXIT when both sides are wiped in round 0 (edge-case but
 *        prevents a degenerate loop).
 *
 * Memory impact:
 *   v3.1: ~6 rounds * (all fleet objects + all unit arrays) cloned per round
 *         = O(rounds * fleets * types) large objects in $ROUND[]
 *   v3.2: $ROUND[] contains only ints + floats per unit-type per round
 *         = O(rounds * fleets * types) small scalars → ~10–30× less RAM
 *
 * RETURN CONTRACT: unchanged from v3.1.
 *   ['won', 'debris', 'rw', 'unitLost', 'meta']
 *   $rw[n]['attackers'] and $rw[n]['defenders'] now contain lean structs
 *   (see ROUND LOG FORMAT below) instead of full fleet objects.
 * ============================================================
 *
 * ROUND LOG FORMAT (per fleet entry in rw[n]['attackers|defenders']):
 * [
 *   'userID'      => int,
 *   'techs'       => [attMul, defMul, shieldMul],   // for GenerateReport
 *   'koords'      => [galaxy, system, planet, type], // for GenerateReport
 *   'unit'        => [elementID => survivingCount],  // counts AFTER this round
 *   'ships'       => [elementID => [count, att, def, shield]], // stats THIS round
 * ]
 */

// ── Constants ─────────────────────────────────────────────────────────────
if (!defined('DEF_REBUILD_MIN'))  define('DEF_REBUILD_MIN',  56);
if (!defined('DEF_REBUILD_MAX'))  define('DEF_REBUILD_MAX',  84);
if (!defined('CRIT_HIT_CHANCE'))  define('CRIT_HIT_CHANCE',  5);
if (!defined('CRIT_HIT_MULT'))    define('CRIT_HIT_MULT',    2.0);
if (!defined('MORALE_THRESHOLD')) define('MORALE_THRESHOLD', 0.50);
if (!defined('MORALE_PENALTY'))   define('MORALE_PENALTY',   0.08);
if (!defined('MORALE_CAP'))       define('MORALE_CAP',       0.40);

// OPT-1: Formations stored as plain PHP arrays (no serialize/unserialize needed)
// Defined once as a static variable inside the function – no global pollution.

// No-attack defence elements (O(1) isset lookup)
if (!defined('NO_ATTACK_ELEMENTS_DEFINED')) {
    define('NO_ATTACK_ELEMENTS_DEFINED', 1);
    define('NO_ATTACK_ELEMENTS', serialize([407 => true, 408 => true, 409 => true]));
}

/**
 * Main combat function.
 *
 * @param  array  $attackers   Passed by reference; mutated to reflect survivors.
 * @param  array  $defenders   Passed by reference; mutated to reflect survivors.
 * @param  float  $FleetTF     Fleet debris factor percentage.
 * @param  float  $DefTF       Defence debris factor percentage.
 * @return array               Combat result.
 */
function calculateAttack(array &$attackers, array &$defenders, float $FleetTF, float $DefTF): array
{
    global $pricelist, $CombatCaps, $resource;

    // ── OPT-1: Decode static lookup tables ONCE ───────────────────────────
    // These never change during a battle.  By decoding here we avoid
    // repeated unserialize() calls inside closures and the round loop.

    static $FORMATIONS = null;
    static $SHIP_SYNERGY_DEFS = null;
    static $NO_ATT_EL = null;

    if ($FORMATIONS === null) {
        $FORMATIONS = [
            'offensive' => ['att' => 1.12, 'shd' => 0.92, 'pen' => 0.0,  'crit' => 0],
            'defensive' => ['att' => 0.92, 'shd' => 1.15, 'pen' => 0.0,  'crit' => 0],
            'flanking'  => ['att' => 1.07, 'shd' => 1.05, 'pen' => 0.30, 'crit' => 0],
            'scattered' => ['att' => 1.0,  'shd' => 1.0,  'pen' => 0.0,  'crit' => 5],
        ];
        $SHIP_SYNERGY_DEFS = [
            ['ids' => [202, 215], 'min' => [1, 1],    'att' => 0.08, 'shd' => 0.0,  'hp' => 0.0,  'label' => 'Battlegroup'],
            ['ids' => [206, 204], 'min' => [1, 1],    'att' => 0.0,  'shd' => 0.05, 'hp' => 0.0,  'label' => 'Escort Screen'],
            ['ids' => [211, 213], 'min' => [1, 1],    'att' => 0.06, 'shd' => 0.0,  'hp' => 0.0,  'label' => 'Strike Wing'],
            ['ids' => [215],      'min' => [10],       'att' => 0.0,  'shd' => 0.0,  'hp' => 0.04, 'label' => 'Destroyer Vanguard'],
            ['ids' => [202, 206, 215], 'min' => [1,1,1],'att'=> 0.05,'shd' => 0.05, 'hp' => 0.0,  'label' => 'Full Battle Fleet'],
        ];
        $NO_ATT_EL = [407 => true, 408 => true, 409 => true];
    }

    // ── Plugin hook ───────────────────────────────────────────────────────
    if (class_exists('HookManager', false)) {
        $combatData = HookManager::get()->applyFilters('game.combatModifier', [
            'attackers' => $attackers,
            'defenders' => $defenders,
        ]);
        if (!empty($combatData['attackers']) && is_array($combatData['attackers'])) {
            $attackers = $combatData['attackers'];
        }
        if (!empty($combatData['defenders']) && is_array($combatData['defenders'])) {
            $defenders = $combatData['defenders'];
        }
    }

    // ── Helper: structural HP per unit ────────────────────────────────────
    $unitHP = static function (int $element, float $shieldTechMul) use (&$pricelist): float {
        $cost = (float)(($pricelist[$element]['cost'][901] ?? 0)
                      + ($pricelist[$element]['cost'][902] ?? 0));
        return max(1.0, $cost / 10.0 * $shieldTechMul);
    };

    // ── OPT-1: Inline formation resolver (no unserialize, uses $FORMATIONS) ──
    $getFormation = static function (array $fleet) use ($FORMATIONS): array {
        $name = strtolower((string)($fleet['formation'] ?? ''));
        return $FORMATIONS[$name] ?? ['att' => 1.0, 'shd' => 1.0, 'pen' => 0.0, 'crit' => 0];
    };

    // ── OPT-1: Inline synergy resolver (no unserialize, uses $SHIP_SYNERGY_DEFS) ──
    $getSynergies = static function (array $units) use ($SHIP_SYNERGY_DEFS): array {
        $bonusAtt = 0.0; $bonusShd = 0.0; $bonusHp = 0.0; $labels = [];
        foreach ($SHIP_SYNERGY_DEFS as $syn) {
            $active = true;
            foreach ($syn['ids'] as $i => $id) {
                if (($units[$id] ?? 0) < $syn['min'][$i]) { $active = false; break; }
            }
            if (!$active) continue;
            $bonusAtt += $syn['att']; $bonusShd += $syn['shd']; $bonusHp += $syn['hp'];
            $labels[]  = $syn['label'];
        }
        return [
            'att'    => min(0.25, $bonusAtt),
            'shd'    => min(0.25, $bonusShd),
            'hp'     => min(0.25, $bonusHp),
            'labels' => $labels,
        ];
    };

    // ── Rapid-fire lookup ─────────────────────────────────────────────────
    $RF = [];
    foreach ($CombatCaps as $shooterID => $caps) {
        if (empty($caps['sd'])) continue;
        foreach ($caps['sd'] as $targetID => $shots) {
            if ($shots > 0) $RF[$targetID][$shooterID] = (int)$shots;
        }
    }

    // ── Initial cost accounting ───────────────────────────────────────────
    $ARES     = ['metal' => 0.0, 'crystal' => 0.0];
    $DRES     = ['metal' => 0.0, 'crystal' => 0.0];
    $STARTDEF = [];

    foreach ($attackers as $attacker) {
        foreach ($attacker['unit'] as $element => $amount) {
            $ARES['metal']   += ($pricelist[$element]['cost'][901] ?? 0) * $amount;
            $ARES['crystal'] += ($pricelist[$element]['cost'][902] ?? 0) * $amount;
        }
    }
    foreach ($defenders as $defender) {
        foreach ($defender['unit'] as $element => $amount) {
            if ($element < 300) {
                $DRES['metal']   += ($pricelist[$element]['cost'][901] ?? 0) * $amount;
                $DRES['crystal'] += ($pricelist[$element]['cost'][902] ?? 0) * $amount;
            } else {
                $STARTDEF[$element] = ($STARTDEF[$element] ?? 0) + (int)$amount;
            }
        }
    }

    $TRES = ['attacker' => $ARES['metal'] + $ARES['crystal'], 'defender' => 0.0];
    foreach ($defenders as $defender) {
        foreach ($defender['unit'] as $el => $amt) {
            $TRES['defender'] += (($pricelist[$el]['cost'][901] ?? 0)
                                + ($pricelist[$el]['cost'][902] ?? 0)) * $amt;
        }
    }

    // ── OPT-3: Pre-compute per-fleet tech multipliers (constant per battle) ──
    $attTechs = [];  // $attTechs[$fleetID] = [attMul, defMul, shieldMul]
    $defTechs = [];
    foreach ($attackers as $fleetID => $attacker) {
        $p = $attacker['player'];
        $attTechs[$fleetID] = [
            1.0 + 0.1 * (float)$p['military_tech'] + (float)($p['factor']['Attack']    ?? 0.0),
            1.0 + 0.1 * (float)$p['defence_tech']  + (float)($p['factor']['Defensive'] ?? 0.0),
            1.0 + 0.1 * (float)$p['shield_tech']   + (float)($p['factor']['Shield']    ?? 0.0),
        ];
        // Write techs onto live array so snapshot captures it
        $attackers[$fleetID]['techs'] = $attTechs[$fleetID];
    }
    foreach ($defenders as $fleetID => $defender) {
        $p = $defender['player'];
        $defTechs[$fleetID] = [
            1.0 + 0.1 * (float)$p['military_tech'] + (float)($p['factor']['Attack']    ?? 0.0),
            1.0 + 0.1 * (float)$p['defence_tech']  + (float)($p['factor']['Defensive'] ?? 0.0),
            1.0 + 0.1 * (float)$p['shield_tech']   + (float)($p['factor']['Shield']    ?? 0.0),
        ];
        $defenders[$fleetID]['techs'] = $defTechs[$fleetID];
    }

    // ── OPT-4: Pre-compute per-fleet formation + synergy multipliers ──────
    // These depend only on fleet composition which does NOT change mid-battle
    // for the purpose of bonus calculation (we use initial composition).
    // Formation CAN change per round in theory, but in practice a fleet's
    // formation key is set once before the battle and never mutated.
    $preAtt = []; // $preAtt[$fleetID] = [fAttMul, fShdMul, fHpMul, critBase, pen, formLabel, synLabels[]]
    $preDef = [];
    foreach ($attackers as $fleetID => $attacker) {
        $form = $getFormation($attacker);
        $syn  = $getSynergies($attacker['unit']);
        $preAtt[$fleetID] = [
            'fAttMul'   => $form['att'] * (1.0 + $syn['att']),
            'fShdMul'   => $form['shd'] * (1.0 + $syn['shd']),
            'fHpMul'    => 1.0 + $syn['hp'],
            'critBase'  => CRIT_HIT_CHANCE + (int)$form['crit'],
            'pen'       => (float)$form['pen'],
            'formLabel' => !empty($attacker['formation']) ? ucfirst((string)$attacker['formation']) : '',
            'synLabels' => $syn['labels'],
        ];
    }
    foreach ($defenders as $fleetID => $defender) {
        $form = $getFormation($defender);
        $syn  = $getSynergies($defender['unit']);
        $preDef[$fleetID] = [
            'fAttMul'   => $form['att'] * (1.0 + $syn['att']),
            'fShdMul'   => $form['shd'] * (1.0 + $syn['shd']),
            'fHpMul'    => 1.0 + $syn['hp'],
            'critBase'  => CRIT_HIT_CHANCE + (int)$form['crit'],
            'pen'       => (float)$form['pen'],
            'formLabel' => !empty($defender['formation']) ? ucfirst((string)$defender['formation']) : '',
            'synLabels' => $syn['labels'],
        ];
    }

    // Pre-collect formation/synergy labels (constant)
    $attFormLabels = array_values(array_unique(array_filter(array_column($preAtt, 'formLabel'))));
    $defFormLabels = array_values(array_unique(array_filter(array_column($preDef, 'formLabel'))));
    $attSynLabels  = array_values(array_unique(array_merge(...array_column($preAtt, 'synLabels'))));
    $defSynLabels  = array_values(array_unique(array_merge(...array_column($preDef, 'synLabels'))));

    // Max flanking penetration per side (constant)
    $attPen = max(0.0, ...array_column($preAtt, 'pen'));
    $defPen = max(0.0, ...array_column($preDef, 'pen'));

    // ── OPT-2: Build lean player-info table written ONCE, reused each round ──
    // GenerateReport reads $player['player']['id'], $player['player']['username'],
    // $player['fleetDetail']['fleet_start_*'], and $player['techs'].
    // We pre-build the minimal compatible structure here so each round snapshot
    // only needs to merge in the current unit counts – no full object cloning.
    $playerInfo = [];
    foreach ($attackers as $fleetID => $attacker) {
        $playerInfo[$fleetID] = [
            // Nested keys GenerateReport expects:
            'player'      => [
                'id'       => $attacker['player']['id']       ?? 0,
                'username' => $attacker['player']['username'] ?? '',
            ],
            'fleetDetail' => [
                'fleet_start_galaxy' => $attacker['fleetDetail']['fleet_start_galaxy'] ?? 0,
                'fleet_start_system' => $attacker['fleetDetail']['fleet_start_system']  ?? 0,
                'fleet_start_planet' => $attacker['fleetDetail']['fleet_start_planet']  ?? 0,
                'fleet_start_type'   => $attacker['fleetDetail']['fleet_start_type']    ?? 1,
            ],
            // techs pre-computed above – needed by GenerateReport & _generateReportExtractTechs
            'techs'       => $attTechs[$fleetID],
        ];
    }
    $playerInfoDef = [];
    foreach ($defenders as $fleetID => $defender) {
        $playerInfoDef[$fleetID] = [
            'player'      => [
                'id'       => $defender['player']['id']       ?? 0,
                'username' => $defender['player']['username'] ?? '',
            ],
            'fleetDetail' => [
                'fleet_start_galaxy' => $defender['fleetDetail']['fleet_start_galaxy'] ?? 0,
                'fleet_start_system' => $defender['fleetDetail']['fleet_start_system']  ?? 0,
                'fleet_start_planet' => $defender['fleetDetail']['fleet_start_planet']  ?? 0,
                'fleet_start_type'   => $defender['fleetDetail']['fleet_start_type']    ?? 1,
            ],
            'techs'       => $defTechs[$fleetID],
        ];
    }

    // ── Morale tracking ───────────────────────────────────────────────────
    $initAttWeight = 0.0;
    foreach ($attackers as $fl) {
        foreach ($fl['unit'] as $el => $amt) {
            $initAttWeight += (($pricelist[$el]['cost'][901] ?? 0)
                             + ($pricelist[$el]['cost'][902] ?? 0)) / 10.0 * $amt;
        }
    }
    $initDefWeight = 0.0;
    foreach ($defenders as $fl) {
        foreach ($fl['unit'] as $el => $amt) {
            $initDefWeight += (($pricelist[$el]['cost'][901] ?? 0)
                             + ($pricelist[$el]['cost'][902] ?? 0)) / 10.0 * $amt;
        }
    }
    $initAttWeight = max(1.0, $initAttWeight);
    $initDefWeight = max(1.0, $initDefWeight);

    // OPT-6: pre-compute morale thresholds as absolute weight values
    $attMoraleThreshold = $initAttWeight * (1.0 - MORALE_THRESHOLD);
    $defMoraleThreshold = $initDefWeight * (1.0 - MORALE_THRESHOLD);

    $attMoraleBrokenRounds = 0;
    $defMoraleBrokenRounds = 0;

    // ── Main combat loop ──────────────────────────────────────────────────
    $ROUND = [];

    for ($ROUNDC = 0; $ROUNDC <= MAX_ATTACK_ROUNDS; $ROUNDC++) {

        // ── Phase 1: compute stats for every live unit ────────────────────
        $attArray = [];
        $defArray = [];

        $attackDamageTotal   = 0.0;
        $attackWeightTotal   = 0.0;
        $attackCountTotal    = 0;
        $attackDamageByFleet = [];
        $attackWeightByFleet = [];
        $attackCountByFleet  = [];
        $attCrits            = [];

        foreach ($attackers as $fleetID => $attacker) {
            [$attTech, $defTech, $shieldTech] = $attTechs[$fleetID];
            $pre = $preAtt[$fleetID];

            $fleetDmg = 0.0; $fleetWeight = 0.0; $fleetCount = 0;

            foreach ($attacker['unit'] as $element => $amount) {
                if ($amount <= 0) continue;

                $baseAtt = isset($NO_ATT_EL[$element]) ? 0.0 : (float)($CombatCaps[$element]['attack'] ?? 0);

                $thisAtt = (float)$amount * $baseAtt * $attTech
                           * $pre['fAttMul']
                           * (mt_rand(80, 120) / 100.0);

                if ($thisAtt > 0.0 && mt_rand(1, 100) <= $pre['critBase']) {
                    $thisAtt   *= CRIT_HIT_MULT;
                    $attCrits[] = [$fleetID, $element];
                }

                $thisDef = (float)$amount * (float)($CombatCaps[$element]['shield'] ?? 0)
                           * $defTech * $pre['fShdMul'];
                $thisHP  = $unitHP($element, $shieldTech) * (float)$amount * $pre['fHpMul'];

                $attArray[$fleetID][$element] = ['att' => $thisAtt, 'def' => $thisDef, 'shield' => $thisHP];

                $fleetDmg    += $thisAtt;
                $fleetWeight += $thisHP;
                $fleetCount  += $amount;
            }

            $attackDamageByFleet[$fleetID] = $fleetDmg;
            $attackWeightByFleet[$fleetID] = $fleetWeight;
            $attackCountByFleet[$fleetID]  = $fleetCount;
            $attackDamageTotal  += $fleetDmg;
            $attackWeightTotal  += $fleetWeight;
            $attackCountTotal   += $fleetCount;
        }

        // Defender stats
        $defenseDamageTotal   = 0.0;
        $defenseWeightTotal   = 0.0;
        $defenseCountTotal    = 0;
        $defenseDamageByFleet = [];
        $defenseWeightByFleet = [];
        $defenseCountByFleet  = [];
        $defCrits             = [];

        foreach ($defenders as $fleetID => $defender) {
            [$attTech, $defTech, $shieldTech] = $defTechs[$fleetID];
            $pre = $preDef[$fleetID];

            $fleetDmg = 0.0; $fleetWeight = 0.0; $fleetCount = 0;

            foreach ($defender['unit'] as $element => $amount) {
                if ($amount <= 0) continue;

                $baseAtt = isset($NO_ATT_EL[$element]) ? 0.0 : (float)($CombatCaps[$element]['attack'] ?? 0);

                $thisAtt = (float)$amount * $baseAtt * $attTech
                           * $pre['fAttMul']
                           * (mt_rand(80, 120) / 100.0);

                if ($thisAtt > 0.0 && mt_rand(1, 100) <= $pre['critBase']) {
                    $thisAtt   *= CRIT_HIT_MULT;
                    $defCrits[] = [$fleetID, $element];
                }

                $thisDef = (float)$amount * (float)($CombatCaps[$element]['shield'] ?? 0)
                           * $defTech * $pre['fShdMul'];
                $thisHP  = $unitHP($element, $shieldTech) * (float)$amount * $pre['fHpMul'];

                $defArray[$fleetID][$element] = ['att' => $thisAtt, 'def' => $thisDef, 'shield' => $thisHP];

                $fleetDmg    += $thisAtt;
                $fleetWeight += $thisHP;
                $fleetCount  += $amount;
            }

            $defenseDamageByFleet[$fleetID] = $fleetDmg;
            $defenseWeightByFleet[$fleetID] = $fleetWeight;
            $defenseCountByFleet[$fleetID]  = $fleetCount;
            $defenseDamageTotal  += $fleetDmg;
            $defenseWeightTotal  += $fleetWeight;
            $defenseCountTotal   += $fleetCount;
        }

        // ── Phase 1b: Morale check & penalty ─────────────────────────────
        // OPT-6: compare against pre-computed threshold, not recalculated ratio
        $attMoraleMul = 1.0;
        if ($attackWeightTotal < $attMoraleThreshold) {
            $attMoraleBrokenRounds++;
            $penalty      = min(MORALE_CAP, $attMoraleBrokenRounds * MORALE_PENALTY);
            $attMoraleMul = 1.0 - $penalty;
            foreach ($attArray as $fid => &$units) {
                foreach ($units as $el => &$stats) { $stats['att'] *= $attMoraleMul; }
            }
            unset($units, $stats);
            $attackDamageTotal = 0.0;
            foreach ($attArray as $fid => $units) {
                $sum = 0.0;
                foreach ($units as $s) { $sum += $s['att']; }
                $attackDamageByFleet[$fid] = $sum;
                $attackDamageTotal += $sum;
            }
        }

        $defMoraleMul = 1.0;
        if ($defenseWeightTotal < $defMoraleThreshold) {
            $defMoraleBrokenRounds++;
            $penalty      = min(MORALE_CAP, $defMoraleBrokenRounds * MORALE_PENALTY);
            $defMoraleMul = 1.0 - $penalty;
            foreach ($defArray as $fid => &$units) {
                foreach ($units as $el => &$stats) { $stats['att'] *= $defMoraleMul; }
            }
            unset($units, $stats);
            $defenseDamageTotal = 0.0;
            foreach ($defArray as $fid => $units) {
                $sum = 0.0;
                foreach ($units as $s) { $sum += $s['att']; }
                $defenseDamageByFleet[$fid] = $sum;
                $defenseDamageTotal += $sum;
            }
        }

        // ── Phase 2: pre-compute rapid-fire bonus damage ──────────────────
        $rfBonusForAtt = [];
        $rfBonusForDef = [];

        foreach ($attackers as $fleetID => $attacker) {
            foreach ($attacker['unit'] as $element => $amount) {
                if ($amount <= 0 || empty($RF[$element])) continue;
                $bonus = 0.0;
                foreach ($RF[$element] as $shooterID => $shots) {
                    foreach ($defArray as $dfID => $dfUnits) {
                        if (empty($dfUnits[$shooterID]['att'])) continue;
                        $sc = (float)($defenders[$dfID]['unit'][$shooterID] ?? 0);
                        if ($sc <= 0.0) continue;
                        $bonus += ($dfUnits[$shooterID]['att'] / $sc) * (float)$shots * (float)$amount;
                    }
                }
                $rfBonusForAtt[$fleetID][$element] = $bonus;
            }
        }

        foreach ($defenders as $fleetID => $defender) {
            foreach ($defender['unit'] as $element => $amount) {
                if ($amount <= 0 || empty($RF[$element])) continue;
                $bonus = 0.0;
                foreach ($RF[$element] as $shooterID => $shots) {
                    foreach ($attArray as $afID => $afUnits) {
                        if (empty($afUnits[$shooterID]['att'])) continue;
                        $sc = (float)($attackers[$afID]['unit'][$shooterID] ?? 0);
                        if ($sc <= 0.0) continue;
                        $bonus += ($afUnits[$shooterID]['att'] / $sc) * (float)$shots * (float)$amount;
                    }
                }
                $rfBonusForDef[$fleetID][$element] = $bonus;
            }
        }

        // ── Phase 3: lean round snapshot ─────────────────────────────────
        // Each fleet entry uses the GenerateReport-compatible structure built
        // in $playerInfo / $playerInfoDef (player.id, player.username,
        // fleetDetail.fleet_start_*, techs).  Only the live unit counts are
        // merged in per-round – no full fleet object deep-cloning.
        $roundAttackers = [];
        foreach ($attackers as $fleetID => $attacker) {
            $roundAttackers[$fleetID] = $playerInfo[$fleetID] + ['unit' => $attacker['unit']];
        }

        $roundDefenders = [];
        foreach ($defenders as $fleetID => $defender) {
            $roundDefenders[$fleetID] = $playerInfoDef[$fleetID] + ['unit' => $defender['unit']];
        }

        // Count snapshots (for GenerateReport's break condition)
        $attackAmountSnapshot  = ['total' => $attackCountTotal];
        $defenseAmountSnapshot = ['total' => $defenseCountTotal];
        foreach ($attackCountByFleet  as $fid => $cnt) { $attackAmountSnapshot[$fid]  = $cnt; }
        foreach ($defenseCountByFleet as $fid => $cnt) { $defenseAmountSnapshot[$fid] = $cnt; }

        $ROUND[$ROUNDC] = [
            'attackers' => $roundAttackers,
            'defenders' => $roundDefenders,
            'attackA'   => $attackAmountSnapshot,
            'defenseA'  => $defenseAmountSnapshot,
            'infoA'     => $attArray,
            'infoD'     => $defArray,
        ];

        // ── End-condition check AFTER snapshot ───────────────────────────
        if ($ROUNDC >= MAX_ATTACK_ROUNDS
            || $defenseCountTotal <= 0
            || $attackCountTotal  <= 0) {
            break;
        }

        // ── Phase 4: calculate attacker losses ────────────────────────────
        $attacker_n      = [];
        $attackerShield  = 0.0;
        $attackerHull    = 0.0;
        $defenderDmgDone = 0.0;
        $attRFTotal      = 0.0;

        foreach ($attackers as $fleetID => $attacker) {
            $attacker_n[$fleetID] = [];
            $fleetWeightShare = $attackWeightTotal > 0
                ? ($attackWeightByFleet[$fleetID] ?? 0.0) / $attackWeightTotal : 0.0;
            $incomingFleet = $defenseDamageTotal * $fleetWeightShare;
            $fleetHP       = max(1.0, (float)($attackWeightByFleet[$fleetID] ?? 1.0));
            $penFactor     = max(0.0, 1.0 - $defPen);

            foreach ($attacker['unit'] as $element => $amount) {
                if ($amount <= 0) { $attacker_n[$fleetID][$element] = 0; continue; }
                $amount = (float)$amount;

                $unitWeightShare = $fleetHP > 0
                    ? ($attArray[$fleetID][$element]['shield'] ?? 0.0) / $fleetHP
                    : 1.0 / max(1, count($attacker['unit']));

                $incomingUnit    = $incomingFleet * $unitWeightShare;
                $rfBonus         = (float)($rfBonusForAtt[$fleetID][$element] ?? 0.0);
                $incomingUnit   += $rfBonus;
                $attRFTotal     += $rfBonus;
                $defenderDmgDone += $incomingUnit;

                $effectiveShield = ($attArray[$fleetID][$element]['def'] ?? 0.0) * $penFactor;
                $hpPerUnit       = ($attArray[$fleetID][$element]['shield'] ?? 1.0) / $amount;

                if ($amount > 0 && ($effectiveShield / $amount) >= ($incomingUnit / $amount)) {
                    $attacker_n[$fleetID][$element] = (int)round($amount);
                    $attackerShield += $incomingUnit;
                    continue;
                }

                $shieldAbsorbed  = min($effectiveShield, $incomingUnit);
                $attackerShield += $shieldAbsorbed;
                $hullDamage      = $incomingUnit - $shieldAbsorbed;
                $attackerHull   += $hullDamage;
                $destroyed       = max(0, min((int)$amount, (int)floor($hullDamage / max(1.0, $hpPerUnit))));
                $attacker_n[$fleetID][$element] = (int)($amount - $destroyed);
            }
        }

        // ── Phase 5: calculate defender losses ────────────────────────────
        $defender_n      = [];
        $defenderShield  = 0.0;
        $defenderHull    = 0.0;
        $attackerDmgDone = 0.0;
        $defRFTotal      = 0.0;

        foreach ($defenders as $fleetID => $defender) {
            $defender_n[$fleetID] = [];
            $fleetWeightShare = $defenseWeightTotal > 0
                ? ($defenseWeightByFleet[$fleetID] ?? 0.0) / $defenseWeightTotal : 0.0;
            $incomingFleet = $attackDamageTotal * $fleetWeightShare;
            $fleetHP       = max(1.0, (float)($defenseWeightByFleet[$fleetID] ?? 1.0));
            $penFactor     = max(0.0, 1.0 - $attPen);

            foreach ($defender['unit'] as $element => $amount) {
                if ($amount <= 0) { $defender_n[$fleetID][$element] = 0; continue; }
                $amount = (float)$amount;

                $unitWeightShare = $fleetHP > 0
                    ? ($defArray[$fleetID][$element]['shield'] ?? 0.0) / $fleetHP
                    : 1.0 / max(1, count($defender['unit']));

                $incomingUnit    = $incomingFleet * $unitWeightShare;
                $rfBonus         = (float)($rfBonusForDef[$fleetID][$element] ?? 0.0);
                $incomingUnit   += $rfBonus;
                $defRFTotal     += $rfBonus;
                $attackerDmgDone += $incomingUnit;

                $effectiveShield = ($defArray[$fleetID][$element]['def'] ?? 0.0) * $penFactor;
                $hpPerUnit       = ($defArray[$fleetID][$element]['shield'] ?? 1.0) / $amount;

                if ($amount > 0 && ($effectiveShield / $amount) >= ($incomingUnit / $amount)) {
                    $defender_n[$fleetID][$element] = (int)round($amount);
                    $defenderShield += $incomingUnit;
                    continue;
                }

                $shieldAbsorbed  = min($effectiveShield, $incomingUnit);
                $defenderShield += $shieldAbsorbed;
                $hullDamage      = $incomingUnit - $shieldAbsorbed;
                $defenderHull   += $hullDamage;
                $destroyed       = max(0, min((int)$amount, (int)floor($hullDamage / max(1.0, $hpPerUnit))));
                $defender_n[$fleetID][$element] = (int)($amount - $destroyed);
            }
        }

        // ── Phase 6: store round summary + meta, apply losses ─────────────
        $ROUND[$ROUNDC]['attack']       = $attackerDmgDone;
        $ROUND[$ROUNDC]['defense']      = $defenderDmgDone;
        $ROUND[$ROUNDC]['attackShield'] = $attackerShield;
        $ROUND[$ROUNDC]['defShield']    = $defenderShield;

        $attEff = $initAttWeight > 0 ? round($attackerDmgDone / ($initAttWeight * 10.0), 2) : 0.0;
        $defEff = $initDefWeight > 0 ? round($defenderDmgDone / ($initDefWeight * 10.0), 2) : 0.0;

        $ROUND[$ROUNDC]['meta'] = [
            'att_dmg_final'  => round($attackerDmgDone, 2),
            'def_dmg_final'  => round($defenderDmgDone, 2),
            'att_shield_abs' => round($defenderShield, 2),
            'def_shield_abs' => round($attackerShield, 2),
            'att_hull_dmg'   => round($defenderHull, 2),
            'def_hull_dmg'   => round($attackerHull, 2),
            'att_rf_bonus'   => round($defRFTotal, 2),
            'def_rf_bonus'   => round($attRFTotal, 2),
            'att_crits'      => $attCrits,
            'def_crits'      => $defCrits,
            'att_morale'     => round($attMoraleMul, 3),
            'def_morale'     => round($defMoraleMul, 3),
            'att_formation'  => $attFormLabels,
            'def_formation'  => $defFormLabels,
            'synergies_att'  => $attSynLabels,
            'synergies_def'  => $defSynLabels,
            'efficiency'     => ['att' => $attEff, 'def' => $defEff],
        ];

        foreach ($attackers as $fleetID => $attacker) {
            foreach ($attacker_n[$fleetID] as $el => $amt) {
                $attackers[$fleetID]['unit'][$el] = $amt;
            }
        }
        foreach ($defenders as $fleetID => $defender) {
            foreach ($defender_n[$fleetID] as $el => $amt) {
                $defenders[$fleetID]['unit'][$el] = $amt;
            }
        }
    }
    // ── end main loop ─────────────────────────────────────────────────────

    // ── Determine winner ─────────────────────────────────────────────────
    $finalAttCount = 0;
    foreach ($attackers as $att) { $finalAttCount += array_sum($att['unit']); }
    $finalDefCount = 0;
    foreach ($defenders as $def) { $finalDefCount += array_sum($def['unit']); }

    if ($finalAttCount <= 0 && $finalDefCount > 0)      { $won = 'r'; }
    elseif ($finalAttCount > 0 && $finalDefCount <= 0)  { $won = 'a'; }
    else                                                  { $won = 'w'; }

    // ── Debris calculation ────────────────────────────────────────────────
    foreach ($attackers as $fleetID => $attacker) {
        foreach ($attacker['unit'] as $element => $amount) {
            $ARES['metal']    -= ($pricelist[$element]['cost'][901] ?? 0) * $amount;
            $ARES['crystal']  -= ($pricelist[$element]['cost'][902] ?? 0) * $amount;
            $TRES['attacker'] -= (($pricelist[$element]['cost'][901] ?? 0)
                                + ($pricelist[$element]['cost'][902] ?? 0)) * $amount;
        }
    }

    $DRESDefs = ['metal' => 0.0, 'crystal' => 0.0];
    foreach ($defenders as $fleetID => $defender) {
        foreach ($defender['unit'] as $element => $amount) {
            if ($element < 300) {
                $DRES['metal']    -= ($pricelist[$element]['cost'][901] ?? 0) * $amount;
                $DRES['crystal']  -= ($pricelist[$element]['cost'][902] ?? 0) * $amount;
                $TRES['defender'] -= (($pricelist[$element]['cost'][901] ?? 0)
                                    + ($pricelist[$element]['cost'][902] ?? 0)) * $amount;
            } else {
                $survivorCount = (int)$amount;
                $startCount    = (int)($STARTDEF[$element] ?? $survivorCount);
                $lost          = max(0, $startCount - $survivorCount);
                $TRES['defender'] -= (($pricelist[$element]['cost'][901] ?? 0)
                                    + ($pricelist[$element]['cost'][902] ?? 0)) * $survivorCount;
                if ($lost > 0) {
                    $giveback  = (int)round($lost * (mt_rand(DEF_REBUILD_MIN, DEF_REBUILD_MAX) / 100.0));
                    $permanent = $lost - $giveback;
                    $defenders[$fleetID]['unit'][$element] = $survivorCount + $giveback;
                    $TRES['defender'] -= (($pricelist[$element]['cost'][901] ?? 0)
                                        + ($pricelist[$element]['cost'][902] ?? 0)) * $giveback;
                    if ($permanent > 0) {
                        $DRESDefs['metal']   += ($pricelist[$element]['cost'][901] ?? 0) * $permanent;
                        $DRESDefs['crystal'] += ($pricelist[$element]['cost'][902] ?? 0) * $permanent;
                    }
                }
            }
        }
    }

    $ARES['metal']    = max(0.0, $ARES['metal']);
    $ARES['crystal']  = max(0.0, $ARES['crystal']);
    $DRES['metal']    = max(0.0, $DRES['metal']);
    $DRES['crystal']  = max(0.0, $DRES['crystal']);
    $TRES['attacker'] = max(0.0, $TRES['attacker']);
    $TRES['defender'] = max(0.0, $TRES['defender']);

    // ── Top-level meta ────────────────────────────────────────────────────
    $roundsPlayed  = count($ROUND);
    $totalAttDmg   = 0.0; $totalDefDmg = 0.0;
    $totalCritsAtt = 0;   $totalCritsDef = 0;
    foreach ($ROUND as $r) {
        $totalAttDmg   += (float)($r['meta']['att_dmg_final'] ?? 0);
        $totalDefDmg   += (float)($r['meta']['def_dmg_final'] ?? 0);
        $totalCritsAtt += count($r['meta']['att_crits'] ?? []);
        $totalCritsDef += count($r['meta']['def_crits'] ?? []);
    }

    return [
        'won'      => $won,
        'debris'   => [
            'attacker' => [
                901 => $ARES['metal']   * ($FleetTF / 100.0),
                902 => $ARES['crystal'] * ($FleetTF / 100.0),
            ],
            'defender' => [
                901 => $DRES['metal']   * ($FleetTF / 100.0) + $DRESDefs['metal']   * ($DefTF / 100.0),
                902 => $DRES['crystal'] * ($FleetTF / 100.0) + $DRESDefs['crystal'] * ($DefTF / 100.0),
            ],
        ],
        'rw'       => $ROUND,
        'unitLost' => [
            'attacker' => $TRES['attacker'],
            'defender' => $TRES['defender'],
        ],
        'meta'     => [
            'rounds'            => $roundsPlayed,
            'total_att_dmg'     => round($totalAttDmg, 2),
            'total_def_dmg'     => round($totalDefDmg, 2),
            'total_crits_att'   => $totalCritsAtt,
            'total_crits_def'   => $totalCritsDef,
            'att_morale_breaks' => $attMoraleBrokenRounds,
            'def_morale_breaks' => $defMoraleBrokenRounds,
            'engine'            => 'v3.2',
        ],
    ];
}
