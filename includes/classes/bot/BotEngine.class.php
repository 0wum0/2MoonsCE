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
        $personalityName = (string)($botRow['personality'] ?? 'balanced');

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
        
        // Load Personality
        $personalityClass = (defined('ROOT_PATH') ? ROOT_PATH : '') . 'includes/classes/bot/BotPersonality.class.php';
        if (file_exists($personalityClass)) {
            require_once $personalityClass;
            $personality = BotPersonality::get($personalityName);
        } else {
            $personality = null; // Fallback wenn Class fehlt
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

        $this->log("BOT#{$botId} ({$user['username']}) type={$typeId} personality={$personalityName} tick start");

        // 0) Alliance management — join or create a bot-alliance (no-op if already in one)
        $this->doAllianceActions($user, $universeId);

        // 1) Player-like queued actions first (economy)
        $didAny = false;
        $maxActions = max(1, (int)($settings['max_actions_per_tick'] ?? 4));
        $actionsLeft = $maxActions;

        if ($actionsLeft > 0) {
            $didAny = $this->doEconomyActions($user, $planet, $settings, $personality, $actionsLeft) || $didAny;
        }

        // 2) Fleet actions (PVE/PVP)
        if ($actionsLeft > 0) {
            $didAny = $this->doFleetActions($user, $planet, $botRow, $settings, $personality, $actionsLeft) || $didAny;
        }

        // 3) ACS raid — coordinated attack with alliance-bots (raider/balanced only, 30% chance per tick)
        $canAcs = !empty((int)($settings['can_acs'] ?? 1));
        if ($canAcs && $actionsLeft > 0 && mt_rand(1, 100) <= 30) {
            if ($this->doAcsRaid($user, $planet, $settings, $personalityName)) {
                $didAny = true;
            }
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

    // -------------------------------------------------------------------------
    // Personality build/research/defense priority tables
    // -------------------------------------------------------------------------

    /**
     * Returns ordered building priority list per personality.
     * IDs: 1=MetalMine 2=CrystalMine 3=DeutSynth 4=SolarPlant 12=FusionReactor
     * 14=NaniteFactory 15=RoboFactory 21=Shipyard 22=MetalStorage 23=CrystalStorage
     * 24=DeutTank 31=ResearchLab 33=Terraformer 34=AllianceDepot 41=LunarBase
     * 42=SensorPhalanx 43=JumpGate 44=MissilesSilo
     */
    private function getBuildingPriority(string $personality): array
    {
        $common = [
            // Emergency energy first (handled dynamically, these are fallback order)
            4,   // Solar Plant
            1,   // Metal Mine
            2,   // Crystal Mine
            4,   // Solar Plant (repeat — energy keeps up with mines)
            3,   // Deut Synth
            22,  // Metal Storage
            23,  // Crystal Storage
            24,  // Deut Tank
            15,  // Robo Factory
            14,  // Nanite Factory
            21,  // Shipyard
            31,  // Research Lab
            12,  // Fusion Reactor
            44,  // Missiles Silo
        ];

        switch ($personality) {
            case 'miner':
                return [
                    4, 1, 2, 4, 3, 1, 2, 3, 22, 23, 24, 15, 14, 12, 31, 21,
                ];
            case 'raider':
                return [
                    4, 1, 2, 15, 21, 14, 31, 3, 22, 23, 24, 12, 44,
                ];
            case 'researcher':
                return [
                    4, 1, 2, 31, 15, 14, 3, 22, 23, 24, 21, 12,
                ];
            case 'farmer':
                return [
                    4, 1, 2, 3, 22, 23, 24, 15, 14, 12, 31,
                ];
            case 'turtle':
                return [
                    4, 1, 2, 3, 15, 21, 44, 22, 23, 24, 14, 31, 12,
                ];
            default: // balanced
                return $common;
        }
    }

    /**
     * Returns ordered research priority list per personality.
     * IDs: 106=Espionage 108=Computer 109=Weapons 110=Shields 111=Armor
     * 113=Energy 114=Hyperspace 115=Combustion 117=Impulse 118=Hyperspace Drive
     * 120=Laser 121=Ion 122=Plasma 123=IntergalacticNet 124=Astrophysics 199=Graviton
     */
    private function getResearchPriority(string $personality): array
    {
        switch ($personality) {
            case 'miner':
                return [113, 115, 117, 106, 108, 124, 118, 109, 110, 111, 114, 120, 121, 122, 123];
            case 'raider':
                return [109, 110, 111, 115, 117, 118, 108, 106, 113, 114, 120, 121, 122, 124, 123];
            case 'researcher':
                return [113, 114, 120, 121, 122, 115, 117, 118, 106, 108, 109, 110, 111, 123, 124, 199];
            case 'farmer':
                return [113, 115, 106, 108, 117, 110, 111, 124, 118, 109, 120, 121, 122, 123];
            case 'turtle':
                return [109, 110, 111, 120, 121, 122, 113, 115, 117, 106, 108, 118, 114, 123, 124];
            default: // balanced
                return [106, 108, 113, 115, 117, 109, 110, 111, 118, 114, 120, 121, 122, 124, 123];
        }
    }

    /**
     * Returns shipyard build todo per personality.
     * Ships: 202=SmallCargo 203=LargeCargo 204=LightFighter 205=HeavyFighter
     *        206=Cruiser 207=Battleship 208=ColonyShip 209=Recycler 210=SpyProbe
     *        211=Bomber 212=SolarSatellite 213=Destroyer 214=Deathstar 215=BattleCruiser
     * Defense: 401=RocketLauncher 402=LightLaser 403=HeavyLaser 404=GaussCanyon
     *          405=IonCanyon 406=PlasmaTurret 407=SmallShield 408=LargeShield
     */
    private function getShipyardTodo(string $personality): array
    {
        switch ($personality) {
            case 'miner':
                return [210 => 5, 202 => 30, 203 => 10, 209 => 15, 204 => 5];
            case 'raider':
                return [210 => 10, 204 => 30, 205 => 15, 206 => 10, 202 => 20, 203 => 10, 207 => 5, 209 => 5];
            case 'researcher':
                return [210 => 10, 202 => 10, 203 => 5, 209 => 5, 204 => 5];
            case 'farmer':
                return [210 => 5, 202 => 50, 203 => 20, 209 => 20];
            case 'turtle':
                return [210 => 5, 202 => 10, 203 => 5, 204 => 10, 209 => 5];
            default: // balanced
                return [210 => 5, 202 => 25, 203 => 10, 209 => 10, 204 => 15, 205 => 5, 206 => 3];
        }
    }

    /**
     * Returns defense todo per personality.
     */
    private function getDefenseTodo(string $personality): array
    {
        switch ($personality) {
            case 'turtle':
                return [401 => 50, 402 => 30, 403 => 20, 407 => 1, 408 => 1, 404 => 5, 405 => 5, 406 => 2];
            case 'raider':
                return [401 => 10, 402 => 5, 407 => 1];
            case 'farmer':
            case 'miner':
                return [401 => 20, 402 => 10, 407 => 1, 408 => 1];
            case 'researcher':
                return [401 => 15, 402 => 10, 407 => 1];
            default: // balanced
                return [401 => 25, 402 => 15, 403 => 10, 407 => 1, 408 => 1, 404 => 3];
        }
    }

    // -------------------------------------------------------------------------
    // Economy actions
    // -------------------------------------------------------------------------

    private function doEconomyActions(array &$USER, array &$PLANET, array $settings, ?array $personality, int &$actionsLeft): bool
    {
        if ($actionsLeft <= 0) {
            return false;
        }

        if (function_exists('IsVacationMode') && IsVacationMode($USER)) {
            $this->log("ECONOMY skip: vacation mode");
            return false;
        }

        if (!class_exists('BotActions')) {
            $this->log("ECONOMY skip: BotActions class missing");
            return false;
        }

        $pName = 'balanced';
        if ($personality !== null) {
            $pName = $personality['name'] ?? 'balanced';
        }

        $canBuild    = !empty((int)($settings['can_build']    ?? 1));
        $canResearch = !empty((int)($settings['can_research'] ?? 1));
        $canShipyard = !empty((int)($settings['can_shipyard'] ?? 1));
        $canDefense  = !empty((int)($settings['can_defense']  ?? 1));

        // Personality overrides
        if ($pName === 'miner')      { $canResearch = false; $canShipyard = false; }
        if ($pName === 'farmer')     { $canShipyard = false; }
        if ($pName === 'raider')     { $canBuild = (mt_rand(1,100) <= 40); $canResearch = (mt_rand(1,100) <= 30); }
        if ($pName === 'researcher') { $canShipyard = (mt_rand(1,100) <= 30); }
        if ($pName === 'turtle')     { $canDefense = true; }

        // --- 0. Planet Save: detect incoming attacks and save fleet first ---
        if ($this->hasIncomingAttack($USER, $PLANET)) {
            $this->log("SAVE: incoming attack detected, saving fleet");
            if ($this->saveFleet($USER, $PLANET, $settings)) {
                $actionsLeft--;
                return true;
            }
        }

        // --- 1. Emergency energy check ---
        if ($canBuild && $actionsLeft > 0) {
            $energyFree = (float)($PLANET['energy_max'] ?? 0) - (float)($PLANET['energy_used'] ?? 0);
            if ($energyFree < 0) {
                foreach ([4, 12] as $bid) {
                    if (BotActions::tryQueueBuilding($USER, $PLANET, $bid, true)) {
                        $this->log("BUILD emergency energy: id={$bid}");
                        $actionsLeft--;
                        return true;
                    }
                }
            }
        }

        // --- 2. Storage if near cap ---
        if ($canBuild && $actionsLeft > 0) {
            $storageMap = [901 => [22, 'metal_max', 'metal'], 902 => [23, 'crystal_max', 'crystal'], 903 => [24, 'deuterium_max', 'deuterium']];
            foreach ($storageMap as [$sid, $capKey, $resKey]) {
                $cap = (float)($PLANET[$capKey] ?? 0);
                $cur = (float)($PLANET[$resKey] ?? 0);
                if ($cap > 0 && $cur > $cap * 0.85) {
                    if (BotActions::tryQueueBuilding($USER, $PLANET, $sid, true)) {
                        $this->log("BUILD storage full: id={$sid}");
                        $actionsLeft--;
                        return true;
                    }
                }
            }
        }

        // --- 3. Main building priority list ---
        if ($canBuild && $actionsLeft > 0) {
            $prio = $this->getBuildingPriority($pName);
            foreach ($prio as $bid) {
                if (BotActions::tryQueueBuilding($USER, $PLANET, $bid, true)) {
                    $this->log("BUILD queued: id={$bid} personality={$pName}");
                    $actionsLeft--;
                    return true;
                }
            }
        }

        // --- 4. Research priority list ---
        if ($canResearch && $actionsLeft > 0) {
            $prio = $this->getResearchPriority($pName);
            foreach ($prio as $tid) {
                if (BotActions::tryQueueResearch($USER, $PLANET, $tid)) {
                    $this->log("RESEARCH queued: id={$tid} personality={$pName}");
                    $actionsLeft--;
                    return true;
                }
            }
        }

        // --- 5. Shipyard (ships) ---
        if ($canShipyard && $actionsLeft > 0) {
            $todo = $this->getShipyardTodo($pName);
            if (BotActions::tryQueueShipyard($USER, $PLANET, $todo)) {
                $this->log("SHIPYARD queued: personality={$pName}");
                $actionsLeft--;
                return true;
            }
        }

        // --- 6. Defense ---
        if ($canDefense && $canShipyard && $actionsLeft > 0) {
            $todo = $this->getDefenseTodo($pName);
            if (!empty($todo)) {
                // Defense uses tryQueueShipyard with defense IDs
                if (BotActions::tryQueueShipyard($USER, $PLANET, $todo)) {
                    $this->log("DEFENSE queued: personality={$pName}");
                    $actionsLeft--;
                    return true;
                }
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Planet Save: detect incoming attacks and save fleet away
    // -------------------------------------------------------------------------

    private function hasIncomingAttack(array $USER, array $PLANET): bool
    {
        try {
            $count = $this->db->selectSingle(
                "SELECT COUNT(*) as cnt FROM %%FLEETS%%
                 WHERE fleet_target_owner = :uid
                   AND fleet_end_galaxy   = :g
                   AND fleet_end_system   = :s
                   AND fleet_end_planet   = :p
                   AND fleet_mission      IN (1, 2, 9)
                   AND fleet_mess         = 0",
                [
                    ':uid' => (int)$USER['id'],
                    ':g'   => (int)$PLANET['galaxy'],
                    ':s'   => (int)$PLANET['system'],
                    ':p'   => (int)$PLANET['planet'],
                ]
            );
            return (int)($count['cnt'] ?? 0) > 0;
        } catch (Throwable $t) {
            return false;
        }
    }

    private function saveFleet(array $USER, array &$PLANET, array $settings): bool
    {
        global $resource;

        // Collect all ships on the planet
        $shipIds = [202, 203, 204, 205, 206, 207, 208, 209, 210, 211, 213, 214, 215];
        $fleetArray = [];
        foreach ($shipIds as $sid) {
            if (!isset($resource[$sid])) continue;
            $cnt = (int)($PLANET[$resource[$sid]] ?? 0);
            if ($cnt > 0) $fleetArray[$sid] = $cnt;
        }

        if (empty($fleetArray)) {
            $this->log("SAVE skip: no ships on planet");
            return false;
        }

        // Send as expedition (mission 15) to save — target own position with offset system
        $speedFactor   = FleetFunctions::GetGameSpeedFactor();
        $maxFleetSpeed = FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER);

        // Save target: same galaxy, random system offset to create travel time
        $saveGalaxy = (int)$PLANET['galaxy'];
        $saveSystem = max(1, min(499, (int)$PLANET['system'] + mt_rand(10, 50)));
        $savePlanet = 16; // expedition slot

        $distance    = FleetFunctions::GetTargetDistance(
            [(int)$PLANET['galaxy'], (int)$PLANET['system'], (int)$PLANET['planet']],
            [$saveGalaxy, $saveSystem, $savePlanet]
        );
        $duration    = FleetFunctions::GetMissionDuration(10, $maxFleetSpeed, $distance, $speedFactor, $USER);
        $consumption = FleetFunctions::GetFleetConsumption($fleetArray, $duration, $distance, $USER, $speedFactor);
        $deutAvail   = (float)($PLANET['deuterium'] ?? 0);

        if ($deutAvail < $consumption) {
            $this->log("SAVE skip: not enough deut ({$deutAvail} < {$consumption})");
            return false;
        }

        $now     = TIMESTAMP;
        $endTime = $now + $duration;

        try {
            FleetFunctions::sendFleet(
                $fleetArray,
                15, // Expedition
                (int)$USER['id'],
                (int)$PLANET['id'],
                (int)$PLANET['galaxy'], (int)$PLANET['system'], (int)$PLANET['planet'], (int)($PLANET['planet_type'] ?? 1),
                (int)$USER['id'],
                (int)$PLANET['id'],
                $saveGalaxy, $saveSystem, $savePlanet, 1,
                [901 => 0, 902 => 0, 903 => 0],
                $now, $endTime, $endTime
            );
        } catch (Throwable $t) {
            $this->log("SAVE sendFleet failed: " . $t->getMessage());
            return false;
        }

        $this->subtractFleetFromPlanet($PLANET, $fleetArray);
        $this->subtractPlanetDeuterium((int)$PLANET['id'], $consumption);
        $this->log("SAVE fleet sent to [{$saveGalaxy}:{$saveSystem}:{$savePlanet}] ships=" . count($fleetArray) . " dur={$duration}s");
        return true;
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

    private function doFleetActions(array $USER, array &$PLANET, array $botRow, array $settings, ?array $personality, int &$actionsLeft): bool
    {
        if ($actionsLeft <= 0) {
            return false;
        }

        if (function_exists('IsVacationMode') && IsVacationMode($USER)) {
            $this->log("FLEET skip: vacation mode");
            return false;
        }

        // Default pvp/pve from settings — pvp_enabled default NOW 1 so raids happen unless explicitly disabled
        $pve = !empty((int)($settings['pve_enabled'] ?? 1));
        $pvp = !empty((int)($settings['pvp_enabled'] ?? 1));

        // PERSONALITY FILTERS — apply on top of settings
        $personalityName = 'balanced';
        if ($personality !== null) {
            $personalityName = $personality['name'] ?? 'balanced';
        } elseif (!empty($botRow['personality'])) {
            $personalityName = (string)$botRow['personality'];
        }

        $this->log("FLEET personalityName={$personalityName} pvp_setting=" . ($settings['pvp_enabled'] ?? 'NULL') . " can_raid=" . ($settings['can_raid'] ?? 'NULL'));

        switch ($personalityName) {
            case 'miner':
            case 'farmer':
                $pvp = false;
                $pve = (mt_rand(1, 100) <= 30);
                break;
            case 'turtle':
                $pvp = false;
                $pve = true;
                break;
            case 'raider':
                $pvp = true; // Force PVP regardless of settings default
                $pve = (mt_rand(1, 100) <= 40);
                break;
            case 'researcher':
                $pvp = false;
                $pve = true;
                break;
            // balanced: use settings as-is
        }

        $this->log("FLEET after personality: pve=" . ($pve ? '1' : '0') . " pvp=" . ($pvp ? '1' : '0'));

        // Fleet slots — bots get a minimum of bot_min_fleet_slots (default 5) to compensate for computer_tech=0
        $activeSlots = FleetFunctions::GetCurrentFleets($USER['id']);
        $maxSlots    = max(FleetFunctions::GetMaxFleetSlots($USER), (int)($settings['bot_min_fleet_slots'] ?? 5));
        if ($activeSlots >= $maxSlots) {
            $this->log("FLEET skip: no fleet slots ({$activeSlots}/{$maxSlots})");
            return false;
        }

        $anyDid = false;

        // 1. Recycle own debris (PVE)
        if ($pve && !empty((int)($settings['can_recycle'] ?? 1)) && $actionsLeft > 0) {
            if ($this->sendRecycleOnOwnDebris($USER, $PLANET, $botRow, $settings)) {
                $actionsLeft--;
                $anyDid = true;
            }
        }

        // 2. Expedition (PVE) — does NOT abort further actions anymore
        if ($pve && !empty((int)($settings['can_expedition'] ?? 1)) && $actionsLeft > 0) {
            if ($this->sendExpedition($USER, $PLANET, $botRow, $settings)) {
                $actionsLeft--;
                $anyDid = true;
            }
        }

        // 3+4. PVP: find ONE target, spy first, then raid only if worth it
        if ($pvp && $actionsLeft > 0) {
            $canSpy  = !empty((int)($settings['can_spy']  ?? 1));
            $canRaid = !empty((int)($settings['can_raid'] ?? 1));

            if ($canSpy || $canRaid) {
                // Pick a random target so bots spread across different players
                $pvpTarget = $this->findRaidTarget($USER, $PLANET, $settings);

                if ($pvpTarget) {
                    // Spy first
                    if ($canSpy && $actionsLeft > 0) {
                        if ($this->sendSpy($USER, $PLANET, $settings, $pvpTarget)) {
                            $actionsLeft--;
                            $anyDid = true;
                        }
                    }

                    // Raid only if target has enough resources to be worth it
                    $minLoot = (int)($settings['raid_min_loot'] ?? 10000);
                    $targetLoot = (int)($pvpTarget['metal'] ?? 0) + (int)($pvpTarget['crystal'] ?? 0);
                    if ($canRaid && $actionsLeft > 0) {
                        if ($targetLoot >= $minLoot) {
                            if ($this->sendRaid($USER, $PLANET, $settings, $pvpTarget)) {
                                $actionsLeft--;
                                $anyDid = true;
                            }
                        } else {
                            $this->log("RAID skip: target loot too low ({$targetLoot} < {$minLoot})");
                        }
                    }
                } else {
                    $this->log("PVP skip: no target found");
                }
            } else {
                $this->log("PVP skip: both spy and raid disabled");
            }
        }

        return $anyDid;
    }

    private function sendSpy(array $USER, array &$PLANET, array $settings, ?array $pvpTarget = null): bool
    {
        global $resource;

        // Ship ID 210 = spy probe; $resource[210] is the planet column name (string)
        if (!isset($resource[210])) {
            $this->log("SPY skip: resource[210] (spy probe) not defined");
            return false;
        }

        $probeKey     = $resource[210]; // planet column name e.g. "spy_sonde"
        $available    = (int)($PLANET[$probeKey] ?? 0);
        $probesNeeded = max(1, (int)($settings['spy_probes'] ?? 3));

        if ($available < $probesNeeded) {
            $this->log("SPY skip: not enough probes ({$available}/{$probesNeeded})");
            return false;
        }

        // Fleet functions expect [shipId (int) => count] — use integer key 210
        $fleetArray = [210 => $probesNeeded];

        // Use shared target if provided, otherwise pick a random one
        if ($pvpTarget !== null) {
            $target = $pvpTarget;
        } else {
            $myId = (int)$USER['id'];
            try {
                $target = $this->db->selectSingle(
                    "SELECT p.id, p.id_owner, p.galaxy, p.system, p.planet, u.username
                     FROM %%PLANETS%% p
                     INNER JOIN %%USERS%% u ON u.id = p.id_owner
                     WHERE p.id_owner != :me
                     ORDER BY RAND() LIMIT 1",
                    [':me' => (int)$USER['id']]
                );
            } catch (Throwable $t) {
                $this->log("SPY findTarget failed: " . $t->getMessage());
                return false;
            }
            if (!$target) {
                $this->log("SPY skip: no target found");
                return false;
            }
        }

        $speedFactor   = FleetFunctions::GetGameSpeedFactor();
        $maxFleetSpeed = FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER);
        $distance      = FleetFunctions::GetTargetDistance(
            [(int)$PLANET['galaxy'], (int)$PLANET['system'], (int)$PLANET['planet']],
            [(int)$target['galaxy'], (int)$target['system'], (int)$target['planet']]
        );
        $duration    = FleetFunctions::GetMissionDuration(10, $maxFleetSpeed, $distance, $speedFactor, $USER);
        $consumption = FleetFunctions::GetFleetConsumption($fleetArray, $duration, $distance, $USER, $speedFactor);
        $deutAvail   = (float)($PLANET['deuterium'] ?? 0);

        if ($deutAvail < $consumption) {
            $this->log("SPY skip: not enough deut ({$deutAvail} < {$consumption})");
            return false;
        }

        $now        = TIMESTAMP;
        $endTime    = $now + $duration;
        $fleetResource = [901 => 0, 902 => 0, 903 => 0];

        try {
            FleetFunctions::sendFleet(
                $fleetArray,
                6, // Mission: Spy
                (int)$USER['id'],
                (int)$PLANET['id'],
                (int)$PLANET['galaxy'], (int)$PLANET['system'], (int)$PLANET['planet'], (int)($PLANET['planet_type'] ?? 1),
                (int)$target['id_owner'],
                (int)$target['id'],
                (int)$target['galaxy'], (int)$target['system'], (int)$target['planet'], 1,
                $fleetResource,
                $now, $endTime, $endTime
            );
        } catch (Throwable $t) {
            $this->log("SPY sendFleet failed: " . $t->getMessage());
            return false;
        }

        $this->subtractFleetFromPlanet($PLANET, $fleetArray);
        $this->subtractPlanetDeuterium((int)$PLANET['id'], $consumption);
        $this->log("SPY sent {$probesNeeded} probes → {$target['username']} [{$target['galaxy']}:{$target['system']}:{$target['planet']}]");
        return true;
    }

    private function sendRaid(array $USER, array &$PLANET, array $settings, ?array $pvpTarget = null): bool
    {
        global $resource;

        // Find a target planet (simple, safe, stable):
        // - Not self
        // - Not in vacation
        // - Optional: inactive only
        // - Optional: not same ally (unless allowed)
        // - Has enough loot
        // Use shared target if provided (from doFleetActions spy+raid flow)
        if ($pvpTarget !== null) {
            $target = $pvpTarget;
        } else {
            $target = $this->findRaidTarget($USER, $PLANET, $settings);
            if (!$target) {
                $this->log("RAID skip: no suitable target");
                return false;
            }
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

        $this->log("RAID ships on planet: " . json_encode(array_map(fn($sid) => [$sid => (int)($PLANET[$resource[$sid] ?? ''] ?? 0)], array_keys($want))));
        if (empty($fleetArray)) {
            $this->log("RAID fail: no ships available on planet");
            return false;
        }
        $this->log("RAID fleet built: " . json_encode($fleetArray));

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

        $maxFlight = (int)($settings['raid_max_flight_seconds'] ?? 0); // 0 = no limit
        if ($maxFlight > 0 && $duration > $maxFlight) {
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

            $this->subtractFleetFromPlanet($PLANET, $fleetArray);
            $this->subtractPlanetDeuterium((int)$PLANET['id'], $consumption);
            return true;

        } catch (Throwable $t) {
            $this->log("RAID failed: " . $t->getMessage() . " @ " . $t->getFile() . ":" . $t->getLine());
            return false;
        }
    }

    private function findRaidTarget(array $USER, array $PLANET, array $settings): ?array
    {
        $minMetal   = (int)($settings['raid_min_cargo_metal'] ?? 0);
        $minCrystal = (int)($settings['raid_min_cargo_crystal'] ?? 0);
        $minGain    = (int)($settings['raid_min_gain'] ?? 0);

        $inactiveOnly   = !empty((int)($settings['raid_inactive_only'] ?? 0));
        $allowSameAlly  = !empty((int)($settings['raid_allow_same_ally'] ?? 0));
        $maxRankDiff    = (int)($settings['raid_max_rank_diff'] ?? 0); // 0 = disabled

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

        // Note: bots may attack players in vacation mode or other bots — no urlaubs_modus/banaday filter
        // (on small servers all real players may be in vacation; bots must still be able to attack each other)

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
        if ($minGain > 0) {
            $conds[] = "(p.metal + p.crystal) >= :minGain";
            $params[':minGain'] = $minGain;
        }
        if ($minMetal > 0) {
            $conds[] = "p.metal >= :minMetal";
            $params[':minMetal'] = $minMetal;
        }
        if ($minCrystal > 0) {
            $conds[] = "p.crystal >= :minCrystal";
            $params[':minCrystal'] = $minCrystal;
        }

        // rank diff (needs STATPOINTS table in 2Moons: %%STATPOINTS%%)
        // We'll do a soft join; if table absent, we skip rank filter by try/catch.
        // Defense columns in 2Moons (IDs 401-412 mapped to planet fields)
        // misil_launcher=401, small_laser=402, big_laser=403, gauss_canyon=404,
        // ionic_canyon=405, buster_canyon=406, small_protection_shield=407,
        // planet_protector=408, big_protection_shield=409, graviton_canyon=410
        $where = implode(" AND ", $conds);

        // ORDER BY RAND() so different bots pick different targets
        $sql = "SELECT p.id, p.id_owner, p.galaxy, p.system, p.planet, p.metal, p.crystal, u.username, u.ally_id, u.onlinetime
                FROM %%PLANETS%% p
                INNER JOIN %%USERS%% u ON u.id = p.id_owner
                WHERE {$where}
                ORDER BY RAND()
                LIMIT 1;";

        try {
            $rows = $this->db->select($sql, $params);
        } catch (Throwable $t) {
            $this->log("findRaidTarget query failed: " . $t->getMessage());
            return null;
        }

        if (empty($rows)) {
            $this->log("RAID findTarget: 0 rows returned from DB (all filtered by vacation/ban)");
            return null;
        }

        $this->log("RAID findTarget: " . count($rows) . " candidates");

        // Rank diff filter (only if maxRankDiff > 0)
        $myRank = ($maxRankDiff > 0) ? $this->getUserRankPoints($myId) : null;
        foreach ($rows as $r) {
            if ($maxRankDiff > 0 && $myRank !== null) {
                $theirRank = $this->getUserRankPoints((int)$r['id_owner']);
                if ($theirRank !== null && abs($theirRank - $myRank) > $maxRankDiff) {
                    $this->log("RAID skip candidate {$r['username']}: rank diff too large");
                    continue;
                }
            }
            $this->log("RAID target selected: {$r['username']} [{$r['galaxy']}:{$r['system']}:{$r['planet']}] metal={$r['metal']} crystal={$r['crystal']}");
            return $r;
        }

        $this->log("RAID findTarget: all " . count($rows) . " candidates filtered by rank diff");
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
    // Helper: keep $PLANET ship-counts in sync after sendFleet
    // sendFleet only updates the DB; without this the next sendFleet in the
    // same tick would see stale (too-high) counts and cause unsigned underflow.
    // ---------------------------------------------------------------------

    private function subtractFleetFromPlanet(array &$PLANET, array $fleetArray): void
    {
        global $resource;
        foreach ($fleetArray as $sid => $cnt) {
            if (!isset($resource[$sid])) continue;
            $col = $resource[$sid];
            $PLANET[$col] = max(0, (int)($PLANET[$col] ?? 0) - (int)$cnt);
        }
    }

    // ---------------------------------------------------------------------
    // Existing Fleet actions from your previous code (kept, unchanged core)
    // ---------------------------------------------------------------------

    private function sendExpedition(array $USER, array &$PLANET, array $botRow, array $settings): bool
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
        $maxSlots    = max(FleetFunctions::GetMaxFleetSlots($USER), (int)($settings['bot_min_fleet_slots'] ?? 5));
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

            $this->subtractFleetFromPlanet($PLANET, $fleetArray);
            $this->subtractPlanetDeuterium((int)$PLANET['id'], $consumption);
            return true;

        } catch (Throwable $t) {
            $this->log("EXPEDITION failed: " . $t->getMessage() . " @ " . $t->getFile() . ":" . $t->getLine());
            return false;
        }
    }

    private function sendRecycleOnOwnDebris(array $USER, array &$PLANET, array $botRow, array $settings): bool
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

            $this->subtractFleetFromPlanet($PLANET, $fleetArray);
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

    // -------------------------------------------------------------------------
    // Alliance Management
    // -------------------------------------------------------------------------

    /**
     * Ensures this bot is in a bot-only alliance.
     * - If already in an alliance: nothing to do.
     * - If a bot-alliance with free slots exists: join it.
     * - Otherwise: create a new bot-alliance.
     * Called once per tick (cheap: early-exit if already has ally_id).
     */
    public function doAllianceActions(array &$USER, int $universeId): void
    {
        $myAlly = (int)($USER['ally_id'] ?? 0);
        if ($myAlly > 0) {
            return; // already in an alliance
        }

        $myId = (int)$USER['id'];

        try {
            // Look for an existing bot-alliance with free slots
            // Bot-alliances are identified by ally_name starting with "BOT_"
            $existing = $this->db->selectSingle(
                "SELECT a.id, a.ally_members, a.ally_max_members
                 FROM %%ALLIANCE%% a
                 WHERE a.ally_name LIKE 'BOT_%'
                   AND a.ally_universe = :uni
                   AND a.ally_members < a.ally_max_members
                 ORDER BY a.ally_members DESC
                 LIMIT 1",
                [':uni' => $universeId]
            );

            if ($existing && (int)$existing['id'] > 0) {
                $allyId = (int)$existing['id'];
                // Join existing bot-alliance
                $this->db->update(
                    "UPDATE %%USERS%% SET ally_id = :aid, ally_register_time = :t, ally_rank_id = 0 WHERE id = :uid",
                    [':aid' => $allyId, ':t' => TIMESTAMP, ':uid' => $myId]
                );
                $this->db->update(
                    "UPDATE %%ALLIANCE%% SET ally_members = ally_members + 1 WHERE id = :aid",
                    [':aid' => $allyId]
                );
                $USER['ally_id'] = $allyId;
                $this->log("ALLY joined existing bot-alliance id={$allyId} user={$USER['username']}");
            } else {
                // Create a new bot-alliance
                $tag  = 'BOT' . strtoupper(substr(md5($myId . TIMESTAMP), 0, 4));
                $name = 'BOT_' . $USER['username'];

                $this->db->insert(
                    "INSERT INTO %%ALLIANCE%% (ally_name, ally_tag, ally_owner, ally_register_time, ally_universe, ally_members, ally_max_members, ally_request_notallow)
                     VALUES (:name, :tag, :owner, :t, :uni, 1, 50, 0)",
                    [
                        ':name'  => $name,
                        ':tag'   => $tag,
                        ':owner' => $myId,
                        ':t'     => TIMESTAMP,
                        ':uni'   => $universeId,
                    ]
                );
                $newAllyId = $this->db->lastInsertId();

                $this->db->update(
                    "UPDATE %%USERS%% SET ally_id = :aid, ally_register_time = :t, ally_rank_id = 0 WHERE id = :uid",
                    [':aid' => $newAllyId, ':t' => TIMESTAMP, ':uid' => $myId]
                );
                $USER['ally_id'] = $newAllyId;
                $this->log("ALLY created new bot-alliance '{$name}' [{$tag}] id={$newAllyId} user={$USER['username']}");
            }
        } catch (Throwable $t) {
            $this->log("ALLY error: " . $t->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // ACS (coordinated attack) — only for bots in the same alliance
    // -------------------------------------------------------------------------

    /**
     * Attempt to join or initiate an ACS raid with ally-bots.
     * Rules:
     * - Only bots in the same alliance participate.
     * - Max 3 bots per ACS group.
     * - An existing ACS group for the same target is joined; otherwise a new one is created.
     * - Only 'raider' and 'balanced' personalities join ACS.
     */
    public function doAcsRaid(array $USER, array $PLANET, array $settings, string $personalityName): bool
    {
        global $resource;

        $myAlly = (int)($USER['ally_id'] ?? 0);
        if ($myAlly === 0) {
            $this->log("ACS skip: not in an alliance");
            return false;
        }

        // Only aggressive personalities do ACS
        if (!in_array($personalityName, ['raider', 'balanced'], true)) {
            return false;
        }

        $maxAcsSize = (int)($settings['acs_max_size'] ?? 3);

        // Find a target (same logic as solo raid, random)
        $target = $this->findRaidTarget($USER, $PLANET, $settings);
        if (!$target) {
            $this->log("ACS skip: no target found");
            return false;
        }

        $tGalaxy = (int)$target['galaxy'];
        $tSystem = (int)$target['system'];
        $tPlanet = (int)$target['planet'];
        $tOwner  = (int)$target['id_owner'];
        $tPlanetId = (int)$target['id'];

        try {
            // Check if an existing ACS fleet group is already heading to this target from this alliance
            $existingGroup = $this->db->selectSingle(
                "SELECT f.fleet_group, COUNT(*) as cnt
                 FROM %%FLEETS%% f
                 INNER JOIN %%USERS%% u ON u.id = f.fleet_owner
                 WHERE f.fleet_end_galaxy  = :g
                   AND f.fleet_end_system  = :s
                   AND f.fleet_end_planet  = :p
                   AND f.fleet_mission     = 2
                   AND f.fleet_group       > 0
                   AND f.fleet_mess        = 0
                   AND u.ally_id           = :ally
                 GROUP BY f.fleet_group
                 HAVING cnt < :max
                 LIMIT 1",
                [
                    ':g'    => $tGalaxy,
                    ':s'    => $tSystem,
                    ':p'    => $tPlanet,
                    ':ally' => $myAlly,
                    ':max'  => $maxAcsSize,
                ]
            );
        } catch (Throwable $t) {
            $this->log("ACS query failed: " . $t->getMessage());
            return false;
        }

        if ($existingGroup && (int)$existingGroup['fleet_group'] > 0) {
            $fleetGroup = (int)$existingGroup['fleet_group'];
            $this->log("ACS joining existing group={$fleetGroup} target={$target['username']} [{$tGalaxy}:{$tSystem}:{$tPlanet}]");
        } else {
            // Create a new fleet_group ID (use timestamp + random for uniqueness)
            $fleetGroup = (int)(TIMESTAMP % 100000) * 1000 + mt_rand(1, 999);
            $this->log("ACS creating new group={$fleetGroup} target={$target['username']} [{$tGalaxy}:{$tSystem}:{$tPlanet}]");
        }

        // Build raid fleet
        $want = [204 => 20, 205 => 10, 206 => 5, 202 => 20, 210 => 3];
        $fleetArray = [];
        foreach ($want as $sid => $cnt) {
            if (!isset($resource[$sid])) continue;
            $avail = (int)($PLANET[$resource[$sid]] ?? 0);
            $use   = min($avail, $cnt);
            if ($use > 0) $fleetArray[$sid] = $use;
        }

        if (empty($fleetArray)) {
            $this->log("ACS skip: no ships available");
            return false;
        }

        $speedFactor   = FleetFunctions::GetGameSpeedFactor();
        $maxFleetSpeed = FleetFunctions::GetFleetMaxSpeed($fleetArray, $USER);
        $distance      = FleetFunctions::GetTargetDistance(
            [(int)$PLANET['galaxy'], (int)$PLANET['system'], (int)$PLANET['planet']],
            [$tGalaxy, $tSystem, $tPlanet]
        );
        $duration    = FleetFunctions::GetMissionDuration(10, $maxFleetSpeed, $distance, $speedFactor, $USER);
        $consumption = FleetFunctions::GetFleetConsumption($fleetArray, $duration, $distance, $USER, $speedFactor);

        if ((float)($PLANET['deuterium'] ?? 0) < $consumption) {
            $this->log("ACS skip: not enough deut");
            return false;
        }

        $now     = TIMESTAMP;
        $endTime = $now + $duration;

        try {
            FleetFunctions::sendFleet(
                $fleetArray,
                2, // Mission: ACS Attack
                (int)$USER['id'],
                (int)$PLANET['id'],
                (int)$PLANET['galaxy'], (int)$PLANET['system'], (int)$PLANET['planet'], (int)($PLANET['planet_type'] ?? 1),
                $tOwner,
                $tPlanetId,
                $tGalaxy, $tSystem, $tPlanet, 1,
                [901 => 0, 902 => 0, 903 => 0],
                $now, $endTime, $endTime,
                $fleetGroup
            );
        } catch (Throwable $t) {
            $this->log("ACS sendFleet failed: " . $t->getMessage());
            return false;
        }

        $this->subtractFleetFromPlanet($PLANET, $fleetArray);
        $this->subtractPlanetDeuterium((int)$PLANET['id'], $consumption);
        $this->log("ACS sent: {$USER['username']} -> {$target['username']} [{$tGalaxy}:{$tSystem}:{$tPlanet}] group={$fleetGroup} ships=" . array_sum($fleetArray));
        return true;
    }
}