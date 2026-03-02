<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/LiveFleetDb.php';

/**
 * LiveFleetCronjob – runs every 5 minutes.
 * Handles:
 *  1. NPC pirate random attacks on in-flight fleets
 *  2. Warp disruption / delay events
 *  3. Cleanup of expired interceptions
 */
class LiveFleetCronjob implements CronjobTask
{
    public function run(): void
    {
        try {
            $ldb      = LiveFleetDb::get();
            $settings = $ldb->getSettings();

            if (empty($settings) || !(bool)(int)($settings['enabled'] ?? 0)) {
                return;
            }

            $db  = Database::get();
            if ($db === null) {
                return;
            }
            $now = defined('TIMESTAMP') ? TIMESTAMP : time();

            // Load all in-flight fleets (not yet arrived, not missiles)
            $fleets = $db->select(
                'SELECT f.fleet_id, f.fleet_owner, f.fleet_mission,
                        f.fleet_start_galaxy, f.fleet_start_system, f.fleet_start_planet,
                        f.fleet_end_galaxy,   f.fleet_end_system,   f.fleet_end_planet,
                        f.fleet_start_time,   f.fleet_end_time
                 FROM %%FLEETS%% f
                 WHERE f.fleet_universe = :uni
                   AND f.fleet_start_time <= :now
                   AND f.fleet_end_time   >  :now2
                   AND f.fleet_mission   <> 10
                 LIMIT 1000;',
                [':uni' => Universe::current(), ':now' => $now, ':now2' => $now]
            );

            if (empty($fleets)) {
                return;
            }

            $npcEnabled  = (bool)(int)($settings['npc_enabled']       ?? 1);
            $npcChance   = min(50, max(1, (int)($settings['npc_chance_percent']  ?? 5)));
            $warpEnabled = (bool)(int)($settings['warp_risk_enabled']  ?? 1);
            $warpRisk    = min(20, max(1, (int)($settings['warp_risk_percent']   ?? 3)));

            $npcHits  = 0;
            $warpHits = 0;

            foreach ($fleets as $fleet) {
                $fleetId  = (int)$fleet['fleet_id'];
                $ownerId  = (int)$fleet['fleet_owner'];
                $duration = max(1, (int)$fleet['fleet_end_time'] - (int)$fleet['fleet_start_time']);

                // Only process fleets that are at least 10% underway
                $elapsed  = max(0, $now - (int)$fleet['fleet_start_time']);
                $progress = $elapsed / $duration;
                if ($progress < 0.1 || $progress > 0.9) {
                    continue;
                }

                // Avoid double-hitting the same fleet within 5 min window
                if ($ldb->fleetAlreadyHitThisRun($fleetId, 300)) {
                    continue;
                }

                // ── NPC Pirate Attack ─────────────────────────────────────────
                if ($npcEnabled && random_int(1, 100) <= $npcChance) {
                    $lossPct = random_int(5, 30);
                    $ldb->createNpcAttack(
                        $fleetId,
                        $ownerId,
                        (int)$fleet['fleet_end_galaxy'],
                        (int)$fleet['fleet_end_system'],
                        (int)$fleet['fleet_end_planet'],
                        $lossPct
                    );
                    $npcHits++;
                    error_log("[LiveFleetCronjob] NPC attack on fleet {$fleetId} owner {$ownerId} loss={$lossPct}%");
                    continue; // Only one event per fleet per run
                }

                // ── Warp Disruption ───────────────────────────────────────────
                if ($warpEnabled && random_int(1, 100) <= $warpRisk) {
                    // Delay = 5-20% of remaining flight time
                    $remaining    = max(0, (int)$fleet['fleet_end_time'] - $now);
                    $delaySeconds = (int)round($remaining * (random_int(5, 20) / 100));
                    if ($delaySeconds > 60) {
                        $ldb->createWarpEvent($fleetId, $ownerId, $delaySeconds);
                        // Actually delay the fleet in the DB
                        try {
                            $db->update(
                                'UPDATE %%FLEETS%% SET `fleet_end_time` = `fleet_end_time` + :delay
                                 WHERE `fleet_id` = :fid AND `fleet_end_time` > :now;',
                                [':delay' => $delaySeconds, ':fid' => $fleetId, ':now' => $now]
                            );
                        } catch (Throwable $e) {
                            error_log("[LiveFleetCronjob] Warp delay DB update failed fleet {$fleetId}: " . $e->getMessage());
                        }
                        $warpHits++;
                        error_log("[LiveFleetCronjob] Warp disruption fleet {$fleetId} owner {$ownerId} delay={$delaySeconds}s");
                    }
                }
            }

            // ── Interception Combat Resolution ────────────────────────────────
            $this->resolveInterceptions($db, $now);

            error_log("[LiveFleetCronjob] processed=" . count($fleets) . " npc_hits={$npcHits} warp_hits={$warpHits}");

        } catch (Throwable $e) {
            error_log('[LiveFleetCronjob] run() error: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    private function resolveInterceptions($db, int $now): void
    {
        try {
            $ldb  = LiveFleetDb::get();
            $open = $ldb->getActiveInterceptions();

            foreach ($open as $intercept) {
                $interceptId     = (int)$intercept['id'];
                $targetFleetId   = (int)$intercept['target_fleet_id'];
                $interceptorId   = (int)$intercept['interceptor_id'];
                $tgtEndTime      = (int)($intercept['tgt_end_time'] ?? 0);

                // Target fleet already arrived / gone → fail
                if ($tgtEndTime > 0 && $tgtEndTime <= $now) {
                    $ldb->resolveInterception($interceptId, 'failed');
                    continue;
                }

                // Load target fleet
                $targetFleet = $db->selectSingle(
                    'SELECT * FROM %%FLEETS%% WHERE fleet_id = :fid;',
                    [':fid' => $targetFleetId]
                );
                if (empty($targetFleet)) {
                    $ldb->resolveInterception($interceptId, 'failed');
                    continue;
                }

                // Load interceptor's fastest available fleet heading toward interception point
                // We look for any own fleet currently in flight (mission 1=attack, 2=acs, or 4=deploy)
                // that belongs to the interceptor — as a proxy for "interception fleet"
                $interceptorFleet = $db->selectSingle(
                    'SELECT * FROM %%FLEETS%%
                     WHERE fleet_owner = :uid
                       AND fleet_start_time <= :now
                       AND fleet_end_time   >  :now2
                       AND fleet_universe   = :uni
                     ORDER BY fleet_end_time ASC
                     LIMIT 1;',
                    [
                        ':uid'  => $interceptorId,
                        ':now'  => $now,
                        ':now2' => $now,
                        ':uni'  => Universe::current(),
                    ]
                );

                // Interceptor must have at least one fleet in flight
                if (empty($interceptorFleet)) {
                    continue; // Wait until interceptor launches
                }

                // Combat progress threshold: only fight when target fleet is 50-90% done
                $duration = max(1, $tgtEndTime - (int)$targetFleet['fleet_start_time']);
                $elapsed  = $now - (int)$targetFleet['fleet_start_time'];
                $progress = $elapsed / $duration;
                if ($progress < 0.5) {
                    continue; // Too early
                }

                // ── Run Combat ───────────────────────────────────────────────
                $this->runInterceptCombat($db, $ldb, $interceptorFleet, $targetFleet, $interceptId, $now);
            }

        } catch (Throwable $e) {
            error_log('[LiveFleetCronjob] resolveInterceptions() error: ' . $e->getMessage());
        }
    }

    private function runInterceptCombat($db, LiveFleetDb $ldb, array $interceptorFleet, array $targetFleet, int $interceptId, int $now): void
    {
        try {
            global $pricelist, $reslist, $resource, $CombatCaps;

            // Ensure required globals are loaded
            if (empty($pricelist)) {
                require_once 'includes/common.php';
            }
            require_once 'includes/classes/CombatFramework.class.php';
            require_once 'includes/classes/class.FleetFunctions.php';
            require_once 'includes/GeneralFunctions.php';

            $attackerId = (int)$interceptorFleet['fleet_owner'];
            $defenderId = (int)$targetFleet['fleet_owner'];

            // Load both players
            $attackerUser = $db->selectSingle('SELECT * FROM %%USERS%% WHERE id = :id;', [':id' => $attackerId]);
            $defenderUser = $db->selectSingle('SELECT * FROM %%USERS%% WHERE id = :id;', [':id' => $defenderId]);

            if (empty($attackerUser) || empty($defenderUser)) {
                $ldb->resolveInterception($interceptId, 'failed');
                return;
            }

            $attackerUnits = FleetFunctions::unserialize($interceptorFleet['fleet_array']);
            $defenderUnits = FleetFunctions::unserialize($targetFleet['fleet_array']);

            if (empty($attackerUnits) || empty($defenderUnits)) {
                $ldb->resolveInterception($interceptId, 'failed');
                return;
            }

            $config    = Config::get($targetFleet['fleet_universe'] ?? Universe::current());
            $fleetTF   = (float)($config->Fleet_Cdr ?? 0.3);
            $defTF     = (float)($config->Defs_Cdr  ?? 0.3);

            // Build attacker/defender arrays for calculateAttack
            $attackers = [
                $interceptorFleet['fleet_id'] => [
                    'player'      => array_merge($attackerUser, [
                        'factor' => function_exists('getFactors') ? getFactors($attackerUser, 'attack', $now) : [],
                    ]),
                    'unit'        => $attackerUnits,
                    'fleetDetail' => $interceptorFleet,
                ],
            ];

            $defenders = [
                $targetFleet['fleet_id'] => [
                    'player'      => array_merge($defenderUser, [
                        'factor' => function_exists('getFactors') ? getFactors($defenderUser, 'attack', $now) : [],
                    ]),
                    'unit'        => $defenderUnits,
                    'fleetDetail' => $targetFleet,
                ],
            ];

            $result = CombatFramework::run($attackers, $defenders, $fleetTF, $defTF);

            // ── Apply losses to fleets ────────────────────────────────────────
            $this->applyInterceptLosses($db, $interceptorFleet, $attackers[$interceptorFleet['fleet_id']]['unit'] ?? [], $now);
            $this->applyInterceptLosses($db, $targetFleet,      $defenders[$targetFleet['fleet_id']]['unit'] ?? [],      $now);

            // ── Steal resources from target fleet ─────────────────────────────
            $winner = ($result['win'] ?? 'draw');
            if ($winner === 'attacker') {
                $steal = $result['steal'] ?? ['metal' => 0, 'crystal' => 0, 'deuterium' => 0];
                $this->transferResources($db, $targetFleet, $interceptorFleet, $steal);
            }

            $ldb->resolveInterception($interceptId, 'resolved');

            error_log(sprintf(
                '[LiveFleetCronjob] Combat resolved intercept#%d: attacker=%d defender=%d winner=%s',
                $interceptId, $attackerId, $defenderId, $winner
            ));

        } catch (Throwable $e) {
            error_log('[LiveFleetCronjob] runInterceptCombat() error: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
            $ldb->resolveInterception($interceptId, 'failed');
        }
    }

    private function applyInterceptLosses($db, array $fleet, array $survivingUnits, int $now): void
    {
        try {
            $survivingUnits = array_filter($survivingUnits, static fn($v) => $v > 0);

            if (empty($survivingUnits)) {
                // All ships destroyed – delete the fleet
                $db->delete(
                    'DELETE FROM %%FLEETS%% WHERE fleet_id = :fid;',
                    [':fid' => $fleet['fleet_id']]
                );
                return;
            }

            // Rebuild fleet_array
            $newArray = '';
            $total    = 0;
            foreach ($survivingUnits as $elementId => $amount) {
                $newArray .= $elementId . ',' . $amount . ';';
                $total    += $amount;
            }

            $db->update(
                'UPDATE %%FLEETS%% SET fleet_array = :arr, fleet_amount = :amt WHERE fleet_id = :fid;',
                [':arr' => $newArray, ':amt' => $total, ':fid' => $fleet['fleet_id']]
            );
        } catch (Throwable $e) {
            error_log('[LiveFleetCronjob] applyInterceptLosses() fleet_id=' . $fleet['fleet_id'] . ': ' . $e->getMessage());
        }
    }

    private function transferResources($db, array $loserFleet, array $winnerFleet, array $steal): void
    {
        try {
            $metal    = min((float)($loserFleet['fleet_resource_metal']    ?? 0), (float)($steal['metal']    ?? 0));
            $crystal  = min((float)($loserFleet['fleet_resource_crystal']  ?? 0), (float)($steal['crystal']  ?? 0));
            $deut     = min((float)($loserFleet['fleet_resource_deuterium'] ?? 0), (float)($steal['deuterium'] ?? 0));

            if ($metal <= 0 && $crystal <= 0 && $deut <= 0) {
                return;
            }

            // Subtract from loser fleet
            $db->update(
                'UPDATE %%FLEETS%% SET
                    fleet_resource_metal     = GREATEST(0, fleet_resource_metal     - :m),
                    fleet_resource_crystal   = GREATEST(0, fleet_resource_crystal   - :c),
                    fleet_resource_deuterium = GREATEST(0, fleet_resource_deuterium - :d)
                 WHERE fleet_id = :fid;',
                [':m' => $metal, ':c' => $crystal, ':d' => $deut, ':fid' => $loserFleet['fleet_id']]
            );

            // Add to winner fleet
            $db->update(
                'UPDATE %%FLEETS%% SET
                    fleet_resource_metal     = fleet_resource_metal     + :m,
                    fleet_resource_crystal   = fleet_resource_crystal   + :c,
                    fleet_resource_deuterium = fleet_resource_deuterium + :d
                 WHERE fleet_id = :fid;',
                [':m' => $metal, ':c' => $crystal, ':d' => $deut, ':fid' => $winnerFleet['fleet_id']]
            );

        } catch (Throwable $e) {
            error_log('[LiveFleetCronjob] transferResources() error: ' . $e->getMessage());
        }
    }
}
