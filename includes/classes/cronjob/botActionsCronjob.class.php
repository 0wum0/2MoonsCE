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

class botActionsCronjob implements CronjobTask
{
    public function run(): void
    {
        $logFile = ROOT_PATH . 'bot_actions_cron_debug.txt';
        $log = function (string $msg) use ($logFile): void {
            @file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
        };

        $log('--- botActionsCronjob Start (Fair Rotation) ---');

        @set_time_limit(60);
        @ini_set('memory_limit', '256M');

        $db = Database::get();

        // Config laden
        $configFile = ROOT_PATH . 'config/bot_config.json';
        $config = [];
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true) ?: [];
        }

        $botsPerTick = (int)($config['bots_per_tick'] ?? 10);
        
        $log("Config: bots_per_tick={$botsPerTick}");

        // BotEngine + BotActions laden
        $enginePath  = ROOT_PATH . 'includes/classes/bot/BotEngine.class.php';
        $actionsPath = ROOT_PATH . 'includes/classes/bot/BotActions.class.php';
        if (!file_exists($enginePath)) {
            $log("FEHLER: BotEngine nicht gefunden: {$enginePath}");
            $log('--- botActionsCronjob Ende ---');
            return;
        }
        require_once $enginePath;
        if (file_exists($actionsPath)) {
            require_once $actionsPath;
        } else {
            $log("WARNUNG: BotActions nicht gefunden: {$actionsPath} – Economy-Aktionen werden übersprungen");
        }

        $universeID = defined('ROOT_UNI') ? (int)ROOT_UNI : 1;
        $log("Universe-ID: {$universeID}");

        if (!class_exists('FleetFunctions')) {
            $log("FEHLER: FleetFunctions nicht geladen");
            $log('--- botActionsCronjob Ende ---');
            return;
        }

        $botsTable = DB_PREFIX . 'bots';
        $now = time();

        // FAIR-QUEUE: Hol Bots mit ÄLTESTEM next_fleet_action
        // = Bots die am längsten warten kommen zuerst
        $sql = "SELECT * FROM {$botsTable} 
                WHERE next_fleet_action <= :now 
                ORDER BY next_fleet_action ASC 
                LIMIT :limit;";
        
        $dueBots = $db->select($sql, [':now' => $now, ':limit' => $botsPerTick]);

        // FALLBACK: Wenn weniger als X Bots "fällig" sind, hol die nächsten
        if (count($dueBots) < $botsPerTick) {
            $log("Nur " . count($dueBots) . " fällige Bots - hole weitere...");
            
            $sql = "SELECT * FROM {$botsTable} 
                    ORDER BY next_fleet_action ASC 
                    LIMIT :limit;";
            
            $dueBots = $db->select($sql, [':limit' => $botsPerTick]);
        }

        if (empty($dueBots)) {
            $log("Keine Bots verfügbar.");
            $log('--- botActionsCronjob Ende ---');
            return;
        }

        $engine = new BotEngine(ROOT_PATH . 'bot_actions_debug.txt');
        $processed = 0;

        foreach ($dueBots as $botRow) {
            $processed++;
            $botId = (int)($botRow['id'] ?? 0);
            $owner = (int)($botRow['id_owner'] ?? 0);

            $log("Tick botId={$botId} ownerId={$owner}");

            try {
                $engine->runBot($botRow, $universeID);
            } catch (Throwable $t) {
                $log("BOT CRASH botId={$botId}: " . $t->getMessage() . " @ " . $t->getFile() . ":" . $t->getLine());

                // Safety delay bei Crash
                try {
                    $crashDelay = (int)($config['crash_delay'] ?? 1800);
                    $nextAction = $now + $crashDelay;
                    $db->update(
                        "UPDATE {$botsTable} SET next_fleet_action = :next WHERE id = :id",
                        [':next' => $nextAction, ':id' => $botId]
                    );
                } catch (Throwable $t2) {
                    $log("Konnte next_fleet_action nicht setzen");
                }
            }
        }

        $log("Processed={$processed} Bots");
        
        // Stats: Wie viele Bots warten noch?
        $waitingBots = (int)$db->selectSingle(
            "SELECT COUNT(*) as c FROM {$botsTable} WHERE next_fleet_action <= :now",
            [':now' => $now],
            'c'
        );
        
        $log("Still waiting: {$waitingBots} Bots");
        $log('--- botActionsCronjob Ende ---');
    }
}
