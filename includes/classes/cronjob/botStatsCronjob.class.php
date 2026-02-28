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

require_once 'includes/classes/cronjob/CronjobTask.interface.php';

class botStatsCronjob implements CronjobTask
{
    public function run(): void
    {
        $db = Database::get();
        $now = time();
        $hour = (int)date('G', $now);
        $today = strtotime('today');
        
        // Hole alle Bots
        $bots = $db->select("SELECT id, id_owner FROM " . DB_PREFIX . "bots");
        
        $statsGenerated = 0;
        
        foreach ($bots as $bot) {
            $botId = (int)$bot['id'];
            $ownerId = (int)$bot['id_owner'];
            
            // Check ob für diese Stunde schon Stats existieren
            $exists = $db->selectSingle(
                "SELECT id FROM " . DB_PREFIX . "bot_stats 
                 WHERE bot_id = :bid AND hour = :h AND timestamp >= :today",
                [
                    ':bid' => $botId, 
                    ':h' => $hour,
                    ':today' => $today
                ]
            );
            
            if ($exists) continue; // Schon geloggt für diese Stunde
            
            // Sammle Aktivitätsdaten
            
            // 1. Gebäude in Bau (aktiv)
            $buildings = (int)$db->selectSingle(
                "SELECT COUNT(*) as c FROM %%PLANETS%% p
                 WHERE p.id_owner = :oid AND p.b_building_id > 0",
                [':oid' => $ownerId],
                'c'
            ) ?: 0;
            
            // 2. Forschung aktiv
            $research = (int)$db->selectSingle(
                "SELECT COUNT(*) as c FROM %%USERS%% u
                 WHERE u.id = :oid AND u.b_tech_id > 0",
                [':oid' => $ownerId],
                'c'
            ) ?: 0;
            
            // 3. Schiffe im Bau
            $shipyard = (int)$db->selectSingle(
                "SELECT COUNT(*) as c FROM %%PLANETS%% p
                 WHERE p.id_owner = :oid AND p.b_hangar_id IS NOT NULL AND p.b_hangar_id != ''",
                [':oid' => $ownerId],
                'c'
            ) ?: 0;
            
            // 4. Aktive Flotten GESAMT
            $fleets = (int)$db->selectSingle(
                "SELECT COUNT(*) as c FROM %%FLEETS%% f
                 WHERE f.fleet_owner = :oid",
                [':oid' => $ownerId],
                'c'
            ) ?: 0;
            
            // 5. Expeditionen (Mission 15)
            $expeditions = (int)$db->selectSingle(
                "SELECT COUNT(*) as c FROM %%FLEETS%% f
                 WHERE f.fleet_owner = :oid AND f.fleet_mission = 15",
                [':oid' => $ownerId],
                'c'
            ) ?: 0;
            
            // 6. Raids (Mission 1 = Attack)
            $raids = (int)$db->selectSingle(
                "SELECT COUNT(*) as c FROM %%FLEETS%% f
                 WHERE f.fleet_owner = :oid AND f.fleet_mission = 1",
                [':oid' => $ownerId],
                'c'
            ) ?: 0;
            
            // 7. Recycler (Mission 8)
            $recycles = (int)$db->selectSingle(
                "SELECT COUNT(*) as c FROM %%FLEETS%% f
                 WHERE f.fleet_owner = :oid AND f.fleet_mission = 8",
                [':oid' => $ownerId],
                'c'
            ) ?: 0;
            
            // Ressourcen geschätzt (aus aktuellen Planetenwerten)
            $resources = $db->selectSingle(
                "SELECT 
                    COALESCE(SUM(p.metal), 0) as metal,
                    COALESCE(SUM(p.crystal), 0) as crystal,
                    COALESCE(SUM(p.deuterium), 0) as deut
                 FROM %%PLANETS%% p
                 WHERE p.id_owner = :oid",
                [':oid' => $ownerId]
            ) ?: ['metal' => 0, 'crystal' => 0, 'deut' => 0];
            
            // Aktionen zusammenzählen
            $actionsTotal = $buildings + $research + $shipyard + $fleets;
            
            // Stats in Datenbank schreiben
            $sql = "INSERT INTO " . DB_PREFIX . "bot_stats 
                    (bot_id, timestamp, hour, 
                     buildings_built, research_completed, ships_built,
                     expeditions_sent, expeditions_success,
                     raids_sent, raids_success,
                     recycle_sent, recycle_collected,
                     metal_gained, crystal_gained, deuterium_gained,
                     actions_total, ticks_completed)
                    VALUES 
                    (:bid, :ts, :h, 
                     :bb, :rc, :sb,
                     :expos, :expos_s,
                     :raids, :raids_s,
                     :rec, :rec_c,
                     :metal, :crystal, :deut,
                     :actions, 1)";
            
            try {
                $db->insert($sql, [
                    ':bid' => $botId,
                    ':ts' => $now,
                    ':h' => $hour,
                    ':bb' => $buildings,
                    ':rc' => $research,
                    ':sb' => $shipyard,
                    ':expos' => $expeditions,
                    ':expos_s' => (int)($expeditions * 0.6), // Geschätzt 60% Erfolg
                    ':raids' => $raids,
                    ':raids_s' => (int)($raids * 0.7), // Geschätzt 70% Erfolg
                    ':rec' => $recycles,
                    ':rec_c' => 0, // Wird später genauer berechnet
                    ':metal' => (int)$resources['metal'],
                    ':crystal' => (int)$resources['crystal'],
                    ':deut' => (int)$resources['deut'],
                    ':actions' => $actionsTotal,
                ]);
                
                $statsGenerated++;
                
            } catch (Throwable $e) {
                // Silent fail - Stats sind nicht kritisch
                continue;
            }
        }
        
        // Cleanup alte Stats (älter als 7 Tage)
        $sevenDaysAgo = $now - (7 * 86400);
        try {
            $db->delete(
                "DELETE FROM " . DB_PREFIX . "bot_stats WHERE timestamp < :cutoff",
                [':cutoff' => $sevenDaysAgo]
            );
        } catch (Throwable $e) {
            // Ignore
        }
    }
}
