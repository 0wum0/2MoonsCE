<?php

declare(strict_types=1);

/**
 * ModuleManager – v2 Full Modular Gameplay Engine
 *
 * Loads, boots and dispatches lifecycle calls to all registered GameModuleInterface
 * implementations.  Modules are registered with a numeric priority (lower = earlier).
 *
 * Integration points in common.php (INGAME / ADMIN modes only):
 *   ModuleManager::get()->boot($ctx);          // after USER/PLANET are ready
 *   ModuleManager::get()->beforeRequest($ctx); // same spot, after boot
 *
 * afterRequest() is wired via the existing 'afterController' HookManager action
 * inside boot() itself, so no extra call-site is needed.
 *
 * Zero-cost when no modules are registered or all are disabled.
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
                error_log('[ModuleManager] boot() error in module "' . $module->getId() . '": ' . $e->getMessage());
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
                error_log('[ModuleManager] beforeRequest() error in module "' . $module->getId() . '": ' . $e->getMessage());
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
                error_log('[ModuleManager] afterRequest() error in module "' . $module->getId() . '": ' . $e->getMessage());
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
