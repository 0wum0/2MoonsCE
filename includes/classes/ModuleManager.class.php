<?php

declare(strict_types=1);

/**
 *	SmartMoons / 2Moons Community Edition (2MoonsCE)
 * 
 *	Based on the original 2Moons project:
 *	
 * @copyright 2009 Lucky
 * @copyright 2016 Jan-Otto Kröpke <slaver7@gmail.com>
 * @licence MIT
 * @version 1.8.0
 * @link https://github.com/jkroepke/2Moons
 *  2Moons 
 *   by Jan-Otto Kröpke 2009-2016
 *
 * Modernization, PHP 8.3/8.4 compatibility, Twig Migration (Smarty removed)
 * Refactoring and feature extensions:
 * @copyright 2024-2026 Florian Engelhardt (0wum0)
 * @link https://github.com/0wum0/2MoonsCE
 * @eMail info.browsergame@gmail.com
 * 
 * Licensed under the MIT License.
 * See LICENSE for details.
 * @visit http://makeit.uno/
 */
class ModuleManager
{
    private static ?ModuleManager $instance = null;

    /**
     * Registered modules, keyed by id, value = [module, priority].
     * @var array<string, array{module: GameModuleInterface, priority: int}>
     */
    private array $registry = [];

    /** Sorted module list (rebuilt lazily when $dirty = true) */
    private array $sorted = [];
    private bool  $dirty  = true;

    /** Whether boot() has already been called this request */
    private bool $booted = false;

    /** Shared context for this request */
    private ?GameContext $ctx = null;

    private function __construct() {}
    private function __clone() {}

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Registration ─────────────────────────────────────────────────────────

    /**
     * Register a module.  Safe to call multiple times with the same id
     * (second call replaces the first).
     *
     * @param GameModuleInterface $module
     * @param int                 $priority  Lower = boots/runs earlier (default 50)
     */
    public function register(GameModuleInterface $module, int $priority = 50): void
    {
        $id = $module->getId();
        $this->registry[$id] = ['module' => $module, 'priority' => $priority];
        $this->dirty = true;
    }

    /**
     * Deregister a module by id.  No-op if not registered.
     */
    public function deregister(string $id): void
    {
        unset($this->registry[$id]);
        $this->dirty = true;
    }

    /**
     * Check whether a module id is registered (regardless of enabled state).
     */
    public function has(string $id): bool
    {
        return isset($this->registry[$id]);
    }

    // ── Sorted access ────────────────────────────────────────────────────────

    /**
     * Returns enabled modules sorted by priority ascending.
     *
     * @return GameModuleInterface[]
     */
    private function enabledModules(): array
    {
        if ($this->dirty) {
            $list = $this->registry;
            uasort($list, static fn($a, $b) => $a['priority'] <=> $b['priority']);
            $this->sorted = array_column($list, 'module');
            $this->dirty  = false;
        }

        return array_filter(
            $this->sorted,
            static fn(GameModuleInterface $m) => $m->isEnabled()
        );
    }

    // ── Safe Mode helpers ─────────────────────────────────────────────────────

    /**
     * Handle a module lifecycle crash:
     *  1. Deregister the module so it won't run again this request.
     *  2. If it is a plugin module (priority > 10), ask PluginManager to
     *     auto-deactivate the owning plugin in the DB.
     *  3. Core modules (priority ≤ 10) are NEVER deactivated – only logged.
     *
     * @param GameModuleInterface $module  The crashing module
     * @param string              $phase   'boot', 'beforeRequest', or 'afterRequest'
     * @param Throwable           $e       The caught exception/error
     */
    private function handleModuleCrash(GameModuleInterface $module, string $phase, Throwable $e): void
    {
        $id       = $module->getId();
        $priority = $this->registry[$id]['priority'] ?? 0;
        $msg      = get_class($e) . ': ' . $e->getMessage()
                  . ' in ' . basename($e->getFile()) . ':' . $e->getLine();

        error_log('[ModuleManager][SafeMode] ' . $phase . '() crash in module "' . $id . '" (priority=' . $priority . '): ' . $msg);

        // Always deregister so it won't run again this request
        $this->deregister($id);

        // Only auto-deactivate plugin modules (priority > 10)
        if ($priority > 10 && class_exists('PluginManager')) {
            // Derive the owning plugin id: module id format is "pluginId.moduleName"
            // Fall back to searching loadedPlugins via moduleFiles.
            $owningPlugin = $this->resolveOwningPlugin($id);
            if ($owningPlugin !== null) {
                PluginManager::get()->safeDeactivate(
                    $owningPlugin,
                    'Module "' . $id . '" crashed in ' . $phase . '(): ' . $msg,
                    'module'
                );
            } else {
                // Can't identify owner – just log, don't deactivate blindly
                error_log('[ModuleManager][SafeMode] Cannot identify owning plugin for module "' . $id . '" – skipping auto-deactivate.');
            }
        }
        // Core modules: log only, never deactivate
    }

    /**
     * Resolve the plugin id that owns a given module id.
     *
     * Strategy:
     *  1. Module id often follows "pluginId.moduleName" convention → try prefix.
     *  2. Fall back to scanning PluginManager::getLoadedModuleFiles().
     *
     * Returns null if the owning plugin cannot be determined.
     */
    private function resolveOwningPlugin(string $moduleId): ?string
    {
        if (!class_exists('PluginManager')) {
            return null;
        }
        $pm = PluginManager::get();

        // Strategy 1: "pluginId.something" convention
        if (str_contains($moduleId, '.')) {
            $candidate = explode('.', $moduleId, 2)[0];
            // Verify the candidate is actually a loaded plugin
            $loaded = $pm->getLoadedPlugins();
            if (isset($loaded[$candidate])) {
                return $candidate;
            }
        }

        // Strategy 2: scan moduleFiles for all loaded plugins
        $loaded = $pm->getLoadedPlugins();
        foreach (array_keys($loaded) as $pluginId) {
            $files = $pm->getLoadedModuleFiles($pluginId);
            foreach ($files as $relPath) {
                $className = basename($relPath, '.php');
                // Try to match by class name against module id suffix
                if (str_ends_with($moduleId, '.' . strtolower($className))
                    || strtolower($className) === strtolower($moduleId)) {
                    return $pluginId;
                }
            }
        }

        return null;
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Boot all enabled modules and wire afterRequest() into the afterController hook.
     * Must be called once per request after USER/PLANET globals are populated.
     * Subsequent calls within the same request are no-ops.
     */
    public function boot(GameContext $ctx): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;
        $this->ctx    = $ctx;

        foreach ($this->enabledModules() as $module) {
            try {
                $module->boot($ctx);
            } catch (Throwable $e) {
                $this->handleModuleCrash($module, 'boot', $e);
            }
        }

        // Wire afterRequest() to the existing afterController action hook so we
        // don't need an extra call-site in every page controller.
        HookManager::get()->addAction('afterController', function (array $hookCtx) use ($ctx): void {
            $this->afterRequest($ctx);
        }, 200);
    }

    /**
     * Dispatch beforeRequest() to all enabled modules.
     * Call after boot() and after USER/PLANET are fully populated.
     */
    public function beforeRequest(GameContext $ctx): void
    {
        foreach ($this->enabledModules() as $module) {
            try {
                $module->beforeRequest($ctx);
            } catch (Throwable $e) {
                $this->handleModuleCrash($module, 'beforeRequest', $e);
            }
        }
    }

    /**
     * Dispatch afterRequest() to all enabled modules.
     * Called automatically via the afterController hook registered in boot().
     */
    public function afterRequest(GameContext $ctx): void
    {
        foreach ($this->enabledModules() as $module) {
            try {
                $module->afterRequest($ctx);
            } catch (Throwable $e) {
                $this->handleModuleCrash($module, 'afterRequest', $e);
            }
        }
    }

    // ── Introspection ────────────────────────────────────────────────────────

    /**
     * Return all registered module ids (enabled or not).
     *
     * @return string[]
     */
    public function getRegisteredIds(): array
    {
        return array_keys($this->registry);
    }

    /**
     * Return the shared GameContext for this request (null before boot()).
     */
    public function getContext(): ?GameContext
    {
        return $this->ctx;
    }
}
