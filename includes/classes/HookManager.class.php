<?php

declare(strict_types=1);

/**
 * HookManager – Plugin System v1
 * WoltLab-style action/filter hook system with priority support.
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

        return $value;
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
