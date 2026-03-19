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

    // 1. Ingame footer: auto-register if needed, inject AdminNetCfg, show tab for admins
    $hm->addAction('ingame.footer.beforeScripts', static function (array $ctx): string {
        global $USER;
        if (empty($USER['authlevel']) || (int)$USER['authlevel'] < 1) return '';

        require_once __DIR__ . '/lib/AdminNetworkConfig.php';
        require_once __DIR__ . '/lib/HubClient.php';

        $pm  = PluginManager::get();
        $cfg = AdminNetworkConfig::get();

        // ── Auto-fix generic instance_name (e.g. "Mein Server") ─────────────
        if (!empty($cfg['instance_key']) && (
            $cfg['instance_name'] === '' ||
            strtolower($cfg['instance_name']) === 'mein server' ||
            strtolower($cfg['instance_name']) === 'my server'
        )) {
            try {
                $dbFix    = Database::get();
                $rowFix   = $dbFix->selectSingle('SELECT game_name FROM %%CONFIG%% LIMIT 1;');
                $nameFix  = trim((string)($rowFix['game_name'] ?? ''));
                if ($nameFix === '' || strtolower($nameFix) === 'mein server' || strtolower($nameFix) === 'my server') {
                    $nameFix = (string)($_SERVER['HTTP_HOST'] ?? '2MoonsCE');
                }
                $pm->setConfig('AdminNetwork', 'instance_name', $nameFix);
                $cfg = AdminNetworkConfig::get(true);
            } catch (Throwable $e) {
                error_log('[AdminNetwork] auto-fix name error: ' . $e->getMessage());
            }
        }

        // ── Auto-register if instance_key is missing ──────────────────────────
        if (empty($cfg['instance_key'])) {
            try {
                // Read game_name from DB; fall back to HTTP host
                $db       = Database::get();
                $row      = $db->selectSingle('SELECT game_name, uni_name FROM %%CONFIG%% LIMIT 1;');
                $gameName = trim((string)($row['game_name'] ?? $row['uni_name'] ?? ''));
                $host     = (string)($_SERVER['HTTP_HOST'] ?? '');
                // Use hostname if game_name is empty or still the generic install default
                if ($gameName === '' || strtolower($gameName) === 'mein server' || strtolower($gameName) === 'my server') {
                    $gameName = $host ?: '2MoonsCE';
                }

                $instanceUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . ($_SERVER['HTTP_HOST'] ?? 'unknown');

                $hubUrl = 'https://2moonsce.makeit.uno/hub/';
                $client = new HubClient($hubUrl, '');
                $result = $client->publicRegister($gameName, $instanceUrl);

                if (!empty($result['ok']) && !empty($result['instance_key'])) {
                    $pm->setConfig('AdminNetwork', 'hub_url',       $hubUrl);
                    $pm->setConfig('AdminNetwork', 'instance_key',  $result['instance_key']);
                    $pm->setConfig('AdminNetwork', 'instance_name', $gameName);
                    $cfg = AdminNetworkConfig::get(true);
                }
            } catch (Throwable $e) {
                error_log('[AdminNetwork] auto-register error: ' . $e->getMessage());
            }
        }

        if (empty($cfg['instance_key'])) {
            // Still not configured (hub unreachable?) — show tab with setup hint
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
