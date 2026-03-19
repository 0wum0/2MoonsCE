<?php
/**
 * 2MoonsCE — AdminNetwork: Hub HTTP Client
 *
 * Handles all HTTP communication with the central hub server.
 *
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 */

declare(strict_types=1);

class HubClient
{
    private string $hubUrl;
    private string $apiKey;
    private int    $timeout;

    public function __construct(string $hubUrl, string $apiKey, int $timeout = 8)
    {
        $this->hubUrl  = rtrim($hubUrl, '/') . '/';
        $this->apiKey  = $apiKey;
        $this->timeout = $timeout;
    }

    public function send(string $text): array
    {
        return $this->post(['action' => 'send', 'text' => $text]);
    }

    public function poll(int $sinceId = 0, int $limit = 50): array
    {
        return $this->post(['action' => 'poll', 'since_id' => $sinceId, 'limit' => $limit]);
    }

    public function ping(): array
    {
        return $this->post(['action' => 'ping']);
    }

    public function online(): array
    {
        return $this->post(['action' => 'online']);
    }

    public function delete(int $messageId): array
    {
        return $this->post(['action' => 'delete', 'message_id' => $messageId]);
    }

    public function status(): array
    {
        return $this->post(['action' => 'status']);
    }

    // ── Internal HTTP ─────────────────────────────────────────────────────────
    private function post(array $payload): array
    {
        $payload['api_key'] = $this->apiKey;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nX-Hub-Key: {$this->apiKey}\r\n",
                'content'       => $body,
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $raw = @file_get_contents($this->hubUrl, false, $context);
            if ($raw === false) {
                return ['ok' => false, 'error' => 'Hub nicht erreichbar (connection failed)'];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'error' => 'Ungültige Hub-Antwort (kein JSON)'];
            }
            return $decoded;
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
