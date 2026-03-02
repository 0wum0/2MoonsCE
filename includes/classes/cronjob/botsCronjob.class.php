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

class botsCronjob implements CronjobTask
{
    public function run(): void
    {
        $logFile = ROOT_PATH . 'bot_debug.txt';

        $log = function (string $msg) use ($logFile): void {
            file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
        };

        $log("--- BotsCronjob Start ---");

        @set_time_limit(60);
        @ini_set('memory_limit', '256M');

        $db = Database::get();

        // PlayerUtil sicherstellen
        $path = ROOT_PATH . 'includes/classes/PlayerUtil.class.php';
        if (!file_exists($path)) {
            $log("FEHLER: PlayerUtil nicht gefunden: {$path}");
            return;
        }
        require_once $path;

        $universeID = defined('ROOT_UNI') ? (int)ROOT_UNI : 1;
        $log("Verwende Universe-ID: {$universeID}");

        // Bot-Settings laden
        $settingsTable = DB_PREFIX . "bot_setting";
        $botsTable     = DB_PREFIX . "bots";

        $bot_setting = $db->select("SELECT * FROM {$settingsTable}");
        if (empty($bot_setting)) {
            $log("Keine Bot-Einstellungen gefunden (Tabelle: {$settingsTable}).");
            $log("--- BotsCronjob Ende ---");
            return;
        }

        foreach ($bot_setting as $settingData) {

            $typeId  = (int)($settingData['id'] ?? 0);
            $target  = (int)($settingData['number_of_bots'] ?? 0);

            if ($typeId <= 0 || $target <= 0) {
                $log("Typ ungültig oder Ziel=0 (id={$typeId}, target={$target}) -> skip");
                continue;
            }

            $current = (int)$db->selectSingle(
                "SELECT COUNT(*) as count FROM {$botsTable} WHERE bot_type = :type",
                [':type' => $typeId],
                'count'
            );

            $log("Typ {$typeId}: {$current} / {$target}");

            if ($current >= $target) {
                continue;
            }

            // Damit dein Server nicht stirbt: pro Run nur 1 Bot (kannst du hochdrehen)
            $todo = 1;

            for ($i = 0; $i < $todo; $i++) {

                $bName = 'Bot' . mt_rand(10000, 99999);
                $plainPass = 'bot_' . md5(uniqid((string)mt_rand(), true));
                $bPass = PlayerUtil::cryptPassword($plainPass);
                $bMail = $bName . '@botnet.com';

                $log("Versuche Erstellung von {$bName}...");

                try {
                    // createPlayer liefert: array($userId, $planetId)
                    list($userId, $planetId) = PlayerUtil::createPlayer(
                        $universeID,      // universe
                        $bName,           // userName
                        $bPass,           // password (HASH!)
                        $bMail,           // mail
                        'de',             // language
                        null, null, null, // coords optional -> auto placement
                        $bName,           // planet name
                        0,                // authlevel
                        '127.0.0.1'       // ip
                    );

                    if ((int)$userId <= 0) {
                        $log("FEHLER: createPlayer gab ungültige userId zurück.");
                        continue;
                    }

                    // Bot-Eintrag anlegen
                    $db->insert(
                        "INSERT INTO {$botsTable} SET
                            id_owner = :owner,
                            bot_type = :type,
                            last_login = :time,
                            next_fleet_action = :next",
                        [
                            ':owner' => (int)$userId,
                            ':type'  => $typeId,
                            ':time'  => time(),
                            ':next'  => time() + 300
                        ]
                    );

                    $log("ERFOLG: Bot erstellt. userId={$userId}, planetId={$planetId}, type={$typeId}");

                } catch (Throwable $t) {
                    $log("KRITISCHER ABSTURZ: " . $t->getMessage());
                    $log("In Datei: " . $t->getFile() . " (Zeile " . $t->getLine() . ")");
                }
            }
        }

        // Optional: last_set aktualisieren, falls vorhanden
        try {
            $db->update("UPDATE {$settingsTable} SET last_set = :time", [':time' => time()]);
        } catch (Throwable $t) {
            // Nicht schlimm, falls Spalte nicht existiert
        }

        $log("--- BotsCronjob Ende ---");
    }
}