<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/LiveFleetDb.php';

class LiveFleetModule implements GameModuleInterface
{
    public function getId(): string
    {
        return 'live_fleet_tracker.main';
    }

    public function isEnabled(): bool
    {
        try {
            $db = Database::get();
            if (!$db->isConnected()) {
                return false;
            }
            $settings = LiveFleetDb::get()->getSettings();
            return !empty($settings) && (bool)(int)($settings['enabled'] ?? 0);
        } catch (Throwable $e) {
            error_log('[LiveFleetModule] isEnabled() error: ' . $e->getMessage());
            return false;
        }
    }

    public function boot(GameContext $ctx): void
    {
        try {
            $pm  = PluginManager::get();
            $hm  = HookManager::get();
            $css = './plugins/LiveFleetTracker/assets/css/livefleet.css';
            $js  = './plugins/LiveFleetTracker/assets/js/livefleet.js';

            // ── Inject CSS in <head> ──────────────────────────────────────────
            $hm->addAction('head_end', static function (array $ctx) use ($css): string {
                if (!defined('MODE') || MODE !== 'INGAME') {
                    return '';
                }
                return '<link rel="stylesheet" href="' . htmlspecialchars($css, ENT_QUOTES, 'UTF-8') . '">' . "\n";
            }, 30);

            // ── Inject JS + bootstrap data before </body> ─────────────────────
            $hm->addAction('footer_end', static function (array $ctx) use ($js): string {
                if (!defined('MODE') || MODE !== 'INGAME') {
                    return '';
                }
                $page = (string)($_GET['page'] ?? '');
                if ($page !== 'galaxyMap') {
                    return '';
                }
                try {
                    $settings    = LiveFleetDb::get()->getSettings();
                    $pollMs      = max(1000, (int)($settings['poll_interval_ms'] ?? 3000));
                    $intercept   = (bool)(int)($settings['interception_enabled'] ?? 1);
                    $configJson  = json_encode([
                        'pollInterval'        => $pollMs,
                        'interceptionEnabled' => $intercept,
                        'assetBase'           => './plugins/LiveFleetTracker/assets/',
                    ], JSON_UNESCAPED_SLASHES);
                } catch (Throwable $e) {
                    $configJson = '{"pollInterval":3000,"interceptionEnabled":false,"assetBase":"./plugins/LiveFleetTracker/assets/"}';
                }

                return '<script>window.LFT_Config=' . $configJson . ';</script>' . "\n"
                     . '<script src="' . htmlspecialchars($js, ENT_QUOTES, 'UTF-8') . '" defer></script>' . "\n";
            }, 30);

        } catch (Throwable $e) {
            error_log('[LiveFleetModule] boot() error: ' . $e->getMessage());
        }
    }

    public function beforeRequest(GameContext $ctx): void
    {
        try {
            global $USER;
            if (empty($USER['id'])) {
                return;
            }

            $ldb     = LiveFleetDb::get();
            $userId  = (int)$USER['id'];

            // Collect unnotified events for this user
            $npcAttacks  = $ldb->getUnnotifiedNpcAttacks($userId);
            $warpEvents  = $ldb->getUnnotifiedWarpEvents($userId);

            if (!empty($npcAttacks)) {
                $ldb->markNpcNotified($userId);
            }
            if (!empty($warpEvents)) {
                $ldb->markWarpNotified($userId);
            }

            // Alerts are delivered client-side via the /lft_intercept?action=alerts polling endpoint.
        } catch (Throwable $e) {
            error_log('[LiveFleetModule] beforeRequest() error: ' . $e->getMessage());
        }
    }

    public function afterRequest(GameContext $ctx): void {}
}
