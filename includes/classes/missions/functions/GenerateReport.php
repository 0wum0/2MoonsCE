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

/**
 * GenerateReport – build the serialisable report data array from a combat result.
 *
 * FIXES vs previous version:
 *
 *  1. CRASH FIX: $player['techs'] could be absent if the round was stored
 *     before the v3.1 engine wrote it.  Added defensive fallback that
 *     reconstructs tech values from player data.
 *
 *  2. CRASH FIX: $RoundInfo['infoA'][$FleetID][$ShipID] could be absent
 *     when a ship unit survived all rounds without being targeted (e.g. in
 *     an ACS battle where one fleet never received incoming damage).  Added
 *     null-safe fallback [0, 0, 0, 0].
 *
 *  3. LOGIC FIX: The $Destroy['def']++ counter was incremented BOTH when
 *     array_sum($player['unit']) == 0 AND inside the per-ship loop when
 *     $Amount <= 0.  This double-counted destroyed fleets and caused the
 *     round-break condition `$Destroy['def'] == count($RoundInfo['defenders'])`
 *     to fire one round too early.  Fixed by only counting at fleet level.
 *
 *  4. LOGIC FIX: The round['info'] entry was written BEFORE the $Destroy
 *     early-break check, so the last round always had its info array set
 *     even if we were breaking out.  Order corrected.
 *
 *  5. SAFETY: All array accesses guarded with isset/null-coalescing.
 *
 * @param array $combatResult   Return value of calculateAttack() / CombatFramework::run()
 * @param array $reportInfo     Contextual metadata assembled by the mission class
 * @return array                Serialisable report data array
 */
function GenerateReport(array $combatResult, array $reportInfo): array
{
    $Destroy = ['att' => 0, 'def' => 0];
    $DATA    = [];

    // ── Header fields ─────────────────────────────────────────────────────
    $DATA['mode'] = (int)($reportInfo['moonDestroy'] ?? 0);
    $DATA['time'] = $reportInfo['thisFleet']['fleet_start_time'] ?? 0;
    $DATA['start'] = [
        $reportInfo['thisFleet']['fleet_start_galaxy'] ?? 0,
        $reportInfo['thisFleet']['fleet_start_system']  ?? 0,
        $reportInfo['thisFleet']['fleet_start_planet']  ?? 0,
        $reportInfo['thisFleet']['fleet_start_type']    ?? 1,
    ];
    $DATA['koords'] = [
        $reportInfo['thisFleet']['fleet_end_galaxy'] ?? 0,
        $reportInfo['thisFleet']['fleet_end_system']  ?? 0,
        $reportInfo['thisFleet']['fleet_end_planet']  ?? 0,
        $reportInfo['thisFleet']['fleet_end_type']    ?? 1,
    ];
    $DATA['units']  = [
        $combatResult['unitLost']['attacker'] ?? 0,
        $combatResult['unitLost']['defender'] ?? 0,
    ];
    $DATA['debris'] = $reportInfo['debris'] ?? [901 => 0, 902 => 0];
    $DATA['steal']  = $reportInfo['stealResource'] ?? [901 => 0, 902 => 0, 903 => 0];
    $DATA['result'] = $combatResult['won'] ?? 'w';
    $DATA['moon']   = [
        'moonName'          => $reportInfo['moonName']           ?? null,
        'moonChance'        => (int)($reportInfo['moonChance']   ?? 0),
        'moonDestroyChance' => (int)($reportInfo['moonDestroyChance'] ?? 0),
        'moonDestroySuccess'=> (int)($reportInfo['moonDestroySuccess'] ?? 0),
        'fleetDestroyChance'=> (int)($reportInfo['fleetDestroyChance'] ?? 0),
        'fleetDestroySuccess'=> (int)($reportInfo['fleetDestroySuccess'] ?? 0),
    ];
    $DATA['additionalInfo'] = $reportInfo['additionalInfo'] ?? '';

    // Carry over extended battle meta if present (purely additive for
    // future report renderers – existing Twig templates safely ignore it).
    if (!empty($combatResult['meta']) && is_array($combatResult['meta'])) {
        $DATA['battleMeta'] = $combatResult['meta'];
    }

    // ── Build player registry from round 0 ───────────────────────────────
    // FIX: techs fallback reconstructs from player sub-array when absent.
    $round0Attackers = $combatResult['rw'][0]['attackers'] ?? [];
    $round0Defenders = $combatResult['rw'][0]['defenders'] ?? [];

    foreach ($round0Attackers as $player) {
        $techs = _generateReportExtractTechs($player);
        $DATA['players'][$player['player']['id'] ?? 0] = [
            'name'   => $player['player']['username']     ?? 'Unknown',
            'koords' => [
                $player['fleetDetail']['fleet_start_galaxy'] ?? 0,
                $player['fleetDetail']['fleet_start_system']  ?? 0,
                $player['fleetDetail']['fleet_start_planet']  ?? 0,
                $player['fleetDetail']['fleet_start_type']    ?? 1,
            ],
            'tech' => $techs,
        ];
    }
    foreach ($round0Defenders as $player) {
        $techs = _generateReportExtractTechs($player);
        $DATA['players'][$player['player']['id'] ?? 0] = [
            'name'   => $player['player']['username']     ?? 'Unknown',
            'koords' => [
                $player['fleetDetail']['fleet_start_galaxy'] ?? 0,
                $player['fleetDetail']['fleet_start_system']  ?? 0,
                $player['fleetDetail']['fleet_start_planet']  ?? 0,
                $player['fleetDetail']['fleet_start_type']    ?? 1,
            ],
            'tech' => $techs,
        ];
    }

    // ── Round data ────────────────────────────────────────────────────────
    $maxRounds = defined('MAX_ATTACK_ROUNDS') ? MAX_ATTACK_ROUNDS : 6;

    foreach ($combatResult['rw'] as $Round => $RoundInfo) {

        // ── Attacker side for this round ──────────────────────────────────
        $attFleets    = $RoundInfo['attackers'] ?? [];
        $infoA        = $RoundInfo['infoA']     ?? [];
        $attDestroyed = 0;

        foreach ($attFleets as $FleetID => $player) {
            $playerData = ['userID' => $player['player']['id'] ?? 0, 'ships' => []];

            // FIX: count as destroyed at fleet level only (not per-ship loop)
            if (array_sum((array)($player['unit'] ?? [])) == 0) {
                $DATA['rounds'][$Round]['attacker'][] = $playerData;
                $attDestroyed++;
                continue;
            }

            foreach ((array)($player['unit'] ?? []) as $ShipID => $Amount) {
                if ($Amount <= 0) {
                    continue;
                }
                // FIX: null-safe info lookup with default placeholder
                $ShipInfo = $infoA[$FleetID][$ShipID] ?? ['att' => 0.0, 'def' => 0.0, 'shield' => 0.0];
                $playerData['ships'][$ShipID] = [
                    $Amount,
                    (float)($ShipInfo['att']    ?? 0.0),
                    (float)($ShipInfo['def']    ?? 0.0),
                    (float)($ShipInfo['shield'] ?? 0.0),
                ];
            }

            $DATA['rounds'][$Round]['attacker'][] = $playerData;
        }
        $Destroy['att'] += $attDestroyed;

        // ── Defender side for this round ──────────────────────────────────
        $defFleets    = $RoundInfo['defenders'] ?? [];
        $infoD        = $RoundInfo['infoD']     ?? [];
        $defDestroyed = 0;

        foreach ($defFleets as $FleetID => $player) {
            $playerData = ['userID' => $player['player']['id'] ?? 0, 'ships' => []];

            // FIX: count as destroyed at fleet level only
            if (array_sum((array)($player['unit'] ?? [])) == 0) {
                $DATA['rounds'][$Round]['defender'][] = $playerData;
                $defDestroyed++;
                continue;
            }

            foreach ((array)($player['unit'] ?? []) as $ShipID => $Amount) {
                if ($Amount <= 0) {
                    continue;
                }
                $ShipInfo = $infoD[$FleetID][$ShipID] ?? ['att' => 0.0, 'def' => 0.0, 'shield' => 0.0];
                $playerData['ships'][$ShipID] = [
                    $Amount,
                    (float)($ShipInfo['att']    ?? 0.0),
                    (float)($ShipInfo['def']    ?? 0.0),
                    (float)($ShipInfo['shield'] ?? 0.0),
                ];
            }

            $DATA['rounds'][$Round]['defender'][] = $playerData;
        }
        $Destroy['def'] += $defDestroyed;

        // ── FIX: early-break check BEFORE writing round info ─────────────
        // Round info should NOT be written for the terminal (post-battle) round.
        $allAttDestroyed = ($attDestroyed >= count($attFleets) && !empty($attFleets));
        $allDefDestroyed = ($defDestroyed >= count($defFleets) && !empty($defFleets));

        if ($Round >= $maxRounds || $allAttDestroyed || $allDefDestroyed) {
            break;
        }

        // Write round damage/shield summary for this non-terminal round
        if (isset($RoundInfo['attack'], $RoundInfo['attackShield'],
                  $RoundInfo['defense'], $RoundInfo['defShield'])) {
            $DATA['rounds'][$Round]['info'] = [
                $RoundInfo['attack'],
                $RoundInfo['attackShield'],
                $RoundInfo['defense'],
                $RoundInfo['defShield'],
            ];
        } else {
            $DATA['rounds'][$Round]['info'] = [null, null, null, null];
        }

        // Attach per-round meta for extended report renderers (optional)
        if (!empty($RoundInfo['meta']) && is_array($RoundInfo['meta'])) {
            $DATA['rounds'][$Round]['meta'] = $RoundInfo['meta'];
        }
    }

    return $DATA;
}

/**
 * Extract tech multipliers from a fleet entry's 'techs' array.
 * Falls back to reconstructing from player data if 'techs' is absent.
 * Returns [attMul*100, defMul*100, shieldMul*100] as integers.
 *
 * @internal  Only used inside GenerateReport().
 */
function _generateReportExtractTechs(array $player): array
{
    if (isset($player['techs'])
        && is_array($player['techs'])
        && count($player['techs']) >= 3) {
        return [
            (int)round((float)$player['techs'][0] * 100),
            (int)round((float)$player['techs'][1] * 100),
            (int)round((float)$player['techs'][2] * 100),
        ];
    }

    // Fallback: reconstruct from player sub-array
    $p  = $player['player'] ?? [];
    $mt = (float)($p['military_tech'] ?? 0);
    $dt = (float)($p['defence_tech']  ?? 0);
    $st = (float)($p['shield_tech']   ?? 0);
    $fa = (float)($p['factor']['Attack']    ?? 0.0);
    $fd = (float)($p['factor']['Defensive'] ?? 0.0);
    $fs = (float)($p['factor']['Shield']    ?? 0.0);

    return [
        (int)round((1.0 + 0.1 * $mt + $fa) * 100),
        (int)round((1.0 + 0.1 * $dt + $fd) * 100),
        (int)round((1.0 + 0.1 * $st + $fs) * 100),
    ];
}
