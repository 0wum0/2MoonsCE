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
