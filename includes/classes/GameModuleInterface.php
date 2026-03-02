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
