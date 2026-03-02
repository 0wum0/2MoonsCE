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
class GameContext
{
    /** Current authenticated user row (array from %%USERS%%) or empty array in CRON/LOGIN mode */
    public array $user = [];

    /** Current active planet row (array from %%PLANETS%%) or empty array when not in INGAME mode */
    public array $planet = [];

    /** Current game mode: 'INGAME' | 'ADMIN' | 'LOGIN' | 'CRON' | 'INSTALL' | 'CHAT' */
    public string $mode = '';

    /** Unix timestamp of the current request (= TIMESTAMP constant) */
    public int $time = 0;

    /** Whether this is an AJAX sub-request */
    public bool $isAjax = false;

    /** Arbitrary key→value bag for modules to share data without coupling */
    private array $bag = [];

    // ── Factory ──────────────────────────────────────────────────────────────

    /**
     * Build a GameContext from the current global state.
     * Called once from ModuleManager::boot() after common.php has set up globals.
     */
    public static function fromGlobals(): self
    {
        $ctx = new self();
        $ctx->mode   = defined('MODE') ? MODE : '';
        $ctx->time   = defined('TIMESTAMP') ? TIMESTAMP : time();
        $ctx->isAjax = defined('AJAX_REQUEST') && (bool) AJAX_REQUEST;

        if (isset($GLOBALS['USER']) && is_array($GLOBALS['USER'])) {
            $ctx->user = $GLOBALS['USER'];
        }
        if (isset($GLOBALS['PLANET']) && is_array($GLOBALS['PLANET'])) {
            $ctx->planet = $GLOBALS['PLANET'];
        }

        return $ctx;
    }

    // ── Shared bag ───────────────────────────────────────────────────────────

    /**
     * Store an arbitrary value in the shared bag.
     * Use namespaced keys to avoid collisions, e.g. "mymodule.someKey".
     *
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void
    {
        $this->bag[$key] = $value;
    }

    /**
     * Retrieve a value from the shared bag.
     *
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->bag[$key] ?? $default;
    }

    /**
     * Check whether a key exists in the shared bag.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->bag);
    }
}
