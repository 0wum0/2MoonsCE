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
class HookManager
{
    private static ?HookManager $instance = null;

    /** @var array<string, array<int, array<int, callable>>> */
    private array $actions = [];

    /** @var array<string, array<int, array<int, callable>>> */
    private array $filters = [];

    private function __construct() {}
    private function __clone() {}

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][$priority][] = $callback;
    }

    public function doAction(string $hook, array $context = []): void
    {
        if (empty($this->actions[$hook])) {
            return;
        }

        ksort($this->actions[$hook]);

        foreach ($this->actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $callback($context);
                } catch (Throwable $e) {
                    error_log('[HookManager] Action "' . $hook . '" error: ' . $e->getMessage());
                }
            }
        }
    }

    // ── Filters ──────────────────────────────────────────────────────────────

    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][$priority][] = $callback;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function applyFilters(string $hook, mixed $value, array $context = []): mixed
    {
        if (empty($this->filters[$hook])) {
            return $value;
        }

        ksort($this->filters[$hook]);

        foreach ($this->filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $value = $callback($value, $context);
                } catch (Throwable $e) {
                    error_log('[HookManager] Filter "' . $hook . '" error: ' . $e->getMessage());
                }
            }
        }

        if ($hook === 'game.buildTime') {
            $value = max(1, (int) round((float) $value));
        }

        return $value;
    }

    // ── Debug / Introspection ─────────────────────────────────────────────────

    /**
     * Return the full actions registry for read-only inspection.
     * Structure: array<hookName, array<priority, callable[]>>
     *
     * @return array<string, array<int, array<int, callable>>>
     */
    public function getRegisteredActions(): array
    {
        return $this->actions;
    }

    /**
     * Return the full filters registry for read-only inspection.
     * Structure: array<hookName, array<priority, callable[]>>
     *
     * @return array<string, array<int, array<int, callable>>>
     */
    public function getRegisteredFilters(): array
    {
        return $this->filters;
    }

    /**
     * Produce a human-readable signature string for a callable.
     * Used by the debug panel to display callback info without exposing internals.
     */
    public static function callbackSignature(callable $callback): string
    {
        if (is_string($callback)) {
            return $callback . '()';
        }
        if (is_array($callback)) {
            $class  = is_object($callback[0]) ? get_class($callback[0]) : (string) $callback[0];
            return $class . '::' . $callback[1] . '()';
        }
        if ($callback instanceof Closure) {
            try {
                $ref  = new ReflectionFunction($callback);
                $file = basename($ref->getFileName() ?: '?');
                return 'Closure@' . $file . ':' . $ref->getStartLine();
            } catch (Throwable $e) {
                return 'Closure';
            }
        }
        if (is_object($callback) && method_exists($callback, '__invoke')) {
            return get_class($callback) . '::__invoke()';
        }
        return '(unknown)';
    }

    // ── Twig hook() helper ───────────────────────────────────────────────────

    /**
     * Collect and return HTML output from all action callbacks for a named hook.
     * Used by the Twig hook() function.
     */
    public function renderHook(string $hook, array $context = []): string
    {
        if (empty($this->actions[$hook])) {
            return '';
        }

        ksort($this->actions[$hook]);

        $output = '';
        foreach ($this->actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    ob_start();
                    $result = $callback($context);
                    $buffered = ob_get_clean();
                    if (is_string($result)) {
                        $output .= $result;
                    }
                    if (is_string($buffered) && $buffered !== '') {
                        $output .= $buffered;
                    }
                } catch (Throwable $e) {
                    ob_end_clean();
                    error_log('[HookManager] renderHook "' . $hook . '" error: ' . $e->getMessage());
                }
            }
        }

        return $output;
    }
}
