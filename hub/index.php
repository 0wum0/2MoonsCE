<?php
/**
 * 2MoonsCE Admin Network — Hub Server
 *
 * Standalone PHP API that acts as a relay server for cross-instance admin chat.
 * Deploy this file (and the entire hub/ folder) on any PHP host.
 * No framework, no Composer — only SQLite (PDO) required.
 *
 * Endpoints (all POST with JSON body or GET with query params):
 *   POST /hub/     action=send    → send a message
 *   POST /hub/     action=poll    → fetch messages since a given id
 *   POST /hub/     action=ping    → register/update instance presence
 *   GET  /hub/     action=online  → list online instances (public)
 *
 * Authentication: every request must include a valid api_key.
 * The master API key is defined in config.php (HUB_MASTER_KEY).
 * Instance keys are derived per-instance and stored in the DB.
 *
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────
define('HUB_ROOT', __DIR__);

require_once HUB_ROOT . '/config.php';
require_once HUB_ROOT . '/HubDatabase.php';

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Hub-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Parse request ─────────────────────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim((string)($input['action'] ?? $_GET['action'] ?? $_POST['action'] ?? ''));
$apiKey = trim((string)($input['api_key'] ?? $_SERVER['HTTP_X_HUB_KEY'] ?? $_POST['api_key'] ?? ''));

$db = HubDatabase::get();

// ── Rate limiting (simple: per IP, 60 requests/minute) ───────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!$db->checkRateLimit($ip, 60)) {
    jsonError(429, 'Rate limit exceeded');
}

// ── Routing ───────────────────────────────────────────────────────────────────
switch ($action) {

    // ── Register / update instance ──────────────────────────────────────────
    case 'register':
        requireMasterKey($apiKey);
        $name    = trim((string)($input['instance_name'] ?? ''));
        $url     = trim((string)($input['instance_url']  ?? ''));
        if ($name === '' || $url === '') {
            jsonError(400, 'instance_name and instance_url required');
        }
        $key = $db->registerInstance($name, $url);
        jsonOk(['instance_key' => $key, 'message' => 'Instance registered. Store this key safely.']);
        break;

    // ── Send message ────────────────────────────────────────────────────────
    case 'send':
        $instance = requireInstanceKey($apiKey, $db);
        $text = trim((string)($input['text'] ?? ''));
        if ($text === '') {
            jsonError(400, 'text required');
        }
        if (mb_strlen($text) > 2000) {
            jsonError(400, 'text too long (max 2000 chars)');
        }
        $id = $db->insertMessage($instance['id'], $instance['name'], htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        $db->updatePing($instance['id']);
        jsonOk(['id' => $id]);
        break;

    // ── Poll messages ────────────────────────────────────────────────────────
    case 'poll':
        $instance  = requireInstanceKey($apiKey, $db);
        $sinceId   = (int)($input['since_id'] ?? 0);
        $limit     = min((int)($input['limit'] ?? 50), 200);
        $messages  = $db->getMessages($sinceId, $limit);
        $db->updatePing($instance['id']);
        jsonOk(['messages' => $messages]);
        break;

    // ── Ping (keep-alive) ────────────────────────────────────────────────────
    case 'ping':
        $instance = requireInstanceKey($apiKey, $db);
        $db->updatePing($instance['id']);
        jsonOk(['status' => 'ok']);
        break;

    // ── List online instances ────────────────────────────────────────────────
    case 'online':
        requireInstanceKey($apiKey, $db);
        $instances = $db->getOnlineInstances(300); // online = seen in last 5 min
        jsonOk(['instances' => $instances]);
        break;

    // ── Hub status (no auth) ─────────────────────────────────────────────────
    case 'status':
        $stats = $db->getStats();
        jsonOk([
            'hub'      => '2MoonsCE Admin Network Hub',
            'version'  => '1.0.0',
            'messages' => $stats['total_messages'],
            'instances'=> $stats['registered_instances'],
            'online'   => $stats['online_instances'],
        ]);
        break;

    // ── Delete own messages ─────────────────────────────────────────────────
    case 'delete':
        $instance = requireInstanceKey($apiKey, $db);
        $msgId    = (int)($input['message_id'] ?? 0);
        if ($msgId <= 0) jsonError(400, 'message_id required');
        $deleted = $db->deleteMessage($msgId, $instance['id']);
        if (!$deleted) jsonError(403, 'Message not found or not yours');
        jsonOk(['deleted' => true]);
        break;

    default:
        jsonError(400, 'Unknown action. Valid: register, send, poll, ping, online, status, delete');
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function requireMasterKey(string $key): void
{
    if (!defined('HUB_MASTER_KEY') || $key !== HUB_MASTER_KEY) {
        jsonError(403, 'Invalid master key');
    }
}

function requireInstanceKey(string $key, HubDatabase $db): array
{
    if ($key === '') jsonError(401, 'api_key required');
    $instance = $db->getInstanceByKey($key);
    if ($instance === null) jsonError(403, 'Invalid or inactive instance key');
    return $instance;
}

function jsonOk(array $data): never
{
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
