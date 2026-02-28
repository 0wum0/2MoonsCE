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

class BotEngine
{
    private Database $db;
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        $this->db = Database::get();
        $this->logFile = $logFile ?: (defined('ROOT_PATH') ? ROOT_PATH : '') . 'bot_actions_debug.txt';

        // BotActions laden falls noch nicht geschehen
        $botActionsPath = (defined('ROOT_PATH') ? ROOT_PATH : '') . 'includes/classes/bot/BotActions.class.php';
        if (!class_exists('BotActions') && file_exists($botActionsPath)) {
            require_once $botActionsPath;
        }
    }

    private function log(string $msg): void
    {
        @file_put_contents($this->logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
    }

    public function runBot(array $botRow, int $universeId): void
    {
        $botId   = (int)($botRow['id'] ?? 0);
        $ownerId = (int)($botRow['id_owner'] ?? 0);
        $typeId  = (int)($botRow['bot_type'] ?? 0);

        if ($botId <= 0 || $ownerId <= 0 || $typeId <= 0) {
            $this->log("SKIP invalid botRow (botId={$botId}, ownerId={$ownerId}, typeId={$typeId})");
            return;
        }

        $settings = $this->getBotSettings($typeId);
        if (!$settings) {
            $this->log("SKIP botId={$botId} ownerId={$ownerId}: settings missing for typeId={$typeId}");
            $this->scheduleNext($botId, 900);
            return;
        }

        if (empty((int)($settings['enabled'] ?? 1))) {
            $this->log("SKIP botId={$botId}: disabled by setting");
            $this->scheduleNext($botId, 1800);
            return;
        }

        $user = $this->getUser($ownerId);
        if (!$user) {
            $this->log("SKIP botId={$botId}: user missing ownerId={$ownerId}");
            $this->scheduleNext($botId, 1200);
            return;
        }

        $planet = $this->getMainPlanet($ownerId, $universeId);
        if (!$planet) {
            $this->log("SKIP botId={$botId}: main planet missing ownerId={$ownerId} uni={$universeId}");
            $this->scheduleNext($botId, 1200);
            return;
        }

        // Heartbeat like a real player
        $this->touchActivity($ownerId, $botId);

        $this->log("BOT#{$botId} ({$user['username']}) type={$typeId} tick start");

        // 1) Player-like queued actions first (economy)
        $didAny = false;
        $maxActions = max(1, (int)($settings['max_actions_per_tick'] ?? 2));
        $actionsLeft = $maxActions;

        if ($actionsLeft > 0) {
            $didAny = $this->doEconomyActions($user, $planet, $settings, $actionsLeft) || $didAny;
        }

        // 2) Fleet actions (PVE/PVP)
        if ($actionsLeft > 0) {
            $didAny = $this->doFleetActions($user, $planet, $botRow, $settings, $actionsLeft) || $didAny;
        }

        // Persist (BotActions changes are in-memory; we persist user+planet queues safely)
        $this->persistUserAndPlanet($user, $planet);

        // Next schedule
        $delay = $this->getNextDelaySeconds($settings, $didAny);
        $this->scheduleNext($botId, $delay);

        $this->log("BOT#{$botId} ({$user['username']}) tick end didAny=" . ($didAny ? "1" : "0") . " nextIn={$delay}s");
    }

    // ---------------------------------------------------------------------
    // Economy / Queues (Build / Research / Shipyard)
    // ---------------------------------------------------------------------

    private function doEconomyActions(array &$USER, array &$PLANET, array $settings, int &$actionsLeft): bool
    {
        if ($actionsLeft <= 0) {
            return false;
        }

        // Feature toggles
        $canBuild    = !empty((int)($settings['can_build'] ?? 1));
        $canResearch = !empty((int)($settings['can_research'] ?? 1));
        $canShipyard = !empty((int)($settings['can_shipyard'] ?? 1));

        // Vacation mode => no economy actions
        if (function_exists('IsVacationMode') && IsVacationMode($USER)) {
            $this->log("ECONOMY skip: vacation mode");
            return false;
        }

        if (!class_exists('BotActions')) {
            $this->log("ECONOMY skip: BotActions class missing");
            return false;
        }

        $did = false;

        // Build priority: Energy -> Mines -> Storage -> Buildings
        if ($canBuild && $actionsLeft > 0) {
            $did = $this->tryUpgradeEnergyOrMines($USER, $PLANET, $settings);
            if ($did) {
                $actionsLeft--;
                return true;
            }
        }

        // Research priority (basic “player-ish”)
        if ($canResearch && $actionsLeft > 0) {
            $did = $this->tryResearchPriority($USER, $PLANET, $settings);
            if ($did) {
                $actionsLeft--;
                return true;
            }
        }

        // Shipyard priority (build cargos / recyclers / probes)
        if ($canShipyard && $actionsLeft > 0) {
            $did = $this->tryShipyardPriority($USER, $PLANET, $settings);
            if ($did) {
                $actionsLeft--;
                return true;
            }
        }

        return false;
    }

    private function tryUpgradeEnergyOrMines(array &$USER, array &$PLANET, array $settings): bool
    {
        // Standard 2Moons IDs:
        // Mines: 1 Metal, 2 Crystal, 3 Deut
        // Energy: 4 Solar Plant, 12 Fusion Reactor
        // Storages: 22 Metal, 23 Crystal, 24 Deut
        // Tech: 31 Lab, 21 Shipyard, 14 Factory, 15 Robo

        // If fields full or queue full, BotActions will reject.
        $energyNow = (float)($PLANET['energy_max'] ?? 0);
        $energyUse = (float)($PLANET['energy_used'] ?? 0);
        $energyFree = $energyNow - $energyUse;

        // If energy negative/low => solar first
        if ($energyFree < 0) {
            $ok = BotActions::tryQueueBuilding($USER, $PLANET, 4, true);
            if ($ok) {
                $this->log("BUILD queued: Solar Plant (4)");
                return true;
            }
            // fallback fusion if solar locked
            $ok = BotActions::tryQueueBuilding($USER, $PLANET, 12, true);
            if ($ok) {
                $this->log("BUILD queued: Fusion Reactor (12)");
                return true;
            }
        }

        // Mines balancing:
        $m = (int)($PLANET['metal_mine'] ?? ($PLANET['metal_mine'] ?? 0));
        // In 2Moons planet fields use $resource mapping; we rely on BotActions to use $resource.
        // We'll pick based on current levels via $resource in BotActions itself, but we need IDs here.
        $minePick = $this->pickMineToUpgrade($PLANET);

        if ($minePick > 0) {
            $ok = BotActions::tryQueueBuilding($USER, $PLANET, $minePick, true);
            if ($ok) {
                $this->log("BUILD queued: Mine {$minePick}");
                return true;
            }
        }

        // Storage if resources close to capacity (simple heuristic)
        $ok = $this->tryStorageIfNeeded($USER, $PLANET);
        if ($ok) {
            return true;
        }

        // If nothing else, improve infrastructure (Lab, Shipyard, Robo)
        foreach ([31, 14, 15, 21] as $bid) {
            $ok = BotActions::tryQueueBuilding($USER, $PLANET, $bid, true);
            if ($ok) {
                $this->log("BUILD queued: Infra {$bid}");
                return true;
            }
        }

        return false;
    }

    private function pickMineToUpgrade(array $PLANET): int
    {
        // Simple and stable:
        // - Prefer Metal early
        // - Keep Crystal a bit behind
        // - Deut slightly behind Crystal
        // Without $resource mapping here, we use known IDs directly.
        // BotActions will ensure affordability and access.

        $mLvl = $this->getPlanetBuildingLevel($PLANET, 1);
        $cLvl = $this->getPlanetBuildingLevel($PLANET, 2);
        $dLvl = $this->getPlanetBuildingLevel($PLANET, 3);

        // keep Crystal within -2 of Metal
        if ($cLvl < $mLvl - 2) return 2;

        // keep Deut within -2 of Crystal
        if ($dLvl < $cLvl - 2) return 3;

        // otherwise metal
        return 1;
    }

    private function tryStorageIfNeeded(array &$USER, array &$PLANET): bool
    {
        // If current resources > 85% capacity, upgrade storage.
        $capM = (float)($PLANET['metal_max'] ?? 0);
        $capC = (float)($PLANET['crystal_max'] ?? 0);
        $capD = (float)($PLANET['deuterium_max'] ?? 0);

        $m = (float)($PLANET['metal'] ?? 0);
        $c = (float)($PLANET['crystal'] ?? 0);
        $d = (float)($PLANET['deuterium'] ?? 0);

        if ($capM > 0 && $m > $capM * 0.85) {
            $ok = BotActions::tryQueueBuilding($USER, $PLANET, 22, true);
            if ($ok) {
                $this->log("BUILD queued: Metal Storage (22)");
                return true;
            }
        }
        if ($capC > 0 && $c > $capC * 0.85) {
            $ok = BotActions::tryQueueBuilding($USER, $PLANET, 23, true);
            if ($ok) {
                $this->log("BUILD queued: Crystal Storage (23)");
                return true;
            }
        }
        if ($capD > 0 && $d > $capD * 0.85) {
            $ok = BotActions::tryQueueBuilding($USER, $PLANET, 24, true);
            if ($ok) {
                $this->log("BUILD queued: Deut Storage (24)");
                return true;
            }
        }

        return false;
    }

    private function tryResearchPriority(array &$USER, array &$PLANET, array $settings): bool
    {
        // Research IDs (2Moons defaults):
        // 106 Espionage, 108 Computer, 109 Weapons, 110 Shields, 111 Armor
        // 113 Energy, 115 Combustion, 117 Impulse, 118 Hyperspace Drive
        // 120 Laser, 121 Ion, 122 Plasma
        // 124 Astrophysics, 199 Grav
        // We'll keep it basic and "player-ish": Espionage -> Computer -> Energy -> Drives -> Astro

        $priority = [106, 108, 113, 115, 117, 118, 124, 109, 110, 111, 120, 121, 122];

        foreach ($priority as $tid) {
            $ok = BotActions::tryQueueResearch($USER, $PLANET, $tid);
            if ($ok) {
                $this->log("RESEARCH queued: Tech {$tid}");
                return true;
            }
        }

        return false;
    }

    private function tryShipyardPriority(array &$USER, array &$PLANET, array $settings): bool
    {
        // Ships (2Moons):
        // 202 Small Cargo, 203 Large Cargo, 209 Recycler, 210 Spy Probe, 204 Light Fighter, 205 Heavy Fighter
        // We ensure bot can do expeditions and raids: cargos + recycler + probes + few fighters

        $todo = [];

        $todo[210] = 5;     // probes
        $todo[202] = 25;    // small cargos
        $todo[203] = 5;     // large cargos
        $todo[209] = 10;    // recyclers
        $todo[204] = 15;    // light fighters
        $todo[205] = 5;     // heavy fighters

        $ok = BotActions::tryQueueShipyard($USER, $PLANET, $todo);
        if ($ok) {
            $this->log("SHIPYARD queued: base fleet package");
            return true;
        }

        return false;
    }

    private function getPlanetBuildingLevel(array $PLANET, int $elementId): int
    {
        global $resource;
        if (!isset($resource[$elementId])) {
            return 0;
        }
        return (int)($PLANET[$resource[$elementId]] ?? 0);
    }

    // ---------------------------------------------------------------------
    // Fleet actions (Expedition / Recycle / Raid)
    // ---------------------------------------------------------------------

    private function doFleetActions(array $USER, array $PLANET, array $botRow, array $settings, int &$actionsLeft): bool
    {
        if ($actionsLeft <= 0) {
            return false;
        }

        if (function_exists('IsVacationMode') && IsVacationMode($USER)) {
            $this->log("FLEET skip: vacation mode");
            return false;
        }

        $pve = !empty((int)($settings['pve_enabled'] ?? 1));
        $pvp = !empty((int)($settings['pvp_enabled'] ?? 0));

        // Fleet slots
        $activeSlots = FleetFunctions::GetCurrentFleets($USER['id']);
        $maxSlots    = FleetFunctions::GetMaxFleetSlots($USER);
        if ($activeSlots >= $maxSlots) {
            $this->log("FLEET skip: no fleet slots ({$activeSlots}/{$maxSlots})");
            return false;
        }

        // Priority: Recycle (if debris) -> Expedition -> Raid (if enabled)
        // (Raid last, damit Bot nicht komplett aggressiv wird ohne Eco)
        $did = false;

        if ($pve && !empty((int)($settings['can_recycle'] ?? 1)) && $actionsLeft > 0) {
            $did = $this->sendRecycleOnOwnDebris($USER, $PLANET, $botRow, $settings);
            if ($did) {
                $actionsLeft--;
                return true;
            }
        }

        if ($pve && !empty((int)($settings['can_expedition'] ?? 1)) && $actionsLeft > 0) {
            $did = $this->sendExpedition($USER, $PLANET, $botRow, $settings);
            if ($did) {
                $actionsLeft--;
                return true;
            }
        }

        if ($pvp && !empty((int)($settings['can_raid'] ?? 0)) && $actionsLeft > 0) {
            $did = $this->sendRaid($USER, $PLANET, $settings);
            if ($did) {
                $actionsLeft--;
                return true;
            }
        }

        return false;
    }

    private function sendRaid(array $USER, array $PLANET, array $settings): bool
    {
        global $resource;

        // Find a target planet (simple, safe, stable):
        // - Not self
        // - Not in vacation
        // - Optional: inactive only
        // - Optional: not same ally (unless allowed)
        // - Has enough loot
        $target = $this->findRaidTarget($USER, $PLANET, $settings);
        if (!$target) {
            $this->log("RAID skip: no suitable target");
            return false;
        }

        // Build fleet for raid (cargo + some fighters)
        $fleetArray = [];

        $want = [
            202 => 50, // small cargo
            203 => 10, // large cargo
            204 => 20, // LF
            205 => 10, // HF
            210 => 3,  // probes
        ];

        foreach ($want as $sid => $cnt) {
            if (!isset($resource[$sid])) continue;
            $avail = (int)($PLANET[$resource[$sid]] ?? 0);
            $use = min($avail, $cnt);
            if ($use > 0) $fleetArray[$sid] = $use;
        }

        if (empty($fleetArray)) {
            $this->log("RAID fail: no ships available");
            return false;
        }

        $startGalaxy = (int)$PLANET['galaxy'];
        $startSystem = (int)$PLANET['system'];
        $startPlanet = (int)$PLANET['planet'];
        $startType   = (int)$PLANET['planet_type'];

        $endGalaxy = (int)$target['galaxy'];
        $endSystem = (int)$target['system'];
        $endPlanet = (int)$target['planet'];
        $endType   = 1;

        $mission = 1; // ATTACK

        $speedFactor   = FleetFunctions::GetGameSpeedFactor();
        $distance      = FleetFunctions::GetTargetDistance([$startGalaxy, $startSystem, $startPlanet], [$endGalaxy, $endSystem, $endPlanet]);
        $maxFleetSpeed = FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER);
        $duration      = FleetFunctions::GetMissionDuration(10, $maxFleetSpeed, $distance, $speedFactor, $USER);
        $consumption   = FleetFunctions::GetFleetConsumption($fleetArray, $duration, $distance, $USER, $speedFactor);

        $maxFlight = (int)($settings['raid_max_flight_seconds'] ?? 14400);
        if ($duration > $maxFlight) {
            $this->log("RAID skip: flight too long duration={$duration}s max={$maxFlight}s");
            return false;
        }

        if ($consumption > (float)$PLANET['deuterium']) {
            $this->log("RAID skip: not enough deuterium (need={$consumption}, have={$PLANET['deuterium']})");
            return false;
        }

        // No resources transported on attack
        $fleetResource = [901 => 0, 902 => 0, 903 => 0];

        $fleetStartTime = TIMESTAMP + $duration;
        $fleetStayTime  = $fleetStartTime;
        $fleetEndTime   = $fleetStayTime + $duration;

        $this->log("RAID send: {$USER['username']} [{$startGalaxy}:{$startSystem}:{$startPlanet}] -> target [{$endGalaxy}:{$endSystem}:{$endPlanet}] ships=" . json_encode($fleetArray));

        try {
            FleetFunctions::sendFleet(
                $fleetArray,
                $mission,
                (int)$USER['id'],
                (int)$PLANET['id'],
                $startGalaxy,
                $startSystem,
                $startPlanet,
                $startType,
                (int)$target['id_owner'],
                (int)$target['id'],
                $endGalaxy,
                $endSystem,
                $endPlanet,
                $endType,
                $fleetResource,
                $fleetStartTime,
                $fleetStayTime,
                $fleetEndTime,
                0
            );

            $this->subtractPlanetDeuterium((int)$PLANET['id'], $consumption);
            return true;

        } catch (Throwable $t) {
            $this->log("RAID failed: " . $t->getMessage() . " @ " . $t->getFile() . ":" . $t->getLine());
            return false;
        }
    }

    private function findRaidTarget(array $USER, array $PLANET, array $settings): ?array
    {
        $minMetal   = (int)($settings['raid_min_cargo_metal'] ?? 20000);
        $minCrystal = (int)($settings['raid_min_cargo_crystal'] ?? 10000);
        $minGain    = (int)($settings['raid_min_gain'] ?? 50000);

        $inactiveOnly   = !empty((int)($settings['raid_inactive_only'] ?? 1));
        $allowSameAlly  = !empty((int)($settings['raid_allow_same_ally'] ?? 0));
        $maxRankDiff    = (int)($settings['raid_max_rank_diff'] ?? 250);

        $myAlly = (int)($USER['ally_id'] ?? 0);
        $myId   = (int)$USER['id'];

        // Basic safe query:
        // - exclude self + bots can also attack humans
        // - exclude vacation
        // - loot heuristic: planet has enough metal/crystal
        // - prefer inactive if enabled (onlinetime older)
        // Note: This is intentionally simple & stable. You can extend later with espionage reports etc.

        $conds = [];
        $params = [];

        $conds[] = "p.id_owner != :me";
        $params[':me'] = $myId;

        $conds[] = "u.urlaubs_modus = 0";
        $conds[] = "u.banaday = 0";

        if (!$allowSameAlly && $myAlly > 0) {
            $conds[] = "(u.ally_id = 0 OR u.ally_id != :ally)";
            $params[':ally'] = $myAlly;
        }

        if ($inactiveOnly) {
            // inactive: last online older than 7 days
            $conds[] = "u.onlinetime < :inactiveTime";
            $params[':inactiveTime'] = time() - (7 * 86400);
        }

        // loot
        $conds[] = "(p.metal + p.crystal) >= :minGain";
        $params[':minGain'] = $minGain;

        $conds[] = "p.metal >= :minMetal";
        $conds[] = "p.crystal >= :minCrystal";
        $params[':minMetal'] = $minMetal;
        $params[':minCrystal'] = $minCrystal;

        // rank diff (needs STATPOINTS table in 2Moons: %%STATPOINTS%%)
        // We'll do a soft join; if table absent, we skip rank filter by try/catch.
        // Defense columns in 2Moons (IDs 401-412 mapped to planet fields)
        // misil_launcher=401, small_laser=402, big_laser=403, gauss_canyon=404,
        // ionic_canyon=405, buster_canyon=406, small_protection_shield=407,
        // planet_protector=408, big_protection_shield=409, graviton_canyon=410
        // interceptor_misil=411, interplanetary_misil=412
        // We skip targets with ANY active defense structures
        $conds[] = "(p.misil_launcher + p.small_laser + p.big_laser + p.gauss_canyon
                    + p.ionic_canyon + p.buster_canyon + p.small_protection_shield
                    + p.planet_protector + p.big_protection_shield + p.graviton_canyon) = 0";

        $where = implode(" AND ", $conds);

        $sql = "SELECT p.id, p.id_owner, p.galaxy, p.system, p.planet, p.metal, p.crystal, u.username, u.ally_id, u.onlinetime
                FROM %%PLANETS%% p
                INNER JOIN %%USERS%% u ON u.id = p.id_owner
                WHERE {$where}
                ORDER BY (p.metal + p.crystal) DESC
                LIMIT 25;";

        try {
            $rows = $this->db->select($sql, $params);
        } catch (Throwable $t) {
            $this->log("findRaidTarget query failed: " . $t->getMessage());
            return null;
        }

        if (empty($rows)) {
            $this->log("RAID skip: no target found matching criteria (resources/vacation/defense filter)");
            return null;
        }

        // Optional: rank diff filter (best effort)
        $myRank = $this->getUserRankPoints($myId);
        foreach ($rows as $r) {
            if ($maxRankDiff > 0 && $myRank !== null) {
                $theirRank = $this->getUserRankPoints((int)$r['id_owner']);
                if ($theirRank !== null) {
                    if (abs($theirRank - $myRank) > $maxRankDiff) {
                        continue;
                    }
                }
            }

            // Quick distance/flight sanity via coords
            $dist = FleetFunctions::GetTargetDistance(
                [(int)$PLANET['galaxy'], (int)$PLANET['system'], (int)$PLANET['planet']],
                [(int)$r['galaxy'], (int)$r['system'], (int)$r['planet']]
            );

            // avoid “same system spam” only if you want; here we accept all.
            return $r;
        }

        return null;
    }

    private function getUserRankPoints(int $userId): ?int
    {
        // Best-effort: if statpoints exists, use it. Otherwise return null.
        try {
            $row = $this->db->selectSingle(
                "SELECT total_points FROM %%STATPOINTS%% WHERE id_owner = :id AND stat_type = 1 LIMIT 1;",
                [':id' => $userId]
            );
            if (is_array($row) && isset($row['total_points'])) {
                return (int)$row['total_points'];
            }
        } catch (Throwable $t) {
            return null;
        }
        return null;
    }

    // ---------------------------------------------------------------------
    // Existing Fleet actions from your previous code (kept, unchanged core)
    // ---------------------------------------------------------------------

    private function sendExpedition(array $USER, array $PLANET, array $botRow, array $settings): bool
    {
        global $resource;

        if (function_exists('IsVacationMode') && IsVacationMode($USER)) {
            $this->log("EXPEDITION skip: vacation mode.");
            return false;
        }

        $activeExpeditions = FleetFunctions::GetCurrentFleets($USER['id'], 15, true);
        $maxExpeditions    = FleetFunctions::getExpeditionLimit($USER);
        if ($activeExpeditions >= $maxExpeditions) {
            $this->log("EXPEDITION skip: no expedition slot ({$activeExpeditions}/{$maxExpeditions}).");
            return false;
        }

        $activeSlots = FleetFunctions::GetCurrentFleets($USER['id']);
        $maxSlots    = FleetFunctions::GetMaxFleetSlots($USER);
        if ($activeSlots >= $maxSlots) {
            $this->log("EXPEDITION skip: no fleet slots ({$activeSlots}/{$maxSlots}).");
            return false;
        }

        $fleetArray = $this->getBotFleetClampedToPlanet($PLANET, $botRow, $settings);
        if (empty($fleetArray)) {
            $fallback = [202 => 50, 210 => 10, 209 => 10, 204 => 5];
            foreach ($fallback as $sid => $cnt) {
                if (!isset($resource[$sid])) continue;
                $avail = (int)($PLANET[$resource[$sid]] ?? 0);
                $use = min($avail, $cnt);
                if ($use > 0) $fleetArray[$sid] = $use;
            }
        }

        if (empty($fleetArray)) {
            $this->log("EXPEDITION fail: no ships available.");
            return false;
        }

        $cfg = Config::get();

        $startGalaxy = (int)$PLANET['galaxy'];
        $startSystem = (int)$PLANET['system'];
        $startPlanet = (int)$PLANET['planet'];
        $startType   = (int)$PLANET['planet_type'];

        $endGalaxy   = $startGalaxy;
        $endSystem   = $startSystem;
        $endPlanet   = (int)$cfg->max_planets + 1;
        $endType     = 1;

        $mission = 15;

        $speedFactor   = FleetFunctions::GetGameSpeedFactor();
        $distance      = FleetFunctions::GetTargetDistance([$startGalaxy, $startSystem, $startPlanet], [$endGalaxy, $endSystem, $endPlanet]);
        $maxFleetSpeed = FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER);
        $duration      = FleetFunctions::GetMissionDuration(10, $maxFleetSpeed, $distance, $speedFactor, $USER);
        $consumption   = FleetFunctions::GetFleetConsumption($fleetArray, $duration, $distance, $USER, $speedFactor);

        if ($consumption > (float)$PLANET['deuterium']) {
            $this->log("EXPEDITION skip: not enough deuterium (need={$consumption}, have={$PLANET['deuterium']}).");
            return false;
        }

        $fleetResource = [901 => 0, 902 => 0, 903 => 0];

        $fleetStartTime = TIMESTAMP + $duration;
        $fleetStayTime  = $fleetStartTime;
        $fleetEndTime   = $fleetStayTime + $duration;

        $this->log("EXPEDITION send: {$USER['username']} from [{$startGalaxy}:{$startSystem}:{$startPlanet}] -> [{$endGalaxy}:{$endSystem}:{$endPlanet}] ships=" . json_encode($fleetArray));

        try {
            FleetFunctions::sendFleet(
                $fleetArray,
                $mission,
                (int)$USER['id'],
                (int)$PLANET['id'],
                $startGalaxy,
                $startSystem,
                $startPlanet,
                $startType,
                0,
                0,
                $endGalaxy,
                $endSystem,
                $endPlanet,
                $endType,
                $fleetResource,
                $fleetStartTime,
                $fleetStayTime,
                $fleetEndTime,
                0
            );

            $this->subtractPlanetDeuterium((int)$PLANET['id'], $consumption);
            return true;

        } catch (Throwable $t) {
            $this->log("EXPEDITION failed: " . $t->getMessage() . " @ " . $t->getFile() . ":" . $t->getLine());
            return false;
        }
    }

    private function sendRecycleOnOwnDebris(array $USER, array $PLANET, array $botRow, array $settings): bool
    {
        global $resource, $pricelist;

        if (function_exists('IsVacationMode') && IsVacationMode($USER)) {
            $this->log("RECYCLE skip: vacation mode.");
            return false;
        }

        $activeSlots = FleetFunctions::GetCurrentFleets($USER['id']);
        $maxSlots    = FleetFunctions::GetMaxFleetSlots($USER);
        if ($activeSlots >= $maxSlots) {
            $this->log("RECYCLE skip: no fleet slots ({$activeSlots}/{$maxSlots}).");
            return false;
        }

        $debrisMetal   = (float)($PLANET['der_metal'] ?? 0);
        $debrisCrystal = (float)($PLANET['der_crystal'] ?? 0);
        $totalDebris   = $debrisMetal + $debrisCrystal;

        if ($totalDebris <= 0) {
            return false;
        }

        $fleetArray = [];
        $recIDs = [219, 209];
        foreach ($recIDs as $sid) {
            if (!isset($resource[$sid])) continue;

            $avail = (int)($PLANET[$resource[$sid]] ?? 0);
            if ($avail <= 0) continue;

            $cap = (float)($pricelist[$sid]['capacity'] ?? 0);
            if ($cap <= 0) continue;

            $need = (int)min($avail, (int)ceil($totalDebris / $cap));
            if ($need > 0) {
                $fleetArray[$sid] = $need;
                $totalDebris -= ($need * $cap);
            }

            if ($totalDebris <= 0) break;
        }

        $fleetArray = array_filter($fleetArray);
        if (empty($fleetArray)) {
            return false;
        }

        $startGalaxy = (int)$PLANET['galaxy'];
        $startSystem = (int)$PLANET['system'];
        $startPlanet = (int)$PLANET['planet'];
        $startType   = (int)$PLANET['planet_type'];

        $endGalaxy   = $startGalaxy;
        $endSystem   = $startSystem;
        $endPlanet   = $startPlanet;
        $endType     = 2;

        $mission = 8;

        $speedFactor   = FleetFunctions::GetGameSpeedFactor();
        $distance      = FleetFunctions::GetTargetDistance([$startGalaxy, $startSystem, $startPlanet], [$endGalaxy, $endSystem, $endPlanet]);
        $maxFleetSpeed = FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER);
        $duration      = FleetFunctions::GetMissionDuration(10, $maxFleetSpeed, $distance, $speedFactor, $USER);
        $consumption   = FleetFunctions::GetFleetConsumption($fleetArray, $duration, $distance, $USER, $speedFactor);

        if ($consumption > (float)$PLANET['deuterium']) {
            $this->log("RECYCLE skip: not enough deuterium (need={$consumption}, have={$PLANET['deuterium']}).");
            return false;
        }

        $fleetResource = [901 => 0, 902 => 0, 903 => 0];

        $fleetStartTime = TIMESTAMP + $duration;
        $fleetStayTime  = $fleetStartTime;
        $fleetEndTime   = $fleetStayTime + $duration;

        $this->log("RECYCLE send: {$USER['username']} at [{$startGalaxy}:{$startSystem}:{$startPlanet}] ships=" . json_encode($fleetArray));

        try {
            FleetFunctions::sendFleet(
                $fleetArray,
                $mission,
                (int)$USER['id'],
                (int)$PLANET['id'],
                $startGalaxy,
                $startSystem,
                $startPlanet,
                $startType,
                0,            // fleet_target_owner = 0 für Debris (kein Besitzer)
                0,            // fleet_target_id = 0 für Debris-Feld
                $endGalaxy,
                $endSystem,
                $endPlanet,
                $endType,
                $fleetResource,
                $fleetStartTime,
                $fleetStayTime,
                $fleetEndTime,
                0
            );

            $this->subtractPlanetDeuterium((int)$PLANET['id'], $consumption);
            return true;

        } catch (Throwable $t) {
            $this->log("RECYCLE failed: " . $t->getMessage() . " @ " . $t->getFile() . ":" . $t->getLine());
            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function getNextDelaySeconds(array $settings, bool $success): int
    {
        $minSpace  = (int)($settings['min_fleet_seconds_in_space'] ?? 3600);
        $maxSpace  = (int)($settings['max_fleet_seconds_in_space'] ?? 7200);

        $minPlanet = (int)($settings['min_fleet_seconds_on_planet'] ?? 600);
        $maxPlanet = (int)($settings['max_fleet_seconds_on_planet'] ?? 3600);

        if ($success) {
            $min = max(120, $minSpace);
            $max = max($min, $maxSpace);
        } else {
            $min = max(180, $minPlanet);
            $max = max($min, $maxPlanet);
        }

        return random_int($min, $max);
    }

    private function getBotFleetClampedToPlanet(array $PLANET, array $botRow, array $settings): array
    {
        global $resource;

        $ships = $this->getBotShipsNormalized($botRow, $settings);
        if (empty($ships)) {
            return [];
        }

        $fleetArray = [];
        foreach ($ships as $sid => $cnt) {
            $sid = (int)$sid;
            $cnt = (int)$cnt;
            if ($sid <= 0 || $cnt <= 0) continue;
            if (!isset($resource[$sid])) continue;

            $avail = (int)($PLANET[$resource[$sid]] ?? 0);
            $use = min($avail, $cnt);
            if ($use > 0) $fleetArray[$sid] = $use;
        }

        return $fleetArray;
    }

    private function subtractPlanetDeuterium(int $planetId, float $consumption): void
    {
        if ($planetId <= 0 || $consumption <= 0) return;

        try {
            $sql = "UPDATE %%PLANETS%% SET deuterium = GREATEST(0, deuterium - :c) WHERE id = :pid;";
            $this->db->update($sql, [':c' => $consumption, ':pid' => $planetId]);
        } catch (Throwable $t) {
            $this->log("subtractPlanetDeuterium failed: " . $t->getMessage());
        }
    }

    private function persistUserAndPlanet(array $USER, array $PLANET): void
    {
        // Persist only queue fields we touched (safe)
        try {
            $this->db->update(
                "UPDATE %%USERS%% SET
                    b_tech_queue = :q,
                    b_tech = :bt,
                    b_tech_id = :bid,
                    b_tech_planet = :bp
                 WHERE id = :id;",
                [
                    ':q'  => (string)($USER['b_tech_queue'] ?? ''),
                    ':bt' => (int)($USER['b_tech'] ?? 0),
                    ':bid'=> (int)($USER['b_tech_id'] ?? 0),
                    ':bp' => (int)($USER['b_tech_planet'] ?? 0),
                    ':id' => (int)$USER['id'],
                ]
            );

            $this->db->update(
                "UPDATE %%PLANETS%% SET
                    b_building_id = :bqid,
                    b_building = :bb,
                    b_hangar_id = :hq
                 WHERE id = :pid;",
                [
                    ':bqid' => (string)($PLANET['b_building_id'] ?? ''),
                    ':bb'   => (int)($PLANET['b_building'] ?? 0),
                    ':hq'   => (string)($PLANET['b_hangar_id'] ?? ''),
                    ':pid'  => (int)$PLANET['id'],
                ]
            );
        } catch (Throwable $t) {
            $this->log("persistUserAndPlanet failed: " . $t->getMessage());
        }
    }

    // ---------------------------------------------------------------------
    // Data access
    // ---------------------------------------------------------------------

    private function getBotSettings(int $typeId): ?array
    {
        $sql = "SELECT * FROM " . DB_PREFIX . "bot_setting WHERE id = :id";
        $row = $this->db->selectSingle($sql, [':id' => $typeId]);
        return is_array($row) ? $row : null;
    }

    private function getUser(int $userId): ?array
    {
        $sql = "SELECT * FROM %%USERS%% WHERE id = :id;";
        $row = $this->db->selectSingle($sql, [':id' => $userId]);
        if (!is_array($row)) {
            return null;
        }

        // FleetFunctions + BuildFunctions erwarten $USER['factor'] - wird normalerweise
        // beim Spieler-Login durch den Factor-Calculator befuellt.
        // Bots laden den User direkt aus DB, daher manuell mit Defaults befuellen.
        if (!isset($row['factor']) || !is_array($row['factor'])) {
            $row['factor'] = [
                'FleetSlots'    => 0,   // Bonus Fleet-Slots
                'FlyTime'       => 0,   // Flugzeit-Modifier
                'BuildTime'     => 0,   // Gebaeude-Bauzeit-Modifier
                'ShipTime'      => 0,   // Schiffbau-Zeit-Modifier
                'DefensiveTime' => 0,   // Verteidigungsbau-Zeit-Modifier
                'ResearchTime'  => 0,   // Forschungs-Zeit-Modifier
                'Resource'      => 0,   // Ressourcen-Produktion-Modifier
                'Energy'        => 0,   // Energie-Produktion-Modifier
            ];
        }

        return $row;
    }

    private function getMainPlanet(int $ownerId, int $universeId): ?array
    {
        $sql = "SELECT * FROM %%PLANETS%% WHERE id_owner = :uid ORDER BY id ASC LIMIT 1;";
        $row = $this->db->selectSingle($sql, [':uid' => $ownerId]);
        return is_array($row) ? $row : null;
    }

    private function touchActivity(int $userId, int $botId): void
    {
        try {
            $now = time();
            $this->db->update(
                "UPDATE %%USERS%% SET onlinetime = :t WHERE id = :id;",
                [':t' => $now, ':id' => $userId]
            );
            $this->db->update(
                "UPDATE " . DB_PREFIX . "bots SET last_login = :t WHERE id = :bid;",
                [':t' => $now, ':bid' => $botId]
            );
        } catch (Throwable $t) {
            $this->log("touchActivity failed: " . $t->getMessage());
        }
    }

    private function scheduleNext(int $botId, int $delaySeconds): void
    {
        $delaySeconds = max(60, $delaySeconds);
        $next = time() + $delaySeconds;

        try {
            $this->db->update(
                "UPDATE " . DB_PREFIX . "bots SET next_fleet_action = :n WHERE id = :id;",
                [':n' => $next, ':id' => $botId]
            );
        } catch (Throwable $t) {
            $this->log("scheduleNext failed: " . $t->getMessage());
        }
    }

    private function getBotShipsNormalized(array $botRow, array $settings): array
    {
        $ships = [];

        $rawBot = (string)($botRow['ships_array'] ?? '');
        $ships  = $this->parseShipsArray($rawBot);

        if (empty($ships)) {
            $rawSet = (string)($settings['ships_array'] ?? '');
            $ships  = $this->parseShipsArray($rawSet);
        }

        $final = [];
        foreach ($ships as $id => $amount) {
            $id = (int)$id;
            $amount = (int)$amount;
            if ($id > 0 && $amount > 0) $final[$id] = $amount;
        }

        return $final;
    }

    private function parseShipsArray(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];

        try {
            $tmp = @unserialize($raw, ['allowed_classes' => false]);
            if (!is_array($tmp)) return [];

            $isDirect = true;
            foreach ($tmp as $k => $v) {
                if (!is_numeric($k)) { $isDirect = false; break; }
            }

            if ($isDirect) return $tmp;

            $out = [];
            foreach ($tmp as $row) {
                if (!is_array($row)) continue;
                $id = (int)($row['id'] ?? 0);
                $amount = (int)($row['amount'] ?? 0);
                if ($id > 0 && $amount > 0) $out[$id] = ($out[$id] ?? 0) + $amount;
            }
            return $out;

        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Track bot statistics for analytics
     */
    private function trackStats(int $botId, string $actionType, bool $success = true, array $details = []): void
    {
        try {
            $now = time();
            $hour = (int)date('G', $now);

            // Log in activity table
            $sql = "INSERT INTO " . DB_PREFIX . "bot_activity_log 
                    (bot_id, timestamp, action_type, success, details) 
                    VALUES (:bid, :ts, :type, :success, :details)";
            
            $this->db->insert($sql, [
                ':bid' => $botId,
                ':ts' => $now,
                ':type' => $actionType,
                ':success' => $success ? 1 : 0,
                ':details' => json_encode($details),
            ]);

            // Update hourly stats
            $col = '';
            switch ($actionType) {
                case 'build': $col = 'buildings_built'; break;
                case 'research': $col = 'research_completed'; break;
                case 'shipyard': $col = 'ships_built'; break;
                case 'expedition': $col = $success ? 'expeditions_success' : 'expeditions_sent'; break;
                case 'raid': $col = $success ? 'raids_success' : 'raids_sent'; break;
                case 'recycle': $col = 'recycle_sent'; break;
            }

            if ($col) {
                $sql = "INSERT INTO " . DB_PREFIX . "bot_stats 
                        (bot_id, timestamp, hour, {$col}, actions_total, ticks_completed) 
                        VALUES (:bid, :ts, :h, 1, 1, 1)
                        ON DUPLICATE KEY UPDATE 
                        {$col} = {$col} + 1, 
                        actions_total = actions_total + 1";
                
                $this->db->insert($sql, [':bid' => $botId, ':ts' => $now, ':h' => $hour]);
            }

        } catch (Throwable $e) {
            // Silent fail - stats nicht kritisch
        }
    }
}