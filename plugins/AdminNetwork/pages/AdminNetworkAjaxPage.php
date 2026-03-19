<?php
/**
 * 2MoonsCE — AdminNetwork: Ingame AJAX endpoint
 *
 * Handles send/poll/delete for the ingame SmartChat AdminNet tab.
 * Accessible via game.php?page=adminnet_ajax (requires authlevel >= 1).
 *
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 */

declare(strict_types=1);

require_once __DIR__ . '/../lib/AdminNetworkConfig.php';
require_once __DIR__ . '/../lib/HubClient.php';

class AdminNetworkAjaxPage
{
    public function show(): void
    {
        global $USER;

        header('Content-Type: application/json; charset=utf-8');

        if (empty($USER['authlevel']) || (int)$USER['authlevel'] < 1) {
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $cfg    = AdminNetworkConfig::get();
        $action = trim((string)($_POST['an_action'] ?? $_GET['an_action'] ?? ''));

        if (!$cfg['hub_url'] || !$cfg['instance_key']) {
            echo json_encode(['ok' => false, 'error' => 'Not configured']);
            exit;
        }

        $client = new HubClient($cfg['hub_url'], $cfg['instance_key']);

        switch ($action) {
            case 'send_ajax':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['ok' => false, 'error' => 'POST required']);
                    exit;
                }
                $text = trim((string)($_POST['text'] ?? ''));
                if ($text === '') {
                    echo json_encode(['ok' => false, 'error' => 'Text leer']);
                    exit;
                }
                echo json_encode($client->send($text));
                break;

            case 'poll_ajax':
                $sinceId = (int)($_GET['since_id'] ?? $_POST['since_id'] ?? 0);
                echo json_encode($client->poll($sinceId));
                break;

            case 'delete_ajax':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    echo json_encode(['ok' => false]);
                    exit;
                }
                $msgId = (int)($_POST['message_id'] ?? 0);
                if ($msgId <= 0) {
                    echo json_encode(['ok' => false]);
                    exit;
                }
                echo json_encode($client->delete($msgId));
                break;

            default:
                echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        }

        exit;
    }
}
