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
 */10'
 *
 * Every tick:
 *   relic_points += base_points_per_tick + (smr_relic_archive_level * archive_multiplier)
 * for every active user (onlinetime within last 7 days).
 */

require_once ROOT_PATH . 'includes/classes/cronjob/CronjobTask.interface.php';

class RelicsTick implements CronjobTask
{
    public function run(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $db          = Database::get();
        $basePts     = $this->getSetting('base_points_per_tick', 5);
        $archiveMult = $this->getSetting('archive_multiplier', 2);
        $debugMode   = $this->getSetting('debug_mode', 0) === 1;
        $cutoff      = TIMESTAMP - (7 * 86400);

        try {
            $users = $db->select(
                'SELECT id FROM %%USERS%% WHERE onlinetime >= :cutoff AND bana = 0;',
                [':cutoff' => $cutoff]
            );
        } catch (Throwable $e) {
            error_log('[RelicsTick] ERROR fetching users: ' . $e->getMessage());
            return;
        }

        if (empty($users)) {
            return;
        }

        $processed = 0;
        $errors    = 0;

        foreach ($users as $row) {
            $userId = (int) $row['id'];

            // Get max smr_relic_archive level across all planets
            try {
                $archiveLevel = (int) $db->selectSingle(
                    'SELECT COALESCE(MAX(`smr_relic_archive`), 0) AS lvl FROM %%PLANETS%% WHERE id_owner = :uid;',
                    [':uid' => $userId],
                    'lvl'
                );
            } catch (Throwable $e) {
                $archiveLevel = 0;
            }

            $points = $basePts + ($archiveLevel * $archiveMult);
            if ($points <= 0) {
                continue;
            }

            try {
                $db->insert(
                    'INSERT INTO %%RELICS_USER%% (`user_id`, `relic_points`, `doctrine`, `updated_at`)
                     VALUES (:uid, :pts, NULL, :now)
                     ON DUPLICATE KEY UPDATE
                         `relic_points` = `relic_points` + :pts2,
                         `updated_at`   = :now2;',
                    [
                        ':uid'  => $userId,
                        ':pts'  => $points,
                        ':now'  => TIMESTAMP,
                        ':pts2' => $points,
                        ':now2' => TIMESTAMP,
                    ]
                );

                if ($debugMode) {
                    $db->insert(
                        'INSERT INTO %%RELICS_LOG%% (`user_id`, `delta`, `reason`, `created_at`)
                         VALUES (:uid, :delta, :reason, :now);',
                        [
                            ':uid'    => $userId,
                            ':delta'  => $points,
                            ':reason' => 'tick:archive' . $archiveLevel,
                            ':now'    => TIMESTAMP,
                        ]
                    );
                }
                $processed++;
            } catch (Throwable $e) {
                $errors++;
                error_log('[RelicsTick] ERROR for user ' . $userId . ': ' . $e->getMessage());
            }
        }

        if ($debugMode) {
            error_log(sprintf('[RelicsTick] processed=%d errors=%d base=%d mult=%d', $processed, $errors, $basePts, $archiveMult));
        }
    }

    private function isEnabled(): bool
    {
        try {
            $row = Database::get()->selectSingle(
                'SELECT `v` FROM %%RELICS_SETTINGS%% WHERE `k` = :k;',
                [':k' => 'enabled']
            );
            return ($row !== null && isset($row['v'])) ? ((int) $row['v'] === 1) : true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function getSetting(string $key, int $default): int
    {
        try {
            $row = Database::get()->selectSingle(
                'SELECT `v` FROM %%RELICS_SETTINGS%% WHERE `k` = :k;',
                [':k' => $key]
            );
            return ($row !== null && isset($row['v'])) ? (int) $row['v'] : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}
