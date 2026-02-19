<?php

declare(strict_types=1);

/**
 * Bot Online Status Manager
 * Simuliert realistische Online-Aktivität für alle Bots
 * 
 * Features:
 * - Rotierende Online-Bots (nicht immer die gleichen)
 * - Tageszeit-basierte Fluktuation
 * - Alle Bots kommen mal dran
 */

require_once 'includes/classes/cronjob/CronjobTask.interface.php';

class botOnlineStatusCronjob implements CronjobTask
{
    public function run(): void
    {
        $db = Database::get();
        
        // Config laden
        $configFile = ROOT_PATH . 'config/bot_config.json';
        $config = [];
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true) ?: [];
        }

        // Tageszeit ermitteln
        $hour = (int)date('G');
        
        // Online-Anzahl nach Tageszeit
        $onlineCount = $this->getOnlineCountForHour($hour, $config);
        
        // Alle Bots holen
        $allBots = $db->select("SELECT id, id_owner FROM " . DB_PREFIX . "bots");
        $totalBots = count($allBots);
        
        if ($totalBots == 0) return;
        
        // Zufällig rotieren welche Bots online sind
        shuffle($allBots);
        
        $now = time();
        $onlineBots = array_slice($allBots, 0, min($onlineCount, $totalBots));
        $offlineBots = array_slice($allBots, $onlineCount);
        
        // Online-Bots auf "online" setzen
        foreach ($onlineBots as $bot) {
            $ownerId = (int)$bot['id_owner'];
            
            // Onlinetime auf JETZT setzen (innerhalb letzter 5 Min = grüner Punkt)
            $randomOffset = rand(-120, 0); // -2 bis 0 Minuten
            $onlineTime = $now + $randomOffset;
            
            $db->update(
                "UPDATE %%USERS%% SET onlinetime = :time WHERE id = :id",
                [':time' => $onlineTime, ':id' => $ownerId]
            );
        }
        
        // Offline-Bots auf "offline" setzen (onlinetime älter als 15 Min)
        foreach ($offlineBots as $bot) {
            $ownerId = (int)$bot['id_owner'];
            
            // Zufälliges Offline-Datum (15-60 Min her)
            $offlineTime = $now - rand(900, 3600);
            
            $db->update(
                "UPDATE %%USERS%% SET onlinetime = :time WHERE id = :id",
                [':time' => $offlineTime, ':id' => $ownerId]
            );
        }
    }
    
    /**
     * Berechnet wie viele Bots zu dieser Stunde online sein sollen
     */
    private function getOnlineCountForHour(int $hour, array $config): int
    {
        // Defaults aus Config oder Fallback
        $minOnline = (int)($config['min_bots_online'] ?? 15);
        $maxOnline = (int)($config['max_bots_online'] ?? 150);
        
        // Tageszeit-basierte Aktivität
        $activity = 0.5; // Default 50%
        
        if ($hour >= 0 && $hour < 6) {
            // Nacht (00:00-06:00): 10-30% online
            $activity = 0.1 + (rand(0, 20) / 100);
        } elseif ($hour >= 6 && $hour < 12) {
            // Morgen (06:00-12:00): 20-50% online
            $activity = 0.2 + (rand(0, 30) / 100);
        } elseif ($hour >= 12 && $hour < 18) {
            // Nachmittag (12:00-18:00): 40-70% online
            $activity = 0.4 + (rand(0, 30) / 100);
        } elseif ($hour >= 18 && $hour < 24) {
            // Prime-Time (18:00-00:00): 60-95% online
            $activity = 0.6 + (rand(0, 35) / 100);
        }
        
        // Berechne Anzahl mit Fluktuation
        $range = $maxOnline - $minOnline;
        $count = $minOnline + (int)($range * $activity);
        
        // Zufällige Variation +/- 10%
        $variance = (int)($count * (rand(-10, 10) / 100));
        $count += $variance;
        
        return max($minOnline, min($maxOnline, $count));
    }
}
