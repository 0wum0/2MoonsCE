<?php

/**
 * Bot Personality Handler
 * Passt Bot-Verhalten basierend auf Persönlichkeit an
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
