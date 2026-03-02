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

class BotPersonality
{
    private static $personalities = null;
    
    /**
     * Lade Persönlichkeits-Daten
     */
    public static function load(): void
    {
        if (self::$personalities !== null) return;
        
        $db = Database::get();
        $rows = $db->select("SELECT * FROM " . DB_PREFIX . "bot_personalities");
        
        self::$personalities = [];
        foreach ($rows as $row) {
            self::$personalities[$row['name']] = $row;
        }
    }
    
    /**
     * Hole Persönlichkeit eines Bots
     */
    public static function get(string $personality): ?array
    {
        self::load();
        return self::$personalities[$personality] ?? self::$personalities['balanced'] ?? null;
    }
    
    /**
     * Soll Bot bauen?
     */
    public static function shouldBuild(array $personality, string $type): bool
    {
        $priority = 0.5;
        
        switch ($type) {
            case 'mine':
                $priority = (float)($personality['priority_mines'] ?? 0.5);
                break;
            case 'energy':
                $priority = (float)($personality['priority_energy'] ?? 0.3);
                break;
            case 'storage':
                $priority = (float)($personality['priority_storage'] ?? 0.2);
                break;
            case 'research':
                $priority = (float)($personality['priority_research'] ?? 0.3);
                break;
            case 'fleet':
                $priority = (float)($personality['priority_fleet'] ?? 0.3);
                break;
            case 'defense':
                $priority = (float)($personality['priority_defense'] ?? 0.2);
                break;
        }
        
        // Je höher die Priorität, desto wahrscheinlicher wird gebaut
        return (mt_rand(1, 100) / 100) < $priority;
    }
    
    /**
     * Soll Bot Raid machen?
     */
    public static function shouldRaid(array $personality): bool
    {
        if (empty($personality['allow_raids'])) return false;
        
        $aggression = (float)($personality['aggression'] ?? 0.5);
        return (mt_rand(1, 100) / 100) < $aggression;
    }
    
    /**
     * Soll Bot Expedition machen?
     */
    public static function shouldExpedition(array $personality): bool
    {
        return !empty($personality['allow_expeditions']);
    }
    
    /**
     * Wie viele Ressourcen soll Bot ausgeben? (0.0 - 1.0)
     */
    public static function getSpendingRate(array $personality): float
    {
        if (!empty($personality['save_resources'])) {
            return 0.3; // Sparsam
        }
        
        $aggression = (float)($personality['aggression'] ?? 0.5);
        return 0.5 + ($aggression * 0.4); // 0.5 - 0.9
    }
}
