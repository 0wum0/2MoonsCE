<?php

declare(strict_types=1);

/**
 * smRelicsShowAdminPage – sm-relics admin page controller
 *
 * Registered via: PluginManager::get()->registerAdminRoute(...)
 * Route: admin.php?page=relicsAdmin
 *
 * Template: @sm-relics/admin/relics_admin.twig
 */

function smRelicsShowAdminPage(): void
{
    global $USER;

    if ($USER['authlevel'] < AUTH_ADM) {
        exit;
    }

    $db      = Database::get();
    $action  = HTTP::_GP('action', '');
    $message = '';
    $error   = '';

    // ── Save settings ─────────────────────────────────────────────────────────
    if ($action === 'saveSettings') {
        $fields = [
            'base_points_per_tick', 'archive_multiplier',
            'doctrine_build_bonus', 'doctrine_research_bonus',
            'doctrine_prod_bonus',  'doctrine_combat_bonus',
            'debug_mode', 'enabled',
        ];
        try {
            foreach ($fields as $field) {
                $val = HTTP::_GP($field, '');
                if ($val !== '') {
                    $db->insert(
                        'INSERT INTO %%RELICS_SETTINGS%% (`k`, `v`) VALUES (:k, :v)
                         ON DUPLICATE KEY UPDATE `v` = :v2;',
                        [':k' => $field, ':v' => $val, ':v2' => $val]
                    );
                }
            }
            $message = 'Einstellungen gespeichert.';
        } catch (Throwable $e) {
            $error = 'Fehler: ' . htmlspecialchars($e->getMessage());
        }
    }

    // ── Give relic points ─────────────────────────────────────────────────────
    if ($action === 'givePoints') {
        $targetUser = HTTP::_GP('target_user', '');
        $points     = (int) HTTP::_GP('points', 0);
        $reason     = HTTP::_GP('reason', 'admin_grant');

        if ($targetUser !== '' && $points > 0) {
            try {
                $userRow = $db->selectSingle(
                    'SELECT `id` FROM %%USERS%% WHERE `username` = :name;',
                    [':name' => $targetUser]
                );
                if ($userRow !== null) {
                    $uid = (int) $userRow['id'];
                    $db->insert(
                        'INSERT INTO %%RELICS_USER%% (`user_id`, `relic_points`, `doctrine`, `updated_at`)
                         VALUES (:uid, :pts, NULL, :now)
                         ON DUPLICATE KEY UPDATE
                             `relic_points` = `relic_points` + :pts2,
                             `updated_at`   = :now2;',
                        [
                            ':uid'  => $uid,
                            ':pts'  => $points,
                            ':now'  => TIMESTAMP,
                            ':pts2' => $points,
                            ':now2' => TIMESTAMP,
                        ]
                    );
                    $db->insert(
                        'INSERT INTO %%RELICS_LOG%% (`user_id`, `delta`, `reason`, `created_at`)
                         VALUES (:uid, :delta, :reason, :now);',
                        [
                            ':uid'    => $uid,
                            ':delta'  => $points,
                            ':reason' => substr($reason, 0, 64),
                            ':now'    => TIMESTAMP,
                        ]
                    );
                    $message = 'Punkte vergeben an ' . htmlspecialchars($targetUser) . '.';
                } else {
                    $error = 'Spieler nicht gefunden: ' . htmlspecialchars($targetUser);
                }
            } catch (Throwable $e) {
                $error = 'Fehler: ' . htmlspecialchars($e->getMessage());
            }
        }
    }

    // ── Load settings ─────────────────────────────────────────────────────────
    $settings = [];
    try {
        $rows = $db->select('SELECT `k`, `v` FROM %%RELICS_SETTINGS%%;');
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $settings[$row['k']] = $row['v'];
            }
        }
    } catch (Throwable $e) {
        $error = 'Tabellen nicht gefunden – Plugin neu installieren.';
    }

    // ── Top players ───────────────────────────────────────────────────────────
    $topPlayers = [];
    try {
        $topPlayers = $db->select(
            'SELECT u.username, r.relic_points, r.doctrine
             FROM %%RELICS_USER%% r
             JOIN %%USERS%% u ON u.id = r.user_id
             ORDER BY r.relic_points DESC
             LIMIT 20;'
        ) ?: [];
    } catch (Throwable $e) {
        // table may not exist yet
    }

    // ── Doctrine stats ────────────────────────────────────────────────────────
    $doctrineStats = [];
    try {
        $rows = $db->select(
            'SELECT COALESCE(`doctrine`, \'none\') AS doc, COUNT(*) AS cnt
             FROM %%RELICS_USER%%
             GROUP BY `doctrine`;'
        );
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $doctrineStats[$row['doc']] = (int) $row['cnt'];
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    // ── Cronjob status ────────────────────────────────────────────────────────
    $cronStatus = [];
    try {
        $cronStatus = $db->selectSingle(
            'SELECT `nextTime`, `lock`, `lockTime` FROM %%CRONJOBS%% WHERE `name` = :name;',
            [':name' => 'relics_tick']
        ) ?: [];
    } catch (Throwable $e) {
        // ignore
    }

    // ── Render ────────────────────────────────────────────────────────────────
    $template = new template();
    $template->assign_vars([
        'message'       => $message,
        'error'         => $error,
        'settings'      => $settings,
        'topPlayers'    => $topPlayers,
        'doctrineStats' => $doctrineStats,
        'cronStatus'    => $cronStatus,
    ]);
    $template->show('@sm-relics/admin/relics_admin.twig');
}
