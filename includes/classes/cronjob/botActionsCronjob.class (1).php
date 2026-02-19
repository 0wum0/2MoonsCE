<?php

declare(strict_types=1);

/**
 * SmartMoons Bot Actions Cronjob (In-Game Bot AI)
 * PHP 8.3/8.4
 *
 * Runs "player-like" actions for bots whose next_fleet_action <= time()
 * using BotEngine and FleetFunctions::sendFleet flow.
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

        $log('--- botActionsCronjob Start ---');

        @set_time_limit(60);
        @ini_set('memory_limit', '256M');

        $db = Database::get();

        // Load BotEngine
        $enginePath = ROOT_PATH . 'includes/classes/bot/BotEngine.class.php';
        if (!file_exists($enginePath)) {
            $log("FEHLER: BotEngine nicht gefunden: {$enginePath}");
            $log('--- botActionsCronjob Ende ---');
            return;
        }
        require_once $enginePath;

        // Universe id
        $universeID = defined('ROOT_UNI') ? (int)ROOT_UNI : (defined('UNIVERSE') ? (int)UNIVERSE : 1);
        $log("Universe-ID: {$universeID}");

        // IMPORTANT: FleetFunctions must exist for actual fleet sending
        if (!class_exists('FleetFunctions')) {
            $log("FEHLER: FleetFunctions Klasse nicht geladen (Bots können keine Flotten senden).");
            $log('--- botActionsCronjob Ende ---');
            return;
        }

        $botsTable = DB_PREFIX . 'bots';

        // Fetch due bots
        $sql = "SELECT * FROM {$botsTable} WHERE next_fleet_action <= :now ORDER BY next_fleet_action ASC LIMIT 25;";
        $dueBots = $db->select($sql, [':now' => time()]);

        if (empty($dueBots)) {
            $log("Keine fälligen Bots.");
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

                // Safety: push next action forward so we don't crash-loop every minute
                try {
                    $db->update(
                        "UPDATE {$botsTable} SET next_fleet_action = :n WHERE id = :id;",
                        [':n' => time() + 1800, ':id' => $botId]
                    );
                } catch (Throwable $t2) {
                    $log("Could not reschedule botId={$botId}: " . $t2->getMessage());
                }
            }
        }

        $log("Processed={$processed}");
        $log('--- botActionsCronjob Ende ---');
    }
}