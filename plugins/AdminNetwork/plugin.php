<?php
/**
 * 2MoonsCE — Plugin: AdminNetwork
 *
 * Cross-instance admin chat via central hub server.
 *
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 */

declare(strict_types=1);

$pm = PluginManager::get();

// ── Twig namespace ────────────────────────────────────────────────────────────
$pm->registerTwigNamespace('AdminNetwork', 'views');

// ── Admin route ───────────────────────────────────────────────────────────────
$pm->registerAdminRoute(
    'AdminNetwork',
    'plugin_admin_network',
    'admin/AdminNetworkController.php',
    'ShowAdminNetworkPage'
);

// ── Inject CSS + JS into admin pages only ─────────────────────────────────────
(static function (): void {
    $hm = HookManager::get();

    $hm->addAction('head_end', static function (array $ctx): string {
        if (!defined('MODE') || MODE !== 'ADMIN') return '';
        return '<link rel="stylesheet" href="/plugins/AdminNetwork/assets/css/adminnetwork.css">' . "\n";
    }, 30);

    $hm->addAction('footer_end', static function (array $ctx): string {
        if (!defined('MODE') || MODE !== 'ADMIN') return '';
        $cfg = AdminNetworkConfig::get();
        if (!$cfg['hub_url'] || !$cfg['instance_key']) return '';
        $pollInterval = max(5, (int)$cfg['poll_interval']) * 1000;
        $hubUrl       = htmlspecialchars($cfg['hub_url'], ENT_QUOTES, 'UTF-8');
        $instanceKey  = htmlspecialchars($cfg['instance_key'], ENT_QUOTES, 'UTF-8');
        $instanceName = htmlspecialchars($cfg['instance_name'], ENT_QUOTES, 'UTF-8');
        return <<<HTML
<script>
window.AdminNetwork = {
    hubUrl:       "{$hubUrl}",
    instanceKey:  "{$instanceKey}",
    instanceName: "{$instanceName}",
    pollInterval: {$pollInterval}
};
</script>
<script src="/plugins/AdminNetwork/assets/js/adminnetwork.js" defer></script>
HTML;
    }, 30);
})();
