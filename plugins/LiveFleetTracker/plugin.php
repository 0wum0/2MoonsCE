<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/LiveFleetDb.php';
require_once __DIR__ . '/module/LiveFleetModule.php';

// ── 1. Ensure DB tables ───────────────────────────────────────────────────────
try {
    LiveFleetDb::get()->ensureTables();
} catch (Throwable $e) {
    error_log('[LiveFleetTracker] ensureTables() failed: ' . $e->getMessage());
}

$pm = PluginManager::get();

// ── 2. Twig namespace ─────────────────────────────────────────────────────────
$pm->registerTwigNamespace('LiveFleetTracker', 'views');

// ── 3. Admin route ────────────────────────────────────────────────────────────
$pm->registerAdminRoute(
    'LiveFleetTracker',
    'plugin_live_fleet',
    'admin/LiveFleetAdminController.php',
    'ShowLiveFleetAdminPage'
);

// ── 4. Game page route (AJAX intercept endpoint) ──────────────────────────────
$pm->registerPageRoute(
    'LiveFleetTracker',
    'lft_intercept',
    'pages/InterceptPage.php',
    'InterceptPage'
);

// ── 5. Cronjob ────────────────────────────────────────────────────────────────
$pm->registerCronjob(
    'LiveFleetTracker',
    'LiveFleetCronjob',
    'cron/LiveFleetCronjob.php',
    ['min' => '*/5', 'hours' => '*', 'dom' => '*', 'month' => '*', 'dow' => '*']
);

// ── 6. Game Module ────────────────────────────────────────────────────────────
if (class_exists('ModuleManager') && interface_exists('GameModuleInterface')) {
    ModuleManager::get()->register(new LiveFleetModule(), 100);
}
