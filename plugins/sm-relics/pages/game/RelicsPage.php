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

require_once ROOT_PATH . 'includes/pages/game/AbstractGamePage.class.php';

class RelicsPage extends AbstractGamePage
{
    private const DOCTRINE_COOLDOWN = 86400;

    private const DOCTRINES = ['industry', 'research', 'economy', 'war'];

    private const DOCTRINE_REQUIREMENTS = [
        'industry' => ['building' => 912, 'level' => 1],
        'research' => ['building' => 912, 'level' => 3],
        'economy'  => ['building' => 914, 'level' => 1],
        'war'      => ['building' => 913, 'level' => 1],
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function show(): void
    {
        global $USER, $PLANET, $LNG, $resource;

        $db     = Database::get();
        $userId = (int) $USER['id'];

        // ── Handle POST: doctrine selection ──────────────────────────────────
        $message = '';
        $error   = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newDoctrine = HTTP::_GP('doctrine', '');
            if (in_array($newDoctrine, self::DOCTRINES, true)) {
                [$ok, $msg] = $this->trySetDoctrine($userId, $newDoctrine, $db);
                if ($ok) {
                    $message = PluginManager::lang('sm-relics', 'doctrine_set_ok');
                } else {
                    $error = $msg;
                }
            }
        }

        // ── Load user data ────────────────────────────────────────────────────
        $userData = $this->loadUserData($userId, $db);

        // ── Building levels on current planet ─────────────────────────────────
        $buildingLevels = $this->getBuildingLevels($PLANET, $resource);

        // ── Max levels across all planets (for doctrine unlock check) ─────────
        $maxLevels = $this->getMaxBuildingLevels($userId, $db);

        // ── Doctrine availability ─────────────────────────────────────────────
        $doctrineAvail = [];
        foreach (self::DOCTRINE_REQUIREMENTS as $doc => $req) {
            $doctrineAvail[$doc] = ($maxLevels[$req['building']] ?? 0) >= $req['level'];
        }

        // ── Cooldown remaining ────────────────────────────────────────────────
        $cooldownLeft = 0;
        if (!empty($userData['doctrine_changed_at'])) {
            $elapsed      = TIMESTAMP - (int) $userData['doctrine_changed_at'];
            $cooldownLeft = max(0, self::DOCTRINE_COOLDOWN - $elapsed);
        }

        // ── Points log ────────────────────────────────────────────────────────
        $pointsLog = [];
        try {
            $pointsLog = $db->select(
                'SELECT `delta`, `reason`, `created_at`
                 FROM %%RELICS_LOG%%
                 WHERE `user_id` = :uid
                 ORDER BY `created_at` DESC
                 LIMIT 20;',
                [':uid' => $userId]
            ) ?: [];
        } catch (Throwable $e) {
            // table may not exist yet
        }

        // ── Settings ──────────────────────────────────────────────────────────
        $settings = $this->loadSettings($db);

        // ── Lang strings ──────────────────────────────────────────────────────
        $lngRelics = $this->getLangStrings();

        $this->assign([
            'relic_points'    => (int) ($userData['relic_points'] ?? 0),
            'active_doctrine' => $userData['doctrine'] ?? null,
            'doctrine_avail'  => $doctrineAvail,
            'cooldown_left'   => $cooldownLeft,
            'building_levels' => $buildingLevels,
            'max_levels'      => $maxLevels,
            'points_log'      => $pointsLog,
            'settings'        => $settings,
            'message'         => $message,
            'error'           => $error,
            'LNG_relics'      => $lngRelics,
        ]);

        $this->display('@sm-relics/game/relics.twig');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * @return array{bool, string}
     */
    private function trySetDoctrine(int $userId, string $doctrine, object $db): array
    {
        // Check cooldown
        $userData = $this->loadUserData($userId, $db);
        if (!empty($userData['doctrine_changed_at'])) {
            $elapsed = TIMESTAMP - (int) $userData['doctrine_changed_at'];
            if ($elapsed < self::DOCTRINE_COOLDOWN) {
                $remaining = self::DOCTRINE_COOLDOWN - $elapsed;
                return [false, sprintf(
                    PluginManager::lang('sm-relics', 'doctrine_cooldown'),
                    gmdate('H:i:s', $remaining)
                )];
            }
        }

        // Check building requirement
        $req      = self::DOCTRINE_REQUIREMENTS[$doctrine];
        $maxLevels = $this->getMaxBuildingLevels($userId, $db);
        if (($maxLevels[$req['building']] ?? 0) < $req['level']) {
            return [false, PluginManager::lang('sm-relics', 'doctrine_req_not_met')];
        }

        // Set doctrine
        try {
            $db->insert(
                'INSERT INTO %%RELICS_USER%%
                     (`user_id`, `relic_points`, `doctrine`, `doctrine_changed_at`, `updated_at`)
                 VALUES (:uid, 0, :doc, :now, :now2)
                 ON DUPLICATE KEY UPDATE
                     `doctrine`            = :doc2,
                     `doctrine_changed_at` = :now3,
                     `updated_at`          = :now4;',
                [
                    ':uid'  => $userId,
                    ':doc'  => $doctrine,
                    ':now'  => TIMESTAMP,
                    ':now2' => TIMESTAMP,
                    ':doc2' => $doctrine,
                    ':now3' => TIMESTAMP,
                    ':now4' => TIMESTAMP,
                ]
            );
            return [true, ''];
        } catch (Throwable $e) {
            return [false, $e->getMessage()];
        }
    }

    private function loadUserData(int $userId, object $db): array
    {
        try {
            $row = $db->selectSingle(
                'SELECT * FROM %%RELICS_USER%% WHERE `user_id` = :uid;',
                [':uid' => $userId]
            );
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function loadSettings(object $db): array
    {
        $defaults = [
            'base_points_per_tick'    => '5',
            'archive_multiplier'      => '2',
            'doctrine_build_bonus'    => '10',
            'doctrine_research_bonus' => '10',
            'doctrine_prod_bonus'     => '5',
            'doctrine_combat_bonus'   => '5',
        ];
        try {
            $rows = $db->select('SELECT `k`, `v` FROM %%RELICS_SETTINGS%%;');
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $defaults[$row['k']] = $row['v'];
                }
            }
        } catch (Throwable $e) {
            // use defaults
        }
        return $defaults;
    }

    /**
     * @param array<int,string> $resource
     * @return array<int,int>
     */
    private function getBuildingLevels(array $PLANET, array $resource): array
    {
        $map = [
            910 => 'smr_relic_archive',
            911 => 'smr_quantum_reactor',
            912 => 'smr_spacetime_lab',
            913 => 'smr_military_academy',
            914 => 'smr_economic_center',
        ];
        $levels = [];
        foreach ($map as $id => $col) {
            $key          = $resource[$id] ?? $col;
            $levels[$id]  = (int) ($PLANET[$key] ?? $PLANET[$col] ?? 0);
        }
        return $levels;
    }

    /**
     * @return array<int,int>
     */
    private function getMaxBuildingLevels(int $userId, object $db): array
    {
        $cols = [
            910 => 'smr_relic_archive',
            911 => 'smr_quantum_reactor',
            912 => 'smr_spacetime_lab',
            913 => 'smr_military_academy',
            914 => 'smr_economic_center',
        ];
        $maxLevels = array_fill_keys(array_keys($cols), 0);
        try {
            $planets = $db->select(
                'SELECT `smr_relic_archive`, `smr_quantum_reactor`, `smr_spacetime_lab`,
                        `smr_military_academy`, `smr_economic_center`
                 FROM %%PLANETS%% WHERE `id_owner` = :uid;',
                [':uid' => $userId]
            );
            if (is_array($planets)) {
                foreach ($planets as $planet) {
                    foreach ($cols as $id => $col) {
                        $maxLevels[$id] = max($maxLevels[$id], (int) ($planet[$col] ?? 0));
                    }
                }
            }
        } catch (Throwable $e) {
            // columns may not exist yet
        }
        return $maxLevels;
    }

    /**
     * @return array<string,string>
     */
    private function getLangStrings(): array
    {
        $keys = [
            'page_title', 'tab_relics', 'tab_doctrine', 'tab_points',
            'relic_points', 'relic_points_desc',
            'building_910', 'building_911', 'building_912', 'building_913', 'building_914',
            'building_level', 'building_effect',
            'effect_910', 'effect_911', 'effect_912', 'effect_913', 'effect_914',
            'doctrine_title', 'doctrine_active', 'doctrine_none',
            'doctrine_industry', 'doctrine_research', 'doctrine_economy', 'doctrine_war',
            'doctrine_industry_desc', 'doctrine_research_desc',
            'doctrine_economy_desc', 'doctrine_war_desc',
            'doctrine_select', 'doctrine_locked', 'doctrine_cooldown',
            'doctrine_set_ok', 'doctrine_req_not_met',
            'points_history', 'points_log_empty',
            'log_delta', 'log_reason', 'log_time',
            'your_points', 'nav_label',
        ];
        $result = [];
        foreach ($keys as $k) {
            $result[$k] = PluginManager::lang('sm-relics', $k);
        }
        return $result;
    }
}
