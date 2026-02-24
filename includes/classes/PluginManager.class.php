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

    /**
     * Notices collected during safe-mode auto-deactivations this request.
     * Each entry: ['plugin' => id, 'reason' => message, 'type' => 'plugin'|'module']
     * @var array<int, array{plugin: string, reason: string, type: string}>
     */
    private array $safeModeNotices = [];

    /** Path to the safe-mode lock file (relative to ROOT_PATH). */
    private const SAFE_MODE_LOCK = 'cache/safe_mode.lock';

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

    // ── Plugin Page Routes ───────────────────────────────────────────────────

    /**
     * page name → ['file' => abs path, 'class' => class name]
     * @var array<string, array{file: string, class: string}>
     */
    private array $pageRoutes = [];

    /**
     * admin page name → ['file' => abs path, 'fn' => function name]
     * @var array<string, array{file: string, fn: string}>
     */
    private array $adminRoutes = [];

    /**
     * Register a plugin ingame page route.
     * Called from plugin.php:
     *   PluginManager::get()->registerPageRoute('sm-relics', 'relics',
     *       'pages/game/RelicsPage.php', 'RelicsPage');
     *
     * game.php will require the file and instantiate $class->show() when
     * page=<pageName> is requested.
     */
    public function registerPageRoute(
        string $pluginId,
        string $pageName,
        string $relativeFile,
        string $className
    ): void {
        $absFile = $this->pluginDir($pluginId) . ltrim($relativeFile, '/');
        $this->pageRoutes[$pageName] = ['file' => $absFile, 'class' => $className];
    }

    /**
     * Register a plugin admin page route.
     * Called from plugin.php:
     *   PluginManager::get()->registerAdminRoute('sm-relics', 'relicsAdmin',
     *       'pages/admin/RelicsAdminPage.php', 'ShowRelicsAdminPage');
     *
     * admin.php will require the file and call $fn() when page=<pageName>.
     */
    public function registerAdminRoute(
        string $pluginId,
        string $pageName,
        string $relativeFile,
        string $functionName
    ): void {
        $absFile = $this->pluginDir($pluginId) . ltrim($relativeFile, '/');
        $this->adminRoutes[$pageName] = ['file' => $absFile, 'fn' => $functionName];
    }

    /**
     * Dispatch an ingame page route registered by a plugin.
     * Returns true if the route was handled (caller must exit after).
     * Returns false if no plugin handles this page name.
     */
    public function dispatchPageRoute(string $pageName): bool
    {
        if (!isset($this->pageRoutes[$pageName])) {
            return false;
        }
        $route = $this->pageRoutes[$pageName];
        if (!file_exists($route['file'])) {
            error_log('[PluginManager] Plugin page file not found: ' . $route['file']);
            return false;
        }
        require_once $route['file'];
        $class = $route['class'];
        if (!class_exists($class)) {
            error_log('[PluginManager] Plugin page class not found: ' . $class);
            return false;
        }
        $obj = new $class();
        $mode = \HTTP::_GP('mode', 'show');
        if (!is_callable([$obj, $mode])) {
            $props = get_class_vars($class);
            $mode = $props['defaultController'] ?? 'show';
        }
        if (is_callable([$obj, $mode])) {
            $obj->{$mode}();
        }
        return true;
    }

    /**
     * Dispatch an admin page route registered by a plugin.
     * Returns true if handled, false if not found.
     */
    public function dispatchAdminRoute(string $pageName): bool
    {
        if (!isset($this->adminRoutes[$pageName])) {
            return false;
        }
        $route = $this->adminRoutes[$pageName];
        if (!file_exists($route['file'])) {
            error_log('[PluginManager] Plugin admin file not found: ' . $route['file']);
            return false;
        }
        require_once $route['file'];
        $fn = $route['fn'];
        if (!function_exists($fn)) {
            error_log('[PluginManager] Plugin admin function not found: ' . $fn);
            return false;
        }
        $fn();
        return true;
    }

    // ── Plugin Twig Namespaces ───────────────────────────────────────────────

    /**
     * plugin id → absolute template directory
     * @var array<string, string>
     */
    private array $twigNamespaces = [];

    /**
     * Register a Twig template namespace for a plugin.
     * Called from plugin.php:
     *   PluginManager::get()->registerTwigNamespace('sm-relics', 'templates');
     *
     * Templates can then be referenced as @sm-relics/game/relics.twig
     */
    public function registerTwigNamespace(string $pluginId, string $relativeDir = 'templates'): void
    {
        $absDir = $this->pluginDir($pluginId) . ltrim($relativeDir, '/');
        $this->twigNamespaces[$pluginId] = $absDir;
    }

    /**
     * Return all registered Twig namespaces: pluginId → absDir
     * @return array<string, string>
     */
    public function getTwigNamespaces(): array
    {
        return $this->twigNamespaces;
    }

    // ── Plugin Cronjob Paths ─────────────────────────────────────────────────

    /**
     * class name → absolute file path
     * @var array<string, string>
     */
    private array $cronjobPaths = [];

    /**
     * Register a plugin cronjob class file.
     * Called from plugin.php:
     *   PluginManager::get()->registerCronjob('sm-relics', 'RelicsTick',
     *       'cron/RelicsTick.php');
     *
     * Cronjob::execute() will find this path when the DB row has class=RelicsTick.
     */
    public function registerCronjob(string $pluginId, string $className, string $relativeFile): void
    {
        $absFile = $this->pluginDir($pluginId) . ltrim($relativeFile, '/');
        $this->cronjobPaths[$className] = $absFile;
    }

    /**
     * Resolve a cronjob class name to its file path.
     * Returns null if not registered by any plugin (fall back to core path).
     */
    public function resolveCronjobPath(string $className): ?string
    {
        return $this->cronjobPaths[$className] ?? null;
    }

    // ── Load active plugins ──────────────────────────────────────────────────

    /** @var array<string, callable> plugin id → registerElements callback */
    private array $elementCallbacks = [];

    /** @var array<string, string[]> plugin id → list of module file paths (relative to plugin dir) */
    private array $moduleFiles = [];

    /**
     * Called from a plugin's bootstrap (plugin.php) to register a
     * registerElements() callback for Plugin System v1.2.
     *
     * Usage in plugin.php:
     *   PluginManager::get()->registerElementsCallback('my-plugin', function(ElementRegistry $r): void {
     *       $r->register([...]);
     *   });
     */
    public function registerElementsCallback(string $pluginId, callable $callback): void
    {
        $this->elementCallbacks[$pluginId] = $callback;
    }

    /**
     * Dispatch registerElements() to all active plugins that registered a callback.
     * Called from common.php after bootFromLegacyArrays() has been called.
     * Safe to call even when no plugin registered a callback (no-op).
     */
    public function dispatchRegisterElements(ElementRegistry $registry): void
    {
        foreach ($this->elementCallbacks as $pluginId => $callback) {
            try {
                $callback($registry);
            } catch (Throwable $e) {
                error_log('[PluginManager] registerElements error in plugin "' . $pluginId . '": ' . $e->getMessage());
            }
        }
    }

    // ── Safe Mode ─────────────────────────────────────────────────────────────

    /**
     * Return the absolute path to the safe-mode lock file.
     */
    public static function safeModeLocKPath(): string
    {
        return ROOT_PATH . self::SAFE_MODE_LOCK;
    }

    /**
     * Return true if the safe-mode lock file is present.
     * When locked, all plugins are skipped until the admin clears the lock.
     */
    public function isSafeModeLocked(): bool
    {
        return file_exists(self::safeModeLocKPath());
    }

    /**
     * Write the safe-mode lock file.
     * Content is a JSON summary of what triggered the lock.
     */
    public function writeSafeModeLock(string $reason): void
    {
        $path = self::safeModeLocKPath();
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($path, json_encode([
            'time'   => date('Y-m-d H:i:s'),
            'reason' => $reason,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Delete the safe-mode lock file (admin clears it).
     */
    public function clearSafeModeLock(): void
    {
        $path = self::safeModeLocKPath();
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Read the safe-mode lock file contents (for display).
     * Returns null if not locked.
     */
    public function getSafeModeLockInfo(): ?array
    {
        $path = self::safeModeLocKPath();
        if (!file_exists($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return ['time' => '?', 'reason' => '?'];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : ['time' => '?', 'reason' => $raw];
    }

    /**
     * Record a safe-mode notice (plugin or module crash → auto-deactivated).
     *
     * @param string $pluginId  The plugin that was deactivated
     * @param string $reason    Human-readable error summary
     * @param string $type      'plugin' or 'module'
     */
    public function addSafeModeNotice(string $pluginId, string $reason, string $type = 'plugin'): void
    {
        $this->safeModeNotices[] = [
            'plugin' => $pluginId,
            'reason' => $reason,
            'type'   => $type,
        ];
    }

    /**
     * Return all safe-mode notices collected this request.
     *
     * @return array<int, array{plugin: string, reason: string, type: string}>
     */
    public function getSafeModeNotices(): array
    {
        return $this->safeModeNotices;
    }

    /**
     * Auto-deactivate a plugin in the DB due to a crash.
     * Logs the event, records a notice, and optionally writes the lock file.
     *
     * @param string $id      Plugin id
     * @param string $reason  Error message
     * @param string $type    'plugin' or 'module'
     * @param bool   $lock    Whether to also write the safe-mode lock file
     */
    public function safeDeactivate(string $id, string $reason, string $type = 'plugin', bool $lock = false): void
    {
        error_log('[PluginManager][SafeMode] Auto-deactivating plugin "' . $id . '" (' . $type . '): ' . $reason);

        // Guard: never attempt DB deactivation if DB is not ready
        try {
            $this->deactivate($id);
        } catch (Throwable $dbErr) {
            error_log('[PluginManager][SafeMode] DB deactivate failed for "' . $id . '": ' . $dbErr->getMessage());
            // If deactivation itself fails, write lock to prevent infinite crash loop
            $lock = true;
        }

        $this->addSafeModeNotice($id, $reason, $type);

        if ($lock) {
            $this->writeSafeModeLock('Plugin "' . $id . '" crashed and DB deactivation failed: ' . $reason);
        }
    }

    /**
     * Load all active plugins: include their bootstrap, register hooks/assets/lang.
     * Called once from common.php after DB is ready.
     *
     * Safe-Mode behaviour:
     *  - If the lock file is present, all plugins are skipped.
     *  - If a plugin bootstrap throws, it is auto-deactivated and loading continues.
     */
    public function loadActivePlugins(): void
    {
        // ── Safe-Mode lock check ──────────────────────────────────────────────
        if ($this->isSafeModeLocked()) {
            $info = $this->getSafeModeLockInfo();
            $reason = $info['reason'] ?? 'unknown';
            error_log('[PluginManager][SafeMode] Lock file present – all plugins skipped. Reason: ' . $reason);
            $this->addSafeModeNotice(
                'ALL',
                'Safe-Mode Lock aktiv (seit ' . ($info['time'] ?? '?') . '): ' . $reason . '. Alle Plugins übersprungen.',
                'lock'
            );
            return;
        }

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

            // Load language (non-fatal if it fails)
            try {
                $this->loadLanguage($id);
            } catch (Throwable $e) {
                error_log('[PluginManager] Language load error in plugin "' . $id . '": ' . $e->getMessage());
            }

            // Include plugin bootstrap – auto-deactivate on crash
            $bootstrap = $dir . 'plugin.php';
            if (file_exists($bootstrap)) {
                try {
                    require_once $bootstrap;
                } catch (Throwable $e) {
                    $msg = get_class($e) . ': ' . $e->getMessage()
                         . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
                    $this->safeDeactivate($id, $msg, 'plugin');
                    continue; // Skip module loading for this plugin too
                }
            }

            // Load v2 modules declared in manifest["modules"]
            $this->loadPluginModules($id, $row);

            $this->loadedPlugins[$id] = $row;
        }
    }

    // ── v2 Module support ────────────────────────────────────────────────────

    /**
     * Load module files declared in a plugin's manifest and register them
     * with ModuleManager.  Called during loadActivePlugins().
     *
     * Manifest format:
     *   "modules": ["modules/MyModule.php"]
     *
     * Each file must define a class that implements GameModuleInterface.
     * The class name is derived from the filename (without .php extension).
     *
     * @param string              $id   Plugin id
     * @param array<string,mixed> $row  DB row (unused, reserved for future use)
     */
    private function loadPluginModules(string $id, array $row): void
    {
        $dir = $this->pluginDir($id);
        $manifestPath = $dir . self::MANIFEST;

        if (!file_exists($manifestPath)) {
            return;
        }

        $raw = file_get_contents($manifestPath);
        if ($raw === false) {
            return;
        }

        $manifest = json_decode($raw, true);
        if (!is_array($manifest) || empty($manifest['modules']) || !is_array($manifest['modules'])) {
            return;
        }

        if (!class_exists('ModuleManager') || !interface_exists('GameModuleInterface')) {
            return;
        }

        foreach ($manifest['modules'] as $relPath) {
            if (!is_string($relPath) || $relPath === '') {
                continue;
            }

            $absPath = $dir . ltrim($relPath, '/');

            if (!file_exists($absPath)) {
                error_log('[PluginManager] Module file not found for plugin "' . $id . '": ' . $absPath);
                continue;
            }

            try {
                require_once $absPath;

                // Derive class name from filename (strip .php, use basename)
                $className = basename($relPath, '.php');

                if (!class_exists($className)) {
                    error_log('[PluginManager] Module class "' . $className . '" not found after including ' . $absPath);
                    continue;
                }

                if (!in_array('GameModuleInterface', class_implements($className) ?: [], true)) {
                    error_log('[PluginManager] Class "' . $className . '" does not implement GameModuleInterface — skipped');
                    continue;
                }

                /** @var GameModuleInterface $moduleObj */
                $moduleObj = new $className();
                ModuleManager::get()->register($moduleObj, 100);

                $this->moduleFiles[$id][] = $relPath;

            } catch (Throwable $e) {
                $msg = get_class($e) . ': ' . $e->getMessage()
                     . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
                $this->safeDeactivate($id, 'Module "' . basename($relPath) . '" crash: ' . $msg, 'module');
                // Stop loading further modules from this plugin too
                break;
            }
        }
    }

    /**
     * Return the list of module relative paths loaded for a given plugin id.
     *
     * @return string[]
     */
    public function getLoadedModuleFiles(string $pluginId): array
    {
        return $this->moduleFiles[$pluginId] ?? [];
    }

    /**
     * Return all successfully loaded plugins keyed by id.
     * Used by ModuleManager to resolve plugin ownership for safe-mode deactivation.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getLoadedPlugins(): array
    {
        return $this->loadedPlugins;
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

            // Apply %%TABLE%% → real table name replacement (same as Database::_query)
            $tableNames = $db->getDbTableNames();
            if (!empty($tableNames['keys'])) {
                $sql = str_replace($tableNames['keys'], $tableNames['names'], $sql);
            }

            // Split on semicolons, strip leading comment lines, skip empty results
            $statements = array_filter(
                array_map(static function(string $s): string {
                    // Remove leading -- comment lines so they don't cause the
                    // statement to be mistakenly treated as a comment block
                    $lines = array_filter(
                        explode("\n", $s),
                        static fn(string $l): bool => !str_starts_with(trim($l), '--')
                    );
                    return trim(implode("\n", $lines));
                }, explode(';', $sql)),
                static fn(string $s): bool => $s !== ''
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
