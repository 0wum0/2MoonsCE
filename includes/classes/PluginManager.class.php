<?php

declare(strict_types=1);

/**
 * PluginManager – Plugin System v1
 * Handles plugin lifecycle: scan, install, activate, deactivate, uninstall, load.
 */
class PluginManager
{
    private static ?PluginManager $instance = null;

    private const PLUGINS_DIR = 'plugins/';
    private const MANIFEST    = 'manifest.json';
    private const ID_PATTERN  = '/^[a-z0-9\-_]+$/';

    /** @var array<string, array<string, mixed>> */
    private array $loadedPlugins = [];

    /** @var array<string, array<string, string>> */
    private array $langCache = [];

    private function __construct() {}
    private function __clone() {}

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Directory helpers ────────────────────────────────────────────────────

    private function pluginsDir(): string
    {
        return ROOT_PATH . self::PLUGINS_DIR;
    }

    private function pluginDir(string $id): string
    {
        return $this->pluginsDir() . $id . '/';
    }

    // ── Manifest ─────────────────────────────────────────────────────────────

    /**
     * Read and validate a manifest.json from a plugin directory.
     *
     * @return array<string, mixed>
     * @throws RuntimeException on invalid manifest
     */
    public function readManifest(string $pluginDir): array
    {
        $path = rtrim($pluginDir, '/') . '/' . self::MANIFEST;

        if (!file_exists($path)) {
            throw new RuntimeException('manifest.json not found in ' . $pluginDir);
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Cannot read manifest.json');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('manifest.json is not valid JSON');
        }

        foreach (['id', 'name', 'version'] as $required) {
            if (empty($data[$required]) || !is_string($data[$required])) {
                throw new RuntimeException('manifest.json missing required field: ' . $required);
            }
        }

        if (!preg_match(self::ID_PATTERN, $data['id'])) {
            throw new RuntimeException('Plugin id must match [a-z0-9-_], got: ' . $data['id']);
        }

        if (!preg_match('/^\d+\.\d+/', $data['version'])) {
            throw new RuntimeException('Plugin version must start with MAJOR.MINOR, got: ' . $data['version']);
        }

        $data['type'] = isset($data['type']) && is_string($data['type']) ? $data['type'] : 'game';

        return $data;
    }

    // ── Scan ─────────────────────────────────────────────────────────────────

    /**
     * Scan the plugins/ directory and return manifests for all valid plugins.
     *
     * @return array<string, array<string, mixed>>
     */
    public function scanPluginsDir(): array
    {
        $dir = $this->pluginsDir();
        if (!is_dir($dir)) {
            return [];
        }

        $found = [];
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $pluginPath = $dir . $entry;
            if (!is_dir($pluginPath)) {
                continue;
            }
            try {
                $manifest = $this->readManifest($pluginPath);
                $found[$manifest['id']] = $manifest;
            } catch (RuntimeException $e) {
                // Not a valid plugin directory – skip silently
            }
        }

        return $found;
    }

    // ── DB helpers ───────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>|null
     */
    private function dbGetPlugin(string $id): ?array
    {
        try {
            $db  = Database::get();
            $row = $db->selectSingle(
                'SELECT * FROM %%PLUGINS%% WHERE id = :id;',
                [':id' => $id]
            );
            return is_array($row) && !empty($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllPlugins(): array
    {
        try {
            $db   = Database::get();
            $rows = $db->select('SELECT * FROM %%PLUGINS%% ORDER BY installed_at ASC;');
            return is_array($rows) ? $rows : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    // ── Install ──────────────────────────────────────────────────────────────

    /**
     * Install a plugin from its directory (already extracted).
     * Runs SQL migration if present, registers in DB.
     *
     * @param array<string, mixed> $manifest
     * @throws RuntimeException
     */
    public function install(array $manifest): void
    {
        $id  = (string) $manifest['id'];
        $dir = $this->pluginDir($id);

        if (!is_dir($dir)) {
            throw new RuntimeException('Plugin directory not found: ' . $dir);
        }

        // Run migration SQL if present
        $sqlFile = $dir . 'install.sql';
        if (file_exists($sqlFile)) {
            $this->runSqlFile($sqlFile);
        }

        $db  = Database::get();
        $now = time();

        $existing = $this->dbGetPlugin($id);
        if ($existing !== null) {
            // Update existing record
            $db->update(
                'UPDATE %%PLUGINS%% SET name = :name, version = :version, type = :type, updated_at = :updated WHERE id = :id;',
                [
                    ':name'    => (string) $manifest['name'],
                    ':version' => (string) $manifest['version'],
                    ':type'    => (string) $manifest['type'],
                    ':updated' => $now,
                    ':id'      => $id,
                ]
            );
        } else {
            $db->insert(
                'INSERT INTO %%PLUGINS%% (id, name, version, type, is_active, installed_at, updated_at) VALUES (:id, :name, :version, :type, 0, :installed, :updated);',
                [
                    ':id'        => $id,
                    ':name'      => (string) $manifest['name'],
                    ':version'   => (string) $manifest['version'],
                    ':type'      => (string) $manifest['type'],
                    ':installed' => $now,
                    ':updated'   => $now,
                ]
            );
        }
    }

    // ── Update ───────────────────────────────────────────────────────────────

    /**
     * Update an already-installed plugin (re-install with new manifest).
     *
     * @param array<string, mixed> $manifest
     */
    public function update(array $manifest): void
    {
        $this->install($manifest);
    }

    // ── Uninstall ────────────────────────────────────────────────────────────

    /**
     * Uninstall a plugin: run uninstall.sql if present, remove DB record, delete directory.
     */
    public function uninstall(string $id): void
    {
        $dir     = $this->pluginDir($id);
        $sqlFile = $dir . 'uninstall.sql';

        if (file_exists($sqlFile)) {
            $this->runSqlFile($sqlFile);
        }

        try {
            $db = Database::get();
            $db->delete('DELETE FROM %%PLUGINS%% WHERE id = :id;', [':id' => $id]);
        } catch (Throwable $e) {
            error_log('[PluginManager] uninstall DB error for ' . $id . ': ' . $e->getMessage());
        }

        if (is_dir($dir)) {
            $this->deleteDirectory($dir);
        }
    }

    // ── Activate / Deactivate ────────────────────────────────────────────────

    public function activate(string $id): void
    {
        try {
            $db = Database::get();
            $db->update(
                'UPDATE %%PLUGINS%% SET is_active = 1, updated_at = :now WHERE id = :id;',
                [':now' => time(), ':id' => $id]
            );
        } catch (Throwable $e) {
            error_log('[PluginManager] activate error for ' . $id . ': ' . $e->getMessage());
        }
    }

    public function deactivate(string $id): void
    {
        try {
            $db = Database::get();
            $db->update(
                'UPDATE %%PLUGINS%% SET is_active = 0, updated_at = :now WHERE id = :id;',
                [':now' => time(), ':id' => $id]
            );
        } catch (Throwable $e) {
            error_log('[PluginManager] deactivate error for ' . $id . ': ' . $e->getMessage());
        }
    }

    // ── Load active plugins ──────────────────────────────────────────────────

    /**
     * Load all active plugins: include their bootstrap, register hooks/assets/lang.
     * Called once from common.php after DB is ready.
     */
    public function loadActivePlugins(): void
    {
        $plugins = $this->getAllPlugins();

        foreach ($plugins as $row) {
            if ((int) $row['is_active'] !== 1) {
                continue;
            }

            $id  = (string) $row['id'];
            $dir = $this->pluginDir($id);

            if (!is_dir($dir)) {
                continue;
            }

            // Load language
            $this->loadLanguage($id);

            // Include plugin bootstrap
            $bootstrap = $dir . 'plugin.php';
            if (file_exists($bootstrap)) {
                try {
                    require_once $bootstrap;
                } catch (Throwable $e) {
                    error_log('[PluginManager] Bootstrap error in plugin "' . $id . '": ' . $e->getMessage());
                }
            }

            $this->loadedPlugins[$id] = $row;
        }
    }

    // ── Language ─────────────────────────────────────────────────────────────

    private function loadLanguage(string $id): void
    {
        global $LNG;

        $userLang = 'en';
        if (isset($LNG) && is_object($LNG) && method_exists($LNG, 'getLanguage')) {
            $userLang = $LNG->getLanguage();
        }

        $dir      = $this->pluginDir($id) . 'lang/';
        $langFile = $dir . $userLang . '.json';

        if (!file_exists($langFile)) {
            $langFile = $dir . 'en.json';
        }

        if (!file_exists($langFile)) {
            return;
        }

        $raw  = file_get_contents($langFile);
        $data = $raw !== false ? json_decode($raw, true) : null;

        if (is_array($data)) {
            $this->langCache[$id] = $data;
        }
    }

    /**
     * Get a language string for a plugin.
     * Falls back to the key itself if not found.
     */
    public static function lang(string $pluginId, string $key): string
    {
        $self = self::get();
        return (string) ($self->langCache[$pluginId][$key] ?? $key);
    }

    // ── ZIP Upload / Extract ─────────────────────────────────────────────────

    /**
     * Extract a ZIP upload to the plugins directory.
     * Implements ZIP-slip protection.
     *
     * @return array<string, mixed> The manifest from the extracted plugin
     * @throws RuntimeException
     */
    public function installFromZip(string $zipPath): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('ZipArchive extension is not available.');
        }

        $zip = new ZipArchive();
        $res = $zip->open($zipPath);
        if ($res !== true) {
            throw new RuntimeException('Cannot open ZIP file (error code: ' . $res . ')');
        }

        // Find manifest.json inside the ZIP (may be in a subdirectory)
        $manifestIndex = false;
        $manifestPath  = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            if (basename($name) === 'manifest.json' && substr_count(trim($name, '/'), '/') <= 1) {
                $manifestIndex = $i;
                $manifestPath  = $name;
                break;
            }
        }

        if ($manifestIndex === false) {
            $zip->close();
            throw new RuntimeException('No manifest.json found in ZIP.');
        }

        $manifestContent = $zip->getFromIndex($manifestIndex);
        if ($manifestContent === false) {
            $zip->close();
            throw new RuntimeException('Cannot read manifest.json from ZIP.');
        }

        $manifest = json_decode($manifestContent, true);
        if (!is_array($manifest) || empty($manifest['id'])) {
            $zip->close();
            throw new RuntimeException('Invalid manifest.json in ZIP.');
        }

        $id = (string) $manifest['id'];
        if (!preg_match(self::ID_PATTERN, $id)) {
            $zip->close();
            throw new RuntimeException('Invalid plugin id in manifest: ' . $id);
        }

        // Determine the prefix inside the ZIP (e.g. "sm-test/" or "")
        $zipPrefix = dirname($manifestPath);
        if ($zipPrefix === '.') {
            $zipPrefix = '';
        } else {
            $zipPrefix = rtrim($zipPrefix, '/') . '/';
        }

        $targetDir = $this->pluginDir($id);
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0775, true)) {
                $zip->close();
                throw new RuntimeException('Cannot create plugin directory: ' . $targetDir);
            }
        }

        $realTarget = realpath($targetDir);
        if ($realTarget === false) {
            $zip->close();
            throw new RuntimeException('Cannot resolve plugin directory path.');
        }

        // Extract files with ZIP-slip protection
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            // Strip the ZIP prefix
            if ($zipPrefix !== '' && strpos($name, $zipPrefix) === 0) {
                $relative = substr($name, strlen($zipPrefix));
            } elseif ($zipPrefix === '') {
                $relative = $name;
            } else {
                continue;
            }

            if ($relative === '' || $relative === '/') {
                continue;
            }

            // ZIP-slip protection: resolve and check
            $destPath = $targetDir . $relative;
            $realDest = realpath(dirname($destPath));

            if ($realDest === false) {
                // Parent doesn't exist yet – check the string
                $normalised = str_replace('\\', '/', $destPath);
                $normalTarget = str_replace('\\', '/', $realTarget);
                if (strpos($normalised, $normalTarget) !== 0) {
                    continue; // Reject
                }
            } else {
                $normalReal   = str_replace('\\', '/', $realDest);
                $normalTarget = str_replace('\\', '/', $realTarget);
                if (strpos($normalReal, $normalTarget) !== 0) {
                    continue; // ZIP-slip detected – skip
                }
            }

            if (substr($name, -1) === '/') {
                // Directory entry
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0775, true);
                }
            } else {
                $content = $zip->getFromIndex($i);
                if ($content === false) {
                    continue;
                }
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0775, true);
                }
                file_put_contents($destPath, $content);
            }
        }

        $zip->close();

        // Validate the extracted manifest
        return $this->readManifest($targetDir);
    }

    // ── SQL runner ───────────────────────────────────────────────────────────

    private function runSqlFile(string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        try {
            $db = Database::get();
            // Split on semicolons and run each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                static fn(string $s): bool => $s !== '' && !str_starts_with($s, '--')
            );
            foreach ($statements as $stmt) {
                $db->query($stmt . ';');
            }
        } catch (Throwable $e) {
            error_log('[PluginManager] SQL error in ' . $path . ': ' . $e->getMessage());
            throw new RuntimeException('SQL migration failed: ' . $e->getMessage());
        }
    }

    // ── Directory deletion ───────────────────────────────────────────────────

    private function deleteDirectory(string $dir): void
    {
        $dir = rtrim($dir, '/\\');

        // Safety: must be inside plugins/
        $realDir     = realpath($dir);
        $realPlugins = realpath($this->pluginsDir());

        if ($realDir === false || $realPlugins === false) {
            return;
        }

        $normDir     = str_replace('\\', '/', $realDir);
        $normPlugins = str_replace('\\', '/', $realPlugins);

        if (strpos($normDir, $normPlugins . '/') !== 0) {
            return; // Refuse to delete outside plugins/
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            $path = $fileInfo->getRealPath();
            if ($path === false) {
                continue;
            }
            if ($fileInfo->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($realDir);
    }
}
