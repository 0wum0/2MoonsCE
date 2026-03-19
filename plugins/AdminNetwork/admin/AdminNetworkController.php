<?php
/**
 * 2MoonsCE — AdminNetwork: Admin Controller
 *
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/AdminNetworkConfig.php';
require_once __DIR__ . '/../lib/HubClient.php';

function ShowAdminNetworkPage(): void
{
    global $USER;

    if (empty($USER['authlevel']) || (int)$USER['authlevel'] < 1) {
        header('Location: admin.php');
        exit;
    }

    $pm      = PluginManager::get();
    $cfg     = AdminNetworkConfig::get();
    $errors  = [];
    $success = false;
    $testResult = null;
    $action  = trim((string)($_POST['an_action'] ?? $_GET['an_action'] ?? ''));

    // ── Save settings ─────────────────────────────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
        $hubUrl      = trim((string)($_POST['hub_url']      ?? ''));
        $instanceKey = trim((string)($_POST['instance_key'] ?? ''));
        $instanceName= trim((string)($_POST['instance_name']?? ''));
        $pollInt     = max(5, (int)($_POST['poll_interval'] ?? 10));

        if ($hubUrl === '') $errors[] = 'Hub-URL darf nicht leer sein.';
        if ($instanceName === '') $errors[] = 'Instanz-Name darf nicht leer sein.';

        if (empty($errors)) {
            $pm->setConfig('AdminNetwork', 'hub_url',       $hubUrl);
            $pm->setConfig('AdminNetwork', 'instance_key',  $instanceKey);
            $pm->setConfig('AdminNetwork', 'instance_name', $instanceName);
            $pm->setConfig('AdminNetwork', 'poll_interval', (string)$pollInt);
            $cfg     = AdminNetworkConfig::get(true);
            $success = true;
        }
    }

    // ── Test connection ───────────────────────────────────────────────────────
    if ($action === 'test') {
        $client = new HubClient($cfg['hub_url'], $cfg['instance_key']);
        $testResult = $client->ping();
    }

    // ── Send message (AJAX) ───────────────────────────────────────────────────
    if ($action === 'send_ajax' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $text       = trim((string)($_POST['text'] ?? ''));
        $senderName = trim((string)($USER['username'] ?? ''));
        if ($text === '' || !$cfg['hub_url'] || !$cfg['instance_key']) {
            echo json_encode(['ok' => false, 'error' => 'Konfiguration unvollständig oder Text leer.']);
            exit;
        }
        $client = new HubClient($cfg['hub_url'], $cfg['instance_key']);
        $result = $client->send($text, $senderName);
        echo json_encode($result);
        exit;
    }

    // ── Poll messages (AJAX) ──────────────────────────────────────────────────
    if ($action === 'poll_ajax') {
        header('Content-Type: application/json');
        $sinceId = (int)($_GET['since_id'] ?? 0);
        if (!$cfg['hub_url'] || !$cfg['instance_key']) {
            echo json_encode(['ok' => false, 'messages' => []]);
            exit;
        }
        $client = new HubClient($cfg['hub_url'], $cfg['instance_key']);
        $result = $client->poll($sinceId);
        echo json_encode($result);
        exit;
    }

    // ── Online instances (AJAX) ───────────────────────────────────────────────
    if ($action === 'online_ajax') {
        header('Content-Type: application/json');
        if (!$cfg['hub_url'] || !$cfg['instance_key']) {
            echo json_encode(['ok' => false, 'instances' => []]);
            exit;
        }
        $client = new HubClient($cfg['hub_url'], $cfg['instance_key']);
        $result = $client->online();
        echo json_encode($result);
        exit;
    }

    // ── Delete message (AJAX) ─────────────────────────────────────────────────
    if ($action === 'delete_ajax' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $msgId = (int)($_POST['message_id'] ?? 0);
        if (!$cfg['hub_url'] || !$cfg['instance_key'] || $msgId <= 0) {
            echo json_encode(['ok' => false]);
            exit;
        }
        $client = new HubClient($cfg['hub_url'], $cfg['instance_key']);
        echo json_encode($client->delete($msgId));
        exit;
    }

    // ── Render page ───────────────────────────────────────────────────────────
    $isConfigured = $cfg['hub_url'] !== '' && $cfg['instance_key'] !== '';

    try {
        $template = new template();
        $template->assign_vars([
            'cfg'         => $cfg,
            'errors'      => $errors,
            'success'     => $success,
            'testResult'  => $testResult,
            'isConfigured'=> $isConfigured,
        ]);
        $template->show('@AdminNetwork/admin/chat.twig');
    } catch (Throwable $e) {
        error_log('[AdminNetwork] render error: ' . $e->getMessage());
        echo '<div style="color:red;padding:20px;">AdminNetwork: Render-Fehler – '
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
