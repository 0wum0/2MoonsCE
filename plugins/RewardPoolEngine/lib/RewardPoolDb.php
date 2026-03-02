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
class RewardPoolDb
{
    private static ?RewardPoolDb $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    public function ensureTables(): void
    {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';

        Database::get()->query("
            CREATE TABLE IF NOT EXISTS `{$prefix}reward_pools` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`       VARCHAR(64)  NOT NULL,
                `active`     TINYINT(1)   NOT NULL DEFAULT 1,
                `created_at` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        Database::get()->query("
            CREATE TABLE IF NOT EXISTS `{$prefix}reward_entries` (
                `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `pool_id`        INT UNSIGNED NOT NULL,
                `type`           ENUM('metal','crystal','deut','dm','item','relic','tech','ship','defense') NOT NULL,
                `min_value`      BIGINT       NOT NULL DEFAULT 0,
                `max_value`      BIGINT       NOT NULL DEFAULT 0,
                `weight`         INT UNSIGNED NOT NULL DEFAULT 100,
                `chance_percent` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
                PRIMARY KEY (`id`),
                KEY `idx_pool` (`pool_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    // ── Pool API ──────────────────────────────────────────────────────────────

    /**
     * Load an active pool by name.
     * @return array<string,mixed>|null
     */
    public function getPool(string $name): ?array
    {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';

        $row = Database::get()->selectSingle(
            "SELECT * FROM `{$prefix}reward_pools` WHERE `name` = :name AND `active` = 1 LIMIT 1",
            [':name' => $name]
        );
        return is_array($row) ? $row : null;
    }

    /**
     * Load all entries for a given pool_id.
     * @return array<int, array<string,mixed>>
     */
    public function getEntries(int $poolId): array
    {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';

        return Database::get()->select(
            "SELECT * FROM `{$prefix}reward_entries` WHERE `pool_id` = :pool_id",
            [':pool_id' => $poolId]
        ) ?: [];
    }

    // ── Core reward logic ─────────────────────────────────────────────────────

    /**
     * Draw a random reward from the named pool.
     *
     * Algorithm:
     *  1. Load pool; return [] if not found or inactive.
     *  2. Load all entries.
     *  3. For each entry roll chance_percent. Entries that fail the roll are excluded.
     *  4. From surviving entries, pick one proportional to weight.
     *  5. Randomise value between min_value and max_value.
     *
     * @param array<string,mixed> $contextData  Optional caller context (e.g. player level).
     * @return array{type:string,value:int,meta:array<string,mixed>}|array{}
     */
    public function getRandomReward(string $poolName, array $contextData = []): array
    {
        $pool = $this->getPool($poolName);
        if ($pool === null) {
            return [];
        }

        $entries = $this->getEntries((int)$pool['id']);
        if (empty($entries)) {
            return [];
        }

        // Step 1: chance roll per entry
        $eligible = [];
        foreach ($entries as $entry) {
            $chance = (float)($entry['chance_percent'] ?? 100.0);
            if ($chance >= 100.0 || (mt_rand(0, 9999) / 100.0) < $chance) {
                $eligible[] = $entry;
            }
        }

        if (empty($eligible)) {
            return [];
        }

        // Step 2: weighted random selection
        $totalWeight = 0;
        foreach ($eligible as $entry) {
            $totalWeight += max(1, (int)($entry['weight'] ?? 1));
        }

        $roll   = mt_rand(1, $totalWeight);
        $cursor = 0;
        $chosen = null;

        foreach ($eligible as $entry) {
            $cursor += max(1, (int)($entry['weight'] ?? 1));
            if ($roll <= $cursor) {
                $chosen = $entry;
                break;
            }
        }

        if ($chosen === null) {
            $chosen = $eligible[array_key_last($eligible)];
        }

        // Step 3: value randomisation
        $min   = (int)($chosen['min_value'] ?? 0);
        $max   = (int)($chosen['max_value'] ?? 0);
        $value = ($max > $min) ? mt_rand($min, $max) : $min;

        return [
            'type'  => (string)($chosen['type'] ?? 'metal'),
            'value' => $value,
            'meta'  => [
                'pool_name' => $poolName,
                'entry_id'  => (int)($chosen['id'] ?? 0),
                'context'   => $contextData,
            ],
        ];
    }
}
