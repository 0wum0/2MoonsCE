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

// ── Hooks ──────────────────────────────────────────────────────────────────────
(static function (): void {
    $hm = HookManager::get();

    // 1. Ingame footer: inject AdminNetCfg config + show tab for admins
    $hm->addAction('ingame.footer.beforeScripts', static function (array $ctx): string {
        global $USER;
        if (empty($USER['authlevel']) || (int)$USER['authlevel'] < 1) return '';

        require_once __DIR__ . '/lib/AdminNetworkConfig.php';
        $cfg = AdminNetworkConfig::get();
        if (!$cfg['hub_url'] || !$cfg['instance_key']) {
            // Config missing — still show tab with setup hint
            return <<<HTML
<script>
window.AdminNetCfg = { hubUrl:'', instanceKey:'', instanceName:'', ajaxUrl:'admin.php?page=plugin_admin_network' };
(function(){ var t=document.getElementById('scTabAdminNet'); if(t) t.style.display=''; })();
</script>
HTML;
        }

        $ajaxUrl      = 'admin.php?page=plugin_admin_network';
        $instanceName = htmlspecialchars($cfg['instance_name'], ENT_QUOTES, 'UTF-8');
        $hubUrl       = htmlspecialchars($cfg['hub_url'],       ENT_QUOTES, 'UTF-8');
        $instanceKey  = htmlspecialchars($cfg['instance_key'],  ENT_QUOTES, 'UTF-8');

        return <<<HTML
<script>
window.AdminNetCfg = {
    hubUrl:       "{$hubUrl}",
    instanceKey:  "{$instanceKey}",
    instanceName: "{$instanceName}",
    ajaxUrl:      "{$ajaxUrl}"
};
(function(){ var t=document.getElementById('scTabAdminNet'); if(t) t.style.display=''; })();
</script>
HTML;
    }, 20);

    // 2. Admin panel head: load full-page chat CSS
    $hm->addAction('head_end', static function (array $ctx): string {
        if (!defined('MODE') || MODE !== 'ADMIN') return '';
        return '<link rel="stylesheet" href="/plugins/AdminNetwork/assets/css/adminnetwork.css">' . "\n";
    }, 30);

    // 3. Admin panel footer: load full-page chat JS
    $hm->addAction('footer_end', static function (array $ctx): string {
        if (!defined('MODE') || MODE !== 'ADMIN') return '';
        require_once __DIR__ . '/lib/AdminNetworkConfig.php';
        $cfg = AdminNetworkConfig::get();
        if (!$cfg['hub_url'] || !$cfg['instance_key']) return '';
        $pollInterval = max(5, (int)$cfg['poll_interval']) * 1000;
        $hubUrl       = htmlspecialchars($cfg['hub_url'],       ENT_QUOTES, 'UTF-8');
        $instanceKey  = htmlspecialchars($cfg['instance_key'],  ENT_QUOTES, 'UTF-8');
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
