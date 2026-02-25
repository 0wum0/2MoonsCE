<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/GalacticEventsDb.php';

/**
 * GalacticEventsGameController
 *
 * Ingame AJAX endpoint for the GalacticEvents plugin.
 * Registered via PluginManager::registerPageRoute() as a class with a show() method.
 *
 * Route: game.php?page=galactic_events_api&mode=status
 *
 * Modes:
 *   status  → JSON: current active event data (or null)
 */
class GalacticEventsGameController
{
    public string $defaultController = 'show';

    public function show(): void
    {
        $this->status();
    }

    public function status(): void
    {
        global $USER;

        // Auth guard
        if (empty($USER['id'])) {
            $this->sendJson(['ok' => false, 'error' => 'not_authenticated']);
            return;
        }

        try {
            $db          = GalacticEventsDb::get();
            $activeEvent = $db->getActiveEvent();
            $now         = defined('TIMESTAMP') ? TIMESTAMP : time();

            if ($activeEvent === null) {
                $this->sendJson(['ok' => true, 'event' => null]);
                return;
            }

            $until      = (int)($activeEvent['active_until'] ?? 0);
            $secsLeft   = max(0, $until - $now);
            $value      = (float)($activeEvent['effect_value'] ?? 0);

            $this->sendJson([
                'ok'    => true,
                'event' => [
                    'id'           => (int)$activeEvent['id'],
                    'name'         => (string)$activeEvent['name'],
                    'effect_type'  => (string)$activeEvent['effect_type'],
                    'effect_value' => $value,
                    'active_from'  => (int)$activeEvent['active_from'],
                    'active_until' => $until,
                    'seconds_left' => $secsLeft,
                ],
            ]);

        } catch (Throwable $e) {
            error_log('[GalacticEventsGameController] status() error: ' . $e->getMessage());
            $this->sendJson(['ok' => false, 'error' => 'internal_error']);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $data
     */
    private function sendJson(array $data): void
    {
        // Discard any buffered output from game.php ob_start()
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
