<?php

declare(strict_types=1);

/**
 * GalaxyMarkerRegistry – In-memory store for runtime-registered markers.
 *
 * Other plugins push markers via the galaxy.registerMarker action hook.
 * These markers live only for the current request (no DB persistence).
 * Use GalaxyMarkerDb::upsertMarker() for persistent markers.
 */
class GalaxyMarkerRegistry
{
    private static ?GalaxyMarkerRegistry $instance = null;

    /** @var array<int, array<string,mixed>> */
    private array $markers = [];

    private function __construct() {}
    private function __clone() {}

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Push a marker into the runtime registry.
     *
     * Expected keys: galaxy, system, position, type, icon, color, tooltip, expires_at
     * All keys are optional with sensible defaults.
     *
     * @param array<string,mixed> $markerData
     */
    public function push(array $markerData): void
    {
        $this->markers[] = [
            'galaxy'     => (int)($markerData['galaxy']     ?? 1),
            'system'     => (int)($markerData['system']     ?? 1),
            'position'   => (int)($markerData['position']   ?? 1),
            'type'       => (string)($markerData['type']    ?? 'info'),
            'icon'       => (string)($markerData['icon']    ?? 'fa-map-marker-alt'),
            'color'      => (string)($markerData['color']   ?? '#38bdf8'),
            'tooltip'    => (string)($markerData['tooltip'] ?? ''),
            'expires_at' => (int)($markerData['expires_at'] ?? 0),
            'runtime'    => true,
        ];
    }

    /**
     * Return all runtime markers for this request.
     * @return array<int, array<string,mixed>>
     */
    public function all(): array
    {
        return $this->markers;
    }

    public function clear(): void
    {
        $this->markers = [];
    }
}
