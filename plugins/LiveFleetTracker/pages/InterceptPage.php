<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/LiveFleetDb.php';

/**
 * InterceptPage – AJAX endpoint for fleet interception requests.
 * Accessible via: game.php?page=lft_intercept
 *
 * POST actions:
 *   action=intercept  → create interception for target_fleet_id
 *   action=cancel     → cancel own pending interception by id
 *
 * GET actions:
 *   action=list       → return JSON list of active interceptions visible to user
 *   action=alerts     → return JSON of unnotified NPC/warp events for current user
 */
class InterceptPage
{
    public function show(): void
    {
        global $USER;

        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');

        if (empty($USER['id'])) {
            echo json_encode(['ok' => false, 'error' => 'not authenticated']);
            exit;
        }

        $userId = (int)$USER['id'];
        $action = (string)($_REQUEST['action'] ?? 'list');
        $ldb    = LiveFleetDb::get();

        try {
            switch ($action) {
                case 'intercept':
                    $this->handleIntercept($ldb, $userId);
                    break;

                case 'cancel':
                    $this->handleCancel($ldb, $userId);
                    break;

                case 'alerts':
                    $this->handleAlerts($ldb, $userId);
                    break;

                default:
                    $this->handleList($ldb, $userId);
                    break;
            }
        } catch (Throwable $e) {
            error_log('[InterceptPage] error: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'internal error']);
        }

        exit;
    }

    // ── Action: create interception ───────────────────────────────────────────

    private function handleIntercept(LiveFleetDb $ldb, int $userId): void
    {
        $settings = $ldb->getSettings();
        if (!(bool)(int)($settings['interception_enabled'] ?? 1)) {
            echo json_encode(['ok' => false, 'error' => 'interception_disabled']);
            return;
        }

        $targetFleetId = (int)($_POST['target_fleet_id'] ?? 0);
        if ($targetFleetId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'invalid_fleet_id']);
            return;
        }

        // Verify target fleet exists, is hostile and not own
        $db  = Database::get();
        $now = defined('TIMESTAMP') ? TIMESTAMP : time();
        $fleet = $db->selectSingle(
            'SELECT fleet_id, fleet_owner, fleet_mission, fleet_end_time
             FROM %%FLEETS%%
             WHERE fleet_id = :fid AND fleet_end_time > :now AND fleet_universe = :uni;',
            [':fid' => $targetFleetId, ':now' => $now, ':uni' => Universe::current()]
        );

        if (empty($fleet)) {
            echo json_encode(['ok' => false, 'error' => 'fleet_not_found']);
            return;
        }
        if ((int)$fleet['fleet_owner'] === $userId) {
            echo json_encode(['ok' => false, 'error' => 'cannot_intercept_own_fleet']);
            return;
        }
        if (!in_array((int)$fleet['fleet_mission'], [1, 2, 9], true)) {
            echo json_encode(['ok' => false, 'error' => 'fleet_not_hostile']);
            return;
        }

        $id = $ldb->createInterception($targetFleetId, $userId);
        if ($id === 0) {
            echo json_encode(['ok' => false, 'error' => 'already_intercepting']);
            return;
        }

        echo json_encode([
            'ok'               => true,
            'interception_id'  => $id,
            'message'          => 'Abfang-Befehl gesendet. Deine Flotte wurde auf Abfangkurs gebracht.',
        ]);
    }

    // ── Action: cancel interception ───────────────────────────────────────────

    private function handleCancel(LiveFleetDb $ldb, int $userId): void
    {
        $interceptId = (int)($_POST['interception_id'] ?? 0);
        if ($interceptId <= 0) {
            echo json_encode(['ok' => false, 'error' => 'invalid_id']);
            return;
        }

        $db = Database::get();
        $row = $db->selectSingle(
            "SELECT * FROM %%PREFIX%%lft_interceptions WHERE id = :id AND interceptor_id = :uid AND status = 'pending';",
            [':id' => $interceptId, ':uid' => $userId]
        );

        if (empty($row)) {
            echo json_encode(['ok' => false, 'error' => 'not_found_or_not_pending']);
            return;
        }

        $ldb->resolveInterception($interceptId, 'failed');
        echo json_encode(['ok' => true, 'message' => 'Abfang-Befehl abgebrochen.']);
    }

    // ── Action: list active interceptions ─────────────────────────────────────

    private function handleList(LiveFleetDb $ldb, int $userId): void
    {
        $interceptions = $ldb->getActiveInterceptions();
        echo json_encode([
            'ok'            => true,
            'interceptions' => $interceptions,
            'count'         => count($interceptions),
            'server_time'   => defined('TIMESTAMP') ? TIMESTAMP : time(),
        ], JSON_UNESCAPED_UNICODE);
    }

    // ── Action: alerts (NPC + warp events for current user) ───────────────────

    private function handleAlerts(LiveFleetDb $ldb, int $userId): void
    {
        $npc  = $ldb->getUnnotifiedNpcAttacks($userId);
        $warp = $ldb->getUnnotifiedWarpEvents($userId);

        if (!empty($npc)) {
            $ldb->markNpcNotified($userId);
        }
        if (!empty($warp)) {
            $ldb->markWarpNotified($userId);
        }

        echo json_encode([
            'ok'          => true,
            'npc_attacks' => $npc,
            'warp_events' => $warp,
            'server_time' => defined('TIMESTAMP') ? TIMESTAMP : time(),
        ], JSON_UNESCAPED_UNICODE);
    }
}

// Bootstrap: instantiate and run
(new InterceptPage())->show();
