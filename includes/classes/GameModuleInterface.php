<?php

declare(strict_types=1);

/**
 * GameModuleInterface – v2 Full Modular Gameplay Engine
 *
 * Every module (core wrapper or plugin-supplied) must implement this interface.
 * Modules are loaded by ModuleManager and called at defined lifecycle points.
 *
 * Lifecycle order per request:
 *   1. boot()          – once on first load (register hooks, set up state)
 *   2. beforeRequest() – after USER/PLANET are available, before page logic
 *   3. afterRequest()  – after page logic, before output (via afterController hook)
 *
 * Modules may also call registerHooks() to attach to HookManager during boot().
 */
interface GameModuleInterface
{
    /**
     * Unique machine-readable identifier, e.g. "core.production".
     * Must be stable across requests (used as array key and for config lookup).
     */
    public function getId(): string;

    /**
     * Called once when the module is first loaded.
     * Use this to register HookManager hooks, initialise state, etc.
     * Must be idempotent (safe to call multiple times).
     *
     * @param GameContext $ctx  Shared request context
     */
    public function boot(GameContext $ctx): void;

    /**
     * Called after USER and PLANET globals are ready, before the page controller runs.
     * Suitable for injecting template variables, modifying globals, etc.
     *
     * @param GameContext $ctx  Shared request context
     */
    public function beforeRequest(GameContext $ctx): void;

    /**
     * Called after the page controller has finished (via afterController hook).
     * Suitable for cleanup, logging, post-processing.
     *
     * @param GameContext $ctx  Shared request context
     */
    public function afterRequest(GameContext $ctx): void;

    /**
     * Whether this module is currently enabled.
     * ModuleManager will skip disabled modules entirely (no boot/before/after).
     * Implementations may check a config value, DB flag, or constant.
     */
    public function isEnabled(): bool;
}
