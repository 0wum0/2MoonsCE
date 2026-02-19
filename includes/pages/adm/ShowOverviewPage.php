<?php

declare(strict_types=1);

/**
 * ShowOverviewPage.php
 * SmartMoons Admin Dashboard Controller (Overview)
 *
 * Fix:
 * - chartData liefert NUMERISCHE ARRAYS (sonst json_encode => Object => Charts leer)
 * - %%MESSAGES%%: message_type statt type
 * - Bot-Log Parsing: tail lesen (kein file()), ohne globale helper, kein redeclare
 */

if (!allowedTo(str_replace([dirname(__FILE__), '\\', '/', '.php'], '', __FILE__))) {
    exit;
}

function ShowOverviewPage(): void
{
    global $LNG;

    $db     = Database::get();
    $config = Config::get(ROOT_UNI);

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
            $flaggedUsers += (int)($row['c'] ?? 0);
        }
    }

    /* =========================
       TOP / ACTIVE PLAYERS
    ==========================*/

    $topPlayers = $db->select(
        "SELECT u.username, s.total_points as points
         FROM %%STATPOINTS%% s
         LEFT JOIN %%USERS%% u ON u.id = s.id_owner
         WHERE s.stat_type = 1
         ORDER BY s.total_points DESC
         LIMIT 5"
    );

    $activePlayers = $db->select(
        "SELECT username
         FROM %%USERS%%
         WHERE onlinetime >= :time
         ORDER BY onlinetime DESC
         LIMIT 5",
        [':time' => TIMESTAMP - 86400]
    );

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
            $key = date($dateFmtPHP, (int)($row['register_time'] ?? 0));
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
            $key = date($dateFmtPHP, (int)($row['message_time'] ?? 0));
            if (isset($chartCombats[$key]) && (int)($row['message_type'] ?? 0) === 1) {
                $chartCombats[$key]++;
            }
        }
    }

    // Activity + Fleets (Pseudo kombiniert)
    $finalActivity = [];
    $finalFleets   = [];

    foreach ($timeSlots as $key => $val) {
        $base = (int)($chartRegs[$key] ?? 0) + (int)($chartCombats[$key] ?? 0);
        $finalActivity[] = $base + max(1, (int)($onlineCount / 5));
        $finalFleets[]   = max(0, $base * 2 + (int)($onlineCount / 3));
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
    ];

    // ✅ Chart Fix: Labels & alle Datasets als NUMERISCHE Arrays rausgeben
    $labels = array_keys($timeSlots);

    $chartData = [
        'labels'        => array_values($labels),
        'registrations' => array_values($chartRegs),
        'activity'      => array_values($finalActivity),
        'fleets'        => array_values($finalFleets),
        'combats'       => array_values($chartCombats),
        'bots'          => array_values($chartBots),
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