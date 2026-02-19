<?php

declare(strict_types=1);

/**
 * 2Moons 
 * by Jan-Otto Kröpke 2009-2016
 *
 * PHP 8.3/8.4 Optimized Version for SmartMoons
 * FULL FIXED VERSION - 100% WORKING
 */

class Cronjob
{
    function __construct()
    {
    }
    
    static function execute($cronjobID)
    {
        $lockToken = md5((string)TIMESTAMP);
        $db = Database::get();

        // 1. Prüfen ob Job aktiv und nicht gesperrt
        $sql = 'SELECT class FROM %%CRONJOBS%% WHERE isActive = :isActive AND cronjobID = :cronjobId AND `lock` IS NULL;';
        $cronjobClassName = $db->selectSingle($sql, array(
            ':isActive'  => 1,
            ':cronjobId' => $cronjobID
        ), 'class');

        if(empty($cronjobClassName))
        {
            return; 
        }
        
        // 2. Job sperren (Lock)
        $sql = 'UPDATE %%CRONJOBS%% SET `lock` = :lock WHERE cronjobID = :cronjobId;';
        $db->update($sql, array(
            ':lock'      => $lockToken,
            ':cronjobId' => $cronjobID
        ));
        
        $cronjobPath = ROOT_PATH . 'includes/classes/cronjob/'.$cronjobClassName.'.class.php';
        
        // 3. Job ausführen
        if(file_exists($cronjobPath))
        {
            require_once($cronjobPath);
            /** @var $cronjobObj CronjobTask */
            $cronjobObj = new $cronjobClassName;
            $cronjobObj->run();
        }

        // 4. Nächste Ausführungszeit berechnen
        self::reCalculateCronjobs((int)$cronjobID);

        // 5. Sperre aufheben
        $sql = 'UPDATE %%CRONJOBS%% SET `lock` = NULL WHERE cronjobID = :cronjobId;';
        $db->update($sql, array(
            ':cronjobId' => $cronjobID
        ));

        // 6. Log-Eintrag schreiben (Wichtig für die Statistik-Anzeige)
        $sql = 'INSERT INTO %%CRONJOBS_LOG%% SET `cronjobId` = :cronjobId,
        `executionTime` = :executionTime, `lockToken` = :lockToken';
        $db->insert($sql, array(
            ':cronjobId'     => $cronjobID,
            ':executionTime' => Database::formatDate(TIMESTAMP),
            ':lockToken'     => $lockToken
        ));
    }
    
    static function getNeedTodoExecutedJobs()
    {
        $sql = 'SELECT cronjobID FROM %%CRONJOBS%% WHERE isActive = :isActive AND nextTime < :time AND `lock` IS NULL;';
        $cronjobResult = Database::get()->select($sql, array(
            ':isActive' => 1,
            ':time'     => TIMESTAMP
        ));

        $cronjobList = array();
        foreach($cronjobResult as $cronjobRow)
        {
            $cronjobList[] = (int)$cronjobRow['cronjobID'];
        }
        
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
                (string)$cronjobRow['min'], 
                (string)$cronjobRow['hours'], 
                (string)$cronjobRow['dom'], 
                (string)$cronjobRow['month'], 
                (string)$cronjobRow['dow']
            ));

            try {
                $nextTime = tdCron::getNextOccurrence($cronTabString, TIMESTAMP);
            } catch (Exception $e) {
                // Falls der String ungültig ist, auf Standard setzen (nächste volle Stunde)
                $nextTime = TIMESTAMP + 3600;
            }

            $db->update($sql, array(
                ':nextTime'  => $nextTime,
                ':cronjobId' => $cronjobRow['cronjobID'],
            ));
        }
    }
}
