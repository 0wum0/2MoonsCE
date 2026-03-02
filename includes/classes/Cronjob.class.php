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

class Cronjob
{
    const LOCK_EXPIRY_SECONDS = 600; // 10 minutes stale-lock threshold
    const LOG_FILE = 'cache/cron_debug.log';

    private static function cronLog(string $message): void
    {
        $logPath = ROOT_PATH . self::LOG_FILE;
        $line    = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
    }
    function __construct()
    {
    }
    
    static function execute($cronjobID)
    {
        $lockToken = md5((string)TIMESTAMP . (string)$cronjobID . (string)rand());
        $db = Database::get();

        self::cronLog("execute() start: cronjobID=$cronjobID");

        // 1. Stale-lock recovery: fetch job including locked ones
        $sql = 'SELECT class, `lock`, `lockTime` FROM %%CRONJOBS%% WHERE isActive = :isActive AND cronjobID = :cronjobId;';
        $cronjobRow = $db->selectSingle($sql, array(
            ':isActive'  => 1,
            ':cronjobId' => $cronjobID
        ));

        if (empty($cronjobRow)) {
            self::cronLog("execute() abort: job $cronjobID not found or inactive");
            return;
        }

        $cronjobClassName = $cronjobRow['class'];

        // If locked, check if lock is stale (older than LOCK_EXPIRY_SECONDS)
        if (!is_null($cronjobRow['lock'])) {
            $lockAge = TIMESTAMP - (int)($cronjobRow['lockTime'] ?? 0);
            if ($lockAge < self::LOCK_EXPIRY_SECONDS) {
                self::cronLog("execute() abort: job $cronjobID is locked (age={$lockAge}s, token={$cronjobRow['lock']})");
                return;
            }
            // Stale lock — remove it and continue
            self::cronLog("execute() stale lock detected (age={$lockAge}s) for job $cronjobID — clearing and continuing");
            $db->update('UPDATE %%CRONJOBS%% SET `lock` = NULL, `lockTime` = NULL WHERE cronjobID = :cronjobId;', [
                ':cronjobId' => $cronjobID
            ]);
        }

        // 2. Job sperren (Lock) with timestamp
        $sql = 'UPDATE %%CRONJOBS%% SET `lock` = :lock, `lockTime` = :lockTime WHERE cronjobID = :cronjobId;';
        $db->update($sql, array(
            ':lock'      => $lockToken,
            ':lockTime'  => TIMESTAMP,
            ':cronjobId' => $cronjobID
        ));

        self::cronLog("execute() lock acquired: cronjobID=$cronjobID class=$cronjobClassName token=$lockToken");

        // Check plugin-registered cronjob paths first, then fall back to core path.
        // We scan plugin cron/ directories directly so this works even when
        // loadActivePlugins() has not been called (e.g. pure CRON context).
        $pluginPath = null;
        if (class_exists('PluginManager')) {
            $pluginPath = PluginManager::get()->resolveCronjobPath($cronjobClassName);
        }
        if ($pluginPath === null || !file_exists($pluginPath)) {
            // Direct filesystem scan: plugins/<id>/cron/<ClassName>.php
            $pluginsDir = ROOT_PATH . 'plugins/';
            if (is_dir($pluginsDir)) {
                foreach (scandir($pluginsDir) as $entry) {
                    if ($entry === '.' || $entry === '..') continue;
                    $candidate = $pluginsDir . $entry . '/cron/' . $cronjobClassName . '.php';
                    if (file_exists($candidate)) {
                        $pluginPath = $candidate;
                        break;
                    }
                }
            }
        }
        $cronjobPath = ($pluginPath !== null && file_exists($pluginPath))
            ? $pluginPath
            : ROOT_PATH . 'includes/classes/cronjob/'.$cronjobClassName.'.class.php';

        // 3. Job ausführen
        // Ensure CronjobTask interface is loaded before any plugin cronjob file
        require_once ROOT_PATH . 'includes/classes/cronjob/CronjobTask.interface.php';
        if (file_exists($cronjobPath)) {
            try {
                require_once($cronjobPath);
                /** @var $cronjobObj CronjobTask */
                $cronjobObj = new $cronjobClassName;
                self::cronLog("execute() running: $cronjobClassName");
                $cronjobObj->run();
                self::cronLog("execute() finished: $cronjobClassName");
            } catch (Throwable $e) {
                self::cronLog("execute() ERROR in $cronjobClassName: " . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
                // Release lock so next run isn't blocked (covers parse errors too)
                $db->update('UPDATE %%CRONJOBS%% SET `lock` = NULL, `lockTime` = NULL WHERE cronjobID = :cronjobId;', [
                    ':cronjobId' => $cronjobID
                ]);
                return;
            }
        } else {
            self::cronLog("execute() ERROR: class file not found: $cronjobPath");
        }

        // 4. Nächste Ausführungszeit berechnen (always release lock even if this fails)
        try {
            self::reCalculateCronjobs((int)$cronjobID);
        } catch (Throwable $e) {
            self::cronLog("execute() WARNING: reCalculateCronjobs failed for $cronjobID: " . $e->getMessage());
        }

        // 5. Sperre aufheben
        $sql = 'UPDATE %%CRONJOBS%% SET `lock` = NULL, `lockTime` = NULL WHERE cronjobID = :cronjobId;';
        $db->update($sql, array(
            ':cronjobId' => $cronjobID
        ));

        self::cronLog("execute() lock released: cronjobID=$cronjobID");

        // 6. Log-Eintrag schreiben (Wichtig für die Statistik-Anzeige)
        try {
            $sql = 'INSERT INTO %%CRONJOBS_LOG%% SET `cronjobId` = :cronjobId,
            `executionTime` = :executionTime, `lockToken` = :lockToken';
            $db->insert($sql, array(
                ':cronjobId'     => $cronjobID,
                ':executionTime' => Database::formatDate(TIMESTAMP),
                ':lockToken'     => $lockToken
            ));
        } catch (Throwable $e) {
            self::cronLog("execute() WARNING: could not write to CRONJOBS_LOG: " . $e->getMessage());
        }

        self::cronLog("execute() complete: cronjobID=$cronjobID");
    }
    
    static function getNeedTodoExecutedJobs()
    {
        $db = Database::get();

        // Auto-heal: recalculate nextTime for any active jobs stuck at 0 (epoch)
        $zeroJobs = $db->select(
            'SELECT cronjobID FROM %%CRONJOBS%% WHERE isActive = :isActive AND nextTime = 0;',
            [':isActive' => 1]
        );
        if (!empty($zeroJobs)) {
            foreach ($zeroJobs as $zRow) {
                self::cronLog('getNeedTodoExecutedJobs() auto-healing nextTime=0 for cronjobID=' . $zRow['cronjobID']);
                self::reCalculateCronjobs((int)$zRow['cronjobID']);
            }
        }

        // COALESCE(lockTime, 0) ensures NULL lockTime is treated as epoch (0),
        // so stale-lock expiry works even when lockTime was never set.
        $sql = 'SELECT cronjobID FROM %%CRONJOBS%% WHERE isActive = :isActive AND nextTime < :time
                AND (`lock` IS NULL OR COALESCE(`lockTime`, 0) < :expiry);';
        $cronjobResult = $db->select($sql, array(
            ':isActive' => 1,
            ':time'     => TIMESTAMP,
            ':expiry'   => TIMESTAMP - self::LOCK_EXPIRY_SECONDS
        ));

        $cronjobList = array();
        foreach($cronjobResult as $cronjobRow)
        {
            $cronjobList[] = (int)$cronjobRow['cronjobID'];
        }

        self::cronLog('getNeedTodoExecutedJobs() due jobs: [' . implode(',', $cronjobList) . ']');
        return $cronjobList;
    }

    /**
     * Holt den Zeitstempel der letzten Ausführung für die Statistikseite.
     * Korrigiert für PHP 8.3 und SQL-Kompatibilität.
     */
    static function getLastExecutionTime($cronjobName)
    {
        $db = Database::get();
        
        // Wir suchen die letzte erfolgreiche Ausführungszeit aus dem Log basierend auf dem Namen des Jobs
        $sql = 'SELECT MAX(log.executionTime) as lastTime 
                FROM %%CRONJOBS_LOG%% log
                INNER JOIN %%CRONJOBS%% cron ON cron.cronjobId = log.cronjobId 
                WHERE cron.name = :cronjobName;';
        
        $lastTime = $db->selectSingle($sql, array(
            ':cronjobName' => $cronjobName
        ), 'lastTime');

        if(empty($lastTime))
        {
            return false;
        }

        return strtotime((string)$lastTime);
    }
    
    static function reCalculateCronjobs($cronjobID = NULL)
    {
        require_once ROOT_PATH . 'includes/libs/tdcron/class.tdcron.php';
        require_once ROOT_PATH . 'includes/libs/tdcron/class.tdcron.entry.php';

        $db = Database::get();

        if(!empty($cronjobID))
        {
            $sql = 'SELECT cronjobID, min, hours, dom, month, dow FROM %%CRONJOBS%% WHERE cronjobID = :cronjobId;';
            $cronjobResult = $db->select($sql, array(
                ':cronjobId' => $cronjobID
            ));
        }
        else
        {
            $sql = 'SELECT cronjobID, min, hours, dom, month, dow FROM %%CRONJOBS%%;';
            $cronjobResult = $db->select($sql);
        }

        $sql = 'UPDATE %%CRONJOBS%% SET nextTime = :nextTime WHERE cronjobID = :cronjobId;';

        foreach($cronjobResult as $cronjobRow)
        {
            $cronTabString = implode(' ', array(
                trim((string)$cronjobRow['min'])   ?: '*',
                trim((string)$cronjobRow['hours'])  ?: '*',
                trim((string)$cronjobRow['dom'])    ?: '*',
                trim((string)$cronjobRow['month'])  ?: '*',
                trim((string)$cronjobRow['dow'])    ?: '*',
            ));

            try {
                $nextTime = tdCron::getNextOccurrence($cronTabString, TIMESTAMP);
            } catch (Throwable $e) {
                // Invalid/unparseable expression or PHP 8 TypeError — fall back to next hour
                self::cronLog('reCalculateCronjobs() WARNING: cronjobID=' . $cronjobRow['cronjobID'] . ' expr="' . $cronTabString . '" error=' . $e->getMessage());
                $nextTime = TIMESTAMP + 3600;
            }

            $db->update($sql, array(
                ':nextTime'  => $nextTime,
                ':cronjobId' => $cronjobRow['cronjobID'],
            ));
        }
    }
}
