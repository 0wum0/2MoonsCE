<?php

declare(strict_types=1);

/**
 * GameContext – v2 Full Modular Gameplay Engine
 *
 * Lightweight value-object passed to every module lifecycle method.
 * Holds references to the core singletons and the current request's
 * USER/PLANET globals so modules never need to reach into $GLOBALS directly.
 *
 * All properties are public for simplicity; modules should treat them as
 * read-only unless they have a specific reason to mutate shared state.
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
