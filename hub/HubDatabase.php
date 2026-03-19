<?php
/**
 * 2MoonsCE Admin Network Hub — Database Layer (SQLite)
 *
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 */

declare(strict_types=1);

class HubDatabase
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dir = dirname(HUB_DB_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $this->pdo = new PDO('sqlite:' . HUB_DB_PATH);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('PRAGMA journal_mode=WAL;');
        $this->pdo->exec('PRAGMA foreign_keys=ON;');
        $this->ensureTables();
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Schema ────────────────────────────────────────────────────────────────
    private function ensureTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS instances (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL UNIQUE,
                url         TEXT    NOT NULL,
                api_key     TEXT    NOT NULL UNIQUE,
                active      INTEGER NOT NULL DEFAULT 1,
                last_ping   INTEGER NOT NULL DEFAULT 0,
                created_at  INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            );

            CREATE TABLE IF NOT EXISTS messages (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                instance_id  INTEGER NOT NULL REFERENCES instances(id),
                instance_name TEXT   NOT NULL,
                text         TEXT    NOT NULL,
                created_at   INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            );

            CREATE INDEX IF NOT EXISTS idx_messages_created ON messages(created_at);
            CREATE INDEX IF NOT EXISTS idx_instances_ping   ON instances(last_ping);

            CREATE TABLE IF NOT EXISTS rate_limits (
                ip          TEXT    NOT NULL,
                bucket      INTEGER NOT NULL,
                hits        INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (ip, bucket)
            );
        ");
    }

    // ── Instance management ───────────────────────────────────────────────────
    public function registerInstance(string $name, string $url): string
    {
        $key = bin2hex(random_bytes(24));

        $stmt = $this->pdo->prepare("
            INSERT INTO instances (name, url, api_key)
            VALUES (:name, :url, :key)
            ON CONFLICT(name) DO UPDATE SET url=excluded.url, api_key=excluded.api_key, active=1
        ");
        $stmt->execute([':name' => $name, ':url' => $url, ':key' => $key]);
        return $key;
    }

    public function getInstanceByKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, name, url FROM instances WHERE api_key = :key AND active = 1"
        );
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updatePing(int $instanceId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE instances SET last_ping = strftime('%s','now') WHERE id = :id"
        );
        $stmt->execute([':id' => $instanceId]);
    }

    public function getOnlineInstances(int $withinSeconds = 300): array
    {
        $since = time() - $withinSeconds;
        $stmt  = $this->pdo->prepare(
            "SELECT name, url, last_ping FROM instances WHERE active=1 AND last_ping >= :since ORDER BY name"
        );
        $stmt->execute([':since' => $since]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Messages ──────────────────────────────────────────────────────────────
    public function insertMessage(int $instanceId, string $instanceName, string $text): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO messages (instance_id, instance_name, text)
            VALUES (:iid, :iname, :text)
        ");
        $stmt->execute([':iid' => $instanceId, ':iname' => $instanceName, ':text' => $text]);
        $id = (int)$this->pdo->lastInsertId();
        $this->pruneMessages();
        return $id;
    }

    public function getMessages(int $sinceId = 0, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, instance_name, text, created_at
            FROM messages
            WHERE id > :since
            ORDER BY id ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':since', $sinceId, PDO::PARAM_INT);
        $stmt->bindValue(':lim',   $limit,   PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteMessage(int $msgId, int $instanceId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM messages WHERE id = :mid AND instance_id = :iid"
        );
        $stmt->execute([':mid' => $msgId, ':iid' => $instanceId]);
        return $stmt->rowCount() > 0;
    }

    private function pruneMessages(): void
    {
        $maxAge  = defined('HUB_MAX_MESSAGE_AGE') ? HUB_MAX_MESSAGE_AGE : 2592000;
        $maxRows = defined('HUB_MAX_MESSAGES')    ? HUB_MAX_MESSAGES    : 5000;
        $cutoff  = time() - $maxAge;

        $this->pdo->exec("DELETE FROM messages WHERE created_at < $cutoff");

        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
        if ($count > $maxRows) {
            $del = $count - $maxRows;
            $this->pdo->exec("DELETE FROM messages WHERE id IN (SELECT id FROM messages ORDER BY id ASC LIMIT $del)");
        }
    }

    // ── Rate limiting ─────────────────────────────────────────────────────────
    /**
     * @param int    $maxHits       Maximum allowed hits in the window
     * @param int    $windowSeconds Window size in seconds (default 60 = per minute)
     * @param string $bucketPrefix  Prefix to namespace different rate-limit rules
     */
    public function checkRateLimit(string $ip, int $maxHits, int $windowSeconds = 60, string $bucketPrefix = 'gen'): bool
    {
        $bucket = $bucketPrefix . ':' . (int)floor(time() / $windowSeconds);

        $ins = $this->pdo->prepare("
            INSERT INTO rate_limits (ip, bucket, hits) VALUES (:ip, :b, 1)
            ON CONFLICT(ip, bucket) DO UPDATE SET hits = hits + 1
        ");
        $ins->execute([':ip' => $ip, ':b' => $bucket]);

        $sel = $this->pdo->prepare(
            "SELECT hits FROM rate_limits WHERE ip = :ip AND bucket = :b"
        );
        $sel->execute([':ip' => $ip, ':b' => $bucket]);
        $hits = (int)($sel->fetchColumn() ?: 0);

        if (mt_rand(0, 100) < 5) {
            $cutoffBucket = $bucketPrefix . ':' . ((int)floor(time() / $windowSeconds) - 2);
            $this->pdo->exec("DELETE FROM rate_limits WHERE ip = " . $this->pdo->quote($ip) . " AND bucket <= " . $this->pdo->quote($cutoffBucket));
        }

        return $hits <= $maxHits;
    }

    // ── Stats ─────────────────────────────────────────────────────────────────
    public function getStats(): array
    {
        $total    = (int)$this->pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
        $reg      = (int)$this->pdo->query("SELECT COUNT(*) FROM instances WHERE active=1")->fetchColumn();
        $since    = time() - 300;
        $online   = (int)$this->pdo->query("SELECT COUNT(*) FROM instances WHERE active=1 AND last_ping>=$since")->fetchColumn();
        return ['total_messages' => $total, 'registered_instances' => $reg, 'online_instances' => $online];
    }
}
