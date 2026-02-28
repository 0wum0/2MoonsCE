<?php

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
 
if (!allowedTo(str_replace(array(dirname(__FILE__), '\\', '/', '.php'), '', __FILE__))) {
    throw new Exception("Permission error!");
}

function ShowEditBotsPage()
{
    global $LNG, $USER;

    // Load BotManager
    if (file_exists('includes/classes/class.BotManager.php')) {
        require_once 'includes/classes/class.BotManager.php';
    } elseif (file_exists('includes/classes/bot/class.BotManager.php')) {
        require_once 'includes/classes/bot/class.BotManager.php';
    } else {
        throw new Exception("BotManager class not found!");
    }

    $botManager = new BotManager();
    $template   = new template();
    $db         = Database::get();

    $mode = HTTP::_GP('mode', 'default');
    $message = [];

    // ==================== AJAX CONTINGENT UPDATE (ORIGINAL) ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bot_type'], $_POST['userid'])) {

        $botType = (int)$_POST['bot_type'];
        $field = '';
        $value = 0;

        if (isset($_POST['ress_contingent'])) {
            $field = 'ress_contingent';
            $value = (float)$_POST['ress_contingent'];
        } elseif (isset($_POST['ress_ships_contingent'])) {
            $field = 'ress_ships_contingent';
            $value = (float)$_POST['ress_ships_contingent'];
        } elseif (isset($_POST['full_contingent'])) {
            $field = 'full_contingent';
            $value = (float)$_POST['full_contingent'];
        }

        if ($field !== '') {
            $sql = "UPDATE " . DB_PREFIX . "bot_setting SET `{$field}` = :value WHERE id = :id;";
            $db->update($sql, [':value' => $value, ':id' => $botType]);
            echo "OK";
            exit;
        }
    }

    // ==================== AJAX LIVE MONITOR (NEW) ====================
    if ($mode === 'live_monitor' && HTTP::_GP('ajax', '')) {
        sendLiveMonitorData($db);
        exit;
    }

    // ==================== ORIGINAL POST ACTIONS ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Save Bot Types (Original)
        if (isset($_POST['save_bot_types'])) {
            $botManager->setBotTypes($_POST);
            $message = ['class' => 'success', 'text' => 'Bot-Strategien erfolgreich gespeichert.'];
        }

        // Set on all planets (Original)
        if (isset($_POST['elementid'], $_POST['elemval'])) {
            $botManager->setOnAllPlanets((int)$_POST['elementid'], (int)$_POST['elemval']);
            $message = ['class' => 'success', 'text' => 'Planeten-Einstellungen wurden auf alle Bots angewendet.'];
        }

        // Create Bots (Original)
        if (in_array($mode, ['create_bot', 'show_create_bot'], true) && isset($_POST['number_of_bots'])) {
            $amountBots = (int)$_POST['number_of_bots'];
            $planetsPerBot = (int)($_POST['number_of_planets'] ?? 1);

            if ($amountBots < 1) $amountBots = 1;
            if ($planetsPerBot < 1) $planetsPerBot = 1;

            $botManager->createBots($amountBots, $planetsPerBot);
            $message = ['class' => 'success', 'text' => $amountBots . ' Bots erfolgreich erstellt!'];
        }

        // Delete Bot (Original)
        if ($mode === 'delete_bot' && isset($_POST['bot_id'])) {
            $botManager->deleteBot((int)$_POST['bot_id']);
            $message = ['class' => 'warning', 'text' => 'Bot ID ' . (int)$_POST['bot_id'] . ' wurde gel�scht.'];
            $mode = 'show_edit_bot_users';
        }

        // ==================== NEW: BOT SETTINGS SAVE ====================
        if (isset($_POST['save_bot_settings'])) {
            $settings = [
                'bots_per_tick' => max(1, min(50, (int)HTTP::_GP('bots_per_tick', 5))),
                'max_actions_per_tick' => max(1, min(10, (int)HTTP::_GP('max_actions_per_tick', 3))),
                'min_tick_delay' => max(300, (int)HTTP::_GP('min_tick_delay', 1800)),
                'max_tick_delay' => max(1800, (int)HTTP::_GP('max_tick_delay', 7200)),
                'crash_delay' => max(300, (int)HTTP::_GP('crash_delay', 1800)),
                'random_timing' => HTTP::_GP('random_timing', '') ? 1 : 0,
                'spread_bots' => HTTP::_GP('spread_bots', '') ? 1 : 0,
            ];

            // Als JSON in config/bot_config.json speichern
            $configFile = ROOT_PATH . 'config/bot_config.json';
            $configDir = dirname($configFile);
            if (!is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }
            file_put_contents($configFile, json_encode($settings, JSON_PRETTY_PRINT));

            $message = ['class' => 'success', 'text' => 'Bot-Einstellungen erfolgreich gespeichert!'];
        }

        // ==================== NEW: AI BEHAVIOR SAVE ====================
        if (isset($_POST['save_ai_behavior'])) {
            $typeId = (int)HTTP::_GP('type_id', 0);
            
            $aiData = [
                'build_strategy' => HTTP::_GP('build_strategy', 'balanced'),
                'research_strategy' => HTTP::_GP('research_strategy', 'balanced'),
                'shipyard_focus' => HTTP::_GP('shipyard_focus', 'mixed_fleet'),
                'min_resources_for_upgrade' => max(0, (int)HTTP::_GP('min_resources_for_upgrade', 5000)),
                'expedition_frequency' => HTTP::_GP('expedition_frequency', 'when_idle'),
                'auto_recycle' => HTTP::_GP('auto_recycle', 'when_worth'),
                'fleet_percentage' => max(10, min(100, (int)HTTP::_GP('fleet_percentage', 50))),
                'max_mission_distance' => max(0, (int)HTTP::_GP('max_mission_distance', 100)),
                'decision_speed' => HTTP::_GP('decision_speed', 'normal'),
                'risk_tolerance' => max(0, min(100, (int)HTTP::_GP('risk_tolerance', 30))),
                'priority_metal' => (float)HTTP::_GP('priority_metal', 1.0),
                'priority_crystal' => (float)HTTP::_GP('priority_crystal', 0.7),
                'priority_deut' => (float)HTTP::_GP('priority_deut', 0.5),
                'auto_research' => HTTP::_GP('auto_research', '') ? 1 : 0,
                'smart_fleet_save' => HTTP::_GP('smart_fleet_save', '') ? 1 : 0,
                'opportunistic_raids' => HTTP::_GP('opportunistic_raids', '') ? 1 : 0,
            ];

            $jsonData = json_encode($aiData);
            $sql = "UPDATE " . DB_PREFIX . "bot_setting SET ai_behavior = :data WHERE id = :id";
            $db->update($sql, [':data' => $jsonData, ':id' => $typeId]);

            $message = ['class' => 'success', 'text' => 'AI Behavior erfolgreich gespeichert!'];
        }

        // ==================== NEW: DEV TOOLS ACTIONS ====================
        if ($mode === 'dev_tools') {
            $action = HTTP::_GP('action', '');

            if ($action === 'quick_boost') {
                $mineLevel = (int)HTTP::_GP('mine_level', 10);
                $powerLevel = (int)HTTP::_GP('power_level', 10);
                $astroLevel = (int)HTTP::_GP('astro_level', 5);

                quickBoostAllBots($db, $mineLevel, $powerLevel, $astroLevel);
                $message = ['class' => 'success', 'text' => "Alle Bots geboostet! (Mines: {$mineLevel}, Power: {$powerLevel}, Astro: {$astroLevel})"];
            }

            if ($action === 'inject_resources') {
                $metal = (int)HTTP::_GP('inject_metal', 0);
                $crystal = (int)HTTP::_GP('inject_crystal', 0);
                $deuterium = (int)HTTP::_GP('inject_deuterium', 0);

                injectResourcesToAllBots($db, $metal, $crystal, $deuterium);
                $message = ['class' => 'success', 'text' => "Ressourcen injiziert! (M: {$metal}, C: {$crystal}, D: {$deuterium})"];
            }

            if ($action === 'spawn_fleet') {
                $ships = [];
                foreach ($_POST as $key => $val) {
                    if (strpos($key, 'ship_') === 0) {
                        $shipId = (int)str_replace('ship_', '', $key);
                        $amount = (int)$val;
                        if ($amount > 0) {
                            $ships[$shipId] = $amount;
                        }
                    }
                }

                spawnFleetOnAllBots($db, $ships);
                $message = ['class' => 'success', 'text' => 'Flotte auf alle Bot-Planeten gespawnt!'];
            }

            if ($action === 'trigger_all_now') {
                $sql = "UPDATE " . DB_PREFIX . "bots SET next_fleet_action = UNIX_TIMESTAMP()";
                $db->update($sql, []);
                $message = ['class' => 'success', 'text' => 'Alle Bots getriggert! Next tick = JETZT'];
            }
        }
    }

    // ==================== PREPARE TEMPLATE DATA ====================
    $tplData = [];

    switch ($mode) {
        case 'show_all_bots':
            $tplData['botList'] = $botManager->getBotShips();
            break;

        case 'show_edit_bot_types':
            $tplData['botTypes'] = $botManager->getBotTypes();
            break;

        case 'ai_behavior':
            $tplData['botTypes'] = getBotTypesWithAI($db);
            break;

        case 'bot_settings':
            // Load bot config
            $configFile = ROOT_PATH . 'config/bot_config.json';
            if (file_exists($configFile)) {
                $tplData['bot_config'] = json_decode(file_get_contents($configFile), true) ?: [];
            } else {
                // Defaults
                $tplData['bot_config'] = [
                    'bots_per_tick' => 5,
                    'max_actions_per_tick' => 3,
                    'min_tick_delay' => 1800,
                    'max_tick_delay' => 7200,
                    'crash_delay' => 1800,
                    'random_timing' => false,
                    'spread_bots' => true,
                ];
            }
            break;

        case 'show_edit_bot_users':
            $tplData['activeBots'] = $botManager->getBots();
            break;

        case 'show_edit_bot_planets':
            $tplData['botPlanets'] = $botManager->getBotPlanets();
            break;

        case 'live_monitor':
            $tplData['stats'] = getLiveStats($db);
            break;

        case 'analytics':
            $tplData['analytics'] = getAnalyticsData($db);
            break;

        case 'dev_tools':
            // No data needed, just form
            break;

        case 'create_bot':
        case 'show_create_bot':
            break;

        default:
            $tplData['contingents'] = $botManager->getContingents();
            break;
    }

    $template->assign_vars([
        'mode'    => $mode,
        'message' => $message,
        'data'    => $tplData,
    ]);

    $template->show('EditBots.twig');
}

// ==================== NEW HELPER FUNCTIONS ====================

function getBotTypesWithAI($db): array
{
    $sql = "SELECT * FROM " . DB_PREFIX . "bot_setting ORDER BY id ASC";
    $types = $db->select($sql);

    foreach ($types as &$type) {
        if (!empty($type['ai_behavior'])) {
            $decoded = json_decode($type['ai_behavior'], true);
            if (is_array($decoded)) {
                $type = array_merge($type, $decoded);
            }
        }
    }

    return $types;
}

function getLiveStats($db): array
{
    $activeBots = (int)$db->selectSingle(
        "SELECT COUNT(*) as c FROM " . DB_PREFIX . "bots",
        [],
        'c'
    );

    $buildingQueue = (int)$db->selectSingle(
        "SELECT COUNT(*) as c FROM %%PLANETS%% p 
         INNER JOIN " . DB_PREFIX . "bots b ON p.id_owner = b.id_owner
         WHERE p.b_building_id IS NOT NULL AND p.b_building_id != ''",
        [],
        'c'
    );

    $researchQueue = (int)$db->selectSingle(
        "SELECT COUNT(*) as c FROM %%USERS%% u
         INNER JOIN " . DB_PREFIX . "bots b ON u.id = b.id_owner
         WHERE u.b_tech_queue IS NOT NULL AND u.b_tech_queue != ''",
        [],
        'c'
    );

    $fleetsFlying = (int)$db->selectSingle(
        "SELECT COUNT(*) as c FROM %%FLEETS%% f
         INNER JOIN " . DB_PREFIX . "bots b ON f.fleet_owner = b.id_owner",
        [],
        'c'
    );

    return [
        'active_bots' => $activeBots,
        'building_queue' => $buildingQueue,
        'research_queue' => $researchQueue,
        'fleets_flying' => $fleetsFlying,
    ];
}

function sendLiveMonitorData($db): void
{
    $lastTimestamp = (int)HTTP::_GP('last', 0);
    $logFile = ROOT_PATH . 'bot_actions_debug.txt';
    $logs = [];

    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines);

        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (.+)/', $line, $m)) {
                $time = $m[1];
                $msg = $m[2];

                $timestamp = strtotime($time);
                if ($timestamp <= $lastTimestamp) break;

                $type = 'info';
                if (stripos($msg, 'CRASH') !== false || stripos($msg, 'ERROR') !== false) {
                    $type = 'error';
                } elseif (stripos($msg, 'queued') !== false || stripos($msg, 'send') !== false) {
                    $type = 'success';
                }

                $logs[] = [
                    'time' => $time,
                    'message' => $msg,
                    'type' => $type,
                ];
            }

            if (count($logs) >= 20) break;
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'stats' => getLiveStats($db),
        'logs' => array_reverse($logs),
        'last_timestamp' => time(),
    ]);
}

function quickBoostAllBots($db, int $mineLevel, int $powerLevel, int $astroLevel): void
{
    global $resource;

    $sql = "SELECT id_owner FROM " . DB_PREFIX . "bots";
    $bots = $db->select($sql);

    foreach ($bots as $bot) {
        $ownerId = (int)$bot['id_owner'];

        // Update planets
        $sql = "UPDATE %%PLANETS%% SET 
                {$resource[1]} = :mine,
                {$resource[2]} = :mine,
                {$resource[3]} = :mine,
                {$resource[4]} = :power,
                {$resource[12]} = :power
                WHERE id_owner = :owner";

        $db->update($sql, [
            ':mine' => $mineLevel,
            ':power' => $powerLevel,
            ':owner' => $ownerId,
        ]);

        // Update user
        $sql = "UPDATE %%USERS%% SET {$resource[124]} = :astro WHERE id = :owner";
        $db->update($sql, [':astro' => $astroLevel, ':owner' => $ownerId]);
    }
}

function injectResourcesToAllBots($db, int $metal, int $crystal, int $deuterium): void
{
    $sql = "UPDATE %%PLANETS%% p 
            INNER JOIN " . DB_PREFIX . "bots b ON p.id_owner = b.id_owner
            SET p.metal = p.metal + :m,
                p.crystal = p.crystal + :c,
                p.deuterium = p.deuterium + :d";

    $db->update($sql, [':m' => $metal, ':c' => $crystal, ':d' => $deuterium]);
}

function spawnFleetOnAllBots($db, array $ships): void
{
    global $resource;
    if (empty($ships)) return;

    $sql = "SELECT p.id FROM %%PLANETS%% p 
            INNER JOIN " . DB_PREFIX . "bots b ON p.id_owner = b.id_owner";
    $planets = $db->select($sql);

    foreach ($planets as $planet) {
        $updates = [];
        $params = [':pid' => (int)$planet['id']];

        foreach ($ships as $shipId => $amount) {
            if (!isset($resource[$shipId])) continue;
            $field = $resource[$shipId];
            $updates[] = "{$field} = {$field} + :{$field}";
            $params[":{$field}"] = $amount;
        }

        if (!empty($updates)) {
            $sql = "UPDATE %%PLANETS%% SET " . implode(', ', $updates) . " WHERE id = :pid";
            $db->update($sql, $params);
        }
    }
}

function getAnalyticsData($db): array
{
    $now = time();
    $last24h = $now - 86400;

    // KPIs
    $totalActions = (int)$db->selectSingle(
        "SELECT COALESCE(SUM(actions_total), 0) as total 
         FROM " . DB_PREFIX . "bot_stats 
         WHERE timestamp >= :since",
        [':since' => $last24h],
        'total'
    ) ?: 0;

    $totalResources = (int)$db->selectSingle(
        "SELECT COALESCE(SUM(metal_gained + crystal_gained + deuterium_gained), 0) as total 
         FROM " . DB_PREFIX . "bot_stats 
         WHERE timestamp >= :since",
        [':since' => $last24h],
        'total'
    ) ?: 0;

    $resourcesPerHour = $totalResources > 0 ? round($totalResources / 24) : 0;

    // Erfolgsquoten
    $raids = $db->selectSingle(
        "SELECT 
            COALESCE(SUM(raids_sent), 0) as total,
            COALESCE(SUM(raids_success), 0) as success
         FROM " . DB_PREFIX . "bot_stats 
         WHERE timestamp >= :since",
        [':since' => $last24h]
    ) ?: ['total' => 0, 'success' => 0];

    $expos = $db->selectSingle(
        "SELECT 
            COALESCE(SUM(expeditions_sent), 0) as total,
            COALESCE(SUM(expeditions_success), 0) as success
         FROM " . DB_PREFIX . "bot_stats 
         WHERE timestamp >= :since",
        [':since' => $last24h]
    ) ?: ['total' => 0, 'success' => 0];

    $raidSuccessRate = $raids['total'] > 0 ? round(($raids['success'] / $raids['total']) * 100) : 0;
    $expoSuccessRate = $expos['total'] > 0 ? round(($expos['success'] / $expos['total']) * 100) : 0;
    $overallSuccess = $totalActions > 0 ? round((($raids['success'] + $expos['success']) / $totalActions) * 100) : 0;

    // Aktive Flotten
    $fleetsActive = (int)$db->selectSingle(
        "SELECT COUNT(*) as c FROM %%FLEETS%% f
         INNER JOIN " . DB_PREFIX . "bots b ON f.fleet_owner = b.id_owner",
        [],
        'c'
    ) ?: 0;

    // Hourly Activity (24h Heatmap)
    $hourlyActivity = array_fill(0, 24, 0);
    $hourlyData = $db->select(
        "SELECT hour, SUM(actions_total) as actions 
         FROM " . DB_PREFIX . "bot_stats 
         WHERE timestamp >= :since 
         GROUP BY hour",
        [':since' => $last24h]
    );
    foreach ($hourlyData as $row) {
        $hourlyActivity[(int)$row['hour']] = (int)$row['actions'];
    }

    // Resource Flow (letzte 12 Stunden, 1h Intervalle)
    $timeLabels = [];
    $metalFlow = [];
    $crystalFlow = [];
    $deutFlow = [];

    for ($i = 11; $i >= 0; $i--) {
        $hourStart = $now - ($i * 3600);
        $hourEnd = $hourStart + 3600;
        
        $timeLabels[] = date('H:i', $hourStart);
        
        $ress = $db->selectSingle(
            "SELECT 
                COALESCE(SUM(metal_gained), 0) as m,
                COALESCE(SUM(crystal_gained), 0) as c,
                COALESCE(SUM(deuterium_gained), 0) as d
             FROM " . DB_PREFIX . "bot_stats 
             WHERE timestamp >= :start AND timestamp < :end",
            [':start' => $hourStart, ':end' => $hourEnd]
        ) ?: ['m' => 0, 'c' => 0, 'd' => 0];

        $metalFlow[] = (int)$ress['m'];
        $crystalFlow[] = (int)$ress['c'];
        $deutFlow[] = (int)$ress['d'];
    }

    // Top 10 Bots
    $topBots = $db->select(
        "SELECT 
            u.username,
            SUM(s.actions_total) as actions,
            SUM(s.metal_gained + s.crystal_gained + s.deuterium_gained) as resources,
            SUM(s.raids_sent + s.expeditions_sent + s.recycle_sent) as fleets
         FROM " . DB_PREFIX . "bot_stats s
         INNER JOIN " . DB_PREFIX . "bots b ON s.bot_id = b.id
         INNER JOIN %%USERS%% u ON b.id_owner = u.id
         WHERE s.timestamp >= :since
         GROUP BY s.bot_id, u.username
         ORDER BY actions DESC
         LIMIT 10",
        [':since' => $last24h]
    ) ?: [];

    return [
        'total_actions' => $totalActions,
        'resources_per_hour' => $resourcesPerHour,
        'success_rate' => $overallSuccess,
        'fleets_active' => $fleetsActive,
        
        'raid_success_rate' => $raidSuccessRate,
        'raids_success' => (int)$raids['success'],
        'raids_total' => (int)$raids['total'],
        
        'expo_success_rate' => $expoSuccessRate,
        'expos_success' => (int)$expos['success'],
        'expos_total' => (int)$expos['total'],
        
        'hourly_activity' => $hourlyActivity,
        
        'time_labels' => $timeLabels,
        'metal_flow' => $metalFlow,
        'crystal_flow' => $crystalFlow,
        'deut_flow' => $deutFlow,
        
        'top_bots' => $topBots,
    ];
}

