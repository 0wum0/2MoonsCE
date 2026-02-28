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

class BotManager
{
    private Database $db;
    private array $resource;

    public function __construct()
    {
        $this->db = Database::get();
        global $resource;
        $this->resource = is_array($resource) ? $resource : [];
    }

    public function setBotTypes(array $data): void
    {
        $id = (int)($data['id'] ?? 0);
        if ($id <= 0) return;

        $month_in_seconds = 2592000;

        $name = (string)($data['name'] ?? 'Unknown');

        $ress_val_met = (float)($data['ress_value_metal'] ?? 0.0);
        $ress_val_cry = (float)($data['ress_value_crystal'] ?? 0.0);
        $ress_val_deu = (float)($data['ress_value_deuterium'] ?? 0.0);

        $min_space  = (int)($data['min_fleet_seconds_in_space'] ?? 3600);
        $max_space  = (int)($data['max_fleet_seconds_in_space'] ?? 7200);
        $min_planet = (int)($data['min_fleet_seconds_on_planet'] ?? 600);
        $max_planet = (int)($data['max_fleet_seconds_on_planet'] ?? 3600);

        // NEW toggles
        $enabled        = isset($data['enabled']) ? 1 : 0;
        $pve_enabled    = isset($data['pve_enabled']) ? 1 : 0;
        $pvp_enabled    = isset($data['pvp_enabled']) ? 1 : 0;

        $can_build      = isset($data['can_build']) ? 1 : 0;
        $can_research   = isset($data['can_research']) ? 1 : 0;
        $can_shipyard   = isset($data['can_shipyard']) ? 1 : 0;

        $can_expedition = isset($data['can_expedition']) ? 1 : 0;
        $can_recycle    = isset($data['can_recycle']) ? 1 : 0;
        $can_raid       = isset($data['can_raid']) ? 1 : 0;

        $max_actions_per_tick    = max(1, (int)($data['max_actions_per_tick'] ?? 2));
        $max_build_queue_fill    = max(0, (int)($data['max_build_queue_fill'] ?? 1));
        $max_research_queue_fill = max(0, (int)($data['max_research_queue_fill'] ?? 1));
        $max_shipyard_queue_fill = max(0, (int)($data['max_shipyard_queue_fill'] ?? 1));

        // NEW PVP rules
        $raid_min_cargo_metal   = max(0, (int)($data['raid_min_cargo_metal'] ?? 20000));
        $raid_min_cargo_crystal = max(0, (int)($data['raid_min_cargo_crystal'] ?? 10000));
        $raid_min_gain          = max(0, (int)($data['raid_min_gain'] ?? 50000));
        $raid_max_flight_seconds= max(600, (int)($data['raid_max_flight_seconds'] ?? 14400));
        $raid_max_rank_diff     = max(0, (int)($data['raid_max_rank_diff'] ?? 250));
        $raid_inactive_only     = isset($data['raid_inactive_only']) ? 1 : 0;
        $raid_allow_same_ally   = isset($data['raid_allow_same_ally']) ? 1 : 0;

        // Ships preset (serialized)
        $ships_array = trim((string)($data['ships_array'] ?? ''));
        if ($ships_array === '') {
            $ships_array = null;
        }

        $sql = "UPDATE " . DB_PREFIX . "bot_setting SET
                name = :name,
                ress_value_metal = :rvm,
                ress_value_crystal = :rvc,
                ress_value_deuterium = :rvd,
                min_fleet_seconds_in_space = :minfs,
                max_fleet_seconds_in_space = :maxfs,
                min_fleet_seconds_on_planet = :minfp,
                max_fleet_seconds_on_planet = :maxfp,

                enabled = :enabled,
                pve_enabled = :pve,
                pvp_enabled = :pvp,

                can_build = :cb,
                can_research = :cr,
                can_shipyard = :cs,
                can_expedition = :ce,
                can_recycle = :cRec,
                can_raid = :cRaid,

                max_actions_per_tick = :mat,
                max_build_queue_fill = :mbq,
                max_research_queue_fill = :mrq,
                max_shipyard_queue_fill = :msq,

                raid_min_cargo_metal = :rmm,
                raid_min_cargo_crystal = :rmc,
                raid_min_gain = :rmg,
                raid_max_flight_seconds = :rmfs,
                raid_max_rank_diff = :rmrd,
                raid_inactive_only = :rio,
                raid_allow_same_ally = :rasa,

                ships_array = :ships
                WHERE id = :id";

        $this->db->update($sql, [
            ':name'  => $name,
            ':rvm'   => $ress_val_met,
            ':rvc'   => $ress_val_cry,
            ':rvd'   => $ress_val_deu,
            ':minfs' => $min_space,
            ':maxfs' => $max_space,
            ':minfp' => $min_planet,
            ':maxfp' => $max_planet,

            ':enabled' => $enabled,
            ':pve'     => $pve_enabled,
            ':pvp'     => $pvp_enabled,

            ':cb'   => $can_build,
            ':cr'   => $can_research,
            ':cs'   => $can_shipyard,
            ':ce'   => $can_expedition,
            ':cRec' => $can_recycle,
            ':cRaid'=> $can_raid,

            ':mat' => $max_actions_per_tick,
            ':mbq' => $max_build_queue_fill,
            ':mrq' => $max_research_queue_fill,
            ':msq' => $max_shipyard_queue_fill,

            ':rmm'  => $raid_min_cargo_metal,
            ':rmc'  => $raid_min_cargo_crystal,
            ':rmg'  => $raid_min_gain,
            ':rmfs' => $raid_max_flight_seconds,
            ':rmrd' => $raid_max_rank_diff,
            ':rio'  => $raid_inactive_only,
            ':rasa' => $raid_allow_same_ally,

            ':ships'=> $ships_array,
            ':id'   => $id
        ]);

        $this->recalculateContingents($id, $month_in_seconds, $ress_val_met, $ress_val_cry, $ress_val_deu);
    }

    private function recalculateContingents(int $botTypeId, int $monthSeconds, float $valMet, float $valCry, float $valDeu): void
    {
        $settings = $this->db->selectSingle("SELECT * FROM " . DB_PREFIX . "bot_setting WHERE id = :id", [':id' => $botTypeId]);
        if (!$settings) return;

        $botCount = (int)$this->db->selectSingle(
            "SELECT count(*) as count FROM " . DB_PREFIX . "bots WHERE bot_type = :id",
            [':id' => $botTypeId],
            'count'
        );

        if ($monthSeconds <= 0) $monthSeconds = 2592000;

        $monthlyRessPoints = (float)($settings['ress_contingent'] ?? 0);
        $numberOfBotsFactor = ($botCount > 0) ? (1 / $botCount) : 0;

        $metPerSec = ($monthlyRessPoints * $valMet / $monthSeconds) * $numberOfBotsFactor;
        $cryPerSec = ($monthlyRessPoints * $valCry / $monthSeconds) * $numberOfBotsFactor;
        $deuPerSec = ($monthlyRessPoints * $valDeu / $monthSeconds) * $numberOfBotsFactor;

        $sql = "UPDATE " . DB_PREFIX . "bot_setting SET
                metal_per_second = :mps,
                crystal_per_second = :cps,
                deuterium_per_second = :dps,
                number_of_bots = :count
                WHERE id = :id";

        $this->db->update($sql, [
            ':mps'   => $metPerSec,
            ':cps'   => $cryPerSec,
            ':dps'   => $deuPerSec,
            ':count' => $botCount,
            ':id'    => $botTypeId
        ]);
    }

    public function setOnAllPlanets(int $elementId, int $value): void
    {
        if (!isset($this->resource[$elementId])) return;

        $fieldName = $this->resource[$elementId];

        $sql = "UPDATE %%PLANETS%% as p
                INNER JOIN " . DB_PREFIX . "bots as b ON p.id_owner = b.id_owner
                SET p.`{$fieldName}` = :value";

        $this->db->update($sql, [':value' => $value]);
    }

    public function createBots(int $amount, int $planetsPerBot): void
    {
        $debugLog = ROOT_PATH . 'bot_generator_debug.txt';
        
        $namesFile = 'includes/classes/bot/botnames.txt';
        $names = file_exists($namesFile)
            ? file($namesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : ['BotAlpha', 'BotBeta', 'BotGamma', 'BotDelta'];

        file_put_contents($debugLog, "Names loaded: " . count($names) . "\n", FILE_APPEND);

        $botTypes = $this->db->select("SELECT id FROM " . DB_PREFIX . "bot_setting");
        if (empty($botTypes)) {
            file_put_contents($debugLog, "ERROR: No bot types found!\n", FILE_APPEND);
            return;
        }

        file_put_contents($debugLog, "Bot types found: " . count($botTypes) . "\n", FILE_APPEND);

        for ($i = 0; $i < $amount; $i++) {
            file_put_contents($debugLog, "Creating bot " . ($i + 1) . "/" . $amount . "...\n", FILE_APPEND);
            
            $randomName = trim($names[array_rand($names)]) . rand(100, 999);
            $randomPass = 'bot_' . md5(uniqid((string)mt_rand(), true));
            $randomMail = $randomName . '@bot.game';
            $randomType = (int)$botTypes[array_rand($botTypes)]['id'];

            file_put_contents($debugLog, "Name: {$randomName}, Type: {$randomType}\n", FILE_APPEND);

            try {
                $universe = Universe::current();
                file_put_contents($debugLog, "Universe: " . $universe . "\n", FILE_APPEND);

                list($newUserId, $newPlanetId) = PlayerUtil::createPlayer(
                    $universe,
                    $randomName,
                    PlayerUtil::cryptPassword($randomPass),
                    $randomMail,
                    'de',  // WICHTIG: Sprache setzen!
                    null, null, null,
                    null,
                    0,
                    '127.0.0.1'
                );

                file_put_contents($debugLog, "PlayerUtil::createPlayer SUCCESS! UserID: {$newUserId}, PlanetID: {$newPlanetId}\n", FILE_APPEND);

                $sql = "INSERT INTO " . DB_PREFIX . "bots SET
                        id_owner = :userid,
                        bot_type = :type,
                        last_login = :time,
                        next_fleet_action = :time";

                $this->db->insert($sql, [
                    ':userid' => (int)$newUserId,
                    ':type'   => $randomType,
                    ':time'   => time()
                ]);

                file_put_contents($debugLog, "Inserted into bots table\n", FILE_APPEND);

                // WICHTIG: is_bot Flag setzen!
                $this->db->update(
                    "UPDATE %%USERS%% SET is_bot = 1 WHERE id = :id",
                    [':id' => (int)$newUserId]
                );

                file_put_contents($debugLog, "is_bot flag set\n", FILE_APPEND);

                if ($planetsPerBot > 1) {
                    for ($p = 1; $p < $planetsPerBot; $p++) {
                        $this->createColonyForBot((int)$newUserId);
                    }
                }
                
                file_put_contents($debugLog, "Bot " . ($i + 1) . " COMPLETE!\n", FILE_APPEND);
                
            } catch (Throwable $e) {
                file_put_contents($debugLog, "CATCH ERROR Bot " . ($i + 1) . ": " . $e->getMessage() . "\n", FILE_APPEND);
                file_put_contents($debugLog, "File: " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
                continue;
            }
        }

        file_put_contents($debugLog, "All bots processed. Running contingent calculation...\n", FILE_APPEND);

        foreach ($botTypes as $bt) {
            $settings = $this->db->selectSingle(
                "SELECT * FROM " . DB_PREFIX . "bot_setting WHERE id = :id",
                [':id' => (int)$bt['id']]
            );
            if (!$settings) continue;

            $this->recalculateContingents(
                (int)$bt['id'],
                2592000,
                (float)($settings['ress_value_metal'] ?? 0),
                (float)($settings['ress_value_crystal'] ?? 0),
                (float)($settings['ress_value_deuterium'] ?? 0)
            );
        }
    }

    private function createColonyForBot(int $userId): void
    {
        $uni = Universe::current();
        for ($try = 0; $try < 5; $try++) {
            $g = rand(1, (int)Config::get()->max_galaxy);
            $s = rand(1, (int)Config::get()->max_system);
            $p = rand(4, 12);

            if (PlayerUtil::isPositionFree($uni, $g, $s, $p)) {
                PlayerUtil::createPlanet($g, $s, $p, $uni, $userId, 'Colony', false);
                break;
            }
        }
    }

    public function deleteBot(int $botId): void
    {
        $botData = $this->db->selectSingle(
            "SELECT id_owner FROM " . DB_PREFIX . "bots WHERE id = :id",
            [':id' => $botId]
        );

        if (!$botData) return;

        $userId = (int)$botData['id_owner'];
        $this->db->delete("DELETE FROM " . DB_PREFIX . "bots WHERE id = :id", [':id' => $botId]);

        if ($userId > 0) PlayerUtil::deletePlayer($userId);
    }

    // GETTERS

    public function getBotTypes(): array
    {
        return $this->db->select("SELECT * FROM " . DB_PREFIX . "bot_setting ORDER BY id ASC");
    }

    public function getBots(): array
    {
        $sql = "SELECT b.*, u.username, u.onlinetime, u.galaxy, u.system, u.planet, u.ally_id, a.ally_name, bs.name as bot_type_name
                FROM " . DB_PREFIX . "bots as b
                LEFT JOIN %%USERS%% as u ON b.id_owner = u.id
                LEFT JOIN %%ALLIANCE%% as a ON u.ally_id = a.id
                LEFT JOIN " . DB_PREFIX . "bot_setting as bs ON b.bot_type = bs.id
                ORDER BY b.id ASC";
        return $this->db->select($sql);
    }

    public function getContingents(): array
    {
        return $this->db->select("SELECT * FROM " . DB_PREFIX . "bot_setting");
    }

    public function getBotShips(): array
    {
        $sql = "SELECT b.id, b.id_owner, b.bot_type, b.next_fleet_action, b.ships_array, u.username
                FROM " . DB_PREFIX . "bots as b
                LEFT JOIN %%USERS%% as u ON b.id_owner = u.id";

        $result = $this->db->select($sql);
        $final = [];

        foreach ($result as $row) {
            $ships = [];
            if (!empty($row['ships_array'])) {
                try {
                    $tmp = @unserialize((string)$row['ships_array'], ['allowed_classes' => false]);
                    if (is_array($tmp)) $ships = $tmp;
                } catch (Throwable $e) {
                    $ships = [];
                }
            }

            $count = 0;
            if (is_array($ships)) {
                foreach ($ships as $k => $v) {
                    if (is_numeric($k)) {
                        $count += (int)$v;
                    } elseif (is_array($v)) {
                        $count += (int)($v['amount'] ?? 0);
                    }
                }
            }

            $final[] = [
                'id' => (int)$row['id'],
                'username' => (string)($row['username'] ?? ''),
                'bot_type' => (int)$row['bot_type'],
                'next_fleet_action' => (int)$row['next_fleet_action'],
                'total_ships' => $count
            ];
        }

        return $final;
    }

    public function getBotPlanets(): array
    {
        $sql = "SELECT p.*, u.username
                FROM %%PLANETS%% as p
                INNER JOIN " . DB_PREFIX . "bots as b ON p.id_owner = b.id_owner
                LEFT JOIN %%USERS%% as u ON p.id_owner = u.id
                ORDER BY p.id_owner ASC, p.id ASC";

        return $this->db->select($sql);
    }
}