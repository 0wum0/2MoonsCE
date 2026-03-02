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

            // ── Cleanup: resolve interceptions whose target fleet is gone ─────
            $this->cleanupStaleInterceptions($db, $now);

            error_log("[LiveFleetCronjob] processed=" . count($fleets) . " npc_hits={$npcHits} warp_hits={$warpHits}");

        } catch (Throwable $e) {
            error_log('[LiveFleetCronjob] run() error: ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    private function cleanupStaleInterceptions($db, int $now): void
    {
        try {
            // Mark as 'failed' any interception whose target fleet no longer exists or has arrived
            $db->update(
                "UPDATE %%PREFIX%%lft_interceptions i
                 LEFT JOIN %%FLEETS%% f ON f.fleet_id = i.target_fleet_id
                 SET i.`status` = 'failed', i.`resolved_at` = :now
                 WHERE i.`status` IN ('pending','launched')
                   AND (f.fleet_id IS NULL OR f.fleet_end_time <= :now2);",
                [':now' => $now, ':now2' => $now]
            );
        } catch (Throwable $e) {
            // Table join syntax may differ – try simpler cleanup
            try {
                $ldb  = LiveFleetDb::get();
                $open = $ldb->getActiveInterceptions();
                foreach ($open as $intercept) {
                    $fleetEndTime = (int)($intercept['tgt_end_time'] ?? 0);
                    if ($fleetEndTime > 0 && $fleetEndTime <= $now) {
                        $ldb->resolveInterception((int)$intercept['id'], 'resolved');
                    }
                }
            } catch (Throwable $e2) {
                error_log('[LiveFleetCronjob] cleanupStaleInterceptions() error: ' . $e2->getMessage());
            }
        }
    }
}
