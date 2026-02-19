<?php
declare(strict_types=1);

/**
 * BotActions.class.php
 *
 * Zentraler Layer für Bot-Aktionen (Build/Research/Shipyard/Officers/Trader),
 * inkl. Feature-Toggles (aus DB: uni{X}_bot_setting) + Defaults/Fallbacks.
 *
 * Wichtig:
 * - Keine Redirects
 * - Kein Template
 * - Nutzt dieselben Datenstrukturen wie normale Player (Queues / Resource Abzug)
 *
 * Erwartete DB-Spalten (aus deinem ALTER TABLE):
 *  enabled, pve_enabled, pvp_enabled,
 *  can_build, can_research, can_shipyard, can_expedition, can_recycle, can_raid,
 *  max_actions_per_tick, max_build_queue_fill, max_research_queue_fill, max_shipyard_queue_fill,
 *  raid_min_cargo_metal, raid_min_cargo_crystal, raid_min_gain, ...
 *
 * Außerdem (optional): ships_array (Fleet Preset serialized)
 */

class BotActions
{
    /** @var array<string,mixed>|null */
    private static ?array $cachedSettings = null;
    private static int $cachedUni = 0;
    private static int $cachedAt = 0;

    /**
     * Default-Settings (Fallbacks). Werden mit DB-Werten gemerged.
     */
    private static function getDefaultSettings(): array
    {
        return [
            'enabled'                 => true,

            // High-level modes
            'pve_enabled'             => true,
            'pvp_enabled'             => false,

            // Core actions
            'can_build'               => true,
            'can_research'            => true,
            'can_shipyard'            => true,
            'can_expedition'          => true,
            'can_recycle'             => true,
            'can_raid'                => false,

            // Destroy allowed?
            'allow_destroy'           => false,

            // Queues per tick (wie viele Slots wir pro Tick füllen)
            'max_actions_per_tick'    => 2,
            'max_build_queue_fill'    => 1,
            'max_research_queue_fill' => 1,
            'max_shipyard_queue_fill' => 1,

            // Raid thresholds (Fallbacks)
            'raid_min_cargo_metal'    => 20000,
            'raid_min_cargo_crystal'  => 10000,
            'raid_min_gain'           => 25000,

            // Fleet preset (serialized, optional)
            'ships_array'             => '',

            // Officers / DM / Trader (optional)
            'can_buy_officers'        => false,
            'can_buy_dm_extras'       => false,
            'can_use_trader'          => false,
            'max_dm_spend_per_day'    => 0,
        ];
    }

    /**
     * Lädt Settings aus DB (pro Universe) + cached.
     * Nutzt 2Moons-Tablenames: %%BOT_SETTING%% -> uniX_bot_setting
     */
    private static function loadDbSettings(int $universe): array
    {
        // Cache für 10 Sekunden reicht (Cron tickt eh im Minutenbereich)
        $now = defined('TIMESTAMP') ? (int)TIMESTAMP : time();
        if (self::$cachedSettings !== null && self::$cachedUni === $universe && ($now - self::$cachedAt) <= 10) {
            return self::$cachedSettings;
        }

        $row = [];
        try {
            $db = Database::get();

            // Wir nehmen die erste/aktuelle Settings-Row. (Dein Setup wirkt wie 1 Row pro Uni)
            // Wenn du eine ID-Spalte hast: kannst du hier WHERE id=1 eintragen.
            $row = $db->selectSingle("SELECT * FROM %%BOT_SETTING%% LIMIT 1;");
            if (!is_array($row)) {
                $row = [];
            }
        } catch (\Throwable $e) {
            // Wenn Table/Mapping noch nicht existiert, bleiben wir bei Defaults
            $row = [];
        }

        // Normalize & cast
        $normalized = self::normalizeSettingsRow($row);

        self::$cachedSettings = $normalized;
        self::$cachedUni = $universe;
        self::$cachedAt = $now;

        return $normalized;
    }

    /**
     * Public API:
     * Gibt final Settings zurück (Defaults + DB Merge),
     * und mappt Aliases (can_build_ships -> can_shipyard usw.)
     */
    public static function getSettings(array $user): array
    {
        $universe = defined('ROOT_UNI') ? (int)ROOT_UNI : 1;

        $defaults = self::getDefaultSettings();
        $db       = self::loadDbSettings($universe);

        // Merge: DB überschreibt Defaults
        $s = array_merge($defaults, $db);

        // Aliases (Kompatibilität zu deinem bisherigen Code)
        // shipyard:
        if (!isset($s['can_build_ships'])) {
            $s['can_build_ships'] = !empty($s['can_shipyard']);
        }
        if (!isset($s['can_build_defense'])) {
            // Wenn du Defense separat steuern willst, ergänzen wir später eine Spalte.
            $s['can_build_defense'] = false;
        }

        // Hard casts für Safety
        $s['enabled']                 = (bool)($s['enabled'] ?? false);
        $s['pve_enabled']             = (bool)($s['pve_enabled'] ?? true);
        $s['pvp_enabled']             = (bool)($s['pvp_enabled'] ?? false);
        $s['can_build']               = (bool)($s['can_build'] ?? false);
        $s['can_research']            = (bool)($s['can_research'] ?? false);
        $s['can_shipyard']            = (bool)($s['can_shipyard'] ?? false);
        $s['can_expedition']          = (bool)($s['can_expedition'] ?? false);
        $s['can_recycle']             = (bool)($s['can_recycle'] ?? false);
        $s['can_raid']                = (bool)($s['can_raid'] ?? false);
        $s['allow_destroy']           = (bool)($s['allow_destroy'] ?? false);

        $s['max_actions_per_tick']    = max(0, (int)($s['max_actions_per_tick'] ?? 0));
        $s['max_build_queue_fill']    = max(0, (int)($s['max_build_queue_fill'] ?? 0));
        $s['max_research_queue_fill'] = max(0, (int)($s['max_research_queue_fill'] ?? 0));
        $s['max_shipyard_queue_fill'] = max(0, (int)($s['max_shipyard_queue_fill'] ?? 0));

        $s['raid_min_cargo_metal']    = max(0, (int)($s['raid_min_cargo_metal'] ?? 0));
        $s['raid_min_cargo_crystal']  = max(0, (int)($s['raid_min_cargo_crystal'] ?? 0));
        $s['raid_min_gain']           = max(0, (int)($s['raid_min_gain'] ?? 0));

        if (!is_string($s['ships_array'] ?? '')) {
            $s['ships_array'] = '';
        }

        return $s;
    }

    private static function normalizeSettingsRow(array $row): array
    {
        // Manche DB-Layer geben numerische Keys etc. -> wir filtern
        $out = [];
        foreach ($row as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $out[$k] = $v;
        }

        // Common casts für bekannte Keys (wenn vorhanden)
        $boolKeys = [
            'enabled','pve_enabled','pvp_enabled',
            'can_build','can_research','can_shipyard','can_expedition','can_recycle','can_raid',
            'allow_destroy','can_buy_officers','can_buy_dm_extras','can_use_trader',
        ];
        foreach ($boolKeys as $k) {
            if (array_key_exists($k, $out)) {
                $out[$k] = (int)$out[$k] ? true : false;
            }
        }

        $intKeys = [
            'max_actions_per_tick','max_build_queue_fill','max_research_queue_fill','max_shipyard_queue_fill',
            'max_dm_spend_per_day','raid_min_cargo_metal','raid_min_cargo_crystal','raid_min_gain',
        ];
        foreach ($intKeys as $k) {
            if (array_key_exists($k, $out)) {
                $out[$k] = (int)$out[$k];
            }
        }

        if (array_key_exists('ships_array', $out) && !is_string($out['ships_array'])) {
            $out['ships_array'] = '';
        }

        return $out;
    }

    private static function isEnabled(array $user): bool
    {
        $s = self::getSettings($user);
        return !empty($s['enabled']);
    }

    /**
     * ================
     * BUILDINGS
     * ================
     */
    public static function tryQueueBuilding(array &$user, array &$planet, int $elementId, bool $addMode = true): bool
    {
        if (!self::isEnabled($user)) {
            return false;
        }

        $s = self::getSettings($user);
        if (empty($s['can_build'])) {
            return false;
        }

        if (!$addMode && empty($s['allow_destroy'])) {
            return false;
        }

        if (!defined('TIMESTAMP')) {
            return false;
        }

        global $resource, $reslist, $pricelist;

        if (empty($resource) || empty($reslist) || empty($pricelist)) {
            return false;
        }

        // Check allowed on planet type + requirements
        if (!in_array($elementId, $reslist['allow'][$planet['planet_type']] ?? [], true)) {
            return false;
        }

        if (!BuildFunctions::isTechnologieAccessible($user, $planet, $elementId)) {
            return false;
        }

        if (($elementId == 31 && (int)($user["b_tech_planet"] ?? 0) != 0)) {
            return false; // Forschung läuft -> keine Forschungslab/Network etc.
        }

        if ((($elementId == 15 || $elementId == 21) && !empty($planet['b_hangar_id']))) {
            return false; // Werft/Robo im Bau während Hangar-Queue aktiv
        }

        if (!$addMode && (int)($planet[$resource[$elementId]] ?? 0) == 0) {
            return false;
        }

        // Queue laden
        $currentQueue = [];
        if (!empty($planet['b_building_id'])) {
            $tmp = @unserialize($planet['b_building_id']);
            if (is_array($tmp)) {
                $currentQueue = $tmp;
            }
        }

        $actualCount = count($currentQueue);

        // Queue Fill Limit (Bot)
        if (!empty($s['max_build_queue_fill']) && $actualCount >= (int)$s['max_build_queue_fill']) {
            return false;
        }

        // Max Queue (Game)
        $config = Config::get();
        if (!empty($config->max_elements_build) && $actualCount >= (int)$config->max_elements_build) {
            return false;
        }

        // Felder prüfen (bei AddMode)
        $currentMaxFields = CalculateMaxPlanetFields($planet);
        if ($addMode && (int)$planet['field_current'] >= ((int)$currentMaxFields - $actualCount)) {
            return false;
        }

        $buildMode  = $addMode ? 'build' : 'destroy';
        $buildLevel = (int)($planet[$resource[$elementId]] ?? 0) + ($addMode ? 1 : 0);

        // Level anpassen wenn Element schon in Queue vorkommt
        if ($actualCount > 0) {
            $addLevel = 0;
            foreach ($currentQueue as $q) {
                if (!isset($q[0], $q[4])) continue;
                if ((int)$q[0] !== $elementId) continue;
                $addLevel += ($q[4] === 'build') ? 1 : -1;
            }
            $buildLevel += $addLevel;
        }

        if (!$addMode && $buildLevel <= 0) {
            return false;
        }

        if (!empty($pricelist[$elementId]['max']) && $pricelist[$elementId]['max'] < $buildLevel) {
            return false;
        }

        // Preise
        $costResources = BuildFunctions::getElementPrice($user, $planet, $elementId, !$addMode, $buildLevel);

        if ($actualCount === 0) {
            if (!BuildFunctions::isElementBuyable($user, $planet, $elementId, $costResources)) {
                return false;
            }

            // Ressourcen abziehen
            if (isset($costResources[901])) $planet[$resource[901]] -= $costResources[901];
            if (isset($costResources[902])) $planet[$resource[902]] -= $costResources[902];
            if (isset($costResources[903])) $planet[$resource[903]] -= $costResources[903];
            if (isset($costResources[921])) $user[$resource[921]]   -= $costResources[921];

            $elementTime  = BuildFunctions::getBuildingTime($user, $planet, $elementId, $costResources);
            $buildEndTime = TIMESTAMP + $elementTime;

            $planet['b_building_id'] = serialize([[ $elementId, $buildLevel, $elementTime, $buildEndTime, $buildMode ]]);
            $planet['b_building']    = $buildEndTime;
        } else {
            // Zeit an letzte Queue anhängen
            $elementTime  = BuildFunctions::getBuildingTime($user, $planet, $elementId, null, !$addMode, $buildLevel);
            $lastEnd      = (int)$currentQueue[$actualCount - 1][3];
            $buildEndTime = $lastEnd + $elementTime;

            $currentQueue[] = [ $elementId, $buildLevel, $elementTime, $buildEndTime, $buildMode ];
            $planet['b_building_id'] = serialize($currentQueue);
        }

        return true;
    }

    /**
     * ================
     * RESEARCH
     * ================
     */
    public static function tryQueueResearch(array &$user, array &$planet, int $techId): bool
    {
        if (!self::isEnabled($user)) {
            return false;
        }

        $s = self::getSettings($user);
        if (empty($s['can_research'])) {
            return false;
        }

        global $resource, $reslist, $pricelist;

        if (!in_array($techId, $reslist['tech'] ?? [], true)) {
            return false;
        }

        if (!BuildFunctions::isTechnologieAccessible($user, $planet, $techId)) {
            return false;
        }

        // Lab vorhanden?
        if ((int)($planet[$resource[31]] ?? 0) === 0) {
            return false;
        }

        // Queue laden
        $currentQueue = [];
        if (!empty($user['b_tech_queue'])) {
            $tmp = @unserialize($user['b_tech_queue']);
            if (is_array($tmp)) {
                $currentQueue = $tmp;
            }
        }

        $actualCount = count($currentQueue);

        // Bot limit
        if (!empty($s['max_research_queue_fill']) && $actualCount >= (int)$s['max_research_queue_fill']) {
            return false;
        }

        // Game limit
        $cfg = Config::get();
        if (!empty($cfg->max_elements_tech) && $actualCount >= (int)$cfg->max_elements_tech) {
            return false;
        }

        // Research Level berechnen (wenn gleiche Tech mehrfach in Queue)
        $buildLevel = (int)($user[$resource[$techId]] ?? 0) + 1;
        if ($actualCount > 0) {
            $addLevel = 0;
            foreach ($currentQueue as $q) {
                if (!isset($q[0])) continue;
                if ((int)$q[0] !== $techId) continue;
                $addLevel++;
            }
            $buildLevel += $addLevel;
        }

        if (!empty($pricelist[$techId]['max']) && $pricelist[$techId]['max'] < $buildLevel) {
            return false;
        }

        // Preise
        $costResources = BuildFunctions::getElementPrice($user, $planet, $techId, false, $buildLevel);

        if ($actualCount === 0) {
            if (!BuildFunctions::isElementBuyable($user, $planet, $techId, $costResources)) {
                return false;
            }

            // Ressourcen abziehen (Research planet)
            if (isset($costResources[901])) $planet[$resource[901]] -= $costResources[901];
            if (isset($costResources[902])) $planet[$resource[902]] -= $costResources[902];
            if (isset($costResources[903])) $planet[$resource[903]] -= $costResources[903];
            if (isset($costResources[921])) $user[$resource[921]]   -= $costResources[921];

            $elementTime  = BuildFunctions::getBuildingTime($user, $planet, $techId, $costResources);
            $buildEndTime = TIMESTAMP + $elementTime;

            $user['b_tech_queue']  = serialize([[ $techId, $buildLevel, $elementTime, $buildEndTime, $planet['id'] ]]);
            $user['b_tech']        = $buildEndTime;
            $user['b_tech_id']     = $techId;
            $user['b_tech_planet'] = $planet['id'];
        } else {
            $elementTime  = BuildFunctions::getBuildingTime($user, $planet, $techId, null, false, $buildLevel);
            $lastEnd      = (int)$currentQueue[$actualCount - 1][3];
            $buildEndTime = $lastEnd + $elementTime;

            $currentQueue[] = [ $techId, $buildLevel, $elementTime, $buildEndTime, $planet['id'] ];
            $user['b_tech_queue'] = serialize($currentQueue);
        }

        return true;
    }

    /**
     * ================
     * SHIPYARD / DEFENSE
     * ================
     */
    public static function tryQueueShipyard(array &$user, array &$planet, array $buildTodo): bool
    {
        if (!self::isEnabled($user)) {
            return false;
        }

        $s = self::getSettings($user);

        // Kompatibilität: früher can_build_ships/can_build_defense
        $canShips   = !empty($s['can_shipyard']) || !empty($s['can_build_ships']);
        $canDefense = !empty($s['can_build_defense']);

        if (!$canShips && !$canDefense) {
            return false;
        }

        if (empty($buildTodo) || !is_array($buildTodo)) {
            return false;
        }

        global $resource, $reslist;

        // Shipyard vorhanden?
        if ((int)($planet[$resource[21]] ?? 0) === 0) {
            return false;
        }

        // Queue-Limit (Bot) — hier zählen wir Slots/Einträge, nicht die Stückzahl
        $currentQueue = [];
        if (!empty($planet['b_hangar_id'])) {
            $tmp = @unserialize($planet['b_hangar_id']);
            if (is_array($tmp)) {
                $currentQueue = $tmp;
            }
        }

        $queueCount = count($currentQueue);
        if (!empty($s['max_shipyard_queue_fill']) && $queueCount >= (int)$s['max_shipyard_queue_fill']) {
            return false;
        }

        // Im Bau: Werft/Robo blockt?
        $notBuilding = true;
        if (!empty($planet['b_building_id'])) {
            $bq = @unserialize($planet['b_building_id']);
            if (is_array($bq)) {
                foreach ($bq as $e) {
                    if (!isset($e[0])) continue;
                    if ((int)$e[0] === 21 || (int)$e[0] === 15) {
                        $notBuilding = false;
                        break;
                    }
                }
            }
        }
        if (!$notBuilding) {
            return false;
        }

        // Nur erlaubte Elemente einreihen
        foreach ($buildTodo as $elementId => $count) {
            $elementId = (int)$elementId;
            $count     = is_numeric($count) ? (int)round((float)$count) : 0;
            if ($count <= 0) continue;

            $isFleet   = in_array($elementId, $reslist['fleet'] ?? [], true);
            $isDefense = in_array($elementId, $reslist['defense'] ?? [], true);
            $isMissile = in_array($elementId, $reslist['missile'] ?? [], true);

            if (!$isFleet && !$isDefense && !$isMissile) continue;

            if ($isFleet && !$canShips) continue;
            if (($isDefense || $isMissile) && !$canDefense) continue;

            if (!BuildFunctions::isTechnologieAccessible($user, $planet, $elementId)) continue;

            $maxBuildable = BuildFunctions::getMaxConstructibleElements($user, $planet, $elementId);
            $count = min($count, (int)$maxBuildable);
            $count = max($count, 0);

            if ($count <= 0) continue;

            $cost = BuildFunctions::getElementPrice($user, $planet, $elementId, false, $count);
            if (!BuildFunctions::isElementBuyable($user, $planet, $elementId, $cost)) {
                continue;
            }

            if (isset($cost[901])) $planet[$resource[901]] -= $cost[901];
            if (isset($cost[902])) $planet[$resource[902]] -= $cost[902];
            if (isset($cost[903])) $planet[$resource[903]] -= $cost[903];
            if (isset($cost[921])) $user[$resource[921]]   -= $cost[921];

            $currentQueue[] = [ $elementId, $count ];

            // Slot-Limit einhalten (wenn Bot nur X Einträge pro Tick füllen soll)
            $queueCount = count($currentQueue);
            if (!empty($s['max_shipyard_queue_fill']) && $queueCount >= (int)$s['max_shipyard_queue_fill']) {
                break;
            }
        }

        $planet['b_hangar_id'] = empty($currentQueue) ? '' : serialize(array_values($currentQueue));
        return !empty($currentQueue);
    }

    /**
     * ================
     * OFFICERS / DM EXTRAS
     * ================
     */
    public static function tryBuyOfficer(array &$user, array &$planet, int $elementId): bool
    {
        if (!self::isEnabled($user)) {
            return false;
        }

        $s = self::getSettings($user);
        if (empty($s['can_buy_officers'])) {
            return false;
        }

        global $reslist;

        if (!isModuleAvailable(MODULE_OFFICIER)) {
            return false;
        }

        if (!in_array($elementId, $reslist['officier'] ?? [], true)) {
            return false;
        }

        // Wir benutzen bewusst dieselbe Logik wie ShowOfficierPage, aber ohne Page/Template:
        global $resource, $pricelist;

        if (!BuildFunctions::isTechnologieAccessible($user, $planet, $elementId)) {
            return false;
        }

        $cost = BuildFunctions::getElementPrice($user, $planet, $elementId);

        if (!BuildFunctions::isElementBuyable($user, $planet, $elementId, $cost)) {
            return false;
        }

        if (!empty($pricelist[$elementId]['max']) && $pricelist[$elementId]['max'] <= (int)($user[$resource[$elementId]] ?? 0)) {
            return false;
        }

        // Abziehen
        if (isset($cost[901])) $planet[$resource[901]] -= $cost[901];
        if (isset($cost[902])) $planet[$resource[902]] -= $cost[902];
        if (isset($cost[903])) $planet[$resource[903]] -= $cost[903];
        if (isset($cost[921])) $user[$resource[921]]   -= $cost[921];

        $user[$resource[$elementId]] = (int)($user[$resource[$elementId]] ?? 0) + 1;

        // Persist (wie ShowOfficierPage)
        $sql = 'UPDATE %%USERS%% SET '.$resource[$elementId].' = :val WHERE id = :uid;';
        Database::get()->update($sql, [
            ':val' => $user[$resource[$elementId]],
            ':uid' => (int)$user['id'],
        ]);

        return true;
    }
}