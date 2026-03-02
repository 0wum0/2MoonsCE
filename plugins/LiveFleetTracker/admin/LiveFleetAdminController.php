<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/LiveFleetDb.php';

function ShowLiveFleetAdminPage(): void
{
    $ldb      = LiveFleetDb::get();
    $settings = $ldb->getSettings();
    $errors   = [];
    $success  = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = [
            'enabled'              => (int)(isset($_POST['enabled']) ? 1 : 0),
            'npc_enabled'          => (int)(isset($_POST['npc_enabled']) ? 1 : 0),
            'npc_chance_percent'   => (int)($_POST['npc_chance_percent'] ?? 5),
            'warp_risk_enabled'    => (int)(isset($_POST['warp_risk_enabled']) ? 1 : 0),
            'warp_risk_percent'    => (int)($_POST['warp_risk_percent'] ?? 3),
            'interception_enabled' => (int)(isset($_POST['interception_enabled']) ? 1 : 0),
            'poll_interval_ms'     => (int)($_POST['poll_interval_ms'] ?? 3000),
        ];

        if ($raw['npc_chance_percent'] < 1 || $raw['npc_chance_percent'] > 50) {
            $errors[] = 'NPC-Chance muss zwischen 1 und 50 liegen.';
        }
        if ($raw['warp_risk_percent'] < 1 || $raw['warp_risk_percent'] > 20) {
            $errors[] = 'Warp-Risiko muss zwischen 1 und 20 liegen.';
        }
        if ($raw['poll_interval_ms'] < 1000 || $raw['poll_interval_ms'] > 60000) {
            $errors[] = 'Poll-Intervall muss zwischen 1000 und 60000 ms liegen.';
        }

        if (empty($errors)) {
            $ldb->saveSettings($raw);
            $settings = $ldb->getSettings();
            $success  = true;
        }
    }

    $recentNpc  = [];
    $recentWarp = [];
    try {
        $db = Database::get();
        if ($db->isConnected()) {
            $p = defined('DB_PREFIX') ? DB_PREFIX : 'uni1_';
            $recentNpc = $db->select(
                "SELECT n.*, u.username FROM `{$p}lft_npc_attacks` n
                 LEFT JOIN %%USERS%% u ON u.id = n.owner_id
                 ORDER BY n.created_at DESC LIMIT 20;"
            ) ?: [];
            $recentWarp = $db->select(
                "SELECT w.*, u.username FROM `{$p}lft_warp_events` w
                 LEFT JOIN %%USERS%% u ON u.id = w.owner_id
                 ORDER BY w.created_at DESC LIMIT 20;"
            ) ?: [];
        }
    } catch (Throwable $e) {
        // no-op
    }

    PluginManager::get()->registerTwigNamespace('LiveFleetTracker', 'views');

    try {
        $template = new template();
        $template->assign_vars([
            'lft_settings'    => $settings,
            'lft_errors'      => $errors,
            'lft_success'     => $success,
            'lft_recent_npc'  => $recentNpc,
            'lft_recent_warp' => $recentWarp,
        ]);
        $template->show('@LiveFleetTracker/admin/settings.twig');
    } catch (Throwable $e) {
        error_log('[LiveFleetTrackerAdmin] render error: ' . $e->getMessage());
        echo '<div style="color:red;padding:20px;">LiveFleetTracker Admin: Render-Fehler – '
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
