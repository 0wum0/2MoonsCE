<?php

declare(strict_types=1);

/**
 * RewardPoolDb – Database helper for the RewardPoolEngine plugin.
 *
 * Manages:
 *  - uniX_reward_pools   : named pools with active flag
 *  - uniX_reward_entries : weighted reward entries per pool
 *
 * All methods are wrapped in try/catch. A DB error never crashes core.
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
        $name   = Database::get()->sql_escape($name);

        $result = Database::get()->query(
            "SELECT * FROM `{$prefix}reward_pools` WHERE `name` = '{$name}' AND `active` = 1 LIMIT 1"
        );

        if (!$result) {
            return null;
        }

        $row = Database::get()->fetch_array($result);
        return is_array($row) ? $row : null;
    }

    /**
     * Load all entries for a given pool_id.
     * @return array<int, array<string,mixed>>
     */
    public function getEntries(int $poolId): array
    {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';

        $result = Database::get()->query(
            "SELECT * FROM `{$prefix}reward_entries` WHERE `pool_id` = {$poolId}"
        );

        if (!$result) {
            return [];
        }

        $entries = [];
        while ($row = Database::get()->fetch_array($result)) {
            $entries[] = $row;
        }
        return $entries;
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
