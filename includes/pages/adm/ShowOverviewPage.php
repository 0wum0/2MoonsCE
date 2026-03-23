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

// @admin-style (already PDO, style pass applied)
if (!allowedTo(str_replace([dirname(__FILE__), '\\', '/', '.php'], '', __FILE__))) {
    exit;
}

function ShowOverviewPage(): void
{
    global $LNG;

    $db     = Database::get();
    $config = Config::get(ROOT_UNI);

    if (!$db->isConnected()) {
        echo '<div style="color:#f87171;padding:20px;">Datenbankverbindung nicht verfügbar.</div>';
        return;
    }

    $period  = HTTP::_GP('period', 'day'); // day, week, month, year
    $endTime = TIMESTAMP;

    switch ($period) {
        case 'week':
            $startTime  = strtotime('-7 days', $endTime);
            $dateFmtPHP = 'd.m.';
            $step       = 86400;
            break;

        case 'month':
            $startTime  = strtotime('-30 days', $endTime);
            $dateFmtPHP = 'd.m.';
            $step       = 86400;
            break;

        case 'year':
            $startTime  = strtotime('-1 year', $endTime);
            $dateFmtPHP = 'M Y';
            $step       = 2592000; // ~30 Tage
            break;

        case 'day':
        default:
            $period     = 'day';
            $startTime  = strtotime('-24 hours', $endTime);
            $dateFmtPHP = 'H:00';
            $step       = 3600;
            break;
    }

    /* =========================
       KPI: LIVE COUNTER
    ==========================*/

    $onlineCount = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%USERS%% WHERE onlinetime >= :time",
        [':time' => TIMESTAMP - 900],
        'count'
    );

    $totalUsers = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%USERS%%",
        [],
        'count'
    );

    $newRegs = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%USERS%% WHERE register_time >= :time",
        [':time' => $startTime],
        'count'
    );

    $flyingFleets = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%FLEETS%%",
        [],
        'count'
    );

    $newAllys = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%ALLIANCE%% WHERE ally_register_time >= :time",
        [':time' => $startTime],
        'count'
    );

    $totalAllys = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%ALLIANCE%%",
        [],
        'count'
    );

    // WICHTIG: message_type statt type
    $combats = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%MESSAGES%% WHERE message_type = 1 AND message_time >= :time",
        [':time' => $startTime],
        'count'
    );

    $messages = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%MESSAGES%% WHERE message_time >= :time",
        [':time' => $startTime],
        'count'
    );

    $planets = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%PLANETS%%",
        [],
        'count'
    );

    $banned = (int)$db->selectSingle(
        "SELECT COUNT(*) as count FROM %%USERS%% WHERE bana = 1",
        [],
        'count'
    );

    $tickets = 0;

    /* =========================
       MULTI-ACCOUNT FLAGS
    ==========================*/

    $multiIpsResult = $db->select(
        "SELECT user_lastip, COUNT(*) as c
         FROM %%USERS%%
         WHERE user_lastip != ''
         GROUP BY user_lastip
         HAVING c > 1"
    );

    $flaggedIps   = is_array($multiIpsResult) ? count($multiIpsResult) : 0;
    $flaggedUsers = 0;

    if (is_array($multiIpsResult)) {
        foreach ($multiIpsResult as $row) {
            if (!isset($row['c'])) {
                error_log('[ShowOverviewPage] getMultiaccountFlags: missing c key in row');
            }
            $flaggedUsers += (int) ($row['c'] ?? 0);
        }
    }

    /* =========================
       TOP / ACTIVE PLAYERS
    ==========================*/

    $topPlayers = $db->select(
        "SELECT u.id, u.username, s.total_points as points
         FROM %%STATPOINTS%% s
         LEFT JOIN %%USERS%% u ON u.id = s.id_owner
         WHERE s.stat_type = 1
         ORDER BY s.total_points DESC
         LIMIT 5"
    );

    $activePlayers = $db->select(
        "SELECT id, username, onlinetime
         FROM %%USERS%%
         WHERE onlinetime >= :time AND authlevel = 0
         ORDER BY onlinetime DESC
         LIMIT 10",
        [':time' => TIMESTAMP - 86400]
    );

    /* =========================
       LAST 10 REGISTRATIONS
    ==========================*/

    $lastRegistrations = $db->select(
        "SELECT id, username, register_time, user_lastip, authlevel
         FROM %%USERS%%
         ORDER BY register_time DESC
         LIMIT 10"
    );

    /* =========================
       FLEET MISSIONS BREAKDOWN
    ==========================*/

    // Mission types: 1=attack, 2=ACS, 3=transport, 4=deploy, 5=hold, 6=spy, 7=colonize,
    //                8=recycle, 9=destroy, 10=ACS defend, 11=expedition, 15=expedition return
    $fleetMissions = $db->select(
        "SELECT fleet_mission, COUNT(*) as cnt
         FROM %%FLEETS%%
         GROUP BY fleet_mission
         ORDER BY cnt DESC"
    );

    $missionLabels = [
        1  => 'Angriff',
        2  => 'ACS Angriff',
        3  => 'Transport',
        4  => 'Stationieren',
        5  => 'Halten',
        6  => 'Spionage',
        7  => 'Kolonisieren',
        8  => 'Recyceln',
        9  => 'Zerstören',
        10 => 'ACS Verteidigung',
        11 => 'Expedition',
        15 => 'Rückkehr',
    ];

    $fleetMissionData   = [];
    $fleetMissionLabels = [];
    $totalFleetsSent    = 0;
    if (is_array($fleetMissions)) {
        foreach ($fleetMissions as $row) {
            $mid                  = (int)($row['fleet_mission'] ?? 0);
            $cnt                  = (int)($row['cnt'] ?? 0);
            $fleetMissionLabels[] = $missionLabels[$mid] ?? "Mission $mid";
            $fleetMissionData[]   = $cnt;
            $totalFleetsSent     += $cnt;
        }
    }

    /* =========================
       BUILDINGS MOST / LEAST BUILT
    ==========================*/

    // Use records table: elementID = building/research/fleet element, level = max level reached
    // Building IDs 1-44 (standard 2Moons range), get top 10 and bottom 10 by total levels built
    $buildingStats = $db->select(
        "SELECT elementID, SUM(level) as total_levels, COUNT(*) as player_count
         FROM %%RECORDS%%
         WHERE elementID BETWEEN 1 AND 44
         GROUP BY elementID
         ORDER BY total_levels DESC
         LIMIT 10"
    );

    $buildingLeast = $db->select(
        "SELECT elementID, SUM(level) as total_levels, COUNT(*) as player_count
         FROM %%RECORDS%%
         WHERE elementID BETWEEN 1 AND 44
         GROUP BY elementID
         ORDER BY total_levels ASC
         LIMIT 5"
    );

    /* =========================
       PLAYER PLAYSTYLE CLASSIFICATION
       Miner: high buildings/research points, low fleet points
       Aggressive: high fleet + many attacks (message_type=1 sent)
       Bot: authlevel < 0 or special flag
    ==========================*/

    // Real players (authlevel = 0, not banned)
    $realPlayerCount = (int)$db->selectSingle(
        "SELECT COUNT(*) as cnt FROM %%USERS%% WHERE authlevel = 0 AND bana = 0",
        [], 'cnt'
    );

    // Bot players (authlevel < 0)
    $botPlayerCount = (int)$db->selectSingle(
        "SELECT COUNT(*) as cnt FROM %%USERS%% WHERE authlevel < 0",
        [], 'cnt'
    );

    // Most online real players (last 24h, non-bot)
    $topOnlineReal = $db->select(
        "SELECT u.id, u.username, u.onlinetime
         FROM %%USERS%% u
         WHERE u.authlevel = 0 AND u.onlinetime >= :t
         ORDER BY u.onlinetime DESC
         LIMIT 10",
        [':t' => TIMESTAMP - 86400]
    );

    // Most active bots
    $topBots = $db->select(
        "SELECT id, username, onlinetime
         FROM %%USERS%%
         WHERE authlevel < 0
         ORDER BY onlinetime DESC
         LIMIT 10"
    );

    // Player playstyle: join statpoints to classify
    // stat_type 1=total, 2=buildings, 3=research, 4=fleet, 5=defence
    $playstyleData = $db->select(
        "SELECT u.id, u.username,
                MAX(CASE WHEN s.stat_type = 1 THEN s.total_points ELSE 0 END) as total_pts,
                MAX(CASE WHEN s.stat_type = 2 THEN s.total_points ELSE 0 END) as building_pts,
                MAX(CASE WHEN s.stat_type = 3 THEN s.total_points ELSE 0 END) as research_pts,
                MAX(CASE WHEN s.stat_type = 4 THEN s.total_points ELSE 0 END) as fleet_pts,
                MAX(CASE WHEN s.stat_type = 5 THEN s.total_points ELSE 0 END) as defence_pts
         FROM %%USERS%% u
         LEFT JOIN %%STATPOINTS%% s ON s.id_owner = u.id
         WHERE u.authlevel = 0 AND u.bana = 0
         GROUP BY u.id, u.username
         ORDER BY total_pts DESC
         LIMIT 20"
    );

    // Classify each player
    $miners      = [];
    $aggressors  = [];
    $neutrals    = [];

    if (is_array($playstyleData)) {
        foreach ($playstyleData as $p) {
            $total    = (int)($p['total_pts']    ?? 1);
            $fleet    = (int)($p['fleet_pts']    ?? 0);
            $building = (int)($p['building_pts'] ?? 0);
            $research = (int)($p['research_pts'] ?? 0);
            $defence  = (int)($p['defence_pts']  ?? 0);
            if ($total < 1) $total = 1;

            $fleetRatio    = $fleet    / $total;
            $buildingRatio = ($building + $research) / $total;
            $defenceRatio  = $defence  / $total;

            $entry = [
                'id'       => (int)$p['id'],
                'name'     => $p['username'],
                'total'    => $total,
                'fleet'    => $fleet,
                'building' => $building,
                'research' => $research,
                'defence'  => $defence,
            ];

            if ($fleetRatio >= 0.50) {
                $aggressors[] = $entry;
            } elseif ($buildingRatio >= 0.60) {
                $miners[] = $entry;
            } else {
                $neutrals[] = $entry;
            }
        }
    }

    /* =========================
       CHARTS: TIMESLOTS
    ==========================*/

    $timeSlots = [];
    for ($t = $startTime; $t <= $endTime; $t += $step) {
        $label = date($dateFmtPHP, $t);
        $timeSlots[$label] = 0;
    }

    $chartRegs    = $timeSlots;
    $chartCombats = $timeSlots;

    // Registrierungen
    $regDataRaw = $db->select(
        "SELECT register_time FROM %%USERS%% WHERE register_time >= :start",
        [':start' => $startTime]
    );

    if (is_array($regDataRaw)) {
        foreach ($regDataRaw as $row) {
            if (!isset($row['register_time'])) {
                error_log('[ShowOverviewPage] chart: missing register_time in row');
            }
            $key = date($dateFmtPHP, (int) ($row['register_time'] ?? 0));
            if (isset($chartRegs[$key])) {
                $chartRegs[$key]++;
            }
        }
    }

    // Kämpfe (message_type = 1)
    $msgDataRaw = $db->select(
        "SELECT message_time, message_type
         FROM %%MESSAGES%%
         WHERE message_time >= :start",
        [':start' => $startTime]
    );

    if (is_array($msgDataRaw)) {
        foreach ($msgDataRaw as $row) {
            if (!isset($row['message_time'], $row['message_type'])) {
                error_log('[ShowOverviewPage] chart: missing message_time or message_type in row');
            }
            $key = date($dateFmtPHP, (int) ($row['message_time'] ?? 0));
            if (isset($chartCombats[$key]) && (int) ($row['message_type'] ?? 0) === 1) {
                $chartCombats[$key]++;
            }
        }
    }

    // Activity + Fleets (Pseudo kombiniert)
    $finalActivity = [];
    $finalFleets   = [];

    foreach ($timeSlots as $key => $val) {
        $base            = (int) ($chartRegs[$key] ?? 0) + (int) ($chartCombats[$key] ?? 0);
        $finalActivity[] = $base + max(1, (int) ($onlineCount / 5));
        $finalFleets[]   = max(0, $base * 2 + (int) ($onlineCount / 3));
    }

    /* =========================
       BOT AKTIVITÄT (TAIL SAFE)
    ==========================*/

    $botActivity = [
        'active_last_15m' => 0,
        'last_tick_ts'    => 0,
        'last_tick_human' => '-',
        'events'          => [],
        'log_path'        => '',
    ];

    $botActiveIds = [];

    $candidatePaths = [
        ROOT_PATH . 'bot_actions_debug.txt',
        ROOT_PATH . 'cache/bot_actions_debug.txt',
        ROOT_PATH . 'includes/bot_actions_debug.txt',
    ];

    $logPath = '';
    foreach ($candidatePaths as $p) {
        if (is_file($p) && is_readable($p)) {
            $logPath = $p;
            break;
        }
    }
    $botActivity['log_path'] = $logPath;

    $readLastLinesSafe = static function (string $file, int $maxLines = 250): array {
        $maxLines = max(10, $maxLines);

        $fp = @fopen($file, 'rb');
        if (!$fp) {
            return [];
        }

        $size = @filesize($file);
        if ($size !== false && $size > 50_000_000) {
            @fclose($fp);
            return [];
        }

        $buffer = '';
        $lines  = [];

        @fseek($fp, 0, SEEK_END);
        $pos = @ftell($fp);
        if ($pos === false) {
            @fclose($fp);
            return [];
        }

        $chunkSize = 4096;

        while ($pos > 0 && count($lines) < $maxLines) {
            $readSize = ($pos - $chunkSize) >= 0 ? $chunkSize : $pos;
            $pos -= $readSize;

            @fseek($fp, $pos);
            $chunk = (string)@fread($fp, $readSize);
            if ($chunk === '') {
                break;
            }

            $buffer = $chunk . $buffer;
            $parts  = explode("\n", $buffer);

            $buffer = (string)array_shift($parts);

            for ($i = count($parts) - 1; $i >= 0; $i--) {
                $line = trim((string)$parts[$i]);
                if ($line === '') continue;
                array_unshift($lines, $line);
                if (count($lines) >= $maxLines) {
                    break 2;
                }
            }
        }

        if (trim($buffer) !== '' && count($lines) < $maxLines) {
            array_unshift($lines, trim($buffer));
        }

        @fclose($fp);
        return $lines;
    };

    $chartBots = $timeSlots;

    if ($logPath !== '') {
        $lines = $readLastLinesSafe($logPath, 250);

        $events = [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim((string)$lines[$i]);
            if ($line === '') continue;

            if (!preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(.*)$/', $line, $m)) {
                continue;
            }

            $ts  = strtotime($m[1]) ?: 0;
            $msg = (string)$m[2];

            $botId = 0;
            $botName = '';
            $status = 'info';
            $rest = $msg;

            if (preg_match('/^BOT#(\d+)\s+\(([^)]+)\)\s+(.*)$/', $msg, $bm)) {
                $botId   = (int)$bm[1];
                $botName = (string)$bm[2];
                $rest    = (string)$bm[3];
                $status  = (stripos($rest, 'CRASH') !== false) ? 'error' : 'ok';

                if ($ts > 0) {
                    $slotKey = date($dateFmtPHP, $ts);
                    if (isset($chartBots[$slotKey])) {
                        $chartBots[$slotKey]++;
                    }
                }

                if ($ts >= (TIMESTAMP - 900) && $botId > 0) {
                    $botActiveIds[$botId] = true;
                }

                if ($ts > (int)$botActivity['last_tick_ts']) {
                    $botActivity['last_tick_ts'] = $ts;
                }
            } else {
                $lower = strtolower($msg);
                if (strpos($lower, 'bot') === false && strpos($lower, 'cron') === false && strpos($lower, 'economy') === false) {
                    continue;
                }
                $status = (strpos($lower, 'crash') !== false || strpos($lower, 'error') !== false) ? 'error' : 'info';
            }

            $events[] = [
                'ts'       => $ts,
                'time'     => $m[1],
                'bot_id'   => $botId,
                'bot_name' => $botName,
                'status'   => $status,
                'message'  => $rest,
            ];

            if (count($events) >= 25) {
                break;
            }
        }

        $botActivity['events'] = array_reverse($events);
        $botActivity['active_last_15m'] = count($botActiveIds);

        if ((int)$botActivity['last_tick_ts'] > 0) {
            $botActivity['last_tick_human'] = date('d.m.Y H:i:s', (int)$botActivity['last_tick_ts']);
        }
    }

    /* =========================
       REPORT + TEMPLATE
    ==========================*/

    $report = [
        'players_online'      => ['online' => $onlineCount, 'total' => $totalUsers],
        'registrations'       => ['count' => $newRegs],
        'fleets_sent'         => ['count' => $flyingFleets],
        'flying_fleets'       => ['count' => $flyingFleets],
        'alliances_founded'   => ['count' => $newAllys],
        'alliances_total'     => ['count' => $totalAllys],
        'combats'             => ['count' => $combats],
        'messages'            => ['count' => $messages],
        'planets_total'       => ['count' => $planets],
        'banned_players'      => ['count' => $banned],
        'open_tickets'        => ['count' => $tickets],
        'multiaccount_flags'  => ['flagged_ips' => $flaggedIps, 'flagged_users' => $flaggedUsers],
        'top_players'         => is_array($topPlayers) ? $topPlayers : [],
        'active_players'      => is_array($activePlayers) ? $activePlayers : [],
        'bot_activity'        => $botActivity,
        'last_registrations'  => is_array($lastRegistrations) ? $lastRegistrations : [],
        'fleet_missions'      => [
            'labels' => $fleetMissionLabels,
            'data'   => $fleetMissionData,
            'total'  => $totalFleetsSent,
        ],
        'buildings_most'      => is_array($buildingStats) ? $buildingStats : [],
        'buildings_least'     => is_array($buildingLeast) ? $buildingLeast : [],
        'real_player_count'   => $realPlayerCount,
        'bot_player_count'    => $botPlayerCount,
        'top_online_real'     => is_array($topOnlineReal) ? $topOnlineReal : [],
        'top_bots'            => is_array($topBots) ? $topBots : [],
        'playstyle'           => [
            'miners'     => $miners,
            'aggressors' => $aggressors,
            'neutrals'   => $neutrals,
        ],
    ];

    // ✅ Chart Fix: Labels & alle Datasets als NUMERISCHE Arrays rausgeben
    $labels = array_keys($timeSlots);

    $chartData = [
        'labels'              => array_values($labels),
        'registrations'       => array_values($chartRegs),
        'activity'            => array_values($finalActivity),
        'fleets'              => array_values($finalFleets),
        'combats'             => array_values($chartCombats),
        'bots'                => array_values($chartBots),
        'fleetMissionLabels'  => $fleetMissionLabels,
        'fleetMissionData'    => $fleetMissionData,
    ];

    $template = new template();
    $template->assign_vars([
        'uniName'     => $config->uni_name,
        'period'      => $period,
        'periodLabel' => ucfirst($period),
        'report'      => $report,
        'chartData'   => $chartData,
        'Messages'    => [],
    ]);

    $template->show('ShowIndexPage.twig');
}